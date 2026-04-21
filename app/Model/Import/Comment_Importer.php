<?php
/**
 * Comment Importer
 *
 * Handles importing WordPress comments
 *
 * @package RockStarLab\ImportExport\Model\Import
 */

namespace RockStarLab\ImportExport\Model\Import;

defined( 'ABSPATH' ) || exit;

class Comment_Importer extends Abstract_Importer {
	/**
	 * Comment source-id meta key used for cross-site dedupe and comparisons.
	 *
	 * @var string
	 */
	const SOURCE_ID_META_KEY = '_aie_source_comment_id';

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
		return __( 'Import WordPress comments with metadata and ACF fields', 'import-export-by-rockstarlab' );
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

		$source_comment_id       = absint( $item['_aie_source_comment_id'] ?? ( $item['comment_ID'] ?? 0 ) );
		$source_comment_parent   = absint( $item['_aie_source_comment_parent_id'] ?? ( $item['comment_parent'] ?? 0 ) );

		$has_post_hints = $this->has_post_hints( $item );

		// Resolve post ID across sites when hints are present in the file.
		$resolved_post_id = $this->resolve_comment_post_id( $item );
		if ( $resolved_post_id > 0 ) {
			$item['comment_post_ID'] = $resolved_post_id;
		} elseif ( $has_post_hints ) {
			// Avoid accidentally attaching to a random local post that happens to share the same numeric ID.
			$item['comment_post_ID'] = 0;
		}

		// Resolve user_id: imported IDs are from the source site. Prefer local user by email.
		$item['user_id'] = $this->resolve_comment_user_id( $item );

		// Always defer parent mapping to a post-import fixup; source comment IDs
		// do not exist on the target site.
		$item['comment_parent'] = 0;

		// Validate required fields
		if ( empty( $item['comment_content'] ) ) {
			return new \WP_Error(
				'missing_comment_content',
				__( 'Comment content is required', 'import-export-by-rockstarlab' )
			);
		}

		// Check if post exists
		$post_id = absint( $item['comment_post_ID'] ?? 0 );
		
		if ( ! $post_id ) {
			return new \WP_Error(
				'missing_post_id',
				__( 'Post ID is required', 'import-export-by-rockstarlab' )
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
							// translators: %d is a dynamic value.
							'post_title'   => $item['post_title'] ?? sprintf( __( 'Imported Post %d', 'import-export-by-rockstarlab' ), $post_id ),
							'post_status'  => 'draft',
							'post_content' => '',
						]
					);
					if ( is_wp_error( $new_post_id ) ) {
						return new \WP_Error(
							'post_creation_failed',
							sprintf(
								/* translators: %s: error message */
								__( 'Failed to create missing post: %s', 'import-export-by-rockstarlab' ),
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
							__( 'Post ID %d does not exist', 'import-export-by-rockstarlab' ),
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
			// Interpret comment_ID as the SOURCE site ID; we de-dupe by comment meta.
			$existing_comment_id = $this->find_existing_comment_by_source_id( $source_comment_id );
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
				$this->record_source_id_map( $source_comment_id, $existing_comment_id );
				return 'skipped';
			} elseif ( 'update' === $duplicate_mode || $this->get_option( 'update_existing', false ) ) {
				$result = $this->update_comment( $existing_comment_id, $item, $source_comment_id );
				if ( ! is_wp_error( $result ) ) {
					$this->record_source_id_map( $source_comment_id, $existing_comment_id );
				}
				return $result;
			}
			// If 'create' mode, continue to create new comment
		}

		// Create new comment
		$created = $this->create_comment( $item, $source_comment_id, $source_comment_parent );
		if ( is_int( $created ) && $created > 0 ) {
			$this->record_source_id_map( $source_comment_id, $created );
		}
		return $created;
	}

