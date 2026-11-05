/** Consistent activity indicator for remote Pull/Push buttons. */
export default function syncButtonBusy( $button, label = 'Loading...' ) {
	if ( ! document.getElementById( 'rsl-ie-sync-button-busy-style' ) ) {
		const style = document.createElement( 'style' );
		style.id = 'rsl-ie-sync-button-busy-style';
		style.textContent = `
			.rsl-ie-sync-button-busy .rsl-ie-button-spinner { display:inline-block; width:12px; height:12px; margin-right:7px; vertical-align:-2px; border:2px solid currentColor; border-right-color:transparent; border-radius:50%; animation:rsl-ie-button-spin .8s linear infinite; }
			.rsl-ie-sync-button-busy .rsl-ie-button-dots { display:inline-block; width:1.2em; text-align:left; vertical-align:bottom; }
			.rsl-ie-sync-button-busy .rsl-ie-button-dots span { animation:rsl-ie-button-dot 1.2s infinite; }
			.rsl-ie-sync-button-busy .rsl-ie-button-dots span:nth-child(2) { animation-delay:.2s; }
			.rsl-ie-sync-button-busy .rsl-ie-button-dots span:nth-child(3) { animation-delay:.4s; }
			@keyframes rsl-ie-button-spin { to { transform:rotate(360deg); } }
			@keyframes rsl-ie-button-dot { 0%, 80%, 100% { opacity:.2; } 40% { opacity:1; } }
			@media (prefers-reduced-motion:reduce) { .rsl-ie-sync-button-busy .rsl-ie-button-spinner, .rsl-ie-sync-button-busy .rsl-ie-button-dots span { animation:none; } }
		`;
		document.head.appendChild( style );
	}
	const original = $button.html();
	const disabled = $button.prop( 'disabled' );
	const ariaBusy = $button.attr( 'aria-busy' );
	$button
		.prop( 'disabled', true )
		.attr( 'aria-busy', 'true' )
		.addClass( 'rsl-ie-sync-button-busy' )
		.empty();
	const spinner = document.createElement( 'span' );
	spinner.className = 'rsl-ie-button-spinner';
	spinner.setAttribute( 'aria-hidden', 'true' );
	const text = document.createElement( 'span' );
	text.textContent = String( label ).replace( /(?:\.{3}|…)\s*$/, '' );
	const dots = document.createElement( 'span' );
	dots.className = 'rsl-ie-button-dots';
	dots.setAttribute( 'aria-hidden', 'true' );
	for ( let i = 0; i < 3; i++ ) {
		const dot = document.createElement( 'span' );
		dot.textContent = '.';
		dots.appendChild( dot );
	}
	$button.append( spinner, text, dots );
	return () => {
		$button
			.removeClass( 'rsl-ie-sync-button-busy' )
			.html( original )
			.prop( 'disabled', disabled );
		if ( ariaBusy === undefined ) $button.removeAttr( 'aria-busy' );
		else $button.attr( 'aria-busy', ariaBusy );
	};
}
