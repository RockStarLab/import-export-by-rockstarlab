<?php
/**
 * User Importer
 *
 * Handles importing WordPress users
 *
 * @package WP_AIE\Model\Import
 */

namespace WP_AIE\Model\Import;

/**
 * User Importer Class
 *
 * Imports WordPress users with support for:
 * - User meta
 * - User roles and capabilities
 * - ACF fields
 * - Social media profiles
 * - User avatar
 * - Duplicate handling
 *
 * @package WP_AIE\Model\Import
 */
class User_Importer extends Abstract_Importer {

	/**
	 * Get importer name
	 *
	 * @return string
	 */
	public function get_name() {
		return 'users';
	}

	/**
	 * Get importer description
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Import WordPress users with roles, meta, and ACF fields', 'wp-advanced-import-export' );
	}

	/**
	 * Get required fields
	 *
	 * @return array
	 */
	public function get_required_fields() {
		return [ 'user_login' ];
	}

	/**
	 * Get optional fields
	 *
	 * @return array
	 */
	public function get_optional_fields() {
		return [
			'ID',
			'user_email',
			'user_pass',
			'display_name',
			'user_nicename',
			'first_name',
			'last_name',
			'nickname',
			'description',
			'user_url',
			'user_registered',
			'role',
			'roles',
			'capabilities',
			'locale',
			'admin_color',
			'rich_editing',
			'avatar_url',
			'posts_count',
			// Social media fields
			'facebook',
			'instagram',
			'linkedin',
			'myspace',
			'pinterest',
			'soundcloud',
			'tumblr',
			'wikipedia',
			'twitter',
			'youtube',
		];
	}

	/**
	 * Get supported options
	 *
	 * @return array
	 */
	public function get_supported_options() {
		return [
			'duplicate_mode'     => 'How to handle duplicates: skip, update, create',
			'duplicate_check'    => 'Field to check for duplicates: ID, user_login, user_email',
			'default_role'       => 'Default role if not specified: subscriber, author, editor, etc.',
			'send_notification'  => 'Send new user email notification: true, false',
			'update_password'    => 'Update password on duplicate: true, false',
			'generate_password'  => 'Generate random password if not provided: true, false',
			'import_acf'         => 'Import ACF fields: true, false',
			'import_social'      => 'Import social media fields: true, false',
		];
	}

	/**
	 * Get default options
	 *
	 * @return array
	 */
	protected function get_default_options() {
		return array_merge(
			parent::get_default_options(),
			[
				'duplicate_check'    => 'user_login',
				'default_role'       => 'subscriber',
				'send_notification'  => false,
				'update_password'    => false,
				'generate_password'  => true,
				'import_acf'         => true,
				'import_social'      => true,
			]
		);
	}

	/**
	 * Set importer options
	 *
	 * Overrides parent to map UI field names to internal option names
	 *
	 * @param array $options Options to set
	 * @return void
	 */
	public function set_options( $options ) {
		// Map duplicate_handling (from UI) to duplicate_mode (used internally)
		if ( isset( $options['duplicate_handling'] ) && ! isset( $options['duplicate_mode'] ) ) {
			$options['duplicate_mode'] = $options['duplicate_handling'];
		}

		// Map if_exists (from UI) to duplicate_mode (used internally)
		if ( isset( $options['if_exists'] ) && ! isset( $options['duplicate_mode'] ) ) {
			$options['duplicate_mode'] = $options['if_exists'];
		}

		// Map unique_field (from UI) to duplicate_check (used internally)
		if ( isset( $options['unique_field'] ) && ! isset( $options['duplicate_check'] ) ) {
			$options['duplicate_check'] = $options['unique_field'];
		}

		parent::set_options( $options );
	}