	/**
	 * Create new comment
	 *
	 * @param array $item Comment data
	 * @return int|WP_Error Comment ID or WP_Error
	 */
	private function create_comment( $item, $source_comment_id = 0, $source_comment_parent = 0 ) {
		$comment_data = $this->prepare_comment_data( $item );

		// Remove comment_ID if present to force creation
		unset( $comment_data['comment_ID'] );

		$comment_id = wp_insert_comment( $comment_data );

		if ( ! $comment_id ) {
			return new \WP_Error(
				'comment_creation_failed',
				__( 'Failed to create comment', 'import-export-by-rockstarlab' )
			);
		}

		// Import metadata
		$this->import_comment_meta( $comment_id, $item );

		// Persist source IDs so subsequent imports can update instead of duplicating.
		$this->store_source_comment_meta( $comment_id, $source_comment_id, $source_comment_parent );

		// wp_insert_comment/wp_filter_comment may mutate stored HTML (e.g. add rel="nofollow ugc").
		// Preserve the source value from the file for accurate migrations.
		$this->force_update_comment_content( $comment_id, $item );

		// wp_insert_comment may recompute date fields based on site timezone; force exact values from file.
		$this->force_update_comment_dates( $comment_id, $item );

		return $comment_id;
	}

	/**
	 * Update existing comment
	 *
	 * @param int   $comment_id Existing comment ID
	 * @param array $item       Comment data
	 * @return string|WP_Error 'updated' or WP_Error
	 */
	private function update_comment( $comment_id, $item, $source_comment_id = 0 ) {
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
		$this->store_source_comment_meta( $comment_id, $source_comment_id, absint( $item['_aie_source_comment_parent_id'] ?? 0 ) );

		// Preserve raw source HTML for accurate migrations.
		$this->force_update_comment_content( $comment_id, $item );

		// Ensure dates match the import file exactly (timezone-independent).
		$this->force_update_comment_dates( $comment_id, $item );

		return 'updated';
	}

	/**
	 * Whether the import row includes portable post identification hints.
	 *
	 * @param array $item Prepared comment row.
	 * @return bool
	 */
	private function has_post_hints( $item ) {
		$permalink = isset( $item['_aie_source_post_permalink'] ) ? (string) $item['_aie_source_post_permalink'] : ( (string) ( $item['post_permalink'] ?? '' ) );
		$slug      = isset( $item['_aie_source_post_slug'] ) ? (string) $item['_aie_source_post_slug'] : ( (string) ( $item['post_slug'] ?? '' ) );
		$type      = isset( $item['_aie_source_post_type'] ) ? (string) $item['_aie_source_post_type'] : ( (string) ( $item['post_type'] ?? '' ) );
		$title     = isset( $item['post_title'] ) ? (string) $item['post_title'] : '';

		return trim( $permalink ) !== '' || trim( $slug ) !== '' || trim( $type ) !== '' || trim( $title ) !== '';
	}

	/**
	 * Store source IDs as comment meta for cross-site dedupe and parent fixups.
	 *
	 * @param int $comment_id            Target comment ID.
	 * @param int $source_comment_id     Source site comment_ID.
	 * @param int $source_comment_parent Source site comment_parent ID.
	 * @return void
	 */
	private function store_source_comment_meta( $comment_id, $source_comment_id, $source_comment_parent ) {
		$comment_id = absint( $comment_id );
		if ( $comment_id <= 0 ) {
			return;
		}

		$source_comment_id = absint( $source_comment_id );
		if ( $source_comment_id > 0 ) {
			update_comment_meta( $comment_id, self::SOURCE_ID_META_KEY, (string) $source_comment_id );
		}

		$source_comment_parent = absint( $source_comment_parent );
		if ( $source_comment_parent > 0 ) {
			update_comment_meta( $comment_id, '_aie_source_comment_parent_id', (string) $source_comment_parent );
		}
	}

	/**
	 * Find an existing target comment by stored source ID.
	 *
	 * @param int $source_comment_id Source site comment_ID.
	 * @return int|null Target comment ID or null.
	 */
	private function find_existing_comment_by_source_id( $source_comment_id ) {
		$source_comment_id = absint( $source_comment_id );
		if ( $source_comment_id <= 0 ) {
			return null;
		}

		$comment_ids = get_comments( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Targeted lookup by importer-specific meta key.
			[
				'number'     => 1,
					'orderby'    => 'comment_ID',
					'order'      => 'DESC',
					'fields'     => 'ids',
					'status'     => 'all',
					'meta_key'   => self::SOURCE_ID_META_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Targeted lookup by importer-specific meta key.
					'meta_value' => (string) $source_comment_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Targeted lookup by importer-specific meta key.
				]
			);

		return ! empty( $comment_ids[0] ) ? absint( $comment_ids[0] ) : null;
	}

