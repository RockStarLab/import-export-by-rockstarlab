<?php
/**
 * Content Sync Service
 *
 * Transport-independent connected-site operations
 *
 * @package RockStarLab\ImportExport\Service
 */

namespace RockStarLab\ImportExport\Service;

use RockStarLab\ImportExport\Abilities\Sync_Policy;
use RockStarLab\ImportExport\Helper\ACF_Fields;
use RockStarLab\ImportExport\Helper\Field_Transformation_Bridge;
use RockStarLab\ImportExport\Helper\Site_URL;
use RockStarLab\ImportExport\Model\Connected_Site;

defined( 'ABSPATH' ) || exit;

/** Shared content sync business operations. */
class Content_Sync_Service {
	use Sync_Service_Input;

	/**
	 * Server-selected standalone domain, never taken from request data.
	 *
	 * @var string
	 */
	private $standalone_domain = '';
	/**
	 * Invoke the standalone ability contract under its owning provider.
	 *
	 * @param string $action Operation.
	 * @param array  $input Parsed input.
	 * @param string $domain Server-selected domain.
	 * @return array|\WP_Error
	 */
	public function execute_standalone( $action, array $input, $domain ) {
		$this->standalone_domain = $domain;
		if ( 'reviews' === $domain ) {
			$input['comment_type'] = 'review'; }
		$access = \RockStarLab\ImportExport\Abilities\Standalone_Sync_Policy::input( $domain, $action, $input );
		if ( is_wp_error( $access ) ) {
			return $access; }
		return $this->execute( $action, $input );
	}

	/**
	 * Whether to enforce the Free ability contract.
	 *
	 * @var bool
	 */
	private $free_scope = false;

	/**
	 * Server-selected synchronization dependencies.
	 *
	 * @var string Server-selected content policy class.
	 */
	private $policy_class = Sync_Policy::class;

	/**
	 * Current parsed execution input.
	 *
	 * @var array
	 */
	private $input = [];

	/**
	 * Immediate history job for this execution.
	 *
	 * @var int
	 */
	private $job_id = 0;

	/**
	 * Structured completion result for the caller.
	 *
	 * @var array
	 */
	private $sync_result = [];

	/**
	 * Select the server-owned Free policy; callers cannot set it through input.
	 *
	 * @param bool   $free_scope Restrict to the provider ability domain.
	 * @param string $policy_class Server-selected provider policy.
	 */
	public function __construct( $free_scope = false, $policy_class = Sync_Policy::class ) {
		$this->policy_class = $policy_class;
		$this->free_scope   = (bool) $free_scope;
	}


	/**
	 * Dispatch an explicit service operation.
	 *
	 * @param string $action Operation name.
	 * @param array  $input Parsed input, independent of HTTP globals.
	 * @return array|\WP_Error
	 */
	public function execute( $action, array $input = [] ) {
		$access = \RockStarLab\ImportExport\Abilities\Access_Policy::check_management();
		if ( is_wp_error( $access ) ) {
			return $access;
		}
		if ( ! in_array( $action, [ 'auto_map_by_title', 'get_children_posts', 'get_local_posts_info', 'get_remote_comments', 'get_remote_posts', 'get_remote_terms', 'pull_comments', 'pull_content', 'pull_terms', 'push_comments', 'push_content', 'push_terms', 'search_local_posts', 'search_local_terms', 'search_remote_posts' ], true ) ) {
			return $this->error( __( 'Unknown sync operation.', 'import-export-by-rockstarlab' ) );
		}
		$peer_action = [
			'push_content'         => 'send_content',
			'get_remote_terms'     => 'list_terms',
			'search_local_terms'   => 'list_terms',
			'push_terms'           => 'send_terms',
			'pull_terms'           => 'receive_terms',
			'get_remote_comments'  => 'list_comments',
			'push_comments'        => 'send_comments',
			'pull_comments'        => 'receive_comments',
			'search_local_posts'   => 'list_posts',
			'get_remote_posts'     => 'list_posts',
			'search_remote_posts'  => 'list_posts',
			'get_local_posts_info' => 'send_content',
			'auto_map_by_title'    => 'send_content',
		][ $action ] ?? null;
		if ( $peer_action ) {
			$check_input = $input;
			if ( 'push_comments' === $action ) {
				unset( $check_input['target_post_id'] ); }
			if ( 'pull_terms' === $action ) {
				unset( $check_input['term_ids'] ); }
			if ( in_array( $action, [ 'pull_comments', 'get_remote_comments' ], true ) ) {
				unset( $check_input['comment_ids'], $check_input['post_id'] ); }
			$access = \RockStarLab\ImportExport\Abilities\Sync_Peer_Policy::check( $peer_action, $check_input );
			if ( is_wp_error( $access ) ) {
				return $access; }
		}
		$this->input       = $input;
		$this->job_id      = 0;
		$this->sync_result = [];
		if ( $this->free_scope ) {
			$access = $this->policy_class::input( $action, $input );
			if ( is_wp_error( $access ) ) {
				return $access;
			}
		}
		$site_id = absint( $input['site_id'] ?? 0 );
		// Lists and searches only read the other site. A dropdown sends a new search
		// while the previous one is still running, so wait briefly for it instead of
		// failing with "connection busy". Writes still fail fast.
		$wait    = in_array( $action, [ 'get_remote_posts', 'search_remote_posts', 'get_children_posts', 'get_remote_comments' ], true ) ? 10 : 0;
		$lock    = $site_id ? Job_Lock::acquire_resource( 'connection', $site_id, $wait ) : null;
		if ( false === $lock || is_wp_error( $lock ) ) {
			return new \WP_Error( 'rsl_ie_connection_busy', __( 'This connection is already in use. Try again later.', 'import-export-by-rockstarlab' ), [ 'status' => 409 ] );
		}
		try {
			if ( $site_id ) {
				$site      = Connected_Site::get_by_id( $site_id );
				$direction = 0 === strpos( $action, 'push_' ) ? 'push' : ( 0 === strpos( $action, 'pull_' ) ? 'pull' : '' );
				if ( ! $site || 'active' !== $site['status'] || ( $direction && ! in_array( $site['direction'], [ $direction, 'bidirectional' ], true ) ) ) {
					return new \WP_Error( 'rsl_ie_connection_unavailable', __( 'The connection must be active and allow this direction.', 'import-export-by-rockstarlab' ), [ 'status' => 409 ] );
				}
				$guard = apply_filters( 'rsl_ie_sync_connection_guard', true, $site );
				if ( is_wp_error( $guard ) ) {
					return $guard;
				}
				if ( $this->free_scope && 'push_content' === $action && ! empty( $input['post_mapping'] ) ) {
					$ids = array_values( array_filter( array_map( 'absint', $input['post_mapping'] ) ) );
					if ( $ids ) {
						$remote = $this->remote_post( $site, 'send-content', [ 'post_ids' => $ids ] );
						$access = is_wp_error( $remote ) ? $remote : $this->policy_class::remote_posts( $remote['data']['posts'] ?? null, $ids, $input['post_type'] );
						if ( is_wp_error( $access ) ) {
							return $access;
						}
					}
				}
			}
			$result = Media_Reference_Transfer::run( $direction ?? '', $site ?? [], function () use ( $action, $input ) {
				return $this->$action( $input );
			} );
			if ( is_wp_error( $result ) && $this->job_id ) {
				$this->fail_content_sync_job( $this->job_id, $result->get_error_message() );
			}
			if ( ! is_wp_error( $result ) && ! empty( $this->sync_result['failed'] ) ) {
				return new \WP_Error(
					'rsl_ie_sync_partial',
					__( 'Some records could not be synchronized. Inspect the sync result before retrying.', 'import-export-by-rockstarlab' ),
					[
						'status' => 409,
						'job_id' => $this->job_id,
					]
				);
			}
			if ( is_wp_error( $result ) && $this->job_id ) {
				$result->add_data(
					[
						'status' => $result->get_error_data()['status'] ?? 400,
						'job_id' => $this->job_id,
					]
				);
			}
			if ( ! is_wp_error( $result ) && $this->job_id ) {
				$result['job_id'] = $this->job_id;
				$result['result'] = $this->sync_result;
			}
			// "Last Sync" on the Content Sync screen shows the last completed push or pull.
			if ( ! is_wp_error( $result ) && $site_id && in_array( $direction ?? '', [ 'push', 'pull' ], true ) ) {
				Connected_Site::update_last_sync( $site_id );
			}
			return $result;
		} catch ( \Throwable $exception ) {
			$message = __( 'Sync stopped unexpectedly. Inspect its job before repeating the request; some content may already have changed.', 'import-export-by-rockstarlab' );
			if ( $this->job_id ) {
				$this->fail_content_sync_job( $this->job_id, $message );
			}
			return new \WP_Error(
				'rsl_ie_sync_failed',
				$message,
				[
					'status' => 500,
					'job_id' => $this->job_id,
				]
			);
		} finally {
			if ( $lock ) {
				Job_Lock::release( $lock );
			}
		}
	}

