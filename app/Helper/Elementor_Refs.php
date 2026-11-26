<?php
/**
 * Posts, terms and users that Elementor designs refer to by ID.
 *
 * Query controls of Elementor Pro widgets (Posts, Portfolio, Loop Grid / Carousel, Search,
 * Sitemap...) keep lists like posts_posts_ids, posts_include_term_ids, posts_include_authors;
 * dynamic tags keep post_id (Internal URL, Post URL...). These IDs belong to the source site:
 * the sender describes each one (post type + slug, taxonomy + slug, login + e-mail), the
 * receiver finds the same object here and renumbers, and drops what it cannot find.
 *
 * @package RockStarLab\ImportExport\Helper
 */

namespace RockStarLab\ImportExport\Helper;

defined( 'ABSPATH' ) || exit;

/** Portable post / term / user references of Elementor data. */
class Elementor_Refs {
	/** Meta key that carries the portable references in exported files. */
	const META_KEY = '_rsl_ie_elementor_refs';

	/** Post meta marking a post whose references still wait for their targets. */
	const PENDING_KEY = '_rsl_ie_elementor_pending_refs';

	/** Posts remembered in this request. @var int[] */
	public static $touched = array();

	/** Dynamic tag settings that hold a post ID. */
	const TAG_POST_KEYS = array( 'post_id' );

	/**
	 * Kind of the IDs stored under a setting key: post, term, user or ''.
	 *
	 * @param string $key Setting key.
	 * @return string
	 */
	private static function kind( $key ) {
		$key = (string) $key;
		if ( preg_match( '/term_ids$/', $key ) ) {
			return 'term';
		}
		if ( preg_match( '/_authors$/', $key ) ) {
			return 'user';
		}
		if ( preg_match( '/(^|_)(posts_ids|exclude_ids|fallback_ids|include_ids)$/', $key ) || 'sitemap_exclude_ids' === $key ) {
			return 'post';
		}
		return '';
	}

	/**
	 * Walk Elementor data and let $visit rewrite every reference list and dynamic tag.
	 *
	 * @param mixed    $data  Data.
	 * @param callable $visit fn( string $kind, array $ids ): array for lists, fn( 'tag', string $tag_value ): string for tags.
	 * @return mixed
	 */
	private static function walk( $data, callable $visit ) {
		if ( ! is_array( $data ) ) {
			return is_string( $data ) && false !== strpos( $data, '[elementor-tag' ) ? call_user_func( $visit, 'tag', $data ) : $data;
		}
		foreach ( $data as $key => $value ) {
			$kind = is_string( $key ) ? self::kind( $key ) : '';
			if ( '' !== $kind && is_array( $value ) && $value && count( array_filter( $value, 'is_numeric' ) ) === count( $value ) ) {
				$data[ $key ] = call_user_func( $visit, $kind, $value );
			} else {
				$data[ $key ] = self::walk( $value, $visit );
			}
		}
		return $data;
	}

	/**
	 * IDs referred to by the data, by kind.
	 *
	 * @param mixed $data Decoded data or JSON.
	 * @return array{post:int[],term:int[],user:int[]}
	 */
	public static function collect( $data ) {
		if ( is_string( $data ) ) {
			$decoded = json_decode( $data, true );
			$data    = is_array( $decoded ) ? $decoded : $data;
		}
		$out = array(
			'post' => array(),
			'term' => array(),
			'user' => array(),
		);
		self::walk(
			$data,
			static function ( $kind, $value ) use ( &$out ) {
				if ( 'tag' === $kind ) {
					foreach ( Elementor_Templates::tags_in( $value ) as $tag ) {
						foreach ( self::TAG_POST_KEYS as $key ) {
							if ( isset( $tag['settings'][ $key ] ) && is_numeric( $tag['settings'][ $key ] ) && (int) $tag['settings'][ $key ] > 0 ) {
								$out['post'][] = (int) $tag['settings'][ $key ];
							}
						}
					}
					return $value;
				}
				foreach ( $value as $id ) {
					$out[ $kind ][] = (int) $id;
				}
				return $value;
			}
		);
		return array_map( static fn( $ids ) => array_values( array_unique( array_filter( $ids ) ) ), $out );
	}

