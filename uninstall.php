<?php
/**
 * Uninstall Script
 *
 * Fired when the plugin is uninstalled.
 *
 * @package ViraCode
 */

// If uninstall not called from WordPress, exit.
if (!defined("WP_UNINSTALL_PLUGIN")) {
    exit();
}

// Get global database object.
global $wpdb;

// Delete custom table.
$table_name = $wpdb->prefix . "vira_snippets";
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query("DROP TABLE IF EXISTS {$table_name}");

// Delete plugin options.
delete_option("vira_code_version");
delete_option("vira_code_safe_mode");

// Delete transients.
delete_transient("vira_code_execution_logs");
delete_transient("vira_code_fatal_errors");
delete_transient("vira_code_recent_errors");

// Clean up any remaining transients with vira_code prefix.
$wpdb->query(
    "DELETE FROM {$wpdb->options}
    WHERE option_name LIKE '_transient_vira_code%'
    OR option_name LIKE '_transient_timeout_vira_code%'",
);

// Delete error log file if exists.
$error_log = dirname(__FILE__) . "/vira-code-errors.log";
if (file_exists($error_log)) {
    @unlink($error_log);
}

// Clear any cached data.
wp_cache_flush();

// Fire action for extensions to clean up.
do_action("vira_code/uninstalled");