	/**
	 * Get list of posts from remote site for mapping
	 *
	 * @param array $input Parsed operation input.
	 * @return array|\WP_Error
	 */
	private function get_remote_posts( array $input ) {
		$site_id          = $this->param( $input, 'site_id', 0 );
		$post_type        = $this->param( $input, 'post_type', 'any' );
		$search           = $this->param( $input, 'search', '' );
		$status           = $this->param( $input, 'status', '' );
		$page             = $this->param( $input, 'page', 1 );
		$per_page         = $this->param( $input, 'per_page', 20 );
		$commentable_only = filter_var( $this->param( $input, 'commentable_only', false ), FILTER_VALIDATE_BOOLEAN );
		$comment_type     = sanitize_key( (string) $this->param( $input, 'comment_type', '' ) );
		$language         = sanitize_key( (string) $this->param( $input, 'language', '' ) );
		$include_children = filter_var( $this->param( $input, 'include_children', false ), FILTER_VALIDATE_BOOLEAN );

		// Validate input.
		if ( empty( $site_id ) ) {
			return $this->error( __( 'Site ID is required', 'import-export-by-rockstarlab' ) );
		}

		// Get site details.
		$site = Connected_Site::get_by_id( $site_id );
		if ( ! $site ) {
			return $this->error( __( 'Site not found', 'import-export-by-rockstarlab' ) );
		}

		// Request posts list from remote site.
		$response = \RockStarLab\ImportExport\Helper\Remote_API::post(
			$site['remote_url'],
			'list-posts',
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Bearer ' . $site['api_key'],
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'post_type'        => $post_type,
						'search'           => $search,
						'status'           => $status,
						'page'             => $page,
						'per_page'         => $per_page,
						'commentable_only' => $commentable_only,
						'comment_type'     => $comment_type,
						'language'         => $language,
						'include_children' => $include_children,
					)
				),
			)
		);
		// translators: %s = content placeholder.

		if ( is_wp_error( $response ) ) {
			return $this->error( __( 'Failed to connect to remote site: ', 'import-export-by-rockstarlab' ) . $response->get_error_message() );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );

		if ( 200 !== $status_code ) {
			$error_data = json_decode( $body, true );
			// translators: %d is a dynamic value.
			$error_msg = isset( $error_data['message'] ) ? $error_data['message'] : sprintf( __( 'Request failed with status code: %d', 'import-export-by-rockstarlab' ), $status_code );
			return $this->error( $error_msg );
		}

		$data = json_decode( $body, true );
		if ( ! isset( $data['success'] ) || ! $data['success'] || ! isset( $data['posts'] ) ) {
			return $this->error( __( 'Remote site returned invalid data', 'import-export-by-rockstarlab' ) );
		}

		return $this->success(
			array(
				'posts'         => $data['posts'],
				'total'         => isset( $data['total'] ) ? $data['total'] : count( $data['posts'] ),
				'pages'         => isset( $data['pages'] ) ? $data['pages'] : 1,
				'current_page'  => isset( $data['current_page'] ) ? $data['current_page'] : 1,
				'status_counts' => isset( $data['status_counts'] ) ? $data['status_counts'] : array(),
			)
		);
	}

	/**
	 * Search remote posts with pagination (alias for get_remote_posts for Select2)
	 *
	 * @param array $input Parsed operation input.
	 * @return array|\WP_Error
	 */
	private function search_remote_posts( array $input ) {
		// This is just an alias for get_remote_posts with a different action name.
		return $this->get_remote_posts( $input );
	}

	/**
	 * Search local posts with pagination for Select2 mapping fields.
	 *
	 * @param array $input Parsed operation input.
	 * @return array|\WP_Error
	 */
	private function search_local_posts( array $input ) {
		$post_type        = sanitize_key( (string) $this->param( $input, 'post_type', 'any' ) );
		$commentable_only = filter_var( $this->param( $input, 'commentable_only', false ), FILTER_VALIDATE_BOOLEAN );
		$comment_type     = sanitize_key( (string) $this->param( $input, 'comment_type', '' ) );
		$search           = sanitize_text_field( (string) $this->param( $input, 'search', '' ) );
		$page             = max( 1, absint( $this->param( $input, 'page', 1 ) ) );
		$per_page         = min( max( 1, absint( $this->param( $input, 'per_page', 20 ) ) ), 50 );
		$args             = array(
			'post_type'              => $commentable_only ? $this->get_commentable_sync_post_types_for_context( $comment_type, $post_type ) : ( '' !== $post_type && 'any' !== $post_type ? $post_type : \RockStarLab\ImportExport\Abilities\Sync_Peer_Policy::browsable_types() ),
			'post_status'            => 'any',
			'posts_per_page'         => $per_page,
			'paged'                  => $page,
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);

		if ( '' !== $search ) {
			$args['s'] = $search;
		}

		$query = new \WP_Query( $args );
		$posts = array();
		foreach ( $query->posts as $post ) {
			$posts[] = array(
				'ID'          => (int) $post->ID,
				'title'       => (string) get_the_title( $post ),
				'post_title'  => (string) $post->post_title,
				'post_name'   => (string) $post->post_name,
				'post_type'   => (string) $post->post_type,
				'post_status' => (string) $post->post_status,
			);
		}

		return $this->success(
			array(
				'posts'        => $posts,
				'total'        => (int) $query->found_posts,
				'pages'        => max( 1, (int) $query->max_num_pages ),
				'current_page' => $page,
			)
		);
	}

	/**
	 * Search local taxonomy terms with pagination for Select2 mapping fields.
	 *
	 * @param array $input Parsed operation input.
	 * @return array|\WP_Error
	 */
	private function search_local_terms( array $input ) {
		$helpers = [
			'collect_term_acf_images_for_sync'  => function ( $info, $term, $taxonomy, &$images ) {
				return $this->collect_term_acf_images_for_sync( $info, $term, $taxonomy, $images ); },
			'download_remote_images_for_sync'   => function ( ...$args ) {
				return $this->download_remote_images_for_sync( ...$args ); },
			'export_term_acf_sync_value'        => function ( ...$args ) {
				return $this->export_term_acf_sync_value( ...$args ); },
			'get_term_acf_field_objects'        => function ( ...$args ) {
				return $this->get_term_acf_field_objects( ...$args ); },
			'get_term_parent_path'              => function ( ...$args ) {
				return $this->get_term_parent_path( ...$args ); },
			'get_term_slug_by_id'               => function ( ...$args ) {
				return $this->get_term_slug_by_id( ...$args ); },
			'portable_synced_meta_value'        => function ( ...$args ) {
				return $this->portable_synced_meta_value( ...$args ); },
			'remote_post'                       => function ( ...$args ) {
				return $this->remote_post( ...$args ); },
			'replace_term_acf_media_references' => function ( ...$args ) {
				return $this->replace_term_acf_media_references( ...$args ); },
			'resolve_synced_media_references'   => function ( ...$args ) {
				return $this->resolve_synced_media_references( ...$args ); },
			'resolve_synced_term'               => function ( ...$args ) {
				return $this->resolve_synced_term( ...$args ); },
			'update_mapped_synced_term'         => function ( ...$args ) {
				return $this->update_mapped_synced_term( ...$args ); },
			'upload_images_to_remote'           => function ( ...$args ) {
				return $this->upload_images_to_remote( ...$args ); },
		];
		return apply_filters( 'rsl_ie_sync_terms_operation', \RockStarLab\ImportExport\Abilities\Standalone_Sync_Policy::denied(), 'search_local_terms', [ $input ], $helpers );
	}

	/**
	 * Get public post types that support comments.
	 *
	 * @return string[]
	 */
	private function get_commentable_sync_post_types() {
		$post_types = get_post_types(
			array(
				'public' => true,
			),
			'names'
		);

		$post_types = array_values(
			array_filter(
				(array) $post_types,
				static function ( $post_type ) {
					return 'attachment' !== $post_type && post_type_supports( $post_type, 'comments' );
				}
			)
		);

		return ! empty( $post_types ) ? $post_types : array( 'post', 'page' );
	}

	/**
	 * Return commentable post types for comments vs WooCommerce reviews.
	 *
	 * @param string $comment_type Comment subtype.
	 * @param string $requested_post_type Requested post type.
	 * @return string|array
	 */
	private function get_commentable_sync_post_types_for_context( $comment_type, $requested_post_type = 'any' ) {
		if ( 'review' === sanitize_key( (string) $comment_type ) ) {
			return post_type_exists( 'product' ) ? 'product' : '__rsl_ie_no_post_type';
		}

		$requested_post_type = sanitize_key( (string) $requested_post_type );
		if ( 'product' === $requested_post_type ) {
			return '__rsl_ie_no_post_type';
		}
		if ( '' !== $requested_post_type && 'any' !== $requested_post_type ) {
			return $requested_post_type;
		}

		return array_values( array_diff( $this->get_commentable_sync_post_types(), array( 'product' ) ) );
	}

	/**
	 * Get product IDs for separating normal comments from WooCommerce reviews.
	 *
	 * @param mixed $language Language.
	 * @return int[]
	 */
	private function get_product_post_ids_for_comment_filter( $language = '' ) {
		if ( ! post_type_exists( 'product' ) ) {
			return array();
		}

		$args = array(
			'post_type'              => 'product',
			'post_status'            => 'any',
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'suppress_filters'       => false,
		);

		$language = sanitize_key( (string) $language );
		if ( '' !== $language && 'all' !== $language ) {
			$args['lang'] = $language;
		} elseif ( 'all' === $language ) {
			$args['suppress_filters'] = true;
		}

		return array_values( array_map( 'absint', get_posts( $args ) ) );
	}

	/**
	 * Check whether a comment belongs to the requested sync entity.
	 *
	 * @param \WP_Comment $comment      Comment object.
	 * @param string      $comment_type Requested comment subtype.
	 * @return bool
	 */
	private function is_comment_in_sync_context( $comment, $comment_type = '' ) {
		$post      = get_post( (int) $comment->comment_post_ID );
		$post_type = $post ? (string) $post->post_type : '';

		if ( 'review' === sanitize_key( (string) $comment_type ) ) {
			return 'review' === (string) $comment->comment_type && 'product' === $post_type;
		}

		return 'review' !== (string) $comment->comment_type && 'product' !== $post_type;
	}

	/**
	 * Get taxonomy terms from a remote site for Browse dialogs.
	 *
	 * @param array $input Parsed operation input.
	 * @return array|\WP_Error
	 */
	private function get_remote_terms( array $input ) {
		$helpers = [
			'collect_term_acf_images_for_sync'  => function ( $info, $term, $taxonomy, &$images ) {
				return $this->collect_term_acf_images_for_sync( $info, $term, $taxonomy, $images ); },
			'download_remote_images_for_sync'   => function ( ...$args ) {
				return $this->download_remote_images_for_sync( ...$args ); },
			'export_term_acf_sync_value'        => function ( ...$args ) {
				return $this->export_term_acf_sync_value( ...$args ); },
			'get_term_acf_field_objects'        => function ( ...$args ) {
				return $this->get_term_acf_field_objects( ...$args ); },
			'get_term_parent_path'              => function ( ...$args ) {
				return $this->get_term_parent_path( ...$args ); },
			'get_term_slug_by_id'               => function ( ...$args ) {
				return $this->get_term_slug_by_id( ...$args ); },
			'portable_synced_meta_value'        => function ( ...$args ) {
				return $this->portable_synced_meta_value( ...$args ); },
			'remote_post'                       => function ( ...$args ) {
				return $this->remote_post( ...$args ); },
			'replace_term_acf_media_references' => function ( ...$args ) {
				return $this->replace_term_acf_media_references( ...$args ); },
			'resolve_synced_media_references'   => function ( ...$args ) {
				return $this->resolve_synced_media_references( ...$args ); },
			'resolve_synced_term'               => function ( ...$args ) {
				return $this->resolve_synced_term( ...$args ); },
			'update_mapped_synced_term'         => function ( ...$args ) {
				return $this->update_mapped_synced_term( ...$args ); },
			'upload_images_to_remote'           => function ( ...$args ) {
				return $this->upload_images_to_remote( ...$args ); },
		];
		return apply_filters( 'rsl_ie_sync_terms_operation', \RockStarLab\ImportExport\Abilities\Standalone_Sync_Policy::denied(), 'get_remote_terms', [ $input ], $helpers );
	}

	/**
	 * Push selected local taxonomy terms to a remote site.
	 *
	 * @param array $input Parsed operation input.
	 * @return array|\WP_Error
	 */
	private function push_terms( array $input ) {
		$helpers = [
			'collect_term_acf_images_for_sync'  => function ( $info, $term, $taxonomy, &$images ) {
				return $this->collect_term_acf_images_for_sync( $info, $term, $taxonomy, $images ); },
			'download_remote_images_for_sync'   => function ( ...$args ) {
				return $this->download_remote_images_for_sync( ...$args ); },
			'export_term_acf_sync_value'        => function ( ...$args ) {
				return $this->export_term_acf_sync_value( ...$args ); },
			'get_term_acf_field_objects'        => function ( ...$args ) {
				return $this->get_term_acf_field_objects( ...$args ); },
			'get_term_parent_path'              => function ( ...$args ) {
				return $this->get_term_parent_path( ...$args ); },
			'get_term_slug_by_id'               => function ( ...$args ) {
				return $this->get_term_slug_by_id( ...$args ); },
			'portable_synced_meta_value'        => function ( ...$args ) {
				return $this->portable_synced_meta_value( ...$args ); },
			'remote_post'                       => function ( ...$args ) {
				return $this->remote_post( ...$args ); },
			'replace_term_acf_media_references' => function ( ...$args ) {
				return $this->replace_term_acf_media_references( ...$args ); },
			'resolve_synced_media_references'   => function ( ...$args ) {
				return $this->resolve_synced_media_references( ...$args ); },
			'resolve_synced_term'               => function ( ...$args ) {
				return $this->resolve_synced_term( ...$args ); },
			'update_mapped_synced_term'         => function ( ...$args ) {
				return $this->update_mapped_synced_term( ...$args ); },
			'upload_images_to_remote'           => function ( ...$args ) {
				return $this->upload_images_to_remote( ...$args ); },
		];
		return apply_filters( 'rsl_ie_sync_terms_operation', \RockStarLab\ImportExport\Abilities\Standalone_Sync_Policy::denied(), 'push_terms', [ $input ], $helpers );
	}

	/**
	 * Pull selected taxonomy terms from a remote site into this site.
	 *
	 * @param array $input Parsed operation input.
	 * @return array|\WP_Error
	 */
	private function pull_terms( array $input ) {
		$helpers = [
			'collect_term_acf_images_for_sync'  => function ( $info, $term, $taxonomy, &$images ) {
				return $this->collect_term_acf_images_for_sync( $info, $term, $taxonomy, $images ); },
			'download_remote_images_for_sync'   => function ( ...$args ) {
				return $this->download_remote_images_for_sync( ...$args ); },
			'export_term_acf_sync_value'        => function ( ...$args ) {
				return $this->export_term_acf_sync_value( ...$args ); },
			'get_term_acf_field_objects'        => function ( ...$args ) {
				return $this->get_term_acf_field_objects( ...$args ); },
			'get_term_parent_path'              => function ( ...$args ) {
				return $this->get_term_parent_path( ...$args ); },
			'get_term_slug_by_id'               => function ( ...$args ) {
				return $this->get_term_slug_by_id( ...$args ); },
			'portable_synced_meta_value'        => function ( ...$args ) {
				return $this->portable_synced_meta_value( ...$args ); },
			'remote_post'                       => function ( ...$args ) {
				return $this->remote_post( ...$args ); },
			'replace_term_acf_media_references' => function ( ...$args ) {
				return $this->replace_term_acf_media_references( ...$args ); },
			'resolve_synced_media_references'   => function ( ...$args ) {
				return $this->resolve_synced_media_references( ...$args ); },
			'resolve_synced_term'               => function ( ...$args ) {
				return $this->resolve_synced_term( ...$args ); },
			'update_mapped_synced_term'         => function ( ...$args ) {
				return $this->update_mapped_synced_term( ...$args ); },
			'upload_images_to_remote'           => function ( ...$args ) {
				return $this->upload_images_to_remote( ...$args ); },
		];
		return apply_filters( 'rsl_ie_sync_terms_operation', \RockStarLab\ImportExport\Abilities\Standalone_Sync_Policy::denied(), 'pull_terms', [ $input ], $helpers );
	}

	/**
	 * Get comments from a remote site for Browse dialogs.
	 *
	 * @param array $input Parsed operation input.
	 * @return array|\WP_Error
	 */
	private function get_remote_comments( array $input ) {
		$site_id      = $this->param( $input, 'site_id', 0 );
		$comment_type = sanitize_key( (string) $this->param( $input, 'comment_type', '' ) );
		$search       = sanitize_text_field( (string) $this->param( $input, 'search', '' ) );
		$post_id      = absint( $this->param( $input, 'post_id', 0 ) );
		$page         = absint( $this->param( $input, 'page', 1 ) );
		$per_page     = absint( $this->param( $input, 'per_page', 20 ) );
		$language     = sanitize_key( (string) $this->param( $input, 'language', '' ) );

		if ( empty( $site_id ) ) {
			return $this->error( __( 'Site ID is required', 'import-export-by-rockstarlab' ) );
		}

		$site = Connected_Site::get_by_id( $site_id );
		if ( ! $site ) {
			return $this->error( __( 'Site not found', 'import-export-by-rockstarlab' ) );
		}

		$response = $this->remote_post(
			$site,
			'list-comments',
			array(
				'comment_type' => $comment_type,
				'search'       => $search,
				'post_id'      => $post_id,
				'page'         => max( 1, $page ),
				'per_page'     => min( max( 1, $per_page ), 100 ),
				'language'     => $language,
			),
			30
		);

		if ( is_wp_error( $response ) ) {
			return $this->error( $response->get_error_message() );
		}

		return $this->success( $response );
	}

	/**
	 * Push selected local comments to a remote site.
	 *
	 * @param array $input Parsed operation input.
	 * @return array|\WP_Error
	 */
	private function push_comments( array $input ) {
		$site_id        = $this->param( $input, 'site_id', 0 );
		$comment_ids    = array_values( array_filter( array_unique( array_map( 'absint', $this->array_param( $input, 'comment_ids', array() ) ) ) ) );
		$target_post_id = absint( $this->param( $input, 'target_post_id', 0 ) );
		$comment_type   = sanitize_key( (string) $this->param( $input, 'comment_type', '' ) );
		$is_migration   = filter_var( $this->param( $input, 'migration', false ), FILTER_VALIDATE_BOOLEAN );

		if ( empty( $site_id ) ) {
			return $this->error( __( 'Site ID is required', 'import-export-by-rockstarlab' ) );
		}

		if ( empty( $comment_ids ) ) {
			return $this->error( __( 'No comments selected.', 'import-export-by-rockstarlab' ) );
		}

		if ( $target_post_id <= 0 && ! $is_migration && empty( $input['post_mapping'] ) ) {
			return $this->error( __( 'Please select a destination post on the remote site before pushing comments.', 'import-export-by-rockstarlab' ) );
		}

		$site = Connected_Site::get_by_id( $site_id );
		if ( ! $site ) {
			return $this->error( __( 'Site not found', 'import-export-by-rockstarlab' ) );
		}

		$comments = array();
		$images   = array();
		foreach ( $comment_ids as $comment_id ) {
			$comment = get_comment( $comment_id );
			if ( $comment instanceof \WP_Comment ) {
				if ( ! $this->is_comment_in_sync_context( $comment, $comment_type ) ) {
					continue;
				}

				$comment_data = $this->prepare_standalone_comment_for_sync( $comment );
				$comments[]   = $comment_data;
				$this->collect_comment_acf_images_for_sync( $comment_data, $images );
			}
		}

		if ( empty( $comments ) ) {
			return $this->error( __( 'No valid comments selected.', 'import-export-by-rockstarlab' ) );
		}

		if ( $this->standalone_domain ) {
			if ( ! \RockStarLab\ImportExport\Abilities\Standalone_Sync_Policy::selection( $comments, $comment_ids, 'comment_ID' ) ) {
				return \RockStarLab\ImportExport\Abilities\Standalone_Sync_Policy::denied(); }
			$targets = $target_post_id ? [ $target_post_id ] : array_values( (array) ( $input['post_mapping'] ?? [] ) );
			$targets = array_values( array_unique( array_map( 'intval', $targets ) ) );
			$remote  = $this->remote_post( $site, 'send-content', [ 'post_ids' => $targets ] );
			$posts   = is_wp_error( $remote ) ? [] : ( $remote['data']['posts'] ?? [] );
			if ( ! \RockStarLab\ImportExport\Abilities\Standalone_Sync_Policy::selection( $posts, $targets, 'ID' ) ) {
				return \RockStarLab\ImportExport\Abilities\Standalone_Sync_Policy::denied(); }
			foreach ( $posts as $post ) {
				if ( $post['post_type'] !== $input['post_type'] ) {
					return \RockStarLab\ImportExport\Abilities\Standalone_Sync_Policy::denied(); }
			}
		}

		$users = Sync_Comment_Users::prepare( $comments, $input['comment_user_mapping'] ?? null, false, $site );
		if ( is_wp_error( $users ) ) {
			return $users;
		}

		$image_sources = $images;
		$image_map     = $this->upload_images_to_remote( array_values( $images ), $site );
		$comments      = $this->replace_comment_acf_media_references( $comments, Site_URL::current_request_site_url(), $site['remote_url'], $image_map, $image_sources );

		$result = $this->remote_post(
			$site,
			'receive-comments',
			array(
				'comments'       => $comments,
				'image_map'      => $image_map,
				'image_sources'  => $image_sources,
				'target_post_id' => $target_post_id,
				'post_mapping'   => (array) ( $input['post_mapping'] ?? [] ),
			) + ( isset( $input['comment_user_mapping'] ) ? [ 'comment_user_mapping' => $input['comment_user_mapping'] ] : [] ),
			60
		);

		if ( is_wp_error( $result ) ) {
			return $this->error( $result->get_error_message() );
		}

		if ( isset( $input['comment_user_mapping'] ) && 1 !== ( $result['comment_users_version'] ?? null ) ) {
			return Migration_Sync_Maps::error();
		}
		return $this->success(
			array(
				'message' => isset( $result['message'] ) ? $result['message'] : __( 'Comments pushed successfully.', 'import-export-by-rockstarlab' ),
				'created' => isset( $result['created'] ) ? (int) $result['created'] : 0,
				'updated' => isset( $result['updated'] ) ? (int) $result['updated'] : 0,
				'failed'  => isset( $result['failed'] ) ? (int) $result['failed'] : 0,
				'mapping' => $result['mapping'] ?? [],
			)
		);
	}

	/**
	 * Pull selected comments from a remote site into this site.
	 *
	 * @param array $input Parsed operation input.
	 * @return array|\WP_Error
	 */
	private function pull_comments( array $input ) {
		$site_id        = $this->param( $input, 'site_id', 0 );
		$comment_ids    = array_values( array_filter( array_unique( array_map( 'absint', $this->array_param( $input, 'comment_ids', array() ) ) ) ) );
		$target_post_id = absint( $this->param( $input, 'target_post_id', 0 ) );
		$post_mapping   = $this->param( $input, 'post_mapping', array() );
		$comment_type   = sanitize_key( (string) $this->param( $input, 'comment_type', '' ) );
		$language       = sanitize_key( (string) $this->param( $input, 'language', '' ) );

		if ( is_string( $post_mapping ) ) {
			$post_mapping = json_decode( $post_mapping, true );
		}
		if ( ! is_array( $post_mapping ) ) {
			$post_mapping = array();
		}

		if ( empty( $site_id ) ) {
			return $this->error( __( 'Site ID is required', 'import-export-by-rockstarlab' ) );
		}

		if ( empty( $comment_ids ) ) {
			return $this->error( __( 'No remote comments selected.', 'import-export-by-rockstarlab' ) );
		}

		$site = Connected_Site::get_by_id( $site_id );
		if ( ! $site ) {
			return $this->error( __( 'Site not found', 'import-export-by-rockstarlab' ) );
		}

		$response = $this->remote_post(
			$site,
			'send-comments',
			array(
				'comment_ids'  => $comment_ids,
				'comment_type' => $comment_type,
				'language'     => $language,
			),
			60
		);

		if ( is_wp_error( $response ) ) {
			return $this->error( $response->get_error_message() );
		}

		$comments = isset( $response['comments'] ) && is_array( $response['comments'] ) ? $response['comments'] : array();
		if ( $this->standalone_domain ) {
			$access = \RockStarLab\ImportExport\Abilities\Standalone_Sync_Policy::comments( $comments, $input );
			if ( is_wp_error( $access ) ) {
				return $access;
			}
		}
		$users = Sync_Comment_Users::prepare( $comments, $input['comment_user_mapping'] ?? null );
		if ( is_wp_error( $users ) ) {
			return $users;
		}
		$plan = $this->standalone_comment_importer()->prepare( $comments, $post_mapping, $target_post_id, ! $this->standalone_domain );
		if ( is_wp_error( $plan ) ) {
			return $plan; }
		$remote_images = isset( $response['images'] ) && is_array( $response['images'] ) ? $response['images'] : array();
		$image_map     = $this->download_remote_images_for_sync( $remote_images, $site );
		$comments      = $this->replace_comment_acf_media_references( $comments, $site['remote_url'], Site_URL::current_request_site_url(), $image_map, $remote_images );
		$result        = $this->standalone_comment_importer()->import( $comments, $post_mapping, $target_post_id, $input['comment_user_mapping'] ?? null, ! $this->standalone_domain );
		if ( is_wp_error( $result ) ) {
			return $result; }

		$message = sprintf(
			/* translators: 1: created comments, 2: updated comments, 3: failed comments. */
			__( 'Comments pulled. Created: %1$d, Updated: %2$d, Failed: %3$d', 'import-export-by-rockstarlab' ),
			$result['created'],
			$result['updated'],
			$result['failed']
		);

		if ( ! empty( $result['errors'] ) ) {
			$message .= ' ' . reset( $result['errors'] );
		}

		return $this->success(
			array(
				'message' => $message,
				'created' => $result['created'],
				'updated' => $result['updated'],
				'failed'  => $result['failed'],
				'errors'  => $result['errors'],
				'mapping' => $result['mapping'] ?? [],
			)
		);
	}

	/**
	 * Create a Jobs Log row for immediate content sync actions.
	 *
	 * @param string $direction push|pull.
	 * @param array  $site Connected site.
	 * @param array  $post_ids Selected post IDs.
	 * @param array  $post_mapping Explicit source/target mapping.
	 * @return int
	 * @throws \RuntimeException When the history row cannot be created.
	 */
	private function create_content_sync_job( $direction, $site, $post_ids, $post_mapping ) {
		$direction = 'pull' === $direction ? 'pull' : 'push';
		$edition   = Sync_Policy::class === $this->policy_class && in_array( $this->input['post_type'] ?? 'post', [ 'post', 'page' ], true ) ? 'free' : 'pro';
		$scheduler = new Sync_Job_Service();
		$provider  = $scheduler->provider( $edition );
		$operation = 'sync-' . $direction;
		$replay    = null;
		if ( is_array( $provider ) && isset( $provider['definitions'][ $operation ] ) ) {
			$replay = [
				'sync_queue_version'     => 1,
				'edition'                => $edition,
				'operation'              => $operation,
				'input'                  => array_intersect_key( $this->input, $provider['definitions'][ $operation ]['input']['properties'] ),
				'connection_fingerprint' => $scheduler->fingerprint( $site ),
			];
		}
		$job_id = rsl_ie()->Model->job->create(
			array(
				'type'            => 'push' === $direction ? 'content_sync_push' : 'content_sync_pull',
				'data_type'       => $this->input['post_type'] ?? ( 1 === count( array_unique( array_map( 'get_post_type', (array) $post_ids ) ) ) && 'page' === get_post_type( (int) reset( $post_ids ) ) && 'push' === $direction ? 'page' : 'post' ),
				'file_format'     => 'json',
				'status'          => 'processing',
				'user_id'         => get_current_user_id(),
				'total_items'     => count( $post_ids ),
				'processed_items' => 0,
				'started_at'      => current_time( 'mysql' ),
				'parameters'      => wp_json_encode(
					array(
						'schedule_replay'      => $replay,
						'sync_ability_version' => $this->free_scope ? $this->policy_class::version() : 0,
						'execution_context'    => \RockStarLab\ImportExport\Abilities\Execution_Context::capture()->to_storage(),
						'direction'            => $direction,
						'site_id'              => isset( $site['id'] ) ? (int) $site['id'] : 0,
						'remote_url'           => isset( $site['remote_url'] ) ? (string) $site['remote_url'] : '',
						'post_ids'             => array_values( array_map( 'absint', $post_ids ) ),
						'post_mapping'         => $post_mapping,
					)
				),
			)
		);

		$this->job_id = is_wp_error( $job_id ) ? 0 : (int) $job_id;
		if ( ! $this->job_id ) {
			throw new \RuntimeException( 'Content sync history could not be created.' );
		}
		do_action( 'rsl_ie_content_sync_job_created', $this->job_id );
		return $this->job_id;
	}

	/**
	 * Mark a content sync job complete with debug details.
	 *
	 * @param int   $job_id Job ID.
	 * @param int   $processed Processed count.
	 * @param array $result Debug result.
	 * @return void
	 */
	private function complete_content_sync_job( $job_id, $processed, $result ) {
		$this->sync_result = array_intersect_key( $result, array_flip( [ 'direction', 'selected_ids', 'post_mapping', 'created', 'updated', 'failed', 'images_synced', 'image_map', 'variation_map', 'posts' ] ) );
		$job_id            = absint( $job_id );
		if ( $job_id <= 0 ) {
			return;
		}

		$job                         = rsl_ie()->Model->job->find( $job_id );
		$total                       = $job ? (int) $job->total_items : (int) $processed;
		$failed                      = max( 0, $total - (int) $processed );
		$this->sync_result['failed'] = $failed;
		if ( $this->free_scope ) {
			$result = $this->sync_result;
		}
		rsl_ie()->Model->job->update(
			$job_id,
			array(
				'status'          => $failed ? 'failed' : ( apply_filters( 'rsl_ie_content_sync_history_pending', false, $job_id ) ? 'processing' : 'completed' ),
				'failed_items'    => $failed,
				'error_items'     => $failed,
				'progress'        => 100,
				'total_items'     => $total,
				'processed_items' => (int) $processed,
				'success_items'   => (int) $processed,
				'imported_items'  => isset( $result['created'] ) ? (int) $result['created'] : (int) $processed,
				'completed_at'    => ! $failed && apply_filters( 'rsl_ie_content_sync_history_pending', false, $job_id ) ? null : current_time( 'mysql' ),
				'updated_at'      => current_time( 'mysql' ),
				'result'          => wp_json_encode( $result ),
			)
		);
	}

	/**
	 * Mark a content sync job failed.
	 *
	 * @param int    $job_id Job ID.
	 * @param string $message Error message.
	 * @return void
	 */
	private function fail_content_sync_job( $job_id, $message ) {
		if ( $this->free_scope ) {
			$message = __( 'Sync failed. Check the connection and selection; some content may already have changed.', 'import-export-by-rockstarlab' );
		}
		$job_id = absint( $job_id );
		if ( $job_id <= 0 ) {
			return;
		}

		rsl_ie()->Model->job->update(
			$job_id,
			array(
				'status'       => 'failed',
				'failed_items' => 1,
				'error_items'  => 1,
				'completed_at' => current_time( 'mysql' ),
				'updated_at'   => current_time( 'mysql' ),
				'result'       => wp_json_encode(
					array(
						'error' => (string) $message,
					)
				),
			)
		);
	}

	/**
	 * POST to a connected site's Content Sync REST API and decode response.
	 *
	 * @param array  $site Connected site record.
	 * @param string $endpoint Remote endpoint without namespace.
	 * @param array  $payload JSON payload.
	 * @param int    $timeout Request timeout.
	 * @return array|\WP_Error
	 */
	private function remote_post( $site, $endpoint, $payload, $timeout = 30 ) {
		$response = \RockStarLab\ImportExport\Helper\Remote_API::post(
			$site['remote_url'],
			$endpoint,
			array(
				'timeout' => $timeout,
				'headers' => array(
					'Authorization' => 'Bearer ' . $site['api_key'],
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'rsl_ie_remote_error', __( 'Failed to connect to remote site: ', 'import-export-by-rockstarlab' ) . $response->get_error_message() );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		if ( 200 !== (int) $status_code ) {
			$message = $this->get_remote_error_message( $data, $status_code );
			return new \WP_Error( 'rsl_ie_remote_status', $message );
		}

		if ( ! is_array( $data ) || empty( $data['success'] ) ) {
			return new \WP_Error( 'rsl_ie_remote_invalid', __( 'Remote site returned invalid data.', 'import-export-by-rockstarlab' ) );
		}

		return $data;
	}

	/**
	 * Extract a readable message from a remote REST error response.
	 *
	 * @param mixed $data Remote decoded response.
	 * @param int   $status_code HTTP status code.
	 * @return string
	 */
	private function get_remote_error_message( $data, $status_code ) {
		if ( is_array( $data ) ) {
			if ( ! empty( $data['message'] ) && is_scalar( $data['message'] ) ) {
				return (string) $data['message'];
			}

			if ( ! empty( $data['data']['message'] ) && is_scalar( $data['data']['message'] ) ) {
				return (string) $data['data']['message'];
			}

			if ( ! empty( $data['code'] ) && is_scalar( $data['code'] ) ) {
				return (string) $data['code'];
			}
		}

		return sprintf(
			/* translators: %d: HTTP status code. */
			__( 'Request failed with status code: %d', 'import-export-by-rockstarlab' ),
			(int) $status_code
		);
	}

	/**
	 * Build a local-to-remote post map by exact title and post type.
	 *
	 * @param array $input Parsed operation input.
	 * @return array|\WP_Error
	 */
	private function auto_map_by_title( array $input ) {
		$site_id  = $this->param( $input, 'site_id', 0 );
		$post_ids = $this->array_param( $input, 'post_ids', array() );

		if ( empty( $site_id ) ) {
			return $this->error( __( 'Site ID is required', 'import-export-by-rockstarlab' ) );
		}

		if ( empty( $post_ids ) || ! is_array( $post_ids ) ) {
			return $this->error( __( 'No posts selected', 'import-export-by-rockstarlab' ) );
		}

		$site = Connected_Site::get_by_id( $site_id );
		if ( ! $site ) {
			return $this->error( __( 'Site not found', 'import-export-by-rockstarlab' ) );
		}

		$mapping   = array();
		$matches   = array();
		$ambiguous = array();
		$index     = $this->remote_title_index( $site, $post_ids );
		if ( is_wp_error( $index ) ) {
			return $index;
		}

		foreach ( $post_ids as $post_id ) {
			$post_id = absint( $post_id );
			if ( ! $post_id ) {
				continue;
			}

			// Long-running callers (an AI agent through the stdio MCP server, WP-CLI) may hold an old copy.
			clean_post_cache( $post_id );
			$post = get_post( $post_id );
			if ( ! $post ) {
				continue;
			}

			$remote_post = isset( $index[ $post->post_type ] )
				? $this->match_from_title_index( $index[ $post->post_type ], $post )
				: $this->find_remote_post_by_exact_title( $site, $post );
			// Several remote posts with this title: leave this post for a manual choice, keep matching the rest.
			if ( is_wp_error( $remote_post ) && 'rsl_ie_sync_ambiguous_title' === $remote_post->get_error_code() ) {
				$mapping[ $post_id ] = null;
				$ambiguous[]         = array(
					'local_id'   => $post_id,
					'title'      => wp_strip_all_tags( $post->post_title ),
					'remote_ids' => array_map( 'absint', (array) ( $remote_post->get_error_data()['remote_ids'] ?? array() ) ),
					'message'    => $remote_post->get_error_message(),
				);
				continue;
			}
			if ( is_wp_error( $remote_post ) ) {
				return $remote_post;
			}
			if ( ! $remote_post ) {
				$mapping[ $post_id ] = null;
				continue;
			}

			$remote_id = isset( $remote_post['ID'] ) ? absint( $remote_post['ID'] ) : 0;
			if ( ! $remote_id ) {
				$mapping[ $post_id ] = null;
				continue;
			}

			$mapping[ $post_id ] = $remote_id;
			$matches[ $post_id ] = array(
				'ID'            => $remote_id,
				'post_title'    => isset( $remote_post['post_title'] ) ? sanitize_text_field( $remote_post['post_title'] ) : '',
				'post_type'     => isset( $remote_post['post_type'] ) ? sanitize_key( $remote_post['post_type'] ) : '',
				'post_date'     => isset( $remote_post['post_date'] ) ? sanitize_text_field( $remote_post['post_date'] ) : '',
				'post_modified' => isset( $remote_post['post_modified'] ) ? sanitize_text_field( $remote_post['post_modified'] ) : '',
			);
		}

		return $this->success(
			array(
				'mapping'   => $mapping,
				'matches'   => $matches,
				'ambiguous' => $ambiguous,
			)
		);
	}

	/**
	 * List remote posts once per post type when that costs fewer requests than one title search per post.
	 *
	 * Large selections otherwise send one remote search per post and keep the connection busy for minutes.
	 *
	 * @param array $site     Connected site data.
	 * @param array $post_ids Selected local post IDs.
	 * @return array|\WP_Error Post type => normalized title => remote candidates.
	 */
	private function remote_title_index( $site, array $post_ids ) {
		$counts = array();
		foreach ( $post_ids as $post_id ) {
			$type = get_post_type( absint( $post_id ) );
			if ( $type ) {
				$counts[ $type ] = ( $counts[ $type ] ?? 0 ) + 1;
			}
		}
		$index    = array();
		$per_page = 100;
		foreach ( $counts as $type => $count ) {
			if ( $count < 5 ) {
				continue;
			}
			$titles = array();
			$page   = 1;
			do {
				$data = $this->remote_post(
					$site,
					'list-posts',
					[
						'post_type'        => $type,
						'status'           => '',
						'include_children' => 1,
						'page'             => $page,
						'per_page'         => $per_page,
					],
					60
				);
				if ( is_wp_error( $data ) ) {
					return $data;
				}
				if ( ! isset( $data['posts'] ) || ! is_array( $data['posts'] ) ) {
					return $this->error( __( 'Remote search returned invalid data.', 'import-export-by-rockstarlab' ) );
				}
				$pages = isset( $data['pages'] ) ? (int) $data['pages'] : ( count( $data['posts'] ) < $per_page ? $page : $page + 1 );
				// Listing must stay cheaper than searching each selected post.
				if ( 1 === $page && $pages >= $count ) {
					continue 2;
				}
				foreach ( $data['posts'] as $candidate ) {
					if ( is_array( $candidate ) && ( $candidate['post_type'] ?? '' ) === $type ) {
						$titles[ $this->normalize_post_title_for_mapping( $candidate['post_title'] ?? '' ) ][] = $candidate;
					}
				}
				++$page;
			} while ( $page <= $pages && $data['posts'] );
			$index[ $type ] = $titles;
		}
		return $index;
	}

	/**
	 * Match one local post against a prebuilt remote title index.
	 *
	 * @param array    $titles Normalized title => remote candidates.
	 * @param \WP_Post $post   Local post object.
	 * @return array|null|\WP_Error Matching remote post data, or null.
	 */
	private function match_from_title_index( array $titles, $post ) {
		$candidates = $titles[ $this->normalize_post_title_for_mapping( $post->post_title ) ] ?? array();
		$ids        = array_unique( array_map( 'intval', wp_list_pluck( $candidates, 'ID' ) ) );
		if ( count( $ids ) > 1 ) {
			return new \WP_Error( 'rsl_ie_sync_ambiguous_title', sprintf( /* translators: %s: post title. */ __( 'Several posts on the remote site are titled "%s". Choose the right one in the list for this post.', 'import-export-by-rockstarlab' ), wp_strip_all_tags( $post->post_title ) ), [ 'status' => 409, 'remote_ids' => array_values( $ids ) ] );
		}
		return $candidates ? $candidates[0] : null;
	}

	/**
	 * Find a matching remote post by exact normalized title and post type.
	 *
	 * @param array    $site Connected site data.
	 * @param \WP_Post $post Local post object.
	 * @return array|null Matching remote post data, or null.
	 */
	private function find_remote_post_by_exact_title( $site, $post ) {
		$match       = null;
		$local_title = $this->normalize_post_title_for_mapping( $post->post_title );
		for ( $page = 1; $page <= 10; ++$page ) {
			$data = $this->remote_post(
				$site,
				'list-posts',
				[
					'post_type' => $post->post_type,
					'search'    => $post->post_title,
					'status'    => '',
					'page'      => $page,
					'per_page'  => 50,
				]
			);
			if ( is_wp_error( $data ) ) {
				return $data;
			}
			if ( ! isset( $data['posts'] ) || ! is_array( $data['posts'] ) ) {
				return $this->error( __( 'Remote search returned invalid data.', 'import-export-by-rockstarlab' ) );
			}
			foreach ( $data['posts'] as $candidate ) {
				if ( is_array( $candidate ) && ( $candidate['post_type'] ?? '' ) === $post->post_type && $this->normalize_post_title_for_mapping( $candidate['post_title'] ?? '' ) === $local_title ) {
					if ( $match && (int) $match['ID'] !== (int) $candidate['ID'] ) {
						return new \WP_Error( 'rsl_ie_sync_ambiguous_title', sprintf( /* translators: %s: post title. */ __( 'Several posts on the remote site are titled "%s". Choose the right one in the list for this post.', 'import-export-by-rockstarlab' ), wp_strip_all_tags( $post->post_title ) ), [ 'status' => 409, 'remote_ids' => [ (int) $match['ID'], (int) $candidate['ID'] ] ] );
					}
					$match = $candidate;
				}
			}
			$pages = isset( $data['pages'] ) ? (int) $data['pages'] : ( count( $data['posts'] ) < 50 ? $page : $page + 1 );
			if ( $page >= $pages ) {
				return $match;
			}
		}
		return new \WP_Error( 'rsl_ie_sync_search_incomplete', __( 'Remote search is too broad. Choose an explicit mapping.', 'import-export-by-rockstarlab' ), [ 'status' => 409 ] );
	}

	/**
	 * Normalize titles before exact matching.
	 *
	 * @param string $title Post title.
	 * @return string Normalized title.
	 */
	private function normalize_post_title_for_mapping( $title ) {
		$title = html_entity_decode( wp_strip_all_tags( (string) $title ), ENT_QUOTES, get_bloginfo( 'charset' ) );
		$title = preg_replace( '/\s+/u', ' ', trim( $title ) );

		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $title, get_bloginfo( 'charset' ) ) : strtolower( $title );
	}

	/**
	 * Get children posts from remote site
	 *
	 * @param array $input Parsed operation input.
	 * @return array|\WP_Error
	 */
	private function get_children_posts( array $input ) {
		$site_id   = $this->param( $input, 'site_id', 0 );
		$parent_id = $this->param( $input, 'parent_id', 0 );
		$post_type = $this->param( $input, 'post_type', '' );
		$language  = sanitize_key( (string) $this->param( $input, 'language', '' ) );

		// Validate input.
		if ( empty( $site_id ) ) {
			return $this->error( __( 'Site ID is required', 'import-export-by-rockstarlab' ) );
		}

		if ( empty( $parent_id ) ) {
			return $this->error( __( 'Parent ID is required', 'import-export-by-rockstarlab' ) );
		}

		// Get site details.
		$site = Connected_Site::get_by_id( $site_id );
		if ( ! $site ) {
			return $this->error( __( 'Site not found', 'import-export-by-rockstarlab' ) );
		}

			// Request children posts from remote site.
			$response = \RockStarLab\ImportExport\Helper\Remote_API::post(
				$site['remote_url'],
				'get-children-posts',
				array(
					'timeout' => 30,
					'headers' => array(
						'Authorization' => 'Bearer ' . $site['api_key'],
						'Content-Type'  => 'application/json',
					),
					'body'    => wp_json_encode(
						array(
							'parent_id' => $parent_id,
							'post_type' => $post_type,
							'language'  => $language,
						)
					),
				// translators: %s = content placeholder.
				)
			);

		if ( is_wp_error( $response ) ) {
			return $this->error( __( 'Failed to connect to remote site: ', 'import-export-by-rockstarlab' ) . $response->get_error_message() );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );

		if ( 200 !== $status_code ) {
			$error_data = json_decode( $body, true );
			// translators: %d is a dynamic value.
			$error_msg = isset( $error_data['message'] ) ? $error_data['message'] : sprintf( __( 'Request failed with status code: %d', 'import-export-by-rockstarlab' ), $status_code );
			return $this->error( $error_msg );
		}

		$data = json_decode( $body, true );
		if ( ! isset( $data['success'] ) || ! $data['success'] || ! isset( $data['children'] ) ) {
			return $this->error( __( 'Remote site returned invalid data', 'import-export-by-rockstarlab' ) );
		}

		return $this->success(
			array(
				'children' => $data['children'],
			)
		);
	}

	/**
	 * Get local posts info
	 *
	 * @param array $input Parsed operation input.
	 * @return array|\WP_Error
	 */
	private function get_local_posts_info( array $input ) {
		$post_ids = $this->array_param( $input, 'post_ids', array() );

		// Validate input.
		if ( empty( $post_ids ) || ! is_array( $post_ids ) ) {
			return $this->error( __( 'Post IDs are required', 'import-export-by-rockstarlab' ) );
		}

		$posts_info = array();

		foreach ( $post_ids as $post_id ) {
			clean_post_cache( (int) $post_id ); // Fresh title and status for long-running callers.
			$post = get_post( $post_id );
			if ( $post ) {
				$orig         = get_post_meta( $post->ID, '_rsl_ie_original_post_id', true );
				$posts_info[] = array(
					'ID'          => $post->ID,
					'post_title'  => $post->post_title,
					'post_name'   => $post->post_name,
					'post_type'   => $post->post_type,
					'post_date'   => $post->post_date,
					'post_status' => $post->post_status,
					'original_id' => is_numeric( $orig ) ? (int) $orig : 0,
				);
			}
		}

		return $this->success(
			array(
				'posts' => $posts_info,
			)
		);
	}

	/**
	 * Push content to remote site
	 *
	 * @param array $input Parsed operation input.
	 * @return array|\WP_Error
	 */
	private function push_content( array $input ) {
		$site_id      = $this->param( $input, 'site_id', 0 );
		$post_ids     = $this->array_param( $input, 'post_ids', array() );
		$post_mapping = $this->param( $input, 'post_mapping', array() );

		// Validate input.
		if ( empty( $site_id ) ) {
			return $this->error( __( 'Site ID is required', 'import-export-by-rockstarlab' ) );
		}

		if ( empty( $post_ids ) || ! is_array( $post_ids ) ) {
			return $this->error( __( 'No posts selected', 'import-export-by-rockstarlab' ) );
		}

		// Parse post_mapping if it's a JSON string.
		if ( is_string( $post_mapping ) ) {
			$post_mapping = json_decode( $post_mapping, true );
		}
		if ( ! is_array( $post_mapping ) ) {
			$post_mapping = array();
		}

		// Get site details.
		$site = Connected_Site::get_by_id( $site_id );
		if ( ! $site ) {
			return $this->error( __( 'Site not found', 'import-export-by-rockstarlab' ) );
		}
		if ( isset( $input['hierarchy'] ) ) {
			$access = Sync_Hierarchy::probe( $site, 'post_type', $input['post_type'] );
			if ( is_wp_error( $access ) ) {
				return $access; }
		}
		$job_id = $this->create_content_sync_job( 'push', $site, $post_ids, $post_mapping );
		if ( ! $job_id ) {
			return $this->error( __( 'Could not create a sync history job.', 'import-export-by-rockstarlab' ) );
		}

		// Get source and target domains.
		$source_domain = Site_URL::current_request_site_url();
		$target_domain = $site['remote_url'];

		// Long-running processes (WP-CLI, the stdio MCP server) keep posts in the object cache;
		// read the selection fresh so edits made elsewhere since then are sent.
		foreach ( $post_ids as $cached_id ) {
			clean_post_cache( absint( $cached_id ) );
		}
		$payload       = $this->prepare_posts( $post_ids );
		$posts_data    = $payload['posts'];
		$all_images    = $payload['images'];
		$image_context = $payload['context'];

		if ( empty( $posts_data ) ) {
			return $this->error( __( 'No valid posts to sync', 'import-export-by-rockstarlab' ) );
		}

		if ( $this->free_scope ) {
			$access = $this->policy_class::remote_posts( $posts_data, array_map( 'intval', $post_ids ), $this->input['post_type'] );
			if ( is_wp_error( $access ) ) {
				return $access;
			}
		}

		$posts_data = Sync_ACF_Graph::strip( $posts_data );
		if ( is_wp_error( $posts_data ) ) {
			return $posts_data;
		}

		if ( array_key_exists( 'woo_product_mapping', $input ) ) {
			$posts_data = Sync_Product_Links::run(
				'export',
				$posts_data,
				[
					'mapping' => $input['woo_product_mapping'],
					'site'    => $site,
				]
			);
			if ( is_wp_error( $posts_data ) ) {
				return $posts_data;
			}
		}

		if ( isset( $input['author_mapping'] ) ) {
			$authors = apply_filters( 'rsl_ie_sync_post_authors', Migration_Sync_Maps::error(), $posts_data, $input['author_mapping'], false, $site );
			if ( is_wp_error( $authors ) ) {
				return $authors;
			}
		}

		$comment_users = Sync_Comment_Users::posts( $posts_data, $input['comment_user_mapping'] ?? null, false, $site );
		if ( is_wp_error( $comment_users ) ) {
			return $comment_users;
		}

		if ( isset( $input['acf_user_mapping'] ) ) {
			$posts_data = Sync_ACF_Users::run( 'export', $posts_data );
			if ( is_wp_error( $posts_data ) ) {
				return $posts_data;
			}
			$acf_users = Sync_ACF_Users::run(
				'prepare',
				$posts_data,
				[
					'mapping'  => $input['acf_user_mapping'],
					'outgoing' => true,
					'site'     => $site,
				]
			);
			if ( is_wp_error( $acf_users ) ) {
				return $acf_users;
			}
		}

		if ( isset( $input['acf_term_mapping'] ) ) {
			$posts_data = Sync_ACF_Terms::run( 'export', $posts_data );
			if ( is_wp_error( $posts_data ) ) {
				return $posts_data;
			}
			$acf_terms = Sync_ACF_Terms::run(
				'prepare',
				$posts_data,
				[
					'mapping'  => $input['acf_term_mapping'],
					'outgoing' => true,
					'site'     => $site,
				]
			);
			if ( is_wp_error( $acf_terms ) ) {
				return $acf_terms;
			}
		}

		if ( isset( $input['acf_post_mapping'] ) ) {
			$posts_data = Sync_ACF_Posts::run( 'export', $posts_data );
			if ( is_wp_error( $posts_data ) ) {
				return $posts_data;
			}
			$acf_posts = Sync_ACF_Posts::run(
				'prepare',
				$posts_data,
				[
					'mapping'  => $input['acf_post_mapping'],
					'outgoing' => true,
					'site'     => $site,
				]
			);
			if ( is_wp_error( $acf_posts ) ) {
				return $acf_posts;
			}
		}

		if ( ! empty( $input['acf_media_references'] ) ) {
			$posts_data = Sync_ACF_Media::export( $posts_data, $all_images, $site );
			if ( is_wp_error( $posts_data ) ) {
				return $posts_data;
			}
		}

		if ( array_key_exists( 'woo_product_mapping', $input ) ) {
			$bounded = Sync_Product_Links::run( 'size', $posts_data );
			if ( is_wp_error( $bounded ) ) {
				return $bounded;
			}
		}

		// Upload images to remote site first.
		$image_sources = $all_images;
		$image_map     = $this->upload_images_to_remote( array_values( $all_images ), $site );

		// Replace domains in post data.
		foreach ( $posts_data as &$post_data ) {
			$source_meta = $post_data['meta'] ?? null;
			$post_data = \RockStarLab\ImportExport\Helper\Content_Sync_Replacer::replace_post_domains(
				$post_data,
				$source_domain,
				$target_domain,
				$image_map,
				$image_sources
			);
			// The receiver owns attachment lookup and ID translation for metadata.
			// Sender-side lookup would resolve target IDs against the source DB.
			if ( is_array( $source_meta ) ) {
				$post_data['meta'] = \RockStarLab\ImportExport\Helper\Content_Sync_Replacer::replace_in_meta_public(
					$source_meta, $source_domain, $target_domain
				);
			}
		}

		unset( $post_data );

		if ( array_key_exists( 'woo_product_mapping', $input ) ) {
			$bounded = Sync_Product_Links::run( 'size', $posts_data );
			if ( is_wp_error( $bounded ) ) {
				return $bounded;
			}
		}

		// Synced patterns travel with the posts. Their images were uploaded with the post images
		// (Content_Sync_Media::extract_post_images), so links and IDs are translated the same way.
		$synced_patterns = Sync_Synced_Patterns::collect( $posts_data );
		foreach ( $synced_patterns as &$synced_pattern ) {
			$synced_pattern['content'] = \RockStarLab\ImportExport\Helper\Content_Sync_Replacer::replace_post_domains(
				array( 'post_content' => $synced_pattern['content'] ),
				$source_domain,
				$target_domain,
				$image_map,
				$image_sources
			)['post_content'];
		}
		unset( $synced_pattern );

		// Send to remote site.
		$response = \RockStarLab\ImportExport\Helper\Remote_API::post(
			$site['remote_url'],
			isset( $input['hierarchy'] ) ? 'migration-hierarchy-receive' : 'receive-content',
			array(
				'timeout' => 180,
				'headers' => array(
					'Authorization' => 'Bearer ' . $site['api_key'],
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					Sync_Hierarchy::envelope(
						'post_type',
						$input['post_type'] ?? '',
						array(
							'posts'           => $posts_data,
							'image_map'       => $image_map,
							'image_sources'   => $image_sources,
							'post_mapping'    => $post_mapping,
							'synced_patterns' => $synced_patterns,
							'block_terms'     => Sync_Synced_Patterns::collect_terms( $posts_data ),
						// translators: %s = content placeholder.
						) + ( isset( $input['author_mapping'] ) ? [ 'author_mapping' => $input['author_mapping'] ] : [] ) + ( isset( $input['comment_user_mapping'] ) ? [ 'comment_user_mapping' => $input['comment_user_mapping'] ] : [] ) + ( isset( $input['acf_user_mapping'] ) ? [ 'acf_user_mapping' => $input['acf_user_mapping'] ] : [] ) + ( isset( $input['acf_term_mapping'] ) ? [ 'acf_term_mapping' => $input['acf_term_mapping'] ] : [] ) + ( isset( $input['acf_post_mapping'] ) ? [ 'acf_post_mapping' => $input['acf_post_mapping'] ] : [] ) + ( array_key_exists( 'woo_product_mapping', $input ) ? [ 'woo_product_mapping' => $input['woo_product_mapping'] ] : [] ) + ( ! empty( $input['acf_media_references'] ) ? [ 'acf_media_version' => 1 ] : [] ),
						$input
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->fail_content_sync_job( $job_id, $response->get_error_message() );
			return $this->error( __( 'Failed to connect to remote site: ', 'import-export-by-rockstarlab' ) . $response->get_error_message() );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );

		if ( 200 !== $status_code ) {
			$error_data = json_decode( $body, true );
			// translators: %d is a dynamic value.
			$error_msg = isset( $error_data['message'] ) ? $error_data['message'] : sprintf( __( 'Push failed with status code: %d', 'import-export-by-rockstarlab' ), $status_code );
			$this->fail_content_sync_job( $job_id, $error_msg );
			return $this->error( $error_msg );
		}

		$data = json_decode( $body, true );
		if ( array_key_exists( 'woo_product_mapping', $input ) && 1 !== ( $data['woo_links_version'] ?? null ) ) {
			return Migration_Sync_Maps::error();
		}

		if ( array_key_exists( 'woo_product_mapping', $input ) ) {
			$confirmed = Sync_Product_Links::run( 'ack', $posts_data, [ 'response' => $data ] );
			if ( is_wp_error( $confirmed ) ) {
				$this->fail_content_sync_job( $job_id, $confirmed->get_error_message() );
				return $confirmed;
			}
		}

		if ( isset( $input['acf_user_mapping'] ) && 1 !== ( $data['acf_users_version'] ?? null ) ) {
			$error = Migration_Sync_Maps::error();
			$this->fail_content_sync_job( $job_id, $error->get_error_message() );
			return $error;
		}
		if ( isset( $input['acf_term_mapping'] ) && 1 !== ( $data['acf_terms_version'] ?? null ) ) {
			$error = Migration_Sync_Maps::error();
			$this->fail_content_sync_job( $job_id, $error->get_error_message() );
			return $error;
		}

		if ( ! empty( $input['acf_media_references'] ) && 1 !== ( $data['acf_media_version'] ?? null ) ) {
			$error = Migration_Sync_Maps::error();
			$this->fail_content_sync_job( $job_id, $error->get_error_message() );
			return $error;
		}
		if ( isset( $input['acf_post_mapping'] ) && 1 !== ( $data['acf_posts_version'] ?? null ) ) {
			$error = Migration_Sync_Maps::error();
			$this->fail_content_sync_job( $job_id, $error->get_error_message() );
			return $error;
		}
		if ( isset( $input['comment_user_mapping'] ) && 1 !== ( $data['comment_users_version'] ?? null ) ) {
			$error = Migration_Sync_Maps::error();
			$this->fail_content_sync_job( $job_id, $error->get_error_message() );
			return $error;
		}
		if ( isset( $input['author_mapping'] ) && 1 !== ( $data['authors_version'] ?? null ) ) {
			$error = Migration_Sync_Maps::error();
			$this->fail_content_sync_job( $job_id, $error->get_error_message() );
			return $error;
		}
		if ( isset( $input['hierarchy'] ) && 1 !== ( $data['hierarchy_version'] ?? null ) ) {
			$error = Sync_Hierarchy::error();
			$this->fail_content_sync_job( $job_id, $error->get_error_message() );
			return $error; }
		if ( ! isset( $data['success'] ) || ! $data['success'] ) {
			$this->fail_content_sync_job( $job_id, __( 'Remote site rejected the content', 'import-export-by-rockstarlab' ) );
			return $this->error( __( 'Remote site rejected the content', 'import-export-by-rockstarlab' ) );
		}
		if ( ! isset( $data['data']['total'] ) || ! is_numeric( $data['data']['total'] ) || (int) $data['data']['total'] < 0 || (int) $data['data']['total'] > count( $posts_data ) ) {
			return $this->error( __( 'The remote site did not confirm how many records were synchronized.', 'import-export-by-rockstarlab' ) );
		}
		$this->complete_content_sync_job(
			$job_id,
			(int) $data['data']['total'],
			array(
				'created'       => (int) ( $data['data']['created'] ?? 0 ),
				'updated'       => (int) ( $data['data']['updated'] ?? 0 ),
				'variation_map' => isset( $input['woo_product_mapping'] ) ? (array) ( $data['data']['variation_map'] ?? [] ) : [],
				'direction'     => 'push',
				'site_id'       => (int) $site_id,
				'remote_url'    => isset( $site['remote_url'] ) ? (string) $site['remote_url'] : '',
				'selected_ids'  => array_values( array_map( 'absint', $post_ids ) ),
				'post_mapping'  => $post_mapping,
				'images_synced' => count( $image_map ),
				'image_map'     => $image_map,
				'posts'         => array_map(
					static function ( $post_data ) use ( $data ) {
						return array_filter(
							array(
								'target_id' => isset( $data['data']['post_map'][ $post_data['ID'] ] ) ? absint( $data['data']['post_map'][ $post_data['ID'] ] ) : null,
								'source_id' => isset( $post_data['ID'] ) ? absint( $post_data['ID'] ) : 0,
								'title'     => isset( $post_data['post_title'] ) ? (string) $post_data['post_title'] : '',
								'type'      => isset( $post_data['post_type'] ) ? (string) $post_data['post_type'] : '',
								'slug'      => isset( $post_data['post_name'] ) ? (string) $post_data['post_name'] : '',
							),
							static function ( $value ) {
								return null !== $value;
							}
						);
					},
					$posts_data
				),
				'remote_result' => isset( $data['data'] ) ? $data['data'] : array(),
			)
		);

		return $this->success(
			array(
				'message'       => sprintf(
					/* translators: %d: number of posts */
					__( 'Successfully pushed %d post(s) to remote site', 'import-export-by-rockstarlab' ),
					count( $posts_data )
				),
				'images_synced' => count( $image_map ),
			)
		);
	}

	/**
	 * Upload images to remote site
	 *
	 * @param array $images Array of image data.
	 * @param array $site Site connection data.
	 * @return array Mapping of old attachment IDs to new ones
	 */
	private function upload_images_to_remote( $images, $site ) {
		$image_map = array();

		if ( empty( $images ) ) {
			return $image_map;
		}

		foreach ( $images as $image ) {
			// Send current bytes even for a potential duplicate; the receiver validates before reuse.
			$image['force_unique'] = false;
			$new_id                = $this->upload_single_image_to_remote( $image, $site );
			if ( $new_id ) {
				$image_map[ $image['attachment_id'] ] = $new_id;
			}
		}

		return $image_map;
	}

	/**
	 * Download remote image payloads and return source attachment ID => local ID map.
	 *
	 * @param array $remote_images Remote image payloads.
	 * @param array $site Connected site.
	 * @return array<int,int>
	 */
	private function download_remote_images_for_sync( $remote_images, $site ) {
		$image_map = array();
		if ( empty( $remote_images ) || ! is_array( $remote_images ) ) {
			return $image_map;
		}

		foreach ( $remote_images as $image ) {
			if ( ! is_array( $image ) || empty( $image['attachment_id'] ) ) {
				continue;
			}

			$new_attachment_id = $this->download_image_from_remote( $image, $site, $image_map, $remote_images );
			if ( $new_attachment_id ) {
				$image_map[ (int) $image['attachment_id'] ] = (int) $new_attachment_id;
			}
		}

		$this->finalize_pulled_media( $remote_images, $image_map );
		return $image_map;
	}

	/**
	 * Upload single image to remote site
	 *
	 * @param array $image Image data.
	 * @param array $site Site connection data.
	 * @return int Confirmed destination attachment ID.
	 * @throws \RuntimeException If media transfer fails before content can be sent.
	 */
	private function upload_single_image_to_remote( $image, $site ) {
		$result = ( new Media_Transport_Service() )->push_image( $image, $site );
		if ( is_wp_error( $result ) ) {
			throw new \RuntimeException( 'Content Sync media transfer failed.' );
		}
		return $result;
	}

	/**
	 * Extract external Elementor media references from pulled post payloads.
	 *
	 * This is a target-side fallback for older/source sites that do not include
	 * Elementor template media (notably SVG icon controls) in the normal images
	 * payload. The returned attachment_id is the source/builder ID so the regular
	 * image map can rewrite Elementor `{id,url}` controls after download.
	 *
	 * @param array $posts_data Remote post payloads.
	 * @return array Media items keyed by source/builder attachment ID or URL hash.
	 */
	private function extract_elementor_external_media_from_posts( $posts_data ) {
		$images = array();

		if ( empty( $posts_data ) || ! is_array( $posts_data ) ) {
			return $images;
		}

		foreach ( $posts_data as $post_data ) {
			if ( empty( $post_data['meta']['_elementor_data'] ) || ! is_string( $post_data['meta']['_elementor_data'] ) ) {
				continue;
			}

			$elementor_data = json_decode( $post_data['meta']['_elementor_data'], true );
			if ( ! is_array( $elementor_data ) ) {
				continue;
			}

			$this->collect_elementor_external_media( $elementor_data, $images );
		}

		return $images;
	}

	/**
	 * Recursively collect Elementor media URL controls.
	 *
	 * @param mixed $value  Elementor data node.
	 * @param array $images Collected media items.
	 * @return void
	 */
	private function collect_elementor_external_media( $value, &$images ) {
		if ( ! is_array( $value ) ) {
			return;
		}

		if ( isset( $value['url'] ) && is_string( $value['url'] ) && $this->is_supported_sync_media_url( $value['url'] ) ) {
			$source_attachment_id = isset( $value['id'] ) && is_numeric( $value['id'] ) ? absint( $value['id'] ) : 0;
			$key                  = $source_attachment_id > 0 ? (string) $source_attachment_id : md5( $value['url'] );

			if ( ! isset( $images[ $key ] ) ) {
				$url      = esc_url_raw( $value['url'] );
				$path     = (string) wp_parse_url( $url, PHP_URL_PATH );
				$filename = sanitize_file_name( wp_basename( $path ) );
				$ext      = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
				$filetype = wp_check_filetype( $filename );
				$mime     = ! empty( $filetype['type'] ) ? $filetype['type'] : '';
				if ( '' === $mime && 'svg' === $ext ) {
					$mime = 'image/svg+xml';
				}

					$images[ $key ] = array(
						'attachment_id' => $source_attachment_id,
						'url'           => $url,
						'source_urls'   => array(
							'full'    => $url,
							'by_size' => array(),
						),
						'file_path'     => '',
						'file_name'     => '' !== $filename ? $filename : 'elementor-media.' . ( '' !== $ext ? $ext : 'bin' ),
						'file_hash'     => '',
						'file_size'     => 0,
						'mime_type'     => $mime,
						'alt_text'      => '',
						'title'         => '' !== $filename ? preg_replace( '/\.[^.]+$/', '', $filename ) : '',
						'caption'       => '',
						'description'   => '',
						'context'       => 'elementor_external_url',
						'metadata'      => array(),
					);
			}
		}

		foreach ( $value as $child ) {
			if ( is_array( $child ) ) {
				$this->collect_elementor_external_media( $child, $images );
			}
		}
	}

		/**
		 * Check whether a URL points to a media file Content Sync can import.
		 *
		 * @param string $url Media URL.
		 * @return bool Whether the URL is supported.
		 */
	private function is_supported_sync_media_url( $url ) {
		$url = esc_url_raw( $url );
		if ( '' === $url || ! wp_http_validate_url( $url ) ) {
			return false;
		}

		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$ext  = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );

		return in_array( $ext, array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg' ), true );
	}

	/**
	 * Check whether a post meta key should be excluded from Content Sync payloads.
	 *
	 * @param string $key Meta key.
	 * @return bool Whether the key should be skipped.
	 */
	private function should_skip_synced_meta_key( $key ) {
		$key = (string) $key;

		return class_exists( '\RockStarLab\ImportExport\Helper\Elementor_Fields' )
			&& \RockStarLab\ImportExport\Helper\Elementor_Fields::is_generated_cache_key( $key );
	}

	/**
	 * Remove target post meta that no longer exists in the source payload.
	 *
	 * @param int   $post_id       Target post ID.
	 * @param array $incoming_meta Source meta payload.
	 * @return void
	 */
	private function delete_stale_synced_post_meta( $post_id, array $incoming_meta ) {
		$existing_meta = get_post_meta( (int) $post_id );
		if ( empty( $existing_meta ) ) {
			return;
		}

		$preserved_keys = array(
			'_edit_lock',
			'_edit_last',
			'_wp_old_slug',
			'_wp_old_date',
		);

		foreach ( $existing_meta as $key => $values ) {
			$key = (string) $key;

			if ( array_key_exists( $key, $incoming_meta ) || in_array( $key, $preserved_keys, true ) ) {
				continue;
			}

			if ( 0 === strpos( $key, '_rsl_ie_' ) ) {
				continue;
			}

			delete_post_meta( (int) $post_id, $key );
		}
	}

	/**
	 * Whether a WooCommerce product meta key belongs to the receiving store.
	 *
	 * Sales counts, ratings and stock already tracked on the receiving store are
	 * its own live data, so a sync never replaces them with the sender's values.
	 *
	 * @param int    $post_id Target post ID.
	 * @param string $key     Meta key.
	 * @return bool
	 */
	private function is_target_owned_product_meta( $post_id, $key ) {
		if ( ! in_array( $key, array( 'total_sales', '_wc_average_rating', '_wc_review_count', '_wc_rating_count', '_stock', '_stock_status' ), true )
			|| ! in_array( get_post_type( $post_id ), array( 'product', 'product_variation' ), true ) ) {
			return false;
		}
		if ( '_stock' === $key ) {
			$current = get_post_meta( $post_id, '_stock', true );
			return 'yes' === get_post_meta( $post_id, '_manage_stock', true ) && '' !== (string) $current;
		}
		if ( '_stock_status' === $key ) {
			return metadata_exists( 'post', $post_id, '_stock_status' );
		}
		return true;
	}

	/**
	 * Save synced post meta with special handling for generated/builder metadata.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @param mixed  $value   Meta value.
	 * @return void
	 */
	public function save_synced_post_meta( $post_id, $key, $value ) {
		// Live inventory, sales counts and ratings belong to the receiving store.
		if ( $this->is_target_owned_product_meta( (int) $post_id, (string) $key ) ) {
			return;
		}

		if ( $this->should_skip_synced_meta_key( $key ) ) {
			delete_post_meta( $post_id, $key );
			return;
		}

		if ( class_exists( '\RockStarLab\ImportExport\Helper\Elementor_Fields' )
			&& \RockStarLab\ImportExport\Helper\Elementor_Fields::is_elementor_meta_key( $key ) ) {
			\RockStarLab\ImportExport\Helper\Elementor_Fields::import_meta_value( (int) $post_id, $key, $value, true, false );
			return;
		}

		$value = $this->resolve_synced_media_references( $value, (int) $post_id );

		update_post_meta( $post_id, $key, wp_slash( $value ) );
	}

	/**
	 * Convert synced meta values to portable form before sending them to another site.
	 *
	 * @param mixed $value Meta value.
	 * @return mixed
	 */
	public function portable_synced_meta_value( $value ) {
		if ( is_string( $value ) && '' !== $value && ( false !== stripos( $value, '[gallery' ) || false !== stripos( $value, '[playlist' ) ) ) {
			return ACF_Fields::export_string_with_media_shortcode_tokens( $value );
		}

		if ( is_array( $value ) ) {
			foreach ( $value as $key => $child ) {
				$value[ $key ] = $this->portable_synced_meta_value( $child );
			}
		}

		return $value;
	}

	/**
	 * Resolve portable media references before saving synced text/meta values.
	 *
	 * @param mixed $value     Meta/content value.
	 * @param int   $parent_id Optional parent post ID.
	 * @return mixed
	 */
	private function resolve_synced_media_references( $value, $parent_id = 0 ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $key => $child ) {
				$value[ $key ] = $this->resolve_synced_media_references( $child, $parent_id );
			}

			return $value;
		}

		if ( is_string( $value )
			&& '' !== $value
			&& (
				false !== strpos( $value, '[[RSL_IE:' )
				|| false !== stripos( $value, '[gallery' )
				|| false !== stripos( $value, '[playlist' )
				|| false !== stripos( $value, '<img' )
				|| false !== stripos( $value, 'srcset=' )
				|| false !== stripos( $value, '<a' )
			)
		) {
			return ACF_Fields::replace_media_urls_in_html( $value, (int) $parent_id );
		}

		return $value;
	}

	/**
	 * Pull content from remote site
	 *
	 * @param array $input Parsed operation input.
	 * @return array|\WP_Error
	 */
	private function pull_content( array $input ) {
		$site_id      = $this->param( $input, 'site_id', 0 );
		$post_ids     = $this->array_param( $input, 'post_ids', array() );
		$post_mapping = $this->param( $input, 'post_mapping', array() );

		// Validate input.
		if ( empty( $site_id ) ) {
			return $this->error( __( 'Site ID is required', 'import-export-by-rockstarlab' ) );
		}

		if ( empty( $post_ids ) || ! is_array( $post_ids ) ) {
			return $this->error( __( 'No posts selected', 'import-export-by-rockstarlab' ) );
		}

		// Parse post_mapping if it's a JSON string.
		if ( is_string( $post_mapping ) ) {
			$post_mapping = json_decode( $post_mapping, true );
		}
		if ( ! is_array( $post_mapping ) ) {
			$post_mapping = array();
		}

		if ( ! empty( $post_mapping ) ) {
			$mapped_remote_ids = array();
			foreach ( $post_mapping as $remote_id ) {
				if ( is_numeric( $remote_id ) && (int) $remote_id > 0 ) {
					$mapped_remote_ids[] = (int) $remote_id;
				}
			}

			if ( ! empty( $mapped_remote_ids ) ) {
				$post_ids = array_values( array_unique( $mapped_remote_ids ) );
			}
		}

		// Get site details.
		$site = Connected_Site::get_by_id( $site_id );
		if ( ! $site ) {
			return $this->error( __( 'Site not found', 'import-export-by-rockstarlab' ) );
		}
		$job_id = $this->create_content_sync_job( 'pull', $site, $post_ids, $post_mapping );
		if ( ! $job_id ) {
			return $this->error( __( 'Could not create a sync history job.', 'import-export-by-rockstarlab' ) );
		}

		// Get domains for replacement.
		// Full site URLs like push: host-only replacement kept the remote port and sub-directory
		// (http://remote:8080/blog/page -> http://local:8080/blog/page).
		$source_domain = untrailingslashit( (string) $site['remote_url'] );
		$target_domain = untrailingslashit( (string) Site_URL::current_request_site_url() );

			// Request content from remote site.
			$response = \RockStarLab\ImportExport\Helper\Remote_API::post(
				$site['remote_url'],
				'send-content',
				array(
					'timeout' => 180,
					'headers' => array(
						'Authorization' => 'Bearer ' . $site['api_key'],
						'Content-Type'  => 'application/json',
					),
					'body'    => wp_json_encode(
						// translators: %s = content placeholder.
						array(
							'post_ids' => $post_ids,
						) + ( isset( $input['acf_user_mapping'] ) ? [ 'acf_users_version' => 1 ] : [] ) + ( isset( $input['acf_term_mapping'] ) ? [ 'acf_terms_version' => 1 ] : [] ) + ( isset( $input['acf_post_mapping'] ) ? [ 'acf_posts_version' => 1 ] : [] ) + ( array_key_exists( 'woo_product_mapping', $input ) ? [
							'woo_links_version'   => 1,
							'woo_product_mapping' => $input['woo_product_mapping'],
						] : [] ) + ( ! empty( $input['acf_media_references'] ) ? [ 'acf_media_version' => 1 ] : [] ) + Sync_ACF_Graph::request() + Sync_Product_Links::request()
					),
				)
			);

		if ( is_wp_error( $response ) ) {
			$this->fail_content_sync_job( $job_id, $response->get_error_message() );
			return $this->error( __( 'Failed to connect to remote site: ', 'import-export-by-rockstarlab' ) . $response->get_error_message() );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );

		if ( 200 !== $status_code ) {
			$error_data = json_decode( $body, true );
			// translators: %d is a dynamic value.
			$error_msg = isset( $error_data['message'] ) ? $error_data['message'] : sprintf( __( 'Pull failed with status code: %d', 'import-export-by-rockstarlab' ), $status_code );
			$this->fail_content_sync_job( $job_id, $error_msg );
			return $this->error( $error_msg );
		}

		$data = json_decode( $body, true );
		if ( ! isset( $data['success'] ) || ! $data['success'] || ! isset( $data['data']['posts'] ) ) {
			$this->fail_content_sync_job( $job_id, __( 'Remote site returned invalid data', 'import-export-by-rockstarlab' ) );
			return $this->error( __( 'Remote site returned invalid data', 'import-export-by-rockstarlab' ) );
		}

		$posts_data   = $data['data']['posts'];
		$graph_access = Sync_ACF_Graph::received( $posts_data );
		if ( is_wp_error( $graph_access ) ) {
			return $graph_access;
		}
		if ( empty( $posts_data ) ) {
			$this->fail_content_sync_job( $job_id, __( 'No posts found on remote site', 'import-export-by-rockstarlab' ) );
			return $this->error( __( 'No posts found on remote site', 'import-export-by-rockstarlab' ) );
		}
		if ( $this->free_scope ) {
			$access = $this->policy_class::remote_posts( $posts_data, array_map( 'intval', $post_ids ), $this->input['post_type'] );
			if ( is_wp_error( $access ) ) {
				return $access;
			}
			$destinations = array_flip( array_filter( $post_mapping ) );
			foreach ( $posts_data as $candidate ) {
				$destination = $destinations[ $candidate['ID'] ] ?? $this->find_existing_post_by_slug( $candidate );
				if ( ! $destination ) {
					$destination = $this->find_existing_post_by_original_id( $candidate['ID'] );
				}
				if ( $destination ) {
					$access = $this->policy_class::local_post( (int) $destination, $this->input['post_type'] );
					if ( is_wp_error( $access ) ) {
						return $access;
					}
				}
			}
		}

		$access = \RockStarLab\ImportExport\Abilities\Sync_Peer_Policy::check( 'receive_content', [ 'posts' => $posts_data ] );
		if ( is_wp_error( $access ) ) {
			return $access; }
		$deferred = Sync_Product_Links::validate_deferred( $posts_data );
		if ( is_wp_error( $deferred ) ) {
			return $deferred; }

		if ( isset( $input['hierarchy'] ) ) {
			$posts_data = Sync_Hierarchy::prepare_pull( 'post_type', $input['post_type'], $posts_data, $input['hierarchy'], array_flip( $post_mapping ) );
			if ( is_wp_error( $posts_data ) ) {
				$this->fail_content_sync_job( $job_id, $posts_data->get_error_message() );
				return $posts_data;
			}
		}

		$authors = [];
		if ( isset( $input['author_mapping'] ) ) {
			$authors = apply_filters( 'rsl_ie_sync_post_authors', Migration_Sync_Maps::error(), $posts_data, $input['author_mapping'], true );
			if ( is_wp_error( $authors ) ) {
				return $authors;
			}
		}

		$comment_users = Sync_Comment_Users::posts( $posts_data, $input['comment_user_mapping'] ?? null );
		if ( is_wp_error( $comment_users ) ) {
			return $comment_users;
		}

		$woo_variation_map = [];
		if ( array_key_exists( 'woo_product_mapping', $input ) ) {
			$woo_links = Sync_Product_Links::run( 'prepare', $posts_data, [ 'mapping' => $input['woo_product_mapping'] ] );
			if ( is_wp_error( $woo_links ) ) {
				return $woo_links;
			}
			$posts_data = Sync_Product_Links::run( 'strip', $posts_data );
			if ( is_wp_error( $posts_data ) ) {
				return $posts_data;
			}
		}

		$acf_users = [];
		$acf_posts = [];
		if ( isset( $input['acf_user_mapping'] ) ) {
			$acf_users = Sync_ACF_Users::run( 'prepare', $posts_data, [ 'mapping' => $input['acf_user_mapping'] ] );
			if ( is_wp_error( $acf_users ) ) {
				return $acf_users;
			}
		}
		if ( isset( $input['acf_term_mapping'] ) ) {
			$acf_terms = Sync_ACF_Terms::run( 'prepare', $posts_data, [ 'mapping' => $input['acf_term_mapping'] ] );
			if ( is_wp_error( $acf_terms ) ) {
				return $acf_terms;
			}
		}

		if ( isset( $input['acf_post_mapping'] ) ) {
			$acf_posts = Sync_ACF_Posts::run( 'prepare', $posts_data, [ 'mapping' => $input['acf_post_mapping'] ] );
			if ( is_wp_error( $acf_posts ) ) {
				return $acf_posts;
			}
		}

		if ( ! empty( $input['acf_media_references'] ) ) {
			$acf_media = Sync_ACF_Media::prepare( $posts_data );
			if ( is_wp_error( $acf_media ) ) {
				return $acf_media;
			}
		}

		if ( isset( $input['acf_term_mapping'] ) ) {
			$posts_data = Sync_ACF_Terms::run( 'strip', $posts_data, [ 'assignments' => $acf_terms ] );
			if ( is_wp_error( $posts_data ) ) {
				return $posts_data;
			}
		}

		// Get images from remote.
		$remote_images = isset( $data['data']['images'] ) ? $data['data']['images'] : array();

		// Download images from remote site.
		$image_map = array();
		if ( ! empty( $remote_images ) ) {
			foreach ( $remote_images as $image ) {
				$image['force_unique'] = false;
				$new_attachment_id     = $this->download_image_from_remote( $image, $site, $image_map, $remote_images );
				if ( $new_attachment_id ) {
					$image_map[ $image['attachment_id'] ] = $new_attachment_id;
				}
			}
		}

		$elementor_fallback_images = $this->extract_elementor_external_media_from_posts( $posts_data );
		if ( ! empty( $elementor_fallback_images ) ) {
			foreach ( $elementor_fallback_images as $image ) {
				$source_attachment_id = isset( $image['attachment_id'] ) ? (int) $image['attachment_id'] : 0;
				if ( $source_attachment_id > 0 && isset( $image_map[ $source_attachment_id ] ) ) {
					continue;
				}

				$new_attachment_id = $this->download_image_from_remote( $image, $site, $image_map, $remote_images );
				if ( $new_attachment_id && $source_attachment_id > 0 ) {
					$image_map[ $source_attachment_id ] = $new_attachment_id;
				}
			}
		}

		if ( ! empty( $input['acf_media_references'] ) ) {
			$acf_media = Sync_ACF_Media::prepare( $posts_data, $image_map );
			if ( is_wp_error( $acf_media ) ) {
				return $acf_media;
			}
			$posts_data = Sync_ACF_Media::strip( $posts_data, $acf_media );
		}

		$this->finalize_pulled_media( array_merge( $remote_images, $elementor_fallback_images ), $image_map );

		// Replace domains and image IDs in post data.
		foreach ( $posts_data as &$post_data ) {
			$post_data = \RockStarLab\ImportExport\Helper\Content_Sync_Replacer::replace_post_domains(
				$post_data,
				$source_domain,
				$target_domain,
				$image_map,
				$remote_images
			);
		}
		unset( $post_data ); // Break the reference to avoid bugs in the next foreach loop.

		// Synced patterns the pulled posts use (newer remote sites send them). Imported after the
		// images so their links and image IDs are translated like the posts' own content.
		$synced_patterns = $data['data']['synced_patterns'] ?? null;
		if ( is_array( $synced_patterns ) ) {
			foreach ( $synced_patterns as &$synced_pattern ) {
				if ( is_array( $synced_pattern ) && isset( $synced_pattern['content'] ) && is_string( $synced_pattern['content'] ) ) {
					$synced_pattern['content'] = \RockStarLab\ImportExport\Helper\Content_Sync_Replacer::replace_post_domains(
						array( 'post_content' => $synced_pattern['content'] ),
						$source_domain,
						$target_domain,
						$image_map,
						$remote_images
					)['post_content'];
				}
			}
			unset( $synced_pattern );
		}
		$pattern_map = Sync_Synced_Patterns::import( $synced_patterns );
		$block_terms = Sync_Synced_Patterns::import_terms( $data['data']['block_terms'] ?? null );
		if ( $pattern_map || $block_terms ) {
			foreach ( $posts_data as &$pattern_post ) {
				if ( is_array( $pattern_post ) && isset( $pattern_post['post_content'] ) ) {
					$pattern_post['post_content'] = Sync_Synced_Patterns::apply_terms( Sync_Synced_Patterns::apply( $pattern_post['post_content'], $pattern_map ), $block_terms );
				}
			}
			unset( $pattern_post );
		}

		// Import posts.
		$imported_count           = 0;
		$updated_count            = 0;
		$imported_remote_to_local = array();
		$imported_remote_parent   = array();
		$imported_remote_type     = array();
		$product_post_ids         = array();

		// JS sends post_mapping as { local_id: remote_id }, but here we need
		// to look up by REMOTE id (the ID coming from the remote post data).
		// Build a reversed map { remote_id: local_id } for fast lookup.
		$remote_to_local_map = array();
		foreach ( $post_mapping as $local_id => $remote_id ) {
			if ( null !== $remote_id && is_numeric( $remote_id ) && $remote_id > 0 ) {
				$remote_to_local_map[ intval( $remote_id ) ] = intval( $local_id );
			}
		}

		foreach ( $posts_data as $post_data ) {
			$remote_post_id = $post_data['ID'];
			$local_post_id  = null;
			$remote_parent  = array_key_exists( 'post_parent', $post_data ) ? (int) $post_data['post_parent'] : null;

			// Check post mapping (remote → local).
			if ( isset( $remote_to_local_map[ $remote_post_id ] ) ) {
				$mapped_value = $remote_to_local_map[ $remote_post_id ];

				// If mapped to specific ID, use it.
				if ( $mapped_value > 0 ) {
					$local_post_id = $mapped_value;
				}
				// null / 0 means "new", but a grouped child may already have
				// been created earlier in this same sync.
				if ( ! $local_post_id ) {
					$local_post_id = $this->find_existing_post_by_original_id( $remote_post_id );
				}
			} else {
				// No mapping provided, use default logic.
				$local_post_id = $this->find_existing_post_by_slug( (array) $post_data );
				if ( ! $local_post_id ) {
					$local_post_id = $this->find_existing_post_by_original_id( $remote_post_id );
				}
			}
			if ( ! $local_post_id ) {
				$local_post_id = $this->find_existing_product_by_sku( (array) $post_data );
			}

			if ( $this->free_scope && $local_post_id ) {
				$access = $this->policy_class::local_post( $local_post_id, $this->input['post_type'] );
				if ( is_wp_error( $access ) ) {
					return $access;
				}
			}
			$access = \RockStarLab\ImportExport\Abilities\Sync_Peer_Policy::destination( (int) $local_post_id, $post_data['post_type'] );
			if ( is_wp_error( $access ) ) {
				return $access; }
			// Prepare post data.
			$post_args = array(
				'post_title'        => $post_data['post_title'],
				'post_content'      => $post_data['post_content'],
				'post_excerpt'      => $post_data['post_excerpt'],
				'post_status'       => $post_data['post_status'],
				'post_type'         => $post_data['post_type'],
				'post_name'         => $post_data['post_name'],
				'post_date'         => $post_data['post_date'],
				'post_date_gmt'     => isset( $post_data['post_date_gmt'] ) ? $post_data['post_date_gmt'] : '',
				'post_modified'     => isset( $post_data['post_modified'] ) ? $post_data['post_modified'] : '',
				'post_modified_gmt' => isset( $post_data['post_modified_gmt'] ) ? $post_data['post_modified_gmt'] : '',
				'menu_order'        => isset( $post_data['menu_order'] ) ? (int) $post_data['menu_order'] : 0,
				'post_author'       => $authors[ (int) $post_data['ID'] ] ?? \RockStarLab\ImportExport\Helper\Sync_Post_Author::resolve( $post_data, (int) $local_post_id ), // PRO mapping, else same user by email/login, else keep current (update) or current user (new).
				'edit_date'         => true,
			);

			if ( $local_post_id ) {
				// Update existing post.
				$post_args['ID'] = $local_post_id;
				$post_id         = wp_update_post( wp_slash( $post_args ), true ); // Slashed: WordPress strips one level (\_/, \u002d in block attributes).
				if ( ! is_wp_error( $post_id ) && $post_id ) {
					++$updated_count;
				}
			} else {
				// Create new post.
				$post_id = wp_insert_post( wp_slash( $post_args ), true ); // Slashed: WordPress strips one level.
				if ( ! is_wp_error( $post_id ) && $post_id ) {
					++$imported_count;
				}
			}

			if ( is_wp_error( $post_id ) || ! $post_id ) {
				continue;
			}

			if ( isset( $authors[ (int) $remote_post_id ] ) && (int) get_post_field( 'post_author', $post_id ) !== $authors[ (int) $remote_post_id ] ) {
				return \RockStarLab\ImportExport\Service\Migration_Sync_Maps::error();
			}

			// Track remote → local ID mapping for this pull so we can fix parent/child
			// relationships after all posts are created/updated.
			$imported_remote_to_local[ (int) $remote_post_id ] = (int) $post_id;
			$imported_remote_parent[ (int) $remote_post_id ]   = $remote_parent;
			$imported_remote_type[ (int) $remote_post_id ]     = isset( $post_data['post_type'] ) ? (string) $post_data['post_type'] : '';
			if ( isset( $post_data['post_type'] ) && 'product' === $post_data['post_type'] ) {
				$product_post_ids[] = (int) $post_id;
			}

			// Store original post ID for future reference.
			update_post_meta( $post_id, '_rsl_ie_original_post_id', $remote_post_id );
			update_post_meta( $post_id, '_rsl_ie_source_id', $remote_post_id );

			// Domains and media IDs were translated once by replace_post_domains.
			// Reapplying the map here corrupts IDs when source and target overlap.
			if ( isset( $post_data['meta'] ) && is_array( $post_data['meta'] ) ) {
				$this->delete_stale_synced_post_meta( (int) $post_id, $post_data['meta'] );

				foreach ( $post_data['meta'] as $key => $value ) {
					$this->save_synced_post_meta( (int) $post_id, (string) $key, $value );
				}

				if ( ! empty( $image_map ) ) {
					\RockStarLab\ImportExport\Helper\Content_Sync_Replacer::attach_transferred_featured_image( (int) $post_id, (array) $image_map );
				}
			}

			// Import terms with ACF fields.
			if ( ! empty( $post_data['terms'] ) ) {
				// Build source_term_id → local_term_id map to fix ACF taxonomy fields.
				$term_id_map = array();

				// Clear ALL existing term assignments for every taxonomy the source
				// sent (including empty ones) so stale local terms are removed.
				foreach ( array_keys( $post_data['terms'] ) as $taxonomy_to_clear ) {
					$this->ensure_woocommerce_attribute_taxonomy( $taxonomy_to_clear );
					if ( taxonomy_exists( $taxonomy_to_clear ) ) {
						wp_set_object_terms( $post_id, array(), $taxonomy_to_clear );
					}
				}

				foreach ( $post_data['terms'] as $taxonomy => $terms_info ) {
					$this->ensure_woocommerce_attribute_taxonomy( $taxonomy );
					if ( ! taxonomy_exists( $taxonomy ) ) {
						continue;
					}

					$term_ids = array();
					foreach ( $terms_info as $term_info ) {
						// Validate term info.
						if ( empty( $term_info['name'] ) || empty( $term_info['slug'] ) ) {
							continue;
						}

						$term_id = $this->resolve_synced_term( $taxonomy, $term_info );
						if ( $term_id <= 0 ) {
							continue;
						}

						if ( ! array_key_exists( 'assign_to_post', $term_info ) || (bool) $term_info['assign_to_post'] ) {
							$term_ids[] = (int) $term_id;
						}

						// Record source → local term ID mapping.
						if ( ! empty( $term_info['term_id'] ) ) {
							$term_id_map[ (int) $term_info['term_id'] ] = (int) $term_id;
						}

						// Import ACF fields for this term.
						if ( ! empty( $term_info['acf'] ) && function_exists( 'update_field' ) ) {
							foreach ( $term_info['acf'] as $field_key => $field_value ) {
								ACF_Fields::import_value( 'term', $term_id, sanitize_text_field( (string) $field_key ), $field_value, $taxonomy );
							}
						}
					}

					// Assign terms to post only if we have valid term IDs.
					if ( ! empty( $term_ids ) ) {
						wp_set_object_terms( $post_id, $term_ids, $taxonomy );
					}
				}

				// Re-save ACF taxonomy fields with correct local term IDs.
				if ( ! empty( $term_id_map ) && ! empty( $post_data['meta'] ) ) {
					\RockStarLab\ImportExport\Helper\Content_Sync_Replacer::translate_acf_taxonomy_fields_in_meta(
						$post_data['meta'],
						$post_id,
						$term_id_map
					);
				}
			}

			// Confirmed ACF references are finalized after all posts exist.
			if ( ! isset( $input['acf_post_mapping'] ) && ! empty( $post_data['meta'] ) ) {
				\RockStarLab\ImportExport\Helper\Content_Sync_Replacer::translate_acf_post_reference_fields_in_meta(
					$post_data['meta'],
					$post_id,
					$remote_post_id,
					isset( $post_data['post_refs'] ) ? $post_data['post_refs'] : array()
				);
			}

			// Import WooCommerce product variations and recalculate the variable
			// product price range so the local site shows the correct prices.
			if ( 'product' === $post_data['post_type']
				&& ( ! empty( $post_data['variations'] ) || ( isset( $woo_links ) && isset( $post_data['woo_variations'] ) ) )
				&& class_exists( 'WC_Product' )
				&& function_exists( 'wc_get_product' )
			) {
					$variation_result = $this->import_product_variations( $post_id, $post_data['variations'], (array) $image_map, (array) $remote_images, isset( $woo_links ) && isset( $post_data['woo_variations'] ) );
				if ( is_wp_error( $variation_result ) ) {
					return $variation_result; }
				if ( isset( $woo_links ) && isset( $post_data['woo_variations'] ) ) {
					if ( ! is_array( $variation_result ) || array_intersect_key( $woo_variation_map, $variation_result ) ) {
						return \RockStarLab\ImportExport\Service\Migration_Sync_Maps::error();
					}
					$woo_variation_map += $variation_result;
				}
			}

			// Import WooCommerce grouped product children and remap _children meta.
			if ( 'product' === $post_data['post_type']
				&& ! empty( $post_data['grouped_children'] )
				&& class_exists( 'WC_Product' )
				&& function_exists( 'wc_get_product' )
			) {
					$local_child_ids = $this->import_grouped_children( $post_id, $post_data['grouped_children'], (array) $image_map, (array) $remote_images );
				if ( is_wp_error( $local_child_ids ) ) {
					return $local_child_ids; }
				if ( ! empty( $local_child_ids ) ) {
					update_post_meta( $post_id, '_children', $local_child_ids );
				}
			}

			if ( ! empty( $post_data['comments'] ) ) {
				$comment_result = $this->import_synced_comments( $post_id, $post_data['comments'], $comment_users );
				if ( is_wp_error( $comment_result ) ) {
					return $comment_result;
				}
			}

			if ( 'product' === $post_data['post_type'] ) {
				$this->sync_woocommerce_product_type( (int) $post_id, (array) $post_data );
				$this->refresh_woocommerce_product_after_sync( $post_id );
			}

			// Fix image URLs in content after import.
			$updated_content = \RockStarLab\ImportExport\Helper\Content_Sync_Replacer::fix_local_image_urls_in_content(
				$post_data['post_content'],
				$image_map,
				$remote_images
			);

			foreach ( $image_map as $old_id => $new_id ) {
				// Also update wp:image block ID.
				$updated_content = str_replace( '"id":' . (int) $old_id, '"id":' . (int) $new_id, $updated_content );
			}

			if ( $updated_content !== $post_data['post_content'] ) {

				$update_result = wp_update_post(
					wp_slash(
						array(
							'ID'           => $post_id,
							'post_content' => $updated_content,
						)
					),
					true
				);

			}

			$this->restore_synced_post_modified_dates( (int) $post_id, (array) $post_data );
		}

		// Fix hierarchical relationships (e.g. pages) after all imports so we can
		// resolve parent IDs that were created in the same pull batch.
		foreach ( isset( $input['hierarchy'] ) ? [] : $imported_remote_to_local as $remote_id => $local_id ) {
			if ( ! array_key_exists( $remote_id, $imported_remote_parent ) ) {
				continue;
			}

			$remote_parent_id = $imported_remote_parent[ $remote_id ];
			if ( null === $remote_parent_id ) {
				continue;
			}

			$post_type = isset( $imported_remote_type[ $remote_id ] ) ? $imported_remote_type[ $remote_id ] : '';
			if ( empty( $post_type ) || ! is_post_type_hierarchical( $post_type ) ) {
				continue;
			}

			$local_parent_id = 0;
			if ( $remote_parent_id > 0 ) {
				if ( isset( $imported_remote_to_local[ $remote_parent_id ] ) ) {
					$local_parent_id = (int) $imported_remote_to_local[ $remote_parent_id ];
				} else {
					$local_parent_id = (int) $this->find_existing_post_by_original_id( $remote_parent_id );
				}

				// Parent not available locally - don't force reset the relationship.
				if ( empty( $local_parent_id ) ) {
					continue;
				}

				$parent_post = get_post( $local_parent_id );
				if ( ! $parent_post || $parent_post->post_type !== $post_type ) {
					continue;
				}
			}

			$child_post = get_post( $local_id );
			if ( ! $child_post || $child_post->post_type !== $post_type ) {
				continue;
			}

			if ( (int) $child_post->post_parent === (int) $local_parent_id ) {
				continue;
			}

			wp_update_post(
				array(
					'ID'          => $local_id,
					'post_parent' => $local_parent_id,
				),
				true
			);

			if ( isset( $posts_data[0] ) ) {
				foreach ( $posts_data as $post_data ) {
					if ( (int) ( $post_data['ID'] ?? 0 ) === (int) $remote_id ) {
						$this->restore_synced_post_modified_dates( (int) $local_id, (array) $post_data );
						break;
					}
				}
			}
		}

		foreach ( array_unique( $product_post_ids ) as $product_post_id ) {
			$this->refresh_woocommerce_product_after_sync( $product_post_id );
		}

		if ( isset( $input['hierarchy'] ) ) {
			$confirmed = Sync_Hierarchy::finalize( 'post_type', $input['post_type'], $input['hierarchy']['edges'], $input['hierarchy'], $imported_remote_to_local );
			if ( is_wp_error( $confirmed ) ) {
				$this->fail_content_sync_job( $job_id, $confirmed->get_error_message() );
				return $confirmed;
			}
		}

		if ( ! empty( $input['acf_media_references'] ) ) {
			$confirmed = Sync_ACF_Media::finalize( $acf_media, $imported_remote_to_local );
			if ( is_wp_error( $confirmed ) ) {
				$this->fail_content_sync_job( $job_id, $confirmed->get_error_message() );
				return $confirmed;
			}
		}

		if ( isset( $input['acf_user_mapping'] ) ) {
			$confirmed = Sync_ACF_Users::run( 'finalize', $acf_users, [ 'posts' => $imported_remote_to_local ] );
			if ( is_wp_error( $confirmed ) ) {
				$this->fail_content_sync_job( $job_id, $confirmed->get_error_message() );
				return $confirmed;
			}
		}
		if ( isset( $input['acf_term_mapping'] ) ) {
			$confirmed = Sync_ACF_Terms::run( 'finalize', $acf_terms, [ 'posts' => $imported_remote_to_local ] );
			if ( is_wp_error( $confirmed ) ) {
				$this->fail_content_sync_job( $job_id, $confirmed->get_error_message() );
				return $confirmed;
			}
		}

		if ( isset( $input['acf_post_mapping'] ) ) {
			$confirmed = Sync_ACF_Posts::run( 'finalize', $acf_posts, [ 'posts' => $imported_remote_to_local ] );
			if ( is_wp_error( $confirmed ) ) {
				$this->fail_content_sync_job( $job_id, $confirmed->get_error_message() );
				return $confirmed;
			}
		}

		if ( array_key_exists( 'woo_product_mapping', $input ) ) {
			$confirmed = Sync_Product_Links::run(
				'finalize',
				$woo_links,
				[
					'posts'      => $imported_remote_to_local,
					'variations' => $woo_variation_map,
					'records'    => $posts_data,
				]
			);
			if ( is_wp_error( $confirmed ) ) {
				$this->fail_content_sync_job( $job_id, $confirmed->get_error_message() );
				return $confirmed;
			}
		}

		$total_processed = $imported_count + $updated_count;
		$this->complete_content_sync_job(
			$job_id,
			$total_processed,
			array(
				'variation_map' => $woo_variation_map,
				'direction'     => 'pull',
				'site_id'       => (int) $site_id,
				'remote_url'    => isset( $site['remote_url'] ) ? (string) $site['remote_url'] : '',
				'selected_ids'  => array_values( array_map( 'absint', $post_ids ) ),
				'post_mapping'  => $post_mapping,
				'created'       => $imported_count,
				'updated'       => $updated_count,
				'images_synced' => count( $image_map ),
				'image_map'     => $image_map,
				'posts'         => array_map(
					static function ( $remote_id, $local_id ) use ( $posts_data ) {
						$title = '';
						$type  = '';
						foreach ( $posts_data as $post_data ) {
							if ( isset( $post_data['ID'] ) && (int) $post_data['ID'] === (int) $remote_id ) {
								$title = isset( $post_data['post_title'] ) ? (string) $post_data['post_title'] : '';
								$type  = isset( $post_data['post_type'] ) ? (string) $post_data['post_type'] : '';
								break;
							}
						}
						return array(
							'source_id' => (int) $remote_id,
							'target_id' => (int) $local_id,
							'title'     => $title,
							'type'      => $type,
						);
					},
					array_keys( $imported_remote_to_local ),
					array_values( $imported_remote_to_local )
				),
			)
		);
		$message = array();

		if ( $imported_count > 0 ) {
			$message[] = sprintf(
				/* translators: %d: number of posts */
				_n( 'Created %d post', 'Created %d posts', $imported_count, 'import-export-by-rockstarlab' ),
				$imported_count
			);
		}

		if ( $updated_count > 0 ) {
			$message[] = sprintf(
				/* translators: %d: number of posts */
				_n( 'Updated %d post', 'Updated %d posts', $updated_count, 'import-export-by-rockstarlab' ),
				$updated_count
			);
		}

		return $this->success(
			array(
				'message' => ! empty( $message ) ? implode( ', ', $message ) : __( 'No posts were processed', 'import-export-by-rockstarlab' ),
			)
		);
	}

	/**
	 * Download image from remote site
	 *
	 * @param array $image Image data from remote.
	 * @param array $site Site connection data.
	 * @param mixed $image_map Image map.
	 * @param mixed $image_sources Image sources.
	 * @return int Confirmed destination attachment ID.
	 * @throws \RuntimeException If media cannot be downloaded or imported.
	 */
	private function download_image_from_remote( $image, $site, $image_map = array(), $image_sources = array() ) {
		$result = ( new Media_Transport_Service() )->pull_image( $image, $site );
		if ( is_wp_error( $result ) ) {
			throw new \RuntimeException( 'Content Sync media pull failed.' );
		}
		return $result;
	}

	/**
	 * Complete attachment fields after every media dependency has a destination ID.
	 *
	 * @param array $images Source media.
	 * @param array $map Complete source-to-target mapping.
	 * @return void
	 * @throws \RuntimeException If destination metadata cannot be applied.
	 */
	private function finalize_pulled_media( array $images, array $map ) {
		$service = new Media_Transport_Service();
		foreach ( $images as $image ) {
			$id = $map[ $image['attachment_id'] ?? 0 ] ?? 0;
			if ( ! $id ) {
				continue;
			}
			$result = $service->apply_pull_fields( $id, $image, $map, $images );
			if ( is_wp_error( $result ) ) {
				throw new \RuntimeException( 'Content Sync media fields failed.' );
			}
		}
	}


	/**
	 * Prepare ACF value - ensure numeric IDs are integers, not strings
	 * This is important for file/image fields in repeaters
	 *
	 * @param mixed $value Value to prepare.
	 * @return mixed Prepared value
	 */
	private function prepare_acf_value( $value ) {
		if ( is_array( $value ) ) {
			// Recursively process arrays (repeater rows, galleries, etc.).
			foreach ( $value as $key => $item ) {
				$value[ $key ] = $this->prepare_acf_value( $item );
			}
			return $value;
		}

		// Convert numeric strings to integers (important for attachment IDs).
		if ( is_string( $value ) && is_numeric( $value ) && strpos( $value, '.' ) === false ) {
			return intval( $value );
		}

		return $value;
	}

	/**
	 * Collect comments/reviews for a synced post.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	private function collect_post_comments_for_sync( $post_id ) {
		$comments = get_comments(
			array(
				'post_id' => (int) $post_id,
				'status'  => 'all',
				'orderby' => 'comment_ID',
				'order'   => 'ASC',
			)
		);

		$data = array();
		foreach ( $comments as $comment ) {
			$meta          = get_comment_meta( $comment->comment_ID );
			$prepared_meta = array();
			foreach ( $meta as $key => $values ) {
				if ( '_rsl_ie_original_comment_id' === $key ) {
					continue;
				}
				$prepared_meta[ $key ] = $this->portable_synced_meta_value( maybe_unserialize( $values[0] ) );
			}

			$data[] = array(
				'comment_ID'           => (int) $comment->comment_ID,
				'comment_parent'       => (int) $comment->comment_parent,
				'comment_author'       => $comment->comment_author,
				'comment_author_email' => $comment->comment_author_email,
				'comment_author_url'   => $comment->comment_author_url,
				'comment_author_IP'    => $comment->comment_author_IP,
				'comment_date'         => $comment->comment_date,
				'comment_date_gmt'     => $comment->comment_date_gmt,
				'comment_content'      => $this->portable_synced_meta_value( $comment->comment_content ),
				'comment_karma'        => (int) $comment->comment_karma,
				'comment_approved'     => $comment->comment_approved,
				'comment_agent'        => $comment->comment_agent,
				'comment_type'         => $comment->comment_type,
				'user_id'              => (int) $comment->user_id,
				'meta'                 => $prepared_meta,
			);
		}

		return $data;
	}

	/**
	 * Import synced comments/reviews for a local post.
	 *
	 * @param int   $post_id  Local post ID.
	 * @param array $comments Synced comments payload.
	 * @param array $users Server-validated comment-to-local-user assignments.
	 * @return void|\WP_Error
	 */
	private function import_synced_comments( $post_id, $comments, array $users = [] ) {
		$source_to_local = array();

		foreach ( (array) $comments as $comment_data ) {
			$source_comment_id = isset( $comment_data['comment_ID'] ) ? (int) $comment_data['comment_ID'] : 0;
			if ( $source_comment_id <= 0 ) {
				continue;
			}

			$existing_id = $this->find_existing_comment_by_original_id( $post_id, $source_comment_id );
			$parent_id   = 0;
			if ( ! empty( $comment_data['comment_parent'] ) ) {
				$source_parent = (int) $comment_data['comment_parent'];
				$parent_id     = isset( $source_to_local[ $source_parent ] ) ? (int) $source_to_local[ $source_parent ] : 0;
			}

			$args = array(
				'comment_post_ID'      => (int) $post_id,
				'comment_author'       => isset( $comment_data['comment_author'] ) ? sanitize_text_field( $comment_data['comment_author'] ) : '',
				'comment_author_email' => isset( $comment_data['comment_author_email'] ) ? sanitize_email( $comment_data['comment_author_email'] ) : '',
				'comment_author_url'   => isset( $comment_data['comment_author_url'] ) ? esc_url_raw( $comment_data['comment_author_url'] ) : '',
				'comment_author_IP'    => isset( $comment_data['comment_author_IP'] ) ? sanitize_text_field( $comment_data['comment_author_IP'] ) : '',
				'comment_date'         => isset( $comment_data['comment_date'] ) ? sanitize_text_field( $comment_data['comment_date'] ) : current_time( 'mysql' ),
				'comment_date_gmt'     => isset( $comment_data['comment_date_gmt'] ) ? sanitize_text_field( $comment_data['comment_date_gmt'] ) : current_time( 'mysql', true ),
				'comment_content'      => isset( $comment_data['comment_content'] ) ? wp_kses_post( $this->resolve_synced_media_references( $comment_data['comment_content'], 0 ) ) : '',
				'comment_karma'        => isset( $comment_data['comment_karma'] ) ? (int) $comment_data['comment_karma'] : 0,
				'comment_approved'     => isset( $comment_data['comment_approved'] ) ? sanitize_text_field( $comment_data['comment_approved'] ) : '1',
				'comment_agent'        => isset( $comment_data['comment_agent'] ) ? sanitize_text_field( $comment_data['comment_agent'] ) : '',
				'comment_type'         => isset( $comment_data['comment_type'] ) ? sanitize_key( $comment_data['comment_type'] ) : '',
				'comment_parent'       => $parent_id,
				'user_id'              => $users[ $source_comment_id ] ?? 0,
			);

			// A comment that was first pushed from this site comes back without our ID: reuse the original.
			if ( ! $existing_id ) {
				$existing_id = $this->find_identical_unsynced_comment( (int) $post_id, $args );
			}

			if ( $existing_id ) {
				$args['comment_ID'] = $existing_id;
				// wp_update_comment() returns 0 when nothing changed; that is still a match.
				$updated   = wp_update_comment( wp_slash( $args ), true );
				$result_id = ( false === $updated || is_wp_error( $updated ) ) ? 0 : $existing_id;
			} else {
				$result_id = wp_insert_comment( wp_slash( $args ) );
			}

			if ( ! $result_id || is_wp_error( $result_id ) ) {
				if ( isset( $users[ $source_comment_id ] ) ) {
					return \RockStarLab\ImportExport\Service\Migration_Sync_Maps::error();
				}
				continue;
			}

			$saved_comment = get_comment( $result_id );
			if ( ! $saved_comment || (int) $saved_comment->user_id !== $args['user_id'] ) {
				return \RockStarLab\ImportExport\Service\Migration_Sync_Maps::error();
			}
			$source_to_local[ $source_comment_id ] = (int) $result_id;
			update_comment_meta( $result_id, '_rsl_ie_original_comment_id', $source_comment_id );

			if ( ! empty( $comment_data['meta'] ) && is_array( $comment_data['meta'] ) ) {
				foreach ( $comment_data['meta'] as $key => $value ) {
					if ( '_rsl_ie_original_comment_id' === $key ) {
						continue;
					}
					update_comment_meta( $result_id, sanitize_key( $key ), wp_slash( $this->resolve_synced_media_references( $value, 0 ) ) );
				}
			}
		}
	}

	/**
	 * Find the same comment already present on a cloned site (same author email,
	 * date and text) that no earlier sync has claimed.
	 *
	 * @param int   $post_id Local post ID.
	 * @param array $args    Sanitized incoming comment fields.
	 * @return int
	 */
	private function find_identical_unsynced_comment( $post_id, array $args ) {
		if ( '' === $args['comment_author_email'] || '' === $args['comment_date_gmt'] ) {
			return 0;
		}
		$candidates = get_comments(
			array(
				'post_id'      => $post_id,
				'status'       => 'any',
				'type'         => '' !== $args['comment_type'] ? $args['comment_type'] : 'comment',
				'author_email' => $args['comment_author_email'],
			)
		);
		foreach ( $candidates as $candidate ) {
			if ( $candidate->comment_date_gmt === $args['comment_date_gmt']
				&& $candidate->comment_content === $args['comment_content']
				&& '' === (string) get_comment_meta( (int) $candidate->comment_ID, '_rsl_ie_original_comment_id', true ) ) {
				return (int) $candidate->comment_ID;
			}
		}
		return 0;
	}

	/**
	 * Find an existing synced comment on a local post.
	 *
	 * @param int $post_id            Local post ID.
	 * @param int $source_comment_id  Source comment ID.
	 * @return int
	 */
	private function find_existing_comment_by_original_id( $post_id, $source_comment_id ) {
		$comments = get_comments(
			array(
				'post_id'    => (int) $post_id,
				'status'     => 'any',
				'number'     => 1,
				'meta_key'   => '_rsl_ie_original_comment_id', // phpcs:ignore WordPress.DB.SlowDBQuery -- Exact source comment lookup for sync mapping.
				'meta_value' => (int) $source_comment_id, // phpcs:ignore WordPress.DB.SlowDBQuery -- Exact source comment lookup for sync mapping.
				'fields'     => 'ids',
			)
		);

		return ! empty( $comments ) ? (int) $comments[0] : 0;
	}

	/**
	 * Import standalone synced comments by resolving each target post first.
	 *
	 * @param array $comments Comment payloads.
	 * @param mixed $post_mapping Post mapping.
	 * @param mixed $target_post_id Target post id.
	 * @return array Counts.
	 */
	private function import_standalone_synced_comments( $comments, $post_mapping = array(), $target_post_id = 0 ) {
		return $this->standalone_comment_importer()->import( $comments, $post_mapping, $target_post_id );
	}

	/**
	 * Prepare a local comment payload for standalone comment sync.
	 *
	 * @param \WP_Comment $comment Comment object.
	 * @return array
	 */
	private function prepare_standalone_comment_for_sync( $comment ) {
		$post = get_post( (int) $comment->comment_post_ID );
		$data = array(
			'comment_ID'           => (int) $comment->comment_ID,
			'comment_post_ID'      => (int) $comment->comment_post_ID,
			'comment_author'       => (string) $comment->comment_author,
			'comment_author_email' => (string) $comment->comment_author_email,
			'comment_author_url'   => (string) $comment->comment_author_url,
			'comment_author_IP'    => (string) $comment->comment_author_IP,
			'comment_date'         => (string) $comment->comment_date,
			'comment_date_gmt'     => (string) $comment->comment_date_gmt,
			'comment_content'      => $this->portable_synced_meta_value( (string) $comment->comment_content ),
			'comment_karma'        => (int) $comment->comment_karma,
			'comment_approved'     => (string) $comment->comment_approved,
			'comment_agent'        => (string) $comment->comment_agent,
			'comment_type'         => (string) $comment->comment_type,
			'comment_parent'       => (int) $comment->comment_parent,
			'user_id'              => (int) $comment->user_id,
			'post'                 => $post ? array(
				'ID'        => (int) $post->ID,
				'post_type' => (string) $post->post_type,
				'post_name' => (string) $post->post_name,
				'title'     => (string) get_the_title( $post ),
			) : array(),
			'meta'                 => array(),
		);

		$meta = get_comment_meta( (int) $comment->comment_ID );
		foreach ( $meta as $key => $values ) {
			$data['meta'][ $key ] = isset( $values[0] ) ? maybe_unserialize( $values[0] ) : '';
		}

		$data['acf'] = $this->export_comment_acf_for_sync( (int) $comment->comment_ID );

		return $data;
	}

	/**
	 * Export ACF fields attached to a comment.
	 *
	 * @param int $comment_id Comment ID.
	 * @return array
	 */
	private function export_comment_acf_for_sync( $comment_id ) {
		$acf = array();

		if ( ! class_exists( ACF_Fields::class ) ) {
			return $acf;
		}

		foreach ( ACF_Fields::get_fields_for_content_type( 'comment' ) as $field ) {
			if ( empty( $field['name'] ) ) {
				continue;
			}

			$name         = (string) $field['name'];
			$acf[ $name ] = ACF_Fields::export_value( 'comment', (int) $comment_id, $name );
		}

		return $acf;
	}

	/**
	 * Resolve target post for standalone comment sync.
	 *
	 * @param array $comment_data Comment payload.
	 * @param mixed $post_mapping Post mapping.
	 * @return int
	 */
	private function resolve_standalone_comment_post_id( $comment_data, $post_mapping = array() ) {
		$post_info      = isset( $comment_data['post'] ) && is_array( $comment_data['post'] ) ? $comment_data['post'] : array();
		$source_post_id = isset( $post_info['ID'] ) ? absint( $post_info['ID'] ) : absint( $comment_data['comment_post_ID'] ?? 0 );
		$post_type      = isset( $post_info['post_type'] ) ? sanitize_key( (string) $post_info['post_type'] ) : '';
		$post_name      = isset( $post_info['post_name'] ) ? sanitize_title( (string) $post_info['post_name'] ) : '';

		if ( $source_post_id > 0 && isset( $post_mapping[ $source_post_id ] ) ) {
			$mapped_post_id = absint( $post_mapping[ $source_post_id ] );
			if ( $mapped_post_id > 0 && get_post( $mapped_post_id ) ) {
				return $mapped_post_id;
			}
		}

		if ( $source_post_id > 0 ) {
			$matched = $this->find_existing_post_by_original_id( $source_post_id );
			if ( $matched > 0 ) {
				return (int) $matched;
			}
		}

		if ( '' !== $post_type && '' !== $post_name ) {
			$post = get_page_by_path( $post_name, OBJECT, $post_type );
			if ( $post ) {
				return (int) $post->ID;
			}
		}

		if ( '' !== $post_type && ! empty( $post_info['title'] ) ) {
			$matched_posts = get_posts(
				array(
					'post_type'              => $post_type,
					'post_status'            => 'any',
					'title'                  => sanitize_text_field( (string) $post_info['title'] ),
					'posts_per_page'         => 1,
					'fields'                 => 'ids',
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				)
			);
			if ( ! empty( $matched_posts ) ) {
				return (int) $matched_posts[0];
			}
		}

		return 0;
	}

	/**
	 * Import synced ACF fields into a comment.
	 *
	 * @param int   $comment_id   Comment ID.
	 * @param array $comment_data Comment sync payload.
	 * @return void
	 */
	private function import_comment_acf_for_sync( $comment_id, $comment_data ) {
		if ( $comment_id <= 0 || empty( $comment_data['acf'] ) || ! is_array( $comment_data['acf'] ) || ! class_exists( ACF_Fields::class ) ) {
			return;
		}

		foreach ( $comment_data['acf'] as $field_name => $value ) {
			$field_name = sanitize_text_field( (string) $field_name );
			if ( '' === $field_name ) {
				continue;
			}

			ACF_Fields::import_value( 'comment', (int) $comment_id, $field_name, $value );
		}
	}

	/**
	 * Collect media referenced by a comment's ACF payload.
	 *
	 * @param array $comment_data Prepared comment payload.
	 * @param array $all_images   Accumulator keyed by attachment ID.
	 * @return void
	 */
	private function collect_comment_acf_images_for_sync( array $comment_data, array &$all_images ) {
		$image_ids = array();
		if ( ! empty( $comment_data['comment_content'] ) && is_string( $comment_data['comment_content'] ) ) {
			$image_ids = array_merge( $image_ids, $this->extract_term_acf_images( array( 'comment_content' => $comment_data['comment_content'] ) ) );
		}

		if ( empty( $comment_data['acf'] ) || ! is_array( $comment_data['acf'] ) ) {
			$comment_data['acf'] = array();
		}

		$image_ids = array_merge( $image_ids, $this->extract_term_acf_images( $comment_data['acf'] ) );
		$image_ids = array_values( array_unique( array_filter( array_map( 'absint', $image_ids ) ) ) );
		foreach ( $image_ids as $image_id ) {
			if ( isset( $all_images[ $image_id ] ) ) {
				continue;
			}

			$image_data = \RockStarLab\ImportExport\Helper\Content_Sync_Media::prepare_image_data( $image_id, 'comment_acf' );
			if ( ! $image_data ) {
				$image_data = array(
					'attachment_id' => $image_id,
					'url'           => wp_get_attachment_url( $image_id ),
					'type'          => 'comment_acf',
				);
			}

			$image_data['comment_id'] = isset( $comment_data['comment_ID'] ) ? (int) $comment_data['comment_ID'] : 0;
			$all_images[ $image_id ]  = $image_data;
		}
	}

	/**
	 * Replace media references inside comment ACF payloads.
	 *
	 * @param array  $comments      Comment payloads.
	 * @param string $source_domain Source site URL.
	 * @param string $target_domain Target site URL.
	 * @param array  $image_map     Source attachment ID => target attachment ID.
	 * @param array  $image_sources Source image metadata.
	 * @return array
	 */
	private function replace_comment_acf_media_references( array $comments, $source_domain, $target_domain, array $image_map, array $image_sources ) {
		foreach ( $comments as &$comment_data ) {
			if ( isset( $comment_data['comment_content'] ) && is_string( $comment_data['comment_content'] ) ) {
				$comment_data['comment_content'] = \RockStarLab\ImportExport\Helper\Content_Sync_Replacer::replace_text_public(
					$comment_data['comment_content'],
					$source_domain,
					$target_domain
				);
				$comment_data['comment_content'] = \RockStarLab\ImportExport\Helper\Content_Sync_Replacer::fix_local_image_urls_in_content(
					$comment_data['comment_content'],
					$image_map,
					$image_sources
				);
			}

			if ( empty( $comment_data['acf'] ) || ! is_array( $comment_data['acf'] ) ) {
				continue;
			}

			$comment_data['acf'] = $this->replace_term_acf_value_media_references(
				$comment_data['acf'],
				$source_domain,
				$target_domain,
				$image_map,
				$image_sources
			);
		}
		unset( $comment_data );

		return $comments;
	}

	/**
	 * Determine whether a raw comment meta key belongs to synced ACF data.
	 *
	 * @param string $meta_key     Meta key.
	 * @param array  $comment_data Comment sync payload.
	 * @return bool
	 */
	private function is_acf_comment_sync_meta_key( $meta_key, $comment_data ) {
		if ( '' === $meta_key || empty( $comment_data['acf'] ) || ! is_array( $comment_data['acf'] ) ) {
			return false;
		}

		$acf_names = array_map( 'strval', array_keys( $comment_data['acf'] ) );
		foreach ( $acf_names as $acf_name ) {
			if ( '' === $acf_name ) {
				continue;
			}
			if (
				$meta_key === $acf_name
				|| '_' . $acf_name === $meta_key
				|| 0 === strpos( $meta_key, $acf_name . '_' )
				|| 0 === strpos( $meta_key, '_' . $acf_name . '_' )
			) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Import WooCommerce product variations and recalculate the variable product.
	 *
	 * @param int   $parent_post_id Local product post ID.
	 * @param array $variations     Variation data from the source site.
	 * @param array $image_map      Source attachment ID => local attachment ID map.
	 * @param mixed $image_sources Image sources.
	 * @param bool  $verified Whether to require confirmed variation identities.
	 * @return mixed
	 */
	private function import_product_variations( $parent_post_id, $variations, $image_map, $image_sources = array(), $verified = false ) {
		$helpers = [
			'find_existing_post_by_source_meta' => function ( ...$args ) {
				return $this->find_existing_post_by_source_meta( ...$args ); },
			'resolve_synced_term'               => function ( ...$args ) {
				return $this->resolve_synced_term( ...$args ); },
			'save_synced_post_meta'             => function ( ...$args ) {
				return $this->save_synced_post_meta( ...$args ); },
		];
		return apply_filters( 'rsl_ie_sync_extension', false, 'service', 'import_product_variations', [ $parent_post_id, $variations, $image_map, $image_sources, $verified ], $helpers );
	}

	/**
	 * Import WooCommerce grouped product children and return local child IDs.
	 *
	 * @param int   $parent_post_id Local grouped product post ID.
	 * @param array $children       Child product data from the source site.
	 * @param array $image_map      Source attachment ID => local attachment ID map.
	 * @param mixed $image_sources Image sources.
	 * @return int[]
	 */
	private function import_grouped_children( $parent_post_id, $children, $image_map, $image_sources = array() ) {
		$helpers = [
			'find_existing_post_by_source_meta' => function ( ...$args ) {
				return $this->find_existing_post_by_source_meta( ...$args ); },
			'resolve_synced_term'               => function ( ...$args ) {
				return $this->resolve_synced_term( ...$args ); },
			'save_synced_post_meta'             => function ( ...$args ) {
				return $this->save_synced_post_meta( ...$args ); },
		];
		return apply_filters( 'rsl_ie_sync_extension', false, 'service', 'import_grouped_children', [ $parent_post_id, $children, $image_map, $image_sources ], $helpers );
	}

	/**
	 * Convert flat ACF meta structure to hierarchical for repeater/flexible content fields
	 *
	 * @param array $meta Post meta array.
	 * @param array $acf_field_keys ACF field keys mapping.
	 * @return array Modified meta array
	 */
	private function convert_acf_flat_to_hierarchical( $meta, $acf_field_keys ) {
		$processed_parents = array();

		// Find all repeater/flexible content parent fields.
		foreach ( $acf_field_keys as $field_name => $field_key ) {
			// Skip nested fields.
			if ( preg_match( '/_\d+_/', $field_name ) ) {
				continue;
			}

			// Check if this field has a numeric value (count of rows) - typical for repeater.
			if ( isset( $meta[ $field_name ] ) && is_numeric( $meta[ $field_name ] ) ) {
				$row_count = intval( $meta[ $field_name ] );

				// Verify this is actually a repeater by checking if sub-fields exist
				// Look for pattern: field_name_0_*.
				$has_sub_fields = false;
				$row_prefix     = $field_name . '_0_';
				foreach ( $meta as $meta_key => $meta_value ) {
					if ( strpos( $meta_key, $row_prefix ) === 0 ) {
						$has_sub_fields = true;
						break;
					}
				}

				// If no sub-fields found, this is not a repeater (probably just a numeric field like image ID).
				if ( ! $has_sub_fields ) {
					continue;
				}

				// Build hierarchical structure.
				$rows = array();
				for ( $i = 0; $i < $row_count; $i++ ) {
					$row_data     = array();
					$row_prefix   = $field_name . '_' . $i . '_';
					$found_fields = 0;

					// Find all fields for this row.
					foreach ( $meta as $meta_key => $meta_value ) {
						if ( strpos( $meta_key, $row_prefix ) === 0 ) {
							++$found_fields;
							// Extract field name without row prefix.
							$sub_field_name = substr( $meta_key, strlen( $row_prefix ) );

							// Check if this is a nested repeater/flexible content.
							if ( isset( $acf_field_keys[ $field_name . '_' . $i . '_' . $sub_field_name ] ) && is_numeric( $meta_value ) ) {
								// Verify nested repeater has sub-fields.
								$nested_prefix         = $field_name . '_' . $i . '_' . $sub_field_name . '_0_';
								$nested_has_sub_fields = false;
								foreach ( $meta as $nested_key => $nested_val ) {
									if ( strpos( $nested_key, $nested_prefix ) === 0 ) {
										$nested_has_sub_fields = true;
										break;
									}
								}

								if ( $nested_has_sub_fields ) {
									// Recursively process nested repeater.
									$nested_rows                 = $this->extract_nested_repeater_data( $meta, $field_name . '_' . $i . '_' . $sub_field_name, $meta_value, $acf_field_keys );
									$row_data[ $sub_field_name ] = $nested_rows;
								} else {
									// Just a numeric value (like image ID).
									$row_data[ $sub_field_name ] = $meta_value;
								}
							} else {
								$row_data[ $sub_field_name ] = $meta_value;
							}
						}
					}

					$rows[] = $row_data;
				}

				// Replace numeric count with actual data array.
				$meta[ $field_name ] = $rows;
				$processed_parents[] = $field_name;

			}
		}

		return $meta;
	}

	/**
	 * Extract nested repeater data recursively
	 *
	 * @param array  $meta Post meta array.
	 * @param string $parent_prefix Parent field prefix (e.g., "repeater_0_nested_repeater").
	 * @param int    $row_count Number of rows.
	 * @param array  $acf_field_keys ACF field keys mapping.
	 * @return array Nested rows data
	 */
	private function extract_nested_repeater_data( $meta, $parent_prefix, $row_count, $acf_field_keys ) {
		$rows = array();

		for ( $i = 0; $i < $row_count; $i++ ) {
			$row_data   = array();
			$row_prefix = $parent_prefix . '_' . $i . '_';

			foreach ( $meta as $meta_key => $meta_value ) {
				if ( strpos( $meta_key, $row_prefix ) === 0 ) {
					$sub_field_name = substr( $meta_key, strlen( $row_prefix ) );

					// Check for even deeper nesting.
					if ( isset( $acf_field_keys[ $parent_prefix . '_' . $i . '_' . $sub_field_name ] ) && is_numeric( $meta_value ) ) {
						// Verify this nested field actually has sub-fields (is a real repeater).
						$nested_prefix         = $parent_prefix . '_' . $i . '_' . $sub_field_name . '_0_';
						$has_nested_sub_fields = false;
						foreach ( $meta as $check_key => $check_value ) {
							if ( strpos( $check_key, $nested_prefix ) === 0 ) {
								$has_nested_sub_fields = true;
								break;
							}
						}

						if ( $has_nested_sub_fields ) {
							// This is a nested repeater.
							$row_data[ $sub_field_name ] = $this->extract_nested_repeater_data(
								$meta,
								$parent_prefix . '_' . $i . '_' . $sub_field_name,
								$meta_value,
								$acf_field_keys
							);
						} else {
							// Just a numeric value (like image ID).
							$row_data[ $sub_field_name ] = $meta_value;
						}
					} else {
						$row_data[ $sub_field_name ] = $meta_value;
					}
				}
			}

			$rows[] = $row_data;
		}

		return $rows;
	}

	/**
	 * Find existing post by original post ID
	 *
	 * @param int $original_post_id Original post ID from source site.
	 * @return int|false Post ID or false if not found
	 */
	private function find_existing_post_by_original_id( $original_post_id ) {
		// Only update posts that were previously synced by this plugin. A plain
		// numeric ID match is not safe because a fresh target site may already
		// have an unrelated wp_posts row with the same ID (attachment, page,
		// revision, product variation, etc.).
		$original_post_id = absint( $original_post_id );
		if ( $original_post_id <= 0 ) {
			return false;
		}

		return $this->find_existing_post_by_source_meta( $original_post_id );
	}

	/**
	 * Find an existing post by any source-id meta key used by import/sync flows.
	 *
	 * @param int    $source_post_id Source-site post ID.
	 * @param string $post_type      Optional post type.
	 * @return int|false
	 */
	private function find_existing_post_by_source_meta( $source_post_id, $post_type = 'any' ) {
		$source_post_id = absint( $source_post_id );
		if ( $source_post_id <= 0 ) {
			return false;
		}

		$posts = get_posts(
			array(
				'post_type'      => $post_type ? $post_type : 'any',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery -- Exact source-id lookup is required for sync mapping.
					'relation' => 'OR',
					array(
						'key'   => '_rsl_ie_original_post_id',
						'value' => $source_post_id,
					),
					array(
						'key'   => '_rsl_ie_source_id',
						'value' => $source_post_id,
					),
					array(
						'key'   => '_aie_original_post_id',
						'value' => $source_post_id,
					),
				),
			)
		);

		return ! empty( $posts ) ? (int) $posts[0] : false;
	}

	/**
	 * Find a single existing local post by post type and slug.
	 *
	 * This lets first-run migrations update default WordPress content such as
	 * Sample Page and Privacy Policy before source-id meta exists locally.
	 *
	 * @param array $post_data Remote post data.
	 * @return int|false
	 */
	private function find_existing_post_by_slug( array $post_data ) {
		$post_type = isset( $post_data['post_type'] ) ? sanitize_key( (string) $post_data['post_type'] ) : '';
		$post_name = isset( $post_data['post_name'] ) ? sanitize_title( (string) $post_data['post_name'] ) : '';

		if ( '' === $post_type || '' === $post_name ) {
			return false;
		}

		$posts = get_posts(
			array(
				'name'                   => $post_name,
				'post_type'              => $post_type,
				'post_status'            => 'any',
				'posts_per_page'         => 2,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		if ( 1 === count( $posts ) ) {
			return (int) $posts[0];
		}

		$unmapped_posts = array_values(
			array_filter(
				$posts,
				function ( $post_id ) {
					return '' === (string) get_post_meta( (int) $post_id, '_rsl_ie_original_post_id', true )
						&& '' === (string) get_post_meta( (int) $post_id, '_rsl_ie_source_id', true )
						&& '' === (string) get_post_meta( (int) $post_id, '_aie_original_post_id', true );
				}
			)
		);

		return 1 === count( $unmapped_posts ) ? (int) $unmapped_posts[0] : false;
	}

	/**
	 * Find an existing product by source SKU.
	 *
	 * @param array $post_data Remote post data.
	 * @return int|false
	 */
	private function find_existing_product_by_sku( array $post_data ) {
		$helpers = [
			'find_existing_post_by_source_meta' => function ( ...$args ) {
				return $this->find_existing_post_by_source_meta( ...$args ); },
			'resolve_synced_term'               => function ( ...$args ) {
				return $this->resolve_synced_term( ...$args ); },
			'save_synced_post_meta'             => function ( ...$args ) {
				return $this->save_synced_post_meta( ...$args ); },
		];
		return apply_filters( 'rsl_ie_sync_extension', false, 'service', 'find_existing_product_by_sku', [ $post_data ], $helpers );
	}

	/**
	 * Sync woocommerce product type.
	 *
	 * @param mixed $post_id Post id.
	 * @param array $post_data Post data.
	 * @return mixed
	 */
	private function sync_woocommerce_product_type( $post_id, array $post_data ) {
		$helpers = [
			'find_existing_post_by_source_meta' => function ( ...$args ) {
				return $this->find_existing_post_by_source_meta( ...$args ); },
			'resolve_synced_term'               => function ( ...$args ) {
				return $this->resolve_synced_term( ...$args ); },
			'save_synced_post_meta'             => function ( ...$args ) {
				return $this->save_synced_post_meta( ...$args ); },
		];
		return apply_filters( 'rsl_ie_sync_extension', false, 'service', 'sync_woocommerce_product_type', [ $post_id, $post_data ], $helpers );
	}

	/**
	 * Restore source modified timestamps after sync follow-up updates.
	 *
	 * @param int   $post_id   Local post ID.
	 * @param array $post_data Source post payload.
	 * @return void
	 */
	private function restore_synced_post_modified_dates( $post_id, array $post_data ) {
		$post_id = absint( $post_id );
		if ( $post_id <= 0 || ( empty( $post_data['post_modified'] ) && empty( $post_data['post_modified_gmt'] ) ) ) {
			return;
		}

		global $wpdb;

		$update = [];
		$format = [];

		if ( ! empty( $post_data['post_modified'] ) ) {
			$update['post_modified'] = (string) $post_data['post_modified'];
			$format[]                = '%s';
		}

		if ( ! empty( $post_data['post_modified_gmt'] ) ) {
			$update['post_modified_gmt'] = (string) $post_data['post_modified_gmt'];
			$format[]                    = '%s';
		}

		$wpdb->update( $wpdb->posts, $update, [ 'ID' => $post_id ], $format, [ '%d' ] );
		clean_post_cache( $post_id );
	}

	/**
	 * Resolve or create a synced taxonomy term, preserving hierarchy when present.
	 *
	 * @param string $taxonomy  Taxonomy name.
	 * @param array  $term_info Synced term payload.
	 * @return int Local term ID, or 0 on failure.
	 */
	private function resolve_synced_term( $taxonomy, $term_info ) {
		if ( ! taxonomy_exists( $taxonomy ) || empty( $term_info['name'] ) || empty( $term_info['slug'] ) ) {
			return 0;
		}

		$parent_id = 0;
		if ( is_taxonomy_hierarchical( $taxonomy ) ) {
			if ( ! empty( $term_info['parent_path'] ) ) {
				$parent_id = $this->resolve_synced_term_parent_path( $taxonomy, (string) $term_info['parent_path'] );
			} elseif ( ! empty( $term_info['parent_slug'] ) ) {
				$parent_id = $this->resolve_synced_term_parent_path( $taxonomy, (string) $term_info['parent_slug'] );
			}
		}

		$args = array(
			'name' => sanitize_text_field( (string) $term_info['name'] ),
		);
		if ( is_taxonomy_hierarchical( $taxonomy ) ) {
			$args['parent'] = $parent_id;
		}

		$existing_term = get_term_by( 'slug', sanitize_title( (string) $term_info['slug'] ), $taxonomy );
		if ( $existing_term ) {
			wp_update_term( (int) $existing_term->term_id, $taxonomy, wp_slash( $args ) );
			return (int) $existing_term->term_id;
		}

		$args['slug'] = sanitize_title( (string) $term_info['slug'] );
		$new_term     = wp_insert_term( wp_slash( sanitize_text_field( (string) $term_info['name'] ) ), $taxonomy, wp_slash( $args ) );
		if ( is_wp_error( $new_term ) || empty( $new_term['term_id'] ) ) {
			return 0;
		}

		$term_id = (int) $new_term['term_id'];

		return $term_id;
	}

	/**
	 * Update a specific mapped local taxonomy term from a remote term payload.
	 *
	 * @param string $taxonomy      Taxonomy name.
	 * @param int    $local_term_id Local term ID selected in the mapping UI.
	 * @param array  $term_info     Remote term payload.
	 * @return int Local term ID, or 0 on failure.
	 */
	private function update_mapped_synced_term( $taxonomy, $local_term_id, $term_info ) {
		if ( ! taxonomy_exists( $taxonomy ) || $local_term_id <= 0 || empty( $term_info['name'] ) ) {
			return 0;
		}

		$current_term = get_term( $local_term_id, $taxonomy );
		if ( ! $current_term || is_wp_error( $current_term ) ) {
			return 0;
		}

		$args = array(
			'name' => sanitize_text_field( (string) $term_info['name'] ),
		);

		if ( ! empty( $term_info['slug'] ) ) {
			$args['slug'] = sanitize_title( (string) $term_info['slug'] );
		}

		if ( is_taxonomy_hierarchical( $taxonomy ) ) {
			$parent_id = 0;
			if ( ! empty( $term_info['parent_path'] ) ) {
				$parent_id = $this->resolve_synced_term_parent_path( $taxonomy, (string) $term_info['parent_path'] );
			} elseif ( ! empty( $term_info['parent_slug'] ) ) {
				$parent_id = $this->resolve_synced_term_parent_path( $taxonomy, (string) $term_info['parent_slug'] );
			}
			if ( $parent_id !== $local_term_id ) {
				$args['parent'] = $parent_id;
			}
		}

		$updated = wp_update_term( $local_term_id, $taxonomy, wp_slash( $args ) );
		if ( is_wp_error( $updated ) ) {
			return 0;
		}

		return $local_term_id;
	}

	/**
	 * Prepare a taxonomy term payload for standalone taxonomy sync.
	 *
	 * @param \WP_Term $term     Term object.
	 * @param string   $taxonomy Taxonomy name.
	 * @return array
	 */
	private function prepare_term_for_sync( $term, $taxonomy ) {
		$helpers = [
			'collect_term_acf_images_for_sync'  => function ( $info, $term, $taxonomy, &$images ) {
				return $this->collect_term_acf_images_for_sync( $info, $term, $taxonomy, $images ); },
			'download_remote_images_for_sync'   => function ( ...$args ) {
				return $this->download_remote_images_for_sync( ...$args ); },
			'export_term_acf_sync_value'        => function ( ...$args ) {
				return $this->export_term_acf_sync_value( ...$args ); },
			'get_term_acf_field_objects'        => function ( ...$args ) {
				return $this->get_term_acf_field_objects( ...$args ); },
			'get_term_parent_path'              => function ( ...$args ) {
				return $this->get_term_parent_path( ...$args ); },
			'get_term_slug_by_id'               => function ( ...$args ) {
				return $this->get_term_slug_by_id( ...$args ); },
			'portable_synced_meta_value'        => function ( ...$args ) {
				return $this->portable_synced_meta_value( ...$args ); },
			'remote_post'                       => function ( ...$args ) {
				return $this->remote_post( ...$args ); },
			'replace_term_acf_media_references' => function ( ...$args ) {
				return $this->replace_term_acf_media_references( ...$args ); },
			'resolve_synced_media_references'   => function ( ...$args ) {
				return $this->resolve_synced_media_references( ...$args ); },
			'resolve_synced_term'               => function ( ...$args ) {
				return $this->resolve_synced_term( ...$args ); },
			'update_mapped_synced_term'         => function ( ...$args ) {
				return $this->update_mapped_synced_term( ...$args ); },
			'upload_images_to_remote'           => function ( ...$args ) {
				return $this->upload_images_to_remote( ...$args ); },
		];
		return apply_filters( 'rsl_ie_sync_terms_operation', \RockStarLab\ImportExport\Abilities\Standalone_Sync_Policy::denied(), 'prepare_term_for_sync', [ $term, $taxonomy ], $helpers );
	}

	/**
	 * Preserve taxonomy WYSIWYG shortcodes in sync payloads.
	 *
	 * @param mixed $term_id Term id.
	 * @param mixed $field_name Field name.
	 * @param mixed $field Field.
	 */
	private function export_term_acf_sync_value( $term_id, $field_name, $field ) {
		$type = is_array( $field ) ? (string) ( $field['type'] ?? '' ) : '';
		if ( 'wysiwyg' === $type ) {
			$raw = get_term_meta( (int) $term_id, (string) $field_name, true );
			return class_exists( ACF_Fields::class )
				? ACF_Fields::export_string_with_media_shortcode_tokens( (string) $raw )
				: (string) $raw;
		}

		return ACF_Fields::export_value( 'term', (int) $term_id, (string) $field_name, '' );
	}

	/**
	 * Import synced taxonomy terms into the local site.
	 *
	 * @param string $taxonomy     Taxonomy name.
	 * @param array  $terms        Term payloads.
	 * @param array  $term_mapping Optional source remote term ID => local term ID mapping.
	 * @return array Counts.
	 */
	private function import_synced_terms( $taxonomy, $terms, $term_mapping = array() ) {
		$helpers = [
			'collect_term_acf_images_for_sync'  => function ( $info, $term, $taxonomy, &$images ) {
				return $this->collect_term_acf_images_for_sync( $info, $term, $taxonomy, $images ); },
			'download_remote_images_for_sync'   => function ( ...$args ) {
				return $this->download_remote_images_for_sync( ...$args ); },
			'export_term_acf_sync_value'        => function ( ...$args ) {
				return $this->export_term_acf_sync_value( ...$args ); },
			'get_term_acf_field_objects'        => function ( ...$args ) {
				return $this->get_term_acf_field_objects( ...$args ); },
			'get_term_parent_path'              => function ( ...$args ) {
				return $this->get_term_parent_path( ...$args ); },
			'get_term_slug_by_id'               => function ( ...$args ) {
				return $this->get_term_slug_by_id( ...$args ); },
			'portable_synced_meta_value'        => function ( ...$args ) {
				return $this->portable_synced_meta_value( ...$args ); },
			'remote_post'                       => function ( ...$args ) {
				return $this->remote_post( ...$args ); },
			'replace_term_acf_media_references' => function ( ...$args ) {
				return $this->replace_term_acf_media_references( ...$args ); },
			'resolve_synced_media_references'   => function ( ...$args ) {
				return $this->resolve_synced_media_references( ...$args ); },
			'resolve_synced_term'               => function ( ...$args ) {
				return $this->resolve_synced_term( ...$args ); },
			'update_mapped_synced_term'         => function ( ...$args ) {
				return $this->update_mapped_synced_term( ...$args ); },
			'upload_images_to_remote'           => function ( ...$args ) {
				return $this->upload_images_to_remote( ...$args ); },
		];
		return apply_filters( 'rsl_ie_sync_terms_operation', \RockStarLab\ImportExport\Abilities\Standalone_Sync_Policy::denied(), 'import_synced_terms', [ $taxonomy, $terms, $term_mapping ], $helpers );
	}

	/**
	 * Collect media referenced by a term's ACF payload.
	 *
	 * @param array    $term_info  Prepared term payload.
	 * @param \WP_Term $term       Term object.
	 * @param string   $taxonomy   Taxonomy.
	 * @param array    $all_images Accumulator keyed by attachment ID.
	 * @return void
	 */
	private function collect_term_acf_images_for_sync( array $term_info, $term, $taxonomy, array &$all_images ) {
		$image_ids = array();
		if ( ! empty( $term_info['description'] ) && is_string( $term_info['description'] ) ) {
			$image_ids = array_merge( $image_ids, $this->extract_term_acf_images( array( 'description' => $term_info['description'] ) ) );
		}
		if ( ! empty( $term_info['acf'] ) && is_array( $term_info['acf'] ) ) {
			$image_ids = array_merge( $image_ids, $this->extract_term_acf_images( $term_info['acf'] ) );
		}
		$image_ids = array_values( array_unique( array_filter( array_map( 'absint', $image_ids ) ) ) );
		if ( empty( $image_ids ) ) {
			return;
		}

		foreach ( $image_ids as $image_id ) {
			if ( isset( $all_images[ $image_id ] ) ) {
				continue;
			}

			$image_data = \RockStarLab\ImportExport\Helper\Content_Sync_Media::prepare_image_data( $image_id, 'term_acf' );
			if ( ! $image_data ) {
				$image_data = array(
					'attachment_id' => $image_id,
					'url'           => wp_get_attachment_url( $image_id ),
					'type'          => 'term_acf',
				);
			}

			$image_data['term_id']   = (int) $term->term_id;
			$image_data['taxonomy']  = $taxonomy;
			$all_images[ $image_id ] = $image_data;
		}
	}

	/**
	 * Get term acf field objects.
	 *
	 * @param mixed $term_id Term id.
	 * @param mixed $taxonomy Taxonomy.
	 * @return array|false
	 */
	private function get_term_acf_field_objects( $term_id, $taxonomy ) {
		if ( ! function_exists( 'get_field_objects' ) ) {
			return false;
		}

		$fields = get_field_objects( 'term_' . (int) $term_id );
		if ( ! empty( $fields ) ) {
			return $fields;
		}

		return get_field_objects( sanitize_key( (string) $taxonomy ) . '_' . (int) $term_id );
	}

	/**
	 * Replace media URLs and attachment IDs inside term ACF payloads.
	 *
	 * @param array  $terms         Term payloads.
	 * @param string $source_domain Source site URL.
	 * @param string $target_domain Target site URL.
	 * @param array  $image_map     Source attachment ID => target attachment ID.
	 * @param array  $image_sources Source image metadata.
	 * @return array
	 */
	private function replace_term_acf_media_references( array $terms, $source_domain, $target_domain, array $image_map, array $image_sources ) {
		foreach ( $terms as &$term_info ) {
			if ( isset( $term_info['description'] ) && is_string( $term_info['description'] ) ) {
				$term_info['description'] = \RockStarLab\ImportExport\Helper\Content_Sync_Replacer::replace_text_public(
					$term_info['description'],
					$source_domain,
					$target_domain
				);
				$term_info['description'] = \RockStarLab\ImportExport\Helper\Content_Sync_Replacer::fix_local_image_urls_in_content(
					$term_info['description'],
					$image_map,
					$image_sources
				);
			}

			if ( empty( $term_info['acf'] ) || ! is_array( $term_info['acf'] ) ) {
				continue;
			}

			$term_info['acf'] = $this->replace_term_acf_value_media_references(
				$term_info['acf'],
				$source_domain,
				$target_domain,
				$image_map,
				$image_sources
			);
		}
		unset( $term_info );

		return $terms;
	}

	/**
	 * Replace media references inside one ACF value.
	 *
	 * @param mixed  $value         ACF value.
	 * @param string $source_domain Source site URL.
	 * @param string $target_domain Target site URL.
	 * @param array  $image_map     Source attachment ID => target attachment ID.
	 * @param array  $image_sources Source image metadata.
	 * @return mixed
	 */
	private function replace_term_acf_value_media_references( $value, $source_domain, $target_domain, array $image_map, array $image_sources ) {
		if ( is_array( $value ) ) {
			$out = array();
			foreach ( $value as $key => $child ) {
				if ( is_numeric( $child ) && isset( $image_map[ (int) $child ] ) && $this->looks_like_term_acf_media_key( $key ) ) {
					$out[ $key ] = (int) $image_map[ (int) $child ];
					continue;
				}

				$out[ $key ] = $this->replace_term_acf_value_media_references(
					$child,
					$source_domain,
					$target_domain,
					$image_map,
					$image_sources
				);
			}

			if ( isset( $out['id'] ) && is_numeric( $out['id'] ) && isset( $image_map[ (int) $out['id'] ] ) ) {
				$mapped_id = (int) $image_map[ (int) $out['id'] ];
				$out['id'] = $mapped_id;
				$url       = wp_get_attachment_url( $mapped_id );
				if ( $url && isset( $out['url'] ) ) {
					$out['url'] = $url;
				}
			}

			return $out;
		}

		if ( is_string( $value ) && '' !== $value ) {
			$decoded = json_decode( $value, true );
			if ( is_array( $decoded ) ) {
				return wp_json_encode(
					$this->replace_term_acf_value_media_references(
						$decoded,
						$source_domain,
						$target_domain,
						$image_map,
						$image_sources
					)
				);
			}

			$mapped_url = $this->get_mapped_acf_media_url( $value, $image_map, $image_sources );
			if ( '' !== $mapped_url ) {
				return $mapped_url;
			}

			// Resolve portable gallery/playlist tokens back to shortcodes and
			// replace their source IDs with the downloaded local attachment IDs.
			if ( class_exists( ACF_Fields::class ) !== strpos( $value, '[[RSL_IE:' ) ) {
				return ACF_Fields::replace_media_urls_in_html( $value );
			}

			$value = \RockStarLab\ImportExport\Helper\Content_Sync_Replacer::replace_text_public(
				$value,
				$source_domain,
				$target_domain
			);

			return \RockStarLab\ImportExport\Helper\Content_Sync_Replacer::fix_local_image_urls_in_content(
				$value,
				$image_map,
				$image_sources
			);
		}

		return $value;
	}

	/**
	 * Looks like term acf media key.
	 *
	 * @param mixed $key Key.
	 * @return bool
	 */
	private function looks_like_term_acf_media_key( $key ) {
		if ( is_int( $key ) ) {
			return true;
		}

		return in_array(
			(string) $key,
			array( 'id', 'ID', 'attachment_id', 'image_id', 'media_id', 'file_id' ),
			true
		);
	}

	/**
	 * Return the target attachment URL for a source media URL when it is mapped.
	 *
	 * @param string $url           Source URL.
	 * @param array  $image_map     Source attachment ID => target attachment ID.
	 * @param array  $image_sources Source image metadata.
	 * @return string
	 */
	private function get_mapped_acf_media_url( $url, array $image_map, array $image_sources ) {
		$url = trim( html_entity_decode( (string) $url, ENT_QUOTES, get_bloginfo( 'charset' ) ) );
		if ( '' === $url || empty( $image_map ) || empty( $image_sources ) ) {
			return '';
		}

		foreach ( $image_sources as $source_id => $source ) {
			if ( ! is_array( $source ) ) {
				continue;
			}

			$attachment_id = isset( $source['attachment_id'] ) ? (int) $source['attachment_id'] : (int) $source_id;
			if ( $attachment_id <= 0 || empty( $image_map[ $attachment_id ] ) ) {
				continue;
			}

			$source_urls = array_filter(
				array(
					isset( $source['url'] ) ? (string) $source['url'] : '',
					isset( $source['full_url'] ) ? (string) $source['full_url'] : '',
				)
			);

			foreach ( $source_urls as $source_url ) {
				if ( html_entity_decode( $source_url, ENT_QUOTES, get_bloginfo( 'charset' ) ) !== $url ) {
					continue;
				}

				$target_url = wp_get_attachment_url( (int) $image_map[ $attachment_id ] );
				return $target_url ? (string) $target_url : '';
			}
		}

		return '';
	}

	/**
	 * Resolve or create parent terms from a slash-separated slug path.
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @param string $path     Parent slug path.
	 * @return int Final parent term ID.
	 */
	private function resolve_synced_term_parent_path( $taxonomy, $path ) {
		$slugs     = array_filter( array_map( 'sanitize_title', explode( '/', (string) $path ) ) );
		$parent_id = 0;

		foreach ( $slugs as $slug ) {
			$term = get_term_by( 'slug', $slug, $taxonomy );
			if ( $term && ! is_wp_error( $term ) ) {
				if ( (int) $term->parent !== $parent_id ) {
					wp_update_term( (int) $term->term_id, $taxonomy, array( 'parent' => $parent_id ) );
				}
				$parent_id = (int) $term->term_id;
				continue;
			}

			$new_term = wp_insert_term(
				ucwords( str_replace( array( '-', '_' ), ' ', $slug ) ),
				$taxonomy,
				array(
					'slug'   => $slug,
					'parent' => $parent_id,
				)
			);
			if ( is_wp_error( $new_term ) || empty( $new_term['term_id'] ) ) {
				return $parent_id;
			}
			$parent_id = (int) $new_term['term_id'];
		}

		return $parent_id;
	}

	/**
	 * Get term slug by ID.
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy name.
	 * @return string Term slug.
	 */
	private function get_term_slug_by_id( $term_id, $taxonomy ) {
		if ( $term_id <= 0 ) {
			return '';
		}
		$term = get_term( (int) $term_id, $taxonomy );
		return ( $term && ! is_wp_error( $term ) ) ? (string) $term->slug : '';
	}

	/**
	 * Get a slash-separated parent slug path for a term parent.
	 *
	 * @param int    $parent_id Parent term ID.
	 * @param string $taxonomy  Taxonomy name.
	 * @return string Parent path.
	 */
	private function get_term_parent_path( $parent_id, $taxonomy ) {
		if ( $parent_id <= 0 || ! taxonomy_exists( $taxonomy ) ) {
			return '';
		}

		$ancestors   = array_reverse( get_ancestors( (int) $parent_id, $taxonomy, 'taxonomy' ) );
		$ancestors[] = (int) $parent_id;

		$slugs = array();
		foreach ( $ancestors as $ancestor_id ) {
			$slug = $this->get_term_slug_by_id( (int) $ancestor_id, $taxonomy );
			if ( '' !== $slug ) {
				$slugs[] = $slug;
			}
		}

		return implode( '/', $slugs );
	}

	/**
	 * Ensure a WooCommerce global product attribute taxonomy exists in this request.
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @return bool Whether the taxonomy is available.
	 */
	private function ensure_woocommerce_attribute_taxonomy( $taxonomy ) {
		$helpers = [
			'find_existing_post_by_source_meta' => function ( ...$args ) {
				return $this->find_existing_post_by_source_meta( ...$args ); },
			'resolve_synced_term'               => function ( ...$args ) {
				return $this->resolve_synced_term( ...$args ); },
			'save_synced_post_meta'             => function ( ...$args ) {
				return $this->save_synced_post_meta( ...$args ); },
		];
		return apply_filters( 'rsl_ie_sync_extension', false, 'service', 'ensure_woocommerce_attribute_taxonomy', [ $taxonomy ], $helpers );
	}

	/**
	 * Refresh WooCommerce product caches and lookup tables after direct meta sync.
	 *
	 * @param int $post_id Product post ID.
	 * @return mixed
	 */
	private function refresh_woocommerce_product_after_sync( $post_id ) {
		$helpers = [
			'find_existing_post_by_source_meta' => function ( ...$args ) {
				return $this->find_existing_post_by_source_meta( ...$args ); },
			'resolve_synced_term'               => function ( ...$args ) {
				return $this->resolve_synced_term( ...$args ); },
			'save_synced_post_meta'             => function ( ...$args ) {
				return $this->save_synced_post_meta( ...$args ); },
		];
		return apply_filters( 'rsl_ie_sync_extension', false, 'service', 'refresh_woocommerce_product_after_sync', [ $post_id ], $helpers );
	}

	/**
	 * Extract image IDs from term ACF fields
	 *
	 * @param array $acf_data ACF field data.
	 * @return array Array of image IDs
	 */
	private function extract_term_acf_images( $acf_data ) {
		$image_ids = array();

		foreach ( $acf_data as $key => $value ) {
			if ( is_string( $value ) && '' !== $value ) {
				$decoded = json_decode( $value, true );
				if ( is_array( $decoded ) ) {
					$image_ids = array_merge( $image_ids, $this->extract_term_acf_images( $decoded ) );
				}

				if ( class_exists( ACF_Fields::class ) ) {
					foreach ( ACF_Fields::extract_media_shortcode_token_source_ids( $value ) as $image_id ) {
						$image_ids[] = (int) $image_id;
					}
				}

				if ( preg_match_all( '/\bwp-image-(\d+)\b/', $value, $matches ) ) {
					foreach ( $matches[1] as $image_id ) {
						$image_ids[] = (int) $image_id;
					}
				}

				if ( preg_match_all( '/\[(gallery|playlist)\b[^\]]*\bids=["\']([\d,\s]+)["\'][^\]]*\]/i', $value, $matches ) ) {
					foreach ( $matches[2] as $ids_string ) {
						foreach ( array_filter( array_map( 'absint', (array) preg_split( '/\s*,\s*/', (string) $ids_string ) ) ) as $attachment_id ) {
							$image_ids[] = (int) $attachment_id;
						}
					}
				}

				foreach ( $this->extract_image_urls_from_term_acf_html( $value ) as $url ) {
					$image_id = \RockStarLab\ImportExport\Helper\Content_Sync_Media::attachment_url_to_original_postid( $url );
					if ( $image_id > 0 ) {
						$image_ids[] = (int) $image_id;
					}
				}

				if ( filter_var( $value, FILTER_VALIDATE_URL ) && $this->is_term_acf_image_url( $value ) ) {
					$image_id = \RockStarLab\ImportExport\Helper\Content_Sync_Media::attachment_url_to_original_postid( $value );
					if ( $image_id > 0 ) {
						$image_ids[] = (int) $image_id;
					}
				}
			}

			if ( is_array( $value ) ) {
				foreach ( array( 'ID', 'id', 'attachment_id' ) as $id_key ) {
					if ( isset( $value[ $id_key ] ) && is_numeric( $value[ $id_key ] ) ) {
						$image_id   = (int) $value[ $id_key ];
						$attachment = get_post( $image_id );
						if ( $attachment && 'attachment' === $attachment->post_type ) {
							$image_ids[] = $image_id;
						}
					}
				}

				if ( isset( $value['url'] ) && is_string( $value['url'] ) && $this->is_term_acf_image_url( $value['url'] ) ) {
					$image_id = \RockStarLab\ImportExport\Helper\Content_Sync_Media::attachment_url_to_original_postid( $value['url'] );
					if ( $image_id > 0 ) {
						$image_ids[] = (int) $image_id;
					}
				}
			}

			// Single image field (numeric ID).
			if ( is_numeric( $value ) && $value > 0 ) {
				$attachment = get_post( $value );
				if ( $attachment && 'attachment' === $attachment->post_type ) {
					$image_ids[] = (int) $value;
				}
			} elseif ( is_array( $value ) ) {
				foreach ( $value as $item ) {
					if ( is_numeric( $item ) && $item > 0 ) {
						$attachment = get_post( $item );
						if ( $attachment && 'attachment' === $attachment->post_type ) {
							$image_ids[] = (int) $item;
						}
					} elseif ( is_array( $item ) ) {
						$nested_images = $this->extract_term_acf_images( $item );
						$image_ids     = array_merge( $image_ids, $nested_images );
					}
				}
			}
		}

		// HTML/shortcode IDs may refer to deleted media or unrelated posts.
		return array_values( array_filter( array_unique( $image_ids ), static function ( $id ) {
			return 'attachment' === get_post_type( $id ) && wp_get_attachment_url( $id );
		} ) );
	}

	/**
	 * Extract image urls from term acf html.
	 *
	 * @param mixed $content Content.
	 * @return array
	 */
	private function extract_image_urls_from_term_acf_html( $content ) {
		$urls = array();

		if ( false === stripos( $content, '<img' ) && false === stripos( $content, 'srcset=' ) ) {
			return $urls;
		}

		if ( preg_match_all( '/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches ) ) {
			foreach ( $matches[1] as $url ) {
				$url = html_entity_decode( $url, ENT_QUOTES, get_bloginfo( 'charset' ) );
				if ( $this->is_term_acf_image_url( $url ) ) {
					$urls[] = $url;
				}
			}
		}

		if ( preg_match_all( '/srcset=["\']([^"\']+)["\']/i', $content, $matches ) ) {
			foreach ( $matches[1] as $srcset ) {
				foreach ( array_map( 'trim', explode( ',', $srcset ) ) as $candidate ) {
					$parts = preg_split( '/\s+/', $candidate );
					$url   = isset( $parts[0] ) ? html_entity_decode( $parts[0], ENT_QUOTES, get_bloginfo( 'charset' ) ) : '';
					if ( $this->is_term_acf_image_url( $url ) ) {
						$urls[] = $url;
					}
				}
			}
		}

		return array_values( array_unique( $urls ) );
	}

	/**
	 * Is term acf image url.
	 *
	 * @param mixed $url Url.
	 * @return bool
	 */
	private function is_term_acf_image_url( $url ) {
		$path = (string) wp_parse_url( html_entity_decode( (string) $url, ENT_QUOTES, get_bloginfo( 'charset' ) ), PHP_URL_PATH );
		return '' !== $path && (bool) preg_match( '~\.(?:jpe?g|png|gif|webp|avif|svg)$~i', $path );
	}
	/**
	 * Share comment persistence with the other sync transport.
	 *
	 * @return \RockStarLab\ImportExport\Service\Comment_Sync_Importer
	 */
	private function standalone_comment_importer() {
		return new \RockStarLab\ImportExport\Service\Comment_Sync_Importer(
			[
				'resolve'  => function ( $data, $map ) {
					return $this->resolve_standalone_comment_post_id( $data, $map ); },
				'media'    => function ( $value, $post ) {
					return $this->resolve_synced_media_references( $value, $post ); },
				'acf'      => function ( $id, $data ) {
					return $this->import_comment_acf_for_sync( $id, $data ); },
				'acf_meta' => function ( $key, $data ) {
					return $this->is_acf_comment_sync_meta_key( $key, $data ); },
			]
		);
	}
	/**
	 * Read the native outgoing payload without a history job or remote writes.
	 *
	 * @param array $post_ids Bounded source IDs.
	 * @return array|\WP_Error
	 */
	public function preview_posts( array $post_ids ) {
		if ( ! $post_ids || count( $post_ids ) > 50 ) {
			return \RockStarLab\ImportExport\Service\Migration_Sync_Maps::error();
		}
		$access = \RockStarLab\ImportExport\Abilities\Sync_Peer_Policy::check( 'send_content', [ 'post_ids' => $post_ids ] );
		return is_wp_error( $access ) ? $access : $this->prepare_posts( $post_ids );
	}

	/**
	 * Assemble records through the same serializers used by the live transfer.
	 *
	 * @param array $post_ids Authorized source IDs.
	 * @return array
	 */
	private function prepare_posts( array $post_ids ) {
		// Prepare posts data with images.
		$posts_data    = array();
		$all_images    = array();
		$image_context = array(); // Track which post each image belongs to.

		foreach ( $post_ids as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				continue;
			}

			// Extract all images from post.
			$post_images = \RockStarLab\ImportExport\Helper\Content_Sync_Media::extract_post_images( $post_id );

			// Store images with post context.
			foreach ( $post_images as $image ) {
				$image_key                     = $image['attachment_id'];
				$all_images[ $image_key ]      = $image;
				$image_context[ $image_key ][] = $post_id;
			}

			// Get post meta.
			$meta           = get_post_meta( $post_id );
			$prepared_meta  = array();
			$skip_meta_keys = array(
				'_edit_lock',
				'_edit_last',
				'_wp_old_slug',
				'_wp_old_date',
				// Internal Content Sync meta: must never be pushed to remote, otherwise it
				// overwrites the receiving site's own "_rsl_ie_original_post_id" mapping.
				'_rsl_ie_original_post_id',
			);
			foreach ( $meta as $key => $values ) {
				if ( in_array( $key, $skip_meta_keys, true ) || $this->should_skip_synced_meta_key( (string) $key ) ) {
					continue;
				}
				$prepared_meta[ $key ] = $this->portable_synced_meta_value( maybe_unserialize( $values[0] ) );
			}

			// Get post terms with ACF fields.
			// All taxonomies are included — even empty ones — so the receiving site
			// can clear any stale term assignments it already has for those taxonomies.
			$taxonomies = get_object_taxonomies( $post->post_type );
			$terms_data = array();
			foreach ( $taxonomies as $taxonomy ) {
				$terms                   = wp_get_post_terms( $post_id, $taxonomy );
				$terms_data[ $taxonomy ] = array(); // always initialise, even when empty.
				if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
					foreach ( $terms as $term ) {
						$term_info = array(
							'term_id'        => $term->term_id,
							'name'           => $term->name,
							'slug'           => $term->slug,
							'parent_term_id' => (int) $term->parent,
							'parent_slug'    => $this->get_term_slug_by_id( (int) $term->parent, $taxonomy ),
							'parent_path'    => $this->get_term_parent_path( (int) $term->parent, $taxonomy ),
						);

						// Get ACF fields for this term.
						if ( function_exists( 'get_field_objects' ) ) {
							$acf_fields = $this->get_term_acf_field_objects( (int) $term->term_id, $taxonomy );
							if ( $acf_fields ) {
								$term_info['acf'] = array();
								foreach ( $acf_fields as $field_key => $field ) {
									$field_name                      = ! empty( $field['name'] ) ? (string) $field['name'] : (string) $field_key;
									$term_info['acf'][ $field_name ] = $this->export_term_acf_sync_value( (int) $term->term_id, $field_name, $field );
								}
							}
						}

						$terms_data[ $taxonomy ][] = $term_info;

						// Extract images from term ACF fields.
						if ( ! empty( $term_info['acf'] ) ) {
							$term_images = $this->extract_term_acf_images( $term_info['acf'] );
							foreach ( $term_images as $image_id ) {
								if ( ! isset( $all_images[ $image_id ] ) ) {
									// Use prepare_image_data to include file_hash for proper dedup on receiving side.
									$image_data = \RockStarLab\ImportExport\Helper\Content_Sync_Media::prepare_image_data( $image_id, 'term_acf' );
									if ( ! $image_data ) {
										// Fallback if file is missing on disk.
										$image_data = array(
											'attachment_id' => $image_id,
											'url'  => wp_get_attachment_url( $image_id ),
											'type' => 'term_acf',
										);
									}
									$image_data['term_id']        = $term->term_id;
									$image_data['taxonomy']       = $taxonomy;
									$all_images[ $image_id ]      = $image_data;
									$image_context[ $image_id ][] = 'term_' . $term->term_id;
								}
							}
						}
					}
				} // end foreach $terms
			} // end if not empty

			// Augment $terms_data with terms referenced inside ACF taxonomy fields.
			// ACF's "save_terms" option defaults to disabled, meaning term IDs are stored
			// only in post_meta and never appear in wp_term_relationships / wp_get_post_terms.
			// Without this augmentation, those term IDs have no name/slug information on
			// the target site and the translator cannot map them.
			if ( function_exists( 'acf_get_field' ) ) {
				foreach ( $prepared_meta as $meta_key => $meta_value ) {
					if ( strpos( $meta_key, '_' ) === 0 ) {
						continue;
					}
					$field_ref_key = '_' . $meta_key;
					if ( ! isset( $prepared_meta[ $field_ref_key ] ) ) {
						continue;
					}
					$field_ref = $prepared_meta[ $field_ref_key ];
					if ( ! is_string( $field_ref ) || strpos( $field_ref, 'field_' ) !== 0 ) {
						continue;
					}
					$field_obj = acf_get_field( $field_ref );
					if ( ! $field_obj || ! isset( $field_obj['type'] ) || 'taxonomy' !== $field_obj['type'] ) {
						continue;
					}
					$acf_taxonomy = isset( $field_obj['taxonomy'] ) ? $field_obj['taxonomy'] : '';
					if ( ! $acf_taxonomy || ! taxonomy_exists( $acf_taxonomy ) ) {
						continue;
					}
					$assign_to_post = ! empty( $field_obj['save_terms'] );
					$raw_ids        = is_array( $meta_value ) ? $meta_value : array( $meta_value );
					if ( ! isset( $terms_data[ $acf_taxonomy ] ) ) {
						$terms_data[ $acf_taxonomy ] = array();
					}
					$known_ids = array_column( $terms_data[ $acf_taxonomy ], 'term_id' );
					foreach ( $raw_ids as $raw_id ) {
						if ( ! is_numeric( $raw_id ) || (int) $raw_id <= 0 ) {
							continue;
						}
						$raw_id = (int) $raw_id;
						if ( in_array( $raw_id, $known_ids, true ) ) {
							if ( ! $assign_to_post ) {
								foreach ( $terms_data[ $acf_taxonomy ] as &$existing_term_info ) {
									if ( isset( $existing_term_info['term_id'] ) && (int) $existing_term_info['term_id'] === $raw_id ) {
										$existing_term_info['assign_to_post'] = false;
									}
								}
								unset( $existing_term_info );
							}
							continue;
						}
						$term = get_term( $raw_id, $acf_taxonomy );
						if ( ! $term || is_wp_error( $term ) ) {
							continue;
						}
						$terms_data[ $acf_taxonomy ][] = array(
							'term_id'        => $term->term_id,
							'name'           => $term->name,
							'slug'           => $term->slug,
							'parent_term_id' => (int) $term->parent,
							'parent_slug'    => $this->get_term_slug_by_id( (int) $term->parent, $acf_taxonomy ),
							'parent_path'    => $this->get_term_parent_path( (int) $term->parent, $acf_taxonomy ),
							'assign_to_post' => $assign_to_post,
						);

						$known_ids[] = $raw_id;
					}
				}
			}

				$post_data = array(
					'ID'                => $post->ID,
					'post_title'        => $post->post_title,
					'post_content'      => $post->post_content,
					'post_excerpt'      => $post->post_excerpt,
					'post_status'       => $post->post_status,
					'post_type'         => $post->post_type,
					'post_parent'       => $post->post_parent,
					'menu_order'        => $post->menu_order,
					'post_name'         => $post->post_name,
					'post_date'         => $post->post_date,
					'post_date_gmt'     => $post->post_date_gmt,
					'post_modified'     => $post->post_modified,
					'post_modified_gmt' => $post->post_modified_gmt,
					'post_author'       => $post->post_author,
					'meta'              => $prepared_meta,
					'post_refs'         => \RockStarLab\ImportExport\Helper\Content_Sync_Replacer::collect_acf_post_reference_map_from_meta( $prepared_meta ),
					'terms'             => $terms_data,
					'comments'          => $this->collect_post_comments_for_sync( $post->ID ),
				);
				$post_data += \RockStarLab\ImportExport\Helper\Sync_Post_Author::payload( $post->post_author ); // Portable author identity: email + login.

				if ( 'product' === $post->post_type ) {
					$helpers    = [
						'get_term_parent_path'       => function ( ...$args ) {
							return $this->get_term_parent_path( ...$args ); },
						'get_term_slug_by_id'        => function ( ...$args ) {
							return $this->get_term_slug_by_id( ...$args ); },
						'portable_synced_meta_value' => function ( ...$args ) {
							return $this->portable_synced_meta_value( ...$args ); },
					];
					$enriched   = apply_filters(
						'rsl_ie_sync_extension',
						[
							'post'    => $post_data,
							'images'  => $all_images,
							'context' => [],
						],
						'service',
						'enrich_post',
						[ $post_data, $post, $all_images ],
						$helpers
					);
					$post_data  = $enriched['post'];
					$all_images = $enriched['images'];
					foreach ( $enriched['context'] as $attachment_id => $contexts ) {
						$image_context[ $attachment_id ] = array_merge( $image_context[ $attachment_id ] ?? [], $contexts ); }
				}

				$posts_data[] = $post_data;
		}

		return [
			'posts'   => $posts_data,
			'images'  => $all_images,
			'context' => $image_context,
		];
	}
}
