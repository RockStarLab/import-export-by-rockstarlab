/**
 * Playwright screenshot sweep for Import Export by RockStarLab on aie2.local.
 *
 * Usage:
 *   node scripts/aie2-plugin-screenshots.js
 *
 * Env (optional, also read from .env.e2e):
 *   AIE_TARGET_URL=http://aie2.local
 *   AIE_TARGET_ADMIN_USER=admin
 *   AIE_TARGET_ADMIN_PASSWORD=admin
 *   AIE_HEADLESS=true
 *   AIE_SCREENSHOT_DIR=screenshots/aie2-plugin
 */

const fs = require( 'fs' );
const path = require( 'path' );
const { chromium } = require( 'playwright' );

function parseDotEnv( contents ) {
	const env = {};
	for ( const line of contents.split( /\r?\n/ ) ) {
		const trimmed = line.trim();
		if ( ! trimmed || trimmed.startsWith( '#' ) ) continue;
		const idx = trimmed.indexOf( '=' );
		if ( idx === -1 ) continue;
		env[ trimmed.slice( 0, idx ).trim() ] = trimmed.slice( idx + 1 ).trim();
	}
	return env;
}

function loadEnv() {
	const envPath = path.resolve( process.cwd(), '.env.e2e' );
	const fileEnv = fs.existsSync( envPath )
		? parseDotEnv( fs.readFileSync( envPath, 'utf8' ) )
		: {};
	const get = ( key, fallback ) =>
		process.env[ key ] ?? fileEnv[ key ] ?? fallback;
	const headlessRaw = String( get( 'AIE_HEADLESS', 'true' ) ).toLowerCase();

	return {
		baseUrl: get( 'AIE_TARGET_URL', 'http://aie2.local' ).replace(
			/\/$/,
			''
		),
		username: get( 'AIE_TARGET_ADMIN_USER', 'admin' ),
		password: get( 'AIE_TARGET_ADMIN_PASSWORD', 'admin' ),
		headless:
			headlessRaw === '1' ||
			headlessRaw === 'true' ||
			headlessRaw === 'yes',
		rootDir: path.resolve(
			process.cwd(),
			get( 'AIE_SCREENSHOT_DIR', 'screenshots/aie2-plugin' ),
			new Date().toISOString().replace( /[:.]/g, '-' )
		),
	};
}

function mkdirp( dir ) {
	fs.mkdirSync( dir, { recursive: true } );
}

function screenshotPath( env, group, name ) {
	const dir = path.join( env.rootDir, group );
	mkdirp( dir );
	return path.join( dir, `${ name }.png` );
}

async function settle( page ) {
	await page.waitForLoadState( 'domcontentloaded' ).catch( () => null );
	await page
		.waitForLoadState( 'networkidle', { timeout: 15_000 } )
		.catch( () => null );
	await page
		.waitForFunction(
			() => {
				const visible = ( el ) => {
					const style = window.getComputedStyle( el );
					const rect = el.getBoundingClientRect();
					return (
						style.display !== 'none' &&
						style.visibility !== 'hidden' &&
						style.opacity !== '0' &&
						rect.width > 0 &&
						rect.height > 0
					);
				};

				const busySelectors = [
					'.spinner.is-active',
					'.rsl-ie-loading',
					'.rsl-ie-functions-loading',
					'.rsl-ie-loading-row',
					'.rsl-ie-progress-container:not([style*="display: none"]) .spinner.is-active',
				];

				return ! busySelectors.some( ( selector ) =>
					Array.from( document.querySelectorAll( selector ) ).some(
						visible
					)
				);
			},
			{ timeout: 20_000 }
		)
		.catch( () => null );
	await page.waitForTimeout( 700 );
}

async function hideObstructions( page ) {
	await page
		.evaluate( () => {
			const selectors = [
				'#rsl-ie-review-notice',
				'.notice.is-dismissible',
				'.fs-notice',
				'.update-nag',
			];
			for ( const selector of selectors ) {
				document.querySelectorAll( selector ).forEach( ( el ) => {
					el.style.display = 'none';
					el.style.pointerEvents = 'none';
				} );
			}
		} )
		.catch( () => null );
}

