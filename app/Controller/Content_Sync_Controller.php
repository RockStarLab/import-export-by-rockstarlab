<?php
/**
 * Content Sync Controller
 *
 * Handles AJAX requests for content synchronization between sites
 *
 * @package RockStarLab\ImportExport\Controller
 */

namespace RockStarLab\ImportExport\Controller;

use RockStarLab\ImportExport\Helper\Ajax_Security;
use RockStarLab\ImportExport\Helper\ACF_Fields;
use RockStarLab\ImportExport\Helper\Field_Transformation_Bridge;
use RockStarLab\ImportExport\Helper\Site_URL;
use RockStarLab\ImportExport\Model\Connected_Site;

defined( 'ABSPATH' ) || exit;

/**
 * Content Sync Controller Class
 *
 * Manages connected sites and synchronization operations.
 *
 * @package RockStarLab\ImportExport\Controller
 */
class Content_Sync_Controller extends Base_Controller {


	/**
	 * Return the current admin screen post status without reading query params.
	 *
	 * @return string
	 */
	private function get_current_admin_post_status() {
		global $post_status;

		return is_string( $post_status ) ? sanitize_key( $post_status ) : '';
	}

	/**
	 * Return the current post ID from WordPress admin globals.
	 *
	 * @return int
	 */
	private function get_current_admin_post_id() {
		global $post, $post_ID;

		if ( $post instanceof \WP_Post ) {
			return (int) $post->ID;
		}

		return ! empty( $post_ID ) ? absint( $post_ID ) : 0;
	}

	/**
	 * Return the current admin post type from screen/global context.
	 *
	 * @param string $default Default post type.
	 * @return string
	 */
	private function get_current_admin_post_type( $default = '' ) {
		global $typenow, $post;

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && ! empty( $screen->post_type ) ) {
			return sanitize_key( $screen->post_type );
		}

		if ( ! empty( $typenow ) ) {
			return sanitize_key( $typenow );
		}

		if ( $post instanceof \WP_Post ) {
			return sanitize_key( $post->post_type );
		}

		$post_id = $this->get_current_admin_post_id();
		if ( $post_id ) {
			$maybe_post = get_post( $post_id );
			if ( $maybe_post ) {
				return sanitize_key( $maybe_post->post_type );
			}
		}