	/**
	 * Portable description of every reference of a post and of its templates (sender side).
	 *
	 * @param int   $post_id   Post ID.
	 * @param array $templates Template records sent with it.
	 * @return array
	 */
	public static function describe_for_post( $post_id, array $templates = array() ) {
		$sources = array( (string) get_post_meta( (int) $post_id, '_elementor_data', true ), get_post_meta( (int) $post_id, '_elementor_page_settings', true ) );
		foreach ( $templates as $record ) {
			$sources[] = (string) ( $record['data'] ?? '' );
			$sources[] = (array) ( $record['page_settings'] ?? array() );
		}
		$ids = array(
			'post' => array(),
			'term' => array(),
			'user' => array(),
		);
		foreach ( $sources as $source ) {
			foreach ( self::collect( $source ) as $kind => $list ) {
				$ids[ $kind ] = array_merge( $ids[ $kind ], $list );
			}
		}
		// Display conditions of an Elementor Pro Theme Builder template (page, category, author IDs).
		foreach ( self::condition_ids( get_post_meta( (int) $post_id, '_elementor_conditions', true ) ) as $kind => $list ) {
			$ids[ $kind ] = array_merge( $ids[ $kind ], $list );
		}
		$out = array();
		foreach ( array_unique( $ids['post'] ) as $id ) {
			$post = get_post( $id );
			if ( $post ) {
				$out['post'][ $id ] = array(
					'type' => $post->post_type,
					'slug' => $post->post_name,
				);
			}
		}
		foreach ( array_unique( $ids['term'] ) as $id ) {
			$term = get_term( $id );
			if ( $term && ! is_wp_error( $term ) ) {
				$out['term'][ $id ] = array(
					'taxonomy' => $term->taxonomy,
					'slug'     => $term->slug,
				);
			}
		}
		foreach ( array_unique( $ids['user'] ) as $id ) {
			$user = get_userdata( $id );
			if ( $user ) {
				$out['user'][ $id ] = array(
					'login' => $user->user_login,
					'email' => $user->user_email,
				);
			}
		}
		return $out;
	}

	/**
	 * Remember references to resolve once every post of the transfer exists (receiver side).
	 *
	 * @param int   $post_id      Local post.
	 * @param array $refs         Portable references.
	 * @param int[] $template_ids Local templates that came with the post.
	 * @return void
	 */
	public static function remember( $post_id, $refs, array $template_ids = array() ) {
		if ( is_string( $refs ) ) {
			$refs = json_decode( $refs, true );
		}
		if ( (int) $post_id <= 0 || ! is_array( $refs ) || ! $refs ) {
			return;
		}
		foreach ( array_merge( array( (int) $post_id ), array_map( 'intval', $template_ids ) ) as $id ) {
			if ( $id > 0 ) {
				self::$touched[] = $id;
				update_post_meta(
					$id,
					self::PENDING_KEY,
					wp_slash(
						array(
							'refs'  => $refs,
							'tries' => 0,
						)
					)
				);
			}
		}
	}

	/**
	 * Find the local object of a portable reference.
	 *
	 * @param string $kind     post|term|user.
	 * @param int    $source   Source ID.
	 * @param array  $info     Portable description.
	 * @param array  $post_map Source post ID => local post ID of the current transfer.
	 * @return int 0 when not found.
	 */
	private static function find( $kind, $source, array $info, array $post_map ) {
		if ( 'post' === $kind ) {
			if ( ! empty( $post_map[ $source ] ) ) {
				return (int) $post_map[ $source ];
			}
			$type = sanitize_key( (string) ( $info['type'] ?? '' ) );
			$slug = sanitize_title( (string) ( $info['slug'] ?? '' ) );
			if ( '' === $type || '' === $slug || ! post_type_exists( $type ) ) {
				return 0;
			}
			$found = get_posts(
				array(
					'post_type'      => $type,
					'name'           => $slug,
					'post_status'    => 'any',
					'posts_per_page' => 1,
					'fields'         => 'ids',
				)
			);
			return $found ? (int) $found[0] : 0;
		}
		if ( 'term' === $kind ) {
			$taxonomy = sanitize_key( (string) ( $info['taxonomy'] ?? '' ) );
			$term     = taxonomy_exists( $taxonomy ) ? get_term_by( 'slug', sanitize_title( (string) ( $info['slug'] ?? '' ) ), $taxonomy ) : false;
			return $term ? (int) $term->term_id : 0;
		}
		$user = ! empty( $info['login'] ) ? get_user_by( 'login', (string) $info['login'] ) : false;
		if ( ! $user && ! empty( $info['email'] ) ) {
			$user = get_user_by( 'email', (string) $info['email'] );
		}
		return $user ? (int) $user->ID : 0;
	}

