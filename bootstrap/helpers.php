<?php
/**
 * Helper Functions for Vira Code
 *
 * @package ViraCode
 */

namespace ViraCode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get the plugin instance.
 *
 * @return App
 */
function app() {
	return App::getInstance();
}

/**
 * Get the plugin path.
 *
 * @param string $path Optional path to append.
 * @return string
 */
function vira_code_path( $path = '' ) {
	return VIRA_CODE_PATH . ltrim( $path, '/' );
}

/**
 * Get the plugin URL.
 *
 * @param string $path Optional path to append.
 * @return string
 */
function vira_code_url( $path = '' ) {
	return VIRA_CODE_URL . ltrim( $path, '/' );
}

/**
 * Get the plugin version.
 *
 * @return string
 */
function vira_code_version() {
	return VIRA_CODE_VERSION;
}

/**
 * Check if safe mode is enabled.
 *
 * @return bool
 */
function vira_code_is_safe_mode() {
	// Check URL parameter first.
	if ( isset( $_GET['vira_safe_mode'] ) && '1' === $_GET['vira_safe_mode'] ) {
		return true;
	}

	// Check option.
	return (bool) get_option( 'vira_code_safe_mode', 0 );
}

/**
 * Enable safe mode.
 *
 * @return bool
 */
function vira_code_enable_safe_mode() {
	return update_option( 'vira_code_safe_mode', 1 );
}

/**
 * Disable safe mode.
 *
 * @return bool
 */
function vira_code_disable_safe_mode() {
	return update_option( 'vira_code_safe_mode', 0 );
}

/**
 * Get snippet types.
 *
 * @return array
 */
function vira_code_snippet_types() {
	return apply_filters(
		'vira_code/snippet_types',
		array(
			'php'  => __( 'PHP', 'vira-code' ),
			'js'   => __( 'JavaScript', 'vira-code' ),
			'css'  => __( 'CSS', 'vira-code' ),
			'html' => __( 'HTML', 'vira-code' ),
		)
	);
}

/**
 * Get snippet scopes.
 *
 * @return array
 */
function vira_code_snippet_scopes() {
	return apply_filters(
		'vira_code/snippet_scopes',
		array(
			'frontend'   => __( 'Frontend', 'vira-code' ),
			'admin'      => __( 'Admin', 'vira-code' ),
			'both'       => __( 'Both', 'vira-code' ),
			'everywhere' => __( 'Everywhere', 'vira-code' ),
		)
	);
}

/**
 * Get snippet statuses.
 *
 * @return array
 */
function vira_code_snippet_statuses() {
	return apply_filters(
		'vira_code/snippet_statuses',
		array(
			'active'   => __( 'Active', 'vira-code' ),
			'inactive' => __( 'Inactive', 'vira-code' ),
			'error'    => __( 'Error', 'vira-code' ),
		)
	);
}

/**
 * Sanitize snippet code based on type.
 *
 * @param string $code Code to sanitize.
 * @param string $type Snippet type.
 * @return string
 */
function vira_code_sanitize_code( $code, $type ) {
	// Apply filters for custom sanitization.
	$code = apply_filters( 'vira_code/sanitize_code', $code, $type );
	$code = apply_filters( "vira_code/sanitize_code/{$type}", $code );

	// Basic sanitization - remove dangerous functions for non-PHP.
	if ( 'php' !== $type ) {
		$code = wp_kses_post( $code );
	}

	return $code;
}

/**
 * Log snippet execution.
 *
 * @param int    $snippet_id Snippet ID.
 * @param string $status     Execution status (success, error).
 * @param string $message    Optional message.
 * @return void
 */
function vira_code_log_execution( $snippet_id, $status, $message = '' ) {
	$log = array(
		'snippet_id' => $snippet_id,
		'status'     => $status,
		'message'    => $message,
		'timestamp'  => current_time( 'mysql' ),
	);

	// Store in transient for 7 days.
	$logs = get_transient( 'vira_code_execution_logs' );
	if ( ! is_array( $logs ) ) {
		$logs = array();
	}

	$logs[] = $log;

	// Keep only last 100 logs.
	if ( count( $logs ) > 100 ) {
		$logs = array_slice( $logs, -100 );
	}

	set_transient( 'vira_code_execution_logs', $logs, 7 * DAY_IN_SECONDS );

	// Fire action for custom logging.
	do_action( 'vira_code/log_execution', $log );
}

/**
 * Get execution logs.
 *
 * @param int $limit Optional limit.
 * @return array
 */
function vira_code_get_logs( $limit = 100 ) {
	$logs = get_transient( 'vira_code_execution_logs' );
	if ( ! is_array( $logs ) ) {
		return array();
	}

	if ( $limit > 0 ) {
		return array_slice( $logs, -$limit );
	}

	return $logs;
}

