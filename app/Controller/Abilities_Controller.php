<?php
/**
 * WordPress Abilities API controller.
 *
 * @package RockStarLab\ImportExport\Controller
 */

namespace RockStarLab\ImportExport\Controller;

use RockStarLab\ImportExport\Abilities\Ability_Registry;
use RockStarLab\ImportExport\Abilities\Access_Policy;
use RockStarLab\ImportExport\Abilities\Schema_Registry;
use RockStarLab\ImportExport\Service\Discovery_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Hooks the lazy core registries before init, including REST and cron requests.
 */
class Abilities_Controller {

	/**
	 * Whether the controller hooks have already been installed.
	 *
	 * @var bool
	 */
	private static $initialized = false;

	/**
	 * Install hooks without initializing the core registry prematurely.
	 */
	public function __construct() {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;
		\RockStarLab\ImportExport\Service\UpdraftPlus_Service::hooks();
		\RockStarLab\ImportExport\Helper\Elementor_Refs::watch_conditions();
		\RockStarLab\ImportExport\Helper\Term_Payload::hooks();
		\RockStarLab\ImportExport\Service\Mcp_Client_Compat::hooks();
		add_filter( 'rsl_ie_sync_post_definitions', array( \RockStarLab\ImportExport\Service\Sync_ACF_Posts::class, 'definitions' ) );
		add_filter( 'rsl_ie_sync_post_definitions', array( \RockStarLab\ImportExport\Service\Sync_ACF_Media::class, 'definitions' ) );
		add_filter( 'rsl_ie_sync_peer_extensions', array( \RockStarLab\ImportExport\Service\Sync_ACF_Media::class, 'extensions' ) );
		add_filter( 'rsl_ie_sync_peer_extensions', array( \RockStarLab\ImportExport\Service\Sync_ACF_Posts::class, 'extensions' ) );
		add_action( 'wp_abilities_api_categories_init', array( $this, 'register_categories' ) );
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
		add_action( 'rest_api_init', array( $this, 'register_hierarchy_transport' ) );
		add_action( 'rest_api_init', array( $this, 'register_acf_graph_transport' ) );
	}

