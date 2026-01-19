<?php
/**
 * Verify Export Script
 * 
 * Checks if exported CSV data matches the database
 * 
 * Usage: wp eval-file verify-export.php
 */

// Load WordPress
if ( ! defined( 'ABSPATH' ) ) {
	// Try to load WordPress
	$wp_load_paths = [
		__DIR__ . '/../../../../../wp-load.php',
		__DIR__ . '/../../../../wp-load.php',
		__DIR__ . '/../../../wp-load.php',
	];
	
	$wp_loaded = false;
	foreach ( $wp_load_paths as $path ) {
		if ( file_exists( $path ) ) {
			require_once $path;
			$wp_loaded = true;
			break;
		}
	}
	
	if ( ! $wp_loaded ) {
		die( "Could not load WordPress. Please run this script using WP-CLI:\nwp eval-file verify-export.php\n" );
	}
}

/**
 * Export Verification Class
 */
class Export_Verifier {
	
	/**
	 * CSV file path
	 */
	private $csv_file;
	
	/**
	 * Verification results
	 */
	private $results = [
		'total_rows'       => 0,
		'verified_posts'   => 0,
		'errors'           => [],
		'warnings'         => [],
		'field_mismatches' => [],
	];
	
	/**
	 * Fields to verify
	 */
	private $core_fields = [
		'ID',
		'post_title',
		'post_content',
		'post_excerpt',
		'post_date',
		'post_name',
		'post_status',
		'post_author',
		'post_modified',
		'post_parent',
		'menu_order',
		'comment_status',
	];
	
	/**
	 * Constructor
	 * 
	 * @param string $csv_file CSV file path
	 */
	public function __construct( $csv_file ) {
		$this->csv_file = $csv_file;
	}
	
	/**
	 * Run verification
	 */
	public function verify() {
		echo "🔍 Starting export verification...\n\n";
		
		if ( ! file_exists( $this->csv_file ) ) {
			echo "❌ Error: CSV file not found: {$this->csv_file}\n";
			return;
		}
		
		echo "📄 Reading CSV file: {$this->csv_file}\n";
		echo "📊 File size: " . $this->format_bytes( filesize( $this->csv_file ) ) . "\n\n";
		
		// Open CSV file
		$handle = fopen( $this->csv_file, 'r' );
		if ( ! $handle ) {
			echo "❌ Error: Could not open CSV file\n";
			return;
		}
		
		// Read header row
		$headers = fgetcsv( $handle );
		if ( ! $headers ) {
			echo "❌ Error: Could not read CSV headers\n";
			fclose( $handle );
			return;
		}
		
		echo "📋 CSV Headers (" . count( $headers ) . " columns):\n";
		echo "   Core fields: " . implode( ', ', array_slice( $this->core_fields, 0, 5 ) ) . "...\n\n";
		
		// Verify posts
		$row_number = 1;
		$sample_posts = [];
		
		while ( ( $row = fgetcsv( $handle ) ) !== false ) {
			$row_number++;
			$this->results['total_rows']++;
			
			// Create associative array
			$post_data = array_combine( $headers, $row );
			
			if ( ! isset( $post_data['ID'] ) || empty( $post_data['ID'] ) ) {
				$this->results['errors'][] = "Row {$row_number}: Missing or empty ID";
				continue;
			}
			
			$post_id = (int) $post_data['ID'];
			
			// Verify this post
			$verification_result = $this->verify_post( $post_id, $post_data );
			
			if ( $verification_result['status'] === 'success' ) {
				$this->results['verified_posts']++;
			} elseif ( $verification_result['status'] === 'error' ) {
				$this->results['errors'][] = "Post ID {$post_id}: " . $verification_result['message'];
			} elseif ( $verification_result['status'] === 'warning' ) {
				$this->results['warnings'][] = "Post ID {$post_id}: " . $verification_result['message'];
			}
			
			// Track field mismatches
			if ( ! empty( $verification_result['mismatches'] ) ) {
				foreach ( $verification_result['mismatches'] as $field => $mismatch ) {
					if ( ! isset( $this->results['field_mismatches'][ $field ] ) ) {
						$this->results['field_mismatches'][ $field ] = 0;
					}
					$this->results['field_mismatches'][ $field ]++;
				}
			}
			
			// Store sample posts for detailed display
			if ( count( $sample_posts ) < 5 ) {
				$sample_posts[] = [
					'id'     => $post_id,
					'title'  => $post_data['post_title'],
					'result' => $verification_result,
				];
			}
			
			// Progress indicator
			if ( $this->results['total_rows'] % 50 === 0 ) {
				echo "📈 Progress: {$this->results['total_rows']} posts verified...\n";
			}
		}
		
		fclose( $handle );
		
		// Display results
		$this->display_results( $sample_posts );
	}
	
