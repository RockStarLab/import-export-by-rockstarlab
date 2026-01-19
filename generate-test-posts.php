<?php
/**
 * Generate Test Posts Script
 * 
 * Creates 500 test posts with random Gutenberg content, images, galleries,
 * tags, categories, featured images, comments, authors, and dates.
 * 
 * Usage: wp-cli eval-file generate-test-posts.php
 * Or: php -d memory_limit=512M generate-test-posts.php
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
		die( "Could not load WordPress. Please run this script using WP-CLI:\nwp eval-file generate-test-posts.php\n" );
	}
}

/**
 * Test Post Generator Class
 */
class Test_Post_Generator {
	
	/**
	 * Number of posts to generate
	 */
	const POSTS_COUNT = 500;
	
	/**
	 * Media attachment IDs
	 */
	private $media_ids = [];
	
	/**
	 * Category IDs
	 */
	private $category_ids = [];
	
	/**
	 * Tag IDs
	 */
	private $tag_ids = [];
	
	/**
	 * User IDs
	 */
	private $user_ids = [];
	
	/**
	 * Gutenberg block types
	 */
	private $block_types = [
		'paragraph',
		'heading',
		'image',
		'gallery',
		'quote',
		'list',
		'separator',
	];
	
	/**
	 * Sample words for content generation
	 */
	private $sample_words = [
		'lorem', 'ipsum', 'dolor', 'sit', 'amet', 'consectetur', 'adipiscing', 'elit',
		'sed', 'do', 'eiusmod', 'tempor', 'incididunt', 'ut', 'labore', 'et', 'dolore',
		'magna', 'aliqua', 'enim', 'ad', 'minim', 'veniam', 'quis', 'nostrud',
		'exercitation', 'ullamco', 'laboris', 'nisi', 'aliquip', 'ex', 'ea', 'commodo',
		'consequat', 'duis', 'aute', 'irure', 'in', 'reprehenderit', 'voluptate',
		'velit', 'esse', 'cillum', 'fugiat', 'nulla', 'pariatur', 'excepteur',
		'sint', 'occaecat', 'cupidatat', 'non', 'proident', 'sunt', 'culpa', 'qui',
		'officia', 'deserunt', 'mollit', 'anim', 'id', 'est', 'laborum',
	];
	
	/**
	 * Run the generator
	 */
	public function run() {
		echo "🚀 Starting test post generation...\n\n";
		
		// Initialize data
		$this->load_media_ids();
		$this->load_or_create_categories();
		$this->load_or_create_tags();
		$this->load_user_ids();
		
		echo "📊 Data loaded:\n";
		echo "   - Media items: " . count( $this->media_ids ) . "\n";
		echo "   - Categories: " . count( $this->category_ids ) . "\n";
		echo "   - Tags: " . count( $this->tag_ids ) . "\n";
		echo "   - Users: " . count( $this->user_ids ) . "\n\n";
		
		// Generate posts
		$success_count = 0;
		$error_count = 0;
		
		for ( $i = 1; $i <= self::POSTS_COUNT; $i++ ) {
			echo "Creating post {$i}/" . self::POSTS_COUNT . "... ";
			
			$result = $this->create_test_post( $i );
			
			if ( is_wp_error( $result ) ) {
				echo "❌ Error: " . $result->get_error_message() . "\n";
				$error_count++;
			} else {
				echo "✅ Created (ID: {$result})\n";
				$success_count++;
			}
			
			// Progress indicator every 50 posts
			if ( $i % 50 === 0 ) {
				echo "\n📈 Progress: {$i}/" . self::POSTS_COUNT . " ({$success_count} successful, {$error_count} errors)\n\n";
			}
		}
		
		echo "\n✨ Generation complete!\n";
		echo "   - Successful: {$success_count}\n";
		echo "   - Errors: {$error_count}\n";
		echo "   - Total: " . self::POSTS_COUNT . "\n";
	}
	
	/**
	 * Load media attachment IDs
	 */
	private function load_media_ids() {
		$attachments = get_posts( [
			'post_type'      => 'attachment',
			'post_mime_type' => 'image',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		] );
		
		$this->media_ids = $attachments;
	}
	