/**
	* Get the configured log limit.
	*
	* @return int
	*/
function vira_code_get_log_limit() {
	return (int) get_option( 'vira_code_log_limit', 100 ); // Default to 100.
}

/**
	* Clear execution logs.
	*
	* @return bool
	*/
function vira_code_clear_logs() {
	return delete_transient( 'vira_code_execution_logs' );
}

/**
 * Check if user can manage snippets.
 *
 * @return bool
 */
function vira_code_user_can_manage() {
	return current_user_can( apply_filters( 'vira_code/manage_capability', 'manage_options' ) );
}

/**
 * Get asset URL with version for cache busting.
 *
 * @param string $asset Asset path relative to resources directory.
 * @return string
 */
function vira_code_asset_url( $asset ) {
	$file_path = vira_code_path( 'resources/' . ltrim( $asset, '/' ) );
	$file_url  = vira_code_url( 'resources/' . ltrim( $asset, '/' ) );

	// Add file modification time for cache busting.
	if ( file_exists( $file_path ) ) {
		return add_query_arg( 'ver', filemtime( $file_path ), $file_url );
	}

	return add_query_arg( 'ver', vira_code_version(), $file_url );
}

/**
 * Render a view file.
 *
 * @param string $view View file name (without .php extension).
 * @param array  $data Data to pass to the view.
 * @return void
 */