	/**
	 * Verify a single post
	 * 
	 * @param int   $post_id   Post ID
	 * @param array $csv_data  CSV row data
	 * @return array Verification result
	 */
	private function verify_post( $post_id, $csv_data ) {
		$result = [
			'status'     => 'success',
			'message'    => '',
			'mismatches' => [],
		];
		
		// Get post from database
		$post = get_post( $post_id );
		
		if ( ! $post ) {
			$result['status'] = 'error';
			$result['message'] = 'Post not found in database';
			return $result;
		}
		
		// Verify core fields
		foreach ( $this->core_fields as $field ) {
			if ( ! isset( $csv_data[ $field ] ) ) {
				continue;
			}
			
			$csv_value = $csv_data[ $field ];
			$db_value = isset( $post->$field ) ? $post->$field : '';
			
			// Normalize values for comparison
			$csv_value = $this->normalize_value( $csv_value );
			$db_value = $this->normalize_value( $db_value );
			
			if ( $csv_value !== $db_value ) {
				// Check if it's a minor difference (whitespace, line endings)
				if ( trim( $csv_value ) === trim( $db_value ) ) {
					// Minor whitespace difference - warning only
					continue;
				}
				
				$result['mismatches'][ $field ] = [
					'csv' => $this->truncate_string( $csv_value, 100 ),
					'db'  => $this->truncate_string( $db_value, 100 ),
				];
			}
		}
		
		// Verify author name
		if ( isset( $csv_data['author_name'] ) && ! empty( $csv_data['author_name'] ) ) {
			$author = get_userdata( $post->post_author );
			if ( $author ) {
				$db_author_name = $author->display_name;
				if ( $csv_data['author_name'] !== $db_author_name ) {
					$result['mismatches']['author_name'] = [
						'csv' => $csv_data['author_name'],
						'db'  => $db_author_name,
					];
				}
			}
		}
		
		// Verify featured image
		if ( isset( $csv_data['featured_image_id'] ) && ! empty( $csv_data['featured_image_id'] ) ) {
			$db_thumbnail_id = get_post_thumbnail_id( $post_id );
			if ( (int) $csv_data['featured_image_id'] !== $db_thumbnail_id ) {
				$result['mismatches']['featured_image_id'] = [
					'csv' => $csv_data['featured_image_id'],
					'db'  => $db_thumbnail_id,
				];
			}
		}
		
		// Verify taxonomies
		$this->verify_taxonomies( $post_id, $csv_data, $result );
		
		// Set status based on mismatches
		if ( ! empty( $result['mismatches'] ) ) {
			$result['status'] = 'warning';
			$result['message'] = count( $result['mismatches'] ) . ' field(s) mismatch';
		}
		
		return $result;
	}
	
	/**
	 * Verify taxonomies
	 * 
	 * @param int   $post_id Post ID
	 * @param array $csv_data CSV row data
	 * @param array &$result Result array (modified by reference)
	 */
	private function verify_taxonomies( $post_id, $csv_data, &$result ) {
		$taxonomies = [ 'category', 'post_tag' ];
		
		foreach ( $taxonomies as $taxonomy ) {
			$csv_field = 'taxonomy_' . $taxonomy;
			
			if ( ! isset( $csv_data[ $csv_field ] ) || empty( $csv_data[ $csv_field ] ) ) {
				continue;
			}
			
			// Get terms from CSV
			$csv_terms = array_map( 'trim', explode( ',', $csv_data[ $csv_field ] ) );
			sort( $csv_terms );
			
			// Get terms from database
			$db_terms = wp_get_post_terms( $post_id, $taxonomy, [ 'fields' => 'names' ] );
			if ( ! is_wp_error( $db_terms ) ) {
				sort( $db_terms );
				
				if ( $csv_terms !== $db_terms ) {
					$result['mismatches'][ $csv_field ] = [
						'csv' => implode( ', ', $csv_terms ),
						'db'  => implode( ', ', $db_terms ),
					];
				}
			}
		}
	}
	
	/**
	 * Normalize value for comparison
	 * 
	 * @param mixed $value Value to normalize
	 * @return string
	 */
	private function normalize_value( $value ) {
		if ( is_null( $value ) ) {
			return '';
		}
		
		$value = (string) $value;
		
		// Normalize line endings
		$value = str_replace( [ "\r\n", "\r" ], "\n", $value );
		
		// Normalize multiple spaces/tabs
		$value = preg_replace( '/[ \t]+/', ' ', $value );
		
		return $value;
	}
	
	/**
	 * Truncate string
	 * 
	 * @param string $str    String to truncate
	 * @param int    $length Maximum length
	 * @return string
	 */
	private function truncate_string( $str, $length ) {
		if ( strlen( $str ) <= $length ) {
			return $str;
		}
		
		return substr( $str, 0, $length ) . '...';
	}
	
	/**
	 * Format bytes
	 * 
	 * @param int $bytes Bytes
	 * @return string
	 */
	private function format_bytes( $bytes ) {
		$units = [ 'B', 'KB', 'MB', 'GB' ];
		$bytes = max( $bytes, 0 );
		$pow = floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
		$pow = min( $pow, count( $units ) - 1 );
		$bytes /= pow( 1024, $pow );
		
		return round( $bytes, 2 ) . ' ' . $units[ $pow ];
	}
	
