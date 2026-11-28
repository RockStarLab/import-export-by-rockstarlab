<?php
/** Turn a Substack export (posts.csv plus posts/<post_id>.html) into import rows. */
namespace RockStarLab\ImportExport\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * A Substack export ZIP holds posts.csv (post_id, post_date, is_published, type, audience,
 * title, subtitle, podcast_url), one HTML body per post in posts/<post_id>.html, the
 * subscriber list and delivery statistics as more CSV files. The posts are read as one row
 * per post: the title, the HTML body, the subtitle as the excerpt, the publish date, drafts
 * as drafts and the slug from the post_id. The other CSV files are left out.
 */
class Substack_Archive {
	const MAX_POSTS = 5000;

	/**
	 * Entry name of posts.csv when the archive is a Substack export.
	 *
	 * @param \ZipArchive $zip Open archive.
	 * @return string Entry name, or '' when it is not a Substack export.
	 */
	public static function posts_csv( \ZipArchive $zip ) {
		for ( $i = 0; $i < $zip->numFiles; $i++ ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName -- Native ZipArchive property.
			$name = str_replace( '\\', '/', (string) $zip->getNameIndex( $i ) );
			if ( 'posts.csv' !== strtolower( basename( $name ) ) || false !== strpos( $name, '__MACOSX/' ) || substr_count( $name, '/' ) > 1 || preg_match( '~(^|/)\.\.(/|$)~', $name ) || 0 === strpos( $name, '/' ) ) {
				continue;
			}
			$head = self::header( (string) $zip->getFromName( $name, 4096 ) );
			if ( in_array( 'post_id', $head, true ) && in_array( 'is_published', $head, true ) && in_array( 'title', $head, true ) ) {
				return $name;
			}
		}
		return '';
	}