	/**
	 * Prepare data with mapping
	 *
	 * @param array $data    Raw data from parser
	 * @param array $mapping Field mapping
	 * @return array Prepared data
	 */
	public function prepare( $data, $mapping = [] ) {
		$prepared = [];

		foreach ( $data as $row ) {
			$item = [];

			// Process mapping array
			foreach ( $mapping as $map ) {
				$source_field = $map['source_field'] ?? '';
				$target_field = $map['target_field'] ?? '';

				if ( empty( $target_field ) ) {
					continue;
				}

				// Get value from source
				$value = '';
				if ( isset( $map['source_index'] ) && isset( $row[ $map['source_index'] ] ) ) {
					$value = $row[ $map['source_index'] ];
				} elseif ( ! empty( $source_field ) && isset( $row[ $source_field ] ) ) {
					$value = $row[ $source_field ];
				}

				$item[ $target_field ] = $value;
			}

			$prepared[] = $item;
		}

		return $prepared;
	}

	/**
	 * Import single user
	 *
	 * @param array $item  User data
	 * @param int   $index Item index
	 * @return int|string|WP_Error User ID, 'skipped', 'updated', or WP_Error
	 */
	public function import_item( $item, $index ) {
		// Debug logging
		error_log( "User_Importer::import_item() - Index: $index" );
		error_log( "User_Importer::import_item() - Raw item: " . print_r( $item, true ) );
		
		// Sanitize data
		$item = $this->sanitize_item( $item );
		error_log( "User_Importer::import_item() - After sanitize: " . print_r( $item, true ) );

		// Check for existing user
		$existing_user = $this->find_existing_user( $item );
		error_log( "User_Importer::import_item() - Existing user found: " . ( $existing_user ? $existing_user->ID : 'no' ) );

		if ( $existing_user ) {
			$duplicate_mode = $this->get_option( 'duplicate_mode', 'skip' );
			error_log( "User_Importer::import_item() - Duplicate mode: $duplicate_mode" );
			error_log( "User_Importer::import_item() - All options: " . print_r( $this->options, true ) );

			if ( 'skip' === $duplicate_mode ) {
				return 'skipped';
			}

			if ( 'update' === $duplicate_mode ) {
				return $this->update_user( $existing_user->ID, $item );
			}

			// 'create' mode - fall through to create new user
		}

		// Create new user
		return $this->create_user( $item );
	}

	/**
	 * Find existing user
	 *
	 * @param array $item User data
	 * @return WP_User|false User object or false
	 */
	protected function find_existing_user( $item ) {
		$check_field = $this->get_option( 'duplicate_check', 'user_login' );

		switch ( $check_field ) {
			case 'ID':
				if ( ! empty( $item['ID'] ) ) {
					return get_user_by( 'id', $item['ID'] );
				}
				break;

			case 'user_email':
				if ( ! empty( $item['user_email'] ) ) {
					return get_user_by( 'email', $item['user_email'] );
				}
				break;

			case 'user_login':
			default:
				if ( ! empty( $item['user_login'] ) ) {
					return get_user_by( 'login', $item['user_login'] );
				}
				break;
		}

		return false;
	}

	/**
	 * Create new user
	 *
	 * @param array $item User data
	 * @return int|WP_Error User ID or WP_Error
	 */
	protected function create_user( $item ) {
		// Prepare user data
		$userdata = $this->prepare_userdata( $item );

		// Validate required field: user_login
		if ( empty( $userdata['user_login'] ) ) {
			return new \WP_Error(
				'missing_user_login',
				__( 'User login is required for creating a new user', 'wp-advanced-import-export' )
			);
		}

		// Generate password if needed (wp_insert_user will hash it automatically)
		if ( empty( $userdata['user_pass'] ) && $this->get_option( 'generate_password', true ) ) {
			$userdata['user_pass'] = wp_generate_password( 12, true, true );
		}

		// Validate email
		if ( ! empty( $userdata['user_email'] ) && ! is_email( $userdata['user_email'] ) ) {
			return new \WP_Error(
				'invalid_email',
				sprintf(
					/* translators: %s: email address */
					__( 'Invalid email address: %s', 'wp-advanced-import-export' ),
					$userdata['user_email']
				)
			);
		}

		// Create user (password will be hashed automatically by wp_insert_user)
		$user_id = wp_insert_user( $userdata );

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		// Import user meta
		$this->import_user_meta( $user_id, $item );

		// Import ACF fields
		if ( $this->get_option( 'import_acf', true ) ) {
			$this->import_acf_fields( $user_id, $item );
		}

		// Import social media fields
		if ( $this->get_option( 'import_social', true ) ) {
			$this->import_social_fields( $user_id, $item );
		}

		// Send notification
		if ( $this->get_option( 'send_notification', false ) ) {
			wp_send_new_user_notifications( $user_id, 'both' );
		}

		$this->log_info( sprintf( 'Created user: %s (ID: %d)', $userdata['user_login'], $user_id ) );

		return 'created';
	}

