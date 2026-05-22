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
	dismissButton.innerHTML = `<span class="screen-reader-text">${
		window.rslIeData?.i18n?.dismissNotice || 'Dismiss this notice.'
	}</span>`;
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
	dismissButton.innerHTML = `<span class="screen-reader-text">${
		window.rslIeData?.i18n?.dismissNotice || 'Dismiss this notice.'
	}</span>`;
	dismissButton.addEventListener( 'click', () => {
		notice.remove();
	} );
	notice.appendChild( dismissButton );
}

/**
 * Show error message inside a modal
 */
export function showModalError( message, modalElement = null ) {
	// If no modal specified, try to find the currently visible modal
	if ( ! modalElement ) {
		modalElement =
			document.querySelector( '.rsl-ie-modal[style*="display: flex"]' ) ||
			document.querySelector( '.rsl-ie-modal[style*="display:flex"]' ) ||
			document.querySelector( '.rsl-ie-modal' );
	}

	if ( ! modalElement ) {
		// Fallback to regular error if no modal found
		showError( message );
		return;
	}

	// Remove any existing error notices in the modal
	const existingErrors = modalElement.querySelectorAll(
		'.rsl-ie-modal-error'
	);
	existingErrors.forEach( ( el ) => el.remove() );

	// Create error notice
	const notice = document.createElement( 'div' );
	notice.className = 'notice notice-error is-dismissible rsl-ie-modal-error';
	notice.innerHTML = `<p>${ escapeHtml( message ) }</p>`;

	// Find modal content area
	const modalContent =
		modalElement.querySelector( '.rsl-ie-modal-content' ) ||
		modalElement.querySelector( '.rsl-ie-modal-body' ) ||
		modalElement;

	// Insert at the top of modal content
	if ( modalContent.firstChild ) {
		modalContent.insertBefore( notice, modalContent.firstChild );
	} else {
		modalContent.appendChild( notice );
	}

	// Auto dismiss after 10 seconds
	setTimeout( () => {
		notice.remove();
	}, 10000 );

	// Make it dismissible
	const dismissButton = document.createElement( 'button' );
	dismissButton.type = 'button';
	dismissButton.className = 'notice-dismiss';
	dismissButton.innerHTML = `<span class="screen-reader-text">${
		window.rslIeData?.i18n?.dismissNotice || 'Dismiss this notice.'
	}</span>`;
	dismissButton.addEventListener( 'click', () => {
		notice.remove();
	} );
	notice.appendChild( dismissButton );

	// Scroll to top of modal to show error
	modalContent.scrollTop = 0;
}

/**
 * Clear modal errors
 */
export function clearModalErrors( modalElement = null ) {
	// If no modal specified, try to find the currently visible modal or clear all
	if ( ! modalElement ) {
		const existingErrors = document.querySelectorAll(
			'.rsl-ie-modal-error'
		);
		existingErrors.forEach( ( el ) => el.remove() );
	} else {
		const existingErrors = modalElement.querySelectorAll(
			'.rsl-ie-modal-error'
		);
		existingErrors.forEach( ( el ) => el.remove() );
	}
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
