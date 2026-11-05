<?php
/**
 * REST response that holds an already authorized download stream.
 *
 * @package RockStarLab\ImportExport\Controller
 */

namespace RockStarLab\ImportExport\Controller;

defined( 'ABSPATH' ) || exit;

/**
 * Binary data is served by rest_pre_serve_request, never serialized into JSON.
 */
class File_Download_Response extends \WP_REST_Response {

	/**
	 * Owned stream, closed after serving or when the response is discarded.
	 *
	 * @var resource|null
	 */
	private $stream;

	/**
	 * Construct from File_Service::download's trusted result.
	 *
	 * @param array $download Open stream and public download metadata.
	 */
	public function __construct( array $download ) {
		$this->stream = $download['stream'];
		parent::__construct(
			null,
			200,
			[
				'Content-Type'           => $download['content_type'],
				'Content-Length'         => (string) $download['file_size'],
				'Content-Disposition'    => 'attachment; filename="' . sanitize_file_name( $download['filename'] ) . '"',
				'Cache-Control'          => 'private, no-store',
				'X-Content-Type-Options' => 'nosniff',
			]
		);
	}

	/**
	 * Stream without buffering the entire download in PHP memory.
	 *
	 * @param bool $head Whether to omit the body.
	 */
	public function serve( $head = false ) {
		if ( is_resource( $this->stream ) ) {
			if ( ! $head ) {
				fpassthru( $this->stream ); // phpcs:ignore WordPress.Security.EscapeOutput,WordPress.WP.AlternativeFunctions -- Authorized binary attachment, not HTML.
			}
			fclose( $this->stream ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- Close the owned stream.
			$this->stream = null;
		}
	}

	/** Close streams for unserved responses, including internal REST dispatch. */
	public function __destruct() {
		$this->serve( true );
	}
}
