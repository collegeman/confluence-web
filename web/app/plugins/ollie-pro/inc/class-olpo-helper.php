<?php

namespace olpo;

class Helper {
	/**
	 * Contains instance or null
	 *
	 * @var object|null
	 */
	private static $instance = null;

	/**
	 * Returns instance of Helper.
	 *
	 * @return object
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Setting up admin fields
	 *
	 * @return void
	 */
	public function __construct() {
		add_filter( 'wp_theme_json_data_theme', [ __CLASS__, 'filter_theme_json_data' ] );
	}

	/**
	 * Create child theme for Ollie.
	 *
	 * @return bool
	 */
	public static function create_child_theme( $data ) {
		// Prepare directories
		$child_theme_file_dir = OLPO_PATH . '/inc/child-theme';
		$ollie_dir            = get_template_directory();
		$ollie_child_dir      = str_replace( '/themes/ollie', '/themes/ollie-child', $ollie_dir );

		// Create directory
		if ( ! file_exists( $ollie_child_dir ) ) {
			wp_mkdir_p( $ollie_child_dir );
		}

		// Get the template style.css content
		$template_style = file_get_contents( $child_theme_file_dir . '/style.css' );
		if ( $template_style === false ) {
			return false;
		}

		// Replace the header information
		$header_replacements = array(
			'/Theme Name:.*/'  => 'Theme Name: ' . $data['themeName'],
			'/Theme URI:.*/'   => 'Theme URI: ' . ( ! empty( $data['themeUrl'] ) ? $data['themeUrl'] : 'https://olliewp.com' ),
			'/Description:.*/' => 'Description: ' . ( ! empty( $data['description'] ) ? $data['description'] : 'A child theme for Ollie.' ),
			'/Author:.*/'      => 'Author: ' . ( ! empty( $data['author'] ) ? $data['author'] : 'Mike McAlister' ),
			'/Author URI:.*/'  => 'Author URI: ' . ( ! empty( $data['authorUrl'] ) ? $data['authorUrl'] : 'https://olliewp.com' ),
			'/Version:.*/'     => 'Version: ' . ( ! empty( $data['version'] ) ? $data['version'] : '1.0.0' ),
			'/Text Domain:.*/' => 'Text Domain: ' . ( ! empty( $data['textDomain'] ) ? $data['textDomain'] : 'ollie-child' )
		);

		foreach ( $header_replacements as $pattern => $replacement ) {
			$template_style = preg_replace( $pattern, $replacement, $template_style );
		}

		// Write the modified style.css
		if ( file_put_contents( $ollie_child_dir . '/style.css', $template_style ) === false ) {
			return false;
		}

		// Copy other required files
		if ( ! copy( $child_theme_file_dir . '/screenshot.png', $ollie_child_dir . '/screenshot.png' ) ) {
			return false;
		}

		if ( ! copy( $child_theme_file_dir . '/functions.php', $ollie_child_dir . '/functions.php' ) ) {
			return false;
		}

		// Activate child theme
		switch_theme( 'ollie-child' );

		return true;
	}

	/**
	 * Create pages in WordPress.
	 *
	 * @param array $pages given list of pages.
	 *
	 * @return array
	 */
	public static function create_pages( $pages ) {
		$create_page_ids = [];

		foreach ( $pages as $page_slug ) {
			// Check if page exists.
			if ( ! get_page_by_path( $page_slug, OBJECT, [ 'page' ] ) ) {
				$proPrefix = [ 'agency', 'creator', 'startup', 'studio' ];
				$content   = '<!-- wp:pattern {"slug":"ollie/page-' . sanitize_title( $page_slug ) . '"} /-->';

				// Check if page_slug contains any of the pro prefixes.
				foreach ( $proPrefix as $prefix ) {
					if ( strpos( $page_slug, $prefix ) !== false ) {
						// Prepare the page slug.
						$page_slug = str_replace( '/', '-', $page_slug );

						// Get content from the pattern file if it exists
						$pattern_path = get_theme_file_path( 'patterns/' . $page_slug . '.php' );

						if ( file_exists( $pattern_path ) ) {
							$content = file_get_contents( $pattern_path );
							$content = preg_replace( '/<\?php.*?\?>/s', '', $content );
						}

						break;
					}
				}

				// Rework the title.
				$title = preg_replace( '/^[a-z]+-\d+-/', '', $page_slug ); // Remove prefix and number
				$title = str_replace( '-', ' ', $title ); // Replace remaining dashes with spaces
				$title = ucwords( $title ); // Capitalize words

				// Create page.
				$page_id = wp_insert_post(
					array(
						'post_author'  => 1,
						'post_title'   => $title,
						'post_name'    => sanitize_title( $page_slug ),
						'post_status'  => 'publish',
						'post_content' => $content,
						'post_type'    => 'page',
					)
				);

				$create_page_ids[ $page_slug ] = $page_id;

				// Update the page template.
				update_post_meta( $page_id, '_wp_page_template', 'page-no-title' );
			}
		}

		return $create_page_ids;
	}

