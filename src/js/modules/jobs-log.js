/**
 * Jobs Log Module
 *
 * Handles jobs log page functionality
 */

import Utils from './utils';

import { __ } from '@wordpress/i18n';
const JobsLogModule = {
	currentPage: 1,
	perPage: 20,
	totalPages: 1,
	totalJobs: 0,
	filters: {
		type: '',
		status: '',
		favorites: false,
	},

	/**
	 * Initialize module
	 */
	init() {
		if ( ! jQuery( '#rsl-ie-jobs-log' ).length ) {
			return;
		}

		this.bindEvents();
		this.loadJobs();
	},

	/**
	 * Bind event handlers
	 */
	bindEvents() {
		const $page = jQuery( '#rsl-ie-jobs-log' );

		// Filter buttons
		$page.on( 'click', '.rsl-ie-filter-apply', () => this.applyFilters() );
		$page.on( 'click', '.rsl-ie-filter-reset', () => this.resetFilters() );

		// Pagination
		$page.on( 'click', '.first-page', () => this.goToPage( 1 ) );
		$page.on( 'click', '.prev-page', () =>
			this.goToPage( this.currentPage - 1 )
		);
		$page.on( 'click', '.next-page', () =>
			this.goToPage( this.currentPage + 1 )
		);
		$page.on( 'click', '.last-page', () =>
			this.goToPage( this.totalPages )
		);

		// Job actions
		$page.on( 'click', '.job-action-resume', ( e ) => this.resumeJob( e ) );
		$page.on( 'click', '.job-action-continue', ( e ) => {
			const $button = jQuery( e.currentTarget );
			this.redirectToJobPage(
				$button.data( 'type' ),
				$button.closest( 'tr' ).data( 'job-id' ),
				$button.data( 'data-type' )
			);
		} );
		$page.on( 'click', '.job-action-restart', ( e ) =>
			this.restartJob( e )
		);
		$page.on( 'click', '.job-action-retry', ( e ) => this.retryJob( e ) );
		$page.on( 'click', '.job-action-save-template', ( e ) =>
			this.saveJobAsTemplate( e )
		);
		jQuery( document ).on(
			'click',
			'.job-action-download-errors',
			async ( e ) => {
				const $button = jQuery( e.currentTarget );
				$button.prop( 'disabled', true );
				try {
					await Utils.downloadJobErrorsCsv(
						$button.data( 'job-id' )
					);
				} catch ( error ) {
					Utils.showNotice( String( error ), 'error' );
				} finally {
					$button.prop( 'disabled', false );
				}
			}
		);
		$page.on( 'click', '.job-action-delete', ( e ) => this.deleteJob( e ) );
		$page.on( 'click', '.rsl-ie-job-favorite', ( e ) =>
			this.toggleFavorite( e )
		);
		$page.on( 'change', '#filter-favorites', () => this.applyFilters() );
		$page.on( 'click', '.job-action-download', ( e ) =>
			this.downloadFile( e )
		);
		$page.on( 'click', '.job-action-view', ( e ) =>
			this.viewJobDetails( e )
		);
		$page.on( 'click', '.rsl-ie-job-name-edit', ( e ) =>
			this.startRenameJob( e )
		);
		$page.on( 'click', '.rsl-ie-job-name-save', ( e ) =>
			this.saveRenameJob( e )
		);
		$page.on( 'click', '.rsl-ie-job-name-cancel', ( e ) =>
			this.cancelRenameJob( e )
		);
		$page.on( 'keydown', '.rsl-ie-job-name-input', ( e ) =>
			this.handleRenameKeydown( e )
		);

		// Modal close - bind to document for modals outside page container
		jQuery( document ).on( 'click', '.rsl-ie-modal-close', () =>
			this.closeModal()
		);
		jQuery( document ).on( 'click', '.rsl-ie-modal-overlay', () =>
			this.closeModal()
		);
		// Escape closes Job Details like the other dialogs.
		jQuery( document ).on( 'keydown.rslIeJobsLog', ( e ) => {
			if (
				'Escape' === e.key &&
				jQuery( '.rsl-ie-modal:visible' ).length
			) {
				this.closeModal();
			}
		} );

		// Confirm delete
		jQuery( document ).on( 'click', '.rsl-ie-confirm-delete', () =>
			this.confirmDelete()
		);
	},

	/**
	 * Apply filters
	 */
	applyFilters() {
		this.filters.type = jQuery( '#filter-type' ).val();
		this.filters.status = jQuery( '#filter-status' ).val();
		this.filters.favorites = jQuery( '#filter-favorites' ).is( ':checked' );
		this.currentPage = 1;
		this.loadJobs();
	},

	/**
	 * Reset filters
	 */
	resetFilters() {
		jQuery( '#filter-type' ).val( '' );
		jQuery( '#filter-status' ).val( '' );
		jQuery( '#filter-favorites' ).prop( 'checked', false );
		this.filters = { type: '', status: '', favorites: false };
		this.currentPage = 1;
		this.loadJobs();
	},

	/**
	 * Load jobs list
	 */
	async loadJobs() {
		const $loading = jQuery( '.rsl-ie-jobs-loading' );
		const $table = jQuery( '.rsl-ie-jobs-table-wrapper' );

		$loading.show();
		$table.hide();

		try {
			const offset = ( this.currentPage - 1 ) * this.perPage;

			const response = await Utils.ajax( 'rsl_ie_job_list', {
				type: this.filters.type,
				status: this.filters.status,
				favorites: this.filters.favorites ? 1 : '',
				limit: this.perPage,
				offset: offset,
			} );

			if ( response && response.jobs ) {
				this.totalJobs = response.total || 0;
				this.totalPages = Math.ceil( this.totalJobs / this.perPage );
				this.renderJobs( response.jobs );
				this.updatePagination();
			} else {
				this.renderJobs( [] );
			}
		} catch ( error ) {
			Utils.showNotice(
				window.rslIeData.i18n.errorLoadingJobs + error.message,
				'error'
			);
			this.renderJobs( [] );
		} finally {
			$loading.hide();
			$table.show();
		}
	},

	/**
	 * Render jobs table
	 */
	renderJobs( jobs ) {
		const $tbody = jQuery( '#jobs-table-body' );

		if ( ! jobs || jobs.length === 0 ) {
			$tbody.html(
				`<tr class="no-items"><td colspan="10">${
					this.filters.favorites
						? __(
								'No favorite jobs yet. Click the star next to a job to add it.',
								'import-export-by-rockstarlab'
						  )
						: window.rslIeData.i18n.noJobsFound
				}</td></tr>`
			);
			return;
		}

		let html = '';
		jobs.forEach( ( job ) => {
			html += this.renderJobRow( job );
		} );

		$tbody.html( html );
	},

	/**
	 * Star button of a job row.
	 */
	renderFavorite( job ) {
		const on = !! job.is_favorite;
		const label = on
			? __( 'Remove from favorites', 'import-export-by-rockstarlab' )
			: __( 'Add to favorites', 'import-export-by-rockstarlab' );
		return `<button type="button" class="rsl-ie-job-favorite${
			on ? ' is-favorite' : ''
		}" aria-pressed="${ on ? 'true' : 'false' }" title="${ this.escapeAttr(
			label
		) }" aria-label="${ this.escapeAttr(
			label
		) }"><span class="dashicons dashicons-star-${
			on ? 'filled' : 'empty'
		}" aria-hidden="true"></span></button>`;
	},

	/**
	 * Star or unstar a job; in "Favorites only" an unstarred job leaves the list.
	 */
	async toggleFavorite( e ) {
		const $button = jQuery( e.currentTarget );
		const jobId = $button.closest( 'tr' ).data( 'job-id' );
		const favorite = ! $button.hasClass( 'is-favorite' );
		$button.prop( 'disabled', true );
		try {
			const response = await Utils.ajax( 'rsl_ie_job_favorite', {
				job_id: jobId,
				favorite: favorite ? 1 : 0,
			} );
			const on = !! ( response && response.is_favorite );
			if ( this.filters.favorites && ! on ) {
				this.loadJobs();
				return;
			}
			$button.replaceWith( this.renderFavorite( { is_favorite: on } ) );
		} catch ( error ) {
			Utils.showNotice( String( error ), 'error' );
			$button.prop( 'disabled', false );
		}
	},

	/**
	 * Render single job row
	 */
	renderJobRow( job ) {
		const statusClass = 'status-' + job.status;
		const typeLabel = this.getTypeLabel( job.type, job.data_type );
		const dataTypeLabel = this.getJobDataTypeLabel( job );
		const statusLabel =
			'completed' === job.status && job.upload_failed
				? __(
						'Completed, upload failed',
						'import-export-by-rockstarlab'
				  )
				: this.getStatusLabel( job.status );
		const progress = this.renderProgress( job );
		const actions = this.renderActions( job );
		const jobName = this.getJobDisplayName( job, typeLabel, dataTypeLabel );
		const customJobName = job.job_name || '';

		return `
			<tr class="job-row ${ statusClass }" data-job-id="${ job.id }">
				<td class="column-favorite">${ this.renderFavorite( job ) }</td>
				<td class="column-id">${ job.id }</td>
				<td class="column-job-name">
					<button
						type="button"
						class="rsl-ie-job-name-edit"
						data-job-name="${ this.escapeAttr( customJobName ) }"
						title="${ this.escapeAttr(
							window.rslIeData.i18n.clickToRenameJob ||
								__(
									'Click to rename this job',
									'import-export-by-rockstarlab'
								)
						) }"
					>
						<span class="rsl-ie-job-name-text">${ this.escapeHtml( jobName ) }</span>
					</button>
				</td>
				<td class="column-type">
					<span class="job-type-badge job-type-${ job.type }">${ typeLabel }</span>
				</td>
				<td class="column-status">
					<span class="job-status-badge job-status-${
						job.status
					}">${ statusLabel }</span>
				</td>
				<td class="column-progress">${ progress }</td>
				<td class="column-items">
					<div class="items-info">
						<div><strong>${ job.processed_items }</strong> / ${ job.total_items }</div>
						${
							job.failed_items > 0
								? `<div class="failed-count">${
										window.rslIeData.i18n.failed ||
										__(
											'Failed',
											'import-export-by-rockstarlab'
										)
								  }: ${ job.failed_items }</div>`
								: ''
						}
					</div>
				</td>
				<td class="column-created">${ this.formatDate( job.created_at ) }</td>
				<td class="column-elapsed">${ job.elapsed_time || '-' }</td>
				<td class="column-actions">${ actions }</td>
			</tr>
		`;
	},

	/**
	 * Get display label for a Job.
	 */
	getJobDisplayName( job, typeLabel, dataTypeLabel ) {
		if ( job.job_name && String( job.job_name ).trim() !== '' ) {
			return String( job.job_name ).trim();
		}

		if ( 'content_update' === job.data_type ) {
			return `#${ job.id } ${ typeLabel }`;
		}

		return `#${ job.id } ${ typeLabel } ${ dataTypeLabel }`;
	},

	/**
	 * Start inline job rename.
	 */
	startRenameJob( e ) {
		e.preventDefault();

		const $button = jQuery( e.currentTarget );
		const $cell = $button.closest( '.column-job-name' );
		const currentName = $button.attr( 'data-job-name' ) || '';

		if ( $cell.find( '.rsl-ie-job-name-form' ).length ) {
			return;
		}

		$cell.data( 'rename-original-html', $cell.html() );
		$cell.html( `
			<div class="rsl-ie-job-name-form">
				<input
					type="text"
					class="regular-text rsl-ie-job-name-input"
					value="${ this.escapeAttr( currentName ) }"
					maxlength="255"
					placeholder="${ this.escapeAttr(
						window.rslIeData.i18n.customJobNamePlaceholder ||
							__(
								'Custom job name...',
								'import-export-by-rockstarlab'
							)
					) }"
				>
				<button type="button" class="button button-small rsl-ie-job-name-save">${
					window.rslIeData.i18n.save ||
					__( 'Save', 'import-export-by-rockstarlab' )
				}</button>
				<button type="button" class="button button-small rsl-ie-job-name-cancel">${
					window.rslIeData.i18n.cancel ||
					__( 'Cancel', 'import-export-by-rockstarlab' )
				}</button>
			</div>
		` );

		$cell.find( '.rsl-ie-job-name-input' ).trigger( 'focus' ).select();
	},

	/**
	 * Save inline job rename.
	 */
	async saveRenameJob( e ) {
		e.preventDefault();

		const $button = jQuery( e.currentTarget );
		const $cell = $button.closest( '.column-job-name' );
		const $row = $button.closest( 'tr' );
		const jobId = $row.data( 'job-id' );
		const jobName = $cell.find( '.rsl-ie-job-name-input' ).val() || '';

		$cell.find( 'input, button' ).prop( 'disabled', true );

		try {
			await Utils.ajax( 'rsl_ie_job_rename', {
				job_id: jobId,
				job_name: jobName,
			} );

			Utils.showNotice(
				window.rslIeData.i18n.jobNameUpdatedSuccess ||
					__(
						'Job name updated successfully',
						'import-export-by-rockstarlab'
					),
				'success'
			);
			this.loadJobs();
		} catch ( error ) {
			const message =
				typeof error === 'string' ? error : error?.message || '';
			Utils.showNotice(
				( window.rslIeData.i18n.errorRenamingJob ||
					__(
						'Error renaming job: ',
						'import-export-by-rockstarlab'
					) ) + message,
				'error'
			);
			$cell.find( 'input, button' ).prop( 'disabled', false );
		}
	},

	/**
	 * Cancel inline job rename.
	 */
	cancelRenameJob( e ) {
		e.preventDefault();

		const $cell = jQuery( e.currentTarget ).closest( '.column-job-name' );
		$cell.html( $cell.data( 'rename-original-html' ) || '' );
	},

	/**
	 * Keyboard controls for inline rename.
	 */
	handleRenameKeydown( e ) {
		if ( e.key === 'Enter' ) {
			e.preventDefault();
			jQuery( e.currentTarget )
				.closest( '.rsl-ie-job-name-form' )
				.find( '.rsl-ie-job-name-save' )
				.trigger( 'click' );
		}

		if ( e.key === 'Escape' ) {
			e.preventDefault();
			jQuery( e.currentTarget )
				.closest( '.rsl-ie-job-name-form' )
				.find( '.rsl-ie-job-name-cancel' )
				.trigger( 'click' );
		}
	},

	/**
	 * Render progress value.
	 */
	renderProgress( job ) {
		const progress = job.progress || 0;
		return `<span class="progress-text">${ progress }%</span>`;
	},

	/**
	 * Render action buttons
	 */
	renderActions( job ) {
		let actions = [];

		// View details
		actions.push(
			`<button class="button button-small job-action-view" title="${
				window.rslIeData.i18n.viewDetails ||
				__( 'View Details', 'import-export-by-rockstarlab' )
			}"><span class="dashicons dashicons-visibility"></span></button>`
		);

		// Continue: a running import/export moves on only while WP-Cron fires or a progress
		// page is open. The progress page runs the next batches from this browser.
		if (
			[ 'import', 'export' ].includes( job.type ) &&
			[ 'pending', 'processing' ].includes( job.status ) &&
			! [ 'content_update', 'migration', 'site_migration' ].includes(
				job.data_type
			)
		) {
			actions.push(
				`<button class="button button-small job-action-continue" data-type="${ Utils.escapeHtml(
					job.type
				) }" data-data-type="${ Utils.escapeHtml(
					job.data_type || ''
				) }" title="${
					window.rslIeData.i18n.continueJob ||
					__(
						'Continue in this window',
						'import-export-by-rockstarlab'
					)
				}"><span class="dashicons dashicons-controls-play"></span></button>`
			);
		}

		// Resume
		if ( job.can_resume ) {
			actions.push(
				`<button class="button button-small job-action-resume" title="${
					window.rslIeData.i18n.resume ||
					__( 'Resume', 'import-export-by-rockstarlab' )
				}"><span class="dashicons dashicons-controls-play"></span></button>`
			);
		}

		// Restart (create a new job with same settings)
		if ( job.can_restart ) {
			actions.push(
				`<button class="button button-small job-action-restart" title="${
					window.rslIeData.i18n.restart ||
					__( 'Restart', 'import-export-by-rockstarlab' )
				}"><span class="dashicons dashicons-controls-repeat"></span></button>`
			);
		}

		// Retry - not available for folder media imports (files may have been moved)
		if ( job.can_retry && job.data_type !== 'media_sync' ) {
			actions.push(
				`<button class="button button-small job-action-retry" title="${
					window.rslIeData.i18n.retry ||
					__(
						'Retry (Create new job with same parameters)',
						'import-export-by-rockstarlab'
					)
				}"><span class="dashicons dashicons-update"></span></button>`
			);
		}

		// Save the field mapping of a file import as a reusable template.
		if (
			job.type === 'import' &&
			! [
				'media_sync',
				'ai_url',
				'content_update',
				'migration',
				'site_migration',
			].includes( job.data_type )
		) {
			actions.push(
				`<button class="button button-small job-action-save-template" title="${
					window.rslIeData.i18n.saveAsMappingTemplate ||
					__(
						'Save field mapping as template',
						'import-export-by-rockstarlab'
					)
				}"><span class="dashicons dashicons-portfolio"></span></button>`
			);
		}

		// Download (for exports)
		if (
			job.type === 'export' &&
			job.file_path &&
			job.status === 'completed'
		) {
			actions.push(
				`<button class="button button-small job-action-download" title="${
					window.rslIeData.i18n.download ||
					__( 'Download', 'import-export-by-rockstarlab' )
				}"><span class="dashicons dashicons-download"></span></button>`
			);
		}

		// Delete
		if ( job.can_delete ) {
			actions.push(
				`<button class="button button-small job-action-delete" title="${
					window.rslIeData.i18n.delete ||
					__( 'Delete', 'import-export-by-rockstarlab' )
				}"><span class="dashicons dashicons-trash"></span></button>`
			);
		}

		return `<div class="job-actions">${ actions.join( '' ) }</div>`;
	},

	/**
	 * Update pagination UI
	 */
	updatePagination() {
		const $pagination = jQuery( '.rsl-ie-jobs-pagination' );

		if ( this.totalJobs === 0 ) {
			$pagination.hide();
			return;
		}

		$pagination.show();

		// Update info text
		const start = ( this.currentPage - 1 ) * this.perPage + 1;
		const end = Math.min( this.currentPage * this.perPage, this.totalJobs );
		const showingText = window.rslIeData.i18n.showingJobs
			.replace( '%1$s', start )
			.replace( '%2$s', end )
			.replace( '%3$s', this.totalJobs );
		jQuery( '.displaying-num' ).text( showingText ); // Update page numbers
		jQuery( '.current-page' ).text( this.currentPage );
		jQuery( '.total-pages' ).text( this.totalPages );

		// Update button states
		jQuery( '.first-page, .prev-page' ).prop(
			'disabled',
			this.currentPage === 1
		);
		jQuery( '.next-page, .last-page' ).prop(
			'disabled',
			this.currentPage >= this.totalPages
		);
	},

	/**
	 * Go to page
	 */
	goToPage( page ) {
		if ( page < 1 || page > this.totalPages || page === this.currentPage ) {
			return;
		}
		this.currentPage = page;
		this.loadJobs();
	},

	/**
	 * Resume job
	 */
	async resumeJob( e ) {
		const $button = jQuery( e.currentTarget );
		const $row = $button.closest( 'tr' );
		const jobId = $row.data( 'job-id' );

		if ( ! confirm( window.rslIeData.i18n.confirmResumeJob ) ) {
			return;
		}

		$button.prop( 'disabled', true );

		try {
			const response = await Utils.ajax( 'rsl_ie_job_resume', {
				job_id: jobId,
			} );

			if ( response && response.job_id ) {
				Utils.showNotice(
					window.rslIeData.i18n.jobResumedSuccess,
					'success'
				);

				// Redirect based on job type
				this.redirectToJobPage(
					response.type,
					response.job_id,
					response.data_type
				);
			}
		} catch ( error ) {
			Utils.showNotice(
				window.rslIeData.i18n.errorResumingJob + error.message,
				'error'
			);
			$button.prop( 'disabled', false );
		}
	},

	/**
	 * Save the mapping and settings of an import job as a mapping template.
	 *
	 * @param {Object} e Click event.
	 */
	async saveJobAsTemplate( e ) {
		const $button = jQuery( e.currentTarget );
		const $row = $button.closest( 'tr' );
		const jobId = $row.data( 'job-id' );
		const i18n = window.rslIeData.i18n || {};
		const currentName = $row
			.find( '.rsl-ie-job-name-text, .job-name' )
			.first()
			.text()
			.trim();
		// eslint-disable-next-line no-alert
		const name = window.prompt(
			i18n.templateNamePrompt ||
				__(
					'Template name (for example "Supplier price list"):',
					'import-export-by-rockstarlab'
				),
			currentName
		);
		if ( name === null ) {
			return;
		}
		$button.prop( 'disabled', true );
		const save = ( overwrite ) =>
			Utils.ajax( 'rsl_ie_mapping_template_from_job', {
				job_id: jobId,
				name: name.trim(),
				overwrite: overwrite ? 1 : 0,
			} );
		try {
			let template;
			try {
				template = await save( false );
			} catch ( error ) {
				if (
					! /already exists/i.test( String( error ) ) ||
					// eslint-disable-next-line no-alert
					! window.confirm(
						(
							i18n.templateReplaceConfirm ||
							__(
								'A template named "%s" already exists. Replace it?',
								'import-export-by-rockstarlab'
							)
						).replace( '%s', name.trim() )
					)
				) {
					throw error;
				}
				template = await save( true );
			}
			Utils.showNotice(
				(
					i18n.templateSavedFromJob ||
					__(
						'Template "%s" saved. Choose it in step 4 of the import wizard.',
						'import-export-by-rockstarlab'
					)
				).replace( '%s', template.name ),
				'success'
			);
		} catch ( error ) {
			Utils.showNotice( String( error ), 'error' );
		} finally {
			$button.prop( 'disabled', false );
		}
	},

	/**
	 * Restart job
	 */
	async restartJob( e ) {
		const $button = jQuery( e.currentTarget );
		const $row = $button.closest( 'tr' );
		const jobId = $row.data( 'job-id' );

		if ( ! confirm( window.rslIeData.i18n.confirmRestartJob ) ) {
			return;
		}

		$button.prop( 'disabled', true );

		try {
			const response = await Utils.ajax( 'rsl_ie_job_restart', {
				job_id: jobId,
			} );

			if ( response && response.job_id ) {
				Utils.showNotice(
					window.rslIeData.i18n.jobRestartedSuccess,
					'success'
				);

				// Redirect based on job type
				this.redirectToJobPage(
					response.type,
					response.job_id,
					response.data_type
				);
			}
		} catch ( error ) {
			Utils.showNotice(
				window.rslIeData.i18n.errorRestartingJob + error.message,
				'error'
			);
			$button.prop( 'disabled', false );
		}
	},

	/**
	 * Retry job (create new job with processing status and show progress immediately)
	 */
	async retryJob( e ) {
		const $button = jQuery( e.currentTarget );
		const $row = $button.closest( 'tr' );
		const jobId = $row.data( 'job-id' );

		if ( ! confirm( window.rslIeData.i18n.confirmRetryJob ) ) {
			return;
		}

		$button.prop( 'disabled', true );

		try {
			// Create new job with processing status
			const response = await Utils.ajax( 'rsl_ie_job_retry', {
				job_id: jobId,
			} );

			if ( response && response.job_id && response.type ) {
				Utils.showNotice(
					window.rslIeData.i18n.jobCreatedStarting,
					'success'
				);

				// Redirect to job page with resume_job parameter to show progress
				this.redirectToJobPage(
					response.type,
					response.job_id,
					response.data_type
				);
			}
		} catch ( error ) {
			const errorMsg =
				error && error.message
					? error.message
					: __(
							'Unknown error occurred',
							'import-export-by-rockstarlab'
					  );
			Utils.showNotice(
				window.rslIeData.i18n.errorRetryingJob + errorMsg,
				'error'
			);
			$button.prop( 'disabled', false );
		}
	},

	/**
	 * Delete job
	 */
	deleteJob( e ) {
		const $button = jQuery( e.currentTarget );
		const $row = $button.closest( 'tr' );
		const jobId = $row.data( 'job-id' );

		// Store job ID for confirmation
		this.deleteJobId = jobId;

		// Show confirmation modal
		jQuery( '#confirm-delete-modal' ).show();
	},

	/**
	 * Confirm delete
	 */
	async confirmDelete() {
		if ( ! this.deleteJobId ) {
			return;
		}

		const jobId = this.deleteJobId;
		this.closeModal();

		try {
			await Utils.ajax( 'rsl_ie_job_delete', { job_id: jobId } );

			Utils.showNotice(
				window.rslIeData.i18n.jobDeletedSuccess,
				'success'
			);
			this.loadJobs(); // Reload list
		} catch ( error ) {
			Utils.showNotice(
				window.rslIeData.i18n.errorDeletingJob + error.message,
				'error'
			);
		}
	},

	/**
	 * Download file
	 */
	downloadFile( e ) {
		const $button = jQuery( e.currentTarget );
		const $row = $button.closest( 'tr' );
		const jobId = $row.data( 'job-id' );

		// Request download URL with nonce from server
		jQuery.ajax( {
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'rsl_ie_job_download_url',
				job_id: jobId,
				nonce: rslIeData.nonce,
			},
			success: ( response ) => {
				if ( response.success && response.data.url ) {
					window.location.href = response.data.url;
				} else {
					alert(
						response.data || window.rslIeData.i18n.downloadFailed
					);
				}
			},
			error: () => {
				alert( window.rslIeData.i18n.failedGenerateDownloadUrl );
			},
		} );
	},

	/**
	 * View job details
	 */
	async viewJobDetails( e ) {
		const $button = jQuery( e.currentTarget );
		const $row = $button.closest( 'tr' );
		const jobId = $row.data( 'job-id' );

		try {
			const response = await Utils.ajax( 'rsl_ie_job_get', {
				job_id: jobId,
			} );

			if ( response ) {
				// Notes such as "Author not found" are kept in the job log.
				try {
					const logs = await Utils.ajax( 'rsl_ie_job_get_logs', {
						job_id: jobId,
						level: 'warning',
						limit: 100,
					} );
					response.warnings = Array.isArray( logs?.logs )
						? logs.logs
						: [];
				} catch ( logError ) {
					response.warnings = [];
				}
				// Why a job failed is written to the log as an error.
				if (
					'failed' === response.status ||
					'paused' === response.status
				) {
					try {
						const errors = await Utils.ajax(
							'rsl_ie_job_get_logs',
							{
								job_id: jobId,
								level: 'error',
								limit: 20,
							}
						);
						response.log_errors = Array.isArray( errors?.logs )
							? errors.logs
							: [];
					} catch ( logError ) {
						response.log_errors = [];
					}
				}
				this.showJobDetailsModal( response );
			}
		} catch ( error ) {
			Utils.showNotice(
				window.rslIeData.i18n.errorLoadingJobDetails + error.message,
				'error'
			);
		}
	},

	/**
	 * Show job details modal
	 */
	showJobDetailsModal( job ) {
		const html = `
			<div class="job-details">
				<table class="form-table">
					<tr>
						<th>${
							window.rslIeData.i18n.jobId ||
							__( 'ID', 'import-export-by-rockstarlab' )
						}:</th>
						<td>${ job.id }</td>
					</tr>
					${
						job.job_name
							? `<tr>
						<th>${
							window.rslIeData.i18n.jobName ||
							__( 'Job Name', 'import-export-by-rockstarlab' )
						}:</th>
						<td>${ this.escapeHtml( job.job_name ) }</td>
					</tr>`
							: ''
					}
					<tr>
						<th>${
							window.rslIeData.i18n.jobType ||
							__( 'Type', 'import-export-by-rockstarlab' )
						}:</th>
						<td>${ this.getTypeLabel( job.type, job.data_type ) }</td>
					</tr>
					${
						job.data_type
							? `<tr>
						<th>${
							window.rslIeData.i18n.jobDataType ||
							__( 'Data Type', 'import-export-by-rockstarlab' )
						}:</th>
						<td>${ job.data_type }</td>
					</tr>`
							: ''
					}
					${
						job.file_format
							? `<tr>
						<th>${
							window.rslIeData.i18n.jobFileFormat ||
							__( 'File Format', 'import-export-by-rockstarlab' )
						}:</th>
						<td>${ job.file_format }</td>
					</tr>`
							: ''
					}
					<tr>
						<th>${
							window.rslIeData.i18n.jobStatus ||
							__( 'Status', 'import-export-by-rockstarlab' )
						}:</th>
						<td><span class="job-status-badge job-status-${
							job.status
						}">${ this.getStatusLabel( job.status ) }</span></td>
					</tr>
					<tr>
						<th>${
							window.rslIeData.i18n.jobProgress ||
							__( 'Progress', 'import-export-by-rockstarlab' )
						}:</th>
						<td>${ job.progress || 0 }%</td>
					</tr>
					<tr>
						<th>${
							window.rslIeData.i18n.jobItems ||
							__( 'Items', 'import-export-by-rockstarlab' )
						}:</th>
						<td>${ job.processed_items } / ${ job.total_items } (${
							window.rslIeData.i18n.jobSuccess ||
							__( 'Success', 'import-export-by-rockstarlab' )
						}: ${ job.success_items }, ${
							window.rslIeData.i18n.failed ||
							__( 'Failed', 'import-export-by-rockstarlab' )
						}: ${ job.failed_items })</td>
					</tr>
					${
						job.created_at
							? `<tr>
						<th>${
							window.rslIeData.i18n.jobCreated ||
							__( 'Created', 'import-export-by-rockstarlab' )
						}:</th>
						<td>${ job.created_at }</td>
					</tr>`
							: ''
					}
					${
						job.started_at
							? `<tr>
						<th>${
							window.rslIeData.i18n.jobStarted ||
							__( 'Started', 'import-export-by-rockstarlab' )
						}:</th>
						<td>${ job.started_at }</td>
					</tr>`
							: ''
					}
					${
						job.completed_at
							? `<tr>
						<th>${
							window.rslIeData.i18n.jobCompleted ||
							__( 'Completed', 'import-export-by-rockstarlab' )
						}:</th>
						<td>${ job.completed_at }</td>
					</tr>`
							: ''
					}
					${
						job.file_path
							? `<tr>
						<th>${
							window.rslIeData.i18n.jobFile ||
							__( 'File', 'import-export-by-rockstarlab' )
						}:</th>
						<td class="job-file-path" title="${ this.escapeHtml(
							job.file_path
						) }">${ this.escapeHtml(
							// The private storage path (/var/tmp/rsl-ie-<hash>/file_…/) meant nothing
							// to the reader; the file name is what identifies the file. Uploads are
							// stored as source.<ext>, so prefer the name that was uploaded.
							job.parameters?.file_name ||
								String( job.file_path ).split( /[\\/]/ ).pop()
						) }</td>
					</tr>`
							: ''
					}
					${
						job.file_size
							? `<tr><th>${
									window.rslIeData.i18n.jobFileSize ||
									__(
										'File Size',
										'import-export-by-rockstarlab'
									)
							  }:</th><td>${ job.file_size_human }</td></tr>`
							: ''
					}
				</table>
				
				${ this.renderJobErrors( job ) }
				${ this.renderJobWarnings( job ) }
				${
					job.parameters
						? `
					<h3>${
						window.rslIeData.i18n.jobParameters ||
						__( 'Parameters', 'import-export-by-rockstarlab' )
					}</h3>
					<textarea class="job-parameters job-parameters-textarea" disabled readonly>${ this.escapeHtml(
						this.formatJobDetailValue( job.parameters )
					) }</textarea>
				`
						: ''
				}
				${
					job.result
						? `
					<h3>${
						window.rslIeData.i18n.jobResult ||
						__( 'Result', 'import-export-by-rockstarlab' )
					}</h3>
					<pre class="job-parameters">${ this.escapeHtml(
						JSON.stringify( job.result, null, 2 )
					) }</pre>
				`
						: ''
				}
			</div>
		`;

		jQuery( '#job-details-content' ).html( html );
		jQuery( '#job-details-modal' ).show();
	},

	/**
	 * Warnings from the job log (rows that were imported with a note).
	 *
	 * @param {Object} job Job details with warnings.
	 * @return {string} HTML.
	 */
	renderJobWarnings( job ) {
		const errors = Array.isArray( job.log_errors ) ? job.log_errors : [];
		const errorHtml = errors.length
			? `<h3>${
					window.rslIeData.i18n.jobErrors ||
					__( 'Errors', 'import-export-by-rockstarlab' )
			  }</h3>
			<ul class="job-warnings job-errors">${ errors
				.map(
					( log ) =>
						`<li>${ this.escapeHtml(
							String( log.message || '' )
						) }</li>`
				)
				.join( '' ) }</ul>`
			: '';
		const warnings = Array.isArray( job.warnings ) ? job.warnings : [];
		if ( ! warnings.length ) {
			return errorHtml;
		}
		const items = warnings
			.map(
				( log ) =>
					`<li>${ this.escapeHtml(
						String( log.message || '' )
					) }</li>`
			)
			.join( '' );
		return `${ errorHtml }
			<h3>${
				window.rslIeData.i18n.jobWarnings ||
				__( 'Warnings', 'import-export-by-rockstarlab' )
			} (${ warnings.length })</h3>
			<ul class="job-warnings">${ items }</ul>
		`;
	},

	/**
	 * Failed rows of a job: row, field, error and how to fix it.
	 *
	 * @param {Object} job Job details.
	 * @return {string} HTML.
	 */
	renderJobErrors( job ) {
		let result = job.result;
		if ( typeof result === 'string' ) {
			try {
				result = JSON.parse( result );
			} catch ( e ) {
				result = null;
			}
		}
		const errors =
			result && Array.isArray( result.errors ) ? result.errors : [];
		if ( ! errors.length ) {
			return '';
		}
		const i18n = window.rslIeData.i18n || {};
		const rows = errors
			.slice( 0, 50 )
			.map(
				( error ) => `<tr>
					<td>${ this.escapeHtml( String( error.row ?? '' ) ) }</td>
					<td><code>${ this.escapeHtml( error.field || '' ) }</code></td>
					<td>${ this.escapeHtml( error.message || String( error ) ) }</td>
					<td>${ this.escapeHtml( error.hint || '' ) }</td>
				</tr>`
			)
			.join( '' );
		return `
			<h3>${ this.escapeHtml(
				i18n.rowErrorsTitle ||
					__( 'Rows with errors', 'import-export-by-rockstarlab' )
			) } (${ errors.length })</h3>
			<table class="widefat striped rsl-ie-job-row-errors">
				<thead><tr>
					<th>${ this.escapeHtml(
						i18n.rowHeader ||
							__( 'Row', 'import-export-by-rockstarlab' )
					) }</th>
					<th>${ this.escapeHtml(
						i18n.field ||
							__( 'Field', 'import-export-by-rockstarlab' )
					) }</th>
					<th>${ this.escapeHtml(
						i18n.error ||
							__( 'Error', 'import-export-by-rockstarlab' )
					) }</th>
					<th>${ this.escapeHtml(
						i18n.howToFix ||
							__( 'How to fix', 'import-export-by-rockstarlab' )
					) }</th>
				</tr></thead>
				<tbody>${ rows }</tbody>
			</table>
			${
				errors.length > 50
					? `<p class="description">${ this.escapeHtml(
							i18n.rowErrorsMore ||
								__(
									'The first 50 rows are shown. The CSV has all of them.',
									'import-export-by-rockstarlab'
								)
					  ) }</p>`
					: ''
			}
			<p><button type="button" class="button job-action-download-errors" data-job-id="${ parseInt(
				job.id,
				10
			) }"><span class="dashicons dashicons-download"></span> ${ this.escapeHtml(
				i18n.downloadErrorRows ||
					__(
						'Download rows with errors (CSV)',
						'import-export-by-rockstarlab'
					)
			) }</button></p>
		`;
	},

	/**
	 * Format a job detail value for read-only display.
	 */
	formatJobDetailValue( value ) {
		if ( typeof value === 'string' ) {
			try {
				return JSON.stringify( JSON.parse( value ), null, 2 );
			} catch ( e ) {
				return value;
			}
		}

		return JSON.stringify( value, null, 2 );
	},

	/**
	 * Close modal
	 */
	closeModal() {
		jQuery( '.rsl-ie-modal' ).hide();
		this.deleteJobId = null;
	},

	/**
	 * Redirect to job page
	 */
	redirectToJobPage( type, jobId, dataType ) {
		let page = '';

		// AI URL importer jobs have type='import' but data_type='ai_url'
		if ( type === 'import' && dataType === 'ai_url' ) {
			page = 'rsl-ie-import&import_mode=ai-url';
		} else {
			switch ( type ) {
				case 'export':
					page = 'rsl-ie-export';
					break;
				case 'import':
					page = 'rsl-ie-import';
					break;
				case 'update':
					if ( window.rslIeData?.isProAddonActive ) {
						page = 'rsl-ie-content-updater';
					}
					break;
				case 'content_sync_push':
				case 'content_sync_pull':
					page = 'rsl-ie-content-sync';
					break;
				case 'migration_export':
				case 'migration_import':
				case 'migration_sync_push':
				case 'migration_sync_pull':
					page = 'rsl-ie-site-migration';
					break;
			}
			if ( type === 'import' && dataType === 'media_sync' ) {
				page = 'rsl-ie-import&import_mode=media-sync';
			}
		}

		if ( page ) {
			window.location.href =
				'admin.php?page=' + page + '&resume_job=' + jobId;
		}
	},

	/**
	 * Get type label
	 */
	getTypeLabel( type, dataType ) {
		// Content Updater jobs are stored as type=import with data_type=content_update.
		if ( 'import' === type && 'content_update' === dataType ) {
			return __( 'Content Update', 'import-export-by-rockstarlab' );
		}

		const labels = {
			import:
				window.rslIeData.i18n.typeImport ||
				__( 'Import', 'import-export-by-rockstarlab' ),
			export:
				window.rslIeData.i18n.typeExport ||
				__( 'Export', 'import-export-by-rockstarlab' ),
			content_sync_push:
				window.rslIeData.i18n.typeContentSyncPush ||
				__( 'Content Sync - Push', 'import-export-by-rockstarlab' ),
			content_sync_pull:
				window.rslIeData.i18n.typeContentSyncPull ||
				__( 'Content Sync - Pull', 'import-export-by-rockstarlab' ),
			sync_background:
				window.rslIeData.i18n.typeContentSyncBackground ||
				__(
					'Content Sync - Background',
					'import-export-by-rockstarlab'
				),
			migration_export: __(
				'Content Migration - Export File',
				'import-export-by-rockstarlab'
			),
			migration_import: __(
				'Content Migration - Import File',
				'import-export-by-rockstarlab'
			),
			migration_sync_push: __(
				'Content Migration - Sync Push',
				'import-export-by-rockstarlab'
			),
			migration_sync_pull: __(
				'Content Migration - Sync Pull',
				'import-export-by-rockstarlab'
			),
		};
		// Variation transfers of a product sync run as their own child jobs.
		if ( 'migration_sources' === type && 'product' === dataType ) {
			return (
				window.rslIeData.i18n.typeContentSyncVariations ||
				__(
					'Content Sync - Product Variations',
					'import-export-by-rockstarlab'
				)
			);
		}
		return labels[ type ] || type;
	},

	/**
	 * Data type label of a job. Content Sync jobs store "post" for every post type,
	 * so the label is taken from the synced items when the job result lists them.
	 */
	getJobDataTypeLabel( job ) {
		const base = this.getDataTypeLabel( job.data_type );
		if (
			! String( job.type || '' ).startsWith( 'content_sync' ) ||
			'post' !== job.data_type
		) {
			return base;
		}

		let result = job.result;
		if ( 'string' === typeof result ) {
			try {
				result = JSON.parse( result );
			} catch ( error ) {
				return base;
			}
		}

		const types = [
			...new Set(
				( ( result && result.posts ) || [] )
					.map( ( item ) => item && item.type )
					.filter( Boolean )
			),
		];
		if ( ! types.length ) {
			return base;
		}

		return types
			.map( ( type ) =>
				'post' === type
					? base
					: 'page' === type
					? __( 'Pages', 'import-export-by-rockstarlab' )
					: type.charAt( 0 ).toUpperCase() + type.slice( 1 )
			)
			.join( ' + ' );
	},

	getDataTypeLabel( dataType ) {
		const labels = {
			urls: __( 'All Site URLs', 'import-export-by-rockstarlab' ),
			post: __( 'Blog Posts', 'import-export-by-rockstarlab' ),
			page: __( 'Pages', 'import-export-by-rockstarlab' ),
			media: __( 'Media', 'import-export-by-rockstarlab' ),
			media_sync: __( 'Media Sync', 'import-export-by-rockstarlab' ),
			site_migration: __(
				'Content Migration',
				'import-export-by-rockstarlab'
			),
			content_update: __(
				'Content Updater',
				'import-export-by-rockstarlab'
			),
			menu: __( 'Menus', 'import-export-by-rockstarlab' ),
			user: __( 'Users', 'import-export-by-rockstarlab' ),
			comment: __( 'Comments', 'import-export-by-rockstarlab' ),
			taxonomy: __( 'Taxonomy Terms', 'import-export-by-rockstarlab' ),
			woo_product: __(
				'WooCommerce Products',
				'import-export-by-rockstarlab'
			),
			woo_order: __(
				'WooCommerce Orders',
				'import-export-by-rockstarlab'
			),
			woo_coupon: __(
				'WooCommerce Coupons',
				'import-export-by-rockstarlab'
			),
			woo_attribute: __(
				'WooCommerce Attributes',
				'import-export-by-rockstarlab'
			),
			woo_review: __(
				'WooCommerce Reviews',
				'import-export-by-rockstarlab'
			),
			woo_refund: __(
				'WooCommerce Refunds',
				'import-export-by-rockstarlab'
			),
			woo_customer: __(
				'WooCommerce Customers',
				'import-export-by-rockstarlab'
			),
			woo_shipping_zone: __(
				'WooCommerce Shipping Zones',
				'import-export-by-rockstarlab'
			),
			woo_tax_rate: __(
				'WooCommerce Tax Rates',
				'import-export-by-rockstarlab'
			),
			custom_post_types: __(
				'Custom Post Types',
				'import-export-by-rockstarlab'
			),
			form_entries: __( 'Form Entries', 'import-export-by-rockstarlab' ),
			database_table: __(
				'MySQL Database Table',
				'import-export-by-rockstarlab'
			),
			table: __( 'MySQL Database Table', 'import-export-by-rockstarlab' ),
		};

		const postTypes = window.rslIeData.postTypeLabels || {};
		return labels[ dataType ] || postTypes[ dataType ] || dataType || '-';
	},

	/**
	 * Get status label
	 */
	getStatusLabel( status ) {
		const labels = {
			pending:
				window.rslIeData.i18n.statusPending ||
				__( 'Pending', 'import-export-by-rockstarlab' ),
			processing:
				window.rslIeData.i18n.statusProcessing ||
				__( 'Processing', 'import-export-by-rockstarlab' ),
			completed:
				window.rslIeData.i18n.statusCompleted ||
				__( 'Completed', 'import-export-by-rockstarlab' ),
			failed:
				window.rslIeData.i18n.statusFailed ||
				__( 'Failed', 'import-export-by-rockstarlab' ),
			paused:
				window.rslIeData.i18n.statusPaused ||
				__( 'Paused', 'import-export-by-rockstarlab' ),
			cancelled:
				window.rslIeData.i18n.statusCancelled ||
				__( 'Cancelled', 'import-export-by-rockstarlab' ),
		};
		return labels[ status ] || status;
	},

	/**
	 * Format date
	 */
	formatDate( dateString ) {
		if ( ! dateString ) {
			return '-';
		}
		const date = new Date( dateString );
		return date.toLocaleString();
	},

	escapeHtml( text ) {
		const div = document.createElement( 'div' );
		div.textContent = text == null ? '' : String( text );
		return div.innerHTML;
	},

	escapeAttr( text ) {
		return this.escapeHtml( text )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#039;' );
	},
};

export default JobsLogModule;
