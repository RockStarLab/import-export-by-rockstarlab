<?php
/**
 * Model Registry
 *
 * Manages model instances and provides access to them
 * Models are accessed as properties: rsl_ie()->model->job
 *
 * @package RockStarLab\ImportExport\Model
 */

namespace RockStarLab\ImportExport\Model;

defined( 'ABSPATH' ) || exit;

class Model_Registry {

	/**
	 * Registered model instances
	 *
	 * @var array
	 */
	private $models = [];

	/**
	 * Register a model instance
	 *
	 * @param string $name Model name
	 * @param object $instance Model instance
	 */
	public function register( $name, $instance ) {
		$this->models[ $name ] = $instance;
	}

	/**
	 * Magic getter for model access
	 * Returns model instance by name
	 *
	 * @param string $name Model name
	 * @return object|null Model instance or null
	 */
	public function __get( $name ) {
		// Normalize name to lowercase for lookup
		$lookup_name = strtolower( $name );

		if ( isset( $this->models[ $lookup_name ] ) ) {
			return $this->models[ $lookup_name ];
		}

		// Convert name to PascalCase for class name (ucfirst + any underscores)
		$class_name = str_replace( ' ', '', ucwords( str_replace( '_', ' ', $name ) ) );
		$class      = "RockStarLab\ImportExport\\Model\\{$class_name}";

		if ( class_exists( $class ) ) {
			$this->models[ $lookup_name ] = new $class();
			return $this->models[ $lookup_name ];
		}

		return null;
	}
}