	/**
	 * Install pattern from cloud into Ollie theme.
	 *
	 * @param $pattern
	 *
	 * @return bool
	 */
	public static function download_pattern( $pattern, $isDynamic = false ) {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return false;
		}

		// Verify pattern data
		if ( ! is_array( $pattern ) || empty( $pattern['slug'] ) ) {
			return false;
		}

		// Get pattern dir using WP Filesystem
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();
		global $wp_filesystem;

		$pattern_dir = wp_normalize_path( get_stylesheet_directory() . '/patterns/' );

		if ( ! $wp_filesystem->exists( $pattern_dir ) ) {
			wp_mkdir_p( $pattern_dir );
		}

		$sample_file  = wp_normalize_path( OLPO_PATH . '/inc/templates/pattern.php' );
		$pattern_slug = sanitize_file_name( str_replace( '/', '-', $pattern['slug'] ) );
		$pattern_file = $pattern_dir . $pattern_slug . '.php';

		// Create pattern in theme
		if ( ! $wp_filesystem->exists( $pattern_file ) ) {
			// Copy and rename sample file
			if ( ! $wp_filesystem->copy( $sample_file, $pattern_file, true ) ) {
				self::debug_log( 'Failed to download pattern: ' . $pattern_slug );

				return false;
			}

			// Prepare categories with sanitization
			$categories        = array_map( 'sanitize_text_field', array_unique( $pattern['categories'] ?? [] ) );
			$categories_string = '';
			foreach ( $categories as $category ) {
				$categories_string .= 'ollie/' . $category . ', ';
			}
			$categories_string = rtrim( $categories_string, ', ' );

			// Prepare placeholders with proper sanitization
			$placeholders = array(
				'[PATTERN_TITLE]'         => wp_kses_post( $pattern['title'] ?? '' ),
				'[PATTERN_SLUG]'          => $pattern_slug,
				'[PATTERN_CATEGORIES]'    => $categories_string,
				'[PATTERN_DESCRIPTION]'   => wp_kses_post( $pattern['description'] ?? '' ),
				'[PATTERN_KEYWORDS]'      => sanitize_text_field( $pattern['keywords'] ?? '' ),
				'[PATTERN_WIDTH]'         => sanitize_text_field( $pattern['width'] ?? '' ),
				'[PATTERN_CONTENT]'       => wp_kses( $pattern['content'], self::get_kses_extended_ruleset() ) ?? '',
				'[PATTERN_TEMPLATE_PART]' => ! empty( $pattern['template_part'] ) ?
					'core/template-part/' . sanitize_text_field( $pattern['template_part'] ) : ''
			);

			// Overwrite pattern content with dynamic_content.
			if ( $isDynamic ) {
				$placeholders['[PATTERN_CONTENT]'] = wp_kses( $pattern['dynamic_content'], self::get_kses_extended_ruleset() ) ?? '';
			}

			// Get and validate pattern content
			$pattern_content = $wp_filesystem->get_contents( $pattern_file );
			if ( $pattern_content === false ) {
				return false;
			}

			// Replace placeholders
			foreach ( $placeholders as $placeholder => $string ) {
				$pattern_content = str_replace( $placeholder, $string, $pattern_content );
			}

			// Write updated content
			if ( ! $wp_filesystem->put_contents( $pattern_file, $pattern_content, FS_CHMOD_FILE ) ) {
				return false;
			}

			return true;
		}

