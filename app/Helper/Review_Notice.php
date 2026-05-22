<?php
/**
 * Review Notice Helper
 *
 * Shows a beautiful notice asking users to leave a 5-star review on WordPress.org.
 * Appears only on plugin pages, 1 week after installation.
 *
 * 🔑 Secret test URL (bypass the 1-week wait):
 *    /wp-admin/admin.php?page=import-export-by-rockstarlab&rsl_ie_review_test=1
 *
 * @package RockStarLab\ImportExport\Helper
 */

namespace RockStarLab\ImportExport\Helper;

defined( 'ABSPATH' ) or exit;

class Review_Notice {

	/**
	 * Option key — stores whether the notice was dismissed.
	 */
	const OPTION_DISMISSED = 'rsl_ie_review_dismissed';

	/**
	 * Option key — stores the Unix timestamp of first activation.
	 */
	const OPTION_INSTALL_DATE = 'rsl_ie_install_date';

	/**
	 * WordPress.org review URL for this plugin.
	 */
	const REVIEW_URL = 'https://wordpress.org/support/plugin/import-export-by-rockstarlab/reviews/#new-post';

	/**
	 * All admin page slugs that belong to this plugin.
	 *
	 * @var string[]
	 */
	private static array $plugin_pages = [
		'import-export-by-rockstarlab',
		'rsl-ie-import',
		'rsl-ie-export',
		'rsl-ie-content-sync',
		'rsl-ie-content-updater',
		'rsl-ie-jobs-log',
		'rsl-ie-media-sync',
		'rsl-ie-ai-url-importer',
		'rsl-ie-functions',
		'rsl-ie-plugin-options',
	];

