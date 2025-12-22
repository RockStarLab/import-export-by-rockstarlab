<?php
/**
 * AI URL Importer Controller
 *
 * Handles AI-powered content extraction from URLs
 * Premium feature requiring OpenAI API key
 *
 * @package WP_AIE\Controller
 */

namespace WP_AIE\Controller;

use WP_AIE\Helper\AI_Content_Extractor;
use WP_AIE\Model\Job;

/**
 * AI URL Importer Controller Class
 *
 * Manages AI URL import workflow:
 * 1. URL input (textarea or CSV upload)
 * 2. Post type selection
 * 3. Content field mapping (post_content or ACF)
 * 4. Test connection and preview
 * 5. Batch import with rate limiting
 *
 * @package WP_AIE\Controller
 */
class AI_URL_Importer_Controller extends Base_Controller {

	/**
	 * AI Content Extractor instance
	 *
	 * @var AI_Content_Extractor
	 */
	private $extractor;

	/**
	 * Initialize controller and register AJAX hooks
	 */
	public function init() {
		$this->extractor = new AI_Content_Extractor();
		parent::init();
	}

	/**
	 * Get AJAX actions to register
	 *
	 * @return array Array of action => config
	 */
	protected function get_ajax_actions() {
		return array(
			'ai_url_test_connection' => array(
				'callback' => 'test_connection',
			),
			'ai_url_preview'         => array(
				'callback' => 'preview_url',
			),
			'ai_url_get_acf_fields'  => array(
				'callback' => 'get_acf_fields',
			),
			'ai_url_start_import'    => array(
				'callback' => 'start_import',
			),
			'ai_url_process_batch'   => array(
				'callback' => 'process_batch',
			),
			'ai_url_get_post_types'  => array(
				'callback' => 'get_post_types',
			),
			'ai_url_get_progress'    => array(
				'callback' => 'get_progress',
			),
		);
	}

	/**
	 * Check if feature is available
	 *
	 * @return bool|\WP_Error
	 */
	private function check_feature_availability() {
		// Check if premium
		if ( ! function_exists( 'waie_fs' ) || ! waie_fs()->can_use_premium_code() ) {
			return new \WP_Error(
				'premium_required',
				__( 'AI URL Importer is a premium feature. Please upgrade to access this functionality.', 'wp-advanced-import-export' )
			);
		}

		// Check if OpenAI API key is set
		if ( ! \WP_AIE\Helper\AI_Function_Generator::has_api_key() ) {
			return new \WP_Error(
				'api_key_required',
				__( 'OpenAI API key is required. Please add your API key in Plugin Options.', 'wp-advanced-import-export' )
			);
		}

		return true;
	}

	/**
	 * Test OpenAI connection
	 */
	public function test_connection() {
		$verification = $this->verify_request( 'ai_url_importer' );
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		$availability = $this->check_feature_availability();
		if ( is_wp_error( $availability ) ) {
			$this->send_error( $availability, null, 403 );
		}

		// Test OpenAI API connection
		$test_result = $this->extractor->test_connection();

		if ( is_wp_error( $test_result ) ) {
			$this->send_error( $test_result );
		}

		$this->send_success(
			array(
				'message' => __( 'OpenAI API connection successful!', 'wp-advanced-import-export' ),
				'model'   => $test_result,
			)
		);
	}

	/**
	 * Preview content from a single URL
	 */
	public function preview_url() {
		$verification = $this->verify_request( 'ai_url_importer' );
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		$availability = $this->check_feature_availability();
		if ( is_wp_error( $availability ) ) {
			$this->send_error( $availability, null, 403 );
		}

		$url = isset( $_POST['url'] ) ? esc_url_raw( $_POST['url'] ) : '';

		if ( empty( $url ) ) {
			$this->send_error(
				new \WP_Error( 'missing_url', __( 'URL is required', 'wp-advanced-import-export' ) )
			);
		}

		// Extract content using AI
		$result = $this->extractor->extract_from_url( $url );

		if ( is_wp_error( $result ) ) {
			$this->send_error( $result );
		}

		$this->send_success( $result );
	}

