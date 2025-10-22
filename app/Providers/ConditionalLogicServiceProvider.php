<?php
/**
 * Conditional Logic Service Provider
 *
 * @package ViraCode
 */

namespace ViraCode\Providers;

use ViraCode\Services\ConditionalLogicService;
use ViraCode\Services\ConditionalLogicPerformanceMonitor;
use ViraCode\Services\ConditionalLogicDebugger;
use ViraCode\Services\Evaluators\PageTypeEvaluator;
use ViraCode\Services\Evaluators\UrlPatternEvaluator;
use ViraCode\Services\Evaluators\UserRoleEvaluator;
use ViraCode\Services\Evaluators\DeviceTypeEvaluator;
use ViraCode\Services\Evaluators\DateRangeEvaluator;
use ViraCode\Services\Evaluators\CustomPhpEvaluator;
use ViraCode\Models\ConditionalLogicModel;

if (!defined("ABSPATH")) {
    exit();
}

/**
 * ConditionalLogicServiceProvider Class
 *
 * Handles registration of conditional logic functionality including
 * condition evaluators, database initialization, and WordPress hooks.
 */
class ConditionalLogicServiceProvider extends ServiceProvider
{
    /**
     * Conditional logic service instance.
     *
     * @var ConditionalLogicService
     */
    protected $conditionalLogicService;

    /**
     * Performance monitor instance.
     *
     * @var ConditionalLogicPerformanceMonitor
     */
    protected $performanceMonitor;

    /**
     * Debugger instance.
     *
     * @var ConditionalLogicDebugger
     */
    protected $debugger;

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        // Register conditional logic service.
        $this->conditionalLogicService = new ConditionalLogicService();
        $this->app->conditionalLogicService = $this->conditionalLogicService;

        // Register performance monitor.
        $this->performanceMonitor = new ConditionalLogicPerformanceMonitor();
        $this->app->conditionalLogicPerformanceMonitor = $this->performanceMonitor;

        // Register debugger.
        $this->debugger = new ConditionalLogicDebugger( $this->conditionalLogicService, $this->performanceMonitor );
        $this->app->conditionalLogicDebugger = $this->debugger;