	/**
	 * Update existing user
	 *
	 * @param int   $user_id User ID
	 * @param array $item    User data
	 * @return string|WP_Error 'updated' or WP_Error
	 */
	protected function update_user( $user_id, $item ) {
		// Get existing user to preserve required fields
		$existing_user = get_user_by( 'id', $user_id );
		if ( ! $existing_user ) {
			return new \WP_Error(
				'user_not_found',
				sprintf(
					/* translators: %d: user ID */
					__( 'User with ID %d not found', 'wp-advanced-import-export' ),
					$user_id
				)
			);
		}

		// Prepare user data
		$userdata = $this->prepare_userdata( $item );
		$userdata['ID'] = $user_id;

		// Ensure user_login is set (required by WordPress)
		if ( empty( $userdata['user_login'] ) ) {
			$userdata['user_login'] = $existing_user->user_login;
		}

		// Validate email if provided
		if ( ! empty( $userdata['user_email'] ) && ! is_email( $userdata['user_email'] ) ) {
			return new \WP_Error(
				'invalid_email',
				sprintf(
					/* translators: %s: email address */
					__( 'Invalid email address: %s', 'wp-advanced-import-export' ),
					$userdata['user_email']
				)
			);
		}

		// Don't update password unless explicitly allowed (security measure)
		// Password will be hashed automatically by wp_update_user if provided
		if ( ! $this->get_option( 'update_password', false ) ) {
			unset( $userdata['user_pass'] );
		}

		// Update user
		$result = wp_update_user( $userdata );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Import user meta
		$this->import_user_meta( $user_id, $item );

		// Import ACF fields
		if ( $this->get_option( 'import_acf', true ) ) {
			$this->import_acf_fields( $user_id, $item );
		}

		// Import social media fields
		if ( $this->get_option( 'import_social', true ) ) {
			$this->import_social_fields( $user_id, $item );
		}

		$this->log_info( sprintf( 'Updated user ID: %d', $user_id ) );

		return 'updated';
	}

	/**
	 * Prepare userdata for wp_insert_user/wp_update_user
	 *
	 * @param array $item Raw user data
	 * @return array Prepared userdata
	 */
	protected function prepare_userdata( $item ) {
		$userdata = [];

		// Core user fields
		$core_fields = [
			'user_login',
			'user_pass',
			'user_email',
			'user_url',
			'display_name',
			'nickname',
			'first_name',
			'last_name',
			'description',
			'user_nicename',
			'user_registered',
			'locale',
			'rich_editing',
		];

		foreach ( $core_fields as $field ) {
			if ( isset( $item[ $field ] ) && '' !== $item[ $field ] ) {
				$userdata[ $field ] = $item[ $field ];
			}
		}

		// Handle role
		if ( ! empty( $item['role'] ) ) {
			$userdata['role'] = $item['role'];
		} elseif ( ! empty( $item['roles'] ) ) {
			// Handle 'roles' field (could be array or comma-separated string)
			if ( is_array( $item['roles'] ) ) {
				$userdata['role'] = reset( $item['roles'] ); // Use first role
			} elseif ( is_string( $item['roles'] ) ) {
				// Could be comma-separated or JSON
				$roles = $this->parse_roles( $item['roles'] );
				if ( ! empty( $roles ) ) {
					$userdata['role'] = reset( $roles );
				} else {
					$userdata['role'] = $this->get_option( 'default_role', 'subscriber' );
				}
			}
		} else {
			$userdata['role'] = $this->get_option( 'default_role', 'subscriber' );
		}

		// Handle rich_editing (convert string to boolean)
		if ( isset( $userdata['rich_editing'] ) ) {
			$userdata['rich_editing'] = filter_var( $userdata['rich_editing'], FILTER_VALIDATE_BOOLEAN ) ? 'true' : 'false';
		}

		return $userdata;
	}