	/**
	 * Load or create categories
	 */
	private function load_or_create_categories() {
		$categories = get_categories( [
			'hide_empty' => false,
			'fields'     => 'ids',
		] );
		
		// Create some test categories if needed
		if ( count( $categories ) < 10 ) {
			$category_names = [
				'Technology', 'Travel', 'Food', 'Fashion', 'Health',
				'Sports', 'Entertainment', 'Business', 'Education', 'Lifestyle',
			];
			
			foreach ( $category_names as $name ) {
				$term = term_exists( $name, 'category' );
				if ( ! $term ) {
					$result = wp_insert_term( $name, 'category' );
					if ( ! is_wp_error( $result ) ) {
						$categories[] = $result['term_id'];
					}
				} else {
					$categories[] = $term['term_id'];
				}
			}
		}
		
		$this->category_ids = $categories;
	}
	
	/**
	 * Load or create tags
	 */
	private function load_or_create_tags() {
		$tags = get_tags( [
			'hide_empty' => false,
			'fields'     => 'ids',
		] );
		
		// Create some test tags if needed
		if ( count( $tags ) < 20 ) {
			$tag_names = [
				'tutorial', 'guide', 'tips', 'news', 'review', 'howto',
				'best', 'top', 'latest', 'trending', 'featured', 'popular',
				'beginner', 'advanced', 'pro', 'expert', 'quick', 'easy',
				'ultimate', 'complete',
			];
			
			foreach ( $tag_names as $name ) {
				$term = term_exists( $name, 'post_tag' );
				if ( ! $term ) {
					$result = wp_insert_term( $name, 'post_tag' );
					if ( ! is_wp_error( $result ) ) {
						$tags[] = $result['term_id'];
					}
				} else {
					$tags[] = $term['term_id'];
				}
			}
		}
		
		$this->tag_ids = $tags;
	}
	
	/**
	 * Load user IDs
	 */
	private function load_user_ids() {
		$users = get_users( [
			'fields' => 'ids',
		] );
		
		$this->user_ids = $users;
	}
	
	/**
	 * Create a test post
	 * 
	 * @param int $index Post index
	 * @return int|WP_Error Post ID or error
	 */
	private function create_test_post( $index ) {
		// Generate random date between 2000 and 2026
		$start_date = strtotime( '2000-01-01' );
		$end_date = strtotime( '2026-12-31' );
		$random_timestamp = mt_rand( $start_date, $end_date );
		$post_date = date( 'Y-m-d H:i:s', $random_timestamp );
		
		// Random author
		$author_id = ! empty( $this->user_ids ) 
			? $this->user_ids[ array_rand( $this->user_ids ) ] 
			: 1;
		
		// Generate title
		$title = $this->generate_title( $index );
		
		// Generate Gutenberg content
		$content = $this->generate_gutenberg_content();
		
		// Create post
		$post_data = [
			'post_title'   => $title,
			'post_content' => $content,
			'post_status'  => 'publish',
			'post_author'  => $author_id,
			'post_date'    => $post_date,
			'post_type'    => 'post',
		];
		
		$post_id = wp_insert_post( $post_data, true );
		
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}
		
		// Set featured image
		if ( ! empty( $this->media_ids ) ) {
			$featured_image_id = $this->media_ids[ array_rand( $this->media_ids ) ];
			set_post_thumbnail( $post_id, $featured_image_id );
		}
		
		// Assign random categories (2-5)
		if ( ! empty( $this->category_ids ) ) {
			$num_categories = mt_rand( 2, min( 5, count( $this->category_ids ) ) );
			$selected_categories = array_rand( 
				array_flip( $this->category_ids ), 
				$num_categories 
			);
			wp_set_post_categories( $post_id, (array) $selected_categories );
		}
		
		// Assign random tags (3-8)
		if ( ! empty( $this->tag_ids ) ) {
			$num_tags = mt_rand( 3, min( 8, count( $this->tag_ids ) ) );
			$selected_tags = array_rand( 
				array_flip( $this->tag_ids ), 
				$num_tags 
			);
			wp_set_post_tags( $post_id, (array) $selected_tags );
		}
		
		// Add random comments (10-100)
		$num_comments = mt_rand( 10, 100 );
		$this->create_comments( $post_id, $num_comments );
		
