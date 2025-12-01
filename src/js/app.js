import FunctionsModule from './modules/functions';
import ImportModule from './modules/import';
import ExportModule from './modules/export';
import MediaSyncModule from './modules/media_sync';

// Initialize modules when DOM is ready
jQuery( document ).ready( function ( $ ) {
	// Initialize import module
	ImportModule.init();

	// Initialize export module
	ExportModule.init();

	// Initialize functions module
	FunctionsModule.init();

	// Initialize media sync module
	MediaSyncModule.init();
} );
