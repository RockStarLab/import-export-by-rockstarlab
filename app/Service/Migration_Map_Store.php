<?php
/**
 * Durable confirmed identities for large migrations.
 *
 * @package RockStarLab\ImportExport\Service
 */

namespace RockStarLab\ImportExport\Service;

defined( 'ABSPATH' ) || exit;

/** The checkpoint count publishes an append-only, committed prefix. */
class Migration_Map_Store {
	/**
	 * Read a bounded page of the committed prefix in stable insertion order.
	 *
	 * @param int   $job_id Owning migration.
	 * @param array $state Published checkpoint.
	 * @param int   $offset Zero-based ordinal.
	 * @param int   $limit Maximum entries, at most 500.
	 * @return array|\WP_Error
	 */
	public static function page( $job_id, array $state, $offset = 0, $limit = 100 ) {
		if ( ! is_int( $offset ) || $offset < 0 || ! is_int( $limit ) || $limit < 1 || $limit > 500 ) {
			return Migration_Sync_Maps::error();
		}
		if ( ! isset( $state['map_count'] ) && isset( $state['maps'] ) && ! is_array( $state['maps'] ) ) {
			return Migration_Sync_Maps::error();
		}
		$total = $state['map_count'] ?? count( $state['maps'] ?? [] );
		if ( ! is_int( $total ) || $total < 0 || $offset > $total ) {
			return Migration_Sync_Maps::error();
		}
		$expected = min( $limit, $total - $offset );
		$maps     = [];
		if ( ! isset( $state['map_count'] ) ) {
			$maps = array_slice( $state['maps'] ?? [], $offset, $expected, true );
		} elseif ( $expected ) {
			global $wpdb;
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT ordinal,identity_key,target_id FROM %i WHERE job_id=%d AND ordinal>=%d AND ordinal<%d ORDER BY ordinal LIMIT %d', $wpdb->prefix . 'rsl_ie_migration_maps', $job_id, $offset, $offset + $expected, $limit ), ARRAY_A );
			if ( $wpdb->last_error || count( $rows ) !== $expected ) {
				return Migration_Sync_Maps::error();
			}
			foreach ( $rows as $i => $row ) {
				if ( (int) $row['ordinal'] !== $offset + $i || isset( $maps[ $row['identity_key'] ] ) ) {
					return Migration_Sync_Maps::error();
				}
				$maps[ $row['identity_key'] ] = (int) $row['target_id'];
			}
		}
		foreach ( $maps as $key => $id ) {
			if ( ! self::valid( $key, $id ) ) {
				return Migration_Sync_Maps::error();
			}
		}
		return [
			'maps'        => (object) $maps,
			'offset'      => $offset,
			'next_offset' => $offset + $expected,
			'total'       => $total,
			'complete'    => $offset + $expected === $total,
		];
	}

	/**
	 * Validate the committed prefix while retaining only requested identities.
	 *
	 * @param int   $job_id Owning migration.
	 * @param array $state Published checkpoint.
	 * @param array $keys Required typed identity keys.
	 * @return array|\WP_Error
	 */
	public static function select( $job_id, array $state, array $keys ) {
		$wanted = [];
		foreach ( $keys as $key ) {
			if ( ! self::valid( $key, 1 ) ) {
				return Migration_Sync_Maps::error();
			}
			$wanted[ $key ] = true;
		}
		$maps = [];
		if ( ! isset( $state['map_count'] ) ) {
			if ( ! is_array( $state['maps'] ?? [] ) ) {
				return Migration_Sync_Maps::error();
			}
			$maps = array_intersect_key( $state['maps'] ?? [], $wanted );
		} else {
			$count = $state['map_count'];
			if ( ! is_int( $count ) || $count < 0 ) {
				return Migration_Sync_Maps::error();
			}
			global $wpdb;
			$table = $wpdb->prefix . 'rsl_ie_migration_maps';
			// Unique ordinals plus this count detect gaps without loading unrelated identities.
			$actual = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE job_id=%d AND ordinal>=0 AND ordinal<%d', $table, $job_id, $count ) );
			if ( $wpdb->last_error || (int) $actual !== $count ) {
				return Migration_Sync_Maps::error();
			}
			$selected = [];
			foreach ( array_chunk( array_keys( $wanted ), 500 ) as $keys_page ) {
				$placeholders = implode( ',', array_fill( 0, count( $keys_page ), '%s' ) );
				$args         = array_merge( [ $table, $job_id, $count ], $keys_page );
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Only generated placeholders.
				$rows = $wpdb->get_results( $wpdb->prepare( "SELECT ordinal,identity_key,target_id FROM %i WHERE job_id=%d AND ordinal<%d AND identity_key IN ($placeholders) ORDER BY ordinal", $args ), ARRAY_A );
				if ( $wpdb->last_error ) {
					return Migration_Sync_Maps::error();
				}
				foreach ( $rows as $row ) {
					if ( ! self::valid( $row['identity_key'], (int) $row['target_id'] ) || (int) $row['ordinal'] < 0 ) {
						return Migration_Sync_Maps::error();
					}
					$selected[ (int) $row['ordinal'] ] = $row;
				}
			}
			ksort( $selected );
			foreach ( $selected as $row ) {
				$maps[ $row['identity_key'] ] = (int) $row['target_id'];
			}
		}
		foreach ( $maps as $key => $id ) {
			if ( ! self::valid( $key, $id ) ) {
				return Migration_Sync_Maps::error();
			}
		}
		if ( count( $maps ) !== count( $wanted ) ) {
			return Migration_Sync_Maps::error();
		}
		return $maps;
	}

