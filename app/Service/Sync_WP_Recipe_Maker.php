<?php
/** Carry WP Recipe Maker recipes with the posts that show them (Content Sync push and pull). */
namespace RockStarLab\ImportExport\Service;

defined( 'ABSPATH' ) || exit;

/**
 * A post shows a recipe through <!-- wp:wp-recipe-maker/recipe {"id":N} -->, [wprm-recipe id="N"]
 * or the <!--WPRM Recipe N--> fallback, all of which store the recipe's post ID. Without the recipe
 * the other site shows an empty block or another post. The sender adds the recipes in WP Recipe
 * Maker's own data format; the receiver saves them with WP Recipe Maker and re-points the IDs.
 */
class Sync_WP_Recipe_Maker {
	const PAYLOAD_KEY      = 'rsl_ie_wprm_recipes';
	const SOURCE_ID_META   = '_rsl_ie_source_recipe_id';
	const SOURCE_SITE_META = '_rsl_ie_source_recipe_site';
	const MAX_RECIPES      = 20;

	/** Source attachment ID => local attachment ID of the sync being received. */
	private static $image_map = array();

	public static function register() {
		add_filter( 'rsl_ie_sync_post_payload', array( __CLASS__, 'payload' ), 10, 2 );
		add_action( 'rsl_ie_sync_post_received', array( __CLASS__, 'received' ), 10, 2 );
	}

	/** The receiver's image map for the posts being saved (set before the posts are saved). */
	public static function set_image_map( $map ) {
		self::$image_map = is_array( $map ) ? array_map( 'intval', $map ) : array();
	}

	private static function active() {
		return class_exists( '\WPRM_Recipe_Manager' ) && class_exists( '\WPRM_Recipe_Saver' ) && defined( 'WPRM_POST_TYPE' ) && post_type_exists( WPRM_POST_TYPE );
	}

	/**
	 * Recipe IDs shown by post content.
	 *
	 * @param string $content Post content.
	 * @return int[]
	 */
	public static function refs( $content ) {
		if ( ! is_string( $content ) || ( false === stripos( $content, 'wprm' ) && false === strpos( $content, 'wp-recipe-maker' ) ) ) {
			return array();
		}
		preg_match_all( '/<!--\s+wp:wp-recipe-maker\/recipe\s+\{[^}]*"id"\s*:\s*"?(\d+)|\[wprm-recipe\s[^\]]*\bid\s*=\s*["\']?(\d+)|<!--WPRM Recipe (\d+)-->/', $content, $m );
		$ids = array_merge( $m[1], $m[2], $m[3] );
		return array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
	}

	/**
	 * Images used by the recipes of a post (recipe image, pin image, instruction images).
	 *
	 * @param string $content Post content.
	 * @return int[] Attachment IDs.
	 */
	public static function image_ids( $content ) {
		$out = array();
		if ( ! self::active() ) {
			return $out;
		}
		foreach ( array_slice( self::refs( $content ), 0, self::MAX_RECIPES ) as $id ) {
			$data = self::recipe_data( $id );
			if ( ! $data ) {
				continue;
			}
			$out[] = (int) ( $data['image_id'] ?? 0 );
			$out[] = (int) ( $data['pin_image_id'] ?? 0 );
			foreach ( (array) ( $data['instructions'] ?? array() ) as $group ) {
				foreach ( (array) ( $group['instructions'] ?? array() ) as $step ) {
					$out[] = (int) ( is_array( $step ) ? ( $step['image'] ?? 0 ) : 0 );
				}
			}
		}
		return array_values( array_unique( array_filter( $out, static function ( $id ) { return $id > 0 && 'attachment' === get_post_type( $id ); } ) ) );
	}

