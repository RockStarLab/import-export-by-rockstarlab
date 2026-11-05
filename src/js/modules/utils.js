/**
 * Utility Functions
 *
 * Common utilities used across the plugin
 */

const AJAX_PREFIX = 'rsl_ie_';

const getClientData = () => window.rslIeData || {};

const normalizeAjaxAction = ( action ) => {
	if ( ! action || typeof action !== 'string' ) {
		return action;
	}

	if ( action.startsWith( AJAX_PREFIX ) ) {
		return action;
	}

	return AJAX_PREFIX + action;
};

const getActionNonce = ( action, clientData = getClientData() ) => {
	const normalizedAction = normalizeAjaxAction( action );
	return clientData?.nonces?.[ normalizedAction ] || '';
};

// Legacy modules use direct jQuery AJAX calls. Always replace their nonce with
// the nonce belonging to the actual endpoint so callers cannot accidentally
// reuse a nonce for another action.
if ( typeof jQuery !== 'undefined' ) {
	jQuery.ajaxPrefilter( ( options, originalOptions ) => {
		const data = originalOptions.data;
		let action = '';

		if ( typeof FormData !== 'undefined' && data instanceof FormData ) {
			action = data.get( 'action' ) || '';
		} else if ( typeof data === 'string' ) {
			action = new URLSearchParams( data ).get( 'action' ) || '';
		} else if ( data && typeof data === 'object' ) {
			action = data.action || '';
		}

		const nonce = getActionNonce( action );
		if ( ! nonce ) {
			return;
		}

		if (
			typeof FormData !== 'undefined' &&
			options.data instanceof FormData
		) {
			options.data.set( 'nonce', nonce );
		} else if ( typeof options.data === 'string' ) {
			const params = new URLSearchParams( options.data );
			params.set( 'nonce', nonce );
			options.data = params.toString();
		} else if ( options.data && typeof options.data === 'object' ) {
			options.data.nonce = nonce;
		}
	} );
}

