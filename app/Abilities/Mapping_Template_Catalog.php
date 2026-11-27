<?php
/**
 * Mapping template ability contracts.
 *
 * @package RockStarLab\ImportExport\Abilities
 */

namespace RockStarLab\ImportExport\Abilities;

defined( 'ABSPATH' ) || exit;

/** Closed schemas for saved import field mappings. */
class Mapping_Template_Catalog {
	/**
	 * Return contracts.
	 *
	 * @return array
	 */
	public static function definitions() {
		$s       = Workflow_Schemas::string();
		$n       = Workflow_Schemas::integer();
		$b       = array( 'type' => 'boolean' );
		$tid     = array(
			'type'    => 'string',
			'pattern' => '^tpl_[a-f0-9]{12}$',
		);
		$type    = array(
			'type'      => 'string',
			'pattern'   => '^[a-z0-9_]{1,64}$',
			'maxLength' => 64,
		);
		$name    = array(
			'type'      => 'string',
			'minLength' => 1,
			'maxLength' => 100,
		);
		$entry   = Workflow_Schemas::object(
			array(
				'source_field'    => array(
					'type'      => 'string',
					'minLength' => 1,
					'maxLength' => 255,
				),
				'target_field'    => array(
					'type'      => 'string',
					'minLength' => 1,
					'maxLength' => 191,
				),
				'source_index'    => $n,
				'function_ids'    => Workflow_Schemas::items(
					array(
						'type'    => array( 'integer', 'string' ),
						'pattern' => '^[A-Za-z0-9_-]+$',
					),
					100
				),
				'functions'       => Workflow_Schemas::items( Workflow_Schemas::value(), 100 ),
				'taxonomy_format' => Workflow_Schemas::string( array( 'name', 'slug', 'id' ) ),
				'field_type'      => $s,
			),
			array( 'source_field', 'target_field' )
		);
		$pair    = Workflow_Schemas::object(
			array(
				'source_field' => $s,
				'target_field' => $s,
			),
			array( 'source_field', 'target_field' )
		);
		$parser  = Workflow_Schemas::object(
			array(
				'delimiter' => $s,
				'encoding'  => $s,
			)
		);
		$full    = Workflow_Schemas::object(
			array(
				'template_id'   => $s,
				'name'          => $s,
				'import_type'   => $s,
				'mapping'       => Workflow_Schemas::items( Workflow_Schemas::value(), 1000 ),
				'options'       => Workflow_Schemas::value(),
				'parser'        => Workflow_Schemas::value(),
				'source_job_id' => $n,
				'created_at'    => $s,
				'updated_at'    => $s,
			),
			array( 'template_id', 'name', 'import_type', 'mapping', 'options', 'parser', 'created_at', 'updated_at' )
		);
		$summary = Workflow_Schemas::object(
			array(
				'template_id'   => $s,
				'name'          => $s,
				'import_type'   => $s,
				'mapping_count' => $n,
				'columns'       => Workflow_Schemas::items( $s, 1000 ),
				'updated_at'    => $s,
			),
			array( 'template_id', 'name', 'import_type', 'mapping_count', 'columns', 'updated_at' )
		);
		$by_id   = Workflow_Schemas::object( array( 'template_id' => $tid ), array( 'template_id' ) );
		$common  = ' Templates keep column names, target fields, transformations and import settings (match key, update/create choices, status, media options), never file paths or rows. They are shared by all administrators of the site.';
		$list    = array(
			'mapping-template-list'     => array(
				'List saved import field mapping templates, newest first, optionally for one import_type (post, page, comment, media or a Pro type such as product).',
				Workflow_Schemas::object( array( 'import_type' => $type ) ),
				Workflow_Schemas::object( array( 'templates' => Workflow_Schemas::items( $summary, 200 ) ), array( 'templates' ) ),
				true,
				false,
			),
			'mapping-template-get'      => array(
				'Read one mapping template with its mapping and import options.',
				$by_id,
				$full,
				true,
				false,
			),
			'mapping-template-save'     => array(
				'Create a template, or update one with template_id. mapping entries name the file column (source_field) and the target field; options use the import-start option names. A new template with the name of an existing template of the same import_type fails with rsl_ie_template_exists unless overwrite is true.',
				Workflow_Schemas::object(
					array(
						'template_id' => $tid,
						'name'        => $name,
						'import_type' => $type,
						'mapping'     => Workflow_Schemas::items( $entry, 1000 ),
						'options'     => Workflow_Schemas::import_options(),
						'parser'      => $parser,
						'overwrite'   => $b,
					),
					array( 'name', 'import_type', 'mapping' )
				),
				$full,
				false,
				false,
			),
			'mapping-template-from-job' => array(
				'Create a template from the mapping and settings of an earlier import job (see job-list). name defaults to the job name.',
				Workflow_Schemas::object(
					array(
						'job_id'    => Workflow_Schemas::integer( 1 ),
						'name'      => $name,
						'overwrite' => $b,
					),
					array( 'job_id' )
				),
				$full,
				false,
				false,
			),
			'mapping-template-apply'    => array(
				'Match a template to the columns of an uploaded import file (file_id from upload-complete) by column name, ignoring case and surrounding spaces. Returns mapping and options that can be passed unchanged to import-validate and import-start, matched (which file column feeds which field), template columns missing from the file (their fields stay empty), and file columns the template does not use. Nothing is imported.',
				Workflow_Schemas::object(
					array(
						'template_id' => $tid,
						'file_id'     => Workflow_Schemas::file_id(),
					),
					array( 'template_id', 'file_id' )
				),
				Workflow_Schemas::object(
					array(
						'template_id'      => $s,
						'name'             => $s,
						'import_type'      => $s,
						'file_id'          => $s,
						'options'          => Workflow_Schemas::value(),
						'mapping'          => Workflow_Schemas::items( Workflow_Schemas::value(), 1000 ),
						'matched'          => Workflow_Schemas::items( Workflow_Schemas::value(), 1000 ),
						'missing'          => Workflow_Schemas::items( $pair, 1000 ),
						'unmapped_columns' => Workflow_Schemas::items( $s, 5000 ),
					),
					array( 'template_id', 'name', 'import_type', 'file_id', 'options', 'mapping', 'matched', 'missing', 'unmapped_columns' )
				),
				false,
				false,
			),
			'mapping-template-delete'   => array(
				'Delete a mapping template. Imports and jobs made with it are not affected.',
				$by_id,
				Workflow_Schemas::object(
					array(
						'deleted'     => $b,
						'template_id' => $s,
					),
					array( 'deleted', 'template_id' )
				),
				false,
				true,
			),
		);
		$out     = array();
		foreach ( $list as $key => $contract ) {
			$out[ $key ] = array(
				'label'       => ucwords( str_replace( '-', ' ', $key ) ),
				'description' => $contract[0] . $common,
				'input'       => $contract[1],
				'output'      => $contract[2],
				'readonly'    => $contract[3],
				'destructive' => $contract[4],
				'idempotent'  => $contract[3] || in_array( $key, array( 'mapping-template-apply', 'mapping-template-delete' ), true ),
			);
		}
		return $out;
	}
}