	/**
	 * Import user meta
	 *
	 * @param int   $user_id User ID
	 * @param array $item    User data
	 */
	protected function import_user_meta( $user_id, $item ) {
		// User meta fields
		$meta_fields = [
			'admin_color',
		];

		foreach ( $meta_fields as $meta_key ) {
			if ( isset( $item[ $meta_key ] ) && '' !== $item[ $meta_key ] ) {
				update_user_meta( $user_id, $meta_key, $item[ $meta_key ] );
			}
		}

		// Handle capabilities
		if ( ! empty( $item['capabilities'] ) ) {
			$capabilities = $this->parse_capabilities( $item['capabilities'] );
			if ( is_array( $capabilities ) && ! empty( $capabilities ) ) {
				update_user_meta( $user_id, 'wp_capabilities', $capabilities );
			}
		}
	}

	/**
	 * Import ACF fields
	 *
	 * @param int   $user_id User ID
	 * @param array $item    User data
	 */
	protected function import_acf_fields( $user_id, $item ) {
		// Look for ACF fields (fields starting with 'acf_')
		foreach ( $item as $key => $value ) {
			if ( strpos( $key, 'acf_' ) === 0 && '' !== $value ) {
				// Remove 'acf_' prefix
				$field_name = substr( $key, 4 );
				
				// Prepare value for ACF
				$acf_value = $this->prepare_acf_value( $value );
				
				// Update ACF field
				if ( function_exists( 'update_field' ) ) {
					update_field( $field_name, $acf_value, 'user_' . $user_id );
				} else {
					// Fallback to user meta
					update_user_meta( $user_id, $field_name, $acf_value );
				}
			}
		}
	}

	/**
	 * Prepare ACF value for import
	 *
	 * @param mixed $value Raw value from import
	 * @return mixed Prepared value
	 */
	protected function prepare_acf_value( $value ) {
		// If already an array, return as is
		if ( is_array( $value ) ) {
			return $value;
		}

		// Try to decode JSON
		if ( is_string( $value ) ) {
			$decoded = json_decode( $value, true );
			if ( json_last_error() === JSON_ERROR_NONE ) {
				return $decoded;
			}

			// Try to unserialize (for legacy serialized data)
			$unserialized = @unserialize( $value );
			if ( $unserialized !== false || $value === 'b:0;' ) {
				return $unserialized;
			}
		}

		// Return as is (simple value)
		return $value;
	}

	/**
	 * Import social media fields
	 *
	 * @param int   $user_id User ID
	 * @param array $item    User data
	 */
	protected function import_social_fields( $user_id, $item ) {
		$social_fields = [
			'facebook',
			'instagram',
			'linkedin',
			'myspace',
			'pinterest',
			'soundcloud',
			'tumblr',
			'wikipedia',
			'twitter',
			'youtube',
		];

		foreach ( $social_fields as $field ) {
			if ( isset( $item[ $field ] ) && '' !== $item[ $field ] ) {
				update_user_meta( $user_id, $field, $item[ $field ] );
			}
		}

		// Handle avatar URL (if provided)
		if ( ! empty( $item['avatar_url'] ) ) {
			update_user_meta( $user_id, 'avatar_url', $item['avatar_url'] );
		}
	}

