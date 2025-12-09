<?php
/**
 * Block Theme Settings Exporter
 *
 * Handles exporting WordPress block theme settings and global styles
 *
 * @package WP_AIE\Model\Export
 */

namespace WP_AIE\Model\Export;

/**
 * Block Theme Settings Exporter Class
 *
 * Exports block theme settings including:
 * - Global styles (theme.json)
 * - Custom templates
 * - Template parts
 * - Site editor settings
 *
 * @package WP_AIE\Model\Export
 */
class Block_Theme_Settings_Exporter extends Abstract_Exporter {

	/**
	 * Get exporter name
	 *
	 * @return string
	 */
	public function get_name() {
		return 'block_theme_settings';
	}

	/**
	 * Get exporter description
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Export WordPress block theme settings and global styles', 'wp-advanced-import-export' );
	}

	/**
	 * Get supported export filters
	 *
	 * @return array
	 */
	public function get_supported_filters() {
		return [
			'include_templates'      => __( 'Include custom templates', 'wp-advanced-import-export' ),
			'include_template_parts' => __( 'Include template parts', 'wp-advanced-import-export' ),
			'include_global_styles'  => __( 'Include global styles', 'wp-advanced-import-export' ),
		];
	}

	/**
	 * Get available fields for export
	 *
	 * @return array
	 */
	public function get_available_fields() {
		return [
			'global_styles',
			'templates',
			'template_parts',
			'theme_mods',
			'custom_css',
		];
	}

	/**
	 * Get default export fields
	 *
	 * @return array
	 */
	public function get_default_fields() {
		return [
			'global_styles',
			'templates',
			'template_parts',
		];
	}

	/**
	 * Get total count of items
	 *
	 * @param array $options Optional. Export filters
	 * @return int
	 */
	public function get_count( $options = [] ) {
		// For settings export, we return 1 as it's a single export operation
		return 1;
	}

	/**
	 * Get data based on export options
	 *
	 * @param array $options Export options
	 * @return array|WP_Error
	 */
	public function get_data( $options = [] ) {
		// Block theme settings is exported as a single item
		// If offset > 0, return empty array (already exported)
		$offset = isset( $options['offset'] ) ? (int) $options['offset'] : 0;
		if ( $offset > 0 ) {
			return [];
		}

		$fields = $options['fields'] ?? $this->get_default_fields();
		$data   = [];

		$this->log_info( 'Exporting block theme settings', $fields );

		foreach ( $fields as $field ) {
			switch ( $field ) {
				case 'global_styles':
					$data['global_styles'] = $this->get_global_styles();
					break;

				case 'templates':
					$data['templates'] = $this->get_templates();
					break;

				case 'template_parts':
					$data['template_parts'] = $this->get_template_parts();
					break;

				case 'theme_mods':
					$data['theme_mods'] = $this->get_theme_mods();
					break;

				case 'custom_css':
					$data['custom_css'] = $this->get_custom_css();
					break;
			}
		}

		return [ $data ];
	}

	/**
	 * Get global styles (theme.json)
	 *
	 * @return array
	 */
	protected function get_global_styles() {
		$global_styles = [];

		// Get custom global styles from database
		$query = new \WP_Query(
			[
				'post_type'      => 'wp_global_styles',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
			]
		);

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$post = get_post();

				$global_styles[] = [
					'title'   => $post->post_title,
					'content' => $post->post_content,
					'status'  => $post->post_status,
					'name'    => $post->post_name,
				];
			}
			wp_reset_postdata();
		}

		return $global_styles;
	}

	/**
	 * Get custom templates
	 *
	 * @return array
	 */
	protected function get_templates() {
		$templates = [];

		$query = new \WP_Query(
			[
				'post_type'      => 'wp_template',
				'posts_per_page' => -1,
				'post_status'    => 'any',
			]
		);

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$post = get_post();

				$templates[] = [
					'id'          => $post->ID,
					'title'       => $post->post_title,
					'content'     => $post->post_content,
					'status'      => $post->post_status,
					'name'        => $post->post_name,
					'description' => $post->post_excerpt,
					'theme'       => get_post_meta( $post->ID, 'theme', true ),
					'type'        => get_post_meta( $post->ID, 'type', true ),
				];
			}
			wp_reset_postdata();
		}

		return $templates;
	}

	/**
	 * Get template parts
	 *
	 * @return array
	 */
	protected function get_template_parts() {
		$template_parts = [];

		$query = new \WP_Query(
			[
				'post_type'      => 'wp_template_part',
				'posts_per_page' => -1,
				'post_status'    => 'any',
			]
		);

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$post = get_post();

				$template_parts[] = [
					'id'          => $post->ID,
					'title'       => $post->post_title,
					'content'     => $post->post_content,
					'status'      => $post->post_status,
					'name'        => $post->post_name,
					'description' => $post->post_excerpt,
					'theme'       => get_post_meta( $post->ID, 'theme', true ),
					'area'        => get_post_meta( $post->ID, 'area', true ),
				];
			}
			wp_reset_postdata();
		}

		return $template_parts;
	}

	/**
	 * Get theme modifications
	 *
	 * @return array
	 */
	protected function get_theme_mods() {
		$theme_slug = get_option( 'stylesheet' );
		$mods       = get_theme_mods();

		return [
			'theme'      => $theme_slug,
			'mods'       => $mods,
			'custom_css' => wp_get_custom_css(),
		];
	}

	/**
	 * Get custom CSS
	 *
	 * @return string
	 */
	protected function get_custom_css() {
		return wp_get_custom_css();
	}

	/**
	 * Build query arguments from options
	 *
	 * @param array $options Export options
	 * @return array
	 */
	protected function build_query_args( $options ) {
		return $options;
	}

	/**
	 * Validate export options
	 *
	 * @param array $options Export options
	 * @return true|\WP_Error
	 */
	public function validate_options( $options ) {
		// Log warning if block theme is not active, but allow export to continue
		// (templates/styles might still exist from Gutenberg plugin)
		if ( ! wp_is_block_theme() ) {
			$this->log_info(
				'Block theme is not active. Some data may be unavailable.',
				[ 'theme' => wp_get_theme()->get( 'Name' ) ]
			);
		}

		return true;
	}
}
