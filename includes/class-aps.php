<?php
if ( ! defined( 'ABSPATH' ) ) exit;
// SPDX-License-Identifier: GPL-2.0-or-later

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Academic_Publications_Showcase
 * @subpackage Academic_Publications_Showcase/includes
 * @link       https://hvck.cz/
 * @author     Martin Haváček <martin.havacek@osu.cz>
 */
class ACADPUSH {
	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      ACADPUSH_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;
	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;
	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;
	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'YACADPUSH_VERSION' ) ) {
			$this->version = YACADPUSH_VERSION;
		} else {
			$this->version = '2.0.0';
		}
		$this->plugin_name = 'yacadpush';
		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
		$this->define_shortcodes();
	}
	/**
	 * Load the required dependencies for this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-aps-loader.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-aps-i18n.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-aps-db.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-aps-admin.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-aps-public.php';
		$this->loader = new ACADPUSH_Loader();
	}
	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {
		// WordPress auto-loads translation files from /languages/ since WP 4.6
		// No need to manually load text domain
	}
	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {
		$plugin_admin = new ACADPUSH_Admin( $this->get_plugin_name(), $this->get_version() );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );

		// Save/Update our plugin options
		$this->loader->add_action( 'admin_init', $plugin_admin, 'options_update');
		// Add menu item
		$this->loader->add_action( 'admin_menu', $plugin_admin, 'add_plugin_admin_menu' );
		// Add Settings link to the plugin
		$plugin_basename = plugin_basename( plugin_dir_path( __DIR__ ) . $this->plugin_name . '.php' );
		$this->loader->add_filter( 'plugin_action_links_' . $plugin_basename, $plugin_admin, 'add_action_links' );
	}
	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {
		$plugin_public = new ACADPUSH_Public( $this->get_plugin_name(), $this->get_version() );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );

		$this->loader->add_filter( 'query_vars', $plugin_public, 'zp_query_vars' );

		// Register primary shortcode
		$this->loader->add_shortcode( 'showcase-publications', $plugin_public, 'display_zenodo_data' );
	}

	private function define_shortcodes() {
		// Shortcodes are now registered in define_public_hooks()
	}
	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}
	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}
	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    ACADPUSH_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}
	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}
}