	/** WP Recipe Maker's data of one recipe, ready to send (term names instead of local term IDs). */
	private static function recipe_data( $id ) {
		$recipe = 'wprm_recipe' === get_post_type( $id ) || ( defined( 'WPRM_POST_TYPE' ) && WPRM_POST_TYPE === get_post_type( $id ) ) ? \WPRM_Recipe_Manager::get_recipe( $id ) : null;
		if ( ! $recipe || ! current_user_can( 'read_post', $id ) ) {
			return null;
		}
		$data = (array) $recipe->get_data();
		foreach ( (array) ( $data['tags'] ?? array() ) as $key => $terms ) {
			$data['tags'][ $key ] = array_values( array_filter( array_map( static function ( $t ) { return is_object( $t ) ? (string) $t->name : ( is_array( $t ) ? (string) ( $t['name'] ?? '' ) : (string) $t ); }, (array) $terms ) ) );
		}
		// Ingredient and equipment term IDs belong to this site; the names identify them on the other.
		foreach ( array( 'ingredients', 'equipment' ) as $list ) {
			if ( empty( $data[ $list ] ) || ! is_array( $data[ $list ] ) ) {
				continue;
			}
			array_walk_recursive( $data[ $list ], static function ( &$value, $key ) { if ( 'id' === $key ) { $value = 0; } } );
		}
		unset( $data['ingredients_flat'], $data['instructions_flat'], $data['image_url'], $data['pin_image_url'], $data['date_formatted'] );
		return $data;
	}

	/**
	 * Sender: add the recipes a post shows to its payload.
	 *
	 * @param array    $post_data Post payload.
	 * @param \WP_Post $post      Post.
	 * @return array
	 */
	public static function payload( $post_data, $post ) {
		if ( ! self::active() || ! $post instanceof \WP_Post ) {
			return $post_data;
		}
		$recipes = array();
		foreach ( array_slice( self::refs( $post->post_content ), 0, self::MAX_RECIPES ) as $id ) {
			$data = self::recipe_data( $id );
			if ( $data ) {
				$recipes[ $id ] = array(
					'site' => untrailingslashit( home_url() ),
					'origin_id'   => (int) get_post_meta( $id, self::SOURCE_ID_META, true ),
					'origin_site' => (string) get_post_meta( $id, self::SOURCE_SITE_META, true ),
					'data' => $data,
				);
			}
		}
		if ( $recipes ) {
			$post_data[ self::PAYLOAD_KEY ] = $recipes;
		}
		return $post_data;
	}

