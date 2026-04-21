<?php
/**
 * Batch Processor
 *
 * Processes data in batches to prevent memory overflow and timeouts
 *
 * @package RockStarLab\ImportExport\Model\Queue
 */

namespace RockStarLab\ImportExport\Model\Queue;

defined( 'ABSPATH' ) || exit;

class Batch_Processor {

	/**
	 * Batch size (items per batch)
	 *
	 * @var int
	 */
	protected $batch_size = 50;

	/**
	 * Memory limit (in bytes)
	 *
	 * @var int
	 */
	protected $memory_limit;

	/**
	 * Time limit (in seconds)
	 *
	 * @var int
	 */
	protected $time_limit = 25;

	/**
	 * Start time
	 *
	 * @var float
	 */
	protected $start_time;

	/**
	 * Current batch number
	 *
	 * @var int
	 */
	protected $current_batch = 0;

	/**
	 * Total items processed
	 *
	 * @var int
	 */
	protected $processed = 0;

	/**
	 * Constructor
	 *
	 * @param int $batch_size Optional. Items per batch (default: 50)
	 */
	public function __construct( $batch_size = 50 ) {
		$this->batch_size = $batch_size;
		$this->start_time = microtime( true );

		// Get memory limit (80% of PHP memory_limit)
		$memory_limit       = ini_get( 'memory_limit' );
		$memory_bytes       = $this->convert_to_bytes( $memory_limit );
		$this->memory_limit = $memory_bytes * 0.8;
	}

	/**
	 * Process data in batches
	 *
	 * @param array    $data     Data to process
	 * @param callable $callback Callback function for processing each item
	 * @param array    $options  Optional. Processing options
	 * @return array Processing results
	 */
	public function process( $data, $callback, $options = array() ) {
		$results = array(
			'processed' => 0,
			'success'   => 0,
			'failed'    => 0,
			'errors'    => array(),
			'completed' => false,
		);

		$batches = array_chunk( $data, $this->batch_size );

		foreach ( $batches as $batch_index => $batch ) {
			$this->current_batch = $batch_index + 1;

			// Process batch
			$batch_result = $this->process_batch( $batch, $callback );

			// Update results
			$results['processed'] += $batch_result['processed'];
			$results['success']   += $batch_result['success'];
			$results['failed']    += $batch_result['failed'];
			$results['errors']     = array_merge( $results['errors'], $batch_result['errors'] );

			// Check if should pause
			if ( $this->should_pause() ) {
				$results['completed']    = false;
				$results['next_batch']   = $this->current_batch + 1;
				$results['pause_reason'] = $this->get_pause_reason();
				$results['memory_usage'] = memory_get_usage( true );
				$results['time_elapsed'] = microtime( true ) - $this->start_time;
				break;
			}

			// Clear memory between batches
			$this->clear_memory();
		}

		// If all batches processed
		if ( $batch_index === count( $batches ) - 1 ) {
			$results['completed'] = true;
		}

		return $results;
	}

	/**
	 * Process single batch
	 *
	 * @param array    $batch    Batch items
	 * @param callable $callback Processing callback
	 * @return array Batch results
	 */
	protected function process_batch( $batch, $callback ) {
		$result = array(
			'processed' => 0,
			'success'   => 0,
			'failed'    => 0,
			'errors'    => array(),
		);

		foreach ( $batch as $index => $item ) {
			try {
				$item_result = call_user_func( $callback, $item, $index );

				if ( is_wp_error( $item_result ) ) {
					++$result['failed'];
					$result['errors'][] = array(
						'index'   => $index,
						'message' => $item_result->get_error_message(),
					);
				} else {
					++$result['success'];
				}

				++$result['processed'];
				++$this->processed;

			} catch ( \Exception $e ) {
				++$result['failed'];
				$result['errors'][] = array(
					'index'   => $index,
					'message' => $e->getMessage(),
				);
			}
		}

		return $result;
	}

	/**
	 * Check if processing should pause
	 *
	 * @return bool True if should pause
	 */
	protected function should_pause() {
		// Check memory usage
		if ( $this->is_memory_limit_reached() ) {
			return true;
		}

		// Check time limit
		if ( $this->is_time_limit_reached() ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if memory limit reached
	 *
	 * @return bool
	 */
	protected function is_memory_limit_reached() {
		$memory_usage = memory_get_usage( true );
		return $memory_usage >= $this->memory_limit;
	}

	/**
	 * Check if time limit reached
	 *
	 * @return bool
	 */
	protected function is_time_limit_reached() {
		$time_elapsed = microtime( true ) - $this->start_time;
		return $time_elapsed >= $this->time_limit;
	}

	/**
	 * Get pause reason
	 *
	 * @return string
	 */
	protected function get_pause_reason() {
		if ( $this->is_memory_limit_reached() ) {
			return 'memory_limit';
		}

		if ( $this->is_time_limit_reached() ) {
			return 'time_limit';
		}

		return 'unknown';
	}

	/**
	 * Clear memory
	 */
	protected function clear_memory() {
		if ( function_exists( 'gc_collect_cycles' ) ) {
			gc_collect_cycles();
		}
	}

	/**
	 * Convert memory string to bytes
	 *
	 * @param string $value Memory value (e.g., '256M', '1G')
	 * @return int Bytes
	 */
	protected function convert_to_bytes( $value ) {
		$value = trim( $value );
		$last  = strtolower( $value[ strlen( $value ) - 1 ] );
		$value = (int) $value;

		switch ( $last ) {
			case 'g':
				$value *= 1024;
				// Fall through.
			case 'm':
				$value *= 1024;
				// Fall through.
			case 'k':
				$value *= 1024;
		}

		return $value;
	}

	/**
	 * Get batch size
	 *
	 * @return int
	 */
	public function get_batch_size() {
		return $this->batch_size;
	}

	/**
	 * Set batch size
	 *
	 * @param int $size Batch size
	 */
	public function set_batch_size( $size ) {
		$this->batch_size = max( 1, (int) $size );
	}

	/**
	 * Get time limit
	 *
	 * @return int
	 */
	public function get_time_limit() {
		return $this->time_limit;
	}

	/**
	 * Set time limit
	 *
	 * @param int $seconds Time limit in seconds
	 */
	public function set_time_limit( $seconds ) {
		$this->time_limit = max( 5, (int) $seconds );
	}

	/**
	 * Get current batch number
	 *
	 * @return int
	 */
	public function get_current_batch() {
		return $this->current_batch;
	}

	/**
	 * Get total processed items
	 *
	 * @return int
	 */
	public function get_processed() {
		return $this->processed;
	}

	/**
	 * Get memory usage
	 *
	 * @param bool $format Optional. Format as human-readable (default: false)
	 * @return mixed Memory usage in bytes or formatted string
	 */
	public function get_memory_usage( $format = false ) {
		$usage = memory_get_usage( true );

		if ( ! $format ) {
			return $usage;
		}

		return $this->format_bytes( $usage );
	}

	/**
	 * Get elapsed time
	 *
	 * @return float Seconds
	 */
	public function get_elapsed_time() {
		return microtime( true ) - $this->start_time;
	}

	/**
	 * Format bytes to human-readable
	 *
	 * @param int $bytes Bytes
	 * @return string Formatted string
	 */
	protected function format_bytes( $bytes ) {
		$units = array( 'B', 'KB', 'MB', 'GB', 'TB' );

		for ( $i = 0; $bytes > 1024 && $i < count( $units ) - 1; ++$i ) {
			$bytes /= 1024;
		}

		return round( $bytes, 2 ) . ' ' . $units[ $i ];
	}
}
