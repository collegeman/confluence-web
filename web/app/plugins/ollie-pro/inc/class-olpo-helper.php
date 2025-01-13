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
		add_action( 'wp_head', array( $this, 'add_favicon' ) );
		add_filter( 'wp_theme_json_data_theme', array( $this, 'set_theme_styles' ) );
	}

	/**
	 * Add favicon.
	 *
	 * @return void
	 */
	public function add_favicon() {
		$options = get_option( 'ollie' );
		?>

		<?php if ( isset( $options['site_icon'] ) && $options['site_icon'] != '' ): ?>
            <link rel="shortcut icon" href="<?php echo esc_url( $options['site_icon'] ); ?>">
		<?php endif; ?>
		<?php
	}

	/**
	 * Create child theme for Ollie.
	 *
	 * @return void
	 */
	public static function create_child_theme() {
		// Prepare directories.
		$child_theme_file_dir = OLPO_PATH . '/inc/child-theme';
		$ollie_dir            = get_template_directory();
		$ollie_child_dir      = str_replace( '/themes/ollie', '/themes/ollie-child', $ollie_dir );

		// Create directory.
		if ( ! file_exists( $ollie_child_dir ) ) {
			wp_mkdir_p( $ollie_child_dir );
		}

		// Copy CSS file.
		if ( ! copy( $child_theme_file_dir . '/style.css', $ollie_child_dir . '/style.css' ) ) {
			error_log( 'Failed to copy style.css.' );
		}

		// Copy screenshot.
		if ( ! copy( $child_theme_file_dir . '/screenshot.png', $ollie_child_dir . '/screenshot.png' ) ) {
			error_log( 'Failed to copy screenshot.png.' );
		}

		// Copy functions.php file.
		if ( ! copy( $child_theme_file_dir . '/functions.php', $ollie_child_dir . '/functions.php' ) ) {
			error_log( 'Failed to copy functions.php.' );
		}

		// Activate child theme.
		switch_theme( 'ollie-child' );
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
				// Create page.
				$page_id = wp_insert_post(
					array(
						'post_author'  => 1,
						'post_title'   => 'Ollie ' . ucwords( sanitize_title( $page_slug ) ),
						'post_name'    => sanitize_title( $page_slug ),
						'post_status'  => 'publish',
						'post_content' => '<!-- wp:pattern {"slug":"ollie/page-' . sanitize_title( $page_slug ) . '"} /-->',
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
	 * This function modifies the theme JSON data by updating the theme's color
	 * palette and brand color.
	 *
	 * @param object $theme_json The original theme JSON data.
	 *
	 * @return object The modified theme JSON data.
	 */
	public function set_theme_styles( $theme_json ) {
		$settings    = get_option( 'ollie', [] );
		$ollie_style = json_decode( file_get_contents( get_template_directory() . '/theme.json' ) );

		// Check if there is a child theme and a theme.json file skip applying modifications.
		if ( get_template_directory() !== get_stylesheet_directory() ) {
			$child_theme_json = get_stylesheet_directory() . '/theme.json';

			if ( file_exists( $child_theme_json ) ) {
				// Don't apply the filter.
				return $theme_json;
			}
		}

		if ( isset( $settings['style'] ) ) {
			if ( 'purple' === $settings['style'] ) {
				$ollie_style = json_decode( file_get_contents( get_template_directory() . '/theme.json' ) );
			} else {
				$ollie_style = json_decode( file_get_contents( get_template_directory() . '/styles/' . $settings['style'] . '.json' ) );
			}
		}

		$ollie_palette = $ollie_style->settings->color->palette;

		// Change brand color.
		if ( isset( $settings['brand_color'] ) && $settings['brand_color'] != '' ) {
			$ollie_palette[0]->color = wp_strip_all_tags( $settings['brand_color'] );
		}

		// Convert values for the filter.
		foreach ( $ollie_palette as $key => $value ) {
			$ollie_palette[ $key ] = (array) $value;
		}

		$new_data = array(
			'version'  => 2,
			'settings' => array(
				'color' => array(
					'palette' => $ollie_palette,
				),
			),
		);

		// Return the modified theme JSON data.
		return $theme_json->update_with( $new_data );
	}

	/**
	 * Install pattern from cloud into Ollie theme.
	 *
	 * @param $pattern
	 *
	 * @return bool
	 */
	public static function download_pattern( $pattern ) {
		// Get pattern dir
		$pattern_dir = get_stylesheet_directory() . '/patterns/';

		if ( ! file_exists( $pattern_dir ) ) {
			wp_mkdir_p( $pattern_dir );
		}

		$sample_file  = OLPO_PATH . '/inc/templates/pattern.php';
		$pattern_slug = str_replace( '/', '-', $pattern['slug'] );
		$pattern_file = sanitize_file_name( $pattern_slug ) . '.php';

		if ( ! $pattern ) {
			return false;
		}

		// Create pattern in theme.
		if ( ! file_exists( $pattern_dir . $pattern_file ) ) {

			// Copy and rename sample file.
			if ( ! copy( $sample_file, $pattern_dir . $pattern_file ) ) {
				error_log( 'Failed to download the pattern - It already exists in the theme.' );
			}

			// Replace placeholders with pattern data.
			$pattern_content = file_get_contents( $pattern_dir . $pattern_file );

			// Prepare categories.
			$categories            = '';
			$pattern['categories'] = array_unique( $pattern['categories'] );

			foreach ( $pattern['categories'] as $category ) {
				$categories .= 'ollie/' . $category . ', ';
			}

			$categories = rtrim( $categories, ', ' );

			$placeholders = array(
				'[PATTERN_TITLE]'       => wp_kses_post( $pattern['title'] ),
				'[PATTERN_SLUG]'        => sanitize_title( $pattern_slug ),
				'[PATTERN_CATEGORIES]'  => sanitize_text_field( $categories ),
				'[PATTERN_DESCRIPTION]' => sanitize_text_field( $pattern['description'] ),
				'[PATTERN_KEYWORDS]'    => sanitize_text_field( $pattern['keywords'] ),
				'[PATTERN_WIDTH]'       => sanitize_text_field( $pattern['width'] ),
				'[PATTERN_CONTENT]'     => wp_kses_post( $pattern['content'] ),
			);

			if ( ! empty( $pattern['template_part'] ) ) {
				$placeholders['[PATTERN_TEMPLATE_PART]'] = 'core/template-part/' . sanitize_text_field( $pattern['template_part'] );
			} else {
				$placeholders['[PATTERN_TEMPLATE_PART]'] = '';
			}

			foreach ( $placeholders as $placeholder => $string ) {
				$pattern_content = str_replace( $placeholder, $string, $pattern_content );
			}

			file_put_contents( $pattern_dir . $pattern_file, $pattern_content );

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
			return unlink( $pattern_dir . $pattern_file );
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
}