	/**
	 * Display results
	 * 
	 * @param array $sample_posts Sample posts for detailed display
	 */
	private function display_results( $sample_posts ) {
		echo "\n\n";
		echo "═══════════════════════════════════════════════════════════════\n";
		echo "                    VERIFICATION RESULTS                       \n";
		echo "═══════════════════════════════════════════════════════════════\n\n";
		
		// Summary
		echo "📊 Summary:\n";
		echo "   Total rows in CSV: " . $this->results['total_rows'] . "\n";
		echo "   Successfully verified: " . $this->results['verified_posts'] . "\n";
		echo "   Errors: " . count( $this->results['errors'] ) . "\n";
		echo "   Warnings: " . count( $this->results['warnings'] ) . "\n\n";
		
		// Success rate
		$success_rate = $this->results['total_rows'] > 0
			? ( $this->results['verified_posts'] / $this->results['total_rows'] ) * 100
			: 0;
		
		if ( $success_rate >= 99 ) {
			echo "✅ Success rate: " . number_format( $success_rate, 2 ) . "%\n\n";
		} elseif ( $success_rate >= 90 ) {
			echo "⚠️  Success rate: " . number_format( $success_rate, 2 ) . "%\n\n";
		} else {
			echo "❌ Success rate: " . number_format( $success_rate, 2 ) . "%\n\n";
		}
		
		// Field mismatches summary
		if ( ! empty( $this->results['field_mismatches'] ) ) {
			echo "📋 Field Mismatches Summary:\n";
			arsort( $this->results['field_mismatches'] );
			foreach ( $this->results['field_mismatches'] as $field => $count ) {
				echo "   - {$field}: {$count} mismatches\n";
			}
			echo "\n";
		}
		
		// Display sample posts
		if ( ! empty( $sample_posts ) ) {
			echo "📝 Sample Post Verification (first 5):\n\n";
			foreach ( $sample_posts as $sample ) {
				$status_icon = $sample['result']['status'] === 'success' ? '✅' : 
				              ( $sample['result']['status'] === 'warning' ? '⚠️' : '❌' );
				
				echo "   {$status_icon} Post #{$sample['id']}: {$sample['title']}\n";
				
				if ( ! empty( $sample['result']['mismatches'] ) ) {
					echo "      Mismatches:\n";
					foreach ( $sample['result']['mismatches'] as $field => $values ) {
						echo "      - {$field}:\n";
						echo "        CSV: {$values['csv']}\n";
						echo "        DB:  {$values['db']}\n";
					}
				}
				echo "\n";
			}
		}
		
		// Display errors
		if ( ! empty( $this->results['errors'] ) ) {
			echo "❌ Errors (first 10):\n";
			$error_count = 0;
			foreach ( $this->results['errors'] as $error ) {
				echo "   - {$error}\n";
				$error_count++;
				if ( $error_count >= 10 ) {
					$remaining = count( $this->results['errors'] ) - 10;
					if ( $remaining > 0 ) {
						echo "   ... and {$remaining} more errors\n";
					}
					break;
				}
			}
			echo "\n";
		}
		
		// Display warnings
		if ( ! empty( $this->results['warnings'] ) && count( $this->results['warnings'] ) <= 10 ) {
			echo "⚠️  Warnings:\n";
			foreach ( $this->results['warnings'] as $warning ) {
				echo "   - {$warning}\n";
			}
			echo "\n";
		} elseif ( count( $this->results['warnings'] ) > 10 ) {
			echo "⚠️  Warnings: " . count( $this->results['warnings'] ) . " (too many to display)\n\n";
		}
		
		// Final verdict
		echo "═══════════════════════════════════════════════════════════════\n";
		if ( count( $this->results['errors'] ) === 0 && count( $this->results['warnings'] ) === 0 ) {
			echo "✅ VERDICT: Export is PERFECT! All data matches the database.\n";
		} elseif ( count( $this->results['errors'] ) === 0 ) {
			echo "⚠️  VERDICT: Export is GOOD with minor warnings.\n";
		} else {
			echo "❌ VERDICT: Export has ERRORS that need attention.\n";
		}
		echo "═══════════════════════════════════════════════════════════════\n";
	}
}

// Get CSV file path
$csv_file = __DIR__ . '/temp/export-2026-01-19-101909.csv';

if ( ! file_exists( $csv_file ) ) {
	echo "❌ Error: CSV file not found.\n";
	echo "Looking for: {$csv_file}\n\n";
	echo "Available files in temp directory:\n";
	$temp_files = glob( __DIR__ . '/temp/*.csv' );
	foreach ( $temp_files as $file ) {
		echo "  - " . basename( $file ) . "\n";
	}
	exit;
}

// Run verification
$verifier = new Export_Verifier( $csv_file );
$verifier->verify();

echo "\n✅ Verification complete!\n";
