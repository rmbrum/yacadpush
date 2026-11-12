<?php
if ( ! defined( 'ABSPATH' ) ) exit;
// SPDX-License-Identifier: GPL-2.0-or-later

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @link       https://hvck.cz/
 * @since      1.0.0
 *
 * @package    Academic_Publications_Showcase
 * @subpackage Academic_Publications_Showcase/admin
 * @author     Martin Haváček <martin.havacek@osu.cz>
 */

class ACADPUSH_Admin {
	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;
	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;
	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version = $version;
	}
	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {
		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/aps-admin.css', array(), $this->version, 'all' );

		// Admin page inline styles (moved from template to avoid direct <style> output).
		$acadpush_admin_css = '
.dyzc-admin-wrap {
	max-width: 1200px;
	margin: 20px 0;
}
.nav-tab-wrapper {
	margin-bottom: 20px;
}
.dyzc-tab-content {
	background: #fff;
	border: 1px solid #c3c4c7;
	border-top: none;
	padding: 20px;
}
.dyzc-section {
	margin-bottom: 30px;
}
.dyzc-section h3 {
	margin-top: 0;
	border-bottom: 1px solid #dcdcde;
	padding-bottom: 10px;
}
.form-table th {
	width: 250px;
	padding: 15px 10px 15px 0;
}
.form-table td {
	padding: 15px 10px;
}
.form-table input[type="text"],
.form-table input[type="number"],
.form-table select {
	width: 400px;
}
.form-table .color-picker {
	width: 100px;
}
.dyzc-help-text {
	display: block;
	margin-top: 5px;
	color: #646970;
	font-style: italic;
}
.dyzc-recommended {
	color: #d63638;
	font-weight: 600;
}
.dyzc-usage-card {
	background: #f6f7f7;
	border: 1px solid #dcdcde;
	border-radius: 4px;
	padding: 20px;
	margin-bottom: 20px;
}
.dyzc-usage-card h3 {
	margin-top: 0;
	border-bottom: none;
}
.dyzc-code-block {
	background: #1d2327;
	color: #f0f0f1;
	padding: 12px 16px;
	border-radius: 4px;
	font-family: inherit;
	margin: 10px 0;
}
.dyzc-example {
	margin-bottom: 20px;
}
.dyzc-example h4 {
	margin-bottom: 8px;
}';
		wp_add_inline_style( $this->plugin_name, $acadpush_admin_css );
	}
	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/aps-admin.js', array( 'jquery' ),
            $this->version, false );
	}

	/**
	 * Register the administration menu for this plugin into the WordPress Dashboard menu.
	 *
	 * @since    1.0.0
	 */
	public function add_plugin_admin_menu() {
		add_options_page( 'Academic Publications Setup', 'Publications', 'manage_options', $this->plugin_name, array($this, 'display_plugin_setup_page') );
	}
	/**
	 * Add settings action link to the plugins page.
	 *
	 * @since    1.0.0
	 */
	public function add_action_links( $links ) {
		$settings_link = array(
			'<a href="' . admin_url( 'options-general.php?page=' . $this->plugin_name ) . '">' . __('Settings', 'yacadpush') . '</a>',
		);
		return array_merge(  $settings_link, $links );
	}
	/**
	 * Render the settings page for this plugin.
	 *
	 * @since    1.0.0
	 */
	public function display_plugin_setup_page() {
		include_once( 'partials/aps-admin-display.php' );
	}
	/**
	 *  Save the plugin options
	 *
	 * @since    1.0.0
	 */
	public function options_update() {
		register_setting( $this->plugin_name, $this->plugin_name, array( 'sanitize_callback' => array( $this, 'validate' ), 'default' => array( 'id_community_orcid' => '', 'choice' => '', 'extra_keyword' => '', 'number_publications' => 10 ) ) );
	}
	/**
	 * Validate all options fields
	 *
	 * @since    1.0.0
	 */
	public function validate( $input ) {
		$valid = array();

		// Fonte de dados
		$allowed_choices = array( 'community', 'orcid', 'database' );
		$valid['choice'] = isset( $input['choice'] ) && in_array( $input['choice'], $allowed_choices, true )
			? $input['choice'] : 'community';

		// API Settings (Zenodo)
		$valid['id_community_orcid']    = isset( $input['id_community_orcid'] )    ? sanitize_text_field( $input['id_community_orcid'] )    : '';
		$valid['access_token']          = isset( $input['access_token'] )          ? sanitize_text_field( $input['access_token'] )          : '';
		$valid['orcid_id']              = isset( $input['orcid_id'] )              ? sanitize_text_field( $input['orcid_id'] )              : '';
		$valid['extra_keyword']         = isset( $input['extra_keyword'] )         ? sanitize_text_field( $input['extra_keyword'] )         : '';
		$valid['number_publications']   = isset( $input['number_publications'] )   ? absint( $input['number_publications'] )               : 10;
		$valid['filter_categories_raw'] = isset( $input['filter_categories_raw'] ) ? sanitize_text_field( $input['filter_categories_raw'] ) : '';

		// Database Settings
		$valid['db_host']     = isset( $input['db_host'] )     ? sanitize_text_field( $input['db_host'] )     : 'localhost';
		$valid['db_port']     = isset( $input['db_port'] )     ? absint( $input['db_port'] )                  : 3306;
		$valid['db_name']     = isset( $input['db_name'] )     ? sanitize_text_field( $input['db_name'] )     : '';
		$valid['db_user']     = isset( $input['db_user'] )     ? sanitize_text_field( $input['db_user'] )     : '';
		// Senha: preserva valor anterior se o campo vier vazio (não sobrescreve)
		$existing             = get_option( $this->plugin_name, array() );
		$valid['db_password'] = ( isset( $input['db_password'] ) && $input['db_password'] !== '' )
			? $input['db_password']
			: ( isset( $existing['db_password'] ) ? $existing['db_password'] : '' );

		// Design Settings
		$primary     = isset( $input['primary_color'] )      ? sanitize_hex_color( $input['primary_color'] )      : false;
		$valid['primary_color'] = $primary ?: '#2271b1';

		$card_bg     = isset( $input['card_bg_color'] )      ? sanitize_hex_color( $input['card_bg_color'] )      : false;
		$valid['card_bg_color'] = $card_bg ?: '#ffffff';

		$card_border = isset( $input['card_border_color'] )  ? sanitize_hex_color( $input['card_border_color'] )  : false;
		$valid['card_border_color'] = $card_border ?: '#dcdcde';

		$text        = isset( $input['text_color'] )         ? sanitize_hex_color( $input['text_color'] )         : false;
		$valid['text_color'] = $text ?: '#1d2327';

		$meta        = isset( $input['meta_text_color'] )    ? sanitize_hex_color( $input['meta_text_color'] )    : false;
		$valid['meta_text_color'] = $meta ?: '#646970';

		$allowed_fonts   = array( 'system', 'Inter', 'Roboto', 'Open Sans', 'Merriweather' );
		$valid['font_family']    = isset( $input['font_family'] ) && in_array( $input['font_family'], $allowed_fonts, true )    ? $input['font_family']    : 'system';

		$allowed_sizes   = array( 'small', 'medium', 'large' );
		$valid['font_size']      = isset( $input['font_size'] )   && in_array( $input['font_size'],   $allowed_sizes, true )    ? $input['font_size']      : 'medium';

		$allowed_layouts = array( 'standard', 'compact', 'detailed' );
		$valid['card_layout']    = isset( $input['card_layout'] ) && in_array( $input['card_layout'], $allowed_layouts, true )  ? $input['card_layout']    : 'standard';

		$allowed_radius  = array( 'none', 'small', 'medium', 'large' );
		$valid['border_radius']  = isset( $input['border_radius'] ) && in_array( $input['border_radius'], $allowed_radius, true ) ? $input['border_radius'] : 'medium';

		// Citations: only the Design tab sends this field; the other tabs keep the stored value.
		$valid['abnt_compound_surnames'] = isset( $input['abnt_compound_surnames'] )
			? sanitize_textarea_field( $input['abnt_compound_surnames'] )
			: ( isset( $existing['abnt_compound_surnames'] ) ? $existing['abnt_compound_surnames'] : '' );

		return $valid;
	}
}
