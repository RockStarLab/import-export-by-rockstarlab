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
			'functions_get_all'      => [ 'callback' => 'get_all_functions' ],
			'functions_get'          => [ 'callback' => 'get_function' ],
			'functions_create'       => [ 'callback' => 'create_function' ],
			'functions_update'       => [ 'callback' => 'update_function' ],
			'functions_delete'       => [ 'callback' => 'delete_function' ],
			'functions_test'         => [ 'callback' => 'test_function' ],
			'functions_get_snippets' => [ 'callback' => 'get_snippets' ],
			'functions_search'       => [ 'callback' => 'search_snippets' ],
			'functions_import'       => [ 'callback' => 'import_snippet' ],
		];
	}

	/**
	 * Get all functions
	 */
	public function get_all_functions() {
		$verify = $this->verify_request( 'functions_get_all' );
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

		$this->send_success(
			[
				'functions'   => $functions,
				'total'       => $total,
				'page'        => $page,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total / $per_page ),
			]
		);
	}

	/**
	 * Get single function
	 */
	public function get_function() {
		$verify = $this->verify_request( 'functions_get' );
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
		}

		$function_id = (int) $this->get_request_param( 'id', 0 );

		if ( empty( $function_id ) ) {
			$this->send_error( __( 'Function ID is required', 'wp-aie' ) );
		}

		$model    = new Custom_Function();
		$function = $model->get( $function_id );

		if ( ! $function ) {
			$this->send_error( __( 'Function not found', 'wp-aie' ) );
		}

		$this->send_success( [ 'function' => $function ] );
	}

	/**
	 * Create new function
	 */
	public function create_function() {
		$verify = $this->verify_request( 'functions_create' );
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
		}

		$name        = $this->get_request_param( 'name', '' );
		$description = $this->get_request_param( 'description', '' );
		$code        = $this->get_request_param( 'code', '' );
		$category    = $this->get_request_param( 'category', 'custom' );
		$status      = $this->get_request_param( 'status', 'active' );

		if ( empty( $name ) || empty( $code ) ) {
			$this->send_error( __( 'Name and code are required', 'wp-aie' ) );
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

		if ( ! $function_id ) {
			$this->send_error( __( 'Failed to create function. Please check code for errors.', 'wp-aie' ) );
		}

		$function = $model->get( $function_id );

		$this->send_success(
			[
				'message'  => __( 'Function created successfully', 'wp-aie' ),
				'function' => $function,
			]
		);
	}

	/**
	 * Update existing function
	 */
	public function update_function() {
		$verify = $this->verify_request( 'functions_update' );
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
		}

		$function_id = (int) $this->get_request_param( 'id', 0 );

		if ( empty( $function_id ) ) {
			$this->send_error( __( 'Function ID is required', 'wp-aie' ) );
		}

		$model = new Custom_Function();

		// Check if function exists and user can edit
		if ( ! $model->can_edit_function( $function_id ) ) {
			$this->send_error( __( 'You do not have permission to edit this function', 'wp-aie' ) );
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

		$code = $this->get_request_param( 'code', '' );
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
			$this->send_error( __( 'No data to update', 'wp-aie' ) );
		}

		$result = $model->update( $function_id, $update_data );

		if ( ! $result ) {
			$this->send_error( __( 'Failed to update function. Please check code for errors.', 'wp-aie' ) );
		}

		$function = $model->get( $function_id );

		$this->send_success(
			[
				'message'  => __( 'Function updated successfully', 'wp-aie' ),
				'function' => $function,
			]
		);
	}

	/**
	 * Delete function
	 */
	public function delete_function() {
		$verify = $this->verify_request( 'functions_delete' );
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
		}

		$function_id = (int) $this->get_request_param( 'id', 0 );

		if ( empty( $function_id ) ) {
			$this->send_error( __( 'Function ID is required', 'wp-aie' ) );
		}

		$model = new Custom_Function();

		// Check permissions
		if ( ! $model->can_edit_function( $function_id ) ) {
			$this->send_error( __( 'You do not have permission to delete this function', 'wp-aie' ) );
		}

		$result = $model->delete( $function_id );

		if ( ! $result ) {
			$this->send_error( __( 'Failed to delete function', 'wp-aie' ) );
		}

		$this->send_success(
			[
				'message' => __( 'Function deleted successfully', 'wp-aie' ),
			]
		);
	}

	/**
	 * Test function with sample value
	 */
	public function test_function() {
		$verify = $this->verify_request( 'functions_test' );
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
		}

		$code  = $this->get_request_param( 'code', '' );
		$value = $this->get_request_param( 'value', '' );

		if ( empty( $code ) ) {
			$this->send_error( __( 'Code is required', 'wp-aie' ) );
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
	 * Get all snippets from library
	 */
	public function get_snippets() {
		$verify = $this->verify_request( 'functions_get_snippets' );
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
		$verify = $this->verify_request( 'functions_search' );
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
		}

		$query = $this->get_request_param( 'query', '' );

		if ( empty( $query ) ) {
			$this->send_error( __( 'Search query is required', 'wp-aie' ) );
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
		$verify = $this->verify_request( 'functions_import' );
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
		}

		$snippet_key = $this->get_request_param( 'snippet_key', '' );
		$name        = $this->get_request_param( 'name', '' );
		$description = $this->get_request_param( 'description', '' );

		if ( empty( $snippet_key ) ) {
			$this->send_error( __( 'Snippet key is required', 'wp-aie' ) );
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
			$this->send_error( __( 'Failed to import snippet', 'wp-aie' ) );
		}

		$function = $model->get( $function_id );

		$this->send_success(
			[
				'message'  => __( 'Snippet imported successfully', 'wp-aie' ),
				'function' => $function,
			]
		);
	}
}
