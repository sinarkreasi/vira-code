<?php
/**
 * Plugin Name: Vira Code
 * Plugin URI: https://viraloka.com/pro/vira-code
 * Description: Lightweight code snippet manager for PHP, JS, CSS & HTML. Fast setup, clean UI, instant productivity.
 * Version: 1.0.0
 * Author: Viraloka
 * Author URI: https://viraloka.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: vira-code
 * Domain Path: /resources/lang
 * Requires at least: 6.0
 * Requires PHP: 8.2
 *
 * @package ViraCode
 */

namespace ViraCode;

// Exit if accessed directly.
if (!defined("ABSPATH")) {
    exit();
}

// Define plugin constants.
define("VIRA_CODE_VERSION", "1.0.0");
define("VIRA_CODE_FILE", __FILE__);
define("VIRA_CODE_PATH", plugin_dir_path(__FILE__));
define("VIRA_CODE_URL", plugin_dir_url(__FILE__));
define("VIRA_CODE_BASENAME", plugin_basename(__FILE__));

// Require Composer autoloader.
if (file_exists(VIRA_CODE_PATH . "vendor/autoload.php")) {
    require_once VIRA_CODE_PATH . "vendor/autoload.php";
} else {
    // Fallback error if Composer autoload is not found.
    add_action("admin_notices", function () {
        ?>
		<div class="notice notice-error">
			<p>
				<?php echo wp_kses_post(
        sprintf(
            /* translators: %s: plugin name */
            __(
                "<strong>%s</strong> requires Composer dependencies. Please run <code>composer install</code> in the plugin directory.",
                "vira-code",
            ),
            "Vira Code",
        ),
    ); ?>
			</p>
		</div>
		<?php
    });
    return;
}

// Bootstrap the application.
require_once VIRA_CODE_PATH . "bootstrap/app.php";

/**
 * Initialize the plugin.
 *
 * @return App
 */
function vira_code()
{
    return App::getInstance();
}

// Kick off the plugin.
vira_code();

/**
 * Activation hook.
 */
register_activation_hook(__FILE__, function () {
    // Create database tables.
    $snippet_model = new Models\SnippetModel();
    $snippet_model->createTable();

    $conditional_logic_model = new Models\ConditionalLogicModel();
    $conditional_logic_model->createTable();

    // Set default options.
    add_option("vira_code_version", VIRA_CODE_VERSION);
    add_option("vira_code_safe_mode", 0);

    // Initialize conditional logic service provider during activation.
    do_action('vira_code/conditional_logic_activation');

    // Flush rewrite rules.
    flush_rewrite_rules();
});

/**
 * Initialize conditional logic hooks.
 */
add_action('init', function() {
    Models\ConditionalLogicModel::registerHooks();
    
    // Ensure conditional logic service is available after app boot.
    if (vira_code()->isBooted() && vira_code()->conditionalLogicService) {
        do_action('vira_code/conditional_logic_initialized', vira_code()->conditionalLogicService);
    }
});

/**
 * Deactivation hook.
 */
register_deactivation_hook(__FILE__, function () {
    // Flush rewrite rules.
    flush_rewrite_rules();
});
