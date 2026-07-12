import ImportModule from './modules/import';
import ExportModule from './modules/export';
import MediaSyncModule from './modules/media_sync';
import JobsLogModule from './modules/jobs-log';
import ContentSyncModule from './modules/content-sync';
import PostSyncModule from './modules/post-sync';
import AIURLImporter from './modules/ai-url-importer';
import PluginOptionsModule from './modules/plugin-options';
import WelcomeModule from './modules/welcome';
import ProPromoModule from './modules/pro-promo';
import MediaHashModule from './modules/media-hash';
import SchedulesModule from './modules/schedules';

// Initialize modules when DOM is ready
jQuery( document ).ready( function ( $ ) {
	// Initialize import module
	ImportModule.init();

	// Initialize export module
	ExportModule.init();

	// Make export module globally accessible for step 3
	window.rslIeExportModule = ExportModule;

	// Initialize media sync module
	MediaSyncModule.init();

	// Initialize jobs log module
	JobsLogModule.init();

	// Initialize content sync module
	ContentSyncModule.init();

	// Initialize post sync module
	PostSyncModule.init();

	window.rslIePostSyncModule = PostSyncModule;

	// Initialize AI URL Importer module
	AIURLImporter.init();

	// Initialize plugin options module
	PluginOptionsModule.init();

	// Initialize welcome module
	WelcomeModule.init();

	// Initialize PRO promo helpers (promo-code copy button)
	ProPromoModule.init();

	// Initialize the media hash maintenance tool and recommendation notice.
	MediaHashModule.init();

	// Initialize schedules page controls.
	SchedulesModule.init();
} );