// Fetch-based modules (notably chunk uploads and the code editor) bypass
// jQuery, so apply the same endpoint-bound nonce rule to their request body.
if ( typeof window.fetch === 'function' && ! window.rslIeFetchSecured ) {
	const nativeFetch = window.fetch.bind( window );
	window.fetch = ( input, init = {} ) => {
		const body = init.body;
		let action = '';

		if ( typeof FormData !== 'undefined' && body instanceof FormData ) {
			action = body.get( 'action' ) || '';
		} else if ( body instanceof URLSearchParams ) {
			action = body.get( 'action' ) || '';
		}

		const nonce = getActionNonce( action );
		if ( nonce && body && typeof body.set === 'function' ) {
			body.set( 'nonce', nonce );
		}

		return nativeFetch( input, init );
	};
	window.rslIeFetchSecured = true;
}

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
			const clientData = getClientData();

			const normalizedAction = normalizeAjaxAction( action );
			const ajaxData = {
				...data,
				action: normalizedAction,
				nonce: getActionNonce( normalizedAction, clientData ),
			};

			jQuery
				.ajax( {
					url: clientData?.ajaxUrl || window.ajaxurl || '',
					type: method,
					data: ajaxData,
					dataType: 'json',
				} )
				.done( ( response ) => {
					if ( response.success ) {
						resolve( response.data || response );
					} else {
						reject(
							response.data?.message ||
								response.data ||
								'Request failed'
						);
					}
				} )
				.fail( ( jqXHR, textStatus, errorThrown ) => {
					// Try to parse error response
					let errorMessage = 'Request failed';

					if (
						jqXHR.responseJSON &&
						jqXHR.responseJSON.data &&
						jqXHR.responseJSON.data.message
					) {
						errorMessage = jqXHR.responseJSON.data.message;
					} else if ( jqXHR.responseText ) {
						try {
							const parsed = JSON.parse( jqXHR.responseText );
							errorMessage =
								parsed.data?.message ||
								parsed.message ||
								errorMessage;
						} catch ( e ) {
							errorMessage =
								errorThrown || textStatus || errorMessage;
						}
					} else if ( errorThrown ) {
						errorMessage = errorThrown;
					}

					reject( errorMessage );
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
			return (
				window.rslIeData?.i18n?.timeFormatSeconds || '%ds'
			).replace( '%d', Math.round( seconds ) );
		}

		const minutes = Math.floor( seconds / 60 );
		const secs = Math.round( seconds % 60 );

		if ( minutes < 60 ) {
			return (
				window.rslIeData?.i18n?.timeFormatMinutesSeconds ||
				'%1$sm %2$ss'
			)
				.replace( '%1$s', minutes )
				.replace( '%2$s', secs );
		}

		const hours = Math.floor( minutes / 60 );
		const mins = minutes % 60;

		return (
			window.rslIeData?.i18n?.timeFormatHoursMinutes || '%1$sh %2$sm'
		)
			.replace( '%1$s', hours )
			.replace( '%2$s', mins );
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
		const noticeClass = 'notice notice-' + type + ' is-dismissible';
		const dismissText =
			window.rslIeData?.i18n?.dismissNotice || 'Dismiss this notice.';

		// Remove all existing notices to show only one at a time
		jQuery( '.wrap > .notice' ).remove();

		const $notice = jQuery(
			'<div class="' +
				noticeClass +
				'">' +
				'<p>' +
				message +
				'</p>' +
				'<button type="button" class="notice-dismiss">' +
				'<span class="screen-reader-text">' +
				dismissText +
				'</span>' +
				'</button>' +
				'</div>'
		);

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
			const fileSizeMsg = (
				window.rslIeData?.i18n?.fileSizeExceeds ||
				'File size (%1$s) exceeds maximum allowed size (%2$s)'
			)
				.replace( '%1$s', this.formatFileSize( file.size ) )
				.replace( '%2$s', this.formatFileSize( maxSize ) );
			errors.push( fileSizeMsg );
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
				const fileTypeMsg = (
					window.rslIeData?.i18n?.fileTypeNotAllowed ||
					'File type .%1$s is not allowed. Allowed types: %2$s'
				)
					.replace( '%1$s', fileExt )
					.replace( '%2$s', allowedTypes.join( ', ' ) );
				errors.push( fileTypeMsg );
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
		const itemsText = window.rslIeData?.i18n?.jobItems || 'items';
		return jQuery(
			'<div class="rsl-ie-progress-container">' +
				'<div class="rsl-ie-progress-bar">' +
				'<div class="rsl-ie-progress-bar-fill" style="width: 0%;"></div>' +
				'</div>' +
				'<div class="rsl-ie-progress-stats">' +
				'<div class="rsl-ie-progress-percentage">0%</div>' +
				'<div class="rsl-ie-progress-details">' +
				'<span class="rsl-ie-processed">0</span> / <span class="rsl-ie-total">0</span> ' +
				itemsText +
				'</div>' +
				'</div>' +
				'</div>'
		);
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
			.find( '.rsl-ie-progress-bar-fill' )
			.css( 'width', percentage + '%' );
		$container
			.find( '.rsl-ie-progress-percentage' )
			.text( Math.round( percentage ) + '%' );
		$container.find( '.rsl-ie-processed' ).text( processed );
		$container.find( '.rsl-ie-total' ).text( total );

		// Update estimates if available
		if ( data.estimates ) {
			if ( data.estimates.elapsed_formatted ) {
				$container
					.find( '.rsl-ie-elapsed-time' )
					.text( data.estimates.elapsed_formatted );
			}
			if ( data.estimates.remaining_formatted ) {
				$container
					.find( '.rsl-ie-remaining-time' )
					.text( data.estimates.remaining_formatted );
			}
			if ( data.estimates.items_per_second ) {
				$container
					.find( '.rsl-ie-items-per-second' )
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