async function shot( page, env, group, name ) {
	await settle( page );
	await hideObstructions( page );
	await page.screenshot( {
		path: screenshotPath( env, group, name ),
		fullPage: true,
	} );
	console.log( `saved ${ group }/${ name }.png` );
}

async function ensureLoggedIn( page, env ) {
	await page.goto( `${ env.baseUrl }/wp-admin/`, {
		waitUntil: 'domcontentloaded',
	} );
	if ( ! ( await page.locator( 'form#loginform' ).count() ) ) {
		await page.waitForSelector( '#wpadminbar', { timeout: 30_000 } );
		return;
	}

	await page.fill( '#user_login', env.username );
	await page.fill( '#user_pass', env.password );
	await Promise.all( [
		page.waitForNavigation( { waitUntil: 'domcontentloaded' } ),
		page.click( '#wp-submit' ),
	] );
	await page.waitForSelector( '#wpadminbar', { timeout: 30_000 } );
}

async function gotoAdminPage( page, env, slug ) {
	await page.goto( `${ env.baseUrl }/wp-admin/admin.php?page=${ slug }`, {
		waitUntil: 'domcontentloaded',
	} );
	if ( await page.locator( 'form#loginform' ).count() ) {
		await ensureLoggedIn( page, env );
		await page.goto( `${ env.baseUrl }/wp-admin/admin.php?page=${ slug }`, {
			waitUntil: 'domcontentloaded',
		} );
	}
	await settle( page );
}

async function selectContentType( page, wizardSelector, value ) {
	const input = page
		.locator(
			`${ wizardSelector } .rsl-ie-step-1.active input[name="content_type"][value="${ value }"]`
		)
		.first();
	await input.waitFor( { state: 'attached', timeout: 30_000 } );
	const label = page
		.locator(
			`${ wizardSelector } .rsl-ie-step-1.active label.rsl-ie-content-type`,
			{
				has: input,
			}
		)
		.first();
	if ( await label.count() ) {
		await label.click( { force: true } );
	} else {
		await input.check( { force: true } );
	}
	await settle( page );
}

async function clickActiveNext( page, wizardSelector ) {
	const next = page
		.locator( `${ wizardSelector } .rsl-ie-step.active .rsl-ie-next-step` )
		.first();
	await next.waitFor( { state: 'visible', timeout: 45_000 } );
	await page.waitForFunction(
		( selector ) => {
			const btn = document.querySelector( selector );
			return btn && ! btn.disabled;
		},
		`${ wizardSelector } .rsl-ie-step.active .rsl-ie-next-step`,
		{ timeout: 45_000 }
	);
	await next.click();
	await settle( page );
}

async function clickUpdaterNext( page ) {
	const next = page
		.locator(
			'#rsl-ie-content-updater .rsl-ie-step.active .rsl-ie-updater-next-step, #rsl-ie-content-updater .rsl-ie-step.active .rsl-ie-next-step'
		)
		.first();
	await next.waitFor( { state: 'visible', timeout: 45_000 } );
	await next.click();
	await settle( page );
}

async function waitActiveStep( page, step ) {
	await page.waitForSelector(
		`.rsl-ie-step-${ step }.active, .rsl-ie-updater-step-${ step }.active`,
		{
			timeout: 45_000,
		}
	);
	await settle( page );
}

