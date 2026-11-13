<?php
/**
 * JSON-only adapters over shared services; no AJAX globals or worker endpoints.
 *
 * @package RockStarLab\ImportExport\Service
 */

namespace RockStarLab\ImportExport\Service;

use RockStarLab\ImportExport\Abilities\Access_Policy;
use RockStarLab\ImportExport\Abilities\Workflow_Schemas;
use RockStarLab\ImportExport\Abilities\Providers\Free_Content_Provider;
use RockStarLab\ImportExport\Model\Import\Importer_Factory;
use RockStarLab\ImportExport\Model\Export\Exporter_Factory;
use RockStarLab\ImportExport\Helper\ACF_Fields;
use RockStarLab\ImportExport\Helper\Seo_Fields;
use RockStarLab\ImportExport\Helper\Elementor_Fields;

defined( 'ABSPATH' ) || exit;

/** Authorizes direct PHP calls as well as Core execution. */
class Workflow_Service {
	/**
	 * Dispatch only reviewed public operations.
	 *
	 * @param string            $action Ability suffix selected by the controller.
	 * @param array|object|null $input Core-validated input.
	 * @return array|\WP_Error
	 */
	public function execute( $action, $input = [] ) {
		$permission = Access_Policy::check_management();
		if ( is_wp_error( $permission ) ) {
			return $permission; }
		$input = (array) $input;
		$jobs  = new Job_Service();
		$files = new File_Service();
		if ( 'get-content-schema' === $action ) {
			return $this->content_schema( $input['content_type'], $input['meta_offset'] ?? 0 ); }
		if ( 'list-url-sources' === $action ) {
			$sources = [];
			foreach ( \RockStarLab\ImportExport\Model\Export\Urls_Exporter::get_available_source_tokens() as $token ) {
				$request = Export_Request::normalize(
					[
						'export_type' => 'urls',
						'options'     => [ 'content_types' => [ $token ] ],
					]
				);
				if ( ! is_wp_error( $request ) ) {
					$sources[] = $token; }
			}
			return [ 'sources' => $sources ];
		}
		if ( 'get-diagnostics' === $action ) {
			return [
				'api_version'          => 1,
				'plugin_version'       => RSL_IE_VERSION,
				'wordpress_version'    => get_bloginfo( 'version' ),
				'php_version'          => PHP_VERSION,
				'cron_disabled'        => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
				'recovery_scheduled'   => false !== wp_next_scheduled( 'rsl_ie_recover_jobs' ),
				'max_upload_bytes'     => File_Service::max_bytes(),
				'max_json_chunk_bytes' => 1048576,
			];
		}

		if ( 0 === strpos( $action, 'import-' ) || 0 === strpos( $action, 'export-' ) ) {
			$operation = 0 === strpos( $action, 'import-' ) ? 'import' : 'export';
			$type      = $input[ $operation . '_type' ] ?? '';
			if ( null === ( new Free_Content_Provider() )->normalize( $type ) ) {
				return $this->error( 'content_type_forbidden', 403 ); }
			if ( 'import-prepare' === $action ) {
				return $this->prepare( $input ); }
			$service = 'import' === $operation ? new Import_Service() : new Export_Service();
			$method  = substr( $action, strlen( $operation ) + 1 );
			if ( ! in_array( $method, [ 'count', 'preview', 'validate', 'start' ], true ) || ! is_callable( [ $service, $method ] ) ) {
				return $this->error( 'unknown_workflow', 404 ); }
			return $service->$method( $input );
		}
		switch ( $action ) {
			case 'upload-create':
				$result = $files->begin( $input );
				if ( ! is_wp_error( $result ) ) {
					$result['chunk_url'] = rest_url( 'rsl-ie/v1/files/' . $result['file_id'] . '/chunks/{index}' ); }
				return $result;
			case 'upload-chunk':
				return $this->chunk( $input );
			case 'upload-complete':
				return $files->finalize( $input['file_id'], $input['parser_options'] ?? [], $input['resumable'] ?? false );
			case 'upload-from-url':
				$fetched = $files->from_url( (string) $input['url'], $input['parser_options'] ?? [] );
				return is_wp_error( $fetched ) ? $fetched : array_diff_key( $fetched, [ 'source_url' => true ] );
			case 'remote-servers':
				$permission = Access_Policy::check_management();
				return is_wp_error( $permission ) ? $permission : [ 'servers' => Remote_File_Service::servers() ];
			case 'remote-browse':
				$listing = ( new Remote_File_Service() )->browse( (string) $input['server_id'], (string) ( $input['path'] ?? '/' ) );
				if ( ! is_wp_error( $listing ) ) {
					foreach ( $listing['items'] as &$item ) {
						$item['address'] = Remote_File_Service::address( sanitize_key( (string) $input['server_id'] ), rtrim( $listing['path'], '/' ) . '/' . $item['name'] ) . ( 'dir' === $item['type'] ? '/' : '' );
					}
					unset( $item );
				}
				return $listing;
			case 'upload-abort':
				return $files->abort( $input['file_id'] );
			case 'file-preview':
				return $files->preview( $input['file_id'], $input['parser_options'] ?? [] );
			case 'file-get':
				return isset( $input['job_id'] ) ? $files->from_job( $input['job_id'] ) : $files->get( $input['file_id'] );
			case 'job-list':
				return $jobs->listing( $input );
			case 'job-get':
				return $jobs->get( $input['job_id'] );
			case 'job-get-logs':
				return $jobs->logs( $input['job_id'], $input['level'] ?? 'all', $input['limit'] ?? 100, $input['offset'] ?? 0 );
			case 'job-get-result':
				return $jobs->result( $input['job_id'], $input['limit'] ?? 100, $input['offset'] ?? 0 );
			case 'job-pause':
				return $jobs->stop( $input['job_id'], 'pause' );
			case 'job-cancel':
				return $jobs->stop( $input['job_id'], 'cancel' );
			case 'job-resume':
				return $jobs->continue_job( $input['job_id'], 'resume' );
			case 'job-retry':
				return $jobs->continue_job( $input['job_id'], 'retry' );
			case 'job-restart':
				return $jobs->restart( $input['job_id'], $input['request_id'] );
			case 'job-rename':
				return $jobs->rename( $input['job_id'], $input['name'] );
			case 'job-delete':
				return $jobs->delete( $input['job_id'] );
			case 'job-cleanup':
				return $jobs->cleanup( $input['days'] );
		}
		return $this->error( 'unknown_workflow', 404 );
	}
	/**
	 * Describe the same importers/exporters used for execution.
	 *
	 * @param string $type Free canonical type.
	 * @param int    $meta_offset Offset into the custom meta catalogue.
	 * @return array|\WP_Error
	 */
	public function content_schema( $type, $meta_offset = 0 ) {
		if ( ! in_array( $type, array_keys( ( new Free_Content_Provider() )->get_definitions() ), true ) ) {
			return $this->error( 'content_type_forbidden', 403 ); }
		$permission = Access_Policy::check_content_type( $type, 'export' );
		if ( is_wp_error( $permission ) ) {
			return $permission; }
		$exporter = Exporter_Factory::get_exporter( $type );
		if ( is_wp_error( $exporter ) ) {
			return $exporter; }
		$import = null;
		if ( ! in_array( $type, [ 'urls', 'form_entries' ], true ) ) {
			$importer = Importer_Factory::get_importer( $type );
			if ( is_wp_error( $importer ) ) {
				return $importer; }
			$import = [
				'required_fields' => $importer->get_required_fields(),
				'optional_fields' => $importer->get_optional_fields(),
				'option_help'     => $importer->get_supported_options(),
				'input_schema'    => Workflow_Schemas::import_input( true ),
			];
		}
		if ( in_array( $type, [ 'post', 'page' ], true ) ) {
			$import['optional_fields'][] = Elementor_Fields::DOCUMENT_FIELD;
		}
		$export_fields = $exporter->get_available_fields();
		if ( in_array( $type, [ 'post', 'page' ], true ) ) {
			$base_fields   = array_merge( $import['required_fields'], $import['optional_fields'], $exporter->get_default_fields(), [ 'ID', 'guid', 'elementor_document', '_wp_page_template' ] );
			$export_fields = array_values(
				array_filter(
					$export_fields,
					static function ( $field ) use ( $base_fields ) {
						return in_array( $field, $base_fields, true ) || 0 === strpos( $field, '_yoast_wpseo_' ) || 0 === strpos( $field, 'rank_math_' ) || 0 === strpos( $field, '_elementor_' );
					}
				)
			);
		}
		return [
			'content_type' => $type,
			'import'       => $import,
			'export'       => [
				'fields'         => $export_fields,
				'default_fields' => $exporter->get_default_fields(),
				'filter_help'    => $exporter->get_supported_filters(),
				'custom_meta'    => $this->meta_fields( $type, $meta_offset ),
				'taxonomies'     => $this->taxonomies( $type ),
				'input_schema'   => Workflow_Schemas::export_input( true ),
			],
			'integrations' => [
				'acf'       => ! in_array( $type, [ 'urls', 'form_entries' ], true ) ? ACF_Fields::get_fields_for_content_type( $type ) : [],
				'yoast'     => in_array( $type, [ 'post', 'page' ], true ) && defined( 'WPSEO_VERSION' ) ? Seo_Fields::get_yoast_fields( $type ) : [],
				'rank_math' => in_array( $type, [ 'post', 'page' ], true ) && Seo_Fields::is_rank_math_active() ? Seo_Fields::get_rank_math_fields( $type ) : [],
				'elementor' => in_array( $type, [ 'post', 'page' ], true ) && Elementor_Fields::is_active() ? Elementor_Fields::get_fields() : [],
			],
		];
	}
	/**
	 * Discover relation fields of a Free post type, not standalone term operations.
	 *
	 * @param string $type Content type.
	 * @return array
	 */
	private function taxonomies( $type ) {
		if ( ! in_array( $type, [ 'post', 'page', 'media' ], true ) ) {
			return []; }
		$result = [];
		foreach ( get_object_taxonomies( 'media' === $type ? 'attachment' : $type, 'objects' ) as $taxonomy ) {
			$result[] = [
				'name'         => $taxonomy->name,
				'label'        => $taxonomy->label,
				'hierarchical' => (bool) $taxonomy->hierarchical,
				'field'        => 'taxonomy_' . $taxonomy->name,
			];
		}
		return $result;
	}

