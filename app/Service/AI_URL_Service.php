<?php
/**
 * AI URL import application service.
 *
 * @package RockStarLab\ImportExport\Service
 */

namespace RockStarLab\ImportExport\Service;

use RockStarLab\ImportExport\Abilities\Access_Policy;
use RockStarLab\ImportExport\Abilities\AI_URL_Catalog;
use RockStarLab\ImportExport\Abilities\Execution_Context;
use RockStarLab\ImportExport\Helper\AI_Content_Extractor;
use RockStarLab\ImportExport\Helper\OpenAI_API_Key;
defined( 'ABSPATH' ) || exit;
/** Shared authorization and immutable background requests for AI URL imports. */
class AI_URL_Service {
	/**
	 * Current execution state.
	 *
	 * @var int Active processor job; cannot be supplied by an API caller.
	 */
	private static $running = 0;
	/**
	 * Handle the authorized operation.
	 *
	 * @param int $id Job ID.
	 * @return bool
	 */
	public static function running( $id ) {
		return self::$running === (int) $id; }
	/**
	 * Handle the authorized operation.
	 *
	 * @param object|null $job Record.
	 * @return bool
	 */
	public static function supports( $job ) {
		return $job && 'import' === $job->type && 'ai_url' === $job->data_type && 1 === ( json_decode( (string) $job->parameters, true )['ai_url_version'] ?? null ); }
	/**
	 * Handle the authorized operation.
	 *
	 * @param string $code Failure code.
	 * @param int    $status HTTP status.
	 * @return \WP_Error
	 */
	private function error( $code, $status = 400 ) {
		return new \WP_Error( 'rsl_ie_ai_' . $code, __( 'AI URL import could not complete this operation. Check the selected URLs, destination, fields and current permissions.', 'import-export-by-rockstarlab' ), [ 'status' => $status ] ); }
	/**
	 * Handle the authorized operation.
	 *
	 * @param string $type Actual post type.
	 * @param string $edition Trusted namespace.
	 * @return mixed
	 */
	public function authorize( $type, $edition = 'free' ) {
		$access = Access_Policy::check_management();
		if ( is_wp_error( $access ) ) {
			return $access; }
		if ( ! in_array( $type, [ 'post', 'page' ], true ) ) {
			return 'pro' === $edition ? apply_filters( 'rsl_ie_ai_url_target', $this->error( 'target_forbidden', 403 ), $type ) : $this->error( 'target_forbidden', 403 );
		}
		return Access_Policy::check_content_type( $type, 'ai_url_import' );
	}
	/**
	 * Handle the authorized operation.
	 *
	 * @param string $name Operation.
	 * @param array  $input Input.
	 * @param string $edition Trusted namespace.
	 * @return mixed
	 */
	public function execute( $name, array $input = [], $edition = 'free' ) {
		$access = Access_Policy::check_management();
		if ( is_wp_error( $access ) ) {
			return $access; }
		$definition = AI_URL_Catalog::definitions()[ $name ] ?? null;
		if ( ! $definition ) {
			return $this->error( 'operation' ); }
		$valid = rest_validate_value_from_schema( $input, $definition['input'], 'input' );
		if ( is_wp_error( $valid ) ) {
			$valid->add_data( [ 'status' => 400 ] );
			return $valid; }
		foreach ( $definition['input']['properties'] ?? [] as $key => $schema ) {
			if ( ! array_key_exists( $key, $input ) && array_key_exists( 'default', $schema ) ) {
				$input[ $key ] = $schema['default']; }
		}
		if ( 'ai-url-settings' === $name ) {
			$types = [];
			foreach ( get_post_types( [ 'public' => true ], 'objects' ) as $type ) {
				if ( 'attachment' !== $type->name && ! is_wp_error( $this->authorize( $type->name, $edition ) ) ) {
					$types[] = [
						'value' => $type->name,
						'label' => $type->label,
					]; }
			}
			return [
				'post_types'       => $types,
				'extraction_modes' => [ 'auto', 'alternate' ],
				'ai_available'     => OpenAI_API_Key::has_api_key(),
				'acf_available'    => function_exists( 'acf_get_field_groups' ),
				'max_urls'         => 50,
				'default_status'   => 'draft',
			];
		}
		if ( 'ai-url-fields' === $name ) {
			return $this->fields( $input['post_type'], $edition ); }
		if ( 'ai-url-import-status' === $name || 'ai-url-import-cancel' === $name ) {
			return $this->control( $input['job_id'], 'ai-url-import-cancel' === $name ); }
		if ( 'ai-url-prepare' === $name || 'ai-url-import-start' === $name ) {
			$request = $input['request_id'] ?? '';
			unset( $input['request_id'] );
			$prepared = $this->prepare( $input, $edition );
			if ( is_wp_error( $prepared ) || 'ai-url-prepare' === $name ) {
				return $prepared; }
			$context = Execution_Context::capture();
			if ( is_wp_error( $context ) ) {
				return $context; }
			$params = [
				'ai_url_version'    => 1,
				'edition'           => $edition,
				'input'             => $input,
				'execution_context' => $context->to_storage(),
			] + $prepared['parameters'];
			return Job_Service::enqueue(
				[
					'type'        => 'import',
					'data_type'   => 'ai_url',
					'file_format' => 'url',
					'file_path'   => '',
					'status'      => 'pending',
					'user_id'     => get_current_user_id(),
					'total_items' => count( $input['urls'] ),
					'parameters'  => wp_json_encode( $params ),
					'settings'    => wp_json_encode(
						[
							'writing'    => false,
							'error_code' => '',
						]
					),
				],
				hash( 'sha256', 'ai-url:' . $edition . ':' . $request ),
				function () {
					return OpenAI_API_Key::has_api_key() ? true : $this->error( 'provider_unavailable', 409 );
				}
			);
		}
		if ( ! OpenAI_API_Key::has_api_key() ) {
			return $this->error( 'provider_unavailable', 409 ); }
		$extractor = new AI_Content_Extractor();
		if ( 'ai-test-connection' === $name ) {
			$result = $extractor->test_connection();
			return is_wp_error( $result ) ? $this->error( 'connection_failed', 502 ) : [
				'model'   => $result,
				'message' => __( 'AI connection successful.', 'import-export-by-rockstarlab' ),
			]; }
		if ( ! wp_http_validate_url( $input['url'] ) ) {
			return $this->error( 'url' ); }
		$result = $extractor->extract_from_url( $input['url'], 0, $input['extraction_mode'] );
		if ( is_wp_error( $result ) ) {
			return $this->error( 'extraction_failed', 502 );
		}
		$result['featured_image'] = (string) ( $result['featured_image'] ?? '' );
		return $result;
	}
	/**
	 * Handle the authorized operation.
	 *
	 * @param string $type Destination.
	 * @param string $edition Namespace.
	 * @return mixed
	 */
	public function fields( $type, $edition = 'free' ) {
		$access = $this->authorize( $type, $edition );
		if ( is_wp_error( $access ) ) {
			return $access; }
		$fields = [];
		if ( function_exists( 'acf_get_field_groups' ) ) {
			foreach ( acf_get_field_groups( [ 'post_type' => $type ] ) as $group ) {
				foreach ( (array) acf_get_fields( $group ) as $field ) {
					$formatted = $this->field( $field, $group['title'] );
					if ( $formatted ) {
						$fields[] = $formatted; }
				}
			}
		}
		return [ 'fields' => $fields ];
	}
	/**
	 * Handle the authorized operation.
	 *
	 * @param array  $field ACF definition.
	 * @param string $group Group label.
	 * @param array  $path Ancestors.
	 * @return array|null
	 */
	private function field( array $field, $group, array $path = [] ) {
		if ( count( $path ) > 8 ) {
			return null; }
		$path[]   = [
			'key'  => $field['key'],
			'type' => $field['type'],
		];
		$children = [];
		foreach ( $field['sub_fields'] ?? [] as $child ) {
			$formatted = $this->field( $child, $group, $path );
			if ( $formatted ) {
				$children[] = $formatted; }
		}
		if ( 'flexible_content' === $field['type'] ) {
			foreach ( $field['layouts'] ?? [] as $layout ) {
				$branch                                   = $path;
				$branch[ count( $branch ) - 1 ]['layout'] = $layout['name'];
				foreach ( $layout['sub_fields'] ?? [] as $child ) {
					$formatted = $this->field( $child, $group, $branch );
					if ( $formatted ) {
						$children[] = $formatted; }
				}
			}
		}
		$allowed = in_array( $field['type'], [ 'text', 'textarea', 'wysiwyg' ], true );
		return $allowed || $children ? [
			'key'        => $field['key'],
			'name'       => $field['name'],
			'label'      => $field['label'],
			'type'       => $field['type'],
			'group'      => $group,
			'level'      => count( $path ) - 1,
			'sub_fields' => $children,
			'is_allowed' => $allowed,
			'path'       => $path,
		] : null;
	}
	/**
	 * Handle the authorized operation.
	 *
	 * @param array  $fields Field tree.
	 * @param string $selector Name or key.
	 * @return array
	 */
	private function selected_fields( array $fields, $selector ) {
		$matches = [];
		foreach ( $fields as $field ) {
			if ( $field['is_allowed'] && in_array( $selector, [ $field['key'], $field['name'] ], true ) ) {
				$matches[] = $field; }
			$matches = array_merge( $matches, $this->selected_fields( $field['sub_fields'], $selector ) );
		}
		return $matches;
	}
	/**
	 * Handle the authorized operation.
	 *
	 * @param array  $input Normalized options.
	 * @param string $edition Namespace.
	 * @return mixed
	 */
	public function prepare( array $input, $edition = 'free' ) {
		$valid = rest_validate_value_from_schema( $input, AI_URL_Catalog::options() );
		if ( is_wp_error( $valid ) ) {
			return $valid; }
		$access = $this->authorize( $input['post_type'], $edition );
		if ( is_wp_error( $access ) ) {
			return $access; }
		$type = get_post_type_object( $input['post_type'] );
		if ( ! $type || ! current_user_can( $type->cap->create_posts ) || ( in_array( $input['post_status'], [ 'publish', 'private' ], true ) && ! current_user_can( $type->cap->publish_posts ) ) || ( $input['import_images'] && ! current_user_can( 'upload_files' ) ) ) {
			return $this->error( 'destination_forbidden', 403 ); }
		foreach ( $input['urls'] as $url ) {
			if ( ! wp_http_validate_url( $url ) ) {
				return $this->error( 'url' ); }
		}
		$field = 'post_content';
		if ( 'custom_field' === $input['content_field'] ) {
			$field = $input['custom_field_name'];
			if ( ! preg_match( '/^[a-zA-Z][a-zA-Z0-9_-]{0,199}$/D', $field ) || is_protected_meta( $field, 'post' ) || in_array( $field, [ 'post_content', 'post_title', 'post_status', 'post_type' ], true ) || ( get_registered_meta_keys( 'post', $type->name )[ $field ]['auth_callback'] ?? false ) ) {
				return $this->error( 'field' ); }
		} elseif ( 'acf_field' === $input['content_field'] ) {
			$fields  = $this->fields( $type->name, $edition );
			$matches = $this->selected_fields( $fields['fields'], $input['acf_field'] );
			if ( 1 !== count( $matches ) ) {
				return $this->error( 'acf_field' ); }
			$field = $matches[0]['key'];
		}
		$params                        = $input;
		$params['content_field']       = $field;
		$params['content_destination'] = $input['content_field'];
		$params['acf_path']            = $matches[0]['path'] ?? [];
		return [
			'valid'      => true,
			'total'      => count( $input['urls'] ),
			'parameters' => $params,
		];
	}
	/**
	 * Handle the authorized operation.
	 *
	 * @param int  $id Job.
	 * @param bool $cancel Cancel flag.
	 * @return mixed
	 */
	private function control( $id, $cancel = false ) {
		$job = ( new Job_Service() )->record( $id );
		if ( is_wp_error( $job ) ) {
			return $job; }
		if ( ! self::supports( $job ) ) {
			return $this->error( 'wrong_job' ); }
		if ( $cancel && ! in_array( $job->status, [ 'completed', 'failed', 'cancelled' ], true ) ) {
			$lock = Job_Lock::acquire( $id );
			if ( ! $lock || is_wp_error( $lock ) ) {
				return $this->error( 'busy', 409 ); }
			try {
				$job = rsl_ie()->Model->job->find( $id );
				if ( in_array( $job->status, [ 'pending', 'processing' ], true ) ) {
					$saved = rsl_ie()->Model->job->update_status( $id, 'cancelled' );
					if ( false === $saved || is_wp_error( $saved ) ) {
						return $this->error( 'cancel_failed', 500 );
					}
					wp_clear_scheduled_hook( 'rsl_ie_run_job', [ (int) $id ] );
				}
			} finally {
				Job_Lock::release( $lock ); }
			$job = rsl_ie()->Model->job->find( $id );
		}
		$params = json_decode( (string) $job->parameters, true );
		$state  = json_decode( (string) $job->settings, true );
		return [
			'job_id'             => (int) $job->id,
			'status'             => $job->status,
			'processed'          => (int) $job->processed_items,
			'total'              => (int) $job->total_items,
			'success_count'      => (int) $job->success_items,
			'failed_count'       => (int) $job->failed_items,
			'progress'           => (float) $job->progress,
			'error_code'         => $state['error_code'] ?? '',
			'uncertain'          => ! empty( $state['writing'] ),
			'import_log'         => $params['import_log'] ?? [],
			'poll_after_seconds' => 2,
		];
	}
	/**
	 * Handle the authorized operation.
	 *
	 * @param int $id Scheduled job.
	 * @return mixed
	 */
	public function process_background_batch( $id ) {
		$job = rsl_ie()->Model->job->find( $id );
		if ( ! self::supports( $job ) || ! in_array( $job->status, [ 'pending', 'processing' ], true ) ) {
			return false; }
		$params   = json_decode( $job->parameters, true );
		$identity = $params['execution_context'] ?? [];
		$context  = Execution_Context::from_storage( $identity['user_id'] ?? 0, $identity['blog_id'] ?? 0 );
		if ( is_wp_error( $context ) || ( $identity['user_id'] ?? null ) !== (int) $job->user_id || ( $identity['blog_id'] ?? null ) !== get_current_blog_id() ) {
			return $this->fail( $job, 'invalid_context' ); }
		$result = $context->run(
			function () use ( $id ) {
				$lock = Job_Lock::acquire( $id );
				if ( ! $lock || is_wp_error( $lock ) ) {
					return false; }
				try {
					$job = rsl_ie()->Model->job->find( $id );
					if ( ! in_array( $job->status, [ 'pending', 'processing' ], true ) ) {
						return false; }
					$params = json_decode( $job->parameters, true );
					$state  = json_decode( (string) $job->settings, true );
					if ( ! empty( $state['writing'] ) ) {
						return $this->fail( $job, 'interrupted' ); }
					$prepared = $this->prepare( $params['input'], $params['edition'] );
					if ( is_wp_error( $prepared ) || ! OpenAI_API_Key::has_api_key() ) {
						return $this->fail( $job, 'preflight_failed' ); }
					foreach ( $prepared['parameters'] as $key => $value ) {
						if ( ! array_key_exists( $key, $params ) || $value !== $params[ $key ] ) {
							return $this->fail( $job, 'input_changed' ); }
					}
					$state['writing'] = true;
					$saved            = rsl_ie()->Model->job->update( $id, [ 'settings' => wp_json_encode( $state ) ] );
					if ( is_wp_error( $saved ) || false === $saved ) {
						return false; }
					$before_processed = (int) $job->processed_items;
					self::$running    = (int) $id;
					$result           = ( new \RockStarLab\ImportExport\Model\Queue\AI_URL_Import_Processor() )->process( $id );
					$job              = rsl_ie()->Model->job->find( $id );
					if ( 'failed' === $job->status || is_wp_error( $result ) || (int) $job->processed_items !== $before_processed + 1 ) {
						return $this->fail( $job, 'batch_failed' ); }
					$state['writing'] = false;
					$saved            = rsl_ie()->Model->job->update( $id, [ 'settings' => wp_json_encode( $state ) ] );
					if ( is_wp_error( $saved ) || false === $saved ) {
						return $this->fail( $job, 'checkpoint_failed' ); }
					return $result;
				} catch ( \Throwable $error ) {
					return $this->fail( rsl_ie()->Model->job->find( $id ), 'batch_failed' ); } finally {
					self::$running = 0;
					Job_Lock::release( $lock ); }
			}
		);
		return is_wp_error( $result ) ? $this->fail( rsl_ie()->Model->job->find( $id ), 'permission_changed' ) : $result;
	}
	/**
	 * Handle the authorized operation.
	 *
	 * @param object $job Record.
	 * @param string $code Safe failure.
	 * @return false
	 */
	private function fail( $job, $code ) {
		$state               = (array) json_decode( (string) $job->settings, true );
		$state['error_code'] = 'rsl_ie_ai_' . $code;
		rsl_ie()->Model->job->update(
			$job->id,
			[
				'status'       => 'failed',
				'settings'     => wp_json_encode( $state ),
				'completed_at' => current_time( 'mysql' ),
			]
		);
		return false;
	}
}
