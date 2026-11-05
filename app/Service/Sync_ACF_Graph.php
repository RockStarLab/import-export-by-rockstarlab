<?php
/**
 * Two-phase ACF post graphs for owned migration parents.
 *
 * @package RockStarLab\ImportExport\Service
 */

namespace RockStarLab\ImportExport\Service;

use RockStarLab\ImportExport\Abilities\Access_Policy;
use RockStarLab\ImportExport\Abilities\Sync_Peer_Policy;
use RockStarLab\ImportExport\Helper\Remote_API;

defined( 'ABSPATH' ) || exit;

/** Only the active parent supplies transfer fingerprints; peers never run jobs. */
class Sync_ACF_Graph {
	/** Only attachment-only batches use the large media metadata path. */
	public static function media_records( $posts ) {
		if ( ! is_array( $posts ) || ! $posts || count( $posts ) > 50 ) {
			return false;
		}
		foreach ( $posts as $post ) {
			if ( ! is_array( $post ) || 'attachment' !== ( $post['post_type'] ?? null ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Delegate nested product fields to the licensed provider.
	 *
	 * @param string $operation Phase.
	 * @param array  $data Records.
	 * @param array  $context Selection or maps.
	 * @return mixed
	 */
	public static function products( $operation, array $data = [], array $context = [] ) {
		$result = apply_filters( 'rsl_ie_sync_acf_product_graph', null, $operation, $data, $context );
		return null === $result ? Migration_Sync_Maps::error() : $result;
	}

	/**
	 * Whether the current child owns nested product snapshots.
	 *
	 * @var bool
	 */
	private static $product_fields = false;

	/**
	 * Delegate account graphs only to the licensed provider.
	 *
	 * @param string $operation Phase.
	 * @param array  $data Phase data.
	 * @param array  $context Phase maps or options.
	 * @return mixed
	 */
	public static function accounts( $operation, array $data = [], array $context = [] ) {
		$result = apply_filters( 'rsl_ie_sync_acf_account_graph', null, $operation, $data, $context );
		return null === $result ? Migration_Sync_Maps::error() : $result;
	}

	/**
	 * Delegate term graphs to the licensed provider.
	 *
	 * @param string $operation Phase.
	 * @param array  $data Phase data.
	 * @param array  $context Selection or maps.
	 * @return mixed
	 */
	public static function terms( $operation, array $data = [], array $context = [] ) {
		$result = apply_filters( 'rsl_ie_sync_acf_term_graph', null, $operation, $data, $context );
		return null === $result ? Migration_Sync_Maps::error() : $result;
	}

	/**
	 * Whether a snapshot belongs to a term-owned graph.
	 *
	 * @param string $type Snapshot type.
	 * @return bool
	 */
	public static function term_type( $type ) {
		return is_string( $type ) && 1 === preg_match( '/^@term-(posts|taxonomies|all):[a-z0-9_-]+$/D', $type );
	}

	/**
	 * Reject aliased term owners across all frozen batches.
	 *
	 * @param array $snapshots Frozen source batches.
	 * @param array $maps Confirmed destination identities.
	 * @return true|\WP_Error
	 */
	public static function term_targets( array $snapshots, array $maps ) {
		$seen = [];
		foreach ( Migration_Snapshot_Store::iterate( $snapshots ) as $records ) {
			if ( is_wp_error( $records ) ) {
				return $records; }
			foreach ( $records as $record ) {
				if ( ! self::term_type( $record['post_type'] ) ) {
					continue; }
				$taxonomy = explode( ':', $record['post_type'], 2 )[1];
				$key      = 'taxonomy:' . $taxonomy . ':' . $record['ID'];
				$target   = $maps[ $key ] ?? 0;
				if ( ! is_int( $target ) || $target < 1 || isset( $seen[ $taxonomy ][ $target ] ) ) {
					return Migration_Sync_Maps::error(); }
				$seen[ $taxonomy ][ $target ] = true;
			}
		}
		return true;
	}

	/**
	 * Current term graph family, or false.
	 *
	 * @var string|false
	 */
	private static $term_posts = false;

	/**
	 * Source fingerprints scoped to the current child invocation.
	 *
	 * @var array|null
	 */
	private static $active = null;

	/**
	 * Whether the active child transfers attachment ACF media fields.
	 *
	 * @var bool
	 */
	private static $media_values = false;

	/**
	 * The current account graph family, or false for a post/media child.
	 *
	 * @var string|false
	 */
	private static $account_posts = false;

	/**
	 * Present graph preparation and finalization without changing worker stages.
	 *
	 * @param array $state Parent checkpoint.
	 * @return string
	 */
	public static function stage( array $state ) {
		if ( 'syncing' !== $state['stage'] || ! isset( $state['acf_graph'] ) ) {
			return $state['stage'];
		}
		$count = 0;
		foreach ( $state['requests'] as $request ) {
			if ( ( ! empty( $state['acf_graph']['term_posts'] ) && in_array( $request['params']['operation'], [ 'sync-terms-push', 'sync-terms-pull' ], true ) ) || ( ! empty( $state['acf_graph']['account_posts'] ) && in_array( $request['params']['operation'], [ 'sync-users-push', 'sync-users-pull' ], true ) ) || in_array( $request['params']['operation'], [ 'sync-push', 'sync-pull' ], true ) || ( ! empty( $state['acf_graph']['media_fields'] ) && in_array( $request['params']['operation'], [ 'sync-media-push', 'sync-media-pull' ], true ) ) ) {
				++$count;
			}
		}
		if ( count( $state['acf_graph']['snapshots'] ) < $count ) {
			return 'preparing_acf_references';
		}
		return $state['index'] >= count( $state['requests'] ) ? 'finalizing_acf_references' : $state['stage'];
	}

	/**
	 * Execute a child with its frozen source reference fingerprints.
	 *
	 * @param array    $snapshot Frozen reference records.
	 * @param callable $callback Owned child execution.
	 * @return mixed
	 */
	public static function during( array $snapshot, $callback ) {
		$before_products      = self::$product_fields;
		self::$product_fields = '@product' === ( $snapshot[0]['post_type'] ?? '' );
		$before               = self::$active;
		$before_terms         = self::$term_posts;
		self::$term_posts     = self::term_type( $snapshot[0]['post_type'] ?? '' ) ? $snapshot[0]['post_type'] : false;
		$before_accounts      = self::$account_posts;
		self::$account_posts  = in_array( $snapshot[0]['post_type'] ?? '', [ '@account', '@account-terms', '@account-all', '@account-full' ], true ) ? $snapshot[0]['post_type'] : false;
		$before_media         = self::$media_values;
		self::$media_values   = in_array( 'attachment', array_column( $snapshot, 'post_type' ), true );
		self::$active         = self::hashes( $snapshot );
		try {
			return call_user_func( $callback );
		} finally {
			self::$product_fields = $before_products;
			self::$active         = $before;
			self::$term_posts     = $before_terms;
			self::$account_posts  = $before_accounts;
			self::$media_values   = $before_media;
		}
	}

	/**
	 * Supply the bounded PULL source fingerprint request.
	 *
	 * @return array
	 */
	public static function request() {
		if ( self::$term_posts ) {
			return [
				'acf_term_graph_hashes'  => self::$active,
				'acf_term_graph_version' => 1,
			] + ( 0 === strpos( self::$term_posts, '@term-posts:' ) ? [] : [ 'acf_term_graph_type' => self::$term_posts ] );
		}
		return null === self::$active ? [] : ( self::$account_posts ? [ 'acf_account_graph_hashes' => self::$active ] + ( '@account' === self::$account_posts ? [] : [ 'acf_account_graph_type' => self::$account_posts ] ) : [ 'acf_graph_hashes' => self::$active ] ) + ( self::$media_values ? [ 'acf_attachment_media_version' => 1 ] : [] ) + ( self::$product_fields ? [ 'acf_product_graph_version' => 1 ] : [] );
	}

	/**
	 * Strip source reference values before native import and verify frozen data.
	 *
	 * @param array      $posts Actual exported payloads.
	 * @param array|null $hashes Explicit peer source fingerprints.
	 * @param bool|null  $products Explicit nested product protocol.
	 * @return array|\WP_Error
	 */
	public static function strip( array $posts, $hashes = null, $products = null ) {
		$hashes = $hashes ?? self::$active;
		if ( null === $hashes ) {
			return $posts;
		}
		if ( $products ?? self::$product_fields ) {
			return self::products( 'strip', $posts, [ 'hashes' => $hashes ] );
		}
		$snapshot = self::snapshot( $posts );
		if ( is_wp_error( $snapshot ) || ! self::same_hashes( $hashes, self::hashes( $snapshot ) ) ) {
			return Migration_Sync_Maps::error();
		}
		foreach ( $posts as &$post ) {
			foreach ( $snapshot as $record ) {
				if ( (int) $post['ID'] !== $record['ID'] ) {
					continue;
				}
				foreach ( $record['acf_post_fields'] as $name => $field ) {
					unset( $post['meta'][ $name ], $post['meta'][ '_' . $name ] );
				}
			}
			$post['acf_graph_hash'] = $hashes[ $post['ID'] ];
		}
		unset( $post );
		return $posts;
	}

	/**
	 * Require the source to acknowledge stripping before PULL imports anything.
	 *
	 * @param array $posts Incoming PULL records.
	 * @return true|\WP_Error
	 */
	public static function received( array $posts ) {
		if ( null === self::$active ) {
			return true;
		}
		if ( self::$product_fields && is_wp_error( self::products( 'received', $posts ) ) ) {
			return Migration_Sync_Maps::error();
		}
		$hashes = [];
		foreach ( $posts as $post ) {
			$hashes[ $post['ID'] ] = $post['acf_graph_hash'] ?? '';
		}
		return self::same_hashes( self::$active, $hashes ) ? true : Migration_Sync_Maps::error();
	}

	/**
	 * Build stable per-record fingerprints from reference-only evidence.
	 *
	 * @param array $posts Frozen reference records.
	 * @return array
	 */
	public static function hashes( array $posts ) {
		$result = [];
		foreach ( $posts as $post ) {
			$result[ $post['ID'] ] = hash( 'sha256', wp_json_encode( $post ) );
		}
		ksort( $result );
		return $result;
	}

	/**
	 * Compare validated hashes independent of JSON object property order.
	 *
	 * @param mixed $expected Expected fingerprints.
	 * @param array $actual Observed fingerprints.
	 * @return bool
	 */
	public static function same_hashes( $expected, array $actual ) {
		if ( ! is_array( $expected ) || ! $expected || count( $expected ) > 50 ) {
			return false;
		}
		ksort( $expected );
		ksort( $actual );
		return $expected === $actual;
	}

	/**
	 * Read only actual authorized reference metadata from a selected source batch.
	 *
	 * @param array  $ids Selected IDs.
	 * @param string $type Expected post type.
	 * @return array|\WP_Error
	 */
	public static function read( array $ids, $type ) {
		if ( '@product' === $type ) {
			return self::products( 'read', $ids );
		}
		if ( self::term_type( $type ) ) {
			return self::terms( 'read', $ids, [ 'type' => $type ] );
		}
		if ( in_array( $type, [ '@account', '@account-terms', '@account-all', '@account-full' ], true ) ) {
			return self::accounts( 'read', $ids, [ 'type' => $type ] ); }
		if ( ! $ids || count( $ids ) > 50 || count( array_unique( $ids ) ) !== count( $ids ) ) {
			return Migration_Sync_Maps::error();
		}
		$posts = [];
		foreach ( $ids as $id ) {
			if ( ! is_int( $id ) || $id < 1 || is_wp_error( Sync_Peer_Policy::destination( $id, $type ) ) ) {
				return Migration_Sync_Maps::error();
			}
			$meta = [];
			foreach ( get_post_meta( $id ) as $key => $values ) {
				$meta[ $key ] = maybe_unserialize( $values[0] );
			}
			$posts[] = [
				'ID'        => $id,
				'post_type' => $type,
				'meta'      => $meta,
			];
		}
		$result = self::snapshot( $posts );
		return $result;
	}

	/**
	 * Extend post snapshots with attachment byte evidence when required.
	 *
	 * @param array $posts Raw source posts.
	 * @return array|\WP_Error
	 */
	public static function snapshot( array $posts ) {
		$attachments = array_values(
			array_filter(
				$posts,
				static function ( $post ) {
					return 'attachment' === $post['post_type'];
				}
			)
		);
		$media       = $attachments ? Sync_ACF_Media::snapshot( $attachments ) : [];
		if ( is_wp_error( $media ) ) {
			return $media;
		}
		$media  = array_column( $media, null, 'ID' );
		$result = Sync_ACF_Posts::snapshot( $posts );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		foreach ( $result as &$post ) {
			if ( 'attachment' === $post['post_type'] ) {
				$proof = ( new Media_Library_Sync_Service() )->reference_proof( $post['ID'], true );
				if ( is_wp_error( $proof ) ) {
					return $proof;
				}
				$post['acf_object_file_proof'] = $proof;
				$post['acf_attachment_media']  = $media[ $post['ID'] ];
			}
		}
		unset( $post );
		return Sync_ACF_Attachments::snapshot( $result );
	}

	/**
	 * Validate reference evidence against the entire selected post graph.
	 *
	 * @param array $posts Frozen batch.
	 * @param array $selected Full source ID to type selection.
	 * @return true|\WP_Error
	 */
	/** Keep only identities required by an ordinary post/media snapshot batch. */
	public static function batch_context( array $posts, array $context, $selection = false, $keys_only = false ) {
		$needed = [];
		foreach ( $posts as $post ) {
			$type = $post['post_type'] ?? '';
			// Premium graph providers own their nested reference representation.
			if ( ! is_string( $type ) || '' === $type ) {
				return $context; }
			if ( '@' === $type[0] ) {
				return apply_filters( 'rsl_ie_sync_acf_graph_batch_context', $context, $posts, $selection, $keys_only ); }
			$identities = [ $post['ID'] ?? 0 => $type ];
			foreach ( (array) ( $post['acf_post_types'] ?? [] ) as $id => $reference_type ) {
				$identities[ $id ] = $reference_type; }
			foreach ( (array) ( $post['acf_attachment_media']['acf_media_values'] ?? [] ) as $ids ) {
				foreach ( (array) $ids as $id ) {
					$identities[ $id ] = 'attachment'; }
			}
			foreach ( $identities as $id => $reference_type ) {
				if ( ! is_numeric( $id ) || ! is_string( $reference_type ) ) {
					continue; }
				$needed[ $selection ? (int) $id : 'post_type:' . $reference_type . ':' . $id ] = true;
			}
		}
		return $keys_only ? $needed : array_intersect_key( $context, $needed );
	}

	public static function check( array $posts, array $selected ) {
		$selected = self::batch_context( $posts, $selected, true );
		if ( '@product' === ( $posts[0]['post_type'] ?? '' ) ) {
			return self::products( 'check', $posts, $selected );
		}
		if ( self::term_type( $posts[0]['post_type'] ?? '' ) ) {
			return self::terms( 'check', $posts, $selected );
		}
		if ( array_intersect( [ '@account', '@account-terms', '@account-all', '@account-full' ], array_column( $posts, 'post_type' ) ) ) {
			return self::accounts( 'check', $posts, $selected ); }
		$selected = array_filter(
			$selected,
			static function ( $key ) {
				return is_int( $key );
			},
			ARRAY_FILTER_USE_KEY
		);
		$result   = Sync_ACF_Posts::run( 'prepare', $posts, [ 'selected' => $selected ] );
		$result   = is_wp_error( $result ) ? $result : Sync_ACF_Attachments::verify( $posts );
		$result   = is_wp_error( $result ) ? $result : Sync_ACF_Media_Fields::files( $posts, null, true );
		return is_wp_error( $result ) ? $result : Sync_ACF_Media_Graph::check( $posts, $selected );
	}

	/**
	 * Require read-only support from every owning graph provider.
	 *
	 * @param array $posts Snapshot records.
	 * @return int Supported verification version, or zero.
	 */
	private static function verify_version( array $posts ) {
		foreach ( $posts as $post ) {
			$type = $post['post_type'] ?? '';
			if ( '@product' === $type && is_wp_error( self::products( 'authorize' ) ) ) {
				return 0;
			}
			if ( self::term_type( $type ) && is_wp_error( self::terms( 'authorize_verify' ) ) ) {
				return 0;
			}
			if ( in_array( $type, [ '@account', '@account-terms', '@account-all', '@account-full' ], true ) && is_wp_error( self::accounts( 'authorize_verify' ) ) ) {
				return 0;
			}
		}
		return 1;
	}

	/**
	 * Assign a frozen batch from confirmed source-to-destination typed maps.
	 *
	 * @param array $posts Frozen batch.
	 * @param array $maps Complete confirmed maps.
	 * @param bool  $read_only Verify without writing.
	 * @return true|\WP_Error
	 */
	public static function finish( array $posts, array $maps, $read_only = false ) {
		$maps = self::batch_context( $posts, $maps );
		if ( '@product' === ( $posts[0]['post_type'] ?? '' ) ) {
			return self::products( $read_only ? 'verify' : 'finalize', $posts, $maps );
		}
		if ( self::term_type( $posts[0]['post_type'] ?? '' ) ) {
			return self::terms( $read_only ? 'verify' : 'finalize', $posts, $maps );
		}
		if ( array_intersect( [ '@account', '@account-terms', '@account-all', '@account-full' ], array_column( $posts, 'post_type' ) ) ) {
			return self::accounts( $read_only ? 'verify' : 'finalize', $posts, $maps ); }
		$maps  = array_filter(
			$maps,
			static function ( $key ) {
				return 0 === strpos( $key, 'post_type:' );
			},
			ARRAY_FILTER_USE_KEY
		);
		$files = Sync_ACF_Attachments::verify( $posts, $maps );
		$files = is_wp_error( $files ) ? $files : Sync_ACF_Media_Fields::files( $posts, $maps, true );
		if ( is_wp_error( $files ) ) {
			return $files;
		}
		$media = Sync_ACF_Media_Graph::prepare( $posts, $maps );
		if ( is_wp_error( $media ) ) {
			return $media;
		}
		$attachment_maps = $maps;
		$targets         = [];
		foreach ( $posts as $post ) {
			$key                    = 'post_type:' . $post['post_type'] . ':' . $post['ID'];
			$targets[ $post['ID'] ] = $maps[ $key ] ?? 0;
			unset( $maps[ $key ] );
		}
		$prepared = Sync_ACF_Posts::run( 'prepare', $posts, [ 'mapping' => $maps ] );
		$result   = is_wp_error( $prepared ) ? $prepared : Sync_ACF_Posts::run( $read_only ? 'verify' : 'finalize', $prepared, [ 'posts' => $targets ] );
		$result   = is_wp_error( $result ) ? $result : Sync_ACF_Media::finalize( $media, $targets, $read_only, true );
		$result   = is_wp_error( $result ) ? $result : Sync_ACF_Attachments::verify( $posts, $attachment_maps );
		return is_wp_error( $result ) ? $result : Sync_ACF_Media_Fields::files( $posts, $attachment_maps, true );
	}

	/**
	 * Use one authenticated, bounded peer protocol for read/check/finalize.
	 *
	 * @param array $site Validated connection.
	 * @param array $input Operation and bounded payload.
	 * @return array|\WP_Error
	 */
	public static function remote( array $site, array $input ) {
		if ( is_array( $input['posts'] ?? null ) && is_array( $input['maps'] ?? null ) ) {
			$input['maps'] = self::batch_context( $input['posts'], $input['maps'], 'check' === ( $input['operation'] ?? '' ) );
		}
		$response = Remote_API::post(
			$site['remote_url'],
			'migration-acf-posts',
			[
				'timeout' => 60,
				'headers' => [
					'Authorization' => 'Bearer ' . $site['api_key'],
					'Content-Type'  => 'application/json',
				],
				'body'    => wp_json_encode( $input + [ 'version' => 1 ] ),
			]
		);
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return Migration_Sync_Maps::error();
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ( ! empty( $input['verify_version'] ) || 'verify' === ( $input['operation'] ?? '' ) ) && ( 1 !== ( $data['verify_version'] ?? null ) || ( $data['receipt'] ?? '' ) !== hash( 'sha256', wp_json_encode( $input + [ 'version' => 1 ] ) ) ) ) {
			return Migration_Sync_Maps::error();
		}
		$records    = array_merge( (array) ( $input['posts'] ?? [] ), (array) ( $data['posts'] ?? [] ) );
		$term_types = array_merge( [ $input['type'] ?? '' ], array_column( $records, 'post_type' ) );
		if ( in_array( '@product', $term_types, true ) && ( 1 !== ( $data['product_fields_version'] ?? null ) || ( $data['receipt'] ?? '' ) !== hash( 'sha256', wp_json_encode( $input + [ 'version' => 1 ] ) ) ) ) {
			return Migration_Sync_Maps::error();
		}
		foreach ( $term_types as $type ) {
			if ( self::term_type( $type ) && 0 !== strpos( $type, '@term-posts:' ) && 1 !== ( $data['term_taxonomies_version'] ?? null ) ) {
				return Migration_Sync_Maps::error(); }
		}
		if ( ( self::term_type( $input['type'] ?? '' ) || array_filter( array_column( $records, 'post_type' ), [ self::class, 'term_type' ] ) ) && 1 !== ( $data['term_posts_version'] ?? null ) ) {
			return Migration_Sync_Maps::error();
		}
		if ( in_array( '@account-full', $term_types, true ) && 1 !== ( $data['account_users_graph_version'] ?? null ) ) {
			return Migration_Sync_Maps::error(); }
		if ( ( in_array( $input['type'] ?? '', [ '@account-terms', '@account-all', '@account-full' ], true ) || array_intersect( [ '@account-terms', '@account-all', '@account-full' ], array_column( $records, 'post_type' ) ) ) && 1 !== ( $data['account_terms_version'] ?? null ) ) {
			return Migration_Sync_Maps::error(); }
		if ( ( '@account' === ( $input['type'] ?? '' ) || in_array( '@account', array_column( $records, 'post_type' ), true ) ) && 1 !== ( $data['account_posts_version'] ?? null ) ) {
			return Migration_Sync_Maps::error(); }
		if ( in_array( 'attachment', array_column( $records, 'post_type' ), true ) && ( 1 !== ( $data['media_fields_version'] ?? null ) || 1 !== ( $data['media_values_version'] ?? null ) ) ) {
			return Migration_Sync_Maps::error();
		}
		if ( ( Sync_ACF_Attachments::required( $input['posts'] ?? [] ) || Sync_ACF_Attachments::required( (array) ( $data['posts'] ?? [] ) ) ) && 1 !== ( $data['attachments_version'] ?? null ) ) {
			return Migration_Sync_Maps::error();
		}
		return 1 === ( $data['version'] ?? null ) && true === ( $data['success'] ?? null ) ? $data : Migration_Sync_Maps::error();
	}

	/**
	 * Authenticated peer entry; validates bounds before invoking shared services.
	 *
	 * @param \WP_REST_Request $request Authenticated request.
	 * @return array|\WP_Error
	 */
	public static function peer( $request ) {
		$access = Access_Policy::check_management();
		if ( is_wp_error( $access ) ) {
			return $access;
		}
		$input = $request->get_json_params();
		if ( ! is_array( $input ) || 1 !== ( $input['version'] ?? null ) ) {
			return Migration_Sync_Maps::error();
		}
		$operation = $input['operation'] ?? '';
		if ( 'read' === $operation ) {
			$result = is_array( $input['ids'] ?? null ) && is_string( $input['type'] ?? null ) ? self::read( $input['ids'], $input['type'] ) : Migration_Sync_Maps::error();
			return is_wp_error( $result ) ? $result : [
				'product_fields_version'      => is_wp_error( self::products( 'authorize' ) ) ? 0 : 1,
				'term_posts_version'          => is_wp_error( self::terms( 'authorize' ) ) ? 0 : 1,
				'term_taxonomies_version'     => is_wp_error( self::terms( 'authorize_taxonomies' ) ) ? 0 : 1,
				'account_posts_version'       => is_wp_error( self::accounts( 'authorize' ) ) ? 0 : 1,
				'account_users_graph_version' => is_wp_error( self::accounts( 'authorize_users' ) ) ? 0 : 1,
				'account_terms_version'       => is_wp_error( self::accounts( 'authorize_terms' ) ) ? 0 : 1,
				'version'                     => 1,
				'attachments_version'         => 1,
				'media_fields_version'        => 1,
				'media_values_version'        => 1,
				'success'                     => true,
				'verify_version'              => self::verify_version( $result ),
				'receipt'                     => hash( 'sha256', wp_json_encode( $input ) ),
				'posts'                       => $result,
			];
		}
		$posts = $input['posts'] ?? null;
		$maps  = $input['maps'] ?? null;
		if ( ! is_array( $posts ) || ! $posts || count( $posts ) > 50 || ! is_array( $maps ) ) {
			return Migration_Sync_Maps::error();
		}
		$seen = [];
		foreach ( $posts as $post ) {
			if ( ! is_array( $post ) || ! is_int( $post['ID'] ?? null ) || $post['ID'] < 1 || isset( $seen[ $post['ID'] ] ) || ! is_string( $post['post_type'] ?? null ) || ! is_array( $post['meta'] ?? null ) ) {
				return Migration_Sync_Maps::error();
			}
			$seen[ $post['ID'] ] = true;
			if ( '@product' === $post['post_type'] ) {
				if ( is_wp_error( self::products( 'authorize' ) ) ) {
					return Migration_Sync_Maps::error();
				}
				continue;
			}
			if ( self::term_type( $post['post_type'] ) ) {
				if ( is_wp_error( self::terms( 'authorize' ) ) ) {
					return Migration_Sync_Maps::error(); }
				continue;
			}
			if ( is_wp_error( in_array( $post['post_type'], [ '@account', '@account-terms', '@account-all', '@account-full' ], true ) ? self::accounts( 'authorize' ) : Sync_Peer_Policy::post( $post['post_type'], 'sync_pull' ) ) ) {
				return Migration_Sync_Maps::error();
			}
		}
		if ( 'check' === $operation ) {
			foreach ( $maps as $id => $type ) {
				if ( is_string( $id ) && 0 === strpos( $id, 'user:user:' ) ) {
					if ( is_wp_error( ( 0 === strpos( $posts[0]['post_type'], '@account' ) ? self::accounts( 'selection_user', [], [ $id => $type ] ) : self::products( 'selection_user', [], [ $id => $type ] ) ) ) ) {
						return Migration_Sync_Maps::error();
					}
					continue;
				}
				if ( is_string( $id ) && 0 === strpos( $id, 'taxonomy:' ) ) {
					if ( is_wp_error( 0 === strpos( $posts[0]['post_type'], '@account' ) ? self::accounts( 'selection', [], [ $id => $type ] ) : self::terms( 'selection', [], [ $id => $type ] ) ) ) {
						return Migration_Sync_Maps::error(); }
					continue;
				}
				if ( ! preg_match( '/^[1-9][0-9]*$/D', (string) $id ) || ! is_string( $type ) || is_wp_error( Sync_Peer_Policy::post( $type, 'sync_pull' ) ) ) {
					return Migration_Sync_Maps::error();
				}
			}
			$result = self::check( $posts, $maps );
		} elseif ( in_array( $operation, [ 'finalize', 'verify' ], true ) ) {
			$result = self::finish( $posts, $maps, 'verify' === $operation );
		} else {
			return Migration_Sync_Maps::error();
		}
		return is_wp_error( $result ) ? $result : [
			'product_fields_version'      => is_wp_error( self::products( 'authorize' ) ) ? 0 : 1,
			'term_posts_version'          => is_wp_error( self::terms( 'authorize' ) ) ? 0 : 1,
			'term_taxonomies_version'     => is_wp_error( self::terms( 'authorize_taxonomies' ) ) ? 0 : 1,
			'account_posts_version'       => is_wp_error( self::accounts( 'authorize' ) ) ? 0 : 1,
			'account_users_graph_version' => is_wp_error( self::accounts( 'authorize_users' ) ) ? 0 : 1,
			'account_terms_version'       => is_wp_error( self::accounts( 'authorize_terms' ) ) ? 0 : 1,
			'version'                     => 1,
			'attachments_version'         => 1,
			'media_fields_version'        => 1,
			'media_values_version'        => 1,
			'success'                     => true,
			'verify_version'              => self::verify_version( $posts ),
			'receipt'                     => hash( 'sha256', wp_json_encode( $input ) ),
		];
	}
}