	/** Existing identities that can conflict with one newly confirmed batch. */
	public static function conflicts( $job_id, array $state, array $maps ) {
		if ( ! isset( $state['map_count'] ) ) {
			return $state['maps'] ?? []; }
		global $wpdb;
		$table  = $wpdb->prefix . 'rsl_ie_migration_maps';
		$result = [];
		foreach ( $maps as $key => $target ) {
			if ( ! self::valid( $key, $target ) ) {
				return Migration_Sync_Maps::error(); }
			$prefix = substr( $key, 0, strrpos( $key, ':' ) + 1 );
			$rows   = $wpdb->get_results( $wpdb->prepare( 'SELECT identity_key,target_id FROM %i WHERE job_id=%d AND ordinal<%d AND ((target_id=%d AND identity_key LIKE %s) OR identity_key=%s) ORDER BY ordinal LIMIT 3', $table, $job_id, $state['map_count'], $target, $wpdb->esc_like( $prefix ) . '%', $key ), ARRAY_A );
			if ( $wpdb->last_error ) {
				return Migration_Sync_Maps::error(); }
			foreach ( $rows as $row ) {
				$result[ $row['identity_key'] ] = (int) $row['target_id']; }
		}
		// Post/term providers use presence of earlier taxonomy maps to opt into uniqueness.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT identity_key,target_id FROM %i WHERE job_id=%d AND ordinal<%d AND identity_key LIKE 'taxonomy:%%' ORDER BY ordinal LIMIT 1", $table, $job_id, $state['map_count'] ), ARRAY_A );
		if ( $wpdb->last_error ) {
			return Migration_Sync_Maps::error(); }
		if ( $row ) {
			$result[ $row['identity_key'] ] = (int) $row['target_id']; }
		return $result;
	}

	/** Append one confirmed child batch without hydrating earlier identities. */
	public static function append( $job_id, array $state, array $maps ) {
		if ( ! isset( $state['map_count'] ) ) {
			$previous = $state['maps'] ?? [];
			foreach ( $maps as $key => $id ) {
				if ( isset( $previous[ $key ] ) && $previous[ $key ] !== $id ) {
					return Migration_Sync_Maps::error();
				}
				$previous[ $key ] = $id;
			}
			ksort( $previous );
			$state['maps'] = $previous;
			return self::checkpoint( $job_id, $state );
		}
		$count = $state['map_count'];
		if ( ! is_int( $count ) || $count < 0 ) {
			return Migration_Sync_Maps::error();
		}
		foreach ( $maps as $key => $id ) {
			if ( ! self::valid( $key, $id ) ) {
				return Migration_Sync_Maps::error();
			}
		}
		global $wpdb;
		$table  = $wpdb->prefix . 'rsl_ie_migration_maps';
		$actual = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE job_id=%d AND ordinal>=0 AND ordinal<%d', $table, $job_id, $count ) );
		if ( $wpdb->last_error || (int) $actual !== $count ) {
			return Migration_Sync_Maps::error(); }
		if ( false === $wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE job_id=%d AND ordinal>=%d', $table, $job_id, $count ) ) ) {
			return Migration_Sync_Maps::error();
		}
		foreach ( array_chunk( $maps, 500, true ) as $page ) {
			$saved = self::append_page( $job_id, $page, $count );
			if ( is_wp_error( $saved ) ) {
				return $saved; }
		}
		$state['map_count'] = $count;
		unset( $state['maps'] );
		return $state;
	}

	/**
	 * Restore committed maps, keeping legacy inline checkpoints readable.
	 *
	 * @param int   $job_id Owning migration.
	 * @param array $state Published checkpoint.
	 * @return array|\WP_Error
	 */
	public static function read( $job_id, array $state ) {
		if ( ! isset( $state['map_count'] ) ) {
			return $state['maps'] ?? [];
		}
		if ( ! is_int( $state['map_count'] ) || $state['map_count'] < 0 ) {
			return Migration_Sync_Maps::error();
		}
		global $wpdb;
		$maps  = [];
		$after = -1;
		do {
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT ordinal,identity_key,target_id FROM %i WHERE job_id=%d AND ordinal>%d AND ordinal<%d ORDER BY ordinal LIMIT 500', $wpdb->prefix . 'rsl_ie_migration_maps', $job_id, $after, $state['map_count'] ), ARRAY_A );
			if ( $wpdb->last_error ) {
				return Migration_Sync_Maps::error();
			}
			foreach ( $rows as $row ) {
				$id = (int) $row['target_id'];
				if ( (int) $row['ordinal'] !== ++$after || isset( $maps[ $row['identity_key'] ] ) || ! self::valid( $row['identity_key'], $id ) ) {
					return Migration_Sync_Maps::error();
				}
				$maps[ $row['identity_key'] ] = $id;
			}
		} while ( count( $rows ) === 500 );
		if ( count( $maps ) !== $state['map_count'] ) {
			return Migration_Sync_Maps::error();
		}
		ksort( $maps );
		return $maps;
	}