	/**
	 * Renumber the references of every post waiting for them.
	 *
	 * @param array $post_map Source post ID => local post ID of the current transfer.
	 * @param bool  $final    Drop what is still not found (otherwise retried, at most three times).
	 * @param int[] $only     Only these posts (empty: every waiting post).
	 * @return int Posts updated.
	 */
	public static function resolve_pending( array $post_map = array(), $final = false, array $only = array() ) {
		global $wpdb;
		$ids  = $only ? array_map( 'intval', $only ) : $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s", self::PENDING_KEY ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Small marker set written here.
		$done = 0;
		foreach ( array_map( 'intval', (array) $ids ) as $post_id ) {
			$pending = get_post_meta( $post_id, self::PENDING_KEY, true );
			if ( ! is_array( $pending ) || empty( $pending['refs'] ) || ! get_post( $post_id ) ) {
				delete_post_meta( $post_id, self::PENDING_KEY );
				continue;
			}
			$tries = (int) ( $pending['tries'] ?? 0 ) + 1;
			$last  = $final || $tries >= 3;
			$maps  = array();
			$left  = array();
			foreach ( array( 'post', 'term', 'user' ) as $kind ) {
				foreach ( (array) ( $pending['refs'][ $kind ] ?? array() ) as $source => $info ) {
					$local = self::find( $kind, (int) $source, (array) $info, $post_map );
					if ( $local > 0 ) {
						$maps[ $kind ][ (int) $source ] = $local;
					} elseif ( ! $last ) {
						$left[ $kind ][ $source ] = $info;
					} else {
						$maps[ $kind ][ (int) $source ] = 0; // Not on this site: removed.
					}
				}
			}
			self::apply( $post_id, $maps );
			if ( $left ) {
				update_post_meta(
					$post_id,
					self::PENDING_KEY,
					wp_slash(
						array(
							'refs'  => $left,
							'tries' => $tries,
						)
					)
				);
			} else {
				delete_post_meta( $post_id, self::PENDING_KEY );
			}
			++$done;
		}
		return $done;
	}

	/**
	 * Renumber the references stored on one post (0 = remove).
	 *
	 * @param int   $post_id Post ID.
	 * @param array $maps    kind => [ source ID => local ID ].
	 * @return void
	 */
	private static function apply( $post_id, array $maps ) {
		if ( ! $maps ) {
			return;
		}
		$rewrite = static function ( $data ) use ( $maps ) {
			return self::walk(
				$data,
				static function ( $kind, $value ) use ( $maps ) {
					if ( 'tag' === $kind ) {
						return Elementor_Templates::map_tags(
							$value,
							static function ( array $settings ) use ( $maps ) {
								foreach ( self::TAG_POST_KEYS as $key ) {
									if ( isset( $settings[ $key ] ) && is_numeric( $settings[ $key ] ) && isset( $maps['post'][ (int) $settings[ $key ] ] ) ) {
										$local            = (int) $maps['post'][ (int) $settings[ $key ] ];
										$settings[ $key ] = $local > 0 ? (string) $local : '';
									}
								}
								return $settings;
							}
						);
					}
					$out = array();
					foreach ( $value as $id ) {
						if ( ! isset( $maps[ $kind ][ (int) $id ] ) ) {
							$out[] = $id; // Already a local ID (renumbered earlier) or unknown here.
						} elseif ( $maps[ $kind ][ (int) $id ] > 0 ) {
							$out[] = is_int( $id ) ? (int) $maps[ $kind ][ (int) $id ] : (string) $maps[ $kind ][ (int) $id ];
						}
					}
					return $out;
				}
			);
		};
		$json    = (string) get_post_meta( (int) $post_id, '_elementor_data', true );
		$data    = json_decode( $json, true );
		if ( is_array( $data ) ) {
			$new = wp_json_encode( $rewrite( $data ) );
			if ( $new && $new !== $json ) {
				update_post_meta( (int) $post_id, '_elementor_data', wp_slash( $new ) );
				delete_post_meta( (int) $post_id, '_elementor_css' );
				delete_post_meta( (int) $post_id, '_elementor_element_cache' );
			}
		}
		$settings = get_post_meta( (int) $post_id, '_elementor_page_settings', true );
		if ( is_array( $settings ) ) {
			$new = $rewrite( $settings );
			if ( $new !== $settings ) {
				update_post_meta( (int) $post_id, '_elementor_page_settings', $new );
			}
		}
		$conditions = get_post_meta( (int) $post_id, '_elementor_conditions', true );
		if ( is_array( $conditions ) && $conditions ) {
			$new = array();
			foreach ( $conditions as $condition ) {
				$kind = self::condition_kind( (string) $condition );
				$id   = $kind ? (int) substr( (string) $condition, strrpos( (string) $condition, '/' ) + 1 ) : 0;
				if ( $kind && isset( $maps[ $kind ][ $id ] ) ) {
					if ( $maps[ $kind ][ $id ] <= 0 ) {
						continue; // The page, term or author is not on this site: drop the condition.
					}
					$condition = substr( (string) $condition, 0, strrpos( (string) $condition, '/' ) + 1 ) . (int) $maps[ $kind ][ $id ];
				}
				$new[] = $condition;
			}
			if ( $new !== $conditions ) {
				update_post_meta( (int) $post_id, '_elementor_conditions', $new );
			}
		}
	}