async function exportWizardScreens( page, env ) {
	await gotoAdminPage( page, env, 'rsl-ie-export' );
	await waitActiveStep( page, 1 );
	await shot( page, env, 'wizard-export', '01-content-type' );

	await selectContentType( page, '#rsl-ie-export', 'post' );
	await shot( page, env, 'wizard-export', '01-content-type-post-selected' );

	await clickActiveNext( page, '#rsl-ie-export' );
	await waitActiveStep( page, 2 );
	await shot( page, env, 'wizard-export', '02-filter-data' );

	const addFilter = page
		.locator( '.rsl-ie-step-2.active .rsl-ie-add-filter' )
		.first();
	if ( await addFilter.count() ) {
		await addFilter.click();
		await settle( page );
		await shot( page, env, 'wizard-export', '02-filter-data-with-row' );
	}

	await clickActiveNext( page, '#rsl-ie-export' );
	await waitActiveStep( page, 3 );
	await shot( page, env, 'wizard-export', '03-select-fields' );

	const functionsButton = page
		.locator(
			'.rsl-ie-step-3.active .rsl-ie-field-functions, .rsl-ie-step-3.active .rsl-ie-assign-functions'
		)
		.first();
	if ( await functionsButton.count() ) {
		await functionsButton.click().catch( () => null );
		await settle( page );
		if (
			await page
				.locator( '#rsl-ie-field-functions-modal:visible' )
				.count()
		) {
			await shot(
				page,
				env,
				'wizard-export',
				'03-field-functions-modal'
			);
			await page.keyboard.press( 'Escape' ).catch( () => null );
			await page
				.locator( '#rsl-ie-field-functions-modal .rsl-ie-modal-close' )
				.first()
				.click()
				.catch( () => null );
		}
	}

	for ( const step of [ 4, 5 ] ) {
		await page.evaluate( ( stepNum ) => {
			document
				.querySelectorAll( '#rsl-ie-export .rsl-ie-step' )
				.forEach( ( el ) => {
					el.classList.remove( 'active' );
					el.style.display = 'none';
				} );
			const stepEl = document.querySelector(
				`#rsl-ie-export .rsl-ie-step-${ stepNum }`
			);
			if ( stepEl ) {
				stepEl.classList.add( 'active' );
				stepEl.style.display = 'block';
			}
			document
				.querySelectorAll( '#rsl-ie-export .rsl-ie-step-indicator' )
				.forEach( ( el ) => {
					el.classList.toggle(
						'active',
						el.dataset.step === String( stepNum )
					);
				} );
		}, step );
		await settle( page );
		await shot(
			page,
			env,
			'wizard-export',
			step === 4 ? '04-format-options' : '05-export-progress'
		);
	}
}

async function importWizardScreens( page, env ) {
	await gotoAdminPage( page, env, 'rsl-ie-import' );
	await waitActiveStep( page, 1 );
	await shot( page, env, 'wizard-import', '01-content-type' );

	await selectContentType( page, '#rsl-ie-import', 'post' );
	await shot( page, env, 'wizard-import', '01-content-type-post-selected' );

	// Later import steps need an uploaded file. Capture their static layouts without
	// starting an import, so this sweep does not mutate site content.
	for ( const step of [ 2, 3, 4, 5, 6 ] ) {
		await page.evaluate( ( stepNum ) => {
			document
				.querySelectorAll( '#rsl-ie-import .rsl-ie-step' )
				.forEach( ( el ) => {
					el.classList.remove( 'active' );
					el.style.display = 'none';
				} );
			const stepEl = document.querySelector(
				`#rsl-ie-import .rsl-ie-step-${ stepNum }`
			);
			if ( stepEl ) {
				stepEl.classList.add( 'active' );
				stepEl.style.display = 'block';
			}
			document
				.querySelectorAll( '#rsl-ie-import .rsl-ie-step-indicator' )
				.forEach( ( el ) => {
					el.classList.toggle(
						'active',
						el.dataset.step === String( stepNum )
					);
				} );
		}, step );
		await settle( page );
		const names = {
			2: '02-upload',
			3: '03-preview',
			4: '04-field-mapping',
			5: '05-options',
			6: '06-import-progress',
		};
		await shot( page, env, 'wizard-import', names[ step ] );
	}
}