	/**
	 * Receiver: save the recipes of a received post and point its content at them.
	 *
	 * @param int   $post_id   Local post.
	 * @param array $post_data Received post.
	 */
	public static function received( $post_id, $post_data ) {
		$recipes = is_array( $post_data ) ? ( $post_data[ self::PAYLOAD_KEY ] ?? null ) : null;
		if ( ! is_array( $recipes ) || ! $recipes || ! self::active() || ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		$map = self::import( $recipes, (int) $post_id );
		if ( ! $map ) {
			return;
		}
		$content = (string) get_post_field( 'post_content', $post_id, 'raw' );
		$fixed   = self::apply( $content, $map );
		if ( $fixed !== $content ) {
			global $wpdb;
			// Direct update: the post is mid-save, and its own hooks run when the sync finishes it.
			$wpdb->update( $wpdb->posts, array( 'post_content' => $fixed ), array( 'ID' => (int) $post_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			clean_post_cache( (int) $post_id );
		}
	}

	/**
	 * Create or update recipes from received data.
	 *
	 * @param array $recipes Source recipe ID => [site, data].
	 * @param int   $parent  Local post that shows them.
	 * @return array Source ID => local recipe ID.
	 */
	public static function import( array $recipes, $parent = 0 ) {
		$map = array();
		foreach ( array_slice( $recipes, 0, self::MAX_RECIPES, true ) as $source_id => $r ) {
			$source_id = absint( $source_id );
			$data      = is_array( $r ) && is_array( $r['data'] ?? null ) ? $r['data'] : null;
			if ( ! $source_id || ! $data ) {
				continue;
			}
			$site = untrailingslashit( esc_url_raw( (string) ( $r['site'] ?? '' ) ) );
			foreach ( array( 'image_id', 'pin_image_id' ) as $key ) {
				$data[ $key ] = self::image( $data[ $key ] ?? 0 );
			}
			foreach ( (array) ( $data['instructions'] ?? array() ) as $g => $group ) {
				foreach ( (array) ( $group['instructions'] ?? array() ) as $s => $step ) {
					if ( is_array( $step ) && ! empty( $step['image'] ) ) {
						$data['instructions'][ $g ]['instructions'][ $s ]['image'] = self::image( $step['image'] );
					}
				}
			}
			$status = in_array( $data['post_status'] ?? '', array( 'publish', 'draft', 'private', 'pending', 'future' ), true ) ? $data['post_status'] : 'publish';
			unset( $data['id'], $data['post_author'], $data['parent_post_id'] );
			$local = self::find( $source_id, $site, $r );
			if ( $local && ! current_user_can( 'edit_post', $local ) ) {
				$map[ $source_id ] = $local;
				continue;
			}
			if ( ! $local ) {
				$local = (int) \WPRM_Recipe_Saver::create_recipe( $data );
			} else {
				\WPRM_Recipe_Saver::update_recipe( $local, $data, true );
			}
			if ( $local <= 0 ) {
				continue;
			}
			if ( $parent > 0 ) {
				update_post_meta( $local, 'wprm_parent_post_id', (int) $parent );
			}
			if ( get_post_status( $local ) !== $status ) {
				global $wpdb;
				// Direct update: saving a recipe post runs WP Recipe Maker's own status rules.
				$wpdb->update( $wpdb->posts, array( 'post_status' => $status ), array( 'ID' => (int) $local ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				clean_post_cache( (int) $local );
			}
			update_post_meta( $local, self::SOURCE_ID_META, $source_id );
			update_post_meta( $local, self::SOURCE_SITE_META, $site );
			$map[ $source_id ] = $local;
		}
		return $map;
	}

	/** Local copy of a source image, or 0 (a recipe without its image rather than another image). */
	private static function image( $id ) {
		$id = absint( $id );
		return $id && isset( self::$image_map[ $id ] ) ? (int) self::$image_map[ $id ] : 0;
	}

	/** Local copy of a source recipe: synced earlier from that site, the same recipe on a loopback, or one that went out from here. */
	private static function find( $source_id, $site, array $r ) {
		$ids = get_posts(
			array(
				'post_type'      => WPRM_POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery -- Bounded identity lookup.
					array( 'key' => self::SOURCE_ID_META, 'value' => $source_id ),
					array( 'key' => self::SOURCE_SITE_META, 'value' => $site ),
				),
			)
		);
		if ( $ids ) {
			return (int) $ids[0];
		}
		$here = untrailingslashit( home_url() );
		if ( $here === $site && WPRM_POST_TYPE === get_post_type( $source_id ) ) {
			return (int) $source_id;
		}
		$origin = absint( $r['origin_id'] ?? 0 );
		if ( $origin && $here === untrailingslashit( (string) ( $r['origin_site'] ?? '' ) ) && WPRM_POST_TYPE === get_post_type( $origin ) ) {
			return $origin;
		}
		return 0;
	}

	/**
	 * Re-point recipe IDs in post content.
	 *
	 * @param string $content Post content.
	 * @param array  $map     Source ID => local ID.
	 * @return string
	 */
	public static function apply( $content, array $map ) {
		if ( ! $map || ! is_string( $content ) ) {
			return $content;
		}
		$swap = static function ( $id ) use ( $map ) { return isset( $map[ (int) $id ] ) ? (int) $map[ (int) $id ] : $id; };
		$content = preg_replace_callback( '/(<!--\s+wp:wp-recipe-maker\/recipe\s+\{[^}]*"id"\s*:\s*"?)(\d+)/', static function ( $m ) use ( $swap ) { return $m[1] . $swap( $m[2] ); }, $content );
		$content = preg_replace_callback( '/(\[wprm-recipe\s[^\]]*\bid\s*=\s*["\']?)(\d+)/', static function ( $m ) use ( $swap ) { return $m[1] . $swap( $m[2] ); }, $content );
		$content = preg_replace_callback( '/(<!--WPRM Recipe )(\d+)(-->)/', static function ( $m ) use ( $swap ) { return $m[1] . $swap( $m[2] ) . $m[3]; }, $content );
		return (string) $content;
	}
}
