<?php
/** Read a Blogger export (Atom feed) as post and page rows. */
namespace RockStarLab\ImportExport\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Blogger's "Back up content" file is an Atom feed that mixes the blog settings, the template,
 * posts, pages and comments, told apart by a category with the scheme
 * http://schemas.google.com/g/2005#kind. Read as plain XML, every entry became a post. Here
 * only posts and pages are rows: title, HTML content, publish date in site time, labels as
 * categories, drafts as drafts and the blogspot address in the source_url custom field.
 */
class Blogger_Feed {
	const KIND = 'http://schemas.google.com/g/2005#kind';

	/** Whether a file is a Blogger export. */
	public static function is_feed( $file_path ) {
		$head = is_readable( $file_path ) ? (string) file_get_contents( $file_path, false, null, 0, 8192 ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local import file.
		return false !== strpos( $head, '<feed' ) && ( false !== strpos( $head, 'schemas.google.com/blogger/2008/kind' ) || false !== stripos( $head, '>Blogger</generator>' ) || false !== strpos( $head, 'tag:blogger.com' ) );
	}

	/**
	 * Post and page rows of the feed, in file order.
	 *
	 * @param string $file_path File.
	 * @return array[]
	 */
	public static function rows( $file_path ) {
		$prev = libxml_use_internal_errors( true );
		$xml  = simplexml_load_file( $file_path, 'SimpleXMLElement', LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE );
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );
		if ( ! $xml ) {
			throw new \RuntimeException( __( 'Invalid XML document.', 'import-export-by-rockstarlab' ) );
		}
		$rows = array();
		foreach ( $xml->entry as $entry ) {
			$kind   = '';
			$labels = array();
			foreach ( $entry->category as $category ) {
				$attr   = $category->attributes();
				$scheme = (string) $attr['scheme'];
				$term   = (string) $attr['term'];
				if ( self::KIND === $scheme ) {
					$kind = (string) substr( $term, (int) strrpos( $term, '#' ) + 1 );
				} elseif ( '' !== $term ) {
					$labels[] = $term;
				}
			}
			// Newer exports name the type in <blogger:type>.
			foreach ( $entry->children( 'http://schemas.google.com/blogger/2018' ) as $name => $child ) {
				if ( 'type' === $name && '' === $kind ) {
					$kind = strtolower( (string) $child );
				}
				if ( 'status' === $name && 'DRAFT' === strtoupper( (string) $child ) ) {
					$entry->addChild( 'rsl_draft', '1' );
				}
			}
			if ( ! in_array( $kind, array( 'post', 'page' ), true ) ) {
				continue;
			}
			$url = '';
			foreach ( $entry->link as $link ) {
				$attr = $link->attributes();
				if ( 'alternate' === (string) $attr['rel'] ) {
					$url = (string) $attr['href'];
				}
			}
			$draft = isset( $entry->rsl_draft );
			foreach ( $entry->children( 'http://purl.org/atom/app#' ) as $name => $control ) {
				if ( 'control' === $name && 'yes' === strtolower( trim( (string) $control->children( 'http://purl.org/atom/app#' )->draft ) ) ) {
					$draft = true;
				}
			}
			$stamp = strtotime( (string) $entry->published );
			$row   = array(
				'post_title'               => trim( (string) $entry->title ),
				'post_content'             => trim( (string) $entry->content ),
				'post_date'                => $stamp ? get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $stamp ) ) : '',
				'post_status'              => $draft ? 'draft' : 'publish',
				'wp:post_type'             => $kind,
				'meta_source_url'          => $url,
			);
			if ( 'post' === $kind && $labels ) {
				$row['categories'] = implode( ',', $labels );
			}
			if ( '' !== $url && preg_match( '~/([^/]+?)\.html?$~i', (string) wp_parse_url( $url, PHP_URL_PATH ), $m ) ) {
				$row['post_name'] = sanitize_title( $m[1] );
			}
			$rows[] = $row;
		}
		return $rows;
	}
}
