import { __ } from '@wordpress/i18n';
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
						<h2>${
							i18n.backupWarningTitle ||
							__(
								'Important: Create a Backup!',
								'import-export-by-rockstarlab'
							)
						}</h2>
						<p>${
							i18n.backupWarningSubtitle ||
							__(
								'This action can modify or delete existing data',
								'import-export-by-rockstarlab'
							)
						}</p>
					</div>

					<div class="rsl-ie-backup-warning-body">
						<div class="rsl-ie-warning-message">
							<p><strong>${
								i18n.backupWarningRisks ||
								__(
									'Action may lead to:',
									'import-export-by-rockstarlab'
								)
							}</strong></p>
							<p>
								• ${
									i18n.backupRisk1 ||
									__(
										'Overwriting existing posts, pages, and records',
										'import-export-by-rockstarlab'
									)
								}<br>
								• ${
									i18n.backupRisk2 ||
									__(
										'Modifying metadata and taxonomies',
										'import-export-by-rockstarlab'
									)
								}<br>
								• ${
									i18n.backupRisk3 ||
									__(
										'Data loss due to incorrect field mapping',
										'import-export-by-rockstarlab'
									)
								}<br>
								• ${
									i18n.backupRisk4 ||
									__(
										'Conflicts with existing IDs',
										'import-export-by-rockstarlab'
									)
								}
							</p>
							<p><strong>${
								i18n.backupWarningImportant ||
								__(
									'Rollback may be impossible, especially for updated data!',
									'import-export-by-rockstarlab'
								)
							}</strong></p>
						</div>

						<div class="rsl-ie-backup-recommendations">
							<h3>${
								i18n.backupRecommendations ||
								__(
									'Recommended backup methods:',
									'import-export-by-rockstarlab'
								)
							}</h3>
							
							<div class="rsl-ie-backup-options">
								<div class="rsl-ie-backup-option">
									<h4>
										<span>UpdraftPlus</span>
										<span class="rsl-ie-badge rsl-ie-badge-free">FREE</span>
										<span class="rsl-ie-badge">${
											i18n.recommended ||
											__(
												'RECOMMENDED',
												'import-export-by-rockstarlab'
											)
										}</span>
									</h4>
									<p>${
										i18n.backupUpdraftPlus ||
										__(
											'Back up your WordPress files and database',
											'import-export-by-rockstarlab'
										)
									}</p>
									<a href="https://teamupdraft.com/ref/3832/" target="_blank" class="rsl-ie-backup-link">
										${ i18n.viewPlugin || __( 'View plugin', 'import-export-by-rockstarlab' ) }
									</a>
								</div>

								<div class="rsl-ie-backup-option">
									<h4>
										<span>${
											i18n.hostingBackup ||
											__(
												'Hosting Backup',
												'import-export-by-rockstarlab'
											)
										}</span>
										<span class="rsl-ie-badge">${
											i18n.recommended ||
											__(
												'RECOMMENDED',
												'import-export-by-rockstarlab'
											)
										}</span>
									</h4>
									<p>${
										i18n.hostingBackupDesc ||
										__(
											'Use built-in backup tools from your hosting provider (cPanel, Plesk, etc.)',
											'import-export-by-rockstarlab'
										)
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
										__(
											'I have created a database backup and understand the irreversibility of data updates',
											'import-export-by-rockstarlab'
										)
									}</strong>
								</label>
							</div>
							<div class="rsl-ie-backup-checkbox">
								<input type="checkbox" id="rsl-ie-backup-dont-show">
								<label for="rsl-ie-backup-dont-show">
									${
										i18n.backupDontShow ||
										__(
											"Don't show this warning again",
											'import-export-by-rockstarlab'
										)
									}
								</label>
							</div>
						</div>
					</div>

					<div class="rsl-ie-backup-warning-footer">
						<button type="button" class="rsl-ie-button rsl-ie-button-secondary rsl-ie-backup-cancel">
							${ i18n.cancel || __( 'Cancel', 'import-export-by-rockstarlab' ) }
						</button>
						<button type="button" class="rsl-ie-button rsl-ie-button-primary rsl-ie-backup-confirm" disabled>
							✓ ${ i18n.backupContinue || __( 'Continue', 'import-export-by-rockstarlab' ) }
						</button>
					</div>
				</div>
			</div>
		`;
	},
};

export default BackupWarningModal;