	/**
	 * Read only custom meta names, scoped to Free entities and paginated.
	 *
	 * @param string $type Content type.
	 * @param int    $offset Catalogue offset.
	 * @return array
	 */
	private function meta_fields( $type, $offset ) {
		global $wpdb;
		$offset = max( 0, (int) $offset );
		if ( in_array( $type, [ 'urls', 'form_entries' ], true ) ) {
			return [
				'fields'      => [],
				'next_offset' => null,
			]; }
		if ( 'comment' === $type ) {
			$sql = $wpdb->prepare( "SELECT DISTINCT m.meta_key FROM %i m INNER JOIN %i c ON m.comment_id = c.comment_ID INNER JOIN %i p ON c.comment_post_ID = p.ID WHERE p.post_type IN ('post','page') AND c.comment_type IN ('','comment','pingback','trackback') AND m.meta_key NOT LIKE %s ORDER BY m.meta_key LIMIT 101 OFFSET %d", $wpdb->commentmeta, $wpdb->comments, $wpdb->posts, $wpdb->esc_like( '_' ) . '%', $offset );
		} else {
			$sql = $wpdb->prepare( 'SELECT DISTINCT m.meta_key FROM %i m INNER JOIN %i p ON m.post_id = p.ID WHERE p.post_type = %s AND m.meta_key NOT LIKE %s ORDER BY m.meta_key LIMIT 101 OFFSET %d', $wpdb->postmeta, $wpdb->posts, 'media' === $type ? 'attachment' : $type, $wpdb->esc_like( '_' ) . '%', $offset );
		}
		$keys = $wpdb->get_col( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- Both complete queries are prepared above; discover names without content values.
		return [
			'fields'      => array_map(
				static function ( $key ) {
					return 'meta_' . $key;
				},
				array_slice( $keys, 0, 100 )
			),
			'next_offset' => count( $keys ) > 100 ? $offset + 100 : null,
		];
	}

	/**
	 * Prepare a reviewable file/schema/mapping envelope without importing content.
	 *
	 * @param array $input Import type and file ID.
	 * @return array|\WP_Error
	 */
	private function prepare( array $input ) {
		$schema = $this->content_schema( $input['import_type'] );
		if ( is_wp_error( $schema ) || empty( $schema['import'] ) ) {
			return is_wp_error( $schema ) ? $schema : $this->error( 'unsupported_operation', 400 ); }
		$file = ( new File_Service() )->preview( $input['file_id'], $input['parser_options'] ?? [] );
		if ( is_wp_error( $file ) ) {
			return $file; }
		$fields  = array_merge( $schema['import']['required_fields'], $schema['import']['optional_fields'] );
		$fields  = array_merge( $fields, self::acf_columns( $schema ) );
		$mapping = [];
		$headers = array_map( 'strval', (array) $file['headers'] );
		// Column names of WordPress exports (WXR) and the WordPress REST API, as Auto Map in the
		// admin reads them: WP-CLI and AI agent imports of such files mapped nothing and failed.
		$aliases = [
			'title'            => 'post_title',
			'title.rendered'   => 'post_title',
			'content:encoded'  => 'post_content',
			'content.rendered' => 'post_content',
			'excerpt:encoded'  => 'post_excerpt',
			'excerpt.rendered' => 'post_excerpt',
			'wp:post_name'     => 'post_name',
			'wp:post_date'     => 'post_date',
			'wp:post_date_gmt' => 'post_date_gmt',
			'wp:status'        => 'post_status',
			'wp:menu_order'    => 'menu_order',
			'wp:attachment_url' => 'file_url',
			'dc:creator'       => 'author_login',
		];
		$mapped_targets = [];
		foreach ( $headers as $index => $header ) {
			$target = in_array( $header, $fields, true ) ? $header : $this->relation_target( $input['import_type'], $header );
			$alias  = $aliases[ strtolower( $header ) ] ?? '';
			// WordPress exports: "Meta: key" custom fields, "category"/"tag" and "category:<taxonomy>"
			// term lists (the same targets Auto Map creates in the admin).
			$dynamic = '';
			if ( preg_match( '/^\x{FEFF}?meta:\s*(\S+)$/iu', $header, $meta_match ) ) {
				$dynamic = 'meta_' . $meta_match[1];
			} elseif ( in_array( $input['import_type'], [ 'post', 'page' ], true ) && 'category' === $header && taxonomy_exists( 'category' ) && in_array( 'taxonomies', $fields, true ) ) {
				$dynamic = 'taxonomy_category';
			} elseif ( in_array( $input['import_type'], [ 'post', 'page' ], true ) && 'tag' === $header && taxonomy_exists( 'post_tag' ) && in_array( 'taxonomies', $fields, true ) ) {
				$dynamic = 'taxonomy_post_tag';
			} elseif ( preg_match( '/^category:([a-z0-9_-]+)$/', $header, $tax_match ) && taxonomy_exists( $tax_match[1] ) && in_array( 'taxonomies', $fields, true ) ) {
				$dynamic = 'taxonomy_' . $tax_match[1];
			}
			if ( '' === $alias && '' !== $dynamic && ! in_array( $dynamic, $headers, true ) ) {
				$alias    = $dynamic;
				$fields[] = $dynamic;
			}
			if ( '' === $target && '' !== $alias && in_array( $alias, $fields, true ) && ! in_array( $alias, $headers, true ) && ! isset( $mapped_targets[ $alias ] ) ) {
				$target = $alias;
			}
			if ( '' !== $target ) {
				$mapped_targets[ $target ] = true;
			}
			if ( '' === $target ) {
				continue; }
			// A readable copy ("category_names") is used only when the file has no portable column for it.
			if ( $target !== $header && in_array( $target, $headers, true ) ) {
				continue; }
			$mapping[] = [
				'source_index' => $index,
				'target_field' => $target,
			];
		}
		return [
			'file'    => $file,
			'schema'  => $schema,
			'mapping' => $mapping,
		];
	}
	/**
	 * The "acf_<field>" columns that exports write for the ACF fields of this content type, so
	 * import-prepare (WP-CLI, AI agents) maps them like Auto Map in the admin does. Without them the
	 * raw post_meta column brought the other site's attachment, post and term IDs into ACF fields.
	 *
	 * @param array $schema Content schema with its "acf" field list.
	 * @return string[]
	 */
	public static function acf_columns( $schema ) {
		$columns = [];
		foreach ( (array) ( $schema['integrations']['acf'] ?? $schema['acf'] ?? [] ) as $field ) {
			$name = is_array( $field ) ? (string) ( $field['name'] ?? '' ) : '';
			if ( '' !== $name ) {
				$columns[] = 'acf_' . $name;
			}
		}
		return $columns;
	}

	/**
	 * Map term and custom field columns that the post importer reads but the field list does not name.
	 *
	 * @param string $type Import type.
	 * @param string $header File column.
	 * @return string Target field, or '' when the column is not one of them.
	 */
	private function relation_target( $type, $header ) {
		if ( ! in_array( $type, [ 'post', 'page' ], true ) ) {
			return ''; }
		$aliases = [
			'categories'     => 'categories',
			'category_names' => 'categories',
			'tags'           => 'tags',
			'tag_names'      => 'tags',
		];
		if ( 'post' === $type && isset( $aliases[ $header ] ) ) {
			return $aliases[ $header ]; }
		if ( 0 === strpos( $header, 'taxonomy_' ) && is_object_in_taxonomy( $type, substr( $header, 9 ) ) ) {
			return $header; }
		if ( 0 === strpos( $header, 'meta_' ) && strlen( $header ) > 5 ) {
			return $header; }
		return '';
	}
	/**
	 * Decode a bounded JSON chunk into the File Service streaming interface.
	 *
	 * @param array $input File ID, chunk index and base64 bytes.
	 * @return array|\WP_Error
	 */
	private function chunk( array $input ) {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decode a bounded data transport chunk; never execute bytes.
		$bytes = base64_decode( $input['data_base64'], true );
		if ( false === $bytes || '' === $bytes || strlen( $bytes ) > 1048576 ) {
			return $this->error( 'invalid_upload_chunk', 400 ); }
		// phpcs:disable WordPress.WP.AlternativeFunctions -- php://temp stream is required by the shared file service.
		$stream = fopen( 'php://temp', 'w+b' );
		if ( ! $stream ) {
			return $this->error( 'file_write', 500 ); }
		try {
			if ( strlen( $bytes ) !== fwrite( $stream, $bytes ) || ! rewind( $stream ) ) {
				return $this->error( 'file_write', 500 ); }
			return ( new File_Service() )->write_chunk( $input['file_id'], $input['index'], $stream );
		} finally {
			fclose( $stream ); }
	}
	/**
	 * Stable adapter error.
	 *
	 * @param string $code Code suffix.
	 * @param int    $status HTTP status.
	 * @return \WP_Error
	 */
	private function error( $code, $status ) {
		return new \WP_Error( 'rsl_ie_' . $code, __( 'The workflow request is not available or is invalid.', 'import-export-by-rockstarlab' ), [ 'status' => $status ] );
	}
}
