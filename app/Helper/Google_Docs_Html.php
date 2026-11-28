<?php
/** Turn a Google Docs "Web page (.html, zipped)" download into clean post HTML. */
namespace RockStarLab\ImportExport\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Google Docs writes bold, italic and underline as classes (.c5{font-weight:700}) defined in a
 * <style> block, wraps every run of text in a <span>, sends links through
 * https://www.google.com/url?q=..., and puts the images next to the page in images/ inside the
 * ZIP. Cleaned like any HTML page, the post lost its bold and italic text, the links went through
 * Google, and the images pointed at files that were not on the site. Here the classes become
 * <strong>, <em> and <u>, links get their real address, the "Title" paragraph becomes the post
 * title (and "Subtitle" the excerpt), and the images are copied next to the import so the media
 * import can add them to the Media Library.
 */
class Google_Docs_Html {

	/** Whether the page is a Google Docs export. */
	public static function is_google_doc( $html ) {
		return is_string( $html ) && (bool) preg_match( '~class="[^"]*\bdoc-content\b|\.lst-kix_|themes\.googleusercontent\.com~', $html );
	}

	/**
	 * Formatting classes of the page: class => [strong, em, u].
	 *
	 * @param string $html Page HTML.
	 * @return array<string,array<string,bool>>
	 */
	public static function formatting_classes( $html ) {
		$map = array();
		if ( ! preg_match_all( '~<style[^>]*>(.*?)</style>~is', (string) $html, $styles ) ) {
			return $map;
		}
		foreach ( $styles[1] as $css ) {
			if ( ! preg_match_all( '~\.(c\d+)\s*\{([^}]*)\}~', $css, $rules, PREG_SET_ORDER ) ) {
				continue;
			}
			foreach ( $rules as $rule ) {
				$decl = strtolower( $rule[2] );
				$map[ $rule[1] ] = array(
					'strong' => (bool) preg_match( '~font-weight\s*:\s*(700|800|900|bold)~', $decl ),
					'em'     => (bool) preg_match( '~font-style\s*:\s*italic~', $decl ),
					'u'      => (bool) preg_match( '~text-decoration\s*:\s*underline~', $decl ),
				);
			}
		}
		return $map;
	}

	/**
	 * Clean the body of a Google Docs page in place.
	 *
	 * @param \DOMDocument     $dom     Document.
	 * @param \DOMXPath        $xp      XPath.
	 * @param \DOMElement      $body    Body element.
	 * @param string           $html    Original page HTML (for the <style> block).
	 * @param \ZipArchive|null $zip     Archive the page came from.
	 * @param string           $page    Entry name of the page in the archive.
	 * @return array{title:string,subtitle:string}
	 */
	public static function clean( \DOMDocument $dom, \DOMXPath $xp, \DOMElement $body, $html, $zip = null, $page = '' ) {
		$text = static function ( $node ) {
			return $node ? trim( preg_replace( '/\s+/u', ' ', (string) $node->textContent ) ) : '';
		};
		$out  = array(
			'title'    => '',
			'subtitle' => '',
		);
		foreach ( array( 'title', 'subtitle' ) as $kind ) {
			$node = $xp->query( './/p[contains(concat(" ", normalize-space(@class), " "), " ' . $kind . ' ")]', $body )->item( 0 );
			if ( $node ) {
				$out[ $kind ] = $text( $node );
				$node->parentNode->removeChild( $node );
			}
		}

		// Bold, italic and underline classes become tags around the text of the span.
		$formats = self::formatting_classes( $html );
		foreach ( iterator_to_array( $xp->query( './/span[@class]', $body ) ) as $span ) {
			$wrap = array();
			foreach ( preg_split( '/\s+/', trim( (string) $span->getAttribute( 'class' ) ) ) as $class ) {
				foreach ( array( 'strong', 'em', 'u' ) as $tag ) {
					if ( ! empty( $formats[ $class ][ $tag ] ) ) {
						$wrap[ $tag ] = true;
					}
				}
			}
			// A link is underlined by the theme already.
			if ( $xp->query( './/a', $span )->length ) {
				unset( $wrap['u'] );
			}
			if ( ! $wrap || '' === trim( $span->textContent ) || $xp->query( './/img', $span )->length ) {
				continue;
			}
			$inner = $dom->createDocumentFragment();
			while ( $span->firstChild ) {
				$inner->appendChild( $span->firstChild );
			}
			$node = $inner;
			foreach ( array_keys( $wrap ) as $tag ) {
				$el = $dom->createElement( $tag );
				$el->appendChild( $node );
				$node = $el;
			}
			$span->appendChild( $node );
		}

		// Links through https://www.google.com/url?q=<address>&sa=... get their real address.
		foreach ( iterator_to_array( $xp->query( './/a[@href]', $body ) ) as $a ) {
			$href = (string) $a->getAttribute( 'href' );
			if ( preg_match( '~^https?://(www\.)?google\.[a-z.]+/url\?~i', $href ) ) {
				$query = array();
				wp_parse_str( (string) wp_parse_url( $href, PHP_URL_QUERY ), $query );
				if ( ! empty( $query['q'] ) && preg_match( '~^(https?:|mailto:)~i', (string) $query['q'] ) ) {
					$a->setAttribute( 'href', (string) $query['q'] );
				}
			}
		}

		// Images in images/ next to the page are copied to the uploads folder for the media import.
		if ( $zip instanceof \ZipArchive ) {
			$base = false === strpos( $page, '/' ) ? '' : dirname( $page ) . '/';
			foreach ( iterator_to_array( $xp->query( './/img[@src]', $body ) ) as $img ) {
				$src = (string) $img->getAttribute( 'src' );
				if ( preg_match( '~^([a-z]+:|//|/)~i', $src ) || preg_match( '~(^|/)\.\.(/|$)~', $src ) ) {
					continue;
				}
				$url = self::copy_image( $zip, $base . rawurldecode( $src ) );
				if ( '' !== $url ) {
					$img->setAttribute( 'src', $url );
				}
			}
		}

		// Google Docs spans, inline styles and empty paragraphs go.
		foreach ( iterator_to_array( $xp->query( './/*[@style]', $body ) ) as $node ) {
			$node->removeAttribute( 'style' );
		}
		foreach ( array_reverse( iterator_to_array( $xp->query( './/span', $body ) ) ) as $span ) {
			while ( $span->firstChild ) {
				$span->parentNode->insertBefore( $span->firstChild, $span );
			}
			$span->parentNode->removeChild( $span );
		}
		foreach ( iterator_to_array( $xp->query( './/img[@title=""]', $body ) ) as $img ) {
			$img->removeAttribute( 'title' );
		}
		// The post title is the page heading: Heading 1 of the document becomes h2, and so on.
		if ( $xp->query( './/h1', $body )->length ) {
			foreach ( array( 5, 4, 3, 2, 1 ) as $level ) {
				foreach ( iterator_to_array( $xp->query( './/h' . $level, $body ) ) as $heading ) {
					$new = $dom->createElement( 'h' . ( $level + 1 ) );
					while ( $heading->firstChild ) {
						$new->appendChild( $heading->firstChild );
					}
					$heading->parentNode->replaceChild( $new, $heading );
				}
			}
		}
		foreach ( iterator_to_array( $xp->query( './/p', $body ) ) as $p ) {
			if ( '' === trim( str_replace( "\xC2\xA0", ' ', $p->textContent ) ) && ! $xp->query( './/img', $p )->length ) {
				$p->parentNode->removeChild( $p );
			}
		}
		return $out;
	}

	/**
	 * Copy one image of the archive to uploads/rsl-ie-archive-images/<hash>/ and return its URL.
	 *
	 * @param \ZipArchive $zip  Archive.
	 * @param string      $name Entry name.
	 * @return string URL, or '' when the entry is missing or not an image.
	 */
	private static function copy_image( \ZipArchive $zip, $name ) {
		$ext = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, array( 'png', 'jpg', 'jpeg', 'gif', 'webp' ), true ) ) {
			return '';
		}
		$data = $zip->getFromName( $name );
		if ( ! is_string( $data ) || '' === $data || strlen( $data ) > 20 * 1024 * 1024 ) {
			return '';
		}
		if ( ! function_exists( 'getimagesizefromstring' ) || false === @getimagesizefromstring( $data ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Not an image: skipped.
			return '';
		}
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return '';
		}
		$hash = substr( md5( $data ), 0, 12 );
		$dir  = trailingslashit( $uploads['basedir'] ) . 'rsl-ie-archive-images/' . $hash;
		$file = sanitize_file_name( basename( $name ) );
		if ( ! wp_mkdir_p( $dir ) || false === file_put_contents( $dir . '/' . $file, $data ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Image copied from the import archive.
			return '';
		}
		return trailingslashit( $uploads['baseurl'] ) . 'rsl-ie-archive-images/' . $hash . '/' . rawurlencode( $file );
	}
}
