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
		const $overlay = jQuery( '.aie-backup-warning-overlay' );
		const $modal = jQuery( '.aie-backup-warning-modal' );
		const $confirmBtn = $modal.find( '.aie-backup-confirm' );
		const $cancelBtn = $modal.find( '.aie-backup-cancel' );
		const $backupCheckbox = $modal.find( '#aie-backup-created' );
		const $dontShowCheckbox = $modal.find( '#aie-backup-dont-show' );

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
		jQuery( document ).on( 'keydown.aie-backup-modal', ( e ) => {
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
		jQuery( '.aie-backup-warning-overlay' ).fadeOut( 200, function () {
			jQuery( this ).remove();
		} );
		jQuery( 'body' ).css( 'overflow', '' );
		jQuery( document ).off( 'keydown.aie-backup-modal' );
	},

	/**
	 * Check if warning is disabled
	 *
	 * @return {boolean}
	 */
	isWarningDisabled() {
		return localStorage.getItem( 'aie_backup_warning_disabled' ) === 'true';
	},

	/**
	 * Disable warning (don't show again)
	 */
	disableWarning() {
		localStorage.setItem( 'aie_backup_warning_disabled', 'true' );
	},

	/**
	 * Enable warning (reset)
	 */
	enableWarning() {
		localStorage.removeItem( 'aie_backup_warning_disabled' );
	},

	/**
	 * Get modal HTML
	 *
	 * @return {string}
	 */
	getModalHtml() {
		const i18n = (typeof aieData !== 'undefined' && aieData.i18n) ? aieData.i18n : {};

		return `
			<div class="aie-backup-warning-overlay">
				<div class="aie-backup-warning-modal">
					<div class="aie-backup-warning-header">
						<div class="aie-warning-icon">⚠️</div>
						<h2>${ i18n.backupWarningTitle || 'Important: Create a Backup!' }</h2>
						<p>${ i18n.backupWarningSubtitle || 'This action can modify or delete existing data' }</p>
					</div>

					<div class="aie-backup-warning-body">
						<div class="aie-warning-message">
							<p><strong>${ i18n.backupWarningRisks || 'Action may lead to:' }</strong></p>
							<p>
								• ${ i18n.backupRisk1 || 'Overwriting existing posts, pages, and records' }<br>
								• ${ i18n.backupRisk2 || 'Modifying metadata and taxonomies' }<br>
								• ${ i18n.backupRisk3 || 'Data loss due to incorrect field mapping' }<br>
								• ${ i18n.backupRisk4 || 'Conflicts with existing IDs' }
							</p>
							<p><strong>${ i18n.backupWarningImportant || 'Rollback may be impossible, especially for updated data!' }</strong></p>
						</div>

						<div class="aie-backup-recommendations">
							<h3>${ i18n.backupRecommendations || 'Recommended backup methods:' }</h3>
							
							<div class="aie-backup-options">
								<div class="aie-backup-option">
									<h4>
										<span>UpdraftPlus</span>
										<span class="aie-badge aie-badge-free">FREE</span>
									</h4>
									<p>${ i18n.backupUpdraftPlus || 'Popular backup plugin with cloud storage support' }</p>
									<a href="https://wordpress.org/plugins/updraftplus/" target="_blank" class="aie-backup-link">
										${ i18n.viewPlugin || 'View plugin' }
									</a>
								</div>

								<div class="aie-backup-option">
									<h4>
										<span>BackWPup</span>
										<span class="aie-badge aie-badge-free">FREE</span>
									</h4>
									<p>${ i18n.backupBackWPup || 'Automatic database and file backups' }</p>
									<a href="https://wordpress.org/plugins/backwpup/" target="_blank" class="aie-backup-link">
										${ i18n.viewPlugin || 'View plugin' }
									</a>
								</div>

								<div class="aie-backup-option">
									<h4>
										<span>All-in-One WP Migration</span>
										<span class="aie-badge aie-badge-free">FREE</span>
									</h4>
									<p>${ i18n.backupAllInOne || 'Complete site export in a single file' }</p>
									<a href="https://wordpress.org/plugins/all-in-one-wp-migration/" target="_blank" class="aie-backup-link">
										${ i18n.viewPlugin || 'View plugin' }
									</a>
								</div>

								<div class="aie-backup-option">
									<h4>
										<span>${ i18n.hostingBackup || 'Hosting Backup' }</span>
										<span class="aie-badge">${ i18n.recommended || 'RECOMMENDED' }</span>
									</h4>
									<p>${ i18n.hostingBackupDesc || 'Use built-in backup tools from your hosting provider (cPanel, Plesk, WP Engine, etc.)' }</p>
								</div>
							</div>
						</div>

						<div class="aie-backup-checkboxes">
							<div class="aie-backup-checkbox aie-checkbox-required">
								<input type="checkbox" id="aie-backup-created">
								<label for="aie-backup-created">
									<strong>${ i18n.backupConfirm || 'I have created a database backup and understand the irreversibility of data updates' }</strong>
								</label>
							</div>
							<div class="aie-backup-checkbox">
								<input type="checkbox" id="aie-backup-dont-show">
								<label for="aie-backup-dont-show">
									${ i18n.backupDontShow || 'Don\'t show this warning again' }
								</label>
							</div>
						</div>
					</div>

					<div class="aie-backup-warning-footer">
						<button type="button" class="aie-button aie-button-secondary aie-backup-cancel">
							${ i18n.cancel || 'Cancel' }
						</button>
						<button type="button" class="aie-button aie-button-primary aie-backup-confirm" disabled>
							✓ ${ i18n.backupContinue || 'Continue' }
						</button>
					</div>
				</div>
			</div>
		`;
	},
};

export default BackupWarningModal;
