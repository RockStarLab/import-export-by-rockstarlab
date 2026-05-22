/**
 * Backup Warning Modal
 *
 * Shows a warning modal before import to ensure users have created a backup
 */

const BackupWarningModal = {
	/**
	 * Show backup warning modal
	 *
	 * @param {Function} onConfirm Callback when user confirms
	 * @param {Function} onCancel  Callback when user cancels
	 */
	show( onConfirm, onCancel ) {
		// Check if user has disabled the warning
		if ( this.isWarningDisabled() ) {
			if ( typeof onConfirm === 'function' ) {
				onConfirm();
			}
			return;
		}

		// Create modal HTML
		const modalHtml = this.getModalHtml();

		// Add to DOM
		jQuery( 'body' ).append( modalHtml );

		// Get modal elements
		const $overlay = jQuery( '.rsl-ie-backup-warning-overlay' );
		const $modal = jQuery( '.rsl-ie-backup-warning-modal' );
		const $confirmBtn = $modal.find( '.rsl-ie-backup-confirm' );
		const $cancelBtn = $modal.find( '.rsl-ie-backup-cancel' );
		const $backupCheckbox = $modal.find( '#rsl-ie-backup-created' );
		const $dontShowCheckbox = $modal.find( '#rsl-ie-backup-dont-show' );

		// Initially disable confirm button
		$confirmBtn.prop( 'disabled', true );

		// Enable confirm button only when backup checkbox is checked
		$backupCheckbox.on( 'change', function () {
			$confirmBtn.prop( 'disabled', ! jQuery( this ).is( ':checked' ) );
		} );

		// Handle confirm
		$confirmBtn.on( 'click', () => {
			// Save "don't show again" preference
			if ( $dontShowCheckbox.is( ':checked' ) ) {
				this.disableWarning();
			}

			// Close modal
			this.close();

			// Call confirm callback
			if ( typeof onConfirm === 'function' ) {
				onConfirm();
			}
		} );

		// Handle cancel
		$cancelBtn.on( 'click', () => {
			this.close();

			if ( typeof onCancel === 'function' ) {
				onCancel();
			}
		} );

		// Handle overlay click (close)
		$overlay.on( 'click', ( e ) => {
			if ( e.target === $overlay[ 0 ] ) {
				this.close();

				if ( typeof onCancel === 'function' ) {
					onCancel();
				}
			}
		} );

		// Handle ESC key
		jQuery( document ).on( 'keydown.rsl-ie-backup-modal', ( e ) => {
			if ( e.key === 'Escape' ) {
				this.close();

				if ( typeof onCancel === 'function' ) {
					onCancel();
				}
			}
		} );

		// Prevent body scroll
		jQuery( 'body' ).css( 'overflow', 'hidden' );
	},

	/**
	 * Close modal
	 */
	close() {
		jQuery( '.rsl-ie-backup-warning-overlay' ).fadeOut( 200, function () {
			jQuery( this ).remove();
		} );
		jQuery( 'body' ).css( 'overflow', '' );
		jQuery( document ).off( 'keydown.rsl-ie-backup-modal' );
	},

	/**
	 * Check if warning is disabled
	 *
	 * @return {boolean}
	 */
	isWarningDisabled() {
		return (
			localStorage.getItem( 'rsl_ie_backup_warning_disabled' ) === 'true'
		);
	},

	/**
	 * Disable warning (don't show again)
	 */
	disableWarning() {
		localStorage.setItem( 'rsl_ie_backup_warning_disabled', 'true' );
	},

	/**
	 * Enable warning (reset)
	 */
	enableWarning() {
		localStorage.removeItem( 'rsl_ie_backup_warning_disabled' );
	},

	/**
	 * Get modal HTML
	 *
	 * @return {string}
	 */
	getModalHtml() {
		const i18n =
			typeof rslIeData !== 'undefined' && rslIeData.i18n
				? rslIeData.i18n
				: {};

		return `
			<div class="rsl-ie-backup-warning-overlay">
				<div class="rsl-ie-backup-warning-modal">
					<div class="rsl-ie-backup-warning-header">
						<div class="rsl-ie-warning-icon">⚠️</div>
						<h2>${ i18n.backupWarningTitle || 'Important: Create a Backup!' }</h2>
						<p>${
							i18n.backupWarningSubtitle ||
							'This action can modify or delete existing data'
						}</p>
					</div>

					<div class="rsl-ie-backup-warning-body">
						<div class="rsl-ie-warning-message">
							<p><strong>${ i18n.backupWarningRisks || 'Action may lead to:' }</strong></p>
							<p>
								• ${ i18n.backupRisk1 || 'Overwriting existing posts, pages, and records' }<br>
								• ${ i18n.backupRisk2 || 'Modifying metadata and taxonomies' }<br>
								• ${ i18n.backupRisk3 || 'Data loss due to incorrect field mapping' }<br>
								• ${ i18n.backupRisk4 || 'Conflicts with existing IDs' }
							</p>
							<p><strong>${
								i18n.backupWarningImportant ||
								'Rollback may be impossible, especially for updated data!'
							}</strong></p>
						</div>

						<div class="rsl-ie-backup-recommendations">
							<h3>${ i18n.backupRecommendations || 'Recommended backup methods:' }</h3>
							
							<div class="rsl-ie-backup-options">
								<div class="rsl-ie-backup-option">
									<h4>
										<span>UpdraftPlus</span>
										<span class="rsl-ie-badge rsl-ie-badge-free">FREE</span>
									</h4>
									<p>${
										i18n.backupUpdraftPlus ||
										'Popular backup plugin with cloud storage support'
									}</p>
									<a href="https://wordpress.org/plugins/updraftplus/" target="_blank" class="rsl-ie-backup-link">
										${ i18n.viewPlugin || 'View plugin' }
									</a>
								</div>

								<div class="rsl-ie-backup-option">
									<h4>
										<span>BackWPup</span>
										<span class="rsl-ie-badge rsl-ie-badge-free">FREE</span>
									</h4>
									<p>${ i18n.backupBackWPup || 'Automatic database and file backups' }</p>
									<a href="https://wordpress.org/plugins/backwpup/" target="_blank" class="rsl-ie-backup-link">
										${ i18n.viewPlugin || 'View plugin' }
									</a>
								</div>

								<div class="rsl-ie-backup-option">
									<h4>
										<span>All-in-One WP Migration</span>
										<span class="rsl-ie-badge rsl-ie-badge-free">FREE</span>
									</h4>
									<p>${ i18n.backupAllInOne || 'Complete site export in a single file' }</p>
									<a href="https://wordpress.org/plugins/all-in-one-wp-migration/" target="_blank" class="rsl-ie-backup-link">
										${ i18n.viewPlugin || 'View plugin' }
									</a>
								</div>

								<div class="rsl-ie-backup-option">
									<h4>
										<span>${ i18n.hostingBackup || 'Hosting Backup' }</span>
										<span class="rsl-ie-badge">${ i18n.recommended || 'RECOMMENDED' }</span>
									</h4>
									<p>${
										i18n.hostingBackupDesc ||
										'Use built-in backup tools from your hosting provider (cPanel, Plesk, WP Engine, etc.)'
									}</p>
								</div>
							</div>
						</div>

						<div class="rsl-ie-backup-checkboxes">
							<div class="rsl-ie-backup-checkbox rsl-ie-checkbox-required">
								<input type="checkbox" id="rsl-ie-backup-created">
								<label for="rsl-ie-backup-created">
									<strong>${
										i18n.backupConfirm ||
										'I have created a database backup and understand the irreversibility of data updates'
									}</strong>
								</label>
							</div>
							<div class="rsl-ie-backup-checkbox">
								<input type="checkbox" id="rsl-ie-backup-dont-show">
								<label for="rsl-ie-backup-dont-show">
									${ i18n.backupDontShow || "Don't show this warning again" }
								</label>
							</div>
						</div>
					</div>

					<div class="rsl-ie-backup-warning-footer">
						<button type="button" class="rsl-ie-button rsl-ie-button-secondary rsl-ie-backup-cancel">
							${ i18n.cancel || 'Cancel' }
						</button>
						<button type="button" class="rsl-ie-button rsl-ie-button-primary rsl-ie-backup-confirm" disabled>
							✓ ${ i18n.backupContinue || 'Continue' }
						</button>
					</div>
				</div>
			</div>
		`;
	},
};

export default BackupWarningModal;