		return $post_id;
	}
	
	/**
	 * Generate post title
	 * 
	 * @param int $index Post index
	 * @return string
	 */
	private function generate_title( $index ) {
		$prefixes = [
			'How to', 'The Ultimate Guide to', 'Top 10', 'Best Ways to',
			'Complete Guide to', 'Introduction to', 'Mastering', 'Understanding',
			'Essential Tips for', 'Advanced Techniques for',
		];
		
		$topics = [
			'WordPress Development', 'Content Marketing', 'SEO Optimization',
			'Web Design', 'Digital Strategy', 'User Experience', 'Performance',
			'Security', 'Analytics', 'Conversion', 'Engagement', 'Growth',
		];
		
		$prefix = $prefixes[ array_rand( $prefixes ) ];
		$topic = $topics[ array_rand( $topics ) ];
		
		return "{$prefix} {$topic} - Test Post #{$index}";
	}
	
	/**
	 * Generate Gutenberg block content
	 * 
	 * @return string
	 */
	private function generate_gutenberg_content() {
		$content = '';
		$num_blocks = mt_rand( 5, 15 );
		
		for ( $i = 0; $i < $num_blocks; $i++ ) {
			$block_type = $this->block_types[ array_rand( $this->block_types ) ];
			$content .= $this->generate_block( $block_type );
		}
		
		return $content;
	}
	
	/**
	 * Generate a single Gutenberg block
	 * 
	 * @param string $type Block type
	 * @return string
	 */
	private function generate_block( $type ) {
		switch ( $type ) {
			case 'paragraph':
				return $this->generate_paragraph_block();
			
			case 'heading':
				return $this->generate_heading_block();
			
			case 'image':
				return $this->generate_image_block();
			
			case 'gallery':
				return $this->generate_gallery_block();
			
			case 'quote':
				return $this->generate_quote_block();
			
			case 'list':
				return $this->generate_list_block();
			
			case 'separator':
				return "<!-- wp:separator -->\n<hr class=\"wp-block-separator has-alpha-channel-opacity\"/>\n<!-- /wp:separator -->\n\n";
			
			default:
				return $this->generate_paragraph_block();
		}
	}
	
	/**
	 * Generate paragraph block
	 * 
	 * @return string
	 */
	private function generate_paragraph_block() {
		$text = $this->generate_random_text( mt_rand( 15, 40 ) );
		return "<!-- wp:paragraph -->\n<p>{$text}</p>\n<!-- /wp:paragraph -->\n\n";
	}
	
	/**
	 * Generate heading block
	 * 
	 * @return string
	 */
	private function generate_heading_block() {
		$level = mt_rand( 2, 4 );
		$text = $this->generate_random_text( mt_rand( 3, 8 ) );
		$text = ucwords( $text );
		return "<!-- wp:heading {\"level\":{$level}} -->\n<h{$level}>{$text}</h{$level}>\n<!-- /wp:heading -->\n\n";
	}
	
	/**
	 * Generate image block
	 * 
	 * @return string
	 */
	private function generate_image_block() {
		if ( empty( $this->media_ids ) ) {
			return '';
		}
		
		$image_id = $this->media_ids[ array_rand( $this->media_ids ) ];
		$image_url = wp_get_attachment_url( $image_id );
		$image_meta = wp_get_attachment_metadata( $image_id );
		
		if ( ! $image_url ) {
			return '';
		}
		
		$width = isset( $image_meta['width'] ) ? $image_meta['width'] : 1024;
		$height = isset( $image_meta['height'] ) ? $image_meta['height'] : 768;
		
		return "<!-- wp:image {\"id\":{$image_id},\"sizeSlug\":\"large\"} -->\n" .
		       "<figure class=\"wp-block-image size-large\">" .
		       "<img src=\"{$image_url}\" alt=\"\" class=\"wp-image-{$image_id}\"/>" .
		       "</figure>\n<!-- /wp:image -->\n\n";
	}
	
	/**
	 * Generate gallery block
	 * 
	 * @return string
	 */
	private function generate_gallery_block() {
		if ( empty( $this->media_ids ) ) {
			return '';
		}
		
		$num_images = mt_rand( 3, min( 6, count( $this->media_ids ) ) );
		$selected_ids = [];
		$images_html = '';
		
		for ( $i = 0; $i < $num_images; $i++ ) {
			$image_id = $this->media_ids[ array_rand( $this->media_ids ) ];
			$selected_ids[] = $image_id;
			$image_url = wp_get_attachment_url( $image_id );
			
			if ( $image_url ) {
				$images_html .= "<figure class=\"wp-block-image size-large\">" .
				               "<img src=\"{$image_url}\" alt=\"\" class=\"wp-image-{$image_id}\"/>" .
				               "</figure>";
			}
		}
		
		$ids_json = wp_json_encode( $selected_ids );
		
		return "<!-- wp:gallery {\"ids\":{$ids_json},\"linkTo\":\"none\"} -->\n" .
		       "<figure class=\"wp-block-gallery has-nested-images columns-default is-cropped\">\n" .
		       $images_html .
		       "</figure>\n<!-- /wp:gallery -->\n\n";
	}
	
	/**
	 * Generate quote block
	 * 
	 * @return string
	 */
	private function generate_quote_block() {
		$text = $this->generate_random_text( mt_rand( 10, 20 ) );
		$author = ucwords( $this->generate_random_text( 2 ) );
		
		return "<!-- wp:quote -->\n" .
		       "<blockquote class=\"wp-block-quote\">" .
		       "<p>{$text}</p>" .
		       "<cite>{$author}</cite>" .
		       "</blockquote>\n<!-- /wp:quote -->\n\n";
	}
	
	/**
	 * Generate list block
	 * 
	 * @return string
	 */
	private function generate_list_block() {
		$num_items = mt_rand( 3, 7 );
		$items = '';
		
		for ( $i = 0; $i < $num_items; $i++ ) {
			$text = ucfirst( $this->generate_random_text( mt_rand( 4, 10 ) ) );
			$items .= "<li>{$text}</li>";
		}
		
		return "<!-- wp:list -->\n<ul>{$items}</ul>\n<!-- /wp:list -->\n\n";
	}
	
	/**
	 * Generate random text
	 * 
	 * @param int $word_count Number of words
	 * @return string
	 */
	private function generate_random_text( $word_count ) {
		$words = [];
		
		for ( $i = 0; $i < $word_count; $i++ ) {
			$words[] = $this->sample_words[ array_rand( $this->sample_words ) ];
		}
		
		$text = implode( ' ', $words );
		
		// Capitalize first letter
		$text = ucfirst( $text );
		
		// Add period at the end
		if ( ! in_array( substr( $text, -1 ), [ '.', '!', '?' ] ) ) {
			$text .= '.';
		}
		
		return $text;
	}
	
	/**
	 * Create comments for a post
	 * 
	 * @param int $post_id    Post ID
	 * @param int $num_comments Number of comments to create
	 */
	private function create_comments( $post_id, $num_comments ) {
		$author_names = [
			'John Smith', 'Jane Doe', 'Mike Johnson', 'Sarah Williams', 'David Brown',
			'Emily Davis', 'Chris Wilson', 'Amanda Taylor', 'Ryan Anderson', 'Lisa Martinez',
		];
		
		for ( $i = 0; $i < $num_comments; $i++ ) {
			// Random comment date (between post date and now, or just after post date)
			$post_date = get_the_date( 'Y-m-d H:i:s', $post_id );
			$post_timestamp = strtotime( $post_date );
			$now_timestamp = time();
			
			// If post is in the future, set comment dates between post date and up to 1 year after
			if ( $post_timestamp > $now_timestamp ) {
				$end_timestamp = min( $post_timestamp + ( 365 * 24 * 60 * 60 ), strtotime( '2026-12-31' ) );
				$comment_timestamp = mt_rand( $post_timestamp, $end_timestamp );
			} else {
				$comment_timestamp = mt_rand( $post_timestamp, $now_timestamp );
			}
			$comment_date = date( 'Y-m-d H:i:s', $comment_timestamp );
			
			// Random author
			$author_name = $author_names[ array_rand( $author_names ) ];
			$author_email = strtolower( str_replace( ' ', '.', $author_name ) ) . '@example.com';
			
			// Random comment text
			$comment_text = $this->generate_random_text( mt_rand( 10, 30 ) );
			
			// Random approval status (mostly approved)
			$approved = mt_rand( 1, 100 ) <= 90 ? 1 : 0;
			
			$comment_data = [
				'comment_post_ID'      => $post_id,
				'comment_author'       => $author_name,
				'comment_author_email' => $author_email,
				'comment_author_url'   => '',
				'comment_content'      => $comment_text,
				'comment_date'         => $comment_date,
				'comment_approved'     => $approved,
				'comment_type'         => 'comment',
			];
			
			// Maybe assign to a user
			if ( ! empty( $this->user_ids ) && mt_rand( 1, 100 ) <= 30 ) {
				$comment_data['user_id'] = $this->user_ids[ array_rand( $this->user_ids ) ];
			}
			
			wp_insert_comment( $comment_data );
		}
	}
}

// Run the generator
$generator = new Test_Post_Generator();
$generator->run();

echo "\n✅ Done!\n";