		return sanitize_key( $default );
	}

	/**
	 * Get AJAX actions to register
	 *
	 * @return array Array of action => config
	 */
	protected function get_ajax_actions() {
		return array(
			'content_sync_get_sites'            => array( 'callback' => 'get_sites' ),
			'content_sync_add_site'             => array( 'callback' => 'add_site' ),
			'content_sync_update_site'          => array( 'callback' => 'update_site' ),
			'content_sync_delete_site'          => array( 'callback' => 'delete_site' ),
			'content_sync_regenerate_key'       => array( 'callback' => 'regenerate_key' ),
			'content_sync_test_connection'      => array( 'callback' => 'test_connection' ),
			'content_sync_get_my_key'           => array( 'callback' => 'get_my_site_key' ),
			'content_sync_regenerate_my_key'    => array( 'callback' => 'regenerate_my_site_key' ),
			'content_sync_get_remote_posts'     => array( 'callback' => 'get_remote_posts' ),
			'content_sync_search_remote_posts'  => array( 'callback' => 'search_remote_posts' ),
			'content_sync_search_local_posts'   => array( 'callback' => 'search_local_posts' ),
			'content_sync_search_local_terms'   => array( 'callback' => 'search_local_terms' ),
			'content_sync_auto_map_by_title'    => array( 'callback' => 'auto_map_by_title' ),
			'content_sync_get_children_posts'   => array( 'callback' => 'get_children_posts' ),
			'content_sync_get_local_posts_info' => array( 'callback' => 'get_local_posts_info' ),
			'content_sync_push'                 => array( 'callback' => 'push_content' ),
			'content_sync_pull'                 => array( 'callback' => 'pull_content' ),
			'content_sync_get_remote_terms'     => array( 'callback' => 'get_remote_terms' ),
			'content_sync_push_terms'           => array( 'callback' => 'push_terms' ),
			'content_sync_pull_terms'           => array( 'callback' => 'pull_terms' ),
			'content_sync_get_remote_comments'  => array( 'callback' => 'get_remote_comments' ),
			'content_sync_push_comments'        => array( 'callback' => 'push_comments' ),
			'content_sync_pull_comments'        => array( 'callback' => 'pull_comments' ),
		);
	}

	/** AJAX adapter for get_sites. */
	public function get_sites() {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
			return;
		}
		$input  = array();
		$result = ( new \RockStarLab\ImportExport\Service\Connection_Service() )->execute( 'get_sites', $input );
		if ( is_wp_error( $result ) ) {
			$this->send_error( $result->get_error_message() );
			return;
		}
		$this->send_success( $result );
	}

	/** AJAX adapter for add_site. */
	public function add_site() {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
			return;
		}
		$input  = array(
			'name'       => $this->get_request_param( 'name', '' ),
			'remote_url' => $this->get_request_param( 'remote_url', '' ),
			'api_key'    => $this->get_request_param( 'api_key', '' ),
			'direction'  => $this->get_request_param( 'direction', 'bidirectional' ),
		);
		$result = ( new \RockStarLab\ImportExport\Service\Connection_Service() )->execute( 'add_site', $input );
		if ( is_wp_error( $result ) ) {
			$this->send_error( $result->get_error_message() );
			return;
		}
		$this->send_success( $result );
	}

	/** AJAX adapter for update_site. */
	public function update_site() {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
			return;
		}
		$input  = array(
			'site_id'    => $this->get_request_param( 'site_id', 0, 'post' ),
			'name'       => $this->get_request_param( 'name', '', 'post' ),
			'remote_url' => $this->get_request_param( 'remote_url', '', 'post' ),
			'api_key'    => $this->get_request_param( 'api_key', '', 'post' ),
			'direction'  => $this->get_request_param( 'direction', '', 'post' ),
			'status'     => $this->get_request_param( 'status', '', 'post' ),
		);
		$result = ( new \RockStarLab\ImportExport\Service\Connection_Service() )->execute( 'update_site', $input );
		if ( is_wp_error( $result ) ) {
			$this->send_error( $result->get_error_message() );
			return;
		}
		$this->send_success( $result );
	}

	/** AJAX adapter for delete_site. */
	public function delete_site() {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
			return;
		}
		$input  = array(
			'site_id' => $this->get_request_param( 'site_id', 0 ),
		);
		$result = ( new \RockStarLab\ImportExport\Service\Connection_Service() )->execute( 'delete_site', $input );
		if ( is_wp_error( $result ) ) {
			$this->send_error( $result->get_error_message() );
			return;
		}
		$this->send_success( $result );
	}

	/** AJAX adapter for regenerate_key. */
	public function regenerate_key() {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
			return;
		}
		$input  = array(
			'site_id' => $this->get_request_param( 'site_id', 0 ),
		);
		$result = ( new \RockStarLab\ImportExport\Service\Connection_Service() )->execute( 'regenerate_key', $input );
		if ( is_wp_error( $result ) ) {
			$this->send_error( $result->get_error_message() );
			return;
		}
		$this->send_success( $result );
	}

	/** AJAX adapter for test_connection. */
	public function test_connection() {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
			return;
		}
		$input  = array(
			'site_id' => $this->get_request_param( 'site_id', 0 ),
		);
		$result = ( new \RockStarLab\ImportExport\Service\Connection_Service() )->execute( 'test_connection', $input );
		if ( is_wp_error( $result ) ) {
			$this->send_error( $result->get_error_message() );
			return;
		}
		$this->send_success( $result );
	}

	/** AJAX adapter for get_my_site_key. */
	public function get_my_site_key() {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
			return;
		}
		$input  = array();
		$result = ( new \RockStarLab\ImportExport\Service\Connection_Service() )->execute( 'get_my_site_key', $input );
		if ( is_wp_error( $result ) ) {
			$this->send_error( $result->get_error_message() );
			return;
		}
		$this->send_success( $result );
	}

	/** AJAX adapter for regenerate_my_site_key. */
	public function regenerate_my_site_key() {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
			return;
		}
		$input  = array();
		$result = ( new \RockStarLab\ImportExport\Service\Connection_Service() )->execute( 'regenerate_my_site_key', $input );
		if ( is_wp_error( $result ) ) {
			$this->send_error( $result->get_error_message() );
			return;
		}
		$this->send_success( $result );
	}


	/**
	 * Register hooks for post list screens
	 */
	public function register_post_list_hooks() {
		// Load assets for post list screens
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_post_list_assets' ) );

		// Add sync button and modal to post list screens
		add_action( 'restrict_manage_posts', array( $this, 'render_sync_button' ) );
		add_action( 'admin_footer-edit.php', array( $this, 'render_sync_modal' ) );
		// WooCommerce orders in HPOS storage are listed on admin.php?page=wc-orders, which has no
		// restrict_manage_posts: the Export button of the orders screen was missing there.
		add_action( 'woocommerce_order_list_table_restrict_manage_orders', array( $this, 'render_order_list_button' ), 10, 2 );

		// Add sync button to post edit screen
		add_action( 'post_submitbox_misc_actions', array( $this, 'render_post_edit_sync_button' ) );
		add_action( 'admin_footer-post.php', array( $this, 'render_gutenberg_sync_button' ) );
		add_action( 'admin_footer-post-new.php', array( $this, 'render_gutenberg_sync_button' ) );
		add_action( 'admin_footer-post.php', array( $this, 'render_sync_modal' ) );
		add_action( 'admin_footer-post-new.php', array( $this, 'render_sync_modal' ) );
		// Some admin contexts (notably the block editor) can behave differently with
		// hook-suffixed footer actions. Also hook into generic admin_footer and guard
		// against duplicate rendering inside render_sync_modal().
		add_action( 'admin_footer', array( $this, 'render_sync_modal' ) );
	}

	/**
	 * Enqueue assets for post list screens
	 */
	public function enqueue_post_list_assets( $hook_suffix ) {
		// The HPOS orders list behaves as the shop_order post list.
		$hpos_orders = 'woocommerce_page_wc-orders' === $hook_suffix && ! $this->is_order_edit_screen();
		if ( $hpos_orders ) {
			$hook_suffix = 'edit.php';
		}

		// Load on edit.php (post list) and post.php/post-new.php (edit post)
		if ( ! in_array( $hook_suffix, array( 'edit.php', 'post.php', 'post-new.php' ) ) ) {
			return;
		}

		// Don't load on trash page
		if ( 'edit.php' === $hook_suffix ) {
			$post_status = $this->get_current_admin_post_status();
			if ( 'trash' === $post_status ) {
				return;
			}
		}

		// Get current post type
		global $post;
		$current_post_type = '';

		if ( $hpos_orders ) {
			$current_post_type = 'shop_order';
		} elseif ( 'edit.php' === $hook_suffix ) {
			$current_post_type = $this->get_current_admin_post_type();
		} elseif ( in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			// On post.php, $post may not yet be populated during admin_enqueue_scripts.
			if ( $post ) {
				$current_post_type = $post->post_type;
			} else {
				$post_id = $this->get_current_admin_post_id();
				if ( $post_id ) {
					$maybe_post = get_post( (int) $post_id );
					if ( $maybe_post ) {
						$current_post_type = $maybe_post->post_type;
					}
				}
				if ( empty( $current_post_type ) ) {
					$current_post_type = $this->get_current_admin_post_type( 'post' );
				}
			}
		}

		if ( empty( $current_post_type ) || ! $this->should_show_post_type_quick_actions( $current_post_type ) ) {
			return;
		}

		// Enqueue post sync script
		// Note: on Gutenberg post editor screens, admin footer scripts are not always printed
		// the same way as on list screens. Load in header for post.php/post-new.php so the
		// sync UI is reliably available.
		$in_footer = ( 'edit.php' === $hook_suffix );
		wp_enqueue_script(
			'rsl-ie-post-sync',
			plugins_url( 'assets/js/post-sync-standalone.js', RSL_IE_FILE ),
			array( 'jquery' ),
			filemtime( plugin_dir_path( RSL_IE_FILE ) . 'assets/js/post-sync-standalone.js' ),
			$in_footer
		);

		// Localize script
		// Get connected sites for Select2 AJAX
		$sites     = Connected_Site::get_all();
		$sites_map = array();
		foreach ( $sites as $site ) {
			$sites_map[ $site['id'] ] = array(
				'id'         => $site['id'],
				'name'       => $site['name'],
				// The other site's key stays on the server: remote calls go through admin-ajax.
				'remote_url' => $site['remote_url'],
			);
		}

		wp_localize_script(
			'rsl-ie-post-sync',
			'rslIePostSyncData',
			array(
				'nonces'                      => Ajax_Security::get_nonces(),
				'ajaxurl'                     => Ajax_Security::ajax_url(),
				'ajaxUrl'                     => Ajax_Security::ajax_url(),
				'adminUrl'                    => Site_URL::admin_url(),
				'functionsUrl'                => Field_Transformation_Bridge::get_management_url(),
				'fieldTransformationsEnabled' => Field_Transformation_Bridge::is_enabled(),
				'exportUrl'                   => Site_URL::admin_url( 'admin.php?page=rsl-ie-export' ),
				'contentSyncUrl'              => Site_URL::admin_url( 'admin.php?page=rsl-ie-content-sync' ),
				'connectedSites'              => $sites_map,
				'i18n'                        => array(
					// Alerts & Messages
					'pleaseSavePost'          => __( 'Please save the post first', 'import-export-by-rockstarlab' ),
					'pleaseSelectSite'        => __( 'Please select a site', 'import-export-by-rockstarlab' ),
					'noPostsSelected'         => __( 'No posts selected', 'import-export-by-rockstarlab' ),
					'pullOverUnsaved'         => __( 'This post has unsaved changes. Pulling replaces it with the version from the other site and the changes are lost. Continue?', 'import-export-by-rockstarlab' ),
					'saveBeforePushFailed'    => __( 'The post could not be saved, so it was not pushed. Save it and try again.', 'import-export-by-rockstarlab' ),
					'export'                  => __( 'Export', 'import-export-by-rockstarlab' ),
					/* translators: %s: number of selected items. */
					'exportCount'             => __( 'Export (%s)', 'import-export-by-rockstarlab' ),
					'failedLoadRemotePosts'   => __( 'Failed to load remote posts', 'import-export-by-rockstarlab' ),
					'unknownError'            => __( 'Unknown error', 'import-export-by-rockstarlab' ),
					'failedConnectRemote'     => __( 'Failed to connect to remote site', 'import-export-by-rockstarlab' ),
					'failedLoadLocalPosts'    => __( 'Failed to load local posts info', 'import-export-by-rockstarlab' ),
					'pleaseSelectOnePost'     => __( 'Please select at least one post', 'import-export-by-rockstarlab' ),

					// translators: %s = content placeholder.
					// Count Text
					'onePost'                 => __( '1 post', 'import-export-by-rockstarlab' ),
					// translators: %s is a dynamic value.
					'postsCount'              => __( '%s posts', 'import-export-by-rockstarlab' ),

					// Post Info
					// translators: %s is a dynamic value.
					'postHash'                => __( 'Post #%s', 'import-export-by-rockstarlab' ),
					// translators: %s = content placeholder.
					'idLabel'                 => __( 'ID:', 'import-export-by-rockstarlab' ),
					'noTitle'                 => __( '(No title)', 'import-export-by-rockstarlab' ),

					// translators: %s = content placeholder.
					// Actions
					'createNewPost'           => __( '➕ Create New Post', 'import-export-by-rockstarlab' ),
					// translators: 1: post title or name, 2: post ID.
					'updatePost'              => __( '🔄 Update: %1$s (ID: %2$s)', 'import-export-by-rockstarlab' ),
					// translators: %s is a dynamic value.
					'searchForUpdate'         => __( 'Search for a %s to update...', 'import-export-by-rockstarlab' ),

					// Progress
					// translators: %s is a dynamic value.
					'starting'                => __( 'Starting %s...', 'import-export-by-rockstarlab' ),
					'completed'               => __( 'Completed!', 'import-export-by-rockstarlab' ),
					'syncCompletedSuccess'    => __( 'Sync completed successfully', 'import-export-by-rockstarlab' ),
					'pullingPosts'            => __( 'Pulling posts...', 'import-export-by-rockstarlab' ),
					'syncFailed'              => __( 'Sync failed', 'import-export-by-rockstarlab' ),
					'errorDuringSync'         => __( 'An error occurred during sync', 'import-export-by-rockstarlab' ),
					'matchingByTitle'         => __( 'Matching...', 'import-export-by-rockstarlab' ),
					'selectRemotePostForPull' => __( 'Please select at least one remote post to pull.', 'import-export-by-rockstarlab' ),

					// Browse
					'noPostsFound'            => __( 'No posts found', 'import-export-by-rockstarlab' ),
					'child'                   => __( 'child', 'import-export-by-rockstarlab' ),
					'children'                => __( 'children', 'import-export-by-rockstarlab' ),
					'pluginDataNotLoaded'     => __( 'Plugin data not loaded. Please refresh the page.', 'import-export-by-rockstarlab' ),
					'errorLoadingPosts'       => __( 'An error occurred while loading posts', 'import-export-by-rockstarlab' ),
					'failedLoadChildren'      => __( 'Failed to load children', 'import-export-by-rockstarlab' ),
					'errorLoadingChildren'    => __( 'Error loading children', 'import-export-by-rockstarlab' ),
				),
			)
		);

		// Enqueue styles (reuse the main plugin styles)
		wp_enqueue_style(
			'rsl-ie-post-sync-styles',
			plugins_url( 'assets/css/app.css', RSL_IE_FILE ),
			array(),
			filemtime( plugin_dir_path( RSL_IE_FILE ) . 'assets/css/app.css' )
		);

		// WordPress 7+ admin UI tweaks.
		global $wp_version;
		if ( isset( $wp_version ) && version_compare( $wp_version, '7.0', '>=' ) ) {
			wp_enqueue_style(
				'rsl-ie-post-sync-admin-wp7',
				plugins_url( 'assets/css/admin-wp7.css', RSL_IE_FILE ),
				array( 'rsl-ie-post-sync-styles' ),
				filemtime( plugin_dir_path( RSL_IE_FILE ) . 'assets/css/admin-wp7.css' )
			);
		}

		// Enqueue Gutenberg sync script for post edit screens
		if ( in_array( $hook_suffix, array( 'post.php', 'post-new.php' ) ) ) {

			// Only show for posts being edited
			if ( $post ) {
				// Enqueue the Gutenberg sync script
				wp_enqueue_script(
					'rsl-ie-gutenberg-sync',
					plugins_url( 'assets/js/gutenberg-sync.js', RSL_IE_FILE ),
					array( 'jquery', 'wp-editor', 'wp-data', 'wp-element', 'wp-components' ),
					filemtime( plugin_dir_path( RSL_IE_FILE ) . 'assets/js/gutenberg-sync.js' ),
					false
				);

				// Localize script with necessary data
				wp_localize_script(
					'rsl-ie-gutenberg-sync',
					'rslIeData',
					array(
						'nonces'                      => Ajax_Security::get_nonces(),
						'ajaxurl'                     => Ajax_Security::ajax_url(),
						'ajaxUrl'                     => Ajax_Security::ajax_url(),
						'adminUrl'                    => Site_URL::admin_url(),
						'functionsUrl'                => Field_Transformation_Bridge::get_management_url(),
						'fieldTransformationsEnabled' => Field_Transformation_Bridge::is_enabled(),
						'exportUrl'                   => Site_URL::admin_url( 'admin.php?page=rsl-ie-export' ),
						'i18n'                        => array(
							'syncContent'  => __( 'Sync Content', 'import-export-by-rockstarlab' ),
							'syncThisPost' => __( 'Sync This Post', 'import-export-by-rockstarlab' ),
						),
					)
				);
			}
		}
	}

		/**
		 * Render sync button on post list screen
		 */
	public function render_sync_button() {
		global $typenow;

		// Only show on post list screens
		if ( empty( $typenow ) ) {
			return;
		}

		// Don't show on trash page
		$post_status = $this->get_current_admin_post_status();
		if ( 'trash' === $post_status ) {
			return;
		}

		if ( ! $this->should_show_post_type_quick_actions( $typenow ) ) {
			return;
		}

		require RSL_IE_PATH . '/app/View/sync/sync-button.php';
	}

	/**
	 * Export button above the HPOS orders list (orders are synced by their own workflow).
	 *
	 * @param string $order_type Order type of the list.
	 * @param string $which      "top" or "bottom".
	 * @return void
	 */
	public function render_order_list_button( $order_type = 'shop_order', $which = 'top' ) {
		if ( 'top' !== $which || 'shop_order' !== $order_type ) {
			return;
		}
		if ( ! \RockStarLab\ImportExport\Helper\Button_Location_Settings::is_export_enabled( 'post_type:shop_order' ) ) {
			return;
		}
		?>
		<button type="button" id="rsl-ie-export-selected-btn" class="button action" style="margin-left: 5px;" disabled aria-disabled="true" data-post-type="shop_order">
			<?php esc_html_e( 'Export', 'import-export-by-rockstarlab' ); ?>
		</button>
		<?php
	}

	/**
	 * Whether the current wc-orders request edits or creates one order (not the list).
	 *
	 * @return bool
	 */
	private function is_order_edit_screen() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen routing.
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		return in_array( $action, array( 'edit', 'new' ), true );
	}

		/**
		 * Render sync modal
		 */
	public function render_sync_modal() {
		static $rendered = false;
		if ( $rendered ) {
			return;
		}

		$current_post_type = $this->get_current_admin_post_type();

		if ( empty( $current_post_type ) ) {
			return;
		}

		// Don't show on trash page
		$post_status = $this->get_current_admin_post_status();
		if ( 'trash' === $post_status ) {
			return;
		}

		if ( ! \RockStarLab\ImportExport\Helper\Button_Location_Settings::is_sync_enabled( 'post_type:' . $current_post_type ) ) {
			return;
		}

		// Get connected sites
		$sites = Connected_Site::get_all();

		$rendered = true;

		// Load view templates
		require RSL_IE_PATH . '/app/View/sync/sync-modal.php';
		require RSL_IE_PATH . '/app/View/sync/mapping-modal.php';
		require RSL_IE_PATH . '/app/View/sync/browse-modal.php';
	}

	/**
	 * Render sync button in post edit screen (Classic Editor)
	 */
	public function render_post_edit_sync_button() {
		global $post;

		// Only show for published posts or posts being edited
		if ( ! $post || ( 'auto-draft' === $post->post_status ) ) {
			return;
		}

		if ( ! \RockStarLab\ImportExport\Helper\Button_Location_Settings::is_sync_enabled( 'post_type:' . $post->post_type ) ) {
			return;
		}

		require RSL_IE_PATH . '/app/View/sync/post-edit-button.php';
	}

	/**
	 * Check whether Export or Sync quick actions are enabled for a post type.
	 *
	 * @param string $post_type Post type slug.
	 * @return bool
	 */
	private function should_show_post_type_quick_actions( $post_type ) {
		$post_type = sanitize_key( $post_type );
		if ( '' === $post_type ) {
			return false;
		}

		$location_id = 'post_type:' . $post_type;

		return \RockStarLab\ImportExport\Helper\Button_Location_Settings::is_export_enabled( $location_id )
			|| \RockStarLab\ImportExport\Helper\Button_Location_Settings::is_sync_enabled( $location_id );
	}

	/**
	 * Render Gutenberg sync button
	 *
	 * @deprecated Moved to enqueue_post_list_assets
	 */
	public function render_gutenberg_sync_button() {
		// This method is kept for backwards compatibility but the functionality
		// has been moved to enqueue_post_list_assets() for proper script enqueueing
	}

	/** AJAX adapter for get_remote_posts. */
	public function get_remote_posts() {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
			return;
		}
		$input  = array(
			'site_id'          => $this->get_request_param( 'site_id', 0 ),
			'post_type'        => $this->get_request_param( 'post_type', 'any' ),
			'search'           => $this->get_request_param( 'search', '' ),
			'status'           => $this->get_request_param( 'status', '' ),
			'page'             => $this->get_request_param( 'page', 1 ),
			'per_page'         => $this->get_request_param( 'per_page', 20 ),
			'commentable_only' => $this->get_request_param( 'commentable_only', false ),
			'comment_type'     => $this->get_request_param( 'comment_type', '' ),
			'language'         => $this->get_request_param( 'language', '' ),
			'include_children' => $this->get_request_param( 'include_children', false ),
		);
		$result = ( new \RockStarLab\ImportExport\Service\Content_Sync_Service() )->execute( 'get_remote_posts', $input );
		if ( is_wp_error( $result ) ) {
			// The code lets the post search retry while another request still uses the connection.
			$this->send_error( $result->get_error_message(), array( 'code' => $result->get_error_code() ) );
			return;
		}
		$this->send_success( $result );
	}

	/** AJAX alias for remote browsing. */
	public function search_remote_posts() {
		$this->get_remote_posts();
	}

	/** AJAX adapter for search_local_posts. */
	public function search_local_posts() {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
			return;
		}
		$input  = array(
			'post_type'        => $this->get_request_param( 'post_type', 'any' ),
			'commentable_only' => $this->get_request_param( 'commentable_only', false ),
			'comment_type'     => $this->get_request_param( 'comment_type', '' ),
			'search'           => $this->get_request_param( 'search', '' ),
			'page'             => $this->get_request_param( 'page', 1 ),
			'per_page'         => $this->get_request_param( 'per_page', 20 ),
		);
		$result = ( new \RockStarLab\ImportExport\Service\Content_Sync_Service() )->execute( 'search_local_posts', $input );
		if ( is_wp_error( $result ) ) {
			$this->send_error( $result->get_error_message() );
			return;
		}
		$this->send_success( $result );
	}

	/** AJAX adapter for search_local_terms. */
	public function search_local_terms() {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
			return;
		}
		$input  = array(
			'taxonomy' => $this->get_request_param( 'taxonomy', '' ),
			'search'   => $this->get_request_param( 'search', '' ),
			'page'     => $this->get_request_param( 'page', 1 ),
			'per_page' => $this->get_request_param( 'per_page', 20 ),
		);
		$result = ( new \RockStarLab\ImportExport\Service\Content_Sync_Service() )->execute( 'search_local_terms', $input );
		if ( is_wp_error( $result ) ) {
			$this->send_error( $result->get_error_message() );
			return;
		}
		$this->send_success( $result );
	}





	/** AJAX adapter for get_remote_terms. */
	public function get_remote_terms() {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
			return;
		}
		$input  = array(
			'site_id'  => $this->get_request_param( 'site_id', 0 ),
			'taxonomy' => $this->get_request_param( 'taxonomy', '' ),
			'search'   => $this->get_request_param( 'search', '' ),
			'page'     => $this->get_request_param( 'page', 1 ),
			'per_page' => $this->get_request_param( 'per_page', 20 ),
			'language' => $this->get_request_param( 'language', '' ),
		);
		$result = ( new \RockStarLab\ImportExport\Service\Content_Sync_Service() )->execute( 'get_remote_terms', $input );
		if ( is_wp_error( $result ) ) {
			$this->send_error( $result->get_error_message() );
			return;
		}
		$this->send_success( $result );
	}

	/** AJAX adapter for push_terms. */
	public function push_terms() {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
			return;
		}
		$input  = array(
			'site_id'  => $this->get_request_param( 'site_id', 0 ),
			'taxonomy' => $this->get_request_param( 'taxonomy', '' ),
			'term_ids' => $this->get_request_array( 'term_ids', array() ),
		);
		$result = ( new \RockStarLab\ImportExport\Service\Content_Sync_Service() )->execute( 'push_terms', $input );
		if ( is_wp_error( $result ) ) {
			$this->send_error( $result->get_error_message() );
			return;
		}
		$this->send_success( $result );
	}

	/** AJAX adapter for pull_terms. */
	public function pull_terms() {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
			return;
		}
		$input  = array(
			'site_id'      => $this->get_request_param( 'site_id', 0 ),
			'taxonomy'     => $this->get_request_param( 'taxonomy', '' ),
			'term_ids'     => $this->get_request_array( 'term_ids', array() ),
			'term_mapping' => $this->get_request_array( 'term_mapping', array() ),
		);
		$result = ( new \RockStarLab\ImportExport\Service\Content_Sync_Service() )->execute( 'pull_terms', $input );
		if ( is_wp_error( $result ) ) {
			$this->send_error( $result->get_error_message() );
			return;
		}
		$this->send_success( $result );
	}

	/** AJAX adapter for get_remote_comments. */
	public function get_remote_comments() {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
			return;
		}
		$input  = array(
			'site_id'      => $this->get_request_param( 'site_id', 0 ),
			'comment_type' => $this->get_request_param( 'comment_type', '' ),
			'search'       => $this->get_request_param( 'search', '' ),
			'post_id'      => $this->get_request_param( 'post_id', 0 ),
			'page'         => $this->get_request_param( 'page', 1 ),
			'per_page'     => $this->get_request_param( 'per_page', 20 ),
			'language'     => $this->get_request_param( 'language', '' ),
		);
		$result = ( new \RockStarLab\ImportExport\Service\Content_Sync_Service() )->execute( 'get_remote_comments', $input );
		if ( is_wp_error( $result ) ) {
			$this->send_error( $result->get_error_message() );
			return;
		}
		$this->send_success( $result );
	}

	/**
	 * Post type of a comment sync from the Comments / Reviews screens. Those screens send no
	 * post_type, and the sync policy refused every such push or pull ("The selected sync
	 * domain, records or destinations are invalid or unavailable").
	 *
	 * @param array $comment_ids Local comments being pushed.
	 * @param int   $post_id     Local post that receives pulled comments.
	 * @return string
	 */
	private function comment_sync_post_type( $comment_ids, $post_id ) {
		$requested = sanitize_key( (string) $this->get_request_param( 'post_type', '' ) );
		if ( '' !== $requested ) {
			return $requested;
		}
		if ( $post_id > 0 ) {
			return (string) get_post_type( $post_id );
		}
		foreach ( (array) $comment_ids as $comment_id ) {
			$comment = get_comment( absint( $comment_id ) );
			if ( $comment ) {
				return (string) get_post_type( (int) $comment->comment_post_ID );
			}
		}
		return '';
	}

	/** AJAX adapter for push_comments. */
	public function push_comments() {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
			return;
		}
		$input  = array(
			'site_id'        => $this->get_request_param( 'site_id', 0 ),
			'comment_ids'    => $this->get_request_array( 'comment_ids', array() ),
			'target_post_id' => $this->get_request_param( 'target_post_id', 0 ),
			'comment_type'   => $this->get_request_param( 'comment_type', '' ),
			'migration'      => $this->get_request_param( 'migration', false ),
		);
		$input['post_type'] = $this->comment_sync_post_type( $input['comment_ids'], 0 );
		$result             = ( new \RockStarLab\ImportExport\Service\Content_Sync_Service() )->execute( 'push_comments', $input );
		if ( is_wp_error( $result ) ) {
			$this->send_error( $result->get_error_message() );
			return;
		}
		$this->send_success( $result );
	}

	/** AJAX adapter for pull_comments. */
	public function pull_comments() {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
			return;
		}
		$input  = array(
			'site_id'        => $this->get_request_param( 'site_id', 0 ),
			'comment_ids'    => $this->get_request_array( 'comment_ids', array() ),
			'target_post_id' => $this->get_request_param( 'target_post_id', 0 ),
			'post_mapping'   => $this->get_request_param( 'post_mapping', array() ),
			'comment_type'   => $this->get_request_param( 'comment_type', '' ),
			'language'       => $this->get_request_param( 'language', '' ),
		);
		$pull_target = absint( $input['target_post_id'] );
		$pull_map    = is_string( $input['post_mapping'] ) ? json_decode( wp_unslash( $input['post_mapping'] ), true ) : $input['post_mapping'];
		if ( ! $pull_target && is_array( $pull_map ) && $pull_map ) {
			$pull_target = absint( reset( $pull_map ) );
		}
		$input['post_type'] = $this->comment_sync_post_type( array(), $pull_target );
		$result             = ( new \RockStarLab\ImportExport\Service\Content_Sync_Service() )->execute( 'pull_comments', $input );
		if ( is_wp_error( $result ) ) {
			$this->send_error( $result->get_error_message() );
			return;
		}
		$this->send_success( $result );
	}






	/** AJAX adapter for auto_map_by_title. */
	public function auto_map_by_title() {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
			return;
		}
		$input  = array(
			'site_id'  => $this->get_request_param( 'site_id', 0 ),
			'post_ids' => $this->get_request_array( 'post_ids', array() ),
		);
		$result = ( new \RockStarLab\ImportExport\Service\Content_Sync_Service() )->execute( 'auto_map_by_title', $input );
		if ( is_wp_error( $result ) ) {
			$this->send_error( $result->get_error_message() );
			return;
		}
		$this->send_success( $result );
	}



	/** AJAX adapter for get_children_posts. */
	public function get_children_posts() {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
			return;
		}
		$input  = array(
			'site_id'   => $this->get_request_param( 'site_id', 0 ),
			'parent_id' => $this->get_request_param( 'parent_id', 0 ),
			'post_type' => $this->get_request_param( 'post_type', '' ),
			'language'  => $this->get_request_param( 'language', '' ),
		);
		$result = ( new \RockStarLab\ImportExport\Service\Content_Sync_Service() )->execute( 'get_children_posts', $input );
		if ( is_wp_error( $result ) ) {
			$this->send_error( $result->get_error_message() );
			return;
		}
		$this->send_success( $result );
	}

	/** AJAX adapter for get_local_posts_info. */
	public function get_local_posts_info() {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
			return;
		}
		$input  = array(
			'post_ids' => $this->get_request_array( 'post_ids', array() ),
		);
		$result = ( new \RockStarLab\ImportExport\Service\Content_Sync_Service() )->execute( 'get_local_posts_info', $input );
		if ( is_wp_error( $result ) ) {
			$this->send_error( $result->get_error_message() );
			return;
		}
		$this->send_success( $result );
	}

	/** AJAX adapter for push_content. */
	public function push_content() {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
			return;
		}
		$input  = array(
			'site_id'      => $this->get_request_param( 'site_id', 0 ),
			'post_ids'     => $this->get_request_array( 'post_ids', array() ),
			'post_mapping' => $this->get_request_param( 'post_mapping', array() ),
		);
		$result = ( new \RockStarLab\ImportExport\Service\Content_Sync_Service() )->execute( 'push_content', $input );
		if ( is_wp_error( $result ) ) {
			$this->send_error( $result->get_error_message() );
			return;
		}
		$this->send_success( $result );
	}












	/** AJAX adapter for pull_content. */
	public function pull_content() {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
			return;
		}
		$input  = array(
			'site_id'      => $this->get_request_param( 'site_id', 0 ),
			'post_ids'     => $this->get_request_array( 'post_ids', array() ),
			'post_mapping' => $this->get_request_param( 'post_mapping', array() ),
		);
		$result = ( new \RockStarLab\ImportExport\Service\Content_Sync_Service() )->execute( 'pull_content', $input );
		if ( is_wp_error( $result ) ) {
			$this->send_error( $result->get_error_message() );
			return;
		}
		$this->send_success( $result );
	}
}
