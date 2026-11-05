/** Shared tab indicator. Completion is explicit: 100% may still mean finalizing. */
const labels = {
	import: [ 'Importing', 'Import' ],
	export: [ 'Exporting', 'Export' ],
	upload: [ 'Uploading', 'Upload' ],
	ai: [ 'AI importing', 'AI import' ],
	media: [ 'Syncing media', 'Media sync' ],
	sync: [ 'Syncing content', 'Content sync' ],
	migration: [ 'Migrating', 'Migration' ],
	hash: [ 'Indexing media', 'Media indexing' ],
};

class TabProgress {
	update( key, percent = null, status = 'processing' ) {
		if ( ! this.original ) {
			this.original = { title: document.title, icons: [] };
			document
				.querySelectorAll( 'link[rel~="icon"]' )
				.forEach( ( icon ) => {
					this.original.icons.push( {
						icon,
						parent: icon.parentNode,
						next: icon.nextSibling,
					} );
					icon.remove();
				} );
			this.icon = document.createElement( 'link' );
			this.icon.rel = 'icon';
			this.icon.type = 'image/svg+xml';
			document.head.appendChild( this.icon );
		}
		this.key = key;
		const [ active, name ] = labels[ key ] || [ 'Processing', 'Operation' ];
		const terminal = [
			'completed',
			'failed',
			'paused',
			'cancelled',
		].includes( status );
		const prefix = {
			completed: '✓ ',
			failed: '',
			paused: 'Ⅱ ',
			cancelled: '',
		};
		const value =
			percent === null || ! Number.isFinite( Number( percent ) )
				? ''
				: ` ${ Math.max(
						0,
						Math.min( 100, Math.round( Number( percent ) ) )
				  ) }%`;
		document.title = `${
			terminal
				? `${ prefix[ status ] }${ name } ${ status }`
				: `${ active }…${ value }`
		} — WP Import Export`;
		if ( terminal ) {
			clearInterval( this.timer );
			this.timer = null;
			this.draw( status, 0 );
		} else if ( ! this.timer ) {
			this.draw( status, 0 );
			let frame = 0;
			this.timer = setInterval(
				() => this.draw( status, ++frame * 45 ),
				200
			);
		}
	}

	finish( key, status = 'completed' ) {
		if ( this.key === key ) this.update( key, null, status );
	}

	draw( status, angle ) {
		const shape =
			status === 'completed'
				? '<circle cx="16" cy="16" r="15" fill="#16a34a"/><path d="m8 16 5 5 11-11" fill="none" stroke="white" stroke-width="3"/>'
				: status === 'failed'
				? '<circle cx="16" cy="16" r="12" fill="#dc2626"/>'
				: [ 'paused', 'cancelled' ].includes( status )
				? '<circle cx="16" cy="16" r="14" fill="#d97706"/><path d="M12 9v14M20 9v14" stroke="white" stroke-width="4"/>'
				: `<circle cx="16" cy="16" r="12" fill="none" stroke="#cbd5e1" stroke-width="4"/><path d="M16 4a12 12 0 0 1 12 12" fill="none" stroke="#7352c7" stroke-width="4" transform="rotate(${ angle } 16 16)"/>`;
		this.icon.href = `data:image/svg+xml,${ encodeURIComponent(
			`<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32">${ shape }</svg>`
		) }`;
	}

	reset( key ) {
		if ( ! this.original || ( key && this.key !== key ) ) return;
		clearInterval( this.timer );
		this.timer = null;
		document.title = this.original.title;
		this.icon.remove();
		this.original.icons
			.slice()
			.reverse()
			.forEach( ( { icon, parent, next } ) => {
				parent.insertBefore(
					icon,
					next?.parentNode === parent ? next : null
				);
			} );
		this.original = null;
		this.key = null;
	}
}

// Several entry bundles can be loaded on the same WordPress admin page.
const tabProgress = window.rslIeTabProgress || new TabProgress();
window.rslIeTabProgress = tabProgress;
export default tabProgress;
