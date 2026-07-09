/**
 * Jobs Log Module
 *
 * Handles jobs log page functionality
 */

import Utils from './utils';

const JobsLogModule = {
	currentPage: 1,
	perPage: 20,
	totalPages: 1,
	totalJobs: 0,
	filters: {
		type: '',
		status: '',
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
		$page.on( 'click', '.job-action-restart', ( e ) =>
			this.restartJob( e )
		);
		$page.on( 'click', '.job-action-retry', ( e ) => this.retryJob( e ) );
		$page.on( 'click', '.job-action-delete', ( e ) => this.deleteJob( e ) );
		$page.on( 'click', '.job-action-download', ( e ) =>
			this.downloadFile( e )
		);
		$page.on( 'click', '.job-action-view', ( e ) =>
			this.viewJobDetails( e )
		);

		// Modal close - bind to document for modals outside page container
		jQuery( document ).on( 'click', '.rsl-ie-modal-close', () =>
			this.closeModal()
		);
		jQuery( document ).on( 'click', '.rsl-ie-modal-overlay', () =>
			this.closeModal()
		);

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
		this.currentPage = 1;
		this.loadJobs();
	},

	/**
	 * Reset filters
	 */
	resetFilters() {
		jQuery( '#filter-type' ).val( '' );
		jQuery( '#filter-status' ).val( '' );
		this.filters = { type: '', status: '' };
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
				`<tr class="no-items"><td colspan="9">${ window.rslIeData.i18n.noJobsFound }</td></tr>`
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
	 * Render single job row
	 */
	renderJobRow( job ) {
		const statusClass = 'status-' + job.status;
		const typeLabel = this.getTypeLabel( job.type );
		const dataTypeLabel = this.getDataTypeLabel( job.data_type );
		const statusLabel = this.getStatusLabel( job.status );
		const progressBar = this.renderProgressBar( job );
		const actions = this.renderActions( job );

		return `
			<tr class="job-row ${ statusClass }" data-job-id="${ job.id }">
				<td class="column-id">${ job.id }</td>
				<td class="column-type">
					<span class="job-type-badge job-type-${ job.type }">${ typeLabel }</span>
				</td>
				<td class="column-data-type">${ this.escapeHtml( dataTypeLabel ) }</td>
				<td class="column-status">
					<span class="job-status-badge job-status-${
						job.status
					}">${ statusLabel }</span>
				</td>
				<td class="column-progress">${ progressBar }</td>
				<td class="column-items">
					<div class="items-info">
						<div><strong>${ job.processed_items }</strong> / ${ job.total_items }</div>
						${
							job.failed_items > 0
								? `<div class="failed-count">${
										window.rslIeData.i18n.failed || 'Failed'
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
	 * Render progress bar
	 */
	renderProgressBar( job ) {
		const progress = job.progress || 0;
		return `
			<div class="progress-bar-wrapper">
				<div class="progress-bar">
					<div class="progress-bar-fill" style="width: ${ progress }%"></div>
				</div>
				<span class="progress-text">${ progress }%</span>
			</div>
		`;
	},

	/**
	 * Render action buttons
	 */
	renderActions( job ) {
		let actions = [];

		// View details
		actions.push(
			`<button class="button button-small job-action-view" title="${
				window.rslIeData.i18n.viewDetails || 'View Details'
			}"><span class="dashicons dashicons-visibility"></span></button>`
		);

		// Resume
		if ( job.can_resume ) {
			actions.push(
				`<button class="button button-small job-action-resume" title="${
					window.rslIeData.i18n.resume || 'Resume'
				}"><span class="dashicons dashicons-controls-play"></span></button>`
			);
		}

		// Restart (create a new job with same settings)
		actions.push(
			`<button class="button button-small job-action-restart" title="${
				window.rslIeData.i18n.restart || 'Restart'
			}"><span class="dashicons dashicons-controls-repeat"></span></button>`
		);

		// Retry - not available for media_sync jobs (files may have been moved)
		if ( job.type !== 'media_sync' ) {
			actions.push(
				`<button class="button button-small job-action-retry" title="${
					window.rslIeData.i18n.retry ||
					'Retry (Create new job with same parameters)'
				}"><span class="dashicons dashicons-update"></span></button>`
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
					window.rslIeData.i18n.download || 'Download'
				}"><span class="dashicons dashicons-download"></span></button>`
			);
		}

		// Delete
		if ( job.can_delete ) {
			actions.push(
				`<button class="button button-small job-action-delete" title="${
					window.rslIeData.i18n.delete || 'Delete'
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
					: 'Unknown error occurred';
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
						<th>${ window.rslIeData.i18n.jobId || 'ID' }:</th>
						<td>${ job.id }</td>
					</tr>
					<tr>
						<th>${ window.rslIeData.i18n.jobType || 'Type' }:</th>
						<td>${ this.getTypeLabel( job.type ) }</td>
					</tr>
					${
						job.data_type
							? `<tr>
						<th>${ window.rslIeData.i18n.jobDataType || 'Data Type' }:</th>
						<td>${ job.data_type }</td>
					</tr>`
							: ''
					}
					${
						job.file_format
							? `<tr>
						<th>${ window.rslIeData.i18n.jobFileFormat || 'File Format' }:</th>
						<td>${ job.file_format }</td>
					</tr>`
							: ''
					}
					<tr>
						<th>${ window.rslIeData.i18n.jobStatus || 'Status' }:</th>
						<td><span class="job-status-badge job-status-${
							job.status
						}">${ this.getStatusLabel( job.status ) }</span></td>
					</tr>
					<tr>
						<th>${ window.rslIeData.i18n.jobProgress || 'Progress' }:</th>
						<td>${ job.progress || 0 }%</td>
					</tr>
					<tr>
						<th>${ window.rslIeData.i18n.jobItems || 'Items' }:</th>
						<td>${ job.processed_items } / ${ job.total_items } (${
							window.rslIeData.i18n.jobSuccess || 'Success'
						}: ${ job.success_items }, ${
							window.rslIeData.i18n.failed || 'Failed'
						}: ${ job.failed_items })</td>
					</tr>
					${
						job.created_at
							? `<tr>
						<th>${ window.rslIeData.i18n.jobCreated || 'Created' }:</th>
						<td>${ job.created_at }</td>
					</tr>`
							: ''
					}
					${
						job.started_at
							? `<tr>
						<th>${ window.rslIeData.i18n.jobStarted || 'Started' }:</th>
						<td>${ job.started_at }</td>
					</tr>`
							: ''
					}
					${
						job.completed_at
							? `<tr>
						<th>${ window.rslIeData.i18n.jobCompleted || 'Completed' }:</th>
						<td>${ job.completed_at }</td>
					</tr>`
							: ''
					}
					${
						job.file_path
							? `<tr>
						<th>${ window.rslIeData.i18n.jobFile || 'File' }:</th>
						<td>${ job.file_path }</td>
					</tr>`
							: ''
					}
					${
						job.file_size
							? `<tr><th>${
									window.rslIeData.i18n.jobFileSize ||
									'File Size'
							  }:</th><td>${ job.file_size_human }</td></tr>`
							: ''
					}
				</table>
				
				${
					job.parameters
						? `
					<h3>${ window.rslIeData.i18n.jobParameters || 'Parameters' }</h3>
					<pre class="job-parameters">${ JSON.stringify( job.parameters, null, 2 ) }</pre>
				`
						: ''
				}
			</div>
		`;

		jQuery( '#job-details-content' ).html( html );
		jQuery( '#job-details-modal' ).show();
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
			page = 'rsl-ie-ai-url-importer';
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
				case 'media_sync':
					page = 'rsl-ie-media-sync';
					break;
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
	getTypeLabel( type ) {
		const labels = {
			import: window.rslIeData.i18n.typeImport || 'Import',
			export: window.rslIeData.i18n.typeExport || 'Export',
			update: window.rslIeData.i18n.typeUpdate || 'Update',
			media_sync: window.rslIeData.i18n.typeMediaSync || 'Media Sync',
		};
		return labels[ type ] || type;
	},

	getDataTypeLabel( dataType ) {
		const labels = {
			urls: 'All Site URLs',
			post: 'Blog Posts',
			page: 'Pages',
			media: 'Media',
			menu: 'Menus',
			user: 'Users',
			comment: 'Comments',
			taxonomy: 'Taxonomy Terms',
			woo_product: 'WooCommerce Products',
			woo_order: 'WooCommerce Orders',
			woo_coupon: 'WooCommerce Coupons',
			database_table: 'MySQL Database Table',
		};

		return labels[ dataType ] || dataType || '-';
	},

	/**
	 * Get status label
	 */
	getStatusLabel( status ) {
		const labels = {
			pending: window.rslIeData.i18n.statusPending || 'Pending',
			processing: window.rslIeData.i18n.statusProcessing || 'Processing',
			completed: window.rslIeData.i18n.statusCompleted || 'Completed',
			failed: window.rslIeData.i18n.statusFailed || 'Failed',
			paused: window.rslIeData.i18n.statusPaused || 'Paused',
			cancelled: window.rslIeData.i18n.statusCancelled || 'Cancelled',
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
};

export default JobsLogModule;