async function updaterWizardScreens( page, env ) {
	await gotoAdminPage( page, env, 'rsl-ie-content-updater' );
	await waitActiveStep( page, 1 );
	await shot( page, env, 'wizard-content-updater', '01-content-type' );

	await selectContentType( page, '#rsl-ie-content-updater', 'post' ).catch(
		() => null
	);
	await shot(
		page,
		env,
		'wizard-content-updater',
		'01-content-type-post-selected'
	);

	for ( const step of [ 2, 3, 4, 5 ] ) {
		await page.evaluate( ( stepNum ) => {
			document
				.querySelectorAll( '#rsl-ie-content-updater .rsl-ie-step' )
				.forEach( ( el ) => {
					el.classList.remove( 'active' );
					el.style.display = 'none';
				} );
			const stepEl = document.querySelector(
				`#rsl-ie-content-updater .rsl-ie-updater-step-${ stepNum }, #rsl-ie-content-updater .rsl-ie-step-${ stepNum }`
			);
			if ( stepEl ) {
				stepEl.classList.add( 'active' );
				stepEl.style.display = 'block';
			}
			document
				.querySelectorAll(
					'#rsl-ie-content-updater .rsl-ie-step-indicator'
				)
				.forEach( ( el ) => {
					el.classList.toggle(
						'active',
						el.dataset.step === String( stepNum )
					);
				} );
		}, step );
		await settle( page );
		const names = {
			2: '02-filters',
			3: '03-select-fields',
			4: '04-assign-functions',
			5: '05-start-update',
		};
		await shot( page, env, 'wizard-content-updater', names[ step ] );
	}
}

async function aiUrlImporterScreens( page, env ) {
	await gotoAdminPage( page, env, 'rsl-ie-ai-url-importer' );
	await shot( page, env, 'wizard-ai-url-importer', '01-url-input' );

	for ( const step of [ 2, 3 ] ) {
		await page.evaluate( ( stepNum ) => {
			document
				.querySelectorAll( '#rsl-ie-ai-url-importer .rsl-ie-step' )
				.forEach( ( el ) => {
					el.classList.remove( 'rsl-ie-step-active' );
					el.style.display = 'none';
				} );
			const stepEl = document.querySelector(
				`#rsl-ie-ai-url-importer .rsl-ie-step-${ stepNum }`
			);
			if ( stepEl ) {
				stepEl.classList.add( 'rsl-ie-step-active' );
				stepEl.style.display = 'block';
			}
		}, step );
		await settle( page );
		await shot(
			page,
			env,
			'wizard-ai-url-importer',
			step === 2 ? '02-ai-analysis' : '03-import-settings'
		);
	}
}

async function staticPluginPages( page, env ) {
	const pages = [
		[ 'import-export-by-rockstarlab', 'welcome' ],
		[ 'rsl-ie-content-sync', 'content-sync' ],
		[ 'rsl-ie-media-sync', 'media-sync' ],
		[ 'rsl-ie-ai-url-importer', 'ai-url-importer' ],
		[ 'rsl-ie-jobs-log', 'jobs-log' ],
		[ 'rsl-ie-plugin-options', 'plugin-options' ],
		[ 'import-export-by-rockstarlab-addons', 'addons' ],
	];

	for ( const [ slug, name ] of pages ) {
		await gotoAdminPage( page, env, slug );
		await shot( page, env, 'pages', name );
	}

	if ( await page.locator( '.rsl-ie-new-function' ).count() ) {
		await page
			.locator( '.rsl-ie-new-function' )
			.first()
			.click()
			.catch( () => null );
		await settle( page );
		if (
			await page
				.locator( '#rsl-ie-function-editor-modal:visible' )
				.count()
		) {
			await shot( page, env, 'pages/functions', 'new-function-modal' );
			await page
				.locator( '#rsl-ie-function-editor-modal .rsl-ie-modal-close' )
				.first()
				.click()
				.catch( () => null );
		}
	}
}

( async () => {
	const env = loadEnv();
	mkdirp( env.rootDir );

	const browser = await chromium.launch( { headless: env.headless } );
	const context = await browser.newContext( {
		viewport: { width: 1440, height: 1100 },
		acceptDownloads: true,
	} );
	const page = await context.newPage();
	page.setDefaultTimeout( 45_000 );

	try {
		await ensureLoggedIn( page, env );
		await staticPluginPages( page, env );
		await exportWizardScreens( page, env );
		await importWizardScreens( page, env );
		await updaterWizardScreens( page, env );
		await aiUrlImporterScreens( page, env );
		console.log( `\nScreenshots saved to: ${ env.rootDir }` );
	} finally {
		await browser.close();
	}
} )().catch( ( error ) => {
	console.error( error );
	process.exitCode = 1;
} );
