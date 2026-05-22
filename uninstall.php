<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @link       https://hvck.cz/
 * @since      1.0.0
 *
 * @package    Academic_Publications_Showcase
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Delete plugin data when uninstalling.
 * - Plugin options (including API tokens)
 * - Cached transients
 * - Postmeta entries (if any)
 */

// Delete main plugin options
delete_option( 'academic-publications-showcase' );

// Delete all transients with acadpush_ prefix (cached API responses)
global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query required in uninstall context; no cache needed for cleanup.
$wpdb->query(
	"DELETE FROM {$wpdb->options}
	 WHERE option_name LIKE '_transient_acadpush_%'
	 OR option_name LIKE '_transient_timeout_acadpush_%'"
);

// Delete postmeta entries (if plugin stored any)
$acadpush_keys = array(
	'academic-publications-showcase'
);

foreach ( $acadpush_keys as $acadpush_key ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query required in uninstall context; no cache needed for cleanup.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM $wpdb->postmeta
			 WHERE meta_key = %s",
			$acadpush_key
		)
	);
}