	/**
	 * Resolve a comment's post ID across sites using portable hints from export.
	 *
	 * @param array $item Prepared comment data.
	 * @return int Target post ID or 0.
	 */
	private function resolve_comment_post_id( $item ) {
		$post_id = absint( $item['comment_post_ID'] ?? 0 );

		$post_permalink = isset( $item['_aie_source_post_permalink'] ) ? (string) $item['_aie_source_post_permalink'] : ( (string) ( $item['post_permalink'] ?? '' ) );
		if ( $post_permalink !== '' ) {
			$local_url = $this->rewrite_url_to_local_site( $post_permalink );
			$mapped    = url_to_postid( $local_url );
			if ( $mapped > 0 ) {
				return (int) $mapped;
			}

			// Fallback: if the source also provided comment_post_ID and it points to a post with the same permalink.
			if ( $post_id > 0 && get_post( $post_id ) ) {
				$current_permalink = get_permalink( $post_id );
				if ( $current_permalink && untrailingslashit( $current_permalink ) === untrailingslashit( $local_url ) ) {
					return $post_id;
				}
			}

			// When a permalink is provided but cannot be mapped, do not trust raw IDs.
			return 0;
		}

		$post_slug = isset( $item['_aie_source_post_slug'] ) ? (string) $item['_aie_source_post_slug'] : ( (string) ( $item['post_slug'] ?? '' ) );
		$post_type = isset( $item['_aie_source_post_type'] ) ? (string) $item['_aie_source_post_type'] : ( (string) ( $item['post_type'] ?? '' ) );
		if ( $post_slug !== '' ) {
			$post_slug = trim( $post_slug, '/' );
			$post_type = $post_type !== '' ? sanitize_key( $post_type ) : 'any';

			$post = get_page_by_path( $post_slug, OBJECT, $post_type === 'any' ? [ 'post', 'page', 'attachment' ] : $post_type );
			if ( $post ) {
				return (int) $post->ID;
			}

			// A slug hint was provided but we couldn't map it; don't trust raw IDs.
			return 0;
		}

		// Fallback: accept comment_post_ID only when no portable hints are present.
		if ( $post_id > 0 && get_post( $post_id ) ) {
			return $post_id;
		}

		// Last resort: try post_title.
		$title = isset( $item['post_title'] ) ? (string) $item['post_title'] : '';
		if ( $title !== '' ) {
			$post_id_by_title = $this->find_post_id_by_title( $title, 'post' );
			if ( $post_id_by_title > 0 ) {
				return $post_id_by_title;
			}

			$page_id_by_title = $this->find_post_id_by_title( $title, 'page' );
			if ( $page_id_by_title > 0 ) {
				return $page_id_by_title;
			}
		}

		return 0;
	}

	/**
	 * Best-effort title lookup using WP_Query (for environments where get_page_by_title() is deprecated).
	 *
	 * @param string       $title     Post title.
	 * @param string|array $post_type Post type(s).
	 * @return int Post ID or 0.
	 */
	private function find_post_id_by_title( $title, $post_type ) {
		$title = trim( (string) $title );
		if ( '' === $title ) {
			return 0;
		}

		$query = new \WP_Query(
			[
				'post_type'              => $post_type,
				'post_status'            => 'any',
				'posts_per_page'         => 10,
				's'                      => $title,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			]
		);

		if ( empty( $query->posts ) ) {
			return 0;
		}

		foreach ( $query->posts as $candidate_id ) {
			$candidate = get_post( $candidate_id );
			if ( $candidate && (string) $candidate->post_title === $title ) {
				return (int) $candidate_id;
			}
		}

		return 0;
	}

	/**
	 * Force comment_date/comment_date_gmt to match file values exactly.
	 *
	 * WordPress core may recompute `comment_date_gmt` based on the current site's
	 * timezone when inserting/updating. For migration use-cases we want to preserve
	 * the source values.
	 *
	 * @param int   $comment_id Target comment ID.
	 * @param array $item       Prepared item.
	 * @return void
	 */
	private function force_update_comment_dates( $comment_id, $item ) {
		$comment_id = absint( $comment_id );
		if ( $comment_id <= 0 ) {
			return;
		}

		$date     = isset( $item['comment_date'] ) ? trim( (string) $item['comment_date'] ) : '';
		$date_gmt = isset( $item['comment_date_gmt'] ) ? trim( (string) $item['comment_date_gmt'] ) : '';

		if ( $date !== '' && $date_gmt === '' ) {
			$date_gmt = get_gmt_from_date( $date );
		} elseif ( $date === '' && $date_gmt !== '' ) {
			$date = get_date_from_gmt( $date_gmt );
		}

		$update = [];
		if ( $date !== '' ) {
			$update['comment_date'] = $date;
		}
		if ( $date_gmt !== '' ) {
			$update['comment_date_gmt'] = $date_gmt;
		}

		if ( empty( $update ) ) {
			return;
		}

		$commentarr = [ 'comment_ID' => $comment_id ] + $update;
		wp_update_comment( wp_slash( $commentarr ) );

		clean_comment_cache( $comment_id );
	}