	/**
	 * Rows (one per post) as JSON.
	 *
	 * @param \ZipArchive $zip  Open archive.
	 * @param string      $name Entry name of posts.csv.
	 * @return string JSON array.
	 */
	public static function to_json( \ZipArchive $zip, $name ) {
		$base   = false === strpos( $name, '/' ) ? '' : dirname( $name ) . '/';
		$stream = fopen( 'php://temp', 'w+b' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Memory stream.
		fwrite( $stream, (string) $zip->getFromName( $name ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Memory stream.
		rewind( $stream );
		$keys = array();
		$rows = array();
		while ( false !== ( $cells = fgetcsv( $stream, 0, ',', '"', '' ) ) && count( $rows ) < self::MAX_POSTS ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition -- Read loop.
			if ( ! $keys ) {
				$keys = self::header_cells( $cells );
				continue;
			}
			if ( count( $cells ) !== count( $keys ) ) {
				continue;
			}
			$row = self::row( $zip, $base, array_combine( $keys, $cells ) );
			if ( $row ) {
				$rows[] = $row;
			}
		}
		fclose( $stream ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Memory stream.
		return (string) wp_json_encode( $rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	}

	/**
	 * One posts.csv line as a post row.
	 *
	 * @param \ZipArchive $zip  Open archive.
	 * @param string      $base Folder of posts.csv inside the archive.
	 * @param array       $item posts.csv line by column name.
	 * @return array|null
	 */
	public static function row( \ZipArchive $zip, $base, array $item ) {
		$id = trim( (string) ( $item['post_id'] ?? '' ) );
		if ( '' === $id || preg_match( '~[/\\\\]|\.\.~', $id ) ) {
			return null;
		}
		$html  = $zip->getFromName( $base . 'posts/' . $id . '.html' );
		$html  = is_string( $html ) ? self::clean( $html ) : '';
		$title = trim( (string) ( $item['title'] ?? '' ) );
		if ( '' === $title && '' === $html ) {
			return null;
		}
		$published = in_array( strtolower( trim( (string) ( $item['is_published'] ?? '' ) ) ), array( 'true', '1', 'yes' ), true );
		$stamp     = strtotime( (string) ( $item['post_date'] ?? '' ) );
		$row       = array(
			'post_title'   => $title,
			'post_content' => $html,
			'post_excerpt' => trim( (string) ( $item['subtitle'] ?? '' ) ),
			'post_status'  => $published ? 'publish' : 'draft',
			'post_date'    => $stamp ? get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $stamp ) ) : '',
		);
		$slug = false !== strpos( $id, '.' ) ? substr( $id, strpos( $id, '.' ) + 1 ) : '';
		if ( '' !== $slug ) {
			$row['post_name'] = sanitize_title( $slug );
		}
		foreach ( array( 'audience', 'type', 'podcast_url' ) as $key ) {
			if ( '' !== trim( (string) ( $item[ $key ] ?? '' ) ) ) {
				$row[ 'meta_substack_' . $key ] = trim( (string) $item[ $key ] );
			}
		}
		return $row;
	}

	/** Column names of the first CSV line. */
	private static function header( $text ) {
		$line = strtok( (string) $text, "\r\n" );
		return false === $line ? array() : self::header_cells( str_getcsv( $line, ',', '"', '' ) );
	}

	/** Normalised column names. */
	private static function header_cells( array $cells ) {
		return array_map(
			static function ( $cell ) {
				return strtolower( trim( preg_replace( '/^\xEF\xBB\xBF/', '', (string) $cell ) ) );
			},
			$cells
		);
	}

	/**
	 * Keep the post HTML, drop Substack's wrappers: image containers, picture sources,
	 * subscribe and share buttons.
	 *
	 * @param string $html Post body.
	 * @return string
	 */
	public static function clean( $html ) {
		// The HTML parser does not know <source> is empty and would swallow the <img> after it.
		$html = preg_replace( '~</?source\b[^>]*>~i', '', (string) $html );
		if ( '' === trim( $html ) ) {
			return '';
		}
		$dom  = new \DOMDocument();
		$prev = libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="UTF-8"><div id="rsl-root">' . $html . '</div>', LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );
		$xp   = new \DOMXPath( $dom );
		$root = $xp->query( '//*[@id="rsl-root"]' )->item( 0 );
		if ( ! $root ) {
			return trim( $html );
		}
		$has  = static function ( $word ) {
			return 'contains(concat(" ", normalize-space(@class), " "), " ' . $word . '")';
		};
		$drop = '//form | //source | //button | //*[' . $has( 'subscription-widget' ) . '] | //*[' . $has( 'button-wrapper' ) . '] | //*[' . $has( 'captioned-button' ) . '] | //*[' . $has( 'share-dialog' ) . '] | //*[' . $has( 'image-link-expand' ) . ']';
		foreach ( iterator_to_array( $xp->query( $drop ) ) as $node ) {
			if ( $node->parentNode ) {
				$node->parentNode->removeChild( $node );
			}
		}
		$unwrap = '//picture | //section | //div[not(@id="rsl-root")] | //a[' . $has( 'image-link' ) . ']';
		foreach ( array_reverse( iterator_to_array( $xp->query( $unwrap ) ) ) as $node ) {
			while ( $node->firstChild ) {
				$node->parentNode->insertBefore( $node->firstChild, $node );
			}
			$node->parentNode->removeChild( $node );
		}
		foreach ( iterator_to_array( $xp->query( '//*[@id="rsl-root"]//*' ) ) as $node ) {
			foreach ( iterator_to_array( $node->attributes ) as $attr ) {
				if ( preg_match( '/^(class|id|srcset|sizes|data-.*|fetchpriority|loading|tabindex|style|rel|target|role)$/i', $attr->name ) ) {
					$node->removeAttribute( $attr->name );
				}
			}
		}
		$out = '';
		foreach ( $root->childNodes as $child ) {
			$out .= $dom->saveHTML( $child );
		}
		return trim( preg_replace( "~\n{3,}~", "\n\n", $out ) );
	}
}
