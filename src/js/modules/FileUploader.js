/**
 * FileUploader - Handles chunked file uploads to bypass PHP upload limits
 *
 * @package RockStarLab\ImportExport\JS
 */

export default class FileUploader {
	/**
	 * Constructor
	 *
	 * @param {Object} options Upload options
	 */
	constructor( options = {} ) {
		this.chunkSize = options.chunkSize || 1024 * 1024; // 1MB chunks by default
		this.file = null;
		this.uploadId = null;
		this.currentChunk = 0;
		this.totalChunks = 0;
		this.uploadedBytes = 0;
		this.startTime = null;
		this.aborted = false;
		this.additionalData = options.additionalData || {}; // Additional data to send with finalize

		// Callbacks
		this.onProgress = options.onProgress || ( () => {} );
		this.onComplete = options.onComplete || ( () => {} );
		this.onError = options.onError || ( () => {} );
		this.onChunkComplete = options.onChunkComplete || ( () => {} );
	}

	/**
	 * Start uploading a file
	 *
	 * @param {File} file File object to upload
	 * @returns {Promise}
	 */
	async upload( file ) {
		this.file = file;
		this.uploadId = this.generateUploadId();
		this.currentChunk = 0;
		this.totalChunks = Math.ceil( file.size / this.chunkSize );
		this.uploadedBytes = 0;
		this.startTime = Date.now();
		this.aborted = false;

		try {
			// Upload chunks sequentially
			for ( let chunk = 0; chunk < this.totalChunks; chunk++ ) {
				if ( this.aborted ) {
					throw new Error( 'Upload aborted' );
				}

				this.currentChunk = chunk;
				await this.uploadChunk( chunk );
			}

			// Finalize upload
			const result = await this.finalizeUpload();
			this.onComplete( result );
			return result;
		} catch ( error ) {
			this.onError( error );
			throw error;
		}
	}

	/**
	 * Upload a single chunk
	 *
	 * @param {number} chunkIndex Chunk index
	 * @returns {Promise}
	 */
	async uploadChunk( chunkIndex ) {
		const clientData = window.rslIeData || window.rslIeData || {};

		const start = chunkIndex * this.chunkSize;
		const end = Math.min( start + this.chunkSize, this.file.size );
		const chunk = this.file.slice( start, end );

		const formData = new FormData();
		formData.append( 'action', 'rsl_ie_upload_chunk' );
		formData.append( 'nonce', clientData.nonce || '' );
		formData.append( 'upload_id', this.uploadId );
		formData.append( 'chunk_index', chunkIndex );
		formData.append( 'total_chunks', this.totalChunks );
		formData.append( 'file_name', this.file.name );
		formData.append( 'file_size', this.file.size );
		formData.append( 'chunk', chunk );

		const response = await fetch(
			clientData.ajaxUrl || window.ajaxurl || '',
			{
				method: 'POST',
				body: formData,
				credentials: 'same-origin',
			}
		);

		if ( ! response.ok ) {
			throw new Error( `HTTP error! status: ${ response.status }` );
		}

		const data = await response.json();

		if ( ! data.success ) {
			throw new Error( data.data || 'Failed to upload chunk' );
		}

		// Update progress
		this.uploadedBytes = end;
		const progress = ( this.uploadedBytes / this.file.size ) * 100;
		const elapsed = ( Date.now() - this.startTime ) / 1000; // seconds
		const speed = this.uploadedBytes / elapsed; // bytes per second

		this.onProgress( {
			progress: progress,
			uploadedBytes: this.uploadedBytes,
			totalBytes: this.file.size,
			currentChunk: chunkIndex + 1,
			totalChunks: this.totalChunks,
			speed: speed,
			elapsed: elapsed,
		} );

		this.onChunkComplete( chunkIndex, this.totalChunks );

		return data;
	}

	/**
	 * Finalize upload - tell server to merge chunks
	 *
	 * @returns {Promise}
	 */
	async finalizeUpload() {
		const clientData = window.rslIeData || window.rslIeData || {};

		const formData = new FormData();
		formData.append( 'action', 'rsl_ie_finalize_upload' );
		formData.append( 'nonce', clientData.nonce || '' );
		formData.append( 'upload_id', this.uploadId );
		formData.append( 'file_name', this.file.name );
		formData.append( 'file_size', this.file.size );
		formData.append( 'total_chunks', this.totalChunks );

		// Append additional data (CSV options, etc.)
		for ( const key in this.additionalData ) {
			if ( this.additionalData.hasOwnProperty( key ) ) {
				formData.append( key, this.additionalData[ key ] );
			}
		}

		const response = await fetch(
			clientData.ajaxUrl || window.ajaxurl || '',
			{
				method: 'POST',
				body: formData,
				credentials: 'same-origin',
			}
		);

		if ( ! response.ok ) {
			throw new Error( `HTTP error! status: ${ response.status }` );
		}

		const data = await response.json();

		if ( ! data.success ) {
			throw new Error( data.data || 'Failed to finalize upload' );
		}

		return data.data;
	}

	/**
	 * Abort current upload
	 */
	abort() {
		const clientData = window.rslIeData || window.rslIeData || {};

		this.aborted = true;

		// Clean up on server
		if ( this.uploadId ) {
			const formData = new FormData();
			formData.append( 'action', 'rsl_ie_abort_upload' );
			formData.append( 'nonce', clientData.nonce || '' );
			formData.append( 'upload_id', this.uploadId );

			fetch( clientData.ajaxUrl || window.ajaxurl || '', {
				method: 'POST',
				body: formData,
				credentials: 'same-origin',
			} ).catch( () => {
				// Ignore errors on abort
			} );
		}
	}

	/**
	 * Generate unique upload ID
	 *
	 * @returns {string}
	 */
	generateUploadId() {
		return (
			'upload_' +
			Date.now() +
			'_' +
			Math.random().toString( 36 ).substr( 2, 9 )
		);
	}

	/**
	 * Format bytes to human readable string
	 *
	 * @param {number} bytes Bytes
	 * @param {number} decimals Decimal places
	 * @returns {string}
	 */
	static formatBytes( bytes, decimals = 2 ) {
		if ( bytes === 0 ) return '0 Bytes';

		const k = 1024;
		const dm = decimals < 0 ? 0 : decimals;
		const sizes = [ 'Bytes', 'KB', 'MB', 'GB', 'TB' ];

		const i = Math.floor( Math.log( bytes ) / Math.log( k ) );

		return (
			parseFloat( ( bytes / Math.pow( k, i ) ).toFixed( dm ) ) +
			' ' +
			sizes[ i ]
		);
	}

	/**
	 * Format speed to human readable string
	 *
	 * @param {number} bytesPerSecond Bytes per second
	 * @returns {string}
	 */
	static formatSpeed( bytesPerSecond ) {
		return FileUploader.formatBytes( bytesPerSecond ) + '/s';
	}

	/**
	 * Format time duration
	 *
	 * @param {number} seconds Seconds
	 * @returns {string}
	 */
	static formatTime( seconds ) {
		if ( seconds < 60 ) {
			return Math.round( seconds ) + 's';
		}

		const minutes = Math.floor( seconds / 60 );
		const secs = Math.round( seconds % 60 );

		return minutes + 'm ' + secs + 's';
	}
}