	/**
	 * What a Theme Builder display condition points at ("include/singular/page/12" → post,
	 * "include/singular/in_category/5" → term, "include/archive/author/3" → user).
	 *
	 * @param string $condition Condition path.
	 * @return string post|term|user, or '' when it has no object ID.
	 */
	public static function condition_kind( $condition ) {
		$parts = explode( '/', (string) $condition );
		if ( count( $parts ) < 3 || ! ctype_digit( (string) end( $parts ) ) ) {
			return '';
		}
		$sub = (string) $parts[ count( $parts ) - 2 ];
		if ( 'author' === $sub || 'by_author' === $sub || '_by_author' === substr( $sub, -10 ) ) {
			return 'user';
		}
		if ( 0 === strpos( $sub, 'in_' ) || 0 === strpos( $sub, 'child_of_' ) || 0 === strpos( $sub, 'any_child_of_' ) || taxonomy_exists( $sub ) ) {
			return 'term';
		}
		if ( 'child_of' === $sub || 'any_child_of' === $sub || post_type_exists( $sub ) ) {
			return 'post';
		}
		return '';
	}

	/**
	 * Object IDs in Theme Builder display conditions.
	 *
	 * @param mixed $conditions Stored conditions.
	 * @return array{post:int[],term:int[],user:int[]}
	 */
	public static function condition_ids( $conditions ) {
		$out = array(
			'post' => array(),
			'term' => array(),
			'user' => array(),
		);
		foreach ( is_array( $conditions ) ? $conditions : array() as $condition ) {
			$kind = self::condition_kind( (string) $condition );
			if ( $kind ) {
				$out[ $kind ][] = (int) substr( (string) $condition, strrpos( (string) $condition, '/' ) + 1 );
			}
		}
		return $out;
	}

	/**
	 * Rebuild Elementor Pro's display-conditions cache once at the end of a request that wrote
	 * template conditions (sync, import, migration write the meta directly, and the cache
	 * decides which header, footer or single template a page shows).
	 *
	 * @return void
	 */
	public static function watch_conditions() {
		$schedule = static function ( $meta_id, $object_id, $meta_key ) {
			static $queued = false;
			if ( '_elementor_conditions' !== $meta_key || $queued ) {
				return;
			}
			$queued = true;
			add_action(
				'shutdown',
				static function () {
					if ( class_exists( '\\ElementorPro\\Modules\\ThemeBuilder\\Classes\\Conditions_Cache' ) ) {
						( new \ElementorPro\Modules\ThemeBuilder\Classes\Conditions_Cache() )->regenerate();
					}
				},
				0
			);
		};
		add_action( 'added_post_meta', $schedule, 10, 3 );
		add_action( 'updated_post_meta', $schedule, 10, 3 );
		add_action( 'deleted_post_meta', $schedule, 10, 3 );
	}
}
