<?php
/**
 * Comment Importer
 *
 * Handles importing WordPress comments
 *
 * @package WP_AIE\Model\Import
 */

namespace WP_AIE\Model\Import;

/**
 * Comment Importer Class
 *
 * Imports WordPress comments with support for:
 * - Comment metadata
 * - Comment status (approved, pending, spam, trash)
 * - Comment type
 * - Parent/child relationships
 * - Duplicate handling
 * - ACF fields
 *
 * @package WP_AIE\Model\Import
 */
class Comment_Importer extends Abstract_Importer {

	/**
	 * Constructor
	 *
	 * @param int $job_id Optional. Job ID for logging
	 */
	public function __construct( $job_id = 0 ) {
		parent::__construct( $job_id );
	}

	/**
	 * Get importer name
	 *
	 * @return string
	 */
	public function get_name() {
		return 'comments';
	}

	/**
	 * Get importer description
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Import WordPress comments with metadata and ACF fields', 'wp-advanced-import-export' );
	}

	/**
	 * Get required fields
	 *
	 * @return array
	 */
	public function get_required_fields() {
		return [ 'comment_post_ID', 'comment_content' ];
	}

	/**
	 * Get optional fields
	 *
	 * @return array
	 */
	public function get_optional_fields() {
		return [
			'comment_ID',
			'comment_author',
			'comment_author_email',
			'comment_author_url',
			'comment_author_IP',
			'comment_date',
			'comment_date_gmt',
			'comment_karma',
			'comment_approved',
			'comment_agent',
			'comment_type',
			'comment_parent',
			'user_id',
			'post_title',
			'post_author',
			'comment_meta',
		];
	}

