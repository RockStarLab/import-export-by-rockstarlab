import functions from './modules/functions';
import ImportModule from './modules/import';
import ExportModule from './modules/export';

// Initialize modules when DOM is ready
jQuery( document ).ready( function ( $ ) {
	// Initialize import module
	ImportModule.init();

	// Initialize export module
	ExportModule.init();

	// Initialize functions module (if exists)
	if ( typeof functions.init === 'function' ) {
		functions.init();
	}
} );
