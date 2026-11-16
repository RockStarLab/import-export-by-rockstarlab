import TabProgress from '../utils/tab-progress';
/**
 * Content Migration wizard.
 */

import Utils from './utils';

import { __, _n, sprintf } from '@wordpress/i18n';
const SiteMigration = {
	step: 2,
	plan: [],
	parentJobId: null,
	selectedFile: null,
	migrationStartTime: null,
	starting: false,

	init() {
		if ( ! jQuery( '#rsl-ie-site-migration' ).length ) {
			return;
		}
		this.bindEvents();
		this.loadPlan();
	},

	// Keep the native streamed download; the response cookie signals its start.
	downloadPackage( e ) {
		const $button = jQuery( e.currentTarget );
		if ( $button.attr( 'aria-disabled' ) === 'true' ) {
			e.preventDefault();
			return;
		}
		const href = $button.attr( 'href' );
		if ( ! href || href === '#' ) {
			e.preventDefault();
			return;
		}
		const originalHtml = $button.html();
		const token =
			Date.now().toString( 36 ) + Math.random().toString( 36 ).slice( 2 );
		const cookieName = 'rsl_ie_migration_download_' + token;
		const url = new URL( href, window.location.href );
		url.searchParams.set( 'download_token', token );
		$button
			.attr( 'href', url.toString() )
			.attr( 'aria-disabled', 'true' )
			.attr( 'aria-busy', 'true' )
			.addClass( 'is-downloading' )
			.empty()
			.append(
				'<span class="dashicons dashicons-update rsl-ie-button-spinner" aria-hidden="true"></span>'
			)
			.append(
				document.createTextNode(
					$button.data( 'downloading-label' ) || 'Downloading...'
				)
			);

		const startedAt = Date.now();
		const interval = setInterval( () => {
			const started = document.cookie
				.split( ';' )
				.some( ( cookie ) => cookie.trim() === cookieName + '=1' );
			if ( ! started && Date.now() - startedAt < 120000 ) {
				return;
			}
			clearInterval( interval );
			document.cookie = cookieName + '=; Max-Age=0; Path=/; SameSite=Lax';
			$button
				.attr( 'href', href )
				.removeAttr( 'aria-disabled aria-busy' )
				.removeClass( 'is-downloading' )
				.html( originalHtml );
			if ( ! started ) {
				Utils.handleError(
					new Error(
						__(
							'The download did not start. Please try again.',
							'import-export-by-rockstarlab'
						)
					),
					__(
						'Download migration package',
						'import-export-by-rockstarlab'
					)
				);
			}
		}, 250 );
	},

	bindEvents() {
		const $page = jQuery( '#rsl-ie-site-migration' );
		$page.on( 'click', '.rsl-ie-migration-download-file', ( e ) =>
			this.downloadPackage( e )
		);
		$page.on( 'click', '.rsl-ie-migration-start', () => this.start() );
		$page.on( 'change', 'input[name="rsl_ie_content_mode"]', () =>
			this.updateContentSelection()
		);
		$page.on( 'change', '.rsl-ie-content-groups input', () => {
			jQuery( 'input[name="rsl_ie_content_mode"][value="custom"]' ).prop(
				'checked',
				true
			);
			this.updateContentSelection();
		} );
		$page.on( 'click', '[data-content-group-select]', ( e ) => {
			const $button = jQuery( e.currentTarget );
			const selected =
				$button.attr( 'data-content-group-select' ) === 'all';
			jQuery( 'input[name="rsl_ie_content_mode"][value="custom"]' ).prop(
				'checked',
				true
			);
			$button
				.closest( '.rsl-ie-content-group' )
				.find( 'input[type="checkbox"]:not(:disabled)' )
				.prop( 'checked', selected );
			this.updateContentSelection();
		} );
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
		$page.on( 'click', '.rsl-ie-migration-intro-hide', ( e ) => {
			e.preventDefault();
			jQuery( e.currentTarget )
				.closest( '.rsl-ie-migration-intro-notice' )
				.hide();
		} );
		$page.on( 'click', '.rsl-ie-migration-intro-dismiss-forever', ( e ) =>
			this.dismissIntroNotice( e )
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

	async dismissIntroNotice( event ) {
		event.preventDefault();
		const $button = jQuery( event.currentTarget );
		const $notice = $button.closest( '.rsl-ie-migration-intro-notice' );

		$button.prop( 'disabled', true );
		try {
			await Utils.ajax( 'migration_dismiss_intro_notice' );
			$notice.hide();
		} catch ( error ) {
			$button.prop( 'disabled', false );
			Utils.handleError(
				error,
				__( 'Content migration notice', 'import-export-by-rockstarlab' )
			);
		}
	},

	async loadPlan() {
		try {
			const response = await Utils.ajax( 'migration_get_plan' );
			this.plan = response.steps || [];
			this.contentContext = null;
			this.updateMode();
		} catch ( error ) {
			Utils.handleError(
				error,
				__( 'Load migration plan', 'import-export-by-rockstarlab' )
			);
		}
	},

	renderContentChoices() {
		const importing = this.getFileOperation() === 'upload';
		const context = importing ? 'upload' : 'file';
		if ( this.contentContext === context ) {
			return;
		}
		this.contentContext = context;
		const leaves = importing
			? ( this.preparedPackage?.steps || [] ).map( ( step ) => ( {
					...step,
					label:
						step.type === 'menu'
							? __( 'Menus', 'import-export-by-rockstarlab' )
							: step.key.replace( /[_:]/g, ' ' ),
			  } ) )
			: this.expandSteps( this.plan );
		// Content families had raw names ("media", "post", "Woo Product") next to
		// "Product Categories (product_cat)"; show the names used in the WordPress admin.
		const i18n =
			( 'undefined' !== typeof window && window.rslIeData?.i18n ) || {};
		const familyLabels = {
			media:
				i18n.migrationMedia ||
				__( 'Media', 'import-export-by-rockstarlab' ),
			post:
				i18n.migrationPosts ||
				__( 'Posts', 'import-export-by-rockstarlab' ),
			page:
				i18n.migrationPages ||
				__( 'Pages', 'import-export-by-rockstarlab' ),
			comment:
				i18n.migrationComments ||
				__( 'Comments', 'import-export-by-rockstarlab' ),
			user:
				i18n.migrationUsers ||
				__( 'Users', 'import-export-by-rockstarlab' ),
			menu:
				i18n.migrationMenus ||
				__( 'Menus', 'import-export-by-rockstarlab' ),
			woo_attribute:
				i18n.migrationAttributes ||
				__( 'Product attributes', 'import-export-by-rockstarlab' ),
			woo_product:
				i18n.migrationProducts ||
				__( 'Products', 'import-export-by-rockstarlab' ),
			woo_coupon:
				i18n.migrationCoupons ||
				__( 'Coupons', 'import-export-by-rockstarlab' ),
			woo_customer:
				i18n.migrationCustomers ||
				__( 'Customers', 'import-export-by-rockstarlab' ),
			woo_order:
				i18n.migrationOrders ||
				__( 'Orders', 'import-export-by-rockstarlab' ),
			woo_refund:
				i18n.migrationRefunds ||
				__( 'Refunds', 'import-export-by-rockstarlab' ),
			woo_review:
				i18n.migrationReviews ||
				__( 'Product reviews', 'import-export-by-rockstarlab' ),
		};
		this.contentChoices = leaves.map( ( leaf ) => ( {
			...leaf,
			label:
				familyLabels[ leaf.key ] ||
				String( leaf.label || leaf.key || '' ).replace(
					/(^|\s)\S/g,
					( letter ) => letter.toUpperCase()
				),
			requires: [],
		} ) );
		const groups = [
			[
				'content',
				__( 'Posts & media', 'import-export-by-rockstarlab' ),
				'admin-post',
			],
			[ 'menus', __( 'Menus', 'import-export-by-rockstarlab' ), 'menu' ],
			[
				'custom',
				__( 'Custom post types', 'import-export-by-rockstarlab' ),
				'admin-page',
			],
			[
				'taxonomy',
				__( 'Taxonomies', 'import-export-by-rockstarlab' ),
				'category',
			],
			[
				'people',
				__( 'People & discussion', 'import-export-by-rockstarlab' ),
				'groups',
			],
			[ 'store', 'WooCommerce', 'cart' ],
		];
		const $groups = jQuery( '.rsl-ie-content-groups' ).empty();
		groups.forEach( ( [ group, title, icon ] ) => {
			const choices = this.contentChoices.filter(
				( choice ) => this.contentGroup( choice ) === group
			);
			if ( ! choices.length ) {
				return;
			}
			const $group = jQuery( '<details>', {
				class: 'rsl-ie-content-group',
			} );
			jQuery( '<summary>' )
				.append(
					jQuery( '<span>', {
						class: `dashicons dashicons-${ icon }`,
						'aria-hidden': 'true',
					} ),
					document.createTextNode( title )
				)
				.appendTo( $group );
			const $body = jQuery( '<div>', {
				class: 'rsl-ie-content-group-body',
			} ).appendTo( $group );
			const $actions = jQuery( '<div>', {
				class: 'rsl-ie-content-group-actions',
			} ).appendTo( $body );
			[
				[ 'all', __( 'Select all', 'import-export-by-rockstarlab' ) ],
				[
					'none',
					__( 'Deselect all', 'import-export-by-rockstarlab' ),
				],
			].forEach( ( [ action, label ] ) => {
				jQuery( '<button>', {
					type: 'button',
					class: 'button button-small',
					'data-content-group-select': action,
				} )
					.text( label )
					.appendTo( $actions );
			} );
			choices.forEach( ( choice ) => {
				const $label = jQuery( '<label>', {
					class: 'rsl-ie-content-item',
				} );
				$label.append(
					jQuery( '<input>', {
						type: 'checkbox',
						value: choice.key,
						checked: ! choice.unavailable,
						disabled: !! choice.unavailable,
					} )
				);
				const $copy = jQuery( '<span>', {
					class: 'rsl-ie-content-item-copy',
				} ).append( jQuery( '<span>' ).text( choice.label ) );
				$copy.append(
					jQuery( '<small>', { class: 'rsl-ie-content-reason' } )
				);
				$label.append( $copy ).appendTo( $body );
			} );
			$groups.append( $group );
		} );
		this.updateContentSelection();
	},

	contentGroup( choice ) {
		const type = choice.type || choice.key;
		if ( type === 'menu' ) {
			return 'menus';
		}
		if ( type.startsWith( 'custom_post_types' ) ) {
			return 'custom';
		}
		if ( type.startsWith( 'woo_' ) ) {
			return 'store';
		}
		if ( type.startsWith( 'taxonomy' ) ) {
			const taxonomy =
				choice.taxonomy || choice.key.split( ':' )[ 1 ] || '';
			return taxonomy.startsWith( 'product_' ) ||
				taxonomy.startsWith( 'pa_' )
				? 'store'
				: 'taxonomy';
		}
		if (
			[ 'user', 'comment', 'free_comments', 'pro_comments' ].includes(
				type
			) ||
			choice.key.startsWith( 'comments:' )
		) {
			return 'people';
		}
		return 'content';
	},

	selectedContentKeys() {
		return jQuery( '.rsl-ie-content-groups input:checked' )
			.map( ( _, input ) => input.value )
			.get();
	},

	updateContentSelection() {
		const full =
			jQuery( 'input[name="rsl_ie_content_mode"]:checked' ).val() ===
			'full';
		jQuery( '.rsl-ie-content-groups' ).toggle( ! full );
		const choices = this.contentChoices || [];
		const selected = full
			? choices
					.filter( ( choice ) => ! choice.unavailable )
					.map( ( choice ) => choice.key )
			: this.selectedContentKeys();
		jQuery( '.rsl-ie-content-groups input' ).each( ( _, input ) => {
			const choice = choices.find( ( item ) => item.key === input.value );
			const missing = ( choice.requires || [] ).filter(
				( key ) => ! selected.includes( key )
			);
			const unavailable = choice.unavailable || missing.length > 0;
			if ( unavailable ) {
				input.checked = false;
			} else if ( full ) {
				input.checked = true;
			}
			input.disabled = !! unavailable;
			const reason = choice.unavailable
				? __(
						'Available through export file',
						'import-export-by-rockstarlab'
				  )
				: missing.length
				? sprintf(
						__( 'Select %s first', 'import-export-by-rockstarlab' ),
						missing
							.map(
								( key ) =>
									choices.find( ( item ) => item.key === key )
										?.label || key
							)
							.join( ', ' )
				  )
				: '';
			jQuery( input )
				.closest( 'label' )
				.toggleClass( 'is-disabled', !! unavailable )
				.find( '.rsl-ie-content-reason' )
				.text( reason )
				.toggle( !! reason );
		} );
		jQuery( '.rsl-ie-content-group' ).each( ( _, group ) => {
			const $group = jQuery( group );
			$group.toggleClass(
				'is-empty',
				$group.find( 'input[type="checkbox"]:checked:not(:disabled)' )
					.length === 0
			);
		} );
		const count = this.selectedContentKeys().length;
		jQuery( '.rsl-ie-content-count' ).text(
			sprintf(
				/* translators: %s: number of selected content types. */
				_n(
					'%s selected',
					'%s selected',
					count,
					'import-export-by-rockstarlab'
				),
				count
			)
		);
	},

	updateMode() {
		const operation = this.getFileOperation();
		this.renderContentChoices();
		jQuery( '.rsl-ie-content-selection' ).toggle(
			operation === 'create' || !! this.preparedPackage
		);
		jQuery( '.rsl-ie-migration-upload' ).toggle(
			operation === 'upload' && ! this.selectedFile
		);
		jQuery( '.rsl-ie-migration-file-info' ).toggle(
			operation === 'upload' && !! this.selectedFile
		);
		jQuery( '.rsl-ie-migration-replace-links' ).toggle(
			operation === 'upload'
		);
	},

	showStep( step ) {
		if ( step === 3 ) {
			window.scrollTo( { top: 0, behavior: 'instant' } );
		}
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

	getFileOperation() {
		return jQuery( 'input[name="rsl_ie_file_operation"]:checked' ).val();
	},

	getSelectedSteps() {
		const keys = this.selectedContentKeys();
		return this.expandSteps( this.plan ).filter( ( step ) =>
			keys.includes( step.key )
		);
	},

	expandSteps( steps ) {
		return steps.flatMap( ( step ) => {
			if ( Array.isArray( step.children ) && step.children.length ) {
				return step.children;
			}
			return [ step ];
		} );
	},

	async setSelectedFile( file ) {
		if ( file && ! /\.zip$/i.test( file.name ) ) {
			Utils.showNotice(
				__(
					'Please choose a ZIP migration package.',
					'import-export-by-rockstarlab'
				),
				'error'
			);
			return;
		}

		this.selectedFile = file;
		this.preparingPackage = false;
		jQuery( '.rsl-ie-migration-start' ).prop( 'disabled', false );
		this.preparedPackage = null;
		this.contentContext = null;
		this.updateMode();
		jQuery( '#rsl-ie-migration-file' ).val( '' );
		jQuery( '.rsl-ie-migration-upload' ).toggle( ! file );
		jQuery( '.rsl-ie-migration-file-info' ).toggle( !! file );
		jQuery( '.rsl-ie-migration-file-name' ).text( file ? file.name : '' );
		jQuery( '.rsl-ie-migration-file-size' ).text(
			file ? Utils.formatFileSize( file.size ) : ''
		);
		if ( ! file ) {
			return;
		}
		jQuery( '.rsl-ie-package-activity' ).remove();
		jQuery( '.rsl-ie-migration-file-info' ).append(
			'<div class="rsl-ie-package-activity" role="status"><span class="spinner is-active"></span><span class="rsl-ie-package-activity-text">' +
				__(
					'Uploading and checking package…',
					'import-export-by-rockstarlab'
				) +
				'</span></div>'
		);
		this.preparingPackage = true;
		jQuery( '.rsl-ie-migration-start' ).prop( 'disabled', true );
		try {
			const formData = new FormData();
			formData.append( 'action', 'rsl_ie_migration_upload_package' );
			formData.append( 'file', file );
			const upload = await jQuery.ajax( {
				url: window.rslIeData.ajaxUrl,
				type: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				dataType: 'json',
			} );
			if ( this.selectedFile !== file ) {
				return;
			}
			if ( ! upload.success ) {
				throw new Error(
					this.getErrorMessage( upload ) ||
						__(
							'Package upload failed',
							'import-export-by-rockstarlab'
						)
				);
			}
			jQuery( '.rsl-ie-package-activity-text' ).text(
				__(
					'Reading package contents…',
					'import-export-by-rockstarlab'
				)
			);
			let prepared = upload.data;
			const started = Date.now();
			while ( prepared.pending ) {
				prepared = await Utils.ajax( 'migration_prepare_package', {
					file_id: prepared.package_file_id,
				} );
				if ( this.selectedFile !== file ) {
					return;
				}
				// Large packages take a while: show that work is moving.
				const read = parseInt( prepared.total_steps, 10 ) || 0;
				const secs = Math.round( ( Date.now() - started ) / 1000 );
				jQuery( '.rsl-ie-package-activity-text' ).text(
					read > 0
						? sprintf(
								_n(
									'Reading package contents… %1$s part read (%2$s s)',
									'Reading package contents… %1$s parts read (%2$s s)',
									read,
									'import-export-by-rockstarlab'
								),
								read,
								secs
						  )
						: sprintf(
								__(
									'Reading package contents… (%s s)',
									'import-export-by-rockstarlab'
								),
								secs
						  )
				);
			}
			const steps = [ ...prepared.steps ];
			while ( ! prepared.complete ) {
				prepared = await Utils.ajax( 'migration_prepare_package', {
					file_id: prepared.package_file_id,
					offset: prepared.next_offset,
				} );
				if ( this.selectedFile !== file ) {
					return;
				}
				steps.push( ...prepared.steps );
			}
			jQuery( '.rsl-ie-package-activity' ).remove();
			this.preparedPackage = { ...prepared, steps };
			if ( prepared.woocommerce_excluded ) {
				jQuery( '.rsl-ie-migration-file-info' ).append(
					jQuery( '<div>', {
						class: 'rsl-ie-package-activity',
						role: 'status',
					} ).text(
						__(
							'WooCommerce is inactive. WooCommerce data is excluded from this import, including when All content is selected. Other content can be imported.',
							'import-export-by-rockstarlab'
						)
					)
				);
			}
			if ( prepared.pro_excluded ) {
				jQuery( '.rsl-ie-migration-file-info' ).append(
					jQuery( '<div>', {
						class: 'rsl-ie-package-activity',
						role: 'status',
					} ).text(
						__(
							'Some content in this package (for example menus, users, categories or custom post types) needs the PRO addon with an active license. It is skipped; posts, pages, media and comments can be imported.',
							'import-export-by-rockstarlab'
						)
					)
				);
			}
			this.contentContext = null;
			this.updateMode();
		} catch ( error ) {
			if ( this.selectedFile === file ) {
				jQuery( '.rsl-ie-package-activity .spinner' ).remove();
				jQuery( '.rsl-ie-package-activity-text' ).text(
					this.getErrorMessage( error )
				);
				Utils.handleError(
					error,
					__(
						'Read migration package',
						'import-export-by-rockstarlab'
					)
				);
			}
		} finally {
			if ( this.selectedFile === file ) {
				this.preparingPackage = false;
				// A package that could not be read left "Start migration" enabled.
				jQuery( '.rsl-ie-migration-start' ).prop(
					'disabled',
					! this.preparedPackage
				);
			}
		}
	},

	async start() {
		if ( this.starting || this.preparingPackage ) {
			return;
		}
		this.starting = true;
		jQuery( '.rsl-ie-migration-start' ).prop( 'disabled', true );
		this.serverMigration = false;
		this.parentJobId = null;
		try {
			this.showStep( 3 );
			this.migrationStartTime = Date.now();
			this.resetCompletion();
			this.log(
				__( 'Starting migration...', 'import-export-by-rockstarlab' )
			);
			const operation =
				this.getFileOperation() === 'upload' ? 'import' : 'export';
			const steps = this.getSelectedSteps();

			this.serverMigration = true;
			if ( operation === 'import' ) {
				await this.runPackageImport();
				return;
			}
			if ( ! steps.length ) {
				throw new Error(
					__(
						'Select at least one content type to migrate.',
						'import-export-by-rockstarlab'
					)
				);
			}
			const parent = await Utils.ajax( 'migration_start', {
				request_id: `migration-${ Date.now() }-${ Math.random()
					.toString( 36 )
					.slice( 2 ) }`,
				mode: 'export_file',
				operation,
				steps: steps.map( ( step ) => step.key || step.type ),
			} );
			this.parentJobId = parent.job_id;

			await this.runPackageExport();
		} catch ( error ) {
			const message = this.getErrorMessage( error );
			this.log(
				`${
					this.serverMigration && ! error.migrationStatus
						? __(
								'Migration request interrupted',
								'import-export-by-rockstarlab'
						  )
						: __(
								'Migration failed',
								'import-export-by-rockstarlab'
						  )
				}: ${ message }`,
				'error'
			);
			if ( this.serverMigration && ! error.migrationStatus ) {
				this.log(
					__(
						'The server job may still be running. Refresh its status before starting another migration.',
						'import-export-by-rockstarlab'
					)
				);
				if ( ! this.parentJobId ) {
					this.showStep( 2 );
					await this.loadPlan();
				}
			}
			this.setProgressState(
				error.migrationStatus || 'interrupted',
				message
			);
			Utils.handleError(
				error,
				__( 'Content migration', 'import-export-by-rockstarlab' )
			);
		} finally {
			this.starting = false;
			jQuery( '.rsl-ie-migration-start' ).prop( 'disabled', false );
		}
	},

	getErrorMessage( error ) {
		if ( ! error ) {
			return __( 'Unknown error', 'import-export-by-rockstarlab' );
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
		if ( error.message ) {
			return this.getErrorMessage( error.message );
		}
		if ( error.data ) {
			return this.getErrorMessage( error.data );
		}
		if ( error.error ) {
			return this.getErrorMessage( error.error );
		}
		try {
			return JSON.stringify( error );
		} catch ( e ) {
			return __( 'Unknown error', 'import-export-by-rockstarlab' );
		}
	},

	async runPackageExport() {
		this.log( __( 'Preparing export…', 'import-export-by-rockstarlab' ) );
		let progress;
		do {
			progress = await Utils.ajax( 'migration_advance', {
				job_id: this.parentJobId,
			} );
			this.renderPackageProgress( progress );
			if ( progress.status !== 'completed' ) {
				await new Promise( ( resolve ) => setTimeout( resolve, 250 ) );
			}
		} while ( progress.status !== 'completed' );
		const download = await Utils.ajax( 'migration_get_download_url', {
			job_id: this.parentJobId,
		} );
		this.completeExport( {
			downloadUrl: download.download_url,
			filename: download.filename,
			fileSize: download.file_size || 0,
			filesCount: download.files_count,
			exportedItems: download.exported_items,
		} );
	},

	async runPackageImport() {
		if ( ! this.selectedFile ) {
			throw new Error(
				__(
					'Please choose a migration ZIP file.',
					'import-export-by-rockstarlab'
				)
			);
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
		};
		if ( ! this.preparedPackage ) {
			throw new Error(
				__(
					'Upload and validate the migration ZIP first.',
					'import-export-by-rockstarlab'
				)
			);
		}
		const keys = this.selectedContentKeys();
		if ( ! keys.length ) {
			throw new Error(
				__(
					'Select at least one content type to migrate.',
					'import-export-by-rockstarlab'
				)
			);
		}

		const parent = await Utils.ajax( 'migration_start', {
			mode: 'export_file',
			operation: 'import',
			file_id: this.preparedPackage.package_file_id,
			steps: keys,
			sha256: this.preparedPackage.sha256,
			request_id: `migration-import-${ Date.now() }-${ Math.random()
				.toString( 36 )
				.slice( 2 ) }`,
			replace_links_rules: replaceLinks ? replaceLinksRules : [],
			duplicate_handling:
				jQuery(
					'input[name="rsl_ie_migration_duplicate_handling"]:checked'
				).val() || 'skip',
		} );
		this.parentJobId = parent.job_id;
		this.log( __( 'Preparing import…', 'import-export-by-rockstarlab' ) );
		let progress;
		do {
			progress = await Utils.ajax( 'migration_advance', {
				job_id: this.parentJobId,
			} );
			this.renderPackageProgress( progress );
			if ( progress.status !== 'completed' ) {
				await new Promise( ( resolve ) => setTimeout( resolve, 250 ) );
			}
		} while ( progress.status !== 'completed' );
		const result = progress.result;
		this.completeImport( {
			...stats,
			...result,
			filesCount: result.files_count,
			emptyFiles: result.empty_files,
			replacedUrls: result.replaced_urls,
		} );
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

	renderSyncProgress( state ) {
		const child = state.active_file;
		const fraction =
			child?.total > 0 ? Math.min( 1, child.processed / child.total ) : 0;
		const ratio = Math.min(
			1,
			( state.processed + fraction ) / Math.max( 1, state.total )
		);
		let percent;
		if ( state.status === 'completed' ) percent = 100;
		else if ( state.stage === 'collecting_sources' ) percent = 15 * ratio;
		else if ( state.stage.startsWith( 'preparing_' ) )
			percent = 15 + 5 * ratio;
		else if ( state.stage.startsWith( 'finalizing_' ) )
			percent = 95 + 4 * ratio;
		else percent = 20 + 75 * ratio;
		this.setProgress( Math.floor( percent ) );
		this.setProgressState(
			[ 'pending', 'processing' ].includes( state.status )
				? 'processing'
				: state.status
		);
	},

	setProgressState( state, message = '' ) {
		const active = state === 'processing';
		jQuery( '.rsl-ie-migration-progress' )
			.toggleClass( 'is-active', active )
			.toggleClass( 'is-stopped', ! active && state !== 'completed' )
			.attr( 'aria-busy', active ? 'true' : 'false' );
		if ( message ) jQuery( '.rsl-ie-migration-log' ).text( message );
		if ( ! active && state !== 'completed' ) {
			jQuery( '.rsl-ie-migration-progress-text' ).text(
				state === 'failed'
					? __( 'Failed', 'import-export-by-rockstarlab' )
					: state === 'paused'
					? __( 'Paused', 'import-export-by-rockstarlab' )
					: state === 'cancelled'
					? __( 'Cancelled', 'import-export-by-rockstarlab' )
					: __(
							'Connection interrupted',
							'import-export-by-rockstarlab'
					  )
			);
		}
	},

	renderPackageProgress( progress ) {
		const child = progress.active_file;
		const fraction =
			child?.total > 0 ? Math.min( 1, child.processed / child.total ) : 0;
		this.setProgress(
			progress.status === 'completed'
				? 100
				: Math.min(
						95,
						Math.floor(
							( 95 * ( progress.processed + fraction ) ) /
								Math.max( 1, progress.total )
						)
				  )
		);
		if ( [ 'failed', 'cancelled', 'paused' ].includes( progress.status ) ) {
			const reason =
				progress.error_code === 'rsl_ie_content_type_forbidden'
					? __(
							'The imported content or a matching destination item is outside the allowed content type.',
							'import-export-by-rockstarlab'
					  )
					: progress.error_code ||
					  sprintf(
							/* translators: %s: job status (failed, cancelled or paused). */
							__(
								'Migration %s.',
								'import-export-by-rockstarlab'
							),
							progress.status
					  );
			const error = new Error( reason );
			error.migrationStatus = progress.status;
			throw error;
		}
		this.setProgressState(
			progress.status === 'completed' ? 'completed' : 'processing'
		);
		this.log(
			child
				? `${ child.type }${
						child.total > 0
							? sprintf(
									/* translators: 1: processed items, 2: total items. */
									__(
										': %1$s / %2$s items',
										'import-export-by-rockstarlab'
									),
									child.processed,
									child.total
							  )
							: ''
				  } · ${ sprintf(
						/* translators: 1: processed files, 2: total files. */
						__(
							'%1$s / %2$s files',
							'import-export-by-rockstarlab'
						),
						progress.processed,
						progress.total
				  ) }`
				: `${ sprintf(
						/* translators: 1: processed files, 2: total files. */
						__(
							'%1$s / %2$s files',
							'import-export-by-rockstarlab'
						),
						progress.processed,
						progress.total
				  ) } · ${
						progress.stage === 'packaging'
							? __(
									'Building package…',
									'import-export-by-rockstarlab'
							  )
							: __( 'Preparing…', 'import-export-by-rockstarlab' )
				  }`
		);
	},

	setProgress( percent ) {
		TabProgress.update( 'migration', percent );
		jQuery( '.rsl-ie-migration-progress-text' ).text( `${ percent }%` );
		jQuery( '.rsl-ie-migration-progress .rsl-ie-progress-bar-fill' ).css(
			'width',
			`${ percent }%`
		);
	},

	log( message, type = 'info' ) {
		if ( type === 'error' ) TabProgress.finish( 'migration', 'failed' );
		jQuery( '.rsl-ie-migration-log' )
			.removeClass( 'is-info is-error' )
			.addClass( `is-${ type }` )
			.text( message );
	},

	completeImport( stats ) {
		this.completeMigrationResult( {
			title: __(
				'Migration Import Completed Successfully!',
				'import-export-by-rockstarlab'
			),
			subtitle: this.importBreakdown( stats ),
			filesLabel: __( 'Import Files', 'import-export-by-rockstarlab' ),
			filesValue: stats.filesCount || 0,
			itemsLabel: __( 'Imported Items', 'import-export-by-rockstarlab' ),
			itemsValue: stats.success || 0,
			extraLabel: __( 'URLs Replaced', 'import-export-by-rockstarlab' ),
			extraValue: stats.replaceLinks ? stats.replacedUrls || 0 : 0,
			logMessage: __(
				'Migration import completed.',
				'import-export-by-rockstarlab'
			),
			showDownload: false,
		} );
	},

	/**
	 * Summary line for a finished import: created, updated and skipped items.
	 *
	 * @param {Object} stats Import result.
	 * @return {string} Subtitle text.
	 */
	importBreakdown( stats ) {
		const created = parseInt( stats.created, 10 ) || 0;
		const updated = parseInt( stats.updated, 10 ) || 0;
		const skipped = parseInt( stats.skipped, 10 ) || 0;
		let text = sprintf(
			__( '%1$s created, %2$s updated', 'import-export-by-rockstarlab' ),
			created,
			updated
		);
		if ( skipped > 0 ) {
			text += sprintf(
				__(
					', %s skipped because they already exist on this site',
					'import-export-by-rockstarlab'
				),
				skipped
			);
		}
		const failed = parseInt( stats.failed, 10 ) || 0;
		if ( failed > 0 ) {
			text += sprintf(
				_n(
					'. %s media file could not be imported (missing on the source site, or a file type this site does not accept, such as SVG); see Jobs Log',
					'. %s media files could not be imported (missing on the source site, or a file type this site does not accept, such as SVG); see Jobs Log',
					failed,
					'import-export-by-rockstarlab'
				),
				failed
			);
		}
		return text + '.';
	},

	completeMigrationResult( options ) {
		this.setProgressState( 'completed' );
		this.setProgress( 100 );
		TabProgress.finish( 'migration' );
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
			title: __(
				'Migration Package Ready!',
				'import-export-by-rockstarlab'
			),
			subtitle: __(
				'Your content migration export has been packaged and is ready to download.',
				'import-export-by-rockstarlab'
			),
			filesLabel: __( 'Export Files', 'import-export-by-rockstarlab' ),
			filesValue: stats.filesCount || 0,
			itemsLabel: __( 'Exported Items', 'import-export-by-rockstarlab' ),
			itemsValue: stats.exportedItems || 0,
			extraLabel: __( 'Package Size', 'import-export-by-rockstarlab' ),
			extraValue: Utils.formatFileSize( stats.fileSize || 0 ),
			logMessage: __(
				'Migration package is ready.',
				'import-export-by-rockstarlab'
			),
			showDownload: true,
		} );
		jQuery( '.rsl-ie-migration-download-file' )
			.attr( 'href', stats.downloadUrl )
			.attr( 'download', stats.filename || 'site-migration.zip' );
	},

	resetCompletion() {
		this.setProgress( 0 );
		this.setProgressState( 'processing' );
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