	/**
	 * Get ACF fields for a post type
	 */
	public function get_acf_fields() {
		$verification = $this->verify_request( 'ai_url_importer' );
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		$post_type = isset( $_POST['post_type'] ) ? sanitize_text_field( $_POST['post_type'] ) : 'post';

		// Check if ACF is active
		if ( ! function_exists( 'acf_get_field_groups' ) ) {
			$this->send_error(
				new \WP_Error( 'acf_not_active', __( 'ACF plugin is not active', 'wp-advanced-import-export' ) )
			);
		}

		$fields = $this->get_acf_fields_for_post_type( $post_type );

		$this->send_success(
			array(
				'fields' => $fields,
			)
		);
	}

	/**
	 * Get ACF fields for a specific post type
	 *
	 * @param string $post_type Post type
	 * @return array ACF fields with nested structure
	 */
	private function get_acf_fields_for_post_type( $post_type ) {
		if ( ! function_exists( 'acf_get_field_groups' ) ) {
			return array();
		}

		$fields       = array();
		$field_groups = acf_get_field_groups(
			array(
				'post_type' => $post_type,
			)
		);

		foreach ( $field_groups as $group ) {
			$group_fields = acf_get_fields( $group['key'] );

			if ( $group_fields ) {
				foreach ( $group_fields as $field ) {
					$formatted = $this->format_acf_field( $field, $group['title'] );
					if ( $formatted ) {
						$fields[] = $formatted;
					}
				}
			}
		}

		return $fields;
	}

	/**
	 * Format ACF field with nested structure
	 *
	 * @param array  $field ACF field
	 * @param string $group_title Group title
	 * @param int    $level Nesting level
	 * @return array|null Formatted field or null if filtered out
	 */
	private function format_acf_field( $field, $group_title, $level = 0 ) {
		// Allowed field types for content
		$allowed_types = array( 'text', 'textarea', 'wysiwyg' );
		
		$formatted = array(
			'key'        => $field['key'],
			'name'       => $field['name'],
			'label'      => $field['label'],
			'type'       => $field['type'],
			'group'      => $group_title,
			'level'      => $level,
			'sub_fields' => array(),
			'is_allowed' => in_array( $field['type'], $allowed_types, true ),
		);

		// Handle repeater, flexible content and group sub-fields
		if ( in_array( $field['type'], array( 'repeater', 'flexible_content', 'group' ) ) && ! empty( $field['sub_fields'] ) ) {
			foreach ( $field['sub_fields'] as $sub_field ) {
				$formatted_sub = $this->format_acf_field( $sub_field, $group_title, $level + 1 );
				if ( $formatted_sub ) {
					$formatted['sub_fields'][] = $formatted_sub;
				}
			}
		}

		// Only return fields that are allowed or have allowed sub-fields
		if ( $formatted['is_allowed'] || ! empty( $formatted['sub_fields'] ) ) {
			return $formatted;
		}

		return null;
	}

	/**
	 * Get available post types
	 */
	public function get_post_types() {
		$verification = $this->verify_request( 'ai_url_importer' );
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		$post_types = get_post_types(
			array(
				'public' => true,
			),
			'objects'
		);

		$result = array();
		foreach ( $post_types as $post_type ) {
			$result[] = array(
				'value' => $post_type->name,
				'label' => $post_type->label,
			);
		}

		$this->send_success(
			array(
				'post_types' => $result,
			)
		);
	}

