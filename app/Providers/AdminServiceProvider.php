<?php
/**
 * Admin Service Provider
 *
 * @package ViraCode
 */

namespace ViraCode\Providers;

use ViraCode\Http\Controllers\AdminController;

if (!defined("ABSPATH")) {
    exit();
}

/**
 * AdminServiceProvider Class
 *
 * Handles registration of admin-related functionality.
 */
class AdminServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        // Register admin controller.
        $this->app->adminController = new AdminController();
    }

    /**
     * Boot the service provider.
     *
     * @return void
     */
    public function boot()
    {
        // Register admin menu.
        add_action("admin_menu", [$this, "registerAdminMenu"]);

        // Enqueue admin assets.
        add_action("admin_enqueue_scripts", [$this, "enqueueAdminAssets"]);

        // Register admin AJAX handlers.
        $this->registerAjaxHandlers();

        // Add settings link on plugins page.
        add_filter("plugin_action_links_" . VIRA_CODE_BASENAME, [
            $this,
            "addSettingsLink",
        ]);

        // Add admin notices.
        add_action("admin_notices", [$this, "showAdminNotices"]);
    }

    /**
     * Register admin menu.
     *
     * @return void
     */
    public function registerAdminMenu()
    {
        add_menu_page(
            __("Vira Code", "vira-code"),
            __("Vira Code", "vira-code"),
            "manage_options",
            "vira-code",
            [$this->app->adminController, "index"],
            "dashicons-editor-code",
            80,
        );

        add_submenu_page(
            "vira-code",
            __("All Snippets", "vira-code"),
            __("All Snippets", "vira-code"),
            "manage_options",
            "vira-code",
            [$this->app->adminController, "index"],
        );

        add_submenu_page(
            "vira-code",
            __("Add New", "vira-code"),
            __("Add New", "vira-code"),
            "manage_options",
            "vira-code-new",
            [$this->app->adminController, "create"],
        );
		
		add_submenu_page(
            "vira-code",
            __("Settings", "vira-code"),
            __("Settings", "vira-code"),
            "manage_options",
            "vira-code-settings",
            [$this->app->adminController, "settings"],
        );

        add_submenu_page(
            "vira-code",
            __("Library", "vira-code"),
            __("Library", "vira-code"),
            "manage_options",
            "vira-code-library",
            [$this->app->adminController, "library"],
        );

        add_submenu_page(
            "vira-code",
            __("Logs", "vira-code"),
            __("Logs", "vira-code"),
            "manage_options",
            "vira-code-logs",
            [$this->app->adminController, "logs"],
        );
    }

    /**
     * Enqueue admin assets.
     *
     * @param string $hook Current admin page hook.
     * @return void
     */
    public function enqueueAdminAssets($hook)
    {
        // Only load on our admin pages.
        if (strpos($hook, "vira-code") === false) {
            return;
        }

        // Enqueue CSS.
        wp_enqueue_style(
            "vira-code-admin",
            \ViraCode\vira_code_asset_url("css/admin.css"),
            [],
            \ViraCode\vira_code_version(),
        );

        // Enqueue CodeMirror for syntax highlighting.
        wp_enqueue_code_editor(["type" => "application/x-httpd-php"]);
        wp_enqueue_script("wp-theme-plugin-editor");
        wp_enqueue_style("wp-codemirror");

        // Enqueue JavaScript.
        wp_enqueue_script(
            "vira-code-admin",
            \ViraCode\vira_code_asset_url("js/admin.js"),
            ["jquery", "wp-code-editor"],
            \ViraCode\vira_code_version(),
            true,
        );

        // Enqueue editor-specific JavaScript on editor page.
        if (strpos($hook, "vira-code-new") !== false) {
            wp_enqueue_script(
                "vira-code-editor",
                \ViraCode\vira_code_asset_url("js/editor.js"),
                ["jquery", "vira-code-admin"],
                \ViraCode\vira_code_version(),
                true,
            );

            // Enqueue jQuery UI for drag and drop
            wp_enqueue_script("jquery-ui-sortable");

            // Enqueue conditional logic JavaScript
            wp_enqueue_script(
                "vira-code-conditional-logic",
                \ViraCode\vira_code_asset_url("js/conditional-logic.js"),
                ["jquery", "jquery-ui-sortable", "vira-code-admin"],
                \ViraCode\vira_code_version(),
                true,
            );
            


            // Localize editor script separately
            wp_localize_script("vira-code-editor", "viraCodeEditor", [
                "ajaxUrl" => admin_url("admin-ajax.php"),
                "nonce" => wp_create_nonce(\ViraCode\vira_code_nonce_action()),
            ]);
        }

        // Localize script.
        wp_localize_script("vira-code-admin", "viraCode", [
            "ajaxUrl" => admin_url("admin-ajax.php"),
            "nonce" => wp_create_nonce(\ViraCode\vira_code_nonce_action()),
            "restUrl" => rest_url("vira/v1/"),
            "restNonce" => wp_create_nonce("wp_rest"),
            "safeMode" => \ViraCode\vira_code_is_safe_mode(),
            "snippetTypes" => \ViraCode\vira_code_snippet_types(),
            "snippetScopes" => \ViraCode\vira_code_snippet_scopes(),
            "i18n" => [
                "confirm_delete" => __(
                    "Are you sure you want to delete this snippet?",
                    "vira-code",
                ),
                "save_success" => __(
                    "Snippet saved successfully!",
                    "vira-code",
                ),
                "save_error" => __(
                    "Error saving snippet. Please try again.",
                    "vira-code",
                ),
                "delete_success" => __(
                    "Snippet deleted successfully!",
                    "vira-code",
                ),
                "delete_error" => __(
                    "Error deleting snippet. Please try again.",
                    "vira-code",
                ),
            ],
        ]);

        // Enqueue library-specific JavaScript on library page.
        if (strpos($hook, "vira-code-library") !== false) {
            wp_enqueue_script(
                "vira-code-library",
                \ViraCode\vira_code_asset_url("js/library.js"),
                ["jquery"],
                \ViraCode\vira_code_version(),
                true,
            );

            // Localize library script.
            wp_localize_script("vira-code-library", "viraCodeLibrary", [
                "editorUrl" => admin_url("admin.php?page=vira-code-new"),
                "i18n" => [
                    "copied" => __("Copied!", "vira-code"),
                ],
            ]);
        }
    }

    /**
     * Register AJAX handlers.
     *
     * @return void
     */
    protected function registerAjaxHandlers()
    {
        add_action("wp_ajax_vira_code_save_snippet", [
            $this->app->adminController,
            "saveSnippet",
        ]);
        add_action("wp_ajax_vira_code_delete_snippet", [
            $this->app->adminController,
            "deleteSnippet",
        ]);
        add_action("wp_ajax_vira_code_toggle_snippet", [
            $this->app->adminController,
            "toggleSnippet",
        ]);
        add_action("wp_ajax_vira_code_get_statistics", [
            $this->app->adminController,
            "getStatistics",
        ]);
        add_action("wp_ajax_vira_code_toggle_safe_mode", [
            $this->app->adminController,
            "toggleSafeMode",
        ]);
        add_action("wp_ajax_vira_code_clear_logs", [
            $this->app->adminController,
            "clearLogs",
        ]);
        add_action("wp_ajax_vira_code_validate_snippet", [
            $this->app->adminController,
            "validateSnippet",
        ]);
        add_action("wp_ajax_vira_code_export_conditional_rules", [
            $this->app->adminController,
            "exportConditionalRules",
        ]);
        add_action("wp_ajax_vira_code_import_conditional_rules", [
            $this->app->adminController,
            "importConditionalRules",
        ]);
        add_action("wp_ajax_vira_code_bulk_conditional_operations", [
            $this->app->adminController,
            "bulkConditionalOperations",
        ]);
        add_action("wp_ajax_vira_code_get_conditional_logic_preview", [
            $this->app->adminController,
            "getConditionalLogicPreview",
        ]);
        add_action("wp_ajax_vira_code_test_conditional_logic", [
            $this->app->adminController,
            "testConditionalLogic",
        ]);
        add_action("wp_ajax_vira_code_get_library_cleanup_info", [
            $this->app->adminController,
            "getLibraryCleanupInfo",
        ]);
    }

    /**
     * Add settings link to plugins page.
     *
     * @param array $links Existing plugin action links.
     * @return array
     */
    public function addSettingsLink($links)
    {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            esc_url(admin_url("admin.php?page=vira-code-settings")),
            __("Settings", "vira-code"),
        );

        array_unshift($links, $settings_link);

        return $links;
    }

    /**
     * Show admin notices.
     *
     * @return void
     */
    public function showAdminNotices()
    {
        // Show safe mode notice.
        if (\ViraCode\vira_code_is_safe_mode()) { ?>
			<div class="notice notice-warning is-dismissible">
				<p>
					<strong><?php esc_html_e(
         "Vira Code Safe Mode is Active",
         "vira-code",
     ); ?></strong>
					<?php esc_html_e(
         "All snippets are currently disabled. Disable safe mode to resume normal operation.",
         "vira-code",
     ); ?>
					<a href="<?php echo esc_url(
         admin_url("admin.php?page=vira-code-settings"),
     ); ?>" class="button button-small">
						<?php esc_html_e("Manage Settings", "vira-code"); ?>
					</a>
				</p>
			</div>
			<?php }
    }
}