function vira_code_view( $view, $data = array() ) {
	$view_file = vira_code_path( 'app/Views/' . $view . '.php' );

	if ( ! file_exists( $view_file ) ) {
		return;
	}

	// Extract data to variables.
	if ( ! empty( $data ) ) {
		extract( $data, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
	}

	include $view_file;
}

/**
 * Get nonce action for AJAX requests.
 *
 * @return string
 */
function vira_code_nonce_action() {
	return 'vira_code_nonce_action';
}

/**
 * Get nonce field name.
 *
 * @return string
 */
function vira_code_nonce_field() {
	return 'vira_code_nonce';
}

/**
 * Verify nonce for AJAX requests.
 *
 * @param string $nonce Nonce to verify.
 * @return bool
 */
function vira_code_verify_nonce( $nonce ) {
	return wp_verify_nonce( $nonce, vira_code_nonce_action() );
}

/**
 * Log conditional logic evaluation.
 *
 * @param int    $snippet_id     Snippet ID.
 * @param string $condition_type Condition type or 'evaluation' for overall result.
 * @param bool   $result         Evaluation result.
 * @param string $error          Optional error message.
 * @return void
 */
function vira_code_log_conditional_logic( $snippet_id, $condition_type, $result, $error = null ) {
	$log = array(
		'snippet_id'     => $snippet_id,
		'condition_type' => $condition_type,
		'result'         => $result,
		'error'          => $error,
		'timestamp'      => current_time( 'mysql' ),
	);

	// Store in transient for 7 days.
	$logs = get_transient( 'vira_code_conditional_logs' );
	if ( ! is_array( $logs ) ) {
		$logs = array();
	}

	$logs[] = $log;

	// Keep only last 100 logs.
	if ( count( $logs ) > 100 ) {
		$logs = array_slice( $logs, -100 );
	}

	set_transient( 'vira_code_conditional_logs', $logs, 7 * DAY_IN_SECONDS );

	// Fire action for custom logging.
	do_action( 'vira_code/log_conditional_logic', $log );
}

/**
 * Get conditional logic logs.
 *
 * @param int $limit Optional limit.
 * @return array
 */
function vira_code_get_conditional_logs( $limit = 100 ) {
	$logs = get_transient( 'vira_code_conditional_logs' );
	if ( ! is_array( $logs ) ) {
		return array();
	}

	if ( $limit > 0 ) {
		return array_slice( $logs, -$limit );
	}

	return $logs;
}

/**
 * Clear conditional logic logs.
 *
 * @return bool
 */
function vira_code_clear_conditional_logs() {
	return delete_transient( 'vira_code_conditional_logs' );
}

/**
 * Log performance metrics for conditional logic.
 *
 * @param string $metric_type Metric type (e.g., 'evaluation', 'cache_hit').
 * @param float  $time        Time in seconds.
 * @param array  $context     Additional context data.
 * @return void
 */
function vira_code_log_performance( $metric_type, $time, $context = array() ) {
	$log = array(
		'metric_type' => $metric_type,
		'time'        => $time,
		'context'     => $context,
		'timestamp'   => current_time( 'mysql' ),
	);

	// Store in transient for 6 hours.
	$logs = get_transient( 'vira_code_performance_logs' );
	if ( ! is_array( $logs ) ) {
		$logs = array();
	}

	$logs[] = $log;

	// Keep only last 200 logs.
	if ( count( $logs ) > 200 ) {
		$logs = array_slice( $logs, -200 );
	}

	set_transient( 'vira_code_performance_logs', $logs, 6 * HOUR_IN_SECONDS );

	// Fire action for custom performance monitoring.
	do_action( 'vira_code/log_performance', $log );
}

/**
 * Get performance logs.
 *
 * @param int $limit Optional limit.
 * @return array
 */
function vira_code_get_performance_logs( $limit = 200 ) {
	$logs = get_transient( 'vira_code_performance_logs' );
	if ( ! is_array( $logs ) ) {
		return array();
	}

	if ( $limit > 0 ) {
		return array_slice( $logs, -$limit );
	}

	return $logs;
}

/**
 * Clear performance logs.
 *
 * @return bool
 */
function vira_code_clear_performance_logs() {
	return delete_transient( 'vira_code_performance_logs' );
}

/**
 * Log rule validation errors.
 *
 * @param int   $snippet_id        Snippet ID.
 * @param array $rules             Rules that failed validation.
 * @param array $validation_errors Validation error messages.
 * @return void
 */
function vira_code_log_rule_validation( $snippet_id, $rules, $validation_errors ) {
	$log = array(
		'snippet_id'         => $snippet_id,
		'rules'              => $rules,
		'validation_errors'  => $validation_errors,
		'timestamp'          => current_time( 'mysql' ),
	);

	// Store in transient for 24 hours.
	$logs = get_transient( 'vira_code_rule_validation_logs' );
	if ( ! is_array( $logs ) ) {
		$logs = array();
	}

	$logs[] = $log;

	// Keep only last 50 logs.
	if ( count( $logs ) > 50 ) {
		$logs = array_slice( $logs, -50 );
	}

	set_transient( 'vira_code_rule_validation_logs', $logs, 24 * HOUR_IN_SECONDS );

	// Fire action for custom logging.
	do_action( 'vira_code/log_rule_validation', $log );
}

/**
 * Get rule validation logs.
 *
 * @param int $limit Optional limit.
 * @return array
 */
function vira_code_get_rule_validation_logs( $limit = 50 ) {
	$logs = get_transient( 'vira_code_rule_validation_logs' );
	if ( ! is_array( $logs ) ) {
		return array();
	}

	if ( $limit > 0 ) {
		return array_slice( $logs, -$limit );
	}

	return $logs;
}

/**
 * Clear rule validation logs.
 *
 * @return bool
 */
function vira_code_clear_rule_validation_logs() {
	return delete_transient( 'vira_code_rule_validation_logs' );
}

/**
 * Get conditional logic error statistics.
 *
 * @return array
 */
function vira_code_get_conditional_error_stats() {
	$error_logs = get_transient( 'vira_code_conditional_error_logs' );
	$evaluator_logs = get_transient( 'vira_code_evaluator_error_logs' );
	$validation_logs = get_transient( 'vira_code_rule_validation_logs' );

	$stats = array(
		'total_errors' => 0,
		'error_types' => array(),
		'recent_errors' => 0,
		'most_common_errors' => array(),
	);

	// Count conditional logic errors.
	if ( is_array( $error_logs ) ) {
		$stats['total_errors'] += count( $error_logs );
		
		// Count recent errors (last hour).
		$one_hour_ago = strtotime( '-1 hour' );
		foreach ( $error_logs as $log ) {
			if ( strtotime( $log['timestamp'] ) > $one_hour_ago ) {
				$stats['recent_errors']++;
			}
			
			// Count error types.
			$error_type = $log['error_type'] ?? 'unknown';
			if ( ! isset( $stats['error_types'][ $error_type ] ) ) {
				$stats['error_types'][ $error_type ] = 0;
			}
			$stats['error_types'][ $error_type ]++;
		}
	}

	// Count evaluator errors.
	if ( is_array( $evaluator_logs ) ) {
		$stats['total_errors'] += count( $evaluator_logs );
		
		foreach ( $evaluator_logs as $log ) {
			if ( strtotime( $log['timestamp'] ) > $one_hour_ago ) {
				$stats['recent_errors']++;
			}
			
			$error_type = 'evaluator_' . ( $log['evaluator_type'] ?? 'unknown' );
			if ( ! isset( $stats['error_types'][ $error_type ] ) ) {
				$stats['error_types'][ $error_type ] = 0;
			}
			$stats['error_types'][ $error_type ]++;
		}
	}

	// Count validation errors.
	if ( is_array( $validation_logs ) ) {
		$stats['total_errors'] += count( $validation_logs );
		
		foreach ( $validation_logs as $log ) {
			if ( strtotime( $log['timestamp'] ) > $one_hour_ago ) {
				$stats['recent_errors']++;
			}
			
			if ( ! isset( $stats['error_types']['validation'] ) ) {
				$stats['error_types']['validation'] = 0;
			}
			$stats['error_types']['validation']++;
		}
	}

	// Get most common errors.
	arsort( $stats['error_types'] );
	$stats['most_common_errors'] = array_slice( $stats['error_types'], 0, 5, true );

	return $stats;
}

/**
 * Test conditional logic rules for a snippet.
 *
 * @param int   $snippet_id Snippet ID.
 * @param array $context    Optional custom context for testing.
 * @return array
 */
function vira_code_test_conditional_logic( $snippet_id, $context = array() ) {
	$app = \ViraCode\app();
	
	if ( ! isset( $app->conditionalLogicDebugger ) ) {
		return array(
			'error' => 'Conditional logic debugger not available',
		);
	}

	return $app->conditionalLogicDebugger->testSnippetRules( $snippet_id, $context );
}

/**
 * Get conditional logic debug information for a snippet.
 *
 * @param int $snippet_id Snippet ID.
 * @return array
 */
function vira_code_get_conditional_debug_info( $snippet_id ) {
	$app = \ViraCode\app();
	
	if ( ! isset( $app->conditionalLogicDebugger ) ) {
		return array(
			'error' => 'Conditional logic debugger not available',
		);
	}

	return $app->conditionalLogicDebugger->getDebugInfo( $snippet_id );
}

/**
 * Generate troubleshooting report for conditional logic.
 *
 * @param int $snippet_id Snippet ID.
 * @return array
 */
function vira_code_generate_troubleshooting_report( $snippet_id ) {
	$app = \ViraCode\app();
	
	if ( ! isset( $app->conditionalLogicDebugger ) ) {
		return array(
			'error' => 'Conditional logic debugger not available',
		);
	}

	return $app->conditionalLogicDebugger->generateTroubleshootingReport( $snippet_id );
}

/**
 * Get conditional logic performance statistics.
 *
 * @return array
 */
function vira_code_get_conditional_performance_stats() {
	$app = \ViraCode\app();
	
	if ( ! isset( $app->conditionalLogicPerformanceMonitor ) ) {
		return array(
			'error' => 'Performance monitor not available',
		);
	}

	return $app->conditionalLogicPerformanceMonitor->getPerformanceStatistics();
}

/**
 * Generate conditional logic performance report.
 *
 * @return array
 */
function vira_code_generate_performance_report() {
	$app = \ViraCode\app();
	
	if ( ! isset( $app->conditionalLogicPerformanceMonitor ) ) {
		return array(
			'error' => 'Performance monitor not available',
		);
	}

	return $app->conditionalLogicPerformanceMonitor->generatePerformanceReport();
}

/**
 * Get slow evaluation logs.
 *
 * @param int $limit Optional limit.
 * @return array
 */
function vira_code_get_slow_evaluations( $limit = 50 ) {
	$app = \ViraCode\app();
	
	if ( ! isset( $app->conditionalLogicPerformanceMonitor ) ) {
		return array();
	}

	return $app->conditionalLogicPerformanceMonitor->getSlowEvaluationLogs( $limit );
}

/**
 * Clear all conditional logic performance data.
 *
 * @return bool
 */
function vira_code_clear_conditional_performance_data() {
	$app = \ViraCode\app();
	
	if ( ! isset( $app->conditionalLogicPerformanceMonitor ) ) {
		return false;
	}

	return $app->conditionalLogicPerformanceMonitor->clearPerformanceData();
}

/**
 * Check if conditional logic debug mode is enabled.
 *
 * @return bool
 */
function vira_code_is_conditional_debug_enabled() {
	return defined( 'WP_DEBUG' ) && WP_DEBUG && apply_filters( 'vira_code/conditional_logic_debug_enabled', true );
}

/**
 * Log conditional logic debug message.
 *
 * @param string $message Debug message.
 * @param array  $context Optional context data.
 * @return void
 */
function vira_code_debug_log( $message, $context = array() ) {
	if ( ! vira_code_is_conditional_debug_enabled() ) {
		return;
	}

	$log_message = 'Vira Code Debug: ' . $message;
	
	if ( ! empty( $context ) ) {
		$log_message .= ' - Context: ' . wp_json_encode( $context );
	}

	error_log( $log_message );

	// Fire action for custom debug logging.
	do_action( 'vira_code/debug_logged', $message, $context );
}
