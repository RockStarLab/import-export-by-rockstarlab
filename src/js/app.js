import FunctionsModule from './modules/functions';
import ImportModule from './modules/import';
import ExportModule from './modules/export';
import MediaSyncModule from './modules/media_sync';
import JobsLogModule from './modules/jobs-log';

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
} );
