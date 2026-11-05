<?php
/**
 * Authenticated binary transport for agents and other REST clients.
 *
 * @package RockStarLab\ImportExport\Controller
 */

namespace RockStarLab\ImportExport\Controller;

use RockStarLab\ImportExport\Abilities\Access_Policy;
use RockStarLab\ImportExport\Service\File_Service;

defined( 'ABSPATH' ) || exit;

/**
 * WordPress REST authentication supports Application Passwords without cookies.
 */
class Files_Controller {

	/** Register independently of the optional WordPress Abilities API. */
	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
		add_filter( 'rest_pre_serve_request', [ $this, 'serve_download' ], 10, 3 );
		add_action( 'admin_post_rsl_ie_file_download', [ $this, 'browser_download' ] );
		add_action( 'admin_post_nopriv_rsl_ie_file_download', [ $this, 'browser_download' ] );
	}

	/**
	 * Download through the browser's WordPress session, prompting login if needed.
	 *
	 * This read-only GET needs no REST nonce from the agent's separate session.
	 * File_Service rechecks current capabilities, site and export permissions.
	 */
	public function browser_download() {
		$method = $_SERVER['REQUEST_METHOD'] ?? '';
		if ( ! in_array( $method, [ 'GET', 'HEAD' ], true ) ) {
			header( 'Allow: GET, HEAD' );
			wp_die( esc_html__( 'Use GET to download an export.', 'import-export-by-rockstarlab' ), '', [ 'response' => 405 ] );
		}
		auth_redirect();
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only download; authentication and service authorization are required.
		$id  = isset( $_GET['file_id'] ) && is_string( $_GET['file_id'] ) ? wp_unslash( $_GET['file_id'] ) : '';
		$zip = isset( $_GET['zip'] ) && is_string( $_GET['zip'] ) && rest_sanitize_boolean( wp_unslash( $_GET['zip'] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$result = ( new File_Service() )->download( $id, $zip );
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ), '', [ 'response' => (int) ( $result->get_error_data()['status'] ?? 400 ) ] );
		}
		$response = new File_Download_Response( $result );
		nocache_headers();
		foreach ( $response->get_headers() as $name => $value ) {
			header( $name . ': ' . $value );
		}
		$response->serve( 'HEAD' === $method );
		exit;
	}

	/** Register JSON lifecycle and multipart binary endpoints. */
	public function register_routes() {
		$id     = '/files/(?P<file_id>file_[a-f0-9]{32})';
		$routes = [
			[ '/files', 'POST', 'upload' ],
			[ '/files/uploads', 'POST', 'begin' ],
			[ '/files/from-job', 'POST', 'from_job' ],
			[ $id, 'GET', 'get' ],
			[ $id . '/chunks/(?P<index>[0-9]+)', 'POST', 'chunk' ],
			[ $id . '/finalize', 'POST', 'finalize' ],
			[ $id . '/preview', 'POST', 'preview' ],
			[ $id . '/abort', 'POST', 'abort' ],
			[ $id . '/download', 'GET', 'download' ],
		];
		foreach ( $routes as $route ) {
			register_rest_route(
				'rsl-ie/v1',
				$route[0],
				[
					'methods'             => $route[1],
					'permission_callback' => [ $this, 'permission' ],
					'callback'            => function ( $request ) use ( $route ) {
						return $this->dispatch( $route[2], $request ); },
				]
			);
		}
	}

	/**
	 * Keep capability checks separate from authentication provided by WordPress.
	 *
	 * @return true|\WP_Error
	 */
	public function permission() {
		return Access_Policy::check_management();
	}

	/**
	 * Read only operation-specific fields; identities and paths are never input.
	 *
	 * @param string           $operation Server-selected operation.
	 * @param \WP_REST_Request $request Authenticated request.
	 * @return mixed
	 */
	private function dispatch( $operation, \WP_REST_Request $request ) {
		$service = new File_Service();
		$id      = $request->get_url_params()['file_id'] ?? '';
		$options = [];
		foreach ( [ 'delimiter', 'has_header', 'encoding' ] as $key ) {
			if ( $request->has_param( $key ) ) {
				$options[ $key ] = $request[ $key ];
			}
		}
		if ( isset( $options['has_header'] ) && ! is_bool( $options['has_header'] ) ) {
			if ( ! in_array( $options['has_header'], [ 'true', 'false', '1', '0' ], true ) ) {
				return new \WP_Error( 'rsl_ie_invalid_boolean', __( 'A boolean parser option is required.', 'import-export-by-rockstarlab' ), [ 'status' => 400 ] );
			}
			$options['has_header'] = rest_sanitize_boolean( $options['has_header'] );
		}
		switch ( $operation ) {
			case 'begin':
				return $service->begin(
					[
						'file_name'    => $request['file_name'],
						'file_size'    => $request['file_size'],
						'total_chunks' => $request['total_chunks'] ?? 1,
					]
				);
			case 'upload':
			case 'chunk':
				$files = $request->get_file_params();
				$file  = $files[ 'upload' === $operation ? 'file' : 'chunk' ] ?? [];
				if ( ! is_array( $file ) ) {
					$file = [];
				}
				return 'upload' === $operation ? $service->upload( $file, $options ) : $service->upload_chunk( $id, $request->get_url_params()['index'], $file );
			case 'finalize':
				if ( $request->has_param( 'resumable' ) && ! in_array( $request['resumable'], [ true, false, 'true', 'false', '1', '0', 1, 0 ], true ) ) {
					return new \WP_Error( 'rsl_ie_invalid_boolean', __( 'A boolean resumable option is required.', 'import-export-by-rockstarlab' ), [ 'status' => 400 ] );
				}
				return $service->finalize( $id, $options, rest_sanitize_boolean( $request['resumable'] ?? false ) );
			case 'preview':
				return $service->preview( $id, $options );
			case 'abort':
				return $service->abort( $id );
			case 'from_job':
				$job_id = filter_var( $request['job_id'], FILTER_VALIDATE_INT );
				return $service->from_job( $job_id ? $job_id : 0 );
			case 'download':
				$result = $service->download( $id, rest_sanitize_boolean( $request['zip'] ?? false ) );
				return is_wp_error( $result ) ? $result : new File_Download_Response( $result );
			default:
				return $service->get( $id );
		}
	}

	/**
	 * Serve only this controller's authorized binary responses.
	 *
	 * @param bool              $served Already handled.
	 * @param \WP_HTTP_Response $result Response.
	 * @param \WP_REST_Request  $request Request.
	 * @return bool
	 */
	public function serve_download( $served, $result, $request ) {
		if ( ! $served && $result instanceof File_Download_Response ) {
			$result->serve( 'HEAD' === $request->get_method() );
			return true;
		}
		return $served;
	}
}