	/**
	 * Start import job
	 */
	public function start_import() {
		$verification = $this->verify_request( 'ai_url_importer' );
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		$availability = $this->check_feature_availability();
		if ( is_wp_error( $availability ) ) {
			$this->send_error( $availability, null, 403 );
		}

		// Get parameters
		$urls         = isset( $_POST['urls'] ) ? array_map( 'esc_url_raw', $_POST['urls'] ) : array();
		$post_type    = isset( $_POST['post_type'] ) ? sanitize_text_field( $_POST['post_type'] ) : 'post';
		$content_field = isset( $_POST['content_field'] ) ? sanitize_text_field( $_POST['content_field'] ) : 'post_content';
		$timeout      = isset( $_POST['timeout'] ) ? absint( $_POST['timeout'] ) : 2;
		$acf_field    = isset( $_POST['acf_field'] ) ? sanitize_text_field( $_POST['acf_field'] ) : '';
		$custom_field_name = isset( $_POST['custom_field_name'] ) ? sanitize_text_field( $_POST['custom_field_name'] ) : '';

		if ( empty( $urls ) ) {
			$this->send_error(
				new \WP_Error( 'missing_urls', __( 'URLs are required', 'wp-advanced-import-export' ) )
			);
		}

		// Determine the actual field name to use
		$field_name = $content_field;
		if ( 'acf_field' === $content_field && ! empty( $acf_field ) ) {
			$field_name = $acf_field;
		} elseif ( 'custom_field' === $content_field && ! empty( $custom_field_name ) ) {
			$field_name = $custom_field_name;
		} else {
			$field_name = 'post_content';
		}

		// Create job
		$job_model = new Job();
		$job_id    = $job_model->create(
			array(
				'type'       => 'import',
				'data_type'  => 'ai_url',
				'file_format' => 'url',
				'status'     => 'pending',
				'total_items' => count( $urls ),
				'parameters' => wp_json_encode(
					array(
						'urls'          => $urls,
						'post_type'     => $post_type,
						'content_field' => $field_name,
						'timeout'       => $timeout,
						'offset'        => 0,
					)
				),
			)
		);

		if ( is_wp_error( $job_id ) ) {
			$this->send_error( $job_id );
		}

		// Job created, JavaScript will start processing batches via AJAX
		$this->send_success(
			array(
				'job_id'  => $job_id,
				'message' => __( 'Import job created successfully', 'wp-advanced-import-export' ),
			)
		);
	}

	/**
	 * Process next batch of URLs
	 */
	public function process_batch() {
		$verification = $this->verify_request( 'ai_url_importer' );
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		$availability = $this->check_feature_availability();
		if ( is_wp_error( $availability ) ) {
			$this->send_error( $availability, null, 403 );
		}

		// Get job ID
		$job_id = isset( $_POST['job_id'] ) ? absint( $_POST['job_id'] ) : 0;

		if ( ! $job_id ) {
			$this->send_error(
				new \WP_Error( 'missing_job_id', __( 'Job ID is required', 'wp-advanced-import-export' ) )
			);
		}

		// Process the batch
		$processor = new \WP_AIE\Model\Queue\AI_URL_Import_Processor();
		$result    = $processor->process( $job_id );

		if ( is_wp_error( $result ) ) {
			$this->send_error( $result );
		}

		$this->send_success( $result );
	}

	/**
	 * Get import progress
	 */
	public function get_progress() {
		$verification = $this->verify_request( 'ai_url_importer' );
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		// Get job ID
		$job_id = isset( $_POST['job_id'] ) ? absint( $_POST['job_id'] ) : 0;

		if ( ! $job_id ) {
			$this->send_error(
				new \WP_Error( 'missing_job_id', __( 'Job ID is required', 'wp-advanced-import-export' ) )
			);
		}

		// Get job
		$job_model = new Job();
		$job       = $job_model->find( $job_id );

		if ( ! $job ) {
			$this->send_error(
				new \WP_Error( 'job_not_found', __( 'Job not found', 'wp-advanced-import-export' ) )
			);
		}

		$this->send_success(
			array(
				'status'        => $job->status,
				'progress'      => floatval( $job->progress ),
				'processed'     => intval( $job->processed_items ),
				'total'         => intval( $job->total_items ),
				'success_count' => intval( $job->success_items ?? 0 ),
				'failed_count'  => intval( $job->failed_items ?? 0 ),
				'error'         => $job->error ?? '',
			)
		);
	}
}
