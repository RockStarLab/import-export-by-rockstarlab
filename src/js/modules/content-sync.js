/**
 * Content Sync Module
 *
 * Handles content synchronization between sites
 */

const ContentSyncModule = {
	/**
	 * Initialize module
	 */
	init() {
		if ( ! jQuery( '#rsl-ie-content-sync' ).length ) {
			return;
		}

		this.bindEvents();
		this.loadSites();
		this.loadMySiteInfo();
	},

	/**
	 * Bind event handlers
	 */
	bindEvents() {
		const $ = jQuery;

		// Add site button
		$( '#rsl-ie-add-site-btn' ).on( 'click', () =>
			this.showAddSiteModal()
		);

		// Save site button
		$( '#rsl-ie-save-site-btn' ).on( 'click', () => this.saveSite() );

		// Modal close buttons
		$( '.rsl-ie-modal-close' ).on( 'click', function () {
			$( this ).closest( '.rsl-ie-modal' ).hide();
		} );

		// Close modal on outside click
		$( '.rsl-ie-modal' ).on( 'click', function ( e ) {
			if ( $( e.target ).hasClass( 'rsl-ie-modal' ) ) {
				$( this ).hide();
			}
		} );

		// Toggle my site info
		$( '#rsl-ie-toggle-my-site' ).on( 'click', () =>
			this.toggleMySiteInfo()
		);

		// Copy my API key
		$( '#rsl-ie-copy-my-key' ).on( 'click', () => this.copyMyApiKey() );

		// Regenerate my API key
		$( '#rsl-ie-regenerate-my-key' ).on( 'click', () =>
			this.regenerateMyApiKey()
		);

		// Delegated events for dynamic content
		$( document ).on( 'click', '.rsl-ie-edit-site', ( e ) => {
			const siteId = $( e.currentTarget ).data( 'site-id' );
			this.showEditSiteModal( siteId );
		} );

		$( document ).on( 'click', '.rsl-ie-delete-site', ( e ) => {
			const siteId = $( e.currentTarget ).data( 'site-id' );
			this.deleteSite( siteId );
		} );

		$( document ).on( 'click', '.rsl-ie-test-connection', ( e ) => {
			const siteId = $( e.currentTarget ).data( 'site-id' );
			this.testConnection( siteId );
		} );

		$( document ).on( 'click', '.rsl-ie-regenerate-key', ( e ) => {
			const siteId = $( e.currentTarget ).data( 'site-id' );
			this.regenerateKey( siteId );
		} );
	},

	/**
	 * Load all connected sites
	 */
	loadSites() {
		jQuery.ajax( {
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'rsl_ie_content_sync_get_sites',
				nonce: rslIeContentSync.nonce,
			},
			success: ( response ) => {
				if ( response.success ) {
					this.renderSites( response.data.sites );
					this.updateStats( response.data.stats );
				} else {
					this.showNotice(
						'error',
						response.data.message ||
							window.rslIeData.i18n.failedLoadSites
					);
				}
			},
			error: () => {
				this.showNotice(
					'error',
					window.rslIeData.i18n.failedLoadSites
				);
			},
		} );
	},

	/**
	 * Load this site's information
	 */
	loadMySiteInfo() {
		jQuery.ajax( {
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'rsl_ie_content_sync_get_my_key',
				nonce: rslIeContentSync.nonce,
			},
			success: ( response ) => {
				if ( response.success ) {
					jQuery( '#rsl-ie-my-site-name' ).val(
						response.data.site_name
					);
					jQuery( '#rsl-ie-my-site-url' ).val(
						response.data.site_url
					);
					jQuery( '#rsl-ie-my-site-key' ).val(
						response.data.site_key
					);
				}
			},
		} );
	},

	/**
	 * Render sites table
	 */
	renderSites( sites ) {
		const $ = jQuery;
		const $tbody = $( '#rsl-ie-sites-list' );
		$tbody.empty();

		if ( ! sites || sites.length === 0 ) {
			$tbody.html( `
				<tr class="rsl-ie-no-sites">
					<td colspan="5" style="text-align: center; padding: 40px;">
						<span class="dashicons dashicons-admin-site" style="font-size: 48px; opacity: 0.3;"></span>
						<p style="margin-top: 40px">${
							window.rslIeData.i18n.noConnectedSites ||
							'No connected sites yet. Add your first connection!'
						}</p>
					</td>
				</tr>
			` );
			return;
		}

		sites.forEach( ( site ) => {
			const statusClass = `rsl-ie-status-${ site.status }`;
			const lastSync = site.last_sync_at
				? new Date( site.last_sync_at ).toLocaleString()
				: window.rslIeData.i18n.never || 'Never';

			const row = `
				<tr data-site-id="${ site.id }">
					<td class="column-name">
						<strong>${ this.escapeHtml( site.name ) }</strong>
					</td>
					<td class="column-url">
						<a href="${ this.escapeHtml(
							site.remote_url
						) }" target="_blank" rel="noopener noreferrer">
							${ this.escapeHtml( site.remote_url ) }
						</a>
					</td>
					<td class="column-status">
						<span class="rsl-ie-status-badge ${ statusClass }">
							${ this.escapeHtml( site.status ) }
						</span>
					</td>
					<td class="column-last-sync">
						${ lastSync }
					</td>
					<td class="column-actions">
						<button type="button" class="button button-small rsl-ie-test-connection" data-site-id="${
							site.id
						}" title="${
							window.rslIeData.i18n.testConnection ||
							'Test Connection'
						}">
							<span class="dashicons dashicons-update"></span>
						</button>
						<button type="button" class="button button-small rsl-ie-edit-site" data-site-id="${
							site.id
						}" title="${ window.rslIeData.i18n.edit || 'Edit' }">
							<span class="dashicons dashicons-edit"></span>
						</button>
						<button type="button" class="button button-small rsl-ie-delete-site" data-site-id="${
							site.id
						}" title="${
							window.rslIeData.i18n.delete || 'Delete'
						}">
							<span class="dashicons dashicons-trash"></span>
						</button>
					</td>
				</tr>
			`;

			$tbody.append( row );
		} );
	},

	/**
	 * Update statistics
	 */
	updateStats( stats ) {
		jQuery( '#rsl-ie-stat-total' ).text( stats.total || 0 );
		jQuery( '#rsl-ie-stat-active' ).text( stats.active || 0 );
		jQuery( '#rsl-ie-stat-error' ).text( stats.error || 0 );
	},

	/**
	 * Show add site modal
	 */
	showAddSiteModal() {
		const $ = jQuery;
		$( '#rsl-ie-modal-title' ).text(
			window.rslIeData.i18n.addNewSite || 'Add New Site'
		);
		$( '#rsl-ie-site-form' )[ 0 ].reset();
		$( '#rsl-ie-site-id' ).val( '' );
		$( '#rsl-ie-site-api-key' ).prop( 'required', true );
		this.hideModalNotice();
		$( '#rsl-ie-site-modal' ).show();
	},

	/**
	 * Show edit site modal
	 */
	showEditSiteModal( siteId ) {
		const $ = jQuery;
		const $row = $( `tr[data-site-id="${ siteId }"]` );
		const site = this.getSiteFromRow( $row );

		if ( ! site ) return;

		$( '#rsl-ie-modal-title' ).text(
			window.rslIeData.i18n.editSite || 'Edit Site'
		);
		$( '#rsl-ie-site-id' ).val( siteId );
		$( '#rsl-ie-site-name' ).val( site.name );
		$( '#rsl-ie-site-url' ).val( site.url );
		$( '#rsl-ie-site-api-key' ).prop( 'required', false ).val( '' );
		this.hideModalNotice();
		$( '#rsl-ie-site-modal' ).show();
	},

	/**
	 * Get site data from row
	 */
	getSiteFromRow( $row ) {
		if ( ! $row.length ) return null;

		return {
			name: $row.find( '.column-name strong' ).text(),
			url: $row.find( '.column-url a' ).attr( 'href' ),
		};
	},

	/**
	 * Save site (add or update)
	 */
	saveSite() {
		const $ = jQuery;
		const $form = $( '#rsl-ie-site-form' );
		const siteId = $( '#rsl-ie-site-id' ).val();
		const isEdit = !! siteId;

		// Basic validation
		if ( ! $form[ 0 ].checkValidity() ) {
			$form[ 0 ].reportValidity();
			return;
		}

		// Hide any previous notifications
		this.hideModalNotice();

		const data = {
			action: isEdit
				? 'rsl_ie_content_sync_update_site'
				: 'rsl_ie_content_sync_add_site',
			nonce: rslIeContentSync.nonce,
			name: $( '#rsl-ie-site-name' ).val(),
			remote_url: $( '#rsl-ie-site-url' ).val(),
			direction: 'bidirectional', // Always bidirectional
		};

		// Get API key value
		const apiKey = $( '#rsl-ie-site-api-key' ).val();

		if ( isEdit ) {
			data.site_id = siteId;
			// If API key was provided during edit, include it (to update/validate)
			if ( apiKey && apiKey.trim() !== '' ) {
				data.api_key = apiKey;
			}
		} else {
			data.api_key = apiKey;
		}

		const $saveBtn = $( '#rsl-ie-save-site-btn' );
		// Check if we're validating API key
		const hasApiKey = data.api_key && data.api_key.trim() !== '';
		const buttonText =
			isEdit && ! hasApiKey
				? window.rslIeData.i18n.updating || 'Updating...'
				: window.rslIeData.i18n.validatingSaving ||
				  'Validating & Saving...';
		$saveBtn.prop( 'disabled', true ).text( buttonText );

		// Show info message when validating API key
		if ( hasApiKey ) {
			this.showModalNotice(
				'info',
				window.rslIeData.i18n.validatingApiKey ||
					'Validating API key...',
				window.rslIeData.i18n.pleaseWaitVerifying ||
					'Please wait while we verify the connection to the remote site.'
			);
		}

		$.ajax( {
			url: ajaxurl,
			type: 'POST',
			data: data,
			success: ( response ) => {
				if ( response.success ) {
					const message =
						response.data.message ||
						window.rslIeData.i18n.operationCompleted ||
						'Operation completed successfully';

					// Check if no changes were made
					if ( message.includes( 'No changes' ) ) {
						this.showModalNotice(
							'info',
							window.rslIeData.i18n.noChanges || 'No Changes',
							message
						);
						// Close modal after delay
						setTimeout( () => {
							$( '#rsl-ie-site-modal' ).hide();
						}, 2000 );
					} else {
						this.showModalNotice(
							'success',
							window.rslIeData.i18n.success || 'Success!',
							message
						);
						// Close modal after short delay
						setTimeout( () => {
							$( '#rsl-ie-site-modal' ).hide();
							this.loadSites();
							this.showNotice( 'success', message );
						}, 1500 );
					}
				} else {
					this.showModalNotice(
						'error',
						window.rslIeData.i18n.validationFailed ||
							'Validation Failed',
						response.data.message ||
							window.rslIeData.i18n.failedSaveSiteConnection ||
							'Failed to save site connection'
					);
				}
			},
			error: ( xhr ) => {
				let errorTitle =
					window.rslIeData.i18n.connectionError || 'Connection Error';
				let errorMessage =
					window.rslIeData.i18n.unexpectedError ||
					'An unexpected error occurred while trying to save the site connection.';
				let errorDetails = [];

				// Check if we have a response from the server with error message
				// WordPress AJAX sends: { success: false, message: "...", data: {...} }
				if ( xhr.responseJSON ) {
					// Try to get message from different possible locations
					if ( xhr.responseJSON.message ) {
						errorMessage = xhr.responseJSON.message;
					} else if (
						xhr.responseJSON.data &&
						xhr.responseJSON.data.message
					) {
						errorMessage = xhr.responseJSON.data.message;
					}

					// Parse specific error types and add helpful details
					if (
						errorMessage.includes( 'Cannot connect to remote site' )
					) {
						errorTitle =
							window.rslIeData.i18n.connectionFailed ||
							'Connection Failed';
						errorDetails.push(
							window.rslIeData.i18n.possibleReasons ||
								'Possible reasons:'
						);
						errorDetails.push(
							window.rslIeData.i18n.urlIncorrect ||
								'- The URL is incorrect or not accessible'
						);
						errorDetails.push(
							window.rslIeData.i18n.remoteSiteOffline ||
								'- The remote site is offline'
						);
						errorDetails.push(
							window.rslIeData.i18n.networkFirewall ||
								'- Network or firewall issues are blocking the connection'
						);
					} else if ( errorMessage.includes( 'Invalid API key' ) ) {
						errorTitle =
							window.rslIeData.i18n.invalidApiKey ||
							'Invalid API Key';
						errorDetails.push(
							window.rslIeData.i18n.toResolveIssue ||
								'To resolve this issue:'
						);
						errorDetails.push(
							window.rslIeData.i18n.goToContentSync ||
								'- Go to Content Sync page on the remote site'
						);
						errorDetails.push(
							window.rslIeData.i18n.clickShowDetails ||
								'- Click "Show Details" to reveal the API key'
						);
						errorDetails.push(
							window.rslIeData.i18n.copyEntireKey ||
								'- Copy the entire key and paste it here'
						);
					} else if (
						errorMessage.includes( 'plugin is not installed' ) ||
						errorMessage.includes( 'plugin is not active' )
					) {
						errorTitle =
							window.rslIeData.i18n.pluginNotFound ||
							'Plugin Not Found';
						// No additional details needed, message is clear
					} else if ( errorMessage.includes( 'already connected' ) ) {
						errorTitle =
							window.rslIeData.i18n.duplicateConnection ||
							'Duplicate Connection';
						errorDetails.push(
							window.rslIeData.i18n.siteAlreadyConnected ||
								'This site URL is already in your connected sites list.'
						);
					} else if ( errorMessage.includes( 'required' ) ) {
						errorTitle =
							window.rslIeData.i18n.validationError ||
							'Validation Error';
						// Field validation errors are clear enough
					} else {
						errorTitle = window.rslIeData.i18n.error || 'Error';
						// Use the server message as-is for other errors
					}
				} else if ( xhr.status === 0 ) {
					errorTitle =
						window.rslIeData.i18n.networkError || 'Network Error';
					errorMessage =
						window.rslIeData.i18n.unableConnectServer ||
						'Unable to connect to the server. Please check your internet connection.';
				} else if ( xhr.status >= 500 ) {
					errorTitle =
						window.rslIeData.i18n.serverError || 'Server Error';
					errorMessage = (
						window.rslIeData.i18n.serverReturnedError ||
						'The server returned an error (%s). Please try again later.'
					).replace( '%s', xhr.status );
				} else if ( xhr.status === 404 ) {
					errorTitle = window.rslIeData.i18n.notFound || 'Not Found';
					errorMessage =
						window.rslIeData.i18n.endpointNotFound ||
						'The requested endpoint was not found. Please check if the plugin is properly installed.';
				}

				this.showModalNotice(
					'error',
					errorTitle,
					errorMessage,
					errorDetails
				);
			},
			complete: () => {
				$saveBtn
					.prop( 'disabled', false )
					.text(
						window.rslIeData.i18n.saveConnection ||
							'Save Connection'
					);
			},
		} );
	},

	/**
	 * Delete site
	 */
	deleteSite( siteId ) {
		if ( ! confirm( window.rslIeData.i18n.confirmDeleteSiteConnection ) ) {
			return;
		}

		jQuery.ajax( {
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'rsl_ie_content_sync_delete_site',
				nonce: rslIeContentSync.nonce,
				site_id: siteId,
			},
			success: ( response ) => {
				if ( response.success ) {
					this.showNotice( 'success', response.data.message );
					this.loadSites();
				} else {
					this.showNotice(
						'error',
						response.data.message ||
							window.rslIeData.i18n.failedDeleteSite
					);
				}
			},
			error: () => {
				this.showNotice(
					'error',
					window.rslIeData.i18n.failedDeleteSite
				);
			},
		} );
	},

	/**
	 * Test connection to remote site
	 */
	testConnection( siteId ) {
		const $ = jQuery;
		const $btn = $( `.rsl-ie-test-connection[data-site-id="${ siteId }"]` );
		$btn.prop( 'disabled', true );

		$.ajax( {
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'rsl_ie_content_sync_test_connection',
				nonce: rslIeContentSync.nonce,
				site_id: siteId,
			},
			success: ( response ) => {
				if ( response.success ) {
					this.showNotice( 'success', response.data.message );
				} else {
					this.showNotice(
						'error',
						response.data.message ||
							window.rslIeData.i18n.connectionTestFailed
					);
				}
				// Always reload sites to update stats
				this.loadSites();
			},
			error: () => {
				this.showNotice(
					'error',
					window.rslIeData.i18n.connectionTestFailed
				);
				this.loadSites();
			},
			complete: () => {
				$btn.prop( 'disabled', false );
			},
		} );
	},

	/**
	 * Regenerate API key for a site
	 */
	regenerateKey( siteId ) {
		if ( ! confirm( window.rslIeData.i18n.confirmRegenerateSiteKey ) ) {
			return;
		}

		jQuery.ajax( {
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'rsl_ie_content_sync_regenerate_key',
				nonce: rslIeContentSync.nonce,
				site_id: siteId,
			},
			success: ( response ) => {
				if ( response.success ) {
					this.showNotice( 'success', response.data.message );
					alert(
						window.rslIeData.i18n.newApiKey + response.data.api_key
					);
				} else {
					this.showNotice(
						'error',
						response.data.message ||
							window.rslIeData.i18n.failedRegenerateKey
					);
				}
			},
			error: () => {
				this.showNotice(
					'error',
					window.rslIeData.i18n.failedRegenerateKey
				);
			},
		} );
	},

	/**
	 * Toggle my site info visibility
	 */
	toggleMySiteInfo() {
		const $ = jQuery;
		const $info = $( '.rsl-ie-my-site-info' );
		const $btn = $( '#rsl-ie-toggle-my-site' );

		$info.slideToggle( 200, () => {
			if ( $info.is( ':visible' ) ) {
				$btn.html(
					`<span class="dashicons dashicons-hidden"></span> ${
						window.rslIeData.i18n.hideDetails || 'Hide Details'
					}`
				);
			} else {
				$btn.html(
					`<span class="dashicons dashicons-visibility"></span> ${
						window.rslIeData.i18n.showDetails || 'Show Details'
					}`
				);
			}
		} );
	},

	/**
	 * Copy this site's API key to clipboard
	 */
	copyMyApiKey() {
		const $ = jQuery;
		const $input = $( '#rsl-ie-my-site-key' );
		$input.select();
		document.execCommand( 'copy' );

		const $btn = $( '#rsl-ie-copy-my-key' );
		const originalText = $btn.html();

		$btn.html(
			`<span class="dashicons dashicons-yes"></span> ${
				window.rslIeData.i18n.copied || 'Copied!'
			}`
		);

		setTimeout( () => {
			$btn.html( originalText );
		}, 2000 );

		this.showNotice( 'success', window.rslIeData.i18n.apiKeyCopied );
	},

	/**
	 * Regenerate this site's API key
	 */
	regenerateMyApiKey() {
		const $ = jQuery;

		// Confirm action
		if ( ! confirm( window.rslIeData.i18n.confirmRegenerateMyKey ) ) {
			return;
		}

		const $btn = $( '#rsl-ie-regenerate-my-key' );
		const originalText = $btn.html();
		$btn.prop( 'disabled', true ).html(
			`<span class="dashicons dashicons-update"></span> ${
				window.rslIeData.i18n.regenerating || 'Regenerating...'
			}`
		);

		$.ajax( {
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'rsl_ie_content_sync_regenerate_my_key',
				nonce: rslIeContentSync.nonce,
			},
			success: ( response ) => {
				if ( response.success ) {
					// Update the API key field with new key
					$( '#rsl-ie-my-site-key' ).val( response.data.site_key );

					// Show success message
					this.showNotice( 'success', response.data.message );

					// Briefly show success state on button
					$btn.html(
						`<span class="dashicons dashicons-yes"></span> ${
							window.rslIeData.i18n.regenerated || 'Regenerated!'
						}`
					);
					setTimeout( () => {
						$btn.html( originalText );
					}, 3000 );
				} else {
					this.showNotice(
						'error',
						response.data.message ||
							window.rslIeData.i18n.failedRegenerateApiKey
					);
					$btn.html( originalText );
				}
			},
			error: () => {
				this.showNotice(
					'error',
					window.rslIeData.i18n.failedRegenerateApiKey
				);
				$btn.html( originalText );
			},
			complete: () => {
				$btn.prop( 'disabled', false );
			},
		} );
	},

	/**
	 * Show notice message in modal
	 */
	showModalNotice( type, title, message, details = [] ) {
		const $ = jQuery;
		const icons = {
			error: 'warning',
			success: 'yes-alt',
			warning: 'info',
			info: 'info-outline',
		};

		const icon = icons[ type ] || 'info';
		const noticeClass = `notice-${ type }`;

		let detailsHtml = '';
		if ( details.length > 0 ) {
			detailsHtml = '<ul>';
			details.forEach( ( detail ) => {
				detailsHtml += `<li>${ this.escapeHtml( detail ) }</li>`;
			} );
			detailsHtml += '</ul>';
		}

		const $notification = $( `
			<div class="rsl-ie-modal-notification ${ noticeClass }">
				<span class="dashicons dashicons-${ icon }"></span>
				<div class="rsl-ie-notification-content">
					<strong>${ this.escapeHtml( title ) }</strong>
					<p>${ this.escapeHtml( message ) }</p>
					${ detailsHtml }
				</div>
			</div>
		` );

		$( '#rsl-ie-modal-notification' ).html( $notification ).show();
	},

	/**
	 * Hide modal notice
	 */
	hideModalNotice() {
		jQuery( '#rsl-ie-modal-notification' ).hide().empty();
	},

	/**
	 * Show notice message
	 */
	showNotice( type, message ) {
		const $ = jQuery;
		const noticeClass =
			type === 'error'
				? 'notice-error'
				: type === 'success'
				? 'notice-success'
				: 'notice-info';

		const $notice = $( `
			<div class="notice ${ noticeClass } is-dismissible">
				<p>${ this.escapeHtml( message ) }</p>
			</div>
		` );

		$( '#rsl-ie-content-sync h1' ).after( $notice );

		// Auto-dismiss after 5 seconds
		setTimeout( () => {
			$notice.fadeOut( () => $notice.remove() );
		}, 5000 );

		// Make dismissible
		$notice.on( 'click', '.notice-dismiss', function () {
			$( this )
				.closest( '.notice' )
				.fadeOut( () => $( this ).remove() );
		} );
	},

	/**
	 * Escape HTML to prevent XSS
	 */
	escapeHtml( text ) {
		const div = document.createElement( 'div' );
		div.textContent = text;
		return div.innerHTML;
	},
};

export default ContentSyncModule;