	/**
	 * Get supported options
	 *
	 * @return array
	 */
	public function get_supported_options() {
		return [
			'duplicate_mode'       => 'How to handle duplicates: skip, update, create',
			'duplicate_check'      => 'Field to check for duplicates: comment_ID, comment_content',
			'default_status'       => 'Default comment status if not specified: 0 (pending), 1 (approved), spam, trash',
			'default_type'         => 'Default comment type: comment, pingback, trackback',
			'update_existing'      => 'Update existing comments: true, false',
			'import_acf'           => 'Import ACF fields: true, false',
			'validate_post_exists' => 'Validate that post exists: true, false',
			'skip_missing_posts'   => 'Skip comments if post does not exist: true, false',
			'create_missing_posts' => 'Create missing posts: true, false (requires validate_post_exists=true or skip_missing_posts=false)',
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
				'duplicate_check'      => 'comment_ID',
				'default_status'       => '1', // approved
				'default_type'         => 'comment',
				'update_existing'      => false,
				'import_acf'           => true,
				'validate_post_exists' => false, // Don't validate by default for performance
				'create_missing_posts' => false,
				'skip_missing_posts'   => false, // Don't skip by default - let it fail with clear error
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
	 * Import single comment
	 *
	 * @param array $item  Comment data
	 * @param int   $index Item index
	 * @return int|string|WP_Error Comment ID, 'skipped', 'updated', or WP_Error
	 */
	public function import_item( $item, $index ) {
		// Sanitize data
		$item = $this->sanitize_item( $item );

		// Validate required fields
		if ( empty( $item['comment_content'] ) ) {
			return new \WP_Error(
				'missing_comment_content',
				__( 'Comment content is required', 'wp-advanced-import-export' )
			);
		}

		// Check if post exists
		$post_id = absint( $item['comment_post_ID'] ?? 0 );
		
		if ( ! $post_id ) {
			return new \WP_Error(
				'missing_post_id',
				__( 'Post ID is required', 'wp-advanced-import-export' )
			);
		}
		
		// Validate post exists if either option is enabled
		$validate_post_exists = $this->get_option( 'validate_post_exists', false );
		$skip_missing_posts   = $this->get_option( 'skip_missing_posts', false );
		
		// Only check post existence if validation is enabled OR we need to skip missing posts
		if ( $validate_post_exists || $skip_missing_posts ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				// Post doesn't exist
				if ( $this->get_option( 'create_missing_posts', false ) ) {
					// Create placeholder post
					$new_post_id = wp_insert_post(
						[
							'post_title'   => $item['post_title'] ?? sprintf( __( 'Imported Post %d', 'wp-advanced-import-export' ), $post_id ),
							'post_status'  => 'draft',
							'post_content' => '',
						]
					);
					if ( is_wp_error( $new_post_id ) ) {
						return new \WP_Error(
							'post_creation_failed',
							sprintf(
								/* translators: %s: error message */
								__( 'Failed to create missing post: %s', 'wp-advanced-import-export' ),
								$new_post_id->get_error_message()
							)
						);
					}
					$post_id = $new_post_id;
					$item['comment_post_ID'] = $post_id; // Update item with new post ID
				} elseif ( $skip_missing_posts ) {
					// Skip comment if post doesn't exist
					return 'skipped';
				} else {
					// Return error if post doesn't exist and we're validating
					return new \WP_Error(
						'post_not_found',
						sprintf(
							/* translators: %d: post ID */
							__( 'Post ID %d does not exist', 'wp-advanced-import-export' ),
							$post_id
						)
					);
				}
			}
		}

		// Check for existing comment based on duplicate_check setting
		$existing_comment_id = null;
		$duplicate_check     = $this->get_option( 'duplicate_check', 'comment_ID' );

		if ( 'comment_ID' === $duplicate_check ) {
			$comment_id = absint( $item['comment_ID'] ?? 0 );
			if ( $comment_id ) {
				$existing_comment = get_comment( $comment_id );
				if ( $existing_comment ) {
					$existing_comment_id = $comment_id;
				}
			}
		} elseif ( 'comment_content' === $duplicate_check ) {
			$existing_comment_id = $this->find_comment_by_content(
				$item['comment_content'] ?? '',
				$post_id
			);
		}

		// Handle duplicate
		if ( $existing_comment_id ) {
			$duplicate_mode = $this->get_option( 'duplicate_mode', 'skip' );

			if ( 'skip' === $duplicate_mode ) {
				return 'skipped';
			} elseif ( 'update' === $duplicate_mode || $this->get_option( 'update_existing', false ) ) {
				return $this->update_comment( $existing_comment_id, $item );
			}
			// If 'create' mode, continue to create new comment
		}

		// Create new comment
		return $this->create_comment( $item );
	}

	/**
	 * Create new comment
	 *
	 * @param array $item Comment data
	 * @return int|WP_Error Comment ID or WP_Error
	 */
	private function create_comment( $item ) {
		$comment_data = $this->prepare_comment_data( $item );

		// Remove comment_ID if present to force creation
		unset( $comment_data['comment_ID'] );

		$comment_id = wp_insert_comment( $comment_data );

		if ( ! $comment_id ) {
			return new \WP_Error(
				'comment_creation_failed',
				__( 'Failed to create comment', 'wp-advanced-import-export' )
			);
		}

		// Import metadata
		$this->import_comment_meta( $comment_id, $item );

		return $comment_id;
	}

	/**
	 * Update existing comment
	 *
	 * @param int   $comment_id Existing comment ID
	 * @param array $item       Comment data
	 * @return string|WP_Error 'updated' or WP_Error
	 */
	private function update_comment( $comment_id, $item ) {
		$comment_data                = $this->prepare_comment_data( $item );
		$comment_data['comment_ID']  = $comment_id;

		// wp_update_comment returns 1 on success, 0 on failure, or WP_Error
		$result = wp_update_comment( $comment_data, true );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Note: wp_update_comment returns 0 when no changes were made (data identical)
		// This is NOT an error, it just means the comment is already up-to-date
		// We should still count this as successfully processed

		// Update metadata
		$this->import_comment_meta( $comment_id, $item );

		return 'updated';
	}

	/**
	 * Prepare comment data for wp_insert_comment or wp_update_comment
	 *
	 * @param array $item Raw comment data
	 * @return array Prepared comment data
	 */
	private function prepare_comment_data( $item ) {
		$comment_data = [
			'comment_post_ID'      => absint( $item['comment_post_ID'] ?? 0 ),
			'comment_content'      => $item['comment_content'] ?? '',
			'comment_approved'     => $item['comment_approved'] ?? $this->get_option( 'default_status', '1' ),
			'comment_type'         => $item['comment_type'] ?? $this->get_option( 'default_type', 'comment' ),
			'comment_author'       => $item['comment_author'] ?? '',
			'comment_author_email' => $item['comment_author_email'] ?? '',
			'comment_author_url'   => $item['comment_author_url'] ?? '',
			'comment_author_IP'    => $item['comment_author_IP'] ?? '',
			'comment_parent'       => absint( $item['comment_parent'] ?? 0 ),
			'user_id'              => absint( $item['user_id'] ?? 0 ),
			'comment_karma'        => absint( $item['comment_karma'] ?? 0 ),
			'comment_agent'        => $item['comment_agent'] ?? '',
		];

		// Handle dates
		if ( ! empty( $item['comment_date'] ) ) {
			$comment_data['comment_date'] = $item['comment_date'];
		}

		if ( ! empty( $item['comment_date_gmt'] ) ) {
			$comment_data['comment_date_gmt'] = $item['comment_date_gmt'];
		}

		// Validate email if provided
		if ( ! empty( $comment_data['comment_author_email'] ) ) {
			if ( ! is_email( $comment_data['comment_author_email'] ) ) {
				$comment_data['comment_author_email'] = '';
			}
		}

		// Validate URL if provided
		if ( ! empty( $comment_data['comment_author_url'] ) ) {
			$comment_data['comment_author_url'] = esc_url_raw( $comment_data['comment_author_url'] );
		}

		return $comment_data;
	}

	/**
	 * Import comment metadata
	 *
	 * @param int   $comment_id Comment ID
	 * @param array $item       Comment data
	 * @return void
	 */
	private function import_comment_meta( $comment_id, $item ) {
		// Import comment_meta if provided
		if ( ! empty( $item['comment_meta'] ) ) {
			$meta_data = $item['comment_meta'];

			// Parse if string (JSON)
			if ( is_string( $meta_data ) ) {
				$meta_data = json_decode( $meta_data, true );
			}

			if ( is_array( $meta_data ) ) {
				foreach ( $meta_data as $meta_key => $meta_value ) {
					update_comment_meta( $comment_id, $meta_key, $meta_value );
				}
			}
		}

		// Import ACF fields if enabled
		if ( $this->get_option( 'import_acf', true ) ) {
			$this->import_acf_fields( $comment_id, $item );
		}
	}

	/**
	 * Import ACF fields for comment
	 *
	 * @param int   $comment_id Comment ID
	 * @param array $item       Comment data
	 * @return void
	 */
	private function import_acf_fields( $comment_id, $item ) {
		if ( ! function_exists( 'acf_get_field_groups' ) ) {
			return;
		}

		// Get ACF field groups for comments
		$field_groups = acf_get_field_groups(
			[
				'comment' => 'comment',
			]
		);

		if ( empty( $field_groups ) ) {
			return;
		}

		$comment_context = 'comment_' . $comment_id;

		foreach ( $field_groups as $field_group ) {
			$fields = acf_get_fields( $field_group['key'] );

			if ( empty( $fields ) ) {
				continue;
			}

			foreach ( $fields as $field ) {
				$field_name = $field['name'];

				// Check if field value exists in import data
				if ( isset( $item[ $field_name ] ) ) {
					update_field( $field['key'], $item[ $field_name ], $comment_context );
				}
			}
		}
	}

	/**
	 * Sanitize item data
	 *
	 * @param array $item Item data
	 * @return array Sanitized data
	 */
	protected function sanitize_item( $item ) {
		if ( ! is_array( $item ) ) {
			return [];
		}

		$sanitized = [];

		foreach ( $item as $key => $value ) {
			// Skip if value is empty
			if ( '' === $value || null === $value ) {
				$sanitized[ $key ] = $value;
				continue;
			}

			// Sanitize based on field type
			switch ( $key ) {
				case 'comment_author_email':
					$sanitized[ $key ] = sanitize_email( $value );
					break;

				case 'comment_author_url':
					$sanitized[ $key ] = esc_url_raw( $value );
					break;

				case 'comment_content':
					$sanitized[ $key ] = wp_kses_post( $value );
					break;

				case 'comment_ID':
				case 'comment_post_ID':
				case 'comment_parent':
				case 'user_id':
				case 'comment_karma':
					$sanitized[ $key ] = absint( $value );
					break;

				default:
					$sanitized[ $key ] = sanitize_text_field( $value );
					break;
			}
		}

		return $sanitized;
	}

	/**
	 * Find comment by content
	 *
	 * @param string $content Comment content
	 * @param int    $post_id Post ID
	 * @return int|null Comment ID or null if not found
	 */
	private function find_comment_by_content( $content, $post_id = 0 ) {
		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT comment_ID FROM {$wpdb->comments} WHERE comment_content = %s",
			$content
		);

		if ( $post_id ) {
			$query = $wpdb->prepare(
				"SELECT comment_ID FROM {$wpdb->comments} WHERE comment_content = %s AND comment_post_ID = %d",
				$content,
				$post_id
			);
		}

		$comment_id = $wpdb->get_var( $query );

		return $comment_id ? absint( $comment_id ) : null;
	}
}
