import 'select2';
import 'select2/dist/css/select2.min.css';

const SchedulesModule = {
	init() {
		const $ = jQuery;
		const $select = $( '#source-job-id.rsl-ie-source-job-select' );

		if ( ! $select.length || typeof $select.select2 !== 'function' ) {
			return;
		}

		const data = window.rslIeData || {};
		const action = 'rsl_ie_schedule_search_source_jobs';
		const nonce = data.nonces?.[ action ] || '';

		$select.select2( {
			width: '25em',
			allowClear: ! $select.prop( 'required' ),
			placeholder: $select.data( 'placeholder' ) || 'Search Jobs...',
			minimumInputLength: 0,
			ajax: {
				url: data.ajaxUrl || window.ajaxurl || '',
				dataType: 'json',
				delay: 250,
				data( params ) {
					return {
						action,
						nonce,
						q: params.term || '',
						page: params.page || 1,
					};
				},
				processResults( response ) {
					return {
						results: response.results || [],
						pagination: {
							more: Boolean( response.pagination?.more ),
						},
					};
				},
				cache: true,
			},
		} );
	},
};

export default SchedulesModule;
