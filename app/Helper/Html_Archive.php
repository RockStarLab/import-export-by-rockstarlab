<?php
/** Turn a ZIP of HTML pages (a Medium export, a folder of saved articles) into import rows. */
namespace RockStarLab\ImportExport\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * A ZIP without a CSV, JSON, XML, XLSX or ODS file but with HTML pages is read as one post
 * per page. Medium exports put each story in posts/<date>_<title>.html with microformats
 * (p-name, p-summary, e-content, dt-published, p-canonical); other pages fall back to
 * <title>, <article> or <body> and the article:published_time meta. Drafts in a Medium
 * export (file names starting with draft_) become draft posts. The story's own address goes to
 * the source_url custom field.
 */
class Html_Archive {
	const MAX_PAGES = 5000;

	/**
	 * Whether the archive holds HTML pages (and no data file).
	 *
	 * @param \ZipArchive $zip Open archive.
	 * @return string[] Entry names of the pages to import.
	 */
	public static function pages( \ZipArchive $zip ) {
		$all   = array();
		$posts = array();
		for ( $i = 0; $i < $zip->numFiles; $i++ ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName -- Native ZipArchive property.
			$name = str_replace( '\\', '/', (string) $zip->getNameIndex( $i ) );
			if ( '' === $name || '/' === substr( $name, -1 ) || false !== strpos( $name, '__MACOSX/' ) || '.' === substr( basename( $name ), 0, 1 ) ) {
				continue;
			}
			if ( 0 === strpos( $name, '/' ) || preg_match( '~(^|/)\.\.(/|$)~', $name ) ) {
				continue;
			}
			if ( in_array( strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ), array( 'html', 'htm' ), true ) ) {
				$all[] = $name;
				if ( preg_match( '~(^|/)posts/[^/]+$~i', $name ) ) {
					$posts[] = $name;
				}
			}
		}
		$list = $posts ? $posts : $all;
		sort( $list );
		return array_slice( $list, 0, self::MAX_PAGES );
	}

	/**
	 * Rows (one per page) as JSON.
	 *
	 * @param \ZipArchive $zip   Open archive.
	 * @param string[]    $names Entry names from pages().
	 * @return string JSON array.
	 */
	public static function to_json( \ZipArchive $zip, array $names ) {
		$rows = array();
		foreach ( $names as $name ) {
			$html = $zip->getFromName( $name );
			if ( ! is_string( $html ) || '' === trim( $html ) ) {
				continue;
			}
			$row = self::parse( $html, basename( $name ), $zip, $name );
			if ( $row ) {
				$rows[] = $row;
			}
		}
		return (string) wp_json_encode( $rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	}

	/**
	 * One page as a post row.
	 *
	 * @param string $html     Page HTML.
	 * @param string           $filename File name.
	 * @param \ZipArchive|null $zip      Archive of the page (images next to it).
	 * @param string           $entry    Entry name of the page in the archive.
	 * @return array|null
	 */
	public static function parse( $html, $filename = '', $zip = null, $entry = '' ) {
		$dom  = new \DOMDocument();
		$prev = libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR );
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );
		$xp = new \DOMXPath( $dom );

		$first = static function ( $query ) use ( $xp ) {
			$nodes = $xp->query( $query );
			return $nodes && $nodes->length ? $nodes->item( 0 ) : null;
		};
		$text  = static function ( $node ) {
			return $node ? trim( preg_replace( '/\s+/u', ' ', (string) $node->textContent ) ) : '';
		};
		$inner = static function ( $node ) use ( $dom ) {
			$out = '';
			if ( $node ) {
				foreach ( $node->childNodes as $child ) {
					$out .= $dom->saveHTML( $child );
				}
			}
			return trim( $out );
		};

		$google = Google_Docs_Html::is_google_doc( $html );
		$title_node = $first( '//*[contains(concat(" ", normalize-space(@class), " "), " p-name ")]' ) ?: ( $google ? null : $first( '//h1' ) );
		$title      = $text( $title_node );
		if ( '' === $title ) {
			$title = $text( $first( '//title' ) );
		}
		$body = $first( '//*[@data-field="body"]' ) ?: $first( '//*[contains(concat(" ", normalize-space(@class), " "), " e-content ")]' ) ?: $first( '//article' ) ?: $first( '//body' );
		if ( ! $body ) {
			return null;
		}
		$google_parts = null;
		if ( $google ) {
			// A Google Docs download: its Title paragraph is the post title, Subtitle the excerpt.
			$google_parts = Google_Docs_Html::clean( $dom, $xp, $body, $html, $zip, (string) $entry );
			if ( '' !== $google_parts['title'] ) {
				$title = $google_parts['title'];
			}
		}
		// The story title is repeated at the top of a Medium body: drop the first heading if it is the title.
		$lead = $xp->query( './/h3[contains(@class,"graf--title")] | .//h1', $body );
		if ( $lead && $lead->length && $text( $lead->item( 0 ) ) === $title ) {
			$lead->item( 0 )->parentNode->removeChild( $lead->item( 0 ) );
		}
		$content = self::clean( $inner( $body ) );

		$summary = $text( $first( '//*[@data-field="subtitle"]' ) ?: $first( '//*[contains(concat(" ", normalize-space(@class), " "), " p-summary ")]' ) );
		if ( $google_parts && '' !== $google_parts['subtitle'] ) {
			$summary = $google_parts['subtitle'];
		}
		$time    = $first( '//time[contains(concat(" ", normalize-space(@class), " "), " dt-published ")]' ) ?: $first( '//time[@datetime]' );
		$date    = $time ? (string) $time->getAttribute( 'datetime' ) : '';
		if ( '' === $date ) {
			$meta = $first( '//meta[@property="article:published_time"]' );
			$date = $meta ? (string) $meta->getAttribute( 'content' ) : '';
		}
		$stamp     = '' !== $date ? strtotime( $date ) : false;
		$canonical = $first( '//a[contains(concat(" ", normalize-space(@class), " "), " p-canonical ")]' ) ?: $first( '//link[@rel="canonical"]' );
		$canonical = $canonical ? (string) $canonical->getAttribute( 'href' ) : '';
		$draft     = 0 === stripos( $filename, 'draft_' );

		$row = array(
			'post_title'      => '' !== $title ? $title : preg_replace( '/\.html?$/i', '', $filename ),
			'post_content'    => $content,
			'post_excerpt'    => $summary,
			'post_status'     => $draft ? 'draft' : 'publish',
			'post_date'       => $stamp ? get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $stamp ) ) : '',
			'meta_source_url' => $canonical,
		);
		if ( $canonical && preg_match( '~/([^/?#]+?)(?:-[0-9a-f]{8,12})?(?:[?#].*)?$~', $canonical, $m ) ) {
			$row['post_name'] = sanitize_title( $m[1] );
		}
		return $row;
	}

	/** Strip Medium's layout wrappers and classes from the story HTML, keep the content. */
	private static function clean( $html ) {
		$html = preg_replace( '~<(/?)(section|div)\b[^>]*>~i', '', $html );
		$html = preg_replace( '~\s(class|name|id|data-[a-z-]+)="[^"]*"~i', '', $html );
		$html = preg_replace( '~<hr[^>]*>~i', '', $html );
		return trim( preg_replace( "~\n{3,}~", "\n\n", (string) $html ) );
	}
}
