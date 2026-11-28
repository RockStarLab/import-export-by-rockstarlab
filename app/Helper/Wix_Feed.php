<?php
/** Read a Wix blog feed (blog-feed.xml) as post rows. */
namespace RockStarLab\ImportExport\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Wix has no blog export; its blog feed (/blog-feed.xml) is the file to move posts with. Each
 * <item> holds the title, the full HTML in content:encoded, a short description, the publish
 * date, categories, the cover image as an <enclosure> and the address /post/<slug>. Read as plain
 * XML, the posts arrived as drafts dated today, without slugs or covers. Here every item is a
 * published post with its date in site time, the slug from its address, the description as the
 * excerpt, the cover as the featured image and the address in the source_url custom field.
 */
class Wix_Feed {

	/** Whether a file is a Wix blog feed. */
	public static function is_feed( $file_path ) {
		$head = is_readable( $file_path ) ? (string) file_get_contents( $file_path, false, null, 0, 16384 ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local import file.
		if ( false === strpos( $head, '<rss' ) || false !== strpos( $head, 'wordpress.org/export/' ) ) {
			return false;
		}
		return (bool) preg_match( '~wixsite\.com/|static\.wixstatic\.com/|<generator>\s*RSS for Node\s*</generator>~i', $head );
	}

	/**
	 * Post rows of the feed, in file order.
	 *
	 * @param string $file_path File.
	 * @return array[]
	 */
	public static function rows( $file_path ) {
		$prev = libxml_use_internal_errors( true );
		$xml  = simplexml_load_file( $file_path, 'SimpleXMLElement', LIBXML_NONET | LIBXML_COMPACT | LIBXML_NOCDATA );
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );
		if ( ! $xml || ! isset( $xml->channel ) ) {
			throw new \RuntimeException( __( 'Invalid XML document.', 'import-export-by-rockstarlab' ) );
		}
		$rows = array();
		foreach ( $xml->channel->item as $item ) {
			$content = trim( (string) $item->children( 'http://purl.org/rss/1.0/modules/content/' )->encoded );
			$summary = trim( (string) $item->description );
			$title   = trim( (string) $item->title );
			if ( '' === $title && '' === $content && '' === $summary ) {
				continue;
			}
			$link  = trim( (string) $item->link );
			$stamp = strtotime( (string) $item->pubDate );
			$row   = array(
				'post_title'   => $title,
				'post_content' => '' !== $content ? $content : $summary,
				'post_excerpt' => '' !== $content ? trim( wp_strip_all_tags( $summary ) ) : '',
				'post_status'  => 'publish',
				'post_date'    => $stamp ? get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $stamp ) ) : '',
			);
			$path = $link ? (string) wp_parse_url( $link, PHP_URL_PATH ) : '';
			if ( '' !== $path && '' !== trim( $path, '/' ) ) {
				$row['post_name'] = sanitize_title( rawurldecode( basename( untrailingslashit( $path ) ) ) );
			}
			$categories = array();
			foreach ( $item->category as $category ) {
				$name = trim( (string) $category );
				if ( '' !== $name ) {
					$categories[] = str_replace( ',', ' ', $name );
				}
			}
			if ( $categories ) {
				$row['categories'] = implode( ',', array_unique( $categories ) );
			}
			foreach ( $item->enclosure as $enclosure ) {
				$attr = $enclosure->attributes();
				$url  = trim( (string) $attr['url'] );
				$type = strtolower( (string) $attr['type'] );
				if ( '' !== $url && ( 0 === strpos( $type, 'image/' ) || preg_match( '~\.(jpe?g|png|gif|webp)(\?|$)~i', $url ) ) ) {
					$row['featured_image_url'] = $url;
					break;
				}
			}
			if ( '' !== $link ) {
				$row['meta_source_url'] = $link;
			}
			$rows[] = $row;
		}
		return $rows;
	}
}