	/**
	 * Write new identities before the caller publishes their checkpoint count.
	 *
	 * @param int   $job_id Owning migration, locked by the worker.
	 * @param array $state Hydrated worker state.
	 * @return array|\WP_Error Compact state, or a persistence failure.
	 */
	public static function checkpoint( $job_id, array $state ) {
		if ( ! isset( $state['maps'] ) ) {
			return $state;
		}
		if ( ! is_array( $state['maps'] ) ) {
			return Migration_Sync_Maps::error();
		}
		if ( ! isset( $state['map_count'] ) && count( $state['maps'] ) <= 2500 ) {
			return $state;
		}
		$count = $state['map_count'] ?? 0;
		if ( ! is_int( $count ) || $count < 0 ) {
			return Migration_Sync_Maps::error();
		}
		for ( $offset = 0; $offset < $count; $offset += 500 ) {
			$previous = self::page( $job_id, $state, $offset, 500 );
			if ( is_wp_error( $previous ) ) {
				return $previous;
			}
			foreach ( (array) $previous['maps'] as $key => $id ) {
				if ( ( $state['maps'][ $key ] ?? null ) !== $id ) {
					return Migration_Sync_Maps::error();
				}
			}
		}
		foreach ( $state['maps'] as $key => $id ) {
			if ( ! self::valid( $key, $id ) ) {
				return Migration_Sync_Maps::error();
			}
		}
		global $wpdb;
		$table = $wpdb->prefix . 'rsl_ie_migration_maps';
		// A failed checkpoint may have left an unpublished tail; only that tail is replaceable.
		if ( false === $wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE job_id=%d AND ordinal>=%d', $table, $job_id, $count ) ) ) {
			return Migration_Sync_Maps::error();
		}
		$page = [];
		foreach ( $state['maps'] as $key => $id ) {
			$page[ $key ] = $id;
			if ( count( $page ) === 500 ) {
				$saved = self::append_page( $job_id, $page, $count );
				if ( is_wp_error( $saved ) ) {
					return $saved;
				}
				$page = [];
			}
		}
		if ( $page ) {
			$saved = self::append_page( $job_id, $page, $count );
			if ( is_wp_error( $saved ) ) {
				return $saved;
			}
		}
		unset( $state['maps'] );
		$state['map_count'] = $count;
		return $state;
	}

	/**
	 * Append only unseen identities from one bounded input page.
	 *
	 * @param int   $job_id Owning locked job.
	 * @param array $page At most 500 validated identities.
	 * @param int   $count Next ordinal, advanced after successful writes.
	 * @return true|\WP_Error
	 */
	private static function append_page( $job_id, array $page, &$count ) {
		global $wpdb;
		$table        = $wpdb->prefix . 'rsl_ie_migration_maps';
		$placeholders = implode( ',', array_fill( 0, count( $page ), '%s' ) );
		$args         = array_merge( [ $table, $job_id ], array_keys( $page ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Generated placeholders; arguments include table, job and every key.
		$existing = $wpdb->get_results( $wpdb->prepare( "SELECT identity_key,target_id FROM %i WHERE job_id=%d AND identity_key IN ($placeholders)", $args ), ARRAY_A );
		if ( $wpdb->last_error ) {
			return Migration_Sync_Maps::error();
		}
		foreach ( $existing as $row ) {
			if ( $page[ $row['identity_key'] ] !== (int) $row['target_id'] ) {
				return Migration_Sync_Maps::error();
			}
			unset( $page[ $row['identity_key'] ] );
		}
		if ( ! $page ) {
			return true;
		}
		$args   = [ $table ];
		$values = [];
		$next   = $count;
		foreach ( $page as $key => $id ) {
			$values[] = '(%d,%d,%s,%d)';
			array_push( $args, $job_id, $next++, $key, $id );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Values contains only placeholder tuples; all data is passed to prepare.
		if ( false === $wpdb->query( $wpdb->prepare( 'INSERT INTO %i (job_id,ordinal,identity_key,target_id) VALUES ' . implode( ',', $values ), $args ) ) ) {
			return Migration_Sync_Maps::error();
		}
		$count = $next;
		return true;
	}

	/** Validate the same typed identity format on storage and readback. */
	private static function valid( $key, $id ) {
		return is_string( $key ) && strlen( $key ) <= 191 && preg_match( '/^(post_type|taxonomy|user):[a-z0-9_-]+:([1-9][0-9]*)$/D', $key, $match ) && (string) (int) $match[2] === $match[2] && is_int( $id ) && $id > 0;
	}
}
