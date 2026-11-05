/**
 * Welcome Module
 *
 * Handles interactions on the Welcome page (promo code copy, etc).
 */

const WelcomeModule = {
	/**
	 * Initialize module.
	 */
	init() {
		this.initAgentDemo();

		if ( ! jQuery( '.rsl-ie-card--promo' ).length ) {
			return;
		}

		this.bindEvents();
	},

	/** Decorative preview only: never sends prompts or runs jobs. */
	initAgentDemo() {
		const hero = document.querySelector( '.rsl-ie-agent-hero' );
		if ( ! hero ) return;
		const output = hero.querySelector( '.rsl-ie-agent-prompt-text' );
		const prompts = Array.from(
			hero.querySelectorAll( '.rsl-ie-agent-prompts li' ),
			( item ) => Array.from( item.textContent.trim() )
		);
		const button = hero.querySelector( '.rsl-ie-agent-pause' );
		const motion = window.matchMedia( '(prefers-reduced-motion: reduce)' );
		let paused = false;
		let timer;
		let index = 0;
		let character = 0;
		let holding = false;
		const tick = () => {
			if ( holding ) {
				index = ( index + 1 ) % prompts.length;
				character = 0;
				holding = false;
			}
			character++;
			output.textContent = prompts[ index ]
				.slice( 0, character )
				.join( '' );
			holding = character >= prompts[ index ].length;
			timer = window.setTimeout( tick, holding ? 4200 : 48 );
		};
		const update = () => {
			window.clearTimeout( timer );
			const stopped = paused || motion.matches || document.hidden;
			hero.classList.toggle( 'is-paused', stopped );
			button.setAttribute( 'aria-pressed', String( paused ) );
			button.hidden = motion.matches;
			if ( motion.matches )
				output.textContent = prompts[ index ].join( '' );
			if ( ! stopped ) timer = window.setTimeout( tick, 600 );
		};
		output.textContent = prompts[ 0 ].join( '' );
		button.addEventListener( 'click', () => {
			paused = ! paused;
			update();
		} );
		motion.addEventListener( 'change', update );
		document.addEventListener( 'visibilitychange', update );
		update();
	},

	/**
	 * Bind event handlers.
	 */
	bindEvents() {
		const $ = jQuery;

		$( document ).on( 'click', '.rsl-ie-copy-promo-code', function ( e ) {
			e.preventDefault();

			const $btn = $( this );
			const codeEl = document.getElementById( 'rsl-ie-promo-code' );
			const promoCode = codeEl ? ( codeEl.textContent || '' ).trim() : '';

			if ( ! promoCode ) {
				return;
			}

			const copiedLabel =
				'<span class="dashicons dashicons-yes"></span> ' +
				( window.rslIeData &&
				window.rslIeData.i18n &&
				window.rslIeData.i18n.copied
					? window.rslIeData.i18n.copied
					: 'Copied!' );

			const original = $btn.html();

			const onSuccess = function () {
				$btn.html( copiedLabel );
				$btn.addClass( 'rsl-ie-copied' );
				setTimeout( function () {
					$btn.html( original );
					$btn.removeClass( 'rsl-ie-copied' );
				}, 2000 );
			};

			const fallbackCopy = function ( text, callback ) {
				const ta = document.createElement( 'textarea' );
				ta.value = text;
				ta.style.cssText =
					'position:fixed;top:-9999px;left:-9999px;opacity:0;';
				document.body.appendChild( ta );
				ta.focus();
				ta.select();
				try {
					document.execCommand( 'copy' );
					if ( callback ) {
						callback();
					}
				} catch ( err ) {
					// Ignore.
				}
				document.body.removeChild( ta );
			};

			if ( navigator.clipboard && window.isSecureContext ) {
				navigator.clipboard
					.writeText( promoCode )
					.then( onSuccess )
					.catch( function () {
						fallbackCopy( promoCode, onSuccess );
					} );
			} else {
				fallbackCopy( promoCode, onSuccess );
			}
		} );
	},
};

export default WelcomeModule;