	/**
	 * Force comment_content to match file value exactly.
	 *
	 * @param int   $comment_id Target comment ID.
	 * @param array $item       Prepared item.
	 * @return void
	 */
	private function force_update_comment_content( $comment_id, $item ) {
		$comment_id = absint( $comment_id );
		if ( $comment_id <= 0 ) {
			return;
		}

		$content = isset( $item['comment_content'] ) ? (string) $item['comment_content'] : '';
		if ( $content === '' ) {
			return;
		}

		wp_update_comment(
			wp_slash(
				[
					'comment_ID'      => $comment_id,
					'comment_content' => $content,
				]
			)
		);

		clean_comment_cache( $comment_id );
	}

	/**
	 * Resolve user_id for a comment using local users (email-based).
	 *
	 * @param array $item Prepared comment data.
	 * @return int Local user ID or 0.
	 */
	private function resolve_comment_user_id( $item ) {
		$user_id = absint( $item['user_id'] ?? 0 );
		if ( $user_id > 0 && get_user_by( 'id', $user_id ) ) {
			return $user_id;
		}

		$email = isset( $item['comment_author_email'] ) ? (string) $item['comment_author_email'] : '';
		if ( $email !== '' && is_email( $email ) ) {
			$user = get_user_by( 'email', $email );
			if ( $user ) {
				return (int) $user->ID;
			}
		}

		return 0;
	}

	/**
	 * Rewrite an absolute URL from another site to the current site's origin.
	 *
	 * @param string $url Raw URL from the import file.
	 * @return string URL rewritten to current site, or original if not absolute/parseable.
	 */
	private function rewrite_url_to_local_site( $url ) {
		$url = trim( (string) $url );
		if ( $url === '' ) {
			return '';
		}

		$parsed = wp_parse_url( $url );
		if ( empty( $parsed['host'] ) ) {
			return esc_url_raw( $url );
		}

		$home        = home_url();
		$home_parsed = wp_parse_url( $home );
		if ( empty( $home_parsed['host'] ) ) {
			return esc_url_raw( $url );
		}

		$scheme = $home_parsed['scheme'] ?? 'http';
		$host   = $home_parsed['host'];
		$port   = isset( $home_parsed['port'] ) ? ':' . (int) $home_parsed['port'] : '';

		$path     = $parsed['path'] ?? '';
		$query    = isset( $parsed['query'] ) ? '?' . $parsed['query'] : '';
		$fragment = isset( $parsed['fragment'] ) ? '#' . $parsed['fragment'] : '';

		return esc_url_raw( $scheme . '://' . $host . $port . $path . $query . $fragment );
	}

	/**
	 * Persist a source->target comment ID map for this import job.
	 *
	 * @param int $source_id Source site comment_ID.
	 * @param int $target_id Target site comment_ID.
	 * @return void
	 */
	private function record_source_id_map( $source_id, $target_id ) {
		$source_id = absint( $source_id );
		$target_id = absint( $target_id );

		if ( $source_id <= 0 || $target_id <= 0 ) {
			return;
		}

		if ( empty( $this->job_id ) ) {
			return;
		}

		$key = $this->get_job_id_map_key();
		$map = get_transient( $key );
		if ( ! is_array( $map ) ) {
			$map = [];
		}

		$map[ (string) $source_id ] = $target_id;
		set_transient( $key, $map, DAY_IN_SECONDS );
	}

	/**
	 * Get transient key for this job's comment ID map.
	 *
	 * @return string
	 */
	private function get_job_id_map_key() {
		return 'rsl_ie_import_comment_id_map_' . absint( $this->job_id );
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
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$query = $wpdb->prepare(
				"SELECT comment_ID FROM {$wpdb->comments} WHERE comment_content = %s AND comment_post_ID = %d",
				$content,
				$post_id
			);
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$comment_id = $wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Direct DB query required here.

		return $comment_id ? absint( $comment_id ) : null;
	}
}
