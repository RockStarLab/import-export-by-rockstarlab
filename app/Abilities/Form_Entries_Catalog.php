<?php
/**
 * Form entry source ability contracts.
 *
 * @package RockStarLab\ImportExport\Abilities
 */

namespace RockStarLab\ImportExport\Abilities;

defined( 'ABSPATH' ) || exit;

/** Closed schemas for discovering forms whose entries can be exported. */
class Form_Entries_Catalog {
	/**
	 * Return contracts.
	 *
	 * @return array
	 */
	public static function definitions() {
		$s    = Workflow_Schemas::string();
		$n    = Workflow_Schemas::integer();
		$b    = [ 'type' => 'boolean' ];
		$form = Workflow_Schemas::object(
			[
				'source'  => $s,
				'form_id' => $n,
				'title'   => $s,
				'active'  => $b,
				'entries' => $n,
			],
			[ 'source', 'form_id', 'title', 'entries' ]
		);
		$list = [
			'form-entry-forms'  => [
				'List forms whose entries can be exported: Gravity Forms forms (source gravityforms, form_id = form ID) and Contact Form 7 forms stored by Flamingo (source flamingo, form_id = the Flamingo channel ID). entries counts active entries (not spam or trash). Export them with export-count/export-preview/export-start, export_type form_entries and options {form_source, form_id}.',
				Schema_Registry::empty_input(),
				Workflow_Schemas::object(
					[
						'sources' => Workflow_Schemas::items( $s, 5 ),
						'forms'   => Workflow_Schemas::items( $form, 500 ),
					],
					[ 'sources', 'forms' ]
				),
			],
			'form-entry-fields' => [
				'Read the columns of one form: entry_id, date_created (site time), status (active, spam, trash), for Flamingo subject/from_name/from_email, the form fields under their labels, then source_url, ip, user_agent and user. default_fields leave out ip, user_agent and user. Exported files use the labels as column titles (Entry ID, Date, Status, Page URL...) unless options.column_headers says otherwise. Use the column names in fields and in filters (form fields: equals, not_equals, contains, not_contains; date_created: greater, less, equals_or_greater, equals_or_less, between; status: equals one value).',
				Workflow_Schemas::object(
					[
						'form_source' => Workflow_Schemas::string( [ 'gravityforms', 'flamingo' ] ),
						'form_id'     => Workflow_Schemas::integer( 1 ),
					],
					[ 'form_source', 'form_id' ]
				),
				Workflow_Schemas::object(
					[
						'fields'         => Workflow_Schemas::items(
							Workflow_Schemas::object(
								[
									'name'  => $s,
									'label' => $s,
								],
								[ 'name', 'label' ]
							),
							500
						),
						'default_fields' => Workflow_Schemas::items( $s, 500 ),
					],
					[ 'fields', 'default_fields' ]
				),
			],
		];
		$out  = [];
		foreach ( $list as $key => $contract ) {
			$out[ $key ] = [
				'label'       => ucwords( str_replace( '-', ' ', $key ) ),
				'description' => $contract[0],
				'input'       => $contract[1],
				'output'      => $contract[2],
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
			];
		}
		return $out;
	}
}
