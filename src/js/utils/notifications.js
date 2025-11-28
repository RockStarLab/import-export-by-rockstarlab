/**
 * Notification utilities
 */

/**
 * Show success notice
 */
export function showNotice( message ) {
	// Use WordPress admin notice
	const notice = document.createElement( 'div' );
	notice.className = 'notice notice-success is-dismissible';
	notice.innerHTML = `<p>${ escapeHtml( message ) }</p>`;

	const container = document.querySelector( '.wrap' ) || document.body;
	container.insertBefore( notice, container.firstChild );

	// Auto dismiss after 5 seconds
	setTimeout( () => {
		notice.remove();
	}, 5000 );

	// Make it dismissible
	const dismissButton = document.createElement( 'button' );
	dismissButton.type = 'button';
	dismissButton.className = 'notice-dismiss';
	dismissButton.innerHTML =
		'<span class="screen-reader-text">Dismiss this notice.</span>';
	dismissButton.addEventListener( 'click', () => {
		notice.remove();
	} );
	notice.appendChild( dismissButton );
}

/**
 * Show error message
 */
export function showError( message ) {
	const notice = document.createElement( 'div' );
	notice.className = 'notice notice-error is-dismissible';
	notice.innerHTML = `<p>${ escapeHtml( message ) }</p>`;

	const container = document.querySelector( '.wrap' ) || document.body;
	container.insertBefore( notice, container.firstChild );

	// Auto dismiss after 10 seconds
	setTimeout( () => {
		notice.remove();
	}, 10000 );

	// Make it dismissible
	const dismissButton = document.createElement( 'button' );
	dismissButton.type = 'button';
	dismissButton.className = 'notice-dismiss';
	dismissButton.innerHTML =
		'<span class="screen-reader-text">Dismiss this notice.</span>';
	dismissButton.addEventListener( 'click', () => {
		notice.remove();
	} );
	notice.appendChild( dismissButton );
}

/**
 * Show confirmation dialog
 */
export function confirmDialog( message ) {
	return new Promise( ( resolve ) => {
		// Use native confirm for simplicity
		const result = confirm( message );
		resolve( result );
	} );
}

/**
 * Escape HTML
 */
function escapeHtml( text ) {
	const div = document.createElement( 'div' );
	div.textContent = text;
	return div.innerHTML;
}
