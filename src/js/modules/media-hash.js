import Utils from './utils';

const MediaHash = {
	init() {
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
