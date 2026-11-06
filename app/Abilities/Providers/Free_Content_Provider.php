<?php
/**
 * Canonical Free content definitions and entity checks.
 *
 * @package RockStarLab\ImportExport\Abilities\Providers
 */

namespace RockStarLab\ImportExport\Abilities\Providers;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps a Free request scoped to actual Free entities after normalization.
 */
class Free_Content_Provider {

	/**
	 * Return canonical types and accepted legacy aliases.
	 *
	 * @return array
	 */
	public function get_definitions() {
		return [
			'post'    => [ 'posts' ],
			'page'    => [ 'pages' ],
			'comment' => [ 'comments' ],
			'media'   => [ 'attachment', 'attachments' ],
			'urls'    => [],
		];
	}

	/**
	 * Resolve a free type; null lets an installed provider handle another type.
	 *
	 * @param string $type Requested canonical type or alias.
	 * @return string|null
	 */
	public function normalize( $type ) {
		foreach ( $this->get_definitions() as $canonical => $aliases ) {
			if ( $type === $canonical || in_array( $type, $aliases, true ) ) {
				return $canonical;
			}
		}
		return null;
	}

	/**
	 * Check normalized options, selected IDs and parsed records before execution.
	 *
	 * @param string $type      Canonical free type.
	 * @param string $operation Server-selected operation.
	 * @param array  $context   Parsed operation context, never raw serialized data.
	 * @return true|\WP_Error
	 */
	public function check( $type, $operation, array $context ) {
		foreach ( [ 'options', 'records', 'selected_ids' ] as $key ) {
			if ( isset( $context[ $key ] ) && ! is_array( $context[ $key ] ) ) {
				return $this->error( 'rsl_ie_invalid_context' );
			}
		}
		$operations = [ 'import', 'export', 'sync_push', 'sync_pull' ];
		if ( in_array( $type, [ 'post', 'page' ], true ) ) {
			$operations[] = 'ai_url_import';
		}
		if ( 'urls' === $type ) {
			$operations = [ 'export' ];
		}
		if ( ! in_array( $operation, $operations, true ) ) {
			return $this->error( 'rsl_ie_unsupported_operation' );
		}
		$options    = isset( $context['options'] ) && is_array( $context['options'] ) ? $context['options'] : [];
		$records    = isset( $context['records'] ) && is_array( $context['records'] ) ? $context['records'] : [];
		$records[]  = $options;
		$post_types = 'media' === $type ? [ 'attachment' ] : [ $type ];
		if ( in_array( $type, [ 'comment', 'urls' ], true ) ) {
			$post_types = [ 'post', 'page' ];
		}

		foreach ( $records as $record ) {
			if ( ! is_array( $record ) ) {
				return $this->error( 'rsl_ie_invalid_record' );
			}
			// The legacy ID duplicate strategy resolves a destination post directly.
			// A source ID must not widen a Free import to an existing premium object.
			if ( 'import' === $operation && in_array( $type, [ 'post', 'page' ], true ) && 'ID' === ( $options['duplicate_check'] ?? $options['unique_field'] ?? '' ) && ! empty( $record['ID'] ) ) {
				$existing = get_post( (int) $record['ID'] );
				if ( $existing && ( $type !== $existing->post_type || ! current_user_can( 'edit_post', $existing->ID ) ) ) {
					return $this->error( 'rsl_ie_content_type_forbidden', __( 'An ID in the file belongs to another content type. When matching by ID, every ID must be an item of the content type you are importing. Nothing was imported.', 'import-export-by-rockstarlab' ) );
				}
			}
			foreach ( [ 'post_type', 'custom_post_type' ] as $key ) {
				if ( isset( $record[ $key ] ) && '' !== $record[ $key ] && ! in_array( $record[ $key ], $post_types, true ) ) {
					return $this->error( 'rsl_ie_content_type_forbidden' );
				}
			}
			if ( 'comment' === $type ) {
				$comment_type = $record['comment_type'] ?? $record['default_type'] ?? '';
				if ( ! in_array( $comment_type, [ '', 'comment', 'pingback', 'trackback' ], true ) ) {
					return $this->error( 'rsl_ie_content_type_forbidden' );
				}
				if ( ! empty( $context['record_ids_are_local'] ) && ! empty( $record['comment_post_ID'] ) && ! in_array( get_post_type( (int) $record['comment_post_ID'] ), $post_types, true ) ) {
					return $this->error( 'rsl_ie_content_type_forbidden' );
				}
			}
		}

		foreach ( (array) ( $context['selected_ids'] ?? [] ) as $object_id ) {
			if ( 'comment' === $type ) {
				$comment = get_comment( (int) $object_id );
				if ( ! $comment || ! in_array( $comment->comment_type, [ '', 'comment', 'pingback', 'trackback' ], true ) ) {
					return $this->error( 'rsl_ie_content_type_forbidden' );
				}
				$object_id = $comment->comment_post_ID;
			}
			if ( (int) $object_id < 1 || ! in_array( get_post_type( (int) $object_id ), $post_types, true ) ) {
				return $this->error( 'rsl_ie_content_type_forbidden' );
			}
		}
		return true;
	}

	/**
	 * Return a stable policy failure without leaking object data.
	 *
	 * @param string $code    Error code.
	 * @param string $message Optional message for a specific failure.
	 * @return \WP_Error
	 */
	private function error( $code, $message = '' ) {
		return new \WP_Error( $code, '' !== $message ? $message : __( 'The requested operation contains a content type outside its allowed scope.', 'import-export-by-rockstarlab' ), [ 'status' => 403 ] );
	}
}
