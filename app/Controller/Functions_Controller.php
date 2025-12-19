<?php
/**
 * Functions Controller
 *
 * Handles custom functions AJAX operations
 *
 * @package WP_AIE\Controller
 */

namespace WP_AIE\Controller;

use WP_AIE\Model\Custom_Function;
use WP_AIE\Helper\Function_Snippets;
use WP_AIE\Helper\Function_Executor;

/**
 * Functions Controller Class
 *
 * Manages custom functions via AJAX:
 * - CRUD operations
 * - Testing functions
 * - Browsing snippet library
 * - Importing snippets
 *
 * @package WP_AIE\Controller
 */
class Functions_Controller extends Base_Controller {

	/**
	 * Get AJAX actions
	 *
	 * @return array
	 */
	protected function get_ajax_actions() {
		return [
			'get_functions'          => [ 'callback' => 'get_functions_list' ],
			'functions_get_all'      => [ 'callback' => 'get_all_functions' ],
			'functions_get'          => [ 'callback' => 'get_function' ],
			'functions_create'       => [ 'callback' => 'create_function' ],
			'functions_update'       => [ 'callback' => 'update_function' ],
			'functions_delete'       => [ 'callback' => 'delete_function' ],
			'functions_test'         => [ 'callback' => 'test_function' ],
			'test_function_pipeline' => [ 'callback' => 'test_function_pipeline' ],
			'functions_get_snippets' => [ 'callback' => 'get_snippets' ],
			'functions_search'       => [ 'callback' => 'search_snippets' ],
			'functions_import'       => [ 'callback' => 'import_snippet' ],
		];
	}

	/**
	 * Get functions list (simplified for export field selection)
	 * Returns only active functions without pagination
	 */
	public function get_functions_list() {
		$verify = $this->verify_request( 'nonce' );
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
		}

		$model = new Custom_Function();

		$args = [
			'status'  => 'active',
			'orderby' => 'name',
			'order'   => 'ASC',
		];

		$functions = $model->get_all( $args );

		// Format custom functions for the export interface
		$formatted_functions = array_map(
			function ( $function ) {
				return [
					'id'          => $function['id'],
					'name'        => $function['name'],
					'description' => $function['description'],
					'category'    => 'custom',
				];
			},
			$functions
		);

		// Also add library snippets as available functions
		$library  = new Function_Snippets();
		$snippets = $library->get_all_snippets();

		foreach ( $snippets as $snippet_key => $snippet ) {
			$formatted_functions[] = [
				'id'          => 'snippet_' . $snippet_key,
				'name'        => $snippet['name'],
				'description' => $snippet['description'],
				'category'    => 'library',
				'snippet_id'  => $snippet_key,
			];
		}

