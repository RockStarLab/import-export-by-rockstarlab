import FunctionsModule from './modules/functions';
import ImportModule from './modules/import';
import ExportModule from './modules/export';

// Initialize modules when DOM is ready
jQuery( document ).ready( function ( $ ) {
	// Initialize import module
	ImportModule.init();

	// Initialize export module
	ExportModule.init();

	// Initialize functions module
	FunctionsModule.init();
} );
