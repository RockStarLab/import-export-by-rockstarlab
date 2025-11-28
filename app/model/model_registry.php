<?php
/**
 * Model Registry
 * 
 * Manages model instances and provides access to them
 * Models are accessed as properties: WP_AIE()->model->job
 * 
 * @package WP_AIE\Model
 */

namespace WP_AIE\model;

class model_registry {

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
if ( isset( $this->models[ $name ] ) ) {
return $this->models[ $name ];
}

$class = "WP_AIE\\model\\{$name}";
if ( class_exists( $class ) ) {
$this->models[ $name ] = new $class();
return $this->models[ $name ];
}

return null;
}
}