        // Register all condition evaluators.
        $this->registerConditionEvaluators();
    }

    /**
     * Boot the service provider.
     *
     * @return void
     */
    public function boot()
    {
        // Set up WordPress hooks and filters.
        $this->registerHooks();

        // Initialize database tables if needed.
        $this->initializeDatabaseTables();
    }

    /**
     * Register all condition evaluators with the service container.
     *
     * @return void
     */
    protected function registerConditionEvaluators()
    {
        $evaluators = [
            'page_type' => new PageTypeEvaluator(),
            'url_pattern' => new UrlPatternEvaluator(),
            'user_role' => new UserRoleEvaluator(),
            'device_type' => new DeviceTypeEvaluator(),
            'date_range' => new DateRangeEvaluator(),
            'custom_php' => new CustomPhpEvaluator(),
        ];

        // Allow filtering of evaluators.
        $evaluators = apply_filters('vira_code/conditional_logic_evaluators', $evaluators);

        // Register each evaluator.
        foreach ($evaluators as $type => $evaluator) {
            $this->conditionalLogicService->registerEvaluator($type, $evaluator);
        }

        // Fire action after evaluators are registered.
        do_action('vira_code/conditional_logic_evaluators_registered', $evaluators, $this->conditionalLogicService);
    }

    /**
     * Set up WordPress hooks and filters for conditional logic.
     *
     * @return void
     */
    protected function registerHooks()
    {
        // Hook into snippet execution to check conditions.
        add_filter('vira_code/should_execute_snippet', [$this, 'checkSnippetConditions'], 10, 2);

        // Add conditional logic meta box to snippet editor.
        add_action('vira_code/snippet_editor_meta_boxes', [$this, 'addConditionalLogicMetaBox']);

        // Save conditional logic rules when snippet is saved.
        add_action('vira_code/snippet_saved', [$this, 'saveConditionalLogicRules'], 10, 2);

        // Delete conditional logic rules when snippet is deleted.
        add_action('vira_code/snippet_deleted', [$this, 'deleteConditionalLogicRules']);

        // Add conditional logic status to snippet list.
        add_filter('vira_code/snippet_list_columns', [$this, 'addConditionalLogicColumn']);
        add_action('vira_code/snippet_list_column_content', [$this, 'renderConditionalLogicColumn'], 10, 2);

        // Clear cache when rules are updated.
        add_action('vira_code/conditional_logic_rules_saved', [$this, 'clearConditionalLogicCache']);
        add_action('vira_code/conditional_logic_rules_deleted', [$this, 'clearConditionalLogicCache']);

        // Performance monitoring hooks.
        add_action('vira_code/conditional_logic_evaluated', [$this, 'logEvaluationPerformance'], 10, 3);
        add_action('vira_code/conditional_logic_evaluation_error', [$this, 'logEvaluationError'], 10, 3);

        // Security validation hooks.
        add_filter('vira_code/conditional_logic_validate_custom_php', [$this, 'validateCustomPhpSecurity'], 10, 2);
    }

    /**
     * Initialize database tables during plugin activation.
     *
     * @return void
     */
    protected function initializeDatabaseTables()
    {
        // Check if we're in activation context or if tables need to be created.
        if (did_action('activate_' . VIRA_CODE_BASENAME) || !$this->tablesExist()) {
            $model = new ConditionalLogicModel();
            $model->createTable();

            // Log table creation.
            do_action('vira_code/conditional_logic_tables_created');
        }

        // Check and optimize database indexes periodically.
        $this->optimizeDatabaseIndexes();
    }

    /**
     * Optimize database indexes for performance.
     *
     * @return void
     */
    protected function optimizeDatabaseIndexes()
    {
        // Only run optimization once per day to avoid overhead.
        $last_optimization = get_option('vira_code_conditional_logic_last_optimization', 0);
        $current_time = current_time('timestamp');
        
        if (($current_time - $last_optimization) < DAY_IN_SECONDS) {
            return;
        }

        $model = new ConditionalLogicModel();
        
        // Analyze table for optimization opportunities.
        $this->analyzeTablePerformance($model);
        
        // Update last optimization timestamp.
        update_option('vira_code_conditional_logic_last_optimization', $current_time);
        
        do_action('vira_code/conditional_logic_database_optimized');
    }

    /**
     * Analyze table performance and suggest optimizations.
     *
     * @param ConditionalLogicModel $model Model instance.
     * @return void
     */
    protected function analyzeTablePerformance($model)
    {
        global $wpdb;
        
        $table_name = $model->getTableName();
        
        // Get table statistics.
        $stats_query = "SHOW TABLE STATUS LIKE '{$table_name}'";
        $table_stats = $wpdb->get_row($stats_query, ARRAY_A);
        
        if ($table_stats) {
            $row_count = (int) $table_stats['Rows'];
            $avg_row_length = (int) $table_stats['Avg_row_length'];
            $data_length = (int) $table_stats['Data_length'];
            
            // Log performance metrics.
            do_action('vira_code/conditional_logic_performance_metrics', [
                'table_rows' => $row_count,
                'avg_row_length' => $avg_row_length,
                'data_length' => $data_length,
                'timestamp' => current_time('mysql'),
            ]);
            
            // Suggest optimization if table is large.
            if ($row_count > 10000) {
                do_action('vira_code/conditional_logic_optimization_suggested', [
                    'reason' => 'large_table',
                    'row_count' => $row_count,
                    'suggestion' => 'Consider archiving old rules or implementing rule cleanup',
                ]);
            }
        }
    }

    /**
     * Check if conditional logic tables exist.
     *
     * @return bool
     */
    protected function tablesExist()
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'vira_snippet_conditions';
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table_name
        ));

        return $table_exists === $table_name;
    }

    /**
     * Check snippet conditions before execution.
     *
     * @param bool $should_execute Current execution status.
     * @param int  $snippet_id     Snippet ID.
     * @return bool
     */
    public function checkSnippetConditions($should_execute, $snippet_id)
    {
        // If already determined not to execute, respect that.
        if (!$should_execute) {
            return false;
        }

        // Check if snippet has conditional logic rules.
        if (!$this->conditionalLogicService->hasRules($snippet_id)) {
            return $should_execute;
        }

        // Evaluate conditional logic rules.
        $conditions_pass = $this->conditionalLogicService->evaluate($snippet_id);

        // Log the decision for debugging.
        do_action('vira_code/conditional_logic_execution_decision', $snippet_id, $conditions_pass, $should_execute);

        return $conditions_pass;
    }

    /**
     * Add conditional logic meta box to snippet editor.
     *
     * @return void
     */
    public function addConditionalLogicMetaBox()
    {
        add_meta_box(
            'vira-code-conditional-logic',
            __('Conditional Logic', 'vira-code'),
            [$this, 'renderConditionalLogicMetaBox'],
            'vira-code-snippet',
            'normal',
            'default'
        );
    }

    /**
     * Render conditional logic meta box content.
     *
     * @param object $snippet Snippet object.
     * @return void
     */
    public function renderConditionalLogicMetaBox($snippet)
    {
        // Get existing rules for the snippet.
        $rules = [];
        if (isset($snippet->id)) {
            $rules = $this->conditionalLogicService->getRules($snippet->id);
        }

        // Include the conditional logic meta box template.
        include VIRA_CODE_PATH . 'app/Views/conditional-logic-meta-box.php';
    }

    /**
     * Save conditional logic rules when snippet is saved.
     *
     * @param int   $snippet_id Snippet ID.
     * @param array $data       Snippet data.
     * @return void
     */
    public function saveConditionalLogicRules($snippet_id, $data)
    {
        // Check if conditional logic data is present.
        if (!isset($data['conditional_logic']) || !is_array($data['conditional_logic'])) {
            return;
        }

        $rules = $data['conditional_logic'];

        // If rules are empty, delete existing rules.
        if (empty($rules) || (isset($rules['enabled']) && !$rules['enabled'])) {
            $this->conditionalLogicService->deleteRules($snippet_id);
            return;
        }

        // Save the rules.
        $result = $this->conditionalLogicService->saveRules($snippet_id, $rules);

        if (!$result) {
            // Log save error.
            do_action('vira_code/conditional_logic_save_error', $snippet_id, $rules);
        }
    }

    /**
     * Delete conditional logic rules when snippet is deleted.
     *
     * @param int $snippet_id Snippet ID.
     * @return void
     */
    public function deleteConditionalLogicRules($snippet_id)
    {
        $this->conditionalLogicService->deleteRules($snippet_id);
    }

    /**
     * Add conditional logic column to snippet list.
     *
     * @param array $columns Existing columns.
     * @return array
     */
    public function addConditionalLogicColumn($columns)
    {
        $columns['conditional_logic'] = __('Conditional Logic', 'vira-code');
        return $columns;
    }

    /**
     * Render conditional logic column content.
     *
     * @param string $column_name Column name.
     * @param object $snippet     Snippet object.
     * @return void
     */
    public function renderConditionalLogicColumn($column_name, $snippet)
    {
        if ('conditional_logic' !== $column_name) {
            return;
        }

        if ($this->conditionalLogicService->hasRules($snippet->id)) {
            $rule_count = $this->conditionalLogicService->getRuleCount($snippet->id);
            echo '<span class="vira-code-conditional-enabled" title="' . 
                 esc_attr(sprintf(__('%d conditions active', 'vira-code'), $rule_count)) . '">';
            echo '<span class="dashicons dashicons-yes-alt"></span> ';
            echo esc_html(sprintf(__('%d rules', 'vira-code'), $rule_count));
            echo '</span>';
        } else {
            echo '<span class="vira-code-conditional-disabled">';
            echo '<span class="dashicons dashicons-minus"></span> ';
            echo esc_html__('None', 'vira-code');
            echo '</span>';
        }
    }

    /**
     * Clear conditional logic cache.
     *
     * @param int $snippet_id Snippet ID.
     * @return void
     */
    public function clearConditionalLogicCache($snippet_id)
    {
        $this->conditionalLogicService->clearCache($snippet_id);
    }

    /**
     * Log evaluation performance for monitoring.
     *
     * @param int   $snippet_id Snippet ID.
     * @param bool  $result     Evaluation result.
     * @param array $rules      Rules that were evaluated.
     * @return void
     */
    public function logEvaluationPerformance($snippet_id, $result, $rules)
    {
        // Only log if performance monitoring is enabled.
        if (!apply_filters('vira_code/conditional_logic_performance_monitoring', false)) {
            return;
        }

        $rule_count = is_array($rules) && isset($rules['groups']) ? 
            count($rules['groups']) : 0;

        do_action('vira_code/performance_log', 'conditional_logic_evaluation', [
            'snippet_id' => $snippet_id,
            'result' => $result,
            'rule_count' => $rule_count,
            'timestamp' => current_time('timestamp'),
        ]);
    }

    /**
     * Log evaluation errors for debugging.
     *
     * @param string $type      Condition type.
     * @param array  $condition Condition data.
     * @param string $error     Error message.
     * @return void
     */
    public function logEvaluationError($type, $condition, $error)
    {
        do_action('vira_code/error_log', 'conditional_logic_evaluation_error', [
            'condition_type' => $type,
            'condition' => $condition,
            'error' => $error,
            'timestamp' => current_time('timestamp'),
        ]);
    }

    /**
     * Validate custom PHP code for security.
     *
     * @param bool   $is_valid Current validation status.
     * @param string $code     PHP code to validate.
     * @return bool
     */
    public function validateCustomPhpSecurity($is_valid, $code)
    {
        // If already invalid, return false.
        if (!$is_valid) {
            return false;
        }

        // List of dangerous functions to block.
        $dangerous_functions = [
            'exec', 'system', 'shell_exec', 'passthru', 'eval',
            'file_get_contents', 'file_put_contents', 'fopen', 'fwrite',
            'curl_exec', 'curl_init', 'fsockopen', 'pfsockopen',
            'stream_socket_client', 'socket_create', 'proc_open',
            'mail', 'wp_mail', 'include', 'require', 'include_once', 'require_once'
        ];

        // Check for dangerous functions.
        foreach ($dangerous_functions as $function) {
            if (preg_match('/\b' . preg_quote($function, '/') . '\s*\(/i', $code)) {
                do_action('vira_code/conditional_logic_security_violation', $function, $code);
                return false;
            }
        }

        // Check for variable variables and dynamic function calls.
        if (preg_match('/\$\$|\$\{|\$[a-zA-Z_][a-zA-Z0-9_]*\s*\(/i', $code)) {
            do_action('vira_code/conditional_logic_security_violation', 'dynamic_execution', $code);
            return false;
        }

        return true;
    }

    /**
     * Get conditional logic service instance.
     *
     * @return ConditionalLogicService
     */
    public function getConditionalLogicService()
    {
        return $this->conditionalLogicService;
    }
}