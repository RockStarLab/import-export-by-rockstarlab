<?php
/** Clean the HTML of posts and pages from a Squarespace export (WordPress XML). */
namespace RockStarLab\ImportExport\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Squarespace writes its layout blocks into the exported HTML: wrapper divs with its classes,
 * inline styles that keep the space for an image (padding-bottom: 75%), lazy-loading attributes
 * (data-src, data-image) and a ?format= size on image addresses. Without Squarespace's scripts
 * and styles that leaves gaps and broken layouts on a WordPress theme. The content (paragraphs,
 * headings, lists, images, captions, links, embeds) stays; the wrappers go.
 */
class Squarespace_Content {

	/**
	 * Whether the HTML comes from Squarespace.
	 *
	 * @param string $html Post content.
	 * @return bool
	 */
	public static function is_squarespace( $html ) {
		return is_string( $html ) && (bool) preg_match( '~class="[^"]*\b(sqs-block|sqs-html-content|image-block-outer-wrapper|sqs-image-shape-container-element)\b|images\.squarespace-cdn\.com~', $html );
	}

	/**
	 * Clean the HTML.
	 *
	 * @param string $html Post content.
	 * @return string
	 */
	public static function clean( $html ) {
		$html = (string) $html;
		if ( '' === trim( $html ) || ! class_exists( '\DOMDocument' ) ) {
			return $html;
		}
		$dom  = new \DOMDocument();
		$prev = libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="UTF-8"><div id="rsl-root">' . preg_replace( '~</?source\b[^>]*>~i', '', $html ) . '</div>', LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );
		$xp   = new \DOMXPath( $dom );
		$root = $xp->query( '//*[@id="rsl-root"]' )->item( 0 );
		if ( ! $root ) {
			return $html;
		}
		// Lazy-loaded images: the real address is in data-src or data-image.
		foreach ( iterator_to_array( $xp->query( '//img' ) ) as $img ) {
			$src = (string) $img->getAttribute( 'src' );
			$alt = (string) ( $img->getAttribute( 'data-src' ) ?: $img->getAttribute( 'data-image' ) );
			if ( '' !== $alt && ( '' === $src || 0 === strpos( $src, 'data:' ) ) ) {
				$img->setAttribute( 'src', $alt );
			}
		}
		foreach ( array_reverse( iterator_to_array( $xp->query( '//*[@id="rsl-root"]//div | //*[@id="rsl-root"]//section | //*[@id="rsl-root"]//span[not(@*)] | //noscript' ) ) ) as $node ) {
			if ( 'noscript' === strtolower( $node->nodeName ) && $node->parentNode ) {
				$node->parentNode->removeChild( $node );
				continue;
			}
			while ( $node->firstChild ) {
				$node->parentNode->insertBefore( $node->firstChild, $node );
			}
			$node->parentNode->removeChild( $node );
		}
		foreach ( iterator_to_array( $xp->query( '//*[@id="rsl-root"]//*' ) ) as $node ) {
			foreach ( iterator_to_array( $node->attributes ) as $attr ) {
				if ( preg_match( '/^(class|id|style|data-.*)$/i', $attr->name ) ) {
					$node->removeAttribute( $attr->name );
				}
			}
		}
		// A caption is a paragraph inside <figcaption>: keep the text only.
		foreach ( iterator_to_array( $xp->query( '//figcaption/p' ) ) as $p ) {
			while ( $p->firstChild ) {
				$p->parentNode->insertBefore( $p->firstChild, $p );
			}
			$p->parentNode->removeChild( $p );
		}
		$out = '';
		foreach ( $root->childNodes as $child ) {
			$out .= $dom->saveHTML( $child );
		}
		return trim( preg_replace( "~\n{3,}~", "\n\n", $out ) );
	}
}
