import FunctionsModule from './modules/functions';
import ImportModule from './modules/import';
import ExportModule from './modules/export';
import MediaSyncModule from './modules/media_sync';
import JobsLogModule from './modules/jobs-log';
import ContentUpdaterModule from './modules/content-updater';
import ContentSyncModule from './modules/content-sync';
import PostSyncModule from './modules/post-sync';
import AIURLImporter from './modules/ai-url-importer';

// Initialize modules when DOM is ready
jQuery( document ).ready( function ( $ ) {
	// Initialize import module
	ImportModule.init();

	// Initialize export module
	ExportModule.init();
	
	// Make export module globally accessible for step 3
	window.aieExportModule = ExportModule;

	// Initialize functions module
	FunctionsModule.init();

	// Initialize media sync module
	MediaSyncModule.init();

	// Initialize jobs log module
	JobsLogModule.init();

	// Initialize content updater module
	ContentUpdaterModule.init();

	// Initialize content sync module
	ContentSyncModule.init();

	// Initialize post sync module
	PostSyncModule.init();
	
	window.aiePostSyncModule = PostSyncModule;

	// Initialize AI URL Importer module
	AIURLImporter.init();
} );
