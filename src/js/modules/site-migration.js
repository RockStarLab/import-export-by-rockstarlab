/**
 * Site Migration wizard.
 */

import Utils from './utils';

const SiteMigration = {
	step: 1,
	plan: [],
	sites: [],
	parentJobId: null,
	selectedFile: null,
	migrationStartTime: null,

	init() {
		if ( ! jQuery( '#rsl-ie-site-migration' ).length ) {
			return;
		}
		this.bindEvents();
		this.loadPlan();
	},

	bindEvents() {
		const $page = jQuery( '#rsl-ie-site-migration' );
		$page.on( 'click', '.rsl-ie-migration-next', () => this.showStep( 2 ) );
		$page.on( 'click', '.rsl-ie-migration-prev', () => this.showStep( 1 ) );
		$page.on( 'click', '.rsl-ie-migration-start', () => this.start() );
		$page.on( 'change', 'input[name="rsl_ie_migration_method"]', () =>
			this.updateMode()
		);
		$page.on( 'change', 'input[name="rsl_ie_file_operation"]', () =>
			this.updateMode()
		);
		$page.on( 'click', '#rsl-ie-select-migration-file', () =>
			$page.find( '#rsl-ie-migration-file' ).trigger( 'click' )
		);
		$page.on( 'change', '#rsl-ie-migration-file', ( e ) => {
			this.setSelectedFile( e.currentTarget.files[ 0 ] || null );
		} );
		$page.on( 'click', '.rsl-ie-remove-migration-file', () =>
			this.setSelectedFile( null )
		);
		$page.on( 'change', '#rsl-ie-migration-enable-replace-links', () =>
			this.toggleReplaceLinksRepeater()
		);
		$page.on( 'click', '.rsl-ie-migration-add-replace-link', () =>
			this.addReplaceLinksRow()
		);
		$page.on( 'click', '.rsl-ie-migration-remove-replace-link', ( e ) =>
			this.removeReplaceLinksRow( e )
		);
		$page.on( 'dragover', '#rsl-ie-migration-upload-area', ( e ) => {
			e.preventDefault();
			jQuery( e.currentTarget ).addClass( 'rsl-ie-dragover' );
		} );
		$page.on( 'dragleave', '#rsl-ie-migration-upload-area', ( e ) => {
			jQuery( e.currentTarget ).removeClass( 'rsl-ie-dragover' );
		} );
		$page.on( 'drop', '#rsl-ie-migration-upload-area', ( e ) => {
			e.preventDefault();
			jQuery( e.currentTarget ).removeClass( 'rsl-ie-dragover' );
			const files = e.originalEvent.dataTransfer.files;
			this.setSelectedFile( files.length ? files[ 0 ] : null );
		} );
	},

	async loadPlan() {
		try {
			const response = await Utils.ajax( 'migration_get_plan' );
			this.plan = response.steps || [];
			this.sites = response.connectedSites || [];
			this.renderSites();
			this.updateMode();
		} catch ( error ) {
			Utils.handleError( error, 'Load migration plan' );
		}
	},

	renderSites() {
		const activeSites = this.sites.filter( ( site ) => site.isActive );
		const $select = jQuery( '#rsl-ie-migration-site' ).empty();
		activeSites.forEach( ( site ) => {
			$select.append(
				`<option value="${ site.id }">${ Utils.escapeHtml(
					site.name
				) } - ${ Utils.escapeHtml( site.url ) }</option>`
			);
		} );

		const syncAvailable = activeSites.length > 0;
		jQuery( '.rsl-ie-sync-choice' ).toggleClass(
			'is-disabled',
			! syncAvailable
		);
		jQuery( '.rsl-ie-sync-choice input' ).prop(
			'disabled',
			! syncAvailable
		);
		jQuery( '.rsl-ie-sync-unavailable' ).toggle( ! syncAvailable );
	},

	updateMode() {
		const method = this.getMethod();
		const operation = this.getFileOperation();
		jQuery( '.rsl-ie-file-mode-panel' ).toggle( method === 'file' );
		jQuery( '.rsl-ie-sync-mode-panel' ).toggle( method === 'sync' );
		jQuery( '.rsl-ie-migration-upload' ).toggle(
			method === 'file' && operation === 'upload' && ! this.selectedFile
		);
		jQuery( '.rsl-ie-migration-file-info' ).toggle(
			method === 'file' && operation === 'upload' && !! this.selectedFile
		);
		jQuery( '.rsl-ie-migration-replace-links' ).toggle(
			method === 'file' && operation === 'upload'
		);
	},

	showStep( step ) {
		this.step = step;
		jQuery( '.rsl-ie-migration-step' ).removeClass( 'active' );
		jQuery( `.rsl-ie-migration-step[data-step="${ step }"]` ).addClass(
			'active'
		);
		jQuery( '.rsl-ie-migration-steps .rsl-ie-step-indicator' ).removeClass(
			'active completed'
		);
		jQuery( '.rsl-ie-migration-steps .rsl-ie-step-indicator' ).each(
			function () {
				const dotStep = parseInt( jQuery( this ).data( 'step' ), 10 );
				jQuery( this ).toggleClass( 'active', dotStep === step );
				jQuery( this ).toggleClass( 'completed', dotStep < step );
			}
		);
	},

	getMethod() {
		return jQuery( 'input[name="rsl_ie_migration_method"]:checked' ).val();
	},

	getFileOperation() {
		return jQuery( 'input[name="rsl_ie_file_operation"]:checked' ).val();
	},

	getSelectedSteps() {
		return this.expandSteps( this.plan );
	},

	expandSteps( steps ) {
		return steps.flatMap( ( step ) => {
			if ( Array.isArray( step.children ) && step.children.length ) {
				return step.children;
			}
			return [ step ];
		} );
	},

	setSelectedFile( file ) {
		if ( file && ! /\.zip$/i.test( file.name ) ) {
			Utils.showNotice(
				'Please choose a ZIP migration package.',
				'error'
			);
			return;
		}

		this.selectedFile = file;
		jQuery( '#rsl-ie-migration-file' ).val( '' );
		jQuery( '.rsl-ie-migration-upload' ).toggle( ! file );
		jQuery( '.rsl-ie-migration-file-info' ).toggle( !! file );
		jQuery( '.rsl-ie-migration-file-name' ).text( file ? file.name : '' );
		jQuery( '.rsl-ie-migration-file-size' ).text(
			file ? Utils.formatFileSize( file.size ) : ''
		);
	},

	async start() {
		try {
			this.showStep( 3 );
			this.migrationStartTime = Date.now();
			this.resetCompletion();
			this.log( 'Starting migration...' );
			const method = this.getMethod();
			const operation =
				method === 'sync'
					? 'sync'
					: this.getFileOperation() === 'upload'
					? 'import'
					: 'export';
			const direction = jQuery(
				'input[name="rsl_ie_migration_direction"]:checked'
			).val();
			const steps = this.getSelectedSteps();

			const parent = await Utils.ajax( 'migration_start', {
				mode: method === 'sync' ? 'content_sync' : 'export_file',
				operation,
				direction,
				site_id: jQuery( '#rsl-ie-migration-site' ).val() || 0,
				steps: steps.map( ( step ) => step.key || step.type ),
			} );
			this.parentJobId = parent.job_id;

			if ( method === 'sync' ) {
				await this.runSync( steps, direction );
			} else if ( operation === 'import' ) {
				await this.runPackageImport();
			} else {
				await this.runPackageExport( steps );
			}
		} catch ( error ) {
			const message = this.getErrorMessage( error );
			this.log( `Migration failed: ${ message }`, 'error' );
			await this.updateParent( 1, 1, 'failed', { error: message } );
			Utils.handleError( error, 'Site migration' );
		}
	},

	getErrorMessage( error ) {
		if ( ! error ) {
			return 'Unknown error';
		}
		if ( typeof error === 'string' ) {
			return error;
		}
		if ( error instanceof Error && error.message ) {
			return error.message;
		}
		if ( error.responseJSON ) {
			return this.getErrorMessage( error.responseJSON );
		}
		if ( error.data ) {
			return this.getErrorMessage( error.data );
		}
		if ( error.message ) {
			return this.getErrorMessage( error.message );
		}
		if ( error.error ) {
			return this.getErrorMessage( error.error );
		}
		try {
			return JSON.stringify( error );
		} catch ( e ) {
			return 'Unknown error';
		}
	},

	async runPackageExport( steps ) {
		const files = [];
		let exportedItems = 0;
		for ( let i = 0; i < steps.length; i++ ) {
			const step = steps[ i ];
			const filename = this.getExportStepFilename(
				step,
				files.length + 1
			);
			this.log( `Exporting ${ step.label }...` );
			const start = await Utils.ajax( 'export_start', {
				export_type: step.type,
				format: 'json',
				options: {
					custom_export_file_name: filename.replace( /\.json$/, '' ),
					items_per_iteration: 100,
					...( step.taxonomy ? { taxonomy: step.taxonomy } : {} ),
					...( step.post_type ? { post_type: step.post_type } : {} ),
				},
				dynamic_filters: step.taxonomy
					? [
							{
								field: 'taxonomy',
								condition: 'equals',
								value: step.taxonomy,
							},
					  ]
					: [],
				fields: step.fields,
				format_options: { json_pretty_print: 1 },
			} );
			const job = await this.processExportJob( start.job_id );
			const processed = parseInt( job.processed || job.total || 0, 10 );
			if ( processed <= 0 ) {
				this.log( `${ step.label } is empty, skipping export file.` );
				await this.updateParent( i + 1, steps.length, 'processing' );
				continue;
			}
			exportedItems += processed;
			files.push( {
				type: step.type,
				taxonomy: step.taxonomy || '',
				post_type: step.post_type || '',
				name: filename,
				path: job.file_path,
			} );
			await this.updateParent( i + 1, steps.length, 'processing' );
		}

		if ( ! files.length ) {
			throw new Error( 'No migration data found to export.' );
		}

		const packageResult = await Utils.ajax( 'migration_package_exports', {
			job_id: this.parentJobId,
			files,
		} );
		const download = await Utils.ajax( 'migration_get_download_url', {
			job_id: packageResult.job_id,
		} );
		this.completeExport( {
			downloadUrl: download.download_url,
			filename: download.filename,
			fileSize: download.file_size || 0,
			filesCount: files.length,
			exportedItems,
		} );
	},

	async processExportJob( jobId ) {
		let progress = null;
		do {
			await Utils.ajax( 'export_process_batch', { job_id: jobId } );
			progress = await Utils.ajax( 'export_get_progress', {
				job_id: jobId,
			} );
		} while (
			progress.status !== 'completed' &&
			progress.status !== 'failed'
		);
		if ( progress.status === 'failed' ) {
			throw new Error( progress.result?.error || 'Export failed' );
		}
		return progress;
	},

	getExportStepFilename( step, index ) {
		const number = String( index ).padStart( 2, '0' );
		let slug = step.type;
		if ( step.taxonomy ) {
			slug = `taxonomy-${ step.taxonomy }`;
		} else if ( step.post_type ) {
			slug = `cpt-${ step.post_type }`;
		} else if ( step.type === 'user' ) {
			slug = 'users';
		} else if ( step.type === 'media' ) {
			slug = 'media';
		} else if ( step.type === 'post' ) {
			slug = 'posts';
		} else if ( step.type === 'page' ) {
			slug = 'pages';
		} else if ( step.type.startsWith( 'woo_' ) ) {
			slug = step.type.replace( /^woo_/, 'woo-' ).replace( /_/g, '-' );
			if ( ! slug.endsWith( 's' ) ) {
				slug += 's';
			}
		}
		slug = slug
			.replace( /[^a-z0-9_-]+/gi, '-' )
			.replace( /-+/g, '-' )
			.toLowerCase();
		return `${ number }-${ slug }.json`;
	},

	async runPackageImport() {
		if ( ! this.selectedFile ) {
			throw new Error( 'Please choose a migration ZIP file.' );
		}
		const replaceLinks = jQuery(
			'#rsl-ie-migration-enable-replace-links'
		).is( ':checked' );
		const replaceLinksRules = this.getReplaceLinksRules();
		const stats = {
			filesCount: 0,
			success: 0,
			created: 0,
			updated: 0,
			skipped: 0,
			failed: 0,
			emptyFiles: 0,
			replaceLinks,
			replacedUrls: 0,
			repairedReferences: 0,
		};
		const formData = new FormData();
		formData.append( 'action', 'rsl_ie_migration_upload_package' );
		formData.append( 'file', this.selectedFile );
		const upload = await jQuery
			.ajax( {
				url: window.rslIeData.ajaxUrl,
				type: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				dataType: 'json',
			} )
			.catch( ( error ) => {
				throw new Error( this.getErrorMessage( error ) );
			} );
		if ( ! upload.success ) {
			throw new Error(
				this.getErrorMessage( upload ) || 'Package upload failed'
			);
		}

		const steps = upload.data.steps || [];
		stats.filesCount = steps.length;
		for ( let i = 0; i < steps.length; i++ ) {
			const step = steps[ i ];
			this.log( `Importing ${ step.file_name }...` );
			const headers = await this.getFileHeaders(
				step.file_path,
				step.format
			);
			if ( ! headers.length ) {
				this.log( `${ step.file_name } is empty, skipping.` );
				stats.emptyFiles++;
				await this.updateParent( i + 1, steps.length, 'processing' );
				continue;
			}
			const start = await Utils.ajax( 'import_start', {
				file_path: step.file_path,
				import_type: step.type,
				format: step.format,
				mapping: headers.map( ( field, index ) => ( {
					source_index: index,
					source_field: field,
					target_field: field,
				} ) ),
				options: {
					duplicate_handling: 'update',
					if_exists: 'update',
					if_not_exists: 'create',
					unique_field: this.getUniqueField( step.type, headers ),
					...( step.taxonomy ? { taxonomy: step.taxonomy } : {} ),
					...( step.post_type
						? {
								post_type: step.post_type,
								custom_post_type: step.post_type,
						  }
						: {} ),
					download_images: true,
					auto_import_media: true,
					batch_size: 25,
					replace_links: replaceLinks,
					replace_links_rules: replaceLinksRules,
				},
			} );
			const response = await this.processImportJob( start.job_id );
			this.addImportStats( stats, response.result || {} );
			await this.updateParent( i + 1, steps.length, 'processing' );
		}
		const finalize = await Utils.ajax( 'migration_finalize_import' );
		stats.repairedReferences = parseInt( finalize.repaired || 0, 10 );
		await this.updateParent( steps.length, steps.length, 'completed' );
		this.completeImport( stats );
	},

	async getFileHeaders( filePath, format ) {
		const upload = await Utils.ajax( 'migration_get_file_headers', {
			file_path: filePath,
			format,
		} );
		return upload?.headers || [];
	},

	toggleReplaceLinksRepeater() {
		jQuery( '#rsl-ie-migration-replace-links-repeater' ).toggle(
			jQuery( '#rsl-ie-migration-enable-replace-links' ).is( ':checked' )
		);
		this.updateReplaceLinksRemoveButtons();
	},

	addReplaceLinksRow() {
		const $repeater = jQuery( '#rsl-ie-migration-replace-links-repeater' );
		const currentSiteUrl = $repeater.data( 'currentSiteUrl' ) || '';
		const $row = $repeater.find( '.rsl-ie-replace-links-row' ).first();
		const $clone = $row.clone();

		$clone.find( '.rsl-ie-migration-replace-what' ).val( '' );
		$clone.find( '.rsl-ie-migration-replace-to' ).val( currentSiteUrl );

		$repeater.find( '.rsl-ie-replace-links-rows' ).append( $clone );
		this.updateReplaceLinksRemoveButtons();
	},

	removeReplaceLinksRow( event ) {
		const $rows = jQuery(
			'#rsl-ie-migration-replace-links-repeater .rsl-ie-replace-links-row'
		);

		if ( $rows.length <= 1 ) {
			const $row = jQuery( event.currentTarget ).closest(
				'.rsl-ie-replace-links-row'
			);
			$row.find( '.rsl-ie-migration-replace-what' ).val( '' );
			$row.find( '.rsl-ie-migration-replace-to' ).val(
				jQuery( '#rsl-ie-migration-replace-links-repeater' ).data(
					'currentSiteUrl'
				) || ''
			);
			return;
		}

		jQuery( event.currentTarget )
			.closest( '.rsl-ie-replace-links-row' )
			.remove();
		this.updateReplaceLinksRemoveButtons();
	},

	updateReplaceLinksRemoveButtons() {
		const $rows = jQuery(
			'#rsl-ie-migration-replace-links-repeater .rsl-ie-replace-links-row'
		);
		$rows
			.find( '.rsl-ie-migration-remove-replace-link' )
			.prop( 'disabled', $rows.length <= 1 );
	},

	getReplaceLinksRules() {
		if (
			! jQuery( '#rsl-ie-migration-enable-replace-links' ).is(
				':checked'
			)
		) {
			return [];
		}

		const rules = [];
		jQuery(
			'#rsl-ie-migration-replace-links-repeater .rsl-ie-replace-links-row'
		).each( function () {
			const $row = jQuery( this );
			const search = (
				$row.find( '.rsl-ie-migration-replace-what' ).val() || ''
			)
				.toString()
				.trim();
			const replace = (
				$row.find( '.rsl-ie-migration-replace-to' ).val() || ''
			)
				.toString()
				.trim();

			if ( search !== '' ) {
				rules.push( {
					search,
					replace,
				} );
			}
		} );

		return rules;
	},

	async processImportJob( jobId ) {
		let response = null;
		do {
			response = await Utils.ajax( 'import_process_batch', {
				job_id: jobId,
			} );
		} while ( ! response.completed );
		if ( response.status === 'failed' ) {
			throw new Error( response.result?.error || 'Import failed' );
		}
		return response;
	},

	async runSync( steps, direction ) {
		const siteId = jQuery( '#rsl-ie-migration-site' ).val();
		const stats = {
			typesCount: steps.length,
			syncedItems: 0,
			created: 0,
			updated: 0,
			skipped: 0,
			failed: 0,
			direction,
		};
		for ( let i = 0; i < steps.length; i++ ) {
			const step = steps[ i ];
			this.log(
				`${ direction === 'pull' ? 'Pulling' : 'Pushing' } ${
					step.label
				}...`
			);
			if ( step.type === 'taxonomy' ) {
				const response = await Utils.ajax(
					`content_sync_${ direction }_terms`,
					{ site_id: siteId }
				);
				this.addSyncStats( stats, response );
			} else if (
				step.type === 'comment' ||
				step.type === 'woo_review'
			) {
				const response = await Utils.ajax(
					`content_sync_${ direction }_comments`,
					{ site_id: siteId }
				);
				this.addSyncStats( stats, response );
			} else if (
				[ 'post', 'page', 'custom_post_types', 'woo_product' ].includes(
					step.type
				)
			) {
				const postType =
					step.post_type ||
					( step.type === 'woo_product' ? 'product' : step.type );
				const postIds =
					direction === 'pull'
						? await this.getRemotePostIds( siteId, postType )
						: await this.getLocalPostIds( postType );
				if ( ! postIds.length ) {
					this.log( `No ${ step.label } found, skipping.` );
					await this.updateParent(
						i + 1,
						steps.length,
						'processing'
					);
					continue;
				}
				const response = await Utils.ajax(
					`content_sync_${ direction }`,
					{
						site_id: siteId,
						post_type: postType,
						post_ids: postIds,
						post_mapping: '{}',
					}
				);
				this.addSyncStats( stats, response, postIds.length );
			}
			await this.updateParent( i + 1, steps.length, 'processing' );
		}
		await this.updateParent( steps.length, steps.length, 'completed' );
		this.completeSync( stats );
	},

	async getLocalPostIds( postType ) {
		const response = await Utils.ajax( 'migration_get_local_post_ids', {
			post_type: postType,
		} );
		return response.ids || [];
	},

	async getRemotePostIds( siteId, postType ) {
		const response = await Utils.ajax( 'content_sync_get_remote_posts', {
			site_id: siteId,
			post_type: postType,
			per_page: 500,
		} );
		return ( response.posts || [] )
			.map( ( post ) => post.ID || post.id )
			.filter( Boolean );
	},

	getUniqueField( type, headers ) {
		const preferred =
			type === 'media'
				? 'file_url'
				: type === 'comment'
				? 'comment_ID'
				: 'ID';
		return headers.includes( preferred ) ? preferred : headers[ 0 ];
	},

	async updateParent( processed, total, status = 'processing', result = {} ) {
		this.setProgress(
			total ? Math.round( ( processed / total ) * 100 ) : 0
		);
		if ( this.parentJobId ) {
			await Utils.ajax( 'migration_update', {
				job_id: this.parentJobId,
				processed,
				total,
				status,
				result,
			} );
		}
	},

	setProgress( percent ) {
		jQuery( '.rsl-ie-migration-progress-text' ).text( `${ percent }%` );
		jQuery( '.rsl-ie-migration-progress .rsl-ie-progress-bar-fill' ).css(
			'width',
			`${ percent }%`
		);
	},

	log( message, type = 'info' ) {
		jQuery( '.rsl-ie-migration-log' )
			.removeClass( 'is-info is-error' )
			.addClass( `is-${ type }` )
			.text( message );
	},

	addImportStats( stats, result ) {
		stats.success += parseInt( result.success || 0, 10 );
		stats.created += parseInt( result.created || 0, 10 );
		stats.updated += parseInt( result.updated || 0, 10 );
		stats.skipped += parseInt( result.skipped || 0, 10 );
		stats.failed += parseInt( result.failed || 0, 10 );
		stats.replacedUrls += parseInt( result.replaced_urls || 0, 10 );
	},

	addSyncStats( stats, result = {}, fallbackProcessed = 0 ) {
		const processed =
			result.processed ||
			result.total ||
			result.success ||
			fallbackProcessed ||
			0;
		stats.syncedItems += parseInt( processed, 10 );
		stats.created += parseInt( result.created || 0, 10 );
		stats.updated += parseInt( result.updated || 0, 10 );
		stats.skipped += parseInt( result.skipped || 0, 10 );
		stats.failed += parseInt( result.failed || 0, 10 );
		if ( result.images_synced ) {
			stats.syncedItems += parseInt( result.images_synced, 10 );
		}
	},

	completeImport( stats ) {
		this.completeMigrationResult( {
			title: 'Migration Import Completed Successfully!',
			subtitle: 'Your migration package has been imported successfully.',
			filesLabel: 'Import Files',
			filesValue: stats.filesCount || 0,
			itemsLabel: 'Imported Items',
			itemsValue: stats.success || 0,
			extraLabel: stats.replaceLinks
				? 'URLs Replaced'
				: 'ACF References Fixed',
			extraValue: stats.replaceLinks
				? stats.replacedUrls || 0
				: stats.repairedReferences || 0,
			logMessage: 'Migration import completed.',
			showDownload: false,
		} );
	},

	completeSync( stats ) {
		this.completeMigrationResult( {
			title: 'Migration Sync Completed Successfully!',
			subtitle:
				'Your full-site migration data has been synced through the connected site.',
			filesLabel: 'Synced Types',
			filesValue: stats.typesCount || 0,
			itemsLabel: 'Synced Items',
			itemsValue: stats.syncedItems || 0,
			extraLabel: 'Direction',
			extraValue: stats.direction === 'pull' ? 'Pull' : 'Push',
			logMessage: 'Content sync migration completed.',
			showDownload: false,
		} );
	},

	completeMigrationResult( options ) {
		this.setProgress( 100 );
		this.log( options.logMessage || 'Done.' );
		jQuery( '.rsl-ie-migration-progress-panel' ).hide();
		jQuery( '.rsl-ie-migration-complete-title' ).text( options.title );
		jQuery( '.rsl-ie-migration-complete-subtitle' ).text(
			options.subtitle
		);
		jQuery( '.rsl-ie-migration-result-files-label' ).text(
			options.filesLabel
		);
		jQuery( '.rsl-ie-migration-result-files' ).text( options.filesValue );
		jQuery( '.rsl-ie-migration-result-items-label' ).text(
			options.itemsLabel
		);
		jQuery( '.rsl-ie-migration-result-items' ).text( options.itemsValue );
		jQuery( '.rsl-ie-migration-result-size-label' ).text(
			options.extraLabel
		);
		jQuery( '.rsl-ie-migration-result-size' ).text( options.extraValue );
		jQuery( '.rsl-ie-migration-result-duration' ).text(
			this.formatDuration(
				Math.max(
					0,
					Date.now() - ( this.migrationStartTime || Date.now() )
				)
			)
		);
		jQuery( '.rsl-ie-download-action' ).toggle( !! options.showDownload );
		jQuery( '.rsl-ie-migration-result' ).show();
	},

	completeExport( stats ) {
		this.completeMigrationResult( {
			title: 'Migration Package Ready!',
			subtitle:
				'Your full-site migration export has been packaged and is ready to download.',
			filesLabel: 'Export Files',
			filesValue: stats.filesCount || 0,
			itemsLabel: 'Exported Items',
			itemsValue: stats.exportedItems || 0,
			extraLabel: 'Package Size',
			extraValue: Utils.formatFileSize( stats.fileSize || 0 ),
			logMessage: 'Migration package is ready.',
			showDownload: true,
		} );
		jQuery( '.rsl-ie-migration-download-file' )
			.attr( 'href', stats.downloadUrl )
			.attr( 'download', stats.filename || 'site-migration.zip' );
	},

	resetCompletion() {
		jQuery( '.rsl-ie-migration-progress-panel' ).show();
		jQuery( '.rsl-ie-migration-result' ).hide();
		jQuery( '.rsl-ie-download-action' ).show();
	},

	formatDuration( ms ) {
		const seconds = Math.max( 0, Math.round( ms / 1000 ) );
		if ( seconds < 60 ) {
			return `${ seconds }s`;
		}
		const minutes = Math.floor( seconds / 60 );
		const rest = seconds % 60;
		return `${ minutes }m ${ rest }s`;
	},
};

export default SiteMigration;
