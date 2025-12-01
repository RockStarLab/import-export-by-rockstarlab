/**
 * Utility Functions
 *
 * Common utilities used across the plugin
 */

const Utils = {
	/**
	 * Make AJAX request
	 *
	 * @param {string} action AJAX action name
	 * @param {Object} data Data to send
	 * @param {string} method HTTP method (GET|POST)
	 * @returns {Promise}
	 */
	ajax( action, data = {}, method = 'POST' ) {
		return new Promise( ( resolve, reject ) => {
			const ajaxData = {
				action: action,
				nonce: window.aieData?.nonce || '',
				...data,
			};

			jQuery
				.ajax( {
					url: window.aieData?.ajaxUrl || '/wp-admin/admin-ajax.php',
					type: method,
					data: ajaxData,
					dataType: 'json',
				} )
				.done( ( response ) => {
					if ( response.success ) {
						resolve( response.data || response );
					} else {
						reject( response.data?.message || 'Request failed' );
					}
				} )
				.fail( ( jqXHR, textStatus, errorThrown ) => {
					reject( `AJAX Error: ${ textStatus } - ${ errorThrown }` );
				} );
		} );
	},

	/**
	 * Format file size
	 *
	 * @param {number} bytes File size in bytes
	 * @returns {string} Formatted size
	 */
	formatFileSize( bytes ) {
		if ( bytes === 0 ) return '0 Bytes';

		const k = 1024;
		const sizes = [ 'Bytes', 'KB', 'MB', 'GB' ];
		const i = Math.floor( Math.log( bytes ) / Math.log( k ) );

		return (
			Math.round( ( bytes / Math.pow( k, i ) ) * 100 ) / 100 +
			' ' +
			sizes[ i ]
		);
	},

	/**
	 * Format duration
	 *
	 * @param {number} seconds Duration in seconds
	 * @returns {string} Formatted duration
	 */
	formatDuration( seconds ) {
		if ( seconds < 60 ) {
			return Math.round( seconds ) + 's';
		}

		const minutes = Math.floor( seconds / 60 );
		const secs = Math.round( seconds % 60 );

		if ( minutes < 60 ) {
			return `${ minutes }m ${ secs }s`;
		}

		const hours = Math.floor( minutes / 60 );
		const mins = minutes % 60;

		return `${ hours }h ${ mins }m`;
	},

	/**
	 * Debounce function
	 *
	 * @param {Function} func Function to debounce
	 * @param {number} wait Wait time in ms
	 * @returns {Function}
	 */
	debounce( func, wait = 300 ) {
		let timeout;
		return function ( ...args ) {
			const context = this;
			clearTimeout( timeout );
			timeout = setTimeout( () => func.apply( context, args ), wait );
		};
	},

	/**
	 * Show notice message
	 *
	 * @param {string} message Message text
	 * @param {string} type Notice type (success|error|warning|info)
	 */
	showNotice( message, type = 'info' ) {
		const noticeClass = `notice notice-${ type } is-dismissible`;
		const noticeHtml = `
			<div class="${ noticeClass }">
				<p>${ message }</p>
				<button type="button" class="notice-dismiss">
					<span class="screen-reader-text">Dismiss this notice.</span>
				</button>
			</div>
		`;

		const $notice = jQuery( noticeHtml );
		jQuery( '.wrap > h1' ).after( $notice );

		// Auto dismiss after 5 seconds
		setTimeout( () => {
			$notice.fadeOut( () => $notice.remove() );
		}, 5000 );

		// Manual dismiss
		$notice.on( 'click', '.notice-dismiss', function () {
			$notice.fadeOut( () => $notice.remove() );
		} );
	},

	/**
	 * Validate file
	 *
	 * @param {File} file File object
	 * @param {Array} allowedTypes Allowed MIME types
	 * @param {number} maxSize Max size in bytes
	 * @returns {Object} Validation result
	 */
	validateFile( file, allowedTypes = [], maxSize = 50 * 1024 * 1024 ) {
		const errors = [];

		// Check file size
		if ( file.size > maxSize ) {
			errors.push(
				`File size (${ this.formatFileSize(
					file.size
				) }) exceeds maximum allowed size (${ this.formatFileSize(
					maxSize
				) })`
			);
		}

		// Check file type
		if ( allowedTypes.length > 0 ) {
			const fileExt = file.name.split( '.' ).pop().toLowerCase();
			const isAllowed = allowedTypes.some( ( type ) => {
				if ( type.startsWith( '.' ) ) {
					return type.substring( 1 ) === fileExt;
				}
				return file.type === type;
			} );

			if ( ! isAllowed ) {
				errors.push(
					`File type .${ fileExt } is not allowed. Allowed types: ${ allowedTypes.join(
						', '
					) }`
				);
			}
		}

		return {
			valid: errors.length === 0,
			errors: errors,
		};
	},

	/**
	 * Parse CSV string to array
	 *
	 * @param {string} csv CSV string
	 * @param {string} delimiter Delimiter character
	 * @returns {Array} Parsed data
	 */
	parseCSV( csv, delimiter = ',' ) {
		const lines = csv.split( '\n' );
		const result = [];

		for ( const line of lines ) {
			if ( line.trim() === '' ) continue;

			const row = [];
			let current = '';
			let inQuotes = false;

			for ( let i = 0; i < line.length; i++ ) {
				const char = line[ i ];

				if ( char === '"' ) {
					inQuotes = ! inQuotes;
				} else if ( char === delimiter && ! inQuotes ) {
					row.push( current.trim() );
					current = '';
				} else {
					current += char;
				}
			}

			row.push( current.trim() );
			result.push( row );
		}

		return result;
	},

	/**
	 * Escape HTML
	 *
	 * @param {string} html HTML string
	 * @returns {string} Escaped HTML
	 */
	escapeHtml( html ) {
		const div = document.createElement( 'div' );
		div.textContent = html;
		return div.innerHTML;
	},

	/**
	 * Get URL parameter
	 *
	 * @param {string} name Parameter name
	 * @returns {string|null} Parameter value
	 */
	getUrlParameter( name ) {
		const urlParams = new URLSearchParams( window.location.search );
		return urlParams.get( name );
	},

	/**
	 * Download file from URL
	 *
	 * @param {string} url File URL
	 * @param {string} filename Filename for download
	 */
	downloadFile( url, filename ) {
		const link = document.createElement( 'a' );
		link.href = url;
		link.download = filename || 'export.csv';
		document.body.appendChild( link );
		link.click();
		document.body.removeChild( link );
	},

	/**
	 * Generate UUID
	 *
	 * @returns {string} UUID
	 */
	generateUUID() {
		return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(
			/[xy]/g,
			function ( c ) {
				const r = ( Math.random() * 16 ) | 0;
				const v = c === 'x' ? r : ( r & 0x3 ) | 0x8;
				return v.toString( 16 );
			}
		);
	},

	/**
	 * Create progress bar element
	 *
	 * @returns {jQuery} Progress bar element
	 */
	createProgressBar() {
		return jQuery( `
			<div class="aie-progress-container">
				<div class="aie-progress-bar">
					<div class="aie-progress-bar-fill" style="width: 0%;"></div>
				</div>
				<div class="aie-progress-stats">
					<div class="aie-progress-percentage">0%</div>
					<div class="aie-progress-details">
						<span class="aie-processed">0</span> / <span class="aie-total">0</span> items
					</div>
				</div>
			</div>
		` );
	},

	/**
	 * Update progress bar
	 *
	 * @param {jQuery} $container Progress container
	 * @param {Object} data Progress data
	 */
	updateProgressBar( $container, data ) {
		const percentage = data.percentage || 0;
		const processed = data.processed || 0;
		const total = data.total || 0;

		$container
			.find( '.aie-progress-bar-fill' )
			.css( 'width', percentage + '%' );
		$container
			.find( '.aie-progress-percentage' )
			.text( Math.round( percentage ) + '%' );
		$container.find( '.aie-processed' ).text( processed );
		$container.find( '.aie-total' ).text( total );

		// Update estimates if available
		if ( data.estimates ) {
			if ( data.estimates.elapsed_formatted ) {
				$container
					.find( '.aie-elapsed-time' )
					.text( data.estimates.elapsed_formatted );
			}
			if ( data.estimates.remaining_formatted ) {
				$container
					.find( '.aie-remaining-time' )
					.text( data.estimates.remaining_formatted );
			}
			if ( data.estimates.items_per_second ) {
				$container
					.find( '.aie-items-per-second' )
					.text(
						data.estimates.items_per_second.toFixed( 1 ) +
							' items/s'
					);
			}
		}
	},

	/**
	 * Handle errors
	 *
	 * @param {Error|string} error Error object or message
	 * @param {string} context Error context
	 */
	handleError( error, context = '' ) {
		console.error(
			`AIE Error${ context ? ' (' + context + ')' : '' }:`,
			error
		);

		const message = error.message || error.toString();
		this.showNotice( message, 'error' );
	},

	/**
	 * Format bytes (alias for formatFileSize)
	 *
	 * @param {number} bytes File size in bytes
	 * @returns {string} Formatted size
	 */
	formatBytes( bytes ) {
		return this.formatFileSize( bytes );
	},
};

export default Utils;