		$this->send_success(
			[
				'functions' => $formatted_functions,
			]
		);
	}

	/**
	 * Get all functions
	 */
	public function get_all_functions() {
		$verify = $this->verify_request( 'nonce' );
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
		}

		$model = new Custom_Function();

		// Get filters from request
		$status   = $this->get_request_param( 'status', '' );
		$category = $this->get_request_param( 'category', '' );
		$search   = $this->get_request_param( 'search', '' );
		$page     = max( 1, (int) $this->get_request_param( 'page', 1 ) );
		$per_page = max( 1, min( 100, (int) $this->get_request_param( 'per_page', 20 ) ) );

		$args = [
			'orderby' => 'name',
			'order'   => 'ASC',
			'limit'   => $per_page,
			'offset'  => ( $page - 1 ) * $per_page,
		];

		if ( ! empty( $status ) ) {
			$args['status'] = $status;
		}

		if ( ! empty( $category ) ) {
			$args['category'] = $category;
		}

		if ( ! empty( $search ) ) {
			$args['search'] = $search;
		}

		$functions = $model->get_all( $args );
		$total     = $model->get_count( $args );

		// Add library snippets
		$library  = new Function_Snippets();
		$snippets = $library->get_all_snippets();

		$snippet_functions = [];
		foreach ( $snippets as $key => $snippet ) {
			$snippet_functions[] = [
				'id'          => 'snippet_' . $key,
				'name'        => $snippet['name'],
				'description' => $snippet['description'],
				'category'    => $snippet['category'],
				'type'        => 'library',
			];
		}

		// Merge custom functions and snippets
		$all_functions = array_merge( $functions, $snippet_functions );

		$this->send_success(
			[
				'functions'   => $all_functions,
				'total'       => $total + count( $snippet_functions ),
				'page'        => $page,
				'per_page'    => $per_page,
				'total_pages' => ceil( ( $total + count( $snippet_functions ) ) / $per_page ),
			]
		);
	}

	/**
	 * Get single function
	 */
	public function get_function() {
		$verify = $this->verify_request( 'nonce' );
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
		}

		$function_id = (int) $this->get_request_param( 'id', 0 );

		if ( empty( $function_id ) ) {
			$this->send_error( __( 'Function ID is required', 'wp-advanced-import-export' ) );
		}

		$model    = new Custom_Function();
		$function = $model->get( $function_id );

		if ( ! $function ) {
			$this->send_error( __( 'Function not found', 'wp-advanced-import-export' ) );
		}

		$this->send_success( $function );
	}

	/**
	 * Create new function
	 */
	public function create_function() {
		$verify = $this->verify_request( 'nonce' );
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
		}

		$name        = $this->get_request_param( 'name', '' );
		$description = $this->get_request_param( 'description', '' );
		// Don't use get_request_param for code - it uses sanitize_text_field which removes newlines
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$code     = isset( $_REQUEST['code'] ) ? wp_unslash( $_REQUEST['code'] ) : '';
		$category = $this->get_request_param( 'category', 'custom' );
		$status   = $this->get_request_param( 'status', 'active' );

		if ( empty( $name ) || empty( $code ) ) {
			$this->send_error( __( 'Name and code are required', 'wp-advanced-import-export' ) );
		}

		$model = new Custom_Function();

		$function_id = $model->create(
			[
				'name'        => $name,
				'description' => $description,
				'code'        => $code,
				'category'    => $category,
				'status'      => $status,
			]
		);

		if ( is_wp_error( $function_id ) ) {
			$this->send_error( $function_id->get_error_message() );
		}

		if ( ! $function_id ) {
			$this->send_error( __( 'Failed to create function', 'wp-advanced-import-export' ) );
		}

		$function = $model->get( $function_id );

		$this->send_success(
			[
				'message'  => __( 'Function created successfully', 'wp-advanced-import-export' ),
				'function' => $function,
			]
		);
	}

	/**
	 * Update existing function
	 */
	public function update_function() {
		$verify = $this->verify_request( 'nonce' );
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
		}

		$function_id = (int) $this->get_request_param( 'id', 0 );

		if ( empty( $function_id ) ) {
			$this->send_error( __( 'Function ID is required', 'wp-advanced-import-export' ) );
		}

		$model = new Custom_Function();

		// Check if function exists and user can edit
		if ( ! $model->can_edit_function( $function_id ) ) {
			$this->send_error( __( 'You do not have permission to edit this function', 'wp-advanced-import-export' ) );
		}

		$update_data = [];

		$name = $this->get_request_param( 'name', '' );
		if ( ! empty( $name ) ) {
			$update_data['name'] = $name;
		}

		$description = $this->get_request_param( 'description', null );
		if ( null !== $description ) {
			$update_data['description'] = $description;
		}

		// Don't use get_request_param for code - it uses sanitize_text_field which removes newlines
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$code = isset( $_REQUEST['code'] ) ? wp_unslash( $_REQUEST['code'] ) : '';
		if ( ! empty( $code ) ) {
			$update_data['code'] = $code;
		}

		$category = $this->get_request_param( 'category', null );
		if ( null !== $category ) {
			$update_data['category'] = $category;
		}

		$status = $this->get_request_param( 'status', null );
		if ( null !== $status ) {
			$update_data['status'] = $status;
		}

		if ( empty( $update_data ) ) {
			$this->send_error( __( 'No data to update', 'wp-advanced-import-export' ) );
		}

		$result = $model->update( $function_id, $update_data );

		if ( is_wp_error( $result ) ) {
			$this->send_error( $result->get_error_message() );
		}

		if ( ! $result ) {
			$this->send_error( __( 'Failed to update function', 'wp-advanced-import-export' ) );
		}

		$function = $model->get( $function_id );

		$this->send_success(
			[
				'message'  => __( 'Function updated successfully', 'wp-advanced-import-export' ),
				'function' => $function,
			]
		);
	}

	/**
	 * Delete function
	 */
	public function delete_function() {
		$verify = $this->verify_request( 'nonce' );
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
		}

		$function_id = (int) $this->get_request_param( 'id', 0 );

		if ( empty( $function_id ) ) {
			$this->send_error( __( 'Function ID is required', 'wp-advanced-import-export' ) );
		}

		$model = new Custom_Function();

		// Check permissions
		if ( ! $model->can_edit_function( $function_id ) ) {
			$this->send_error( __( 'You do not have permission to delete this function', 'wp-advanced-import-export' ) );
		}

		$result = $model->delete( $function_id );

		if ( ! $result ) {
			$this->send_error( __( 'Failed to delete function', 'wp-advanced-import-export' ) );
		}

		$this->send_success(
			[
				'message' => __( 'Function deleted successfully', 'wp-advanced-import-export' ),
			]
		);
	}

	/**
	 * Test function with sample value
	 */
	public function test_function() {
		$verify = $this->verify_request( 'nonce' );
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
		}

		// Don't use get_request_param for code - it uses sanitize_text_field which removes newlines
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$code  = isset( $_REQUEST['code'] ) ? wp_unslash( $_REQUEST['code'] ) : '';
		$value = $this->get_request_param( 'value', '' );

		if ( empty( $code ) ) {
			$this->send_error( __( 'Code is required', 'wp-advanced-import-export' ) );
		}

		$model  = new Custom_Function();
		$result = $model->test_function( $code, $value );

		if ( ! $result['success'] ) {
			$this->send_error( $result['error'] );
		}

		$this->send_success(
			[
				'input'  => $result['input'],
				'output' => $result['output'],
			]
		);
	}

	/**
	 * Test function pipeline with multiple functions
	 */
	public function test_function_pipeline() {
		$verify = $this->verify_request( 'nonce' );
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
		}

		$value        = $this->get_request_param( 'value', '' );
		$function_ids = $this->get_request_array( 'functions' );

		if ( empty( $function_ids ) ) {
			$this->send_error( __( 'No functions provided', 'wp-advanced-import-export' ) );
		}

		$model         = new Custom_Function();
		$library       = new Function_Snippets();
		$steps         = [];
		$current_value = $value;

		foreach ( $function_ids as $function_id ) {
			$code          = '';
			$function_name = '';

			// Check if it's a library snippet
			if ( strpos( $function_id, 'snippet_' ) === 0 ) {
				$snippet_id = str_replace( 'snippet_', '', $function_id );
				$snippet    = $library->get_snippet_by_id( $snippet_id );

				if ( ! $snippet ) {
					$steps[] = [
						'function_id'   => $function_id,
						'function_name' => 'Unknown Snippet',
						'output'        => 'Snippet not found',
						'error'         => true,
					];
					break;
				}

				$code          = isset( $snippet['code'] ) ? $snippet['code'] : '';
				$function_name = isset( $snippet['name'] ) ? $snippet['name'] : 'Unknown';

				// Check if code is empty
				if ( empty( $code ) ) {
					$steps[] = [
						'function_id'   => $function_id,
						'function_name' => $function_name,
						'output'        => 'Function code cannot be empty. Snippet data: ' . print_r( $snippet, true ),
						'error'         => true,
					];
					break;
				}
			} else {
				// Custom function from database
				$function = $model->find( $function_id );

				if ( ! $function ) {
					$steps[] = [
						'function_id'   => $function_id,
						'function_name' => 'Unknown Function',
						'output'        => 'Function not found',
						'error'         => true,
					];
					break;
				}

				// Support both 'code' and 'function_code' field names
				$code          = isset( $function->code ) ? $function->code : ( isset( $function->function_code ) ? $function->function_code : '' );
				$function_name = $function->name;

				// Check if code is empty
				if ( empty( trim( $code ) ) ) {
					$steps[] = [
						'function_id'   => $function_id,
						'function_name' => $function_name,
						'output'        => 'Function code cannot be empty. Available fields: ' . implode( ', ', array_keys( (array) $function ) ),
						'error'         => true,
					];
					break;
				}
			}

			$result = $model->test_function( $code, $current_value );

			if ( ! $result['success'] ) {
				$steps[] = [
					'function_id'   => $function_id,
					'function_name' => $function_name,
					'output'        => $result['error'],
					'error'         => true,
				];
				break;
			}

			$steps[] = [
				'function_id'   => $function_id,
				'function_name' => $function_name,
				'output'        => $result['output'],
				'error'         => false,
			];

			$current_value = $result['output'];
		}

		$this->send_success(
			[
				'input' => $value,
				'steps' => $steps,
			]
		);
	}

	/**
	 * Get all snippets from library
	 */
	public function get_snippets() {
		$verify = $this->verify_request( 'nonce' );
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
		}

		$library  = new Function_Snippets();
		$category = $this->get_request_param( 'category', '' );

		if ( ! empty( $category ) ) {
			$snippets = $library->get_by_category( $category );
		} else {
			$snippets = $library->get_all_snippets();
		}

		$categories = $library->get_categories();

		$this->send_success(
			[
				'snippets'   => $snippets,
				'categories' => $categories,
			]
		);
	}

	/**
	 * Search snippets
	 */
	public function search_snippets() {
		$verify = $this->verify_request( 'nonce' );
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
		}

		$query = $this->get_request_param( 'query', '' );

		if ( empty( $query ) ) {
			$this->send_error( __( 'Search query is required', 'wp-advanced-import-export' ) );
		}

		$library = new Function_Snippets();
		$results = $library->search( $query );

		$this->send_success(
			[
				'snippets' => $results,
				'query'    => $query,
			]
		);
	}

	/**
	 * Import snippet as function
	 */
	public function import_snippet() {
		$verify = $this->verify_request( 'nonce' );
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
		}

		$snippet_key = $this->get_request_param( 'snippet_key', '' );
		$name        = $this->get_request_param( 'name', '' );
		$description = $this->get_request_param( 'description', '' );

		if ( empty( $snippet_key ) ) {
			$this->send_error( __( 'Snippet key is required', 'wp-advanced-import-export' ) );
		}

		$model = new Custom_Function();

		$function_id = $model->create_from_snippet(
			$snippet_key,
			[
				'name'        => $name,
				'description' => $description,
			]
		);

		if ( ! $function_id ) {
			$this->send_error( __( 'Failed to import snippet', 'wp-advanced-import-export' ) );
		}

		$function = $model->get( $function_id );

		$this->send_success(
			[
				'message'  => __( 'Snippet imported successfully', 'wp-advanced-import-export' ),
				'function' => $function,
			]
		);
	}
}