		return false;
	}

	/**
	 * Uninstall pattern from filesystem.
	 *
	 * @param $pattern
	 *
	 * @return false|int|\WP_Error
	 */
	public static function delete_pattern( $pattern ) {
		// Get Ollie theme dir.
		$pattern_dir  = get_stylesheet_directory() . '/patterns/';
		$pattern_slug = str_replace( '/', '-', $pattern['slug'] );
		$pattern_file = sanitize_file_name( $pattern_slug ) . '.php';

		if ( ! $pattern ) {
			return false;
		}

		// Delete the pattern file.
		if ( file_exists( $pattern_dir . $pattern_file ) ) {
			return wp_delete_file( $pattern_dir . $pattern_file );
		}

		return false;
	}

	/**
	 * Get installed pattern slugs.
	 *
	 * @return array
	 */
	public static function get_downloaded_patterns() {
		// Get Ollie theme dir.
		$pattern_dir         = get_stylesheet_directory() . '/patterns/';
		$downloaded_patterns = [];

		if ( ! file_exists( $pattern_dir ) ) {
			wp_mkdir_p( $pattern_dir );
		}

		$pattern_files = scandir( $pattern_dir );

		if ( is_array( $pattern_files ) ) {
			foreach ( $pattern_files as $pattern ) {
				if ( $pattern !== '.' && $pattern !== '..' ) {
					$downloaded_patterns[] = str_replace( '.php', '', $pattern );
				}
			}
		}

		return $downloaded_patterns;
	}

	/**
	 * Get favorite patterns.
	 *
	 * @return array
	 */
	public static function get_favorite_patterns() {
		return get_option( 'ollie_favorite_patterns', [] );
	}

	/**
	 * Log debug messages using WordPress's built-in logging
	 *
	 * @param mixed $message The message to log
	 *
	 * @return void
	 */
	private static function debug_log( $message ) {
		if ( ! current_user_can( 'manage_options' ) || ! defined( 'WP_DEBUG_LOG' ) || ! WP_DEBUG_LOG ) {
			return;
		}

		if ( is_array( $message ) || is_object( $message ) ) {
			$message = wp_json_encode( $message );
		}
	}

	/**
	 * Updates the global styles post with new typography settings
	 *
	 * @param string $style_name The name of the typography style to apply
	 *
	 * @return array Response array with success status and message
	 */
	public static function update_global_typography( $style_name ) {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return [
				'success' => false,
				'message' => 'Insufficient permissions'
			];
		}

		try {
			$style_name = sanitize_text_field( $style_name );

			// Special handling for typography-preset-0
			if ( $style_name === 'typography-preset-0' ) {
				$global_styles_post = get_posts( [
					'name'           => 'wp-global-styles-' . get_stylesheet(),
					'post_type'      => 'wp_global_styles',
					'posts_per_page' => 1,
					'post_status'    => [ 'publish', 'draft' ],
				] );

				if ( ! empty( $global_styles_post ) ) {
					$post           = $global_styles_post[0];
					$current_styles = json_decode( $post->post_content, true ) ?: [];

					// Remove typography settings
					if ( isset( $current_styles['settings']['typography'] ) ) {
						unset( $current_styles['settings']['typography'] );
					}
					if ( isset( $current_styles['styles']['typography'] ) ) {
						unset( $current_styles['styles']['typography'] );
					}
					if ( isset( $current_styles['styles']['elements'] ) ) {
						foreach ( $current_styles['styles']['elements'] as &$element ) {
							if ( isset( $element['typography'] ) ) {
								unset( $element['typography'] );
							}
						}
					}

					// Update the post content
					wp_update_post( [
						'ID'           => $post->ID,
						'post_content' => wp_json_encode( $current_styles )
					] );

					// Clear caches
					wp_cache_delete( 'global_styles_' . get_stylesheet(), 'global_styles' );
					delete_transient( 'global_styles' );
					delete_transient( 'global_styles_' . get_stylesheet() );
				}

				return [
					'success' => true,
					'message' => 'Typography reset to theme defaults'
				];
			}

			// Regular handling for other typography presets
			$variation_file = wp_normalize_path( get_template_directory() . '/styles/typography/' . $style_name . '.json' );

			// Validate file path
			if ( strpos( $variation_file, wp_normalize_path( get_template_directory() ) ) !== 0 ) {
				return [
					'success' => false,
					'message' => 'Invalid file path'
				];
			}

			if ( ! file_exists( $variation_file ) ) {
				return [
					'success' => false,
					'message' => 'Typography style file not found'
				];
			}

			// Use WP Filesystem for file operations
			if ( ! function_exists( 'WP_Filesystem' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}
			WP_Filesystem();
			global $wp_filesystem;

			// Read and validate JSON
			$variation_content = $wp_filesystem->get_contents( $variation_file );
			if ( $variation_content === false ) {
				return [
					'success' => false,
					'message' => 'Failed to read typography file'
				];
			}

			$variation_data = json_decode( $variation_content, true );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				return [
					'success' => false,
					'message' => 'Invalid JSON in typography file'
				];
			}

			// Validate JSON structure
			if ( ! isset( $variation_data['settings'] ) || ! isset( $variation_data['styles'] ) ) {
				return [
					'success' => false,
					'message' => 'Invalid typography data structure'
				];
			}

			// Get or create global styles post
			$result = self::get_or_create_global_styles_post();
			if ( ! $result ) {
				return [
					'success' => false,
					'message' => 'Failed to get or create global styles post'
				];
			}

			$post           = $result['post'];
			$current_styles = $result['styles'];

			// Update typography settings
			if ( isset( $variation_data['settings']['typography'] ) ) {
				$current_styles['settings']['typography'] = $variation_data['settings']['typography'];
			}

			// Update typography styles
			if ( isset( $variation_data['styles'] ) ) {
				// Global typography styles
				if ( isset( $variation_data['styles']['typography'] ) ) {
					$current_styles['styles']['typography'] = $variation_data['styles']['typography'];
				}

				// Element-specific typography styles
				if ( isset( $variation_data['styles']['elements'] ) ) {
					if ( ! isset( $current_styles['styles']['elements'] ) ) {
						$current_styles['styles']['elements'] = [];
					}
					foreach ( $variation_data['styles']['elements'] as $element => $styles ) {
						$current_styles['styles']['elements'][ $element ] = $styles;
					}
				}
			}

			// Update the post content
			$result = wp_update_post( [
				'ID'           => $post->ID,
				'post_content' => wp_json_encode( $current_styles )
			] );

			if ( is_wp_error( $result ) ) {
				return [
					'success' => false,
					'message' => 'Failed to update global styles post'
				];
			}

			// Clear caches
			wp_cache_delete( 'global_styles_' . get_stylesheet(), 'global_styles' );
			delete_transient( 'global_styles' );
			delete_transient( 'global_styles_' . get_stylesheet() );

			return [
				'success' => true,
				'message' => 'Typography updated successfully'
			];

		} catch ( Exception $e ) {
			self::debug_log( 'Typography update error: ' . $e->getMessage() );

			return [
				'success' => false,
				'message' => 'Internal server error'
			];
		}
	}

	/**
	 * Modifies the theme JSON data by updating theme styles and settings.
	 * Now only handles color settings as typography is handled via global styles post.
	 */
	public static function filter_theme_json_data( $theme_json ) {
		$settings = get_option( 'ollie', [] );
		$data     = $theme_json->get_data();

		// Only filter if brand_color or color_palette settings exist
		if ( isset( $settings['brand_color'] ) && ! empty( $settings['brand_color'] ) ||
		     isset( $settings['style'] ) && ! empty( $settings['style'] ) ) {
			$data['settings']['color']['palette'] = self::handle_color_settings( $data, $settings );

			return $theme_json->update_with( $data );
		}

		return $theme_json;
	}

	/**
	 * Removes color values from global styles
	 *
	 * @return array Response array with success status and message
	 */
	public static function remove_color_values() {
		// Get the global styles post
		$result = self::get_or_create_global_styles_post();
		if ( ! $result ) {
			return [
				'success' => false,
				'message' => 'Failed to get or create global styles post'
			];
		}

		$post           = $result['post'];
		$current_styles = $result['styles'];

		// Remove color settings from the global styles
		if ( isset( $current_styles['settings']['color'] ) ) {
			unset( $current_styles['settings']['color'] );
		}

		// Update the post content
		wp_update_post( [
			'ID'           => $post->ID,
			'post_content' => wp_json_encode( $current_styles )
		] );

		// Clean all theme.json related caches
		wp_clean_theme_json_cache();

		return [
			'success' => true,
			'message' => 'Color values removed successfully'
		];
	}

	/**
	 * Handles color palette settings for theme.json
	 *
	 * @param array $data Current theme.json data
	 * @param array $settings Ollie settings
	 *
	 * @return array Updated color palette
	 */
	private static function handle_color_settings( $data, $settings ) {
		// If brand color and custom palette are set, use the custom palette
		if ( isset( $settings['brand_color'] ) && ! empty( $settings['brand_color'] ) &&
		     isset( $settings['color_palette'] ) && ! empty( $settings['color_palette'] ) ) {
			return $settings['color_palette'];
		}

		// Otherwise use the selected style's palette
		$style      = isset( $settings['style'] ) ? $settings['style'] : 'purple';
		$style_file = get_template_directory() . '/styles/colors/' . $style . '.json';

		// If style is 'purple', use the main theme.json
		if ( $style === 'purple' ) {
			$style_file = get_template_directory() . '/theme.json';
		}

		if ( file_exists( $style_file ) ) {
			$style_json = json_decode( file_get_contents( $style_file ), true );

			return $style_json['settings']['color']['palette'];
		}

		return [];
	}

	/**
	 * Gets or creates the global styles post
	 *
	 * @return array|false Array with post and styles data, or false on failure
	 */
	private static function get_or_create_global_styles_post() {
		// Get the latest global styles post
		$global_styles_post = get_posts( [
			'post_type'      => 'wp_global_styles',
			'posts_per_page' => 1,
			'orderby'        => 'ID',
			'order'          => 'DESC',
			'post_status'    => [ 'publish', 'draft' ],
		] );

		// If no global styles post exists, create one
		if ( empty( $global_styles_post ) ) {
			$post_content = array(
				'version'                     => 2,
				'isGlobalStylesUserThemeJSON' => true
			);

			$post_id = wp_insert_post( array(
				'post_content' => wp_json_encode( $post_content ),
				'post_status'  => 'publish',
				'post_title'   => 'Custom Styles',
				'post_type'    => 'wp_global_styles',
				'post_name'    => sprintf( 'wp-global-styles-%s', urlencode( get_stylesheet() ) ),
				'tax_input'    => array(
					'wp_theme' => array( get_stylesheet() )
				)
			), true );

			if ( is_wp_error( $post_id ) ) {
				return false;
			}

			$post = get_post( $post_id );
		} else {
			$post = $global_styles_post[0];
		}

		// Get current styles content
		$current_styles = json_decode( $post->post_content, true ) ?: [];

		return [
			'post'   => $post,
			'styles' => $current_styles
		];
	}

	public static function get_kses_extended_ruleset() {
		$kses_defaults = wp_kses_allowed_html( 'post' );

		$svg_args = array(
			'svg'   => array(
				'class'           => true,
				'aria-hidden'     => true,
				'aria-labelledby' => true,
				'role'            => true,
				'xmlns'           => true,
				'width'           => true,
				'height'          => true,
				'focusable'       => true,
				'fill'            => true,
				'viewbox'         => true,
			),
			'g'     => array( 'fill' => true ),
			'title' => array( 'title' => true ),
			'path'  => array(
				'd'         => true,
				'fill'      => true,
				'fill-rule' => true,
				'clip-rule' => true,
			),
		);

		return array_merge( $kses_defaults, $svg_args );
	}

	/**
	 * Resets all template and template part customizations.
	 *
	 * @return array Response array with success status and message
	 */
	public static function reset_templates() {
		// Check if user has permission
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return new \WP_Error( 'permission_denied', __( 'You do not have permission to reset templates.', 'ollie-pro' ) );
		}

		global $wpdb;

		try {
			// Delete only template and template part posts
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->posts} 
					WHERE post_type IN (%s, %s)",
					'wp_template',
					'wp_template_part'
				)
			);

			return array(
				'success' => true,
				'message' => __( 'Templates reset successfully.', 'ollie-pro' )
			);
		} catch ( \Exception $e ) {
			return new \WP_Error( 'reset_failed', __( 'Failed to reset templates.', 'ollie-pro' ) );
		}
	}

	public static function reset_global_styles() {
		// Check if user has permission
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return new \WP_Error( 'permission_denied', __( 'You do not have permission to reset global styles.', 'ollie-pro' ) );
		}

		global $wpdb;

		try {
			// Delete only global styles posts
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->posts} 
					WHERE post_type = %s",
					'wp_global_styles'
				)
			);

			return array(
				'success' => true,
				'message' => __( 'Global styles reset successfully.', 'ollie-pro' )
			);
		} catch ( \Exception $e ) {
			return new \WP_Error( 'reset_failed', __( 'Failed to reset global styles.', 'ollie-pro' ) );
		}
	}


	/**
	 * Maybe get credentials for auto-login.
	 * @return array
	 */
	public static function get_credentials() {
		// Check if credentials are defined via config.
		$email    = defined( 'OLLIE_EMAIL' ) ? OLLIE_EMAIL : '';
		$password = defined( 'OLLIE_PASSWORD' ) ? OLLIE_PASSWORD : '';

		// Check if credentials are stored in DB.
		$options = get_option( 'ollie' );

		if ( isset( $options['login'] ) && $options['login'] ) {
			$credentials = explode( ':', base64_decode( $options['login'] ) );
			$email       = $credentials[0];
			$password    = $credentials[1];
		}

		return [
			'email'    => $email,
			'password' => $password,
		];
	}
}

