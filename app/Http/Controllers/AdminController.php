<?php
/**
 * Admin Controller
 *
 * @package ViraCode
 */

namespace ViraCode\Http\Controllers;

use ViraCode\Models\SnippetModel;
use ViraCode\Services\SnippetExecutor;

if (!defined("ABSPATH")) {
    exit();
}

/**
 * AdminController Class
 *
 * Handles admin interface rendering and AJAX requests.
 */
class AdminController
{
    /**
     * Snippet model instance.
     *
     * @var SnippetModel
     */
    protected $model;

    /**
     * Snippet executor instance.
     *
     * @var SnippetExecutor
     */
    protected $executor;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->model = new SnippetModel();
        $this->executor = new SnippetExecutor();
    }

    /**
     * Display snippets list page.
     *
     * @return void
     */
    public function index()
    {
        // Check user capability.
        if (!\ViraCode\vira_code_user_can_manage()) {
            wp_die(
                esc_html__(
                    "You do not have permission to access this page.",
                    "vira-code",
                ),
            );
        }

        // Get all snippets.
        $snippets = $this->model->getAll();

        // Get conditional logic information for each snippet
        $conditional_logic_model = new \ViraCode\Models\ConditionalLogicModel();
        foreach ($snippets as &$snippet) {
            $snippet['conditional_logic_enabled'] = $conditional_logic_model->hasRules($snippet['id']);
            $snippet['conditional_logic_count'] = $conditional_logic_model->getRuleCount($snippet['id']);
            if ($snippet['conditional_logic_enabled']) {
                $snippet['conditional_logic_rules'] = $conditional_logic_model->getRules($snippet['id']);
            }
        }

        // Get statistics.
        $stats = [
            "total" => $this->model->count(),
            "active" => $this->model->count(["status" => "active"]),
            "inactive" => $this->model->count(["status" => "inactive"]),
            "error" => $this->model->count(["status" => "error"]),
            "conditional_logic" => count($conditional_logic_model->getSnippetsWithRules()),
        ];

        // Render view.
        \ViraCode\vira_code_view("snippets-list", [
            "snippets" => $snippets,
            "stats" => $stats,
        ]);
    }

    /**
     * Display create snippet page.
     *
     * @return void
     */
    public function create()
    {
        // Check user capability.
        if (!\ViraCode\vira_code_user_can_manage()) {
            wp_die(
                esc_html__(
                    "You do not have permission to access this page.",
                    "vira-code",
                ),
            );
        }

        // Check if editing existing snippet.
        $snippet_id = isset($_GET["id"]) ? absint($_GET["id"]) : 0;
        $snippet = null;

        if ($snippet_id > 0) {
            $snippet = $this->model->getById($snippet_id);
            if (!$snippet) {
                wp_die(esc_html__("Snippet not found.", "vira-code"));
            }
        }

        // Render view.
        \ViraCode\vira_code_view("snippet-editor", [
            "snippet" => $snippet,
            "is_new" => null === $snippet,
        ]);
    }

    /**
     * Display settings page.
     *
     * @return void
     */
    public function settings()
    {
        // Check user capability.
        if (!\ViraCode\vira_code_user_can_manage()) {
            wp_die(
                esc_html__(
                    "You do not have permission to access this page.",
                    "vira-code",
                ),
            );
        }

        // Handle form submission.
        if (isset($_POST["vira_code_settings_nonce"])) {
            $this->saveSettings();
        }

        // Get current settings.
        $settings = [
            "safe_mode" => \ViraCode\vira_code_is_safe_mode(),
            "log_limit" => \ViraCode\vira_code_get_log_limit(),
        ];

        // Render view.
        \ViraCode\vira_code_view("settings", [
            "settings" => $settings,
        ]);
    }

    /**
     * Display logs page.
     *
     * @return void
     */
    public function logs()
    {
        // Check user capability.
        if (!\ViraCode\vira_code_user_can_manage()) {
            wp_die(
                esc_html__(
                    "You do not have permission to access this page.",
                    "vira-code",
                ),
            );
        }

        // Get execution logs.
        $logs = \ViraCode\vira_code_get_logs(\ViraCode\vira_code_get_log_limit());

        // Reverse to show newest first.
        $logs = array_reverse($logs);

        // Render view.
        \ViraCode\vira_code_view("logs", [
            "logs" => $logs,
        ]);
    }

    /**
     * Display library page.
     *
     * @return void
     */
    public function library()
    {
        // Check user capability.
        if (!\ViraCode\vira_code_user_can_manage()) {
            wp_die(
                esc_html__(
                    "You do not have permission to access this page.",
                    "vira-code",
                ),
            );
        }

        // Get library snippets.
        $snippets = $this->getLibrarySnippets();

        // Render view.
        \ViraCode\vira_code_view("library", [
            "snippets" => $snippets,
        ]);
    }

    /**
     * Get library snippets.
     *
     * @return array
     */
    protected function getLibrarySnippets()
    {
        $snippets = [
            [
                "id" => "wc-hide-shipping-when-free",
                "title" =>
                    "Hide Other Shipping Methods When Free Shipping Available",
                "description" =>
                    "Automatically hide all other shipping methods when free shipping is available in WooCommerce cart.",
                "category" => "WooCommerce",
                "type" => "php",
                "scope" => "frontend",
                "code" => "// Hide shipping rates when free shipping is available
add_filter('woocommerce_package_rates', function(\$rates) {
    \$free = array();
    foreach (\$rates as \$rate_id => \$rate) {
        if ('free_shipping' === \$rate->method_id) {
            \$free[\$rate_id] = \$rate;
            break;
        }
    }
    return !empty(\$free) ? \$free : \$rates;
}, 100);",
                "tags" => "shipping, woocommerce, cart",
                "icon" => "dashicons-cart",
            ],
            [
                "id" => "wc-custom-add-to-cart-text",
                "title" => "Change Add to Cart Button Text",
                "description" =>
                    'Customize the "Add to Cart" button text for different product types in WooCommerce.',
                "category" => "WooCommerce",
                "type" => "php",
                "scope" => "frontend",
                "code" => "// Change Add to Cart button text
add_filter('woocommerce_product_single_add_to_cart_text', function(\$text) {
    return __('Buy Now', 'woocommerce');
});

add_filter('woocommerce_product_add_to_cart_text', function(\$text) {
    global \$product;
    if (\$product->is_type('simple')) {
        return __('Purchase', 'woocommerce');
    }
    return \$text;
});",
                "tags" => "button, text, woocommerce",
                "icon" => "dashicons-button",
            ],
            [
                "id" => "wc-remove-product-tabs",
                "title" => "Remove Product Tabs",
                "description" =>
                    "Remove specific tabs from WooCommerce product pages like reviews, description, or additional information.",
                "category" => "WooCommerce",
                "type" => "php",
                "scope" => "frontend",
                "code" => "// Remove product tabs
add_filter('woocommerce_product_tabs', function(\$tabs) {
    // Remove the reviews tab
    unset(\$tabs['reviews']);

    // Remove the description tab
    // unset(\$tabs['description']);

    // Remove the additional information tab
    // unset(\$tabs['additional_information']);

    return \$tabs;
}, 98);",
                "tags" => "tabs, product, woocommerce",
                "icon" => "dashicons-archive",
            ],
            [
                "id" => "wc-minimum-order-amount",
                "title" => "Set Minimum Order Amount",
                "description" =>
                    "Set a minimum order amount required before customers can checkout in WooCommerce.",
                "category" => "WooCommerce",
                "type" => "php",
                "scope" => "frontend",
                "code" => "// Set minimum order amount
add_action('woocommerce_checkout_process', function() {
    \$minimum = 50; // Set your minimum amount
    \$cart_total = WC()->cart->total;

    if (\$cart_total < \$minimum) {
        wc_add_notice(
            sprintf(
                'Minimum order amount is %s. Current total: %s',
                wc_price(\$minimum),
                wc_price(\$cart_total)
            ),
            'error'
        );
    }
});",
                "tags" => "checkout, cart, minimum, woocommerce",
                "icon" => "dashicons-money-alt",
            ],
            [
                "id" => "wc-custom-checkout-fields",
                "title" => "Add Custom Checkout Field",
                "description" =>
                    "Add a custom field to the WooCommerce checkout page and save it with the order.",
                "category" => "WooCommerce",
                "type" => "php",
                "scope" => "frontend",
                "code" => "// Add custom checkout field
add_action('woocommerce_after_order_notes', function(\$checkout) {
    echo '<div id=\"custom_checkout_field\"><h3>' . __('Additional Information') . '</h3>';

    woocommerce_form_field('gift_message', array(
        'type'        => 'textarea',
        'class'       => array('form-row-wide'),
        'label'       => __('Gift Message'),
        'placeholder' => __('Enter your gift message here...'),
    ), \$checkout->get_value('gift_message'));

    echo '</div>';
});

// Save custom checkout field
add_action('woocommerce_checkout_update_order_meta', function(\$order_id) {
    if (!empty(\$_POST['gift_message'])) {
        update_post_meta(\$order_id, 'gift_message', sanitize_text_field(\$_POST['gift_message']));
    }
});",
                "tags" => "checkout, fields, custom, woocommerce",
                "icon" => "dashicons-feedback",
            ],
        ];

        return apply_filters("vira_code/library_snippets", $snippets);
    }

    /**
     * Validate snippet via AJAX (for testing new snippets).
     *
     * @return void
     */
    public function validateSnippet()
    {
        // Verify nonce.
        check_ajax_referer(\ViraCode\vira_code_nonce_action(), "nonce");

        // Check user capability.
        if (!\ViraCode\vira_code_user_can_manage()) {
            wp_send_json_error([
                "message" => __("Permission denied.", "vira-code"),
            ]);
        }

        // Get snippet data.
        $code = isset($_POST["code"]) ? wp_unslash($_POST["code"]) : "";
        $type = isset($_POST["type"])
            ? sanitize_text_field(wp_unslash($_POST["type"]))
            : "php";

        // Validate required fields.
        if (empty($code)) {
            wp_send_json_error([
                "message" => __("Code is required.", "vira-code"),
                "error" => __("No code provided for validation.", "vira-code"),
            ]);
        }

        // Only validate PHP snippets.
        if ("php" !== $type) {
            wp_send_json_success([
                "message" => __(
                    "Validation is only available for PHP snippets.",
                    "vira-code",
                ),
            ]);
        }

        // Test the snippet.
        $test_result = $this->executor->test([
            "id" => 0,
            "code" => $code,
            "type" => $type,
        ]);

        if ($test_result["valid"]) {
            wp_send_json_success([
                "message" => __(
                    "PHP validation passed! No syntax errors found.",
                    "vira-code",
                ),
            ]);
        } else {
            $error_message = !empty($test_result["errors"])
                ? implode("\n", $test_result["errors"])
                : __("Unknown validation error.", "vira-code");

            wp_send_json_error([
                "message" => __("PHP validation failed.", "vira-code"),
                "error" => $error_message,
            ]);
        }
    }

    /**
     * Save snippet via AJAX.
     *
     * @return void
     */
    public function saveSnippet()
    {
        // Verify nonce.
        check_ajax_referer(\ViraCode\vira_code_nonce_action(), "nonce");

        // Check user capability.
        if (!\ViraCode\vira_code_user_can_manage()) {
            wp_send_json_error([
                "message" => __("Permission denied.", "vira-code"),
            ]);
        }

        // Get snippet data.
        $snippet_id = isset($_POST["id"]) ? absint($_POST["id"]) : 0;
        $data = [
            "title" => isset($_POST["title"])
                ? sanitize_text_field(wp_unslash($_POST["title"]))
                : "",
            "description" => isset($_POST["description"])
                ? sanitize_textarea_field(wp_unslash($_POST["description"]))
                : "",
            "code" => isset($_POST["code"]) ? wp_unslash($_POST["code"]) : "",
            "type" => isset($_POST["type"])
                ? sanitize_text_field(wp_unslash($_POST["type"]))
                : "php",
            "scope" => isset($_POST["scope"])
                ? sanitize_text_field(wp_unslash($_POST["scope"]))
                : "frontend",
            "status" => isset($_POST["status"])
                ? sanitize_text_field(wp_unslash($_POST["status"]))
                : "inactive",
            "tags" => isset($_POST["tags"])
                ? sanitize_text_field(wp_unslash($_POST["tags"]))
                : "",
            "category" => isset($_POST["category"])
                ? sanitize_text_field(wp_unslash($_POST["category"]))
                : "",
            "priority" => isset($_POST["priority"])
                ? absint($_POST["priority"])
                : 10,
        ];

        // Get conditional logic data
        $conditional_logic_enabled = isset($_POST["conditional_logic_enabled"]) && $_POST["conditional_logic_enabled"] === "1";
        $conditional_logic_rules = isset($_POST["conditional_logic_rules"]) ? wp_unslash($_POST["conditional_logic_rules"]) : "";
        
        // Parse and validate conditional logic rules
        $parsed_rules = null;
        if ($conditional_logic_enabled && !empty($conditional_logic_rules)) {
            $parsed_rules = json_decode($conditional_logic_rules, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                wp_send_json_error([
                    "message" => __("Invalid conditional logic rules format.", "vira-code"),
                ]);
            }
        }

        // Validate required fields.
        if (empty($data["title"]) || empty($data["code"])) {
            wp_send_json_error([
                "message" => __("Title and code are required.", "vira-code"),
            ]);
        }

        // Validate snippet type.
        $valid_types = array_keys(\ViraCode\vira_code_snippet_types());
        if (!in_array($data["type"], $valid_types, true)) {
            wp_send_json_error([
                "message" => __("Invalid snippet type.", "vira-code"),
            ]);
        }

        // Validate snippet scope.
        $valid_scopes = array_keys(\ViraCode\vira_code_snippet_scopes());
        if (!in_array($data["scope"], $valid_scopes, true)) {
            wp_send_json_error([
                "message" => __("Invalid snippet scope.", "vira-code"),
            ]);
        }

        // Test PHP snippets before saving.
        if ("php" === $data["type"]) {
            $test_result = $this->executor->test(
                array_merge(["id" => 0], $data),
            );
            if (!$test_result["valid"]) {
                wp_send_json_error([
                    "message" => __(
                        "PHP validation failed. Please check your code.",
                        "vira-code",
                    ),
                    "errors" => $test_result["errors"],
                ]);
            }
        }

        // Save snippet.
        if ($snippet_id > 0) {
            // Update existing snippet.
            $result = $this->model->update($snippet_id, $data);
            if ($result) {
                // Save conditional logic rules
                $this->saveConditionalLogicRules($snippet_id, $parsed_rules);
                
                wp_send_json_success([
                    "message" => __(
                        "Snippet updated successfully!",
                        "vira-code",
                    ),
                    "snippet_id" => $snippet_id,
                ]);
            } else {
                wp_send_json_error([
                    "message" => __("Failed to update snippet.", "vira-code"),
                ]);
            }
        } else {
            // Create new snippet.
            $result = $this->model->create($data);
            if ($result) {
                // Save conditional logic rules
                $this->saveConditionalLogicRules($result, $parsed_rules);
                
                wp_send_json_success([
                    "message" => __(
                        "Snippet created successfully!",
                        "vira-code",
                    ),
                    "snippet_id" => $result,
                ]);
            } else {
                wp_send_json_error([
                    "message" => __("Failed to create snippet.", "vira-code"),
                ]);
            }
        }
    }

    /**
     * Delete snippet via AJAX.
     *
     * @return void
     */
    public function deleteSnippet()
    {
        // Verify nonce.
        check_ajax_referer(\ViraCode\vira_code_nonce_action(), "nonce");

        // Check user capability.
        if (!\ViraCode\vira_code_user_can_manage()) {
            wp_send_json_error([
                "message" => __("Permission denied.", "vira-code"),
            ]);
        }

        // Get snippet ID.
        $snippet_id = isset($_POST["id"]) ? absint($_POST["id"]) : 0;

        if ($snippet_id <= 0) {
            wp_send_json_error([
                "message" => __("Invalid snippet ID.", "vira-code"),
            ]);
        }

        // Delete snippet.
        $result = $this->model->delete($snippet_id);

        if ($result) {
            wp_send_json_success([
                "message" => __("Snippet deleted successfully!", "vira-code"),
            ]);
        } else {
            wp_send_json_error([
                "message" => __("Failed to delete snippet.", "vira-code"),
            ]);
        }
    }

    /**
     * Toggle snippet status via AJAX.
     *
     * @return void
     */
    public function toggleSnippet()
    {
        // Verify nonce.
        check_ajax_referer(\ViraCode\vira_code_nonce_action(), "nonce");

        // Check user capability.
        if (!\ViraCode\vira_code_user_can_manage()) {
            wp_send_json_error([
                "message" => __("Permission denied.", "vira-code"),
            ]);
        }

        // Get snippet ID.
        $snippet_id = isset($_POST["id"]) ? absint($_POST["id"]) : 0;

        if ($snippet_id <= 0) {
            wp_send_json_error([
                "message" => __("Invalid snippet ID.", "vira-code"),
            ]);
        }

        // Toggle status.
        $result = $this->model->toggleStatus($snippet_id);

        if ($result) {
            $snippet = $this->model->getById($snippet_id);
            wp_send_json_success([
                "message" => __("Snippet status updated!", "vira-code"),
                "status" => $snippet["status"],
            ]);
        } else {
            wp_send_json_error([
                "message" => __(
                    "Failed to update snippet status.",
                    "vira-code",
                ),
            ]);
        }
    }

    /**
     * Get statistics via AJAX.
     *
     * @return void
     */
    public function getStatistics()
    {
        // Verify nonce.
        check_ajax_referer(\ViraCode\vira_code_nonce_action(), "nonce");

        // Check user capability.
        if (!\ViraCode\vira_code_user_can_manage()) {
            wp_send_json_error([
                "message" => __("Permission denied.", "vira-code"),
            ]);
        }

        // Get statistics.
        $stats = [
            "total" => $this->model->count(),
            "active" => $this->model->count(["status" => "active"]),
            "inactive" => $this->model->count(["status" => "inactive"]),
            "error" => $this->model->count(["status" => "error"]),
        ];

        wp_send_json_success([
            "stats" => $stats,
        ]);
    }

    /**
     * Toggle safe mode via AJAX.
     *
     * @return void
     */
    public function toggleSafeMode()
    {
        // Verify nonce.
        check_ajax_referer(\ViraCode\vira_code_nonce_action(), "nonce");

        // Check user capability.
        if (!\ViraCode\vira_code_user_can_manage()) {
            wp_send_json_error([
                "message" => __("Permission denied.", "vira-code"),
            ]);
        }

        $current_safe_mode = \ViraCode\vira_code_is_safe_mode();

        if ($current_safe_mode) {
            $result = \ViraCode\vira_code_disable_safe_mode();
            $message = __(
                "Safe mode disabled. Snippets will now execute normally.",
                "vira-code",
            );
        } else {
            $result = \ViraCode\vira_code_enable_safe_mode();
            $message = __(
                "Safe mode enabled. All snippets are now disabled.",
                "vira-code",
            );
        }

        if ($result) {
            wp_send_json_success([
                "message" => $message,
                "safe_mode" => !$current_safe_mode,
            ]);
        } else {
            wp_send_json_error([
                "message" => __("Failed to toggle safe mode.", "vira-code"),
            ]);
        }
    }

    /**
     * Clear logs via AJAX.
     *
     * @return void
     */
    public function clearLogs()
    {
        // Verify nonce.
        check_ajax_referer(\ViraCode\vira_code_nonce_action(), "nonce");

        // Check user capability.
        if (!\ViraCode\vira_code_user_can_manage()) {
            wp_send_json_error([
                "message" => __("Permission denied.", "vira-code"),
            ]);
        }

        $result = \ViraCode\vira_code_clear_logs();

        if ($result) {
            wp_send_json_success([
                "message" => __("Logs cleared successfully!", "vira-code"),
            ]);
        } else {
            wp_send_json_error([
                "message" => __("Failed to clear logs.", "vira-code"),
            ]);
        }
    }

    /**
     * Save settings.
     *
     * @return void
     */
    protected function saveSettings()
    {
        // Verify nonce.
        if (
            !isset($_POST["vira_code_settings_nonce"]) ||
            !wp_verify_nonce(
                sanitize_text_field(
                    wp_unslash($_POST["vira_code_settings_nonce"]),
                ),
                "vira_code_save_settings",
            )
        ) {
            add_settings_error(
                "vira_code_settings",
                "invalid_nonce",
                __("Security check failed.", "vira-code"),
            );
            return;
        }

        // Check user capability.
        if (!\ViraCode\vira_code_user_can_manage()) {
            add_settings_error(
                "vira_code_settings",
                "permission_denied",
                __("Permission denied.", "vira-code"),
            );
            return;
        }

        // Save safe mode setting.
        $safe_mode = isset($_POST["safe_mode"]) ? 1 : 0;
        update_option("vira_code_safe_mode", $safe_mode);

        // Save log limit setting.
        $log_limit = isset($_POST["log_limit"]) ? absint($_POST["log_limit"]) : 100;
        update_option("vira_code_log_limit", $log_limit);

        add_settings_error(
            "vira_code_settings",
            "settings_saved",
            __("Settings saved successfully!", "vira-code"),
            "success",
        );
    }

    /**
     * Save conditional logic rules for a snippet.
     *
     * @param int $snippet_id The snippet ID.
     * @param array|null $rules The conditional logic rules.
     * @return void
     */
    protected function saveConditionalLogicRules($snippet_id, $rules)
    {
        $conditional_logic_model = new \ViraCode\Models\ConditionalLogicModel();
        
        if (empty($rules) || !isset($rules['groups']) || empty($rules['groups'])) {
            // Delete existing rules if no rules provided
            $conditional_logic_model->deleteRules($snippet_id);
            return;
        }

        // Save the rules
        $conditional_logic_model->saveRules($snippet_id, $rules);
    }

    /**
     * Export conditional logic rules via AJAX.
     *
     * @return void
     */
    public function exportConditionalRules()
    {
        // Verify nonce.
        check_ajax_referer(\ViraCode\vira_code_nonce_action(), "nonce");

        // Check user capability.
        if (!\ViraCode\vira_code_user_can_manage()) {
            wp_send_json_error([
                "message" => __("Permission denied.", "vira-code"),
            ]);
        }

        $snippet_ids = isset($_POST["snippet_ids"]) ? array_map('absint', $_POST["snippet_ids"]) : [];
        
        if (empty($snippet_ids)) {
            wp_send_json_error([
                "message" => __("No snippets selected.", "vira-code"),
            ]);
        }

        $conditional_logic_model = new \ViraCode\Models\ConditionalLogicModel();
        $export_data = [];

        foreach ($snippet_ids as $snippet_id) {
            $snippet = $this->model->getById($snippet_id);
            $rules = $conditional_logic_model->getRules($snippet_id);
            
            if ($snippet && !empty($rules)) {
                $export_data[] = [
                    'snippet_id' => $snippet_id,
                    'snippet_title' => $snippet['title'],
                    'rules' => $rules,
                    'exported_at' => current_time('mysql')
                ];
            }
        }

        if (empty($export_data)) {
            wp_send_json_error([
                "message" => __("No conditional logic rules found for selected snippets.", "vira-code"),
            ]);
        }

        wp_send_json_success([
            'data' => $export_data,
            'filename' => 'vira-conditional-rules-' . date('Y-m-d-H-i-s') . '.json'
        ]);
    }

    /**
     * Import conditional logic rules via AJAX.
     *
     * @return void
     */
    public function importConditionalRules()
    {
        // Verify nonce.
        check_ajax_referer(\ViraCode\vira_code_nonce_action(), "nonce");

        // Check user capability.
        if (!\ViraCode\vira_code_user_can_manage()) {
            wp_send_json_error([
                "message" => __("Permission denied.", "vira-code"),
            ]);
        }

        $import_data = isset($_POST["import_data"]) ? wp_unslash($_POST["import_data"]) : "";
        $target_snippet_id = isset($_POST["target_snippet_id"]) ? absint($_POST["target_snippet_id"]) : 0;
        
        if (empty($import_data)) {
            wp_send_json_error([
                "message" => __("No import data provided.", "vira-code"),
            ]);
        }

        $parsed_data = json_decode($import_data, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error([
                "message" => __("Invalid JSON format.", "vira-code"),
            ]);
        }

        $conditional_logic_model = new \ViraCode\Models\ConditionalLogicModel();
        $imported_count = 0;

        // Handle single rule import to specific snippet
        if ($target_snippet_id > 0) {
            $snippet = $this->model->getById($target_snippet_id);
            if (!$snippet) {
                wp_send_json_error([
                    "message" => __("Target snippet not found.", "vira-code"),
                ]);
            }

            // If importing single rules object
            if (isset($parsed_data['groups'])) {
                $conditional_logic_model->saveRules($target_snippet_id, $parsed_data);
                $imported_count = 1;
            }
            // If importing from export format
            elseif (is_array($parsed_data) && isset($parsed_data[0]['rules'])) {
                $conditional_logic_model->saveRules($target_snippet_id, $parsed_data[0]['rules']);
                $imported_count = 1;
            }
        }
        // Handle bulk import (create new snippets or update existing)
        else {
            if (!is_array($parsed_data)) {
                wp_send_json_error([
                    "message" => __("Invalid import format for bulk import.", "vira-code"),
                ]);
            }

            foreach ($parsed_data as $item) {
                if (!isset($item['rules']) || !isset($item['snippet_title'])) {
                    continue;
                }

                // Try to find existing snippet by title
                $existing_snippets = $this->model->getAll();
                $existing_snippet = null;
                
                foreach ($existing_snippets as $snippet) {
                    if ($snippet['title'] === $item['snippet_title']) {
                        $existing_snippet = $snippet;
                        break;
                    }
                }

                if ($existing_snippet) {
                    // Update existing snippet rules
                    $conditional_logic_model->saveRules($existing_snippet['id'], $item['rules']);
                    $imported_count++;
                } else {
                    // Create new snippet with rules
                    $new_snippet_data = [
                        'title' => $item['snippet_title'] . ' (Imported)',
                        'description' => 'Imported snippet with conditional logic rules',
                        'code' => '// Add your code here',
                        'type' => 'php',
                        'scope' => 'frontend',
                        'status' => 'inactive'
                    ];
                    
                    $new_snippet_id = $this->model->create($new_snippet_data);
                    if ($new_snippet_id) {
                        $conditional_logic_model->saveRules($new_snippet_id, $item['rules']);
                        $imported_count++;
                    }
                }
            }
        }

        wp_send_json_success([
            "message" => sprintf(
                _n(
                    "Successfully imported %d conditional logic rule.",
                    "Successfully imported %d conditional logic rules.",
                    $imported_count,
                    "vira-code"
                ),
                $imported_count
            ),
            "imported_count" => $imported_count
        ]);
    }

    /**
     * Handle bulk conditional logic operations via AJAX.
     *
     * @return void
     */
    public function bulkConditionalOperations()
    {
        // Verify nonce.
        check_ajax_referer(\ViraCode\vira_code_nonce_action(), "nonce");

        // Check user capability.
        if (!\ViraCode\vira_code_user_can_manage()) {
            wp_send_json_error([
                "message" => __("Permission denied.", "vira-code"),
            ]);
        }

        $operation = isset($_POST["operation"]) ? sanitize_text_field(wp_unslash($_POST["operation"])) : "";
        $snippet_ids = isset($_POST["snippet_ids"]) ? array_map('absint', $_POST["snippet_ids"]) : [];
        
        if (empty($snippet_ids)) {
            wp_send_json_error([
                "message" => __("No snippets selected.", "vira-code"),
            ]);
        }

        $conditional_logic_model = new \ViraCode\Models\ConditionalLogicModel();
        $processed_count = 0;

        switch ($operation) {
            case 'delete_rules':
                foreach ($snippet_ids as $snippet_id) {
                    if ($conditional_logic_model->deleteRules($snippet_id)) {
                        $processed_count++;
                    }
                }
                
                wp_send_json_success([
                    "message" => sprintf(
                        _n(
                            "Successfully deleted conditional logic rules from %d snippet.",
                            "Successfully deleted conditional logic rules from %d snippets.",
                            $processed_count,
                            "vira-code"
                        ),
                        $processed_count
                    )
                ]);
                break;

            case 'copy_rules':
                $source_snippet_id = isset($_POST["source_snippet_id"]) ? absint($_POST["source_snippet_id"]) : 0;
                
                if ($source_snippet_id <= 0) {
                    wp_send_json_error([
                        "message" => __("Source snippet ID is required for copy operation.", "vira-code"),
                    ]);
                }

                $source_rules = $conditional_logic_model->getRules($source_snippet_id);
                if (empty($source_rules)) {
                    wp_send_json_error([
                        "message" => __("Source snippet has no conditional logic rules to copy.", "vira-code"),
                    ]);
                }

                foreach ($snippet_ids as $snippet_id) {
                    if ($snippet_id !== $source_snippet_id) {
                        if ($conditional_logic_model->saveRules($snippet_id, $source_rules)) {
                            $processed_count++;
                        }
                    }
                }
                
                wp_send_json_success([
                    "message" => sprintf(
                        _n(
                            "Successfully copied conditional logic rules to %d snippet.",
                            "Successfully copied conditional logic rules to %d snippets.",
                            $processed_count,
                            "vira-code"
                        ),
                        $processed_count
                    )
                ]);
                break;

            default:
                wp_send_json_error([
                    "message" => __("Invalid bulk operation.", "vira-code"),
                ]);
        }
    }

    /**
     * Get conditional logic preview via AJAX.
     *
     * @return void
     */
    public function getConditionalLogicPreview()
    {
        // Verify nonce.
        check_ajax_referer(\ViraCode\vira_code_nonce_action(), "nonce");

        // Check user capability.
        if (!\ViraCode\vira_code_user_can_manage()) {
            wp_send_json_error([
                "message" => __("Permission denied.", "vira-code"),
            ]);
        }

        $snippet_id = isset($_POST["snippet_id"]) ? absint($_POST["snippet_id"]) : 0;
        
        if ($snippet_id <= 0) {
            wp_send_json_error([
                "message" => __("Invalid snippet ID.", "vira-code"),
            ]);
        }

        $conditional_logic_model = new \ViraCode\Models\ConditionalLogicModel();
        $rules = $conditional_logic_model->getRules($snippet_id);
        
        if (empty($rules)) {
            wp_send_json_error([
                "message" => __("No conditional logic rules found for this snippet.", "vira-code"),
            ]);
        }

        // Format rules for display
        $formatted_rules = $this->formatRulesForPreview($rules);

        wp_send_json_success([
            "rules" => $formatted_rules,
            "count" => count($rules)
        ]);
    }

    /**
     * Format conditional logic rules for preview display.
     *
     * @param array $rules Raw rules from database.
     * @return array
     */
    protected function formatRulesForPreview($rules)
    {
        $formatted = [];
        $condition_types = [
            'page_type' => __('Page Type', 'vira-code'),
            'url_pattern' => __('URL Pattern', 'vira-code'),
            'user_role' => __('User Role', 'vira-code'),
            'device_type' => __('Device Type', 'vira-code'),
            'date_range' => __('Date Range', 'vira-code'),
            'custom_php' => __('Custom PHP', 'vira-code'),
        ];

        $operators = [
            'equals' => __('equals', 'vira-code'),
            'not_equals' => __('does not equal', 'vira-code'),
            'contains' => __('contains', 'vira-code'),
            'starts_with' => __('starts with', 'vira-code'),
            'ends_with' => __('ends with', 'vira-code'),
            'regex' => __('matches regex', 'vira-code'),
            'between' => __('is between', 'vira-code'),
            'before' => __('is before', 'vira-code'),
            'after' => __('is after', 'vira-code'),
        ];

        foreach ($rules as $rule) {
            $condition_type = $condition_types[$rule['condition_type']] ?? $rule['condition_type'];
            $operator = $operators[$rule['condition_operator']] ?? $rule['condition_operator'];
            $value = $rule['condition_value'];

            // Truncate long values
            if (strlen($value) > 50) {
                $value = substr($value, 0, 47) . '...';
            }

            $formatted[] = [
                'type' => $condition_type,
                'operator' => $operator,
                'value' => $value,
                'logic' => $rule['logic_operator'],
                'group' => $rule['rule_group']
            ];
        }

        return $formatted;
    }

    /**
     * Test conditional logic rules via AJAX.
     *
     * @return void
     */
    public function testConditionalLogic()
    {
        // Verify nonce.
        check_ajax_referer(\ViraCode\vira_code_nonce_action(), "nonce");

        // Check user capability.
        if (!\ViraCode\vira_code_user_can_manage()) {
            wp_send_json_error([
                "message" => __(
                    "You do not have permission to perform this action.",
                    "vira-code",
                ),
            ]);
        }

        try {
            $snippet_id = intval($_POST["snippet_id"] ?? 0);
            $rules_json = sanitize_textarea_field($_POST["rules"] ?? "");

            if ($snippet_id <= 0) {
                wp_send_json_error([
                    "message" => __("Invalid snippet ID.", "vira-code"),
                ]);
            }

            // Check if snippet exists.
            $snippet = $this->model->getById($snippet_id);
            if (!$snippet) {
                wp_send_json_error([
                    "message" => __("Snippet not found.", "vira-code"),
                ]);
            }

            // Use the debugging helper function to test the rules.
            $test_result = \ViraCode\vira_code_test_conditional_logic($snippet_id);

            if (isset($test_result["error"])) {
                wp_send_json_error([
                    "message" => $test_result["error"],
                ]);
            }

            // Format the response for the frontend.
            $response = [
                "success" => true,
                "message" => __("Conditional logic test completed.", "vira-code"),
                "evaluation_result" => $test_result["evaluation_result"] ?? null,
                "evaluation_time" => $test_result["evaluation_time"] ?? null,
                "condition_results" => $test_result["condition_results"] ?? [],
                "warnings" => $test_result["warnings"] ?? [],
                "errors" => $test_result["errors"] ?? [],
                "has_rules" => $test_result["has_rules"] ?? false,
                "context_used" => $test_result["context_used"] ?? [],
            ];

            wp_send_json_success($response);
        } catch (\Exception $e) {
            wp_send_json_error([
                "message" => sprintf(
                    /* translators: %s: error message */
                    __("Test failed: %s", "vira-code"),
                    $e->getMessage(),
                ),
                "errors" => [$e->getMessage()],
            ]);
        }
    }
}