	/** Register reference-only graph transport under the existing peer identity. */
	public function register_acf_graph_transport() {
		register_rest_route(
			'rsl-ie/v1',
			'/migration-acf-posts',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( \RockStarLab\ImportExport\Service\Sync_Authentication::class, 'permission' ),
				'callback'            => static function ( $request ) {
					return \RockStarLab\ImportExport\Service\Sync_Authentication::run( $request, array( \RockStarLab\ImportExport\Service\Sync_ACF_Graph::class, 'peer' ) );
				},
			)
		);
	}


	/**
	 * Register the authenticated, versioned migration hierarchy peer transport.
	 *
	 * @return void
	 */
	public function register_hierarchy_transport() {
		foreach ( array(
			'migration-hierarchy-check'   => false,
			'migration-hierarchy-receive' => true,
		) as $route => $receive ) {
			register_rest_route(
				'rsl-ie/v1',
				'/' . $route,
				array(
					'methods'             => 'POST',
					'permission_callback' => array( \RockStarLab\ImportExport\Service\Sync_Authentication::class, 'permission' ),
					'callback'            => static function ( $request ) use ( $receive ) {
						return \RockStarLab\ImportExport\Service\Sync_Authentication::run(
							$request,
							static function ( $authorized ) use ( $receive ) {
								return \RockStarLab\ImportExport\Service\Sync_Hierarchy::peer( $authorized, $receive );
							}
						);
					},
				)
			);
		}
	}

	/**
	 * Register the shared Free/Pro category.
	 *
	 * @return void
	 */
	public function register_categories() {
		if ( ! Ability_Registry::is_enabled() || ! Ability_Registry::is_available() ) {
			return;
		}
		wp_register_ability_category(
			'rsl-ie',
			array(
				'label'       => __( 'Content Sync, Media Sync, Import, Export with AI Agents Support', 'import-export-by-rockstarlab' ),
				'description' => __( 'Discover and run the available import and export workflows.', 'import-export-by-rockstarlab' ),
			)
		);
	}

	/**
	 * Register the implemented discovery endpoints.
	 *
	 * @return void
	 */
	public function register_abilities() {
		if ( ! Ability_Registry::is_enabled() || ! Ability_Registry::is_available() ) {
			return;
		}
		if ( Ability_Registry::is_updraftplus_enabled() ) {
			$updraft = new \RockStarLab\ImportExport\Service\UpdraftPlus_Service();
			foreach ( \RockStarLab\ImportExport\Abilities\UpdraftPlus_Catalog::definitions() as $name => $definition ) {
				Ability_Registry::register_workflow(
					'rsl-ie/' . $name,
					$definition,
					static function ( $input = array() ) use ( $updraft, $name ) {
						return $updraft->execute( $name, $input );
					}
				);
			}
		}
		$service     = new Discovery_Service();
		$boolean     = array( 'type' => 'boolean' );
		$definitions = array(
			'get-capabilities'   => array(
				'label'       => __( 'Get plugin abilities', 'import-export-by-rockstarlab' ),
				'description' => __( 'List the implemented RockStarLab abilities and plugin version. Only listed abilities are available; this does not promise future workflow endpoints.', 'import-export-by-rockstarlab' ),
				'callback'    => 'get_capabilities',
				'output'      => Schema_Registry::object(
					array(
						'api_version'      => array( 'type' => 'integer' ),
						'plugin_version'   => array( 'type' => 'string' ),
						'abilities'        => Schema_Registry::strings(),
						'pro_addon_active' => $boolean,
					)
				),
			),
			'list-content-types' => array(
				'label'       => __( 'List free content types', 'import-export-by-rockstarlab' ),
				'description' => __( 'Read the free plugin content catalogue. URL lists support export only. Consult get-capabilities for implemented workflow abilities.', 'import-export-by-rockstarlab' ),
				'callback'    => 'list_content_types',
				'output'      => Schema_Registry::object(
					array(
						'edition' => array(
							'type' => 'string',
							'enum' => array( 'free' ),
						),
						'types'   => Schema_Registry::strings(),
					)
				),
			),
			'list-formats'       => array(
				'label'       => __( 'List file formats', 'import-export-by-rockstarlab' ),
				'description' => __( 'Read the file formats supported by the plugin. This operation does not read or create files.', 'import-export-by-rockstarlab' ),
				'callback'    => 'list_formats',
				'output'      => Schema_Registry::object( array( 'formats' => Schema_Registry::strings() ) ),
			),
			'get-integrations'   => array(
				'label'       => __( 'Get integration availability', 'import-export-by-rockstarlab' ),
				'description' => __( 'Check whether supported field and commerce integrations are present. Does not reveal credentials, test AI or grant access to Pro operations.', 'import-export-by-rockstarlab' ),
				'callback'    => 'get_integrations',
				'output'      => Schema_Registry::object( array_fill_keys( array( 'acf', 'yoast', 'rank_math', 'elementor', 'woocommerce' ), $boolean ) ),
			),
		);

		foreach ( $definitions as $name => $definition ) {
			Ability_Registry::register_discovery(
				'rsl-ie/' . $name,
				$definition['label'],
				$definition['description'],
				$definition['output'],
				array( $service, $definition['callback'] ),
				array( $this, 'can_discover' )
			);
		}
		$settings = new \RockStarLab\ImportExport\Service\Settings_Service();
		foreach ( \RockStarLab\ImportExport\Abilities\Settings_Catalog::definitions() as $name => $definition ) {
			Ability_Registry::register_workflow(
				'rsl-ie/' . $name,
				$definition,
				static function ( $input = array() ) use ( $settings, $name ) {
					return $settings->execute( $name, $input );
				}
			);
		}
		$schedules = new \RockStarLab\ImportExport\Service\Schedule_Service();
		foreach ( \RockStarLab\ImportExport\Abilities\Schedule_Catalog::definitions() as $name => $definition ) {
			Ability_Registry::register_workflow(
				'rsl-ie/' . $name,
				$definition,
				static function ( $input = array() ) use ( $schedules, $name ) {
					return $schedules->execute( $name, $input );
				}
			);
		}
		$templates = new \RockStarLab\ImportExport\Service\Mapping_Template_Service();
		foreach ( \RockStarLab\ImportExport\Abilities\Mapping_Template_Catalog::definitions() as $name => $definition ) {
			Ability_Registry::register_workflow(
				'rsl-ie/' . $name,
				$definition,
				static function ( $input = array() ) use ( $templates, $name ) {
					return $templates->execute( $name, $input );
				}
			);
		}
		$notifications = new \RockStarLab\ImportExport\Service\Job_Notification_Service();
		foreach ( \RockStarLab\ImportExport\Abilities\Notification_Catalog::definitions() as $name => $definition ) {
			Ability_Registry::register_workflow(
				'rsl-ie/' . $name,
				$definition,
				static function ( $input = array() ) use ( $notifications, $name ) {
					return $notifications->execute( $name, $input );
				}
			);
		}
		$forms = new \RockStarLab\ImportExport\Service\Form_Entries_Service();
		foreach ( \RockStarLab\ImportExport\Abilities\Form_Entries_Catalog::definitions() as $name => $definition ) {
			Ability_Registry::register_workflow(
				'rsl-ie/' . $name,
				$definition,
				static function ( $input = array() ) use ( $forms, $name ) {
					return $forms->execute( $name, (array) $input );
				}
			);
		}
		$triggers = new \RockStarLab\ImportExport\Service\Trigger_Service();
		foreach ( \RockStarLab\ImportExport\Abilities\Trigger_Catalog::definitions() as $name => $definition ) {
			Ability_Registry::register_workflow(
				'rsl-ie/' . $name,
				$definition,
				static function ( $input = array() ) use ( $triggers, $name ) {
					return $triggers->execute( $name, $input );
				}
			);
		}
		$ai = new \RockStarLab\ImportExport\Service\AI_URL_Service();
		foreach ( \RockStarLab\ImportExport\Abilities\AI_URL_Catalog::definitions() as $name => $definition ) {
			Ability_Registry::register_workflow(
				'rsl-ie/' . $name,
				$definition,
				static function ( $input = array() ) use ( $ai, $name ) {
					return $ai->execute( $name, $input );
				}
			);
		}
		$workflow = new \RockStarLab\ImportExport\Service\Workflow_Service();
		foreach ( \RockStarLab\ImportExport\Abilities\Workflow_Catalog::definitions() as $name => $definition ) {
			Ability_Registry::register_workflow(
				'rsl-ie/' . $name,
				$definition,
				static function ( $input = array() ) use ( $workflow, $name ) {
					return $workflow->execute( $name, $input ); }
			);
		}
		$sync = new \RockStarLab\ImportExport\Service\Sync_Workflow_Service();
		foreach ( \RockStarLab\ImportExport\Abilities\Sync_Catalog::definitions() as $name => $definition ) {
			Ability_Registry::register_workflow(
				'rsl-ie/' . $name,
				$definition,
				static function ( $input = array() ) use ( $sync, $name ) {
					return $sync->execute( $name, $input );
				}
			);
		}
		$sync_jobs         = new \RockStarLab\ImportExport\Service\Sync_Job_Service();
		$queue_definitions = \RockStarLab\ImportExport\Abilities\Sync_Job_Catalog::starts( \RockStarLab\ImportExport\Abilities\Sync_Catalog::definitions() + \RockStarLab\ImportExport\Abilities\Media_Library_Catalog::definitions() ) + \RockStarLab\ImportExport\Abilities\Sync_Job_Catalog::controls();
		foreach ( $queue_definitions as $name => $definition ) {
			Ability_Registry::register_workflow(
				'rsl-ie/' . $name,
				$definition,
				static function ( $input = array() ) use ( $sync_jobs, $name ) {
					return $sync_jobs->execute( $name, $input );
				}
			);
		}
		$migration = new \RockStarLab\ImportExport\Service\Migration_Service();
		foreach ( \RockStarLab\ImportExport\Abilities\Migration_Catalog::definitions() as $name => $definition ) {
			Ability_Registry::register_workflow(
				'rsl-ie/' . $name,
				$definition,
				static function ( $input = array() ) use ( $migration, $name ) {
					return $migration->execute( $name, $input );
				}
			);
		}
		$media = new \RockStarLab\ImportExport\Service\Media_Workflow_Service();
		foreach ( \RockStarLab\ImportExport\Abilities\Media_Catalog::definitions() as $name => $definition ) {
			Ability_Registry::register_workflow(
				'rsl-ie/' . $name,
				$definition,
				static function ( $input = array() ) use ( $media, $name ) {
					return $media->execute( $name, $input );
				}
			);
		}
	}

	/**
	 * Preserve the plugin's existing administrator permission requirement.
	 *
	 * @return bool
	 */
	public function can_discover() {
		return true === Access_Policy::check_management();
	}
}
