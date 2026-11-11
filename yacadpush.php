<?php
// SPDX-License-Identifier: GPL-2.0-or-later

/**
 * YACADPUSH: Yet Another Academic Publication Showcase
 *
 * Display publications from Zenodo communities, ORCID author profiles or a local
 * MySQL database on WordPress sites.
 * Fork of "Academic Publications Showcase" 1.0.0 by Martin Haváček, itself based on
 * "Display Your Zenodo Community" by DARIAH-EU.
 *
 * @package           Academic_Publications_Showcase
 * @link              https://github.com/rmbrum/yacadpush
 * @since             1.0.0
 *
 * @wordpress-plugin
 * Plugin Name:       YACADPUSH: Yet Another Academic Publication Showcase
 * Plugin URI:        https://github.com/rmbrum/yacadpush
 * Description:       Display publications from Zenodo communities, ORCID author profiles or a local MySQL database. Fork of Academic Publications Showcase by Martin Haváček, based on Display Your Zenodo Community by DARIAH-EU.
 * Version:           2.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Raphael Brum; Martin Haváček (original)
 * Author URI:        https://github.com/rmbrum
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       yacadpush
 * Domain Path:       /languages
 *
 * Copyright (C) 2020 DARIAH-ERIC (original Display Your Zenodo Community plugin)
 * Copyright (C) 2026 Martin Haváček (modifications and enhancements)
 * Copyright (C) 2026 Raphael Brum (YACADPUSH: local database source and pipeline)
 *
 * This plugin is based on "Display Your Zenodo Community" originally developed by DARIAH-EU.
 * Developed as part of the project "Research of Excellence on Digital Technologies and Wellbeing"
 * Project Registration Number: CZ.02.01.01/00/22_008/0004583
 * Operational Program: The Johannes Amos Comenius
 * Co-funded by the European Union
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'ACADPUSH_ROOT', dirname( __FILE__ ) );
define( 'YACADPUSH_VERSION', '2.0.0' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-aps-activator.php
 */
function acadpush_activate() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-aps-activator.php';
	ACADPUSH_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-aps-deactivator.php
 */
function acadpush_deactivate() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-aps-deactivator.php';
	ACADPUSH_Deactivator::deactivate();
}
register_activation_hook( __FILE__, 'acadpush_activate' );
register_deactivation_hook( __FILE__, 'acadpush_deactivate' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-aps.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function acadpush_run() {
	$plugin = new ACADPUSH();
	$plugin->run();
}

acadpush_run();
