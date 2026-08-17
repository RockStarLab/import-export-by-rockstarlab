import Utils from './utils';

const MediaHash = {
	init() {
		if ( ! document.getElementById( 'rsl-ie-tools' ) ) {
			return;
		}

		this.initTabs();
		this.initDebugTool();

		if ( ! document.getElementById( 'rsl-ie-media-hash-tool' ) ) {
			return;
		}

		this.$button = jQuery( '#rsl-ie-start-hash-index' );
		this.$spinner = jQuery( '#rsl-ie-hash-spinner' );
		this.$progressWrap = jQuery( '#rsl-ie-hash-progress-wrap' );
		this.$progress = jQuery( '#rsl-ie-hash-progress' );
		this.$progressText = jQuery( '#rsl-ie-hash-progress-text' );
		this.$result = jQuery( '#rsl-ie-hash-result' );
		this.$button.on( 'click', () => this.start() );
		this.loadStatistics();
	},

	initTabs() {
		const $tabs = jQuery( '[data-rsl-ie-tools-tab]' );
		const $panels = jQuery( '[data-rsl-ie-tools-panel]' );

		if ( ! $tabs.length || ! $panels.length ) {
			return;
		}

		const activateTab = ( tab ) => {
			$tabs.removeClass( 'nav-tab-active' );
			$tabs.filter( `[data-rsl-ie-tools-tab="${ tab }"]` ).addClass( 'nav-tab-active' );
			$panels.prop( 'hidden', true );
			$panels.filter( `[data-rsl-ie-tools-panel="${ tab }"]` ).prop( 'hidden', false );

			if ( window.history && window.history.replaceState ) {
				const url = new URL( window.location.href );
				if ( tab === 'media-hash' ) {
					url.searchParams.delete( 'tab' );
				} else {
					url.searchParams.set( 'tab', tab );
				}
				window.history.replaceState( {}, '', url.toString() );
			}
		};

		$tabs.on( 'click', ( event ) => {
			event.preventDefault();
			activateTab( jQuery( event.currentTarget ).data( 'rsl-ie-tools-tab' ) );
		} );

		const requestedTab = new URLSearchParams( window.location.search ).get( 'tab' );
		if ( requestedTab && $tabs.filter( `[data-rsl-ie-tools-tab="${ requestedTab }"]` ).length ) {
			activateTab( requestedTab );
		}
	},

	initDebugTool() {
		this.$debugButton = jQuery( '#rsl-ie-load-debug-info' );
		this.$debugSpinner = jQuery( '#rsl-ie-debug-spinner' );
		this.$debugTextarea = jQuery( '#rsl-ie-debug-site-info' );
		this.$debugCopyButton = jQuery( '#rsl-ie-copy-debug-info' );
		this.$debugResult = jQuery( '#rsl-ie-debug-result' );

		if ( ! this.$debugButton.length ) {
			return;
		}

		this.$debugButton.on( 'click', () => this.loadDebugInfo() );
		this.$debugCopyButton.on( 'click', () => this.copyDebugInfo() );
	},

	loadDebugInfo() {
		this.$debugButton.prop( 'disabled', true );
		this.$debugSpinner.addClass( 'is-active' );
		this.$debugResult
			.prop( 'hidden', true )
			.removeClass( 'notice-error notice-success notice-warning' );

		Utils.ajax( 'get_debug_site_info' )
			.then( ( response ) => {
				this.$debugTextarea.val( response.info || '' ).prop( 'hidden', false );
				this.$debugCopyButton.show();
				this.$debugResult
					.addClass( 'notice-success' )
					.prop( 'hidden', false )
					.find( 'p' )
					.text( 'Site info loaded.' );
			} )
			.catch( ( error ) => {
				this.$debugResult
					.addClass( 'notice-error' )
					.prop( 'hidden', false )
					.find( 'p' )
					.text( String( error || 'Failed to load site info.' ) );
			} )
			.finally( () => {
				this.$debugButton.prop( 'disabled', false );
				this.$debugSpinner.removeClass( 'is-active' );
			} );
	},

	copyDebugInfo() {
		const text = this.$debugTextarea.val() || '';
		if ( ! text ) {
			return;
		}

		const showCopied = () => {
			const original = this.$debugCopyButton.html();
			this.$debugCopyButton.text( 'Copied!' );
			setTimeout( () => this.$debugCopyButton.html( original ), 1600 );
		};

		if ( navigator.clipboard && window.isSecureContext ) {
			navigator.clipboard.writeText( text ).then( showCopied ).catch( () => {
				this.fallbackCopyDebugInfo( text, showCopied );
			} );
			return;
		}

		this.fallbackCopyDebugInfo( text, showCopied );
	},

	fallbackCopyDebugInfo( text, onSuccess ) {
		const textarea = document.createElement( 'textarea' );
		textarea.value = text;
		textarea.setAttribute( 'readonly', 'readonly' );
		textarea.style.cssText = 'position:absolute;left:-9999px;top:0;';
		document.body.appendChild( textarea );
		textarea.select();
		document.execCommand( 'copy' );
		document.body.removeChild( textarea );
		onSuccess();
	},

	loadStatistics() {
		return Utils.ajax( 'get_hash_statistics' ).then( ( stats ) => {
			jQuery( '#rsl-ie-hash-total' ).text( stats.total );
			jQuery( '#rsl-ie-hash-indexed' ).text(
				`${ stats.hashed } (${ stats.percentage }%)`
			);
			jQuery( '#rsl-ie-hash-unindexed' ).text( stats.unhashed );
		} );
	},

	start() {
		this.offset = 0;
		this.processed = 0;
		this.errors = 0;
		this.$button.prop( 'disabled', true );
		this.$spinner.addClass( 'is-active' );
		this.$progressWrap.prop( 'hidden', false );
		this.$result
			.prop( 'hidden', true )
			.removeClass( 'notice-error notice-success notice-warning' );
		this.processBatch();
	},

	processBatch() {
		Utils.ajax( 'bulk_add_hashes', {
			batch_size: 50,
			offset: this.offset,
		} )
			.then( ( result ) => {
				this.offset = result.offset;
				this.processed += result.processed;
				this.errors += result.errors;
				const percentage = result.total
					? Math.min(
							100,
							Math.round( ( this.offset / result.total ) * 100 )
					  )
					: 100;
				this.$progress.val( percentage );
				this.$progressText.text(
					`${ this.offset } / ${ result.total } (${ percentage }%)`
				);

				if ( ! result.complete && result.attempted > 0 ) {
					this.processBatch();
					return;
				}

				this.finish();
			} )
			.catch( ( error ) => this.fail( error ) );
	},

	finish() {
		const i18n = window.rslIeData?.i18n || {};
		const message = this.errors
			? (
					i18n.hashScanCompleteErrors ||
					'Scan complete. Indexed %1$s files; %2$s files could not be read.'
			  )
					.replace( '%1$s', this.processed )
					.replace( '%2$s', this.errors )
			: (
					i18n.hashScanComplete || 'Scan complete. Indexed %s files.'
			  ).replace( '%s', this.processed );
		this.$button.prop( 'disabled', false );
		this.$spinner.removeClass( 'is-active' );
		this.$result
			.addClass( this.errors ? 'notice-warning' : 'notice-success' )
			.prop( 'hidden', false )
			.find( 'p' )
			.text( message );
		this.loadStatistics();
	},

	fail( error ) {
		this.$button.prop( 'disabled', false );
		this.$spinner.removeClass( 'is-active' );
		this.$result
			.addClass( 'notice-error' )
			.prop( 'hidden', false )
			.find( 'p' )
			.text(
				String(
					error ||
						window.rslIeData?.i18n?.hashScanFailed ||
						'The media hash scan failed.'
				)
			);
	},
};

export default MediaHash;
