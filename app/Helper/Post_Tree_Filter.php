<?php
/**
 * Hierarchical Post List Filter
 *
 * @package RockStarLab\ImportExport\Helper
 */

namespace RockStarLab\ImportExport\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Adds a Show tree row action to hierarchical post types in wp-admin.
 */
class Post_Tree_Filter {

	/** Query argument containing the root post ID. */
	const QUERY_ARG = 'rsl_ie_show_tree';

	/**
	 * Register hooks when the option is enabled.
	 *
	 * @return void
	 */
	public static function init() {
		$settings = Admin_Menu_Settings::get_settings();
		if ( empty( $settings['show_tree_action'] ) ) {
			return;
		}

		add_filter( 'page_row_actions', [ __CLASS__, 'add_row_action' ], 10, 2 );
		add_filter( 'post_row_actions', [ __CLASS__, 'add_row_action' ], 10, 2 );
		add_action( 'pre_get_posts', [ __CLASS__, 'filter_admin_query' ] );
		add_action( 'admin_notices', [ __CLASS__, 'display_filter_notice' ] );
	}

	/**
	 * Add the Show tree action to hierarchical list-table rows.
	 *
	 * @param array    $actions Existing row actions.
	 * @param \WP_Post $post    Current post.
	 * @return array
	 */
	public static function add_row_action( $actions, $post ) {
		$post_type = get_post_type_object( $post->post_type );
		if ( ! $post_type || empty( $post_type->hierarchical ) ) {
			return $actions;
		}

		$url = add_query_arg(
			[
				'post_type'     => $post->post_type,
				self::QUERY_ARG => $post->ID,
			],
			admin_url( 'edit.php' )
		);
		$url = wp_nonce_url( $url, self::get_nonce_action( $post->ID ) );

		$actions['rsl_ie_show_tree'] = sprintf(
			'<a href="%1$s" aria-label="%2$s">%3$s</a>',
			esc_url( $url ),
			esc_attr(
				sprintf(
					/* translators: %s: post title. */
					__( 'Show %s and all descendants', 'import-export-by-rockstarlab' ),
					get_the_title( $post )
				)
			),
			esc_html__( 'Show tree', 'import-export-by-rockstarlab' )
		);

		return $actions;
	}

	/**
	 * Restrict the current hierarchical post list to a root and its descendants.
	 *
	 * @param \WP_Query $query Current query.
	 * @return void
	 */
	public static function filter_admin_query( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || ! self::is_edit_screen() ) {
			return;
		}

		$root_id = self::get_requested_root_id();
		if ( $root_id <= 0 ) {
			return;
		}

		$post_type = self::get_current_post_type();
		$root      = get_post( $root_id );
		$type      = get_post_type_object( $post_type );

		if ( ! $root || $root->post_type !== $post_type || ! $type || empty( $type->hierarchical ) ) {
			return;
		}

		$query->set( 'post__in', self::get_tree_ids( $root_id, $post_type ) );
	}

	/**
	 * Show the active tree filter and a link back to the complete list.
	 *
	 * @return void
	 */
	public static function display_filter_notice() {
		if ( ! self::is_edit_screen() ) {
			return;
		}

		$root_id = self::get_requested_root_id();
		if ( $root_id <= 0 ) {
			return;
		}

		$post_type = self::get_current_post_type();
		$root      = get_post( $root_id );
		if ( ! $root || $root->post_type !== $post_type ) {
			return;
		}

		$clear_url = add_query_arg( 'post_type', $post_type, admin_url( 'edit.php' ) );
		?>
		<div class="notice notice-info">
			<p>
				<?php
				echo wp_kses_post(
					sprintf(
						/* translators: 1: root post title, 2: URL to clear the filter. */
						__( 'Showing the tree for “%1$s”. <a href="%2$s">Show all</a>', 'import-export-by-rockstarlab' ),
						esc_html( get_the_title( $root ) ),
						esc_url( $clear_url )
					)
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Collect descendants recursively for any hierarchical post type.
	 *
	 * @param int    $root_id   Root post ID.
	 * @param string $post_type Post type.
	 * @return array
	 */
	private static function get_tree_ids( $root_id, $post_type ) {
		$tree_ids = [ $root_id ];
		$parents  = [ $root_id ];
		$statuses = array_values( array_diff( get_post_stati(), [ 'auto-draft', 'inherit' ] ) );

		while ( ! empty( $parents ) ) {
			$children = get_posts(
				[
					'post_type'              => $post_type,
					'post_status'            => $statuses,
					'post_parent__in'        => $parents,
					'posts_per_page'         => -1,
					'fields'                 => 'ids',
					'orderby'                => 'ID',
					'order'                  => 'ASC',
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				]
			);

			$children = array_values( array_diff( array_map( 'absint', $children ), $tree_ids ) );
			if ( empty( $children ) ) {
				break;
			}

			$tree_ids = array_merge( $tree_ids, $children );
			$parents  = $children;
		}

		return $tree_ids;
	}

	/**
	 * Return a verified root ID from the request.
	 *
	 * @return int
	 */
	private static function get_requested_root_id() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read first so empty/tampered requests stop before using other query args.
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( '' === $nonce ) {
			return 0;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce action includes the requested root ID and is verified before returning it.
		$root_id = isset( $_GET[ self::QUERY_ARG ] ) ? absint( wp_unslash( $_GET[ self::QUERY_ARG ] ) ) : 0;
		if ( $root_id <= 0 ) {
			return 0;
		}

		return wp_verify_nonce( $nonce, self::get_nonce_action( $root_id ) ) ? $root_id : 0;
	}

	/**
	 * Return the current list-table post type.
	 *
	 * @return string
	 */
	private static function get_current_post_type() {
		global $typenow;

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && ! empty( $screen->post_type ) ) {
			return sanitize_key( $screen->post_type );
		}

		return ! empty( $typenow ) ? sanitize_key( $typenow ) : 'post';
	}

	/**
	 * Check whether the current admin request is the post list.
	 *
	 * @return bool
	 */
	private static function is_edit_screen() {
		global $pagenow;
		return 'edit.php' === $pagenow;
	}

	/**
	 * Build the nonce action for a root post.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private static function get_nonce_action( $post_id ) {
		return 'rsl_ie_show_tree_' . absint( $post_id );
	}
}
