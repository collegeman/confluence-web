<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

/**
 * Plugin Name:       Ollie Pro
 * Plugin URI:        https://olliewp.com
 * Description:       Adds the Ollie Pro pattern library and Ollie Pro Dashboard to the Ollie block theme.
 * Version:           1.2.9
 * Author:            buildwithollie
 * Author URI:        https://olliewp.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       ollie-pro
 * Domain Path:       /languages
 *
 */

define( 'OLPO_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'OLPO_URL', untrailingslashit( plugin_dir_url( __FILE__ ) ) );
define( 'OLPO_VERSION', '1.2.9' );

// localize.
add_action( 'init', function () {
	$textdomain_dir = plugin_basename( dirname( __FILE__ ) ) . '/languages';
	load_plugin_textdomain( 'ollie-pro', false, $textdomain_dir );
} );

// Plugin updater.
require OLPO_PATH . '/inc/plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$update_checker = PucFactory::buildUpdateChecker(
	'https://vttiicmlzxzxrcyyewfn.supabase.co/storage/v1/object/public/ollie/releases/plugin.json',
	__FILE__,
	'ollie-pro'
);

// run plugin.
if ( ! function_exists( 'olpo_run_plugin' ) ) {
	add_action( 'plugins_loaded', 'olpo_run_plugin' );

	/**
	 * Run plugin
	 *
	 * @return void
	 */
	function olpo_run_plugin() {
		// Get the current theme.
		$theme = wp_get_theme();

		if ( 'ollie' === $theme->template ) {
			require_once( OLPO_PATH . '/inc/class-olpo-settings.php' );
			require_once( OLPO_PATH . '/inc/class-olpo-helper.php' );

			olpo\Settings::get_instance();
			olpo\Helper::get_instance();
		} else {
			// If multisite, only show the notice to network admin.
			if ( is_multisite() && function_exists( 'get_sites' ) ) {
				if ( is_network_admin() ) {
					// Add admin notice.
					add_action( 'network_admin_notices', function () {
						$message = sprintf( __( 'The Ollie Pro plugin needs the free Ollie theme to work. View the theme and install it %s.', 'ollie-pro' ), '<a href=' . esc_url( network_admin_url( 'theme-install.php?search=ollie' ) ) . '>by clicking here</a>' );
						echo wp_kses_post( '<div class="notice notice-error"><p>' . $message . '</p></div>' );
					} );
				}
			} else {
				// Add admin notice.
				add_action( 'admin_notices', function () {
					$message = sprintf( __( 'The Ollie Pro plugin needs the free Ollie theme to work. View the theme and install it %s.', 'ollie-pro' ), '<a href=' . esc_url( admin_url( 'theme-install.php?search=ollie' ) ) . '>by clicking here</a>' );
					echo wp_kses_post( '<div class="notice notice-error"><p>' . $message . '</p></div>' );
				} );
			}
		}
	}

	add_action( 'init', 'olpo_register_pattern_block' );

	/**
	 * Register the pattern block.
	 *
	 * @return void
	 */
	function olpo_register_pattern_block() {
		// Only load in the admin.
		if ( ! is_admin() || is_customize_preview() ) {
			return;
		}

		// Register scripts.
		$olpo_asset_file = include( plugin_dir_path( __FILE__ ) . 'build/pattern-block/index.asset.php' );
		wp_register_script( 'ollie-pattern-block', plugins_url( 'build/pattern-block/index.js', __FILE__ ), $olpo_asset_file['dependencies'], $olpo_asset_file['version'] );

		// Register block.
		register_block_type( __DIR__ . '/build/pattern-block' );
		wp_enqueue_script( 'ollie-pattern-block', plugins_url( 'build/pattern-block/index.js', __FILE__ ), $olpo_asset_file['dependencies'], $olpo_asset_file['version'] );
		wp_enqueue_style( 'ollie-pattern-block-style', plugins_url( 'build/pattern-block/index.css', __FILE__ ) );

		require_once( OLPO_PATH . '/inc/class-olpo-helper.php' );

		$args = array(
			'version'             => OLPO_VERSION,
			'downloaded_patterns' => olpo\Helper::get_downloaded_patterns(),
		);

		wp_localize_script( 'ollie-pattern-block', 'ollie_pattern_options', $args );

		// Make the blocks translatable.
		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'ollie-pattern-block', 'ollie-pro', OLPO_PATH . '/languages' );
		}
	}
}