	/**
	 * Parse capabilities from JSON string
	 *
	 * @param string $capabilities JSON string of capabilities
	 * @return array|false Array of capabilities or false
	 */
	protected function parse_capabilities( $capabilities ) {
		if ( is_array( $capabilities ) ) {
			return $capabilities;
		}

		// Try to decode JSON
		$decoded = json_decode( $capabilities, true );
		if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
			return $decoded;
		}

		return false;
	}

	/**
	 * Parse roles from various formats
	 *
	 * @param string|array $roles Roles data
	 * @return array Array of roles
	 */
	protected function parse_roles( $roles ) {
		if ( is_array( $roles ) ) {
			return $roles;
		}

		// Try to decode JSON
		$decoded = json_decode( $roles, true );
		if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
			return $decoded;
		}

		// Try comma-separated
		if ( strpos( $roles, ',' ) !== false ) {
			return array_map( 'trim', explode( ',', $roles ) );
		}

		// Single role
		return [ trim( $roles ) ];
	}

	/**
	 * Sanitize user item
	 *
	 * @param array $item User data
	 * @return array Sanitized data
	 */
	protected function sanitize_item( $item ) {
		$sanitized = [];

		foreach ( $item as $key => $value ) {
			// Skip empty values
			if ( '' === $value || null === $value ) {
				continue;
			}

			// Sanitize based on field type
			switch ( $key ) {
				case 'user_email':
					$sanitized[ $key ] = sanitize_email( $value );
					break;

				case 'user_url':
					$sanitized[ $key ] = esc_url_raw( $value );
					break;

				case 'user_login':
				case 'user_nicename':
					$sanitized[ $key ] = sanitize_user( $value );
					break;

				case 'ID':
				case 'posts_count':
					$sanitized[ $key ] = absint( $value );
					break;

				case 'rich_editing':
					$sanitized[ $key ] = (bool) $value;
					break;

				case 'description':
				case 'user_pass':
					$sanitized[ $key ] = $value; // Keep as is
					break;

				default:
					$sanitized[ $key ] = sanitize_text_field( $value );
					break;
			}
		}

		return $sanitized;
	}

	/**
	 * Validate data before import
	 *
	 * @param array $data Data to validate
	 * @return true|WP_Error True if valid, WP_Error otherwise
	 */
	public function validate( $data ) {
		if ( empty( $data ) || ! is_array( $data ) ) {
			return new \WP_Error(
				'empty_data',
				__( 'No data provided for import', 'wp-advanced-import-export' )
			);
		}

		// Check if at least one item has required fields
		$has_valid_item = false;
		foreach ( $data as $item ) {
			if ( ! empty( $item['user_login'] ) ) {
				$has_valid_item = true;
				break;
			}
		}

		if ( ! $has_valid_item ) {
			return new \WP_Error(
				'missing_required_fields',
				__( 'No items with required field "user_login" found', 'wp-advanced-import-export' )
			);
		}

		return true;
	}

	/**
	 * Get example data
	 *
	 * @return array Example data structure
	 */
	public function get_example_data() {
		return [
			[
				'user_login'      => 'john_doe',
				'user_email'      => 'john@example.com',
				'user_pass'       => 'secure_password_123',
				'first_name'      => 'John',
				'last_name'       => 'Doe',
				'display_name'    => 'John Doe',
				'description'     => 'A sample user description',
				'role'            => 'editor',
				'user_url'        => 'https://johndoe.com',
				'locale'          => 'en_US',
				'admin_color'     => 'fresh',
				'rich_editing'    => true,
				'facebook'        => 'johndoe',
				'twitter'         => 'johndoe',
				'acf_test'        => 'Sample ACF value',
			],
		];
	}
}