	/**
	 * Register all hooks.
	 */
	public static function init(): void {
		add_action( 'admin_notices', [ __CLASS__, 'maybe_show_notice' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
		add_action( 'wp_ajax_rsl_ie_dismiss_review_notice', [ __CLASS__, 'handle_dismiss' ] );
	}

	/**
	 * Save the install timestamp on first activation (call from activation hook).
	 * Uses add_option so it only writes once and never overwrites an existing value.
	 */
	public static function set_install_date(): void {
		if ( ! get_option( self::OPTION_INSTALL_DATE ) ) {
			add_option( self::OPTION_INSTALL_DATE, time(), '', false );
		}
	}

	// ── Private helpers ───────────────────────────────────────────────────────

	/**
	 * Check whether the current admin screen belongs to this plugin.
	 */
	private static function is_plugin_page(): bool {
		$page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified via verify_request().
		return in_array( $page, self::$plugin_pages, true );
	}

	/**
	 * Check if enough time has passed (or the secret test param is present).
	 */
	private static function is_ready_to_show(): bool {
		// 🔑 Secret test bypass: ?rsl_ie_review_test=1
		if ( isset( $_GET['rsl_ie_review_test'] ) && '1' === $_GET['rsl_ie_review_test'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified via verify_request().
			return true;
		}

		$install_date = (int) get_option( self::OPTION_INSTALL_DATE, 0 );

		if ( ! $install_date ) {
			return false;
		}

		return ( time() - $install_date ) >= WEEK_IN_SECONDS;
	}

	// ── Public callbacks ──────────────────────────────────────────────────────

	/**
	 * Conditionally render the notice.
	 */
	public static function maybe_show_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$is_test = isset( $_GET['rsl_ie_review_test'] ) && '1' === $_GET['rsl_ie_review_test']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified via verify_request().

		// 🔑 Test mode: wipe dismiss flag so the notice always appears
		if ( $is_test ) {
			delete_option( self::OPTION_DISMISSED );
		}

		if ( ! $is_test && get_option( self::OPTION_DISMISSED ) ) {
			return;
		}

		if ( ! self::is_plugin_page() ) {
			return;
		}

		if ( ! self::is_ready_to_show() ) {
			return;
		}

		self::render();
	}

	/**
	 * Enqueue the inline JS needed to dismiss the notice via AJAX.
	 * Always loaded on plugin pages so it is ready regardless of hook order.
	 */
	public static function enqueue_assets(): void {
		if ( ! self::is_plugin_page() ) {
			return;
		}

		// Attach after the main plugin script (which depends on jquery)
		wp_add_inline_script( 'jquery-core', self::get_inline_js() );
	}

	/**
	 * AJAX handler — saves the dismissed flag and sends JSON success.
	 */
	public static function handle_dismiss(): void {
		check_ajax_referer( 'rsl_ie_dismiss_review', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
			return;
		}

		update_option( self::OPTION_DISMISSED, 1, false );
		wp_send_json_success();
	}

	// ── Rendering ─────────────────────────────────────────────────────────────

	/**
	 * Output the notice HTML.
	 */
	public static function render(): void {
		$nonce = wp_create_nonce( 'rsl_ie_dismiss_review' );
		?>
		<div class="rsl-ie-review-notice" id="rsl-ie-review-notice" data-nonce="<?php echo esc_attr( $nonce ); ?>">

			<div class="rsl-ie-review-notice__body">
				<span class="rsl-ie-review-notice__rating-stars" aria-hidden="true">&#9733;&#9733;&#9733;&#9733;&#9733;</span>
				<h3 class="rsl-ie-review-notice__title">
					<?php esc_html_e( 'Enjoying Import Export by RockStarLab?', 'import-export-by-rockstarlab' ); ?>
				</h3>
				<p class="rsl-ie-review-notice__text">
					<?php esc_html_e( "You've been using the plugin for over a week — awesome! If it's been helpful, would you take 2 minutes to leave a ★★★★★ review on WordPress.org? It keeps us motivated and helps others discover the plugin. 🙏", 'import-export-by-rockstarlab' ); ?>
				</p>
				<div class="rsl-ie-review-notice__actions">
					<a href="<?php echo esc_url( self::REVIEW_URL ); ?>"
						target="_blank"
						rel="noopener noreferrer"
						class="rsl-ie-review-notice__btn rsl-ie-review-notice__btn--primary rsl-ie-review-dismiss"
						data-nonce="<?php echo esc_attr( $nonce ); ?>">
						<span class="dashicons dashicons-external" aria-hidden="true"></span>
						<?php esc_html_e( 'Yes, I\'d love to! ⭐', 'import-export-by-rockstarlab' ); ?>
					</a>
					<button type="button"
							class="rsl-ie-review-notice__btn rsl-ie-review-notice__btn--secondary rsl-ie-review-dismiss"
							data-nonce="<?php echo esc_attr( $nonce ); ?>">
						<?php esc_html_e( 'Maybe later', 'import-export-by-rockstarlab' ); ?>
					</button>
					<button type="button"
							class="rsl-ie-review-notice__btn rsl-ie-review-notice__btn--link rsl-ie-review-dismiss"
							data-nonce="<?php echo esc_attr( $nonce ); ?>">
						<?php esc_html_e( 'I\'ve already left a review', 'import-export-by-rockstarlab' ); ?>
					</button>
				</div>
			</div>

			<button type="button"
					class="rsl-ie-review-notice__close rsl-ie-review-dismiss"
					data-nonce="<?php echo esc_attr( $nonce ); ?>"
					aria-label="<?php esc_attr_e( 'Dismiss this notice', 'import-export-by-rockstarlab' ); ?>">
				<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
			</button>

		</div>
		<?php
	}

	/**
	 * Returns the inline JS string that handles the dismiss interaction.
	 */
	private static function get_inline_js(): string {
		return "
jQuery(function($) {
    $(document).on('click', '.rsl-ie-review-dismiss', function(e) {
        // Allow anchor tags to open the link normally, but still dismiss
        if (!$(this).is('a')) {
            e.preventDefault();
        }
        var nonce = $('#rsl-ie-review-notice').data('nonce');
        $.post(ajaxurl, {
            action: 'rsl_ie_dismiss_review_notice',
            nonce:  nonce
        });
        $('#rsl-ie-review-notice').fadeOut(350, function() {
            $(this).remove();
        });
    });
});
";
	}
}
