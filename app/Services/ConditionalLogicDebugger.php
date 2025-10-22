<?php
/**
 * Conditional Logic Debugger
 *
 * @package ViraCode
 */

namespace ViraCode\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ConditionalLogicDebugger Class
 *
 * Provides debugging tools and utilities for troubleshooting conditional logic issues.
 */
class ConditionalLogicDebugger {

	/**
	 * Conditional logic service instance.
	 *
	 * @var ConditionalLogicService
	 */
	protected $service;

	/**
	 * Performance monitor instance.
	 *
	 * @var ConditionalLogicPerformanceMonitor
	 */
	protected $performance_monitor;

	/**
	 * Debug mode enabled.
	 *
	 * @var bool
	 */
	protected $debug_enabled;

	/**
	 * Constructor.
	 *
	 * @param ConditionalLogicService            $service            Conditional logic service.
	 * @param ConditionalLogicPerformanceMonitor $performance_monitor Performance monitor.
	 */
	public function __construct( $service = null, $performance_monitor = null ) {
		$this->service = $service ?: new ConditionalLogicService();
		$this->performance_monitor = $performance_monitor ?: new ConditionalLogicPerformanceMonitor();
		$this->debug_enabled = defined( 'WP_DEBUG' ) && WP_DEBUG;

		// Setup debug hooks if debug mode is enabled.
		if ( $this->debug_enabled ) {
			$this->setupDebugHooks();
		}
	}

	/**
	 * Setup debug hooks for detailed logging.
	 *
	 * @return void
	 */
	protected function setupDebugHooks() {
		// Log all condition evaluations in debug mode.
		add_action( 'vira_code/conditional_logic_condition_evaluated', array( $this, 'logConditionEvaluation' ), 10, 5 );

		// Log rule validation details.
		add_action( 'vira_code/conditional_logic_validation_failed', array( $this, 'logValidationFailure' ), 10, 3 );

		// Log cache operations.
		add_action( 'vira_code/conditional_logic_cache_cleared', array( $this, 'logCacheOperation' ), 10, 1 );

		// Log fallback behavior applications.
		add_action( 'vira_code/conditional_logic_fallback_applied', array( $this, 'logFallbackApplication' ), 10, 4 );
	}

	/**
	 * Test conditional logic rules for a specific snippet.
	 *
	 * @param int   $snippet_id Snippet ID.
	 * @param array $context    Optional custom context for testing.
	 * @return array
	 */
	public function testSnippetRules( $snippet_id, $context = array() ) {
		$start_time = microtime( true );
		
		$test_result = array(
			'snippet_id' => $snippet_id,
			'has_rules' => false,
			'rules' => array(),
			'evaluation_result' => null,
			'evaluation_time' => 0,
			'condition_results' => array(),
			'errors' => array(),
			'warnings' => array(),
			'context_used' => array(),
			'test_timestamp' => current_time( 'mysql' ),
		);

		try {
			// Check if snippet has rules.
			$test_result['has_rules'] = $this->service->hasRules( $snippet_id );
			
			if ( ! $test_result['has_rules'] ) {
				$test_result['evaluation_result'] = true;
				$test_result['warnings'][] = 'No conditional logic rules found for this snippet';
				return $test_result;
			}

			// Get rules for the snippet.
			$rules = $this->service->getRules( $snippet_id );
			$test_result['rules'] = $rules;

			// Convert to structured format for testing.
			$structured_rules = $this->convertDbRulesToStructured( $rules );
			
			// Use provided context or get current context.
			$evaluation_context = ! empty( $context ) ? $context : $this->getCurrentContext();
			$test_result['context_used'] = $evaluation_context;

			// Test each condition individually.
			$test_result['condition_results'] = $this->testIndividualConditions( $structured_rules, $evaluation_context );

			// Perform overall evaluation.
			$test_result['evaluation_result'] = $this->service->evaluate( $snippet_id );
			$test_result['evaluation_time'] = microtime( true ) - $start_time;

			// Analyze results for potential issues.
			$test_result['warnings'] = array_merge( $test_result['warnings'], $this->analyzeTestResults( $test_result ) );

		} catch ( \Exception $e ) {
			$test_result['errors'][] = array(
				'type' => 'exception',
				'message' => $e->getMessage(),
				'file' => $e->getFile(),
				'line' => $e->getLine(),
			);
		}

		$test_result['evaluation_time'] = microtime( true ) - $start_time;

		// Fire action for custom test result processing.
		do_action( 'vira_code/conditional_logic_test_completed', $test_result );

		return $test_result;
	}

	/**
	 * Test individual conditions within rules.
	 *
	 * @param array $structured_rules Structured rules array.
	 * @param array $context          Evaluation context.
	 * @return array
	 */
	protected function testIndividualConditions( $structured_rules, $context ) {
		$condition_results = array();

		if ( ! isset( $structured_rules['groups'] ) || ! is_array( $structured_rules['groups'] ) ) {
			return $condition_results;
		}

		foreach ( $structured_rules['groups'] as $group_index => $group ) {
			if ( ! isset( $group['conditions'] ) || ! is_array( $group['conditions'] ) ) {
				continue;
			}

			foreach ( $group['conditions'] as $condition_index => $condition ) {
				$condition_key = "group_{$group_index}_condition_{$condition_index}";
				$condition_start_time = microtime( true );

				try {
					// Get evaluator for this condition type.
					$evaluator = $this->service->getEvaluator( $condition['type'] );
					
					if ( ! $evaluator ) {
						$condition_results[ $condition_key ] = array(
							'condition' => $condition,
							'result' => false,
							'error' => 'No evaluator found for condition type: ' . $condition['type'],
							'evaluation_time' => 0,
						);
						continue;
					}

					// Evaluate the condition.
					$result = $evaluator->evaluate( $condition, $context );
					$evaluation_time = microtime( true ) - $condition_start_time;

					$condition_results[ $condition_key ] = array(
						'condition' => $condition,
						'result' => $result,
						'evaluation_time' => $evaluation_time,
						'evaluator_type' => get_class( $evaluator ),
					);

					// Add warnings for slow conditions.
					if ( $evaluation_time > 0.1 ) {
						$condition_results[ $condition_key ]['warning'] = 'Slow condition evaluation detected';
					}

				} catch ( \Exception $e ) {
					$condition_results[ $condition_key ] = array(
						'condition' => $condition,
						'result' => false,
						'error' => $e->getMessage(),
						'evaluation_time' => microtime( true ) - $condition_start_time,
					);
				}
			}
		}

		return $condition_results;
	}

	/**
	 * Analyze test results for potential issues.
	 *
	 * @param array $test_result Test result data.
	 * @return array
	 */
	protected function analyzeTestResults( $test_result ) {
		$warnings = array();

		// Check for slow evaluation.
		if ( $test_result['evaluation_time'] > 0.1 ) {
			$warnings[] = sprintf( 'Slow evaluation detected: %.4f seconds', $test_result['evaluation_time'] );
		}

		// Check for failed conditions.
		$failed_conditions = 0;
		foreach ( $test_result['condition_results'] as $condition_result ) {
			if ( isset( $condition_result['error'] ) ) {
				$failed_conditions++;
			}
		}

		if ( $failed_conditions > 0 ) {
			$warnings[] = sprintf( '%d condition(s) failed to evaluate', $failed_conditions );
		}

		// Check for complex rule structures.
		$total_conditions = count( $test_result['condition_results'] );
		if ( $total_conditions > 10 ) {
			$warnings[] = sprintf( 'Complex rule structure with %d conditions may impact performance', $total_conditions );
		}

		return $warnings;
	}

	/**
	 * Get comprehensive debug information for a snippet.
	 *
	 * @param int $snippet_id Snippet ID.
	 * @return array
	 */
	public function getDebugInfo( $snippet_id ) {
		$debug_info = array(
			'snippet_id' => $snippet_id,
			'timestamp' => current_time( 'mysql' ),
			'rules_info' => $this->getRulesDebugInfo( $snippet_id ),
			'performance_info' => $this->getPerformanceDebugInfo( $snippet_id ),
			'error_info' => $this->getErrorDebugInfo( $snippet_id ),
			'cache_info' => $this->getCacheDebugInfo( $snippet_id ),
			'system_info' => $this->getSystemDebugInfo(),
		);

		return apply_filters( 'vira_code/conditional_logic_debug_info', $debug_info, $snippet_id );
	}

	/**
	 * Get rules debug information.
	 *
	 * @param int $snippet_id Snippet ID.
	 * @return array
	 */
	protected function getRulesDebugInfo( $snippet_id ) {
		$info = array(
			'has_rules' => $this->service->hasRules( $snippet_id ),
			'rule_count' => 0,
			'rules' => array(),
			'structured_rules' => array(),
			'validation_status' => 'unknown',
		);

		if ( $info['has_rules'] ) {
			$rules = $this->service->getRules( $snippet_id );
			$info['rule_count'] = count( $rules );
			$info['rules'] = $rules;

			// Convert to structured format.
			try {
				$structured_rules = $this->convertDbRulesToStructured( $rules );
				$info['structured_rules'] = $structured_rules;
				$info['validation_status'] = 'valid';
			} catch ( \Exception $e ) {
				$info['validation_status'] = 'invalid';
				$info['validation_error'] = $e->getMessage();
			}
		}

		return $info;
	}

	/**
	 * Get performance debug information.
	 *
	 * @param int $snippet_id Snippet ID.
	 * @return array
	 */
	protected function getPerformanceDebugInfo( $snippet_id ) {
		$performance_logs = $this->performance_monitor->getDetailedPerformanceLogs( 50 );
		
		// Filter logs for this snippet.
		$snippet_logs = array_filter( $performance_logs, function( $log ) use ( $snippet_id ) {
			return isset( $log['snippet_id'] ) && $log['snippet_id'] == $snippet_id;
		} );

		$info = array(
			'recent_evaluations' => count( $snippet_logs ),
			'performance_logs' => array_values( $snippet_logs ),
			'slow_evaluations' => array(),
		);

		// Get slow evaluation logs for this snippet.
		$slow_logs = $this->performance_monitor->getSlowEvaluationLogs( 20 );
		$snippet_slow_logs = array_filter( $slow_logs, function( $log ) use ( $snippet_id ) {
			return isset( $log['snippet_id'] ) && $log['snippet_id'] == $snippet_id;
		} );

		$info['slow_evaluations'] = array_values( $snippet_slow_logs );

		return $info;
	}

	/**
	 * Get error debug information.
	 *
	 * @param int $snippet_id Snippet ID.
	 * @return array
	 */
	protected function getErrorDebugInfo( $snippet_id ) {
		$error_logs = $this->service->getErrorLogs( 50 );
		
		// Filter logs for this snippet.
		$snippet_errors = array_filter( $error_logs, function( $log ) use ( $snippet_id ) {
			return isset( $log['context']['snippet_id'] ) && $log['context']['snippet_id'] == $snippet_id;
		} );

		return array(
			'recent_errors' => count( $snippet_errors ),
			'error_logs' => array_values( $snippet_errors ),
		);
	}

	/**
	 * Get cache debug information.
	 *
	 * @param int $snippet_id Snippet ID.
	 * @return array
	 */
	protected function getCacheDebugInfo( $snippet_id ) {
		$cache_stats = $this->service->getCacheStats();
		
		return array(
			'cache_stats' => $cache_stats,
			'rules_cached' => isset( $cache_stats['rule_cache_count'] ) && $cache_stats['rule_cache_count'] > 0,
			'evaluation_cached' => isset( $cache_stats['evaluation_cache_count'] ) && $cache_stats['evaluation_cache_count'] > 0,
		);
	}

	/**
	 * Get system debug information.
	 *
	 * @return array
	 */
	protected function getSystemDebugInfo() {
		return array(
			'php_version' => PHP_VERSION,
			'wordpress_version' => get_bloginfo( 'version' ),
			'debug_enabled' => $this->debug_enabled,
			'memory_usage' => memory_get_usage( true ),
			'memory_peak' => memory_get_peak_usage( true ),
			'time_limit' => ini_get( 'max_execution_time' ),
			'registered_evaluators' => array_keys( $this->getRegisteredEvaluators() ),
		);
	}

	/**
	 * Get registered evaluators.
	 *
	 * @return array
	 */
	protected function getRegisteredEvaluators() {
		// This would need to be implemented based on how evaluators are stored in the service.
		// For now, return the standard evaluators.
		return array(
			'page_type' => 'PageTypeEvaluator',
			'url_pattern' => 'UrlPatternEvaluator',
			'user_role' => 'UserRoleEvaluator',
			'device_type' => 'DeviceTypeEvaluator',
			'date_range' => 'DateRangeEvaluator',
			'custom_php' => 'CustomPhpEvaluator',
		);
	}

	/**
	 * Convert database rules to structured format.
	 *
	 * @param array $db_rules Database rules.
	 * @return array
	 */
	protected function convertDbRulesToStructured( $db_rules ) {
		// Use the service method if available, otherwise implement basic conversion.
		if ( method_exists( $this->service, 'convertDbRulesToStructured' ) ) {
			return $this->service->convertDbRulesToStructured( $db_rules );
		}

		// Basic conversion implementation.
		$structured = array(
			'enabled' => true,
			'logic' => 'AND',
			'groups' => array(),
		);

		$groups = array();
		foreach ( $db_rules as $rule ) {
			$group_id = $rule['rule_group'] ?? 0;
			if ( ! isset( $groups[ $group_id ] ) ) {
				$groups[ $group_id ] = array(
					'logic' => $rule['logic_operator'] ?? 'AND',
					'conditions' => array(),
				);
			}

			$groups[ $group_id ]['conditions'][] = array(
				'type' => $rule['condition_type'],
				'operator' => $rule['condition_operator'],
				'value' => $rule['condition_value'],
			);
		}

		$structured['groups'] = array_values( $groups );
		return $structured;
	}

	/**
	 * Get current context for evaluation.
	 *
	 * @return array
	 */
	protected function getCurrentContext() {
		// Use the service method if available.
		if ( method_exists( $this->service, 'getCurrentContext' ) ) {
			return $this->service->getCurrentContext();
		}

		// Basic context implementation.
		return array(
			'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
			'is_home' => is_home(),
			'is_single' => is_single(),
			'is_page' => is_page(),
			'is_admin' => is_admin(),
			'is_user_logged_in' => is_user_logged_in(),
			'current_user_id' => get_current_user_id(),
			'wp_is_mobile' => wp_is_mobile(),
			'current_time' => current_time( 'timestamp' ),
		);
	}

	/**
	 * Generate troubleshooting report.
	 *
	 * @param int $snippet_id Snippet ID.
	 * @return array
	 */
	public function generateTroubleshootingReport( $snippet_id ) {
		$test_result = $this->testSnippetRules( $snippet_id );
		$debug_info = $this->getDebugInfo( $snippet_id );
		$performance_stats = $this->performance_monitor->getPerformanceStatistics();

		$report = array(
			'snippet_id' => $snippet_id,
			'generated_at' => current_time( 'mysql' ),
			'summary' => $this->generateReportSummary( $test_result, $debug_info ),
			'test_results' => $test_result,
			'debug_info' => $debug_info,
			'performance_overview' => $performance_stats,
			'recommendations' => $this->generateTroubleshootingRecommendations( $test_result, $debug_info ),
		);

		return apply_filters( 'vira_code/troubleshooting_report', $report, $snippet_id );
	}

	/**
	 * Generate report summary.
	 *
	 * @param array $test_result Test results.
	 * @param array $debug_info  Debug information.
	 * @return array
	 */
	protected function generateReportSummary( $test_result, $debug_info ) {
		$summary = array(
			'status' => 'unknown',
			'has_rules' => $test_result['has_rules'],
			'evaluation_result' => $test_result['evaluation_result'],
			'total_conditions' => count( $test_result['condition_results'] ),
			'failed_conditions' => 0,
			'performance_issues' => false,
			'errors_detected' => ! empty( $test_result['errors'] ),
		);

		// Count failed conditions.
		foreach ( $test_result['condition_results'] as $condition_result ) {
			if ( isset( $condition_result['error'] ) ) {
				$summary['failed_conditions']++;
			}
		}

		// Check for performance issues.
		if ( $test_result['evaluation_time'] > 0.1 || $summary['failed_conditions'] > 0 ) {
			$summary['performance_issues'] = true;
		}

		// Determine overall status.
		if ( $summary['errors_detected'] || $summary['failed_conditions'] > 0 ) {
			$summary['status'] = 'error';
		} elseif ( $summary['performance_issues'] ) {
			$summary['status'] = 'warning';
		} else {
			$summary['status'] = 'ok';
		}

		return $summary;
	}

	/**
	 * Generate troubleshooting recommendations.
	 *
	 * @param array $test_result Test results.
	 * @param array $debug_info  Debug information.
	 * @return array
	 */
	protected function generateTroubleshootingRecommendations( $test_result, $debug_info ) {
		$recommendations = array();

		// Check for errors.
		if ( ! empty( $test_result['errors'] ) ) {
			$recommendations[] = array(
				'type' => 'error',
				'message' => 'Errors detected during evaluation. Check error logs and rule configuration.',
				'action' => 'review_errors',
			);
		}

		// Check for failed conditions.
		$failed_conditions = 0;
		foreach ( $test_result['condition_results'] as $condition_result ) {
			if ( isset( $condition_result['error'] ) ) {
				$failed_conditions++;
			}
		}

		if ( $failed_conditions > 0 ) {
			$recommendations[] = array(
				'type' => 'warning',
				'message' => sprintf( '%d condition(s) failed to evaluate. Review condition configuration.', $failed_conditions ),
				'action' => 'review_conditions',
			);
		}

		// Check for performance issues.
		if ( $test_result['evaluation_time'] > 0.1 ) {
			$recommendations[] = array(
				'type' => 'performance',
				'message' => sprintf( 'Slow evaluation detected (%.4f seconds). Consider simplifying rules.', $test_result['evaluation_time'] ),
				'action' => 'optimize_rules',
			);
		}

		// Check for complex rules.
		$total_conditions = count( $test_result['condition_results'] );
		if ( $total_conditions > 10 ) {
			$recommendations[] = array(
				'type' => 'optimization',
				'message' => sprintf( 'Complex rule structure with %d conditions. Consider breaking into multiple snippets.', $total_conditions ),
				'action' => 'simplify_rules',
			);
		}

		return $recommendations;
	}

	/**
	 * Log condition evaluation for debugging.
	 *
	 * @param string $type            Condition type.
	 * @param array  $condition       Condition data.
	 * @param bool   $result          Evaluation result.
	 * @param array  $context         Current context.
	 * @param float  $evaluation_time Evaluation time.
	 * @return void
	 */
	public function logConditionEvaluation( $type, $condition, $result, $context, $evaluation_time ) {
		if ( ! $this->debug_enabled ) {
			return;
		}

		error_log( sprintf(
			'Vira Code Debug: Condition [%s] evaluated to %s in %.4fs - Condition: %s',
			$type,
			$result ? 'TRUE' : 'FALSE',
			$evaluation_time,
			wp_json_encode( $condition )
		) );
	}

	/**
	 * Log validation failure for debugging.
	 *
	 * @param int   $snippet_id Snippet ID.
	 * @param array $rules      Rules that failed validation.
	 * @param array $errors     Validation errors.
	 * @return void
	 */
	public function logValidationFailure( $snippet_id, $rules, $errors ) {
		if ( ! $this->debug_enabled ) {
			return;
		}

		error_log( sprintf(
			'Vira Code Debug: Rule validation failed for snippet %d - Errors: %s',
			$snippet_id,
			wp_json_encode( $errors )
		) );
	}

	/**
	 * Log cache operation for debugging.
	 *
	 * @param int $snippet_id Snippet ID.
	 * @return void
	 */
	public function logCacheOperation( $snippet_id ) {
		if ( ! $this->debug_enabled ) {
			return;
		}

		error_log( sprintf( 'Vira Code Debug: Cache cleared for snippet %d', $snippet_id ) );
	}

	/**
	 * Log fallback behavior application.
	 *
	 * @param int    $snippet_id Snippet ID.
	 * @param string $reason     Fallback reason.
	 * @param bool   $result     Fallback result.
	 * @param array  $context    Additional context.
	 * @return void
	 */
	public function logFallbackApplication( $snippet_id, $reason, $result, $context ) {
		if ( ! $this->debug_enabled ) {
			return;
		}

		error_log( sprintf(
			'Vira Code Debug: Fallback applied for snippet %d - Reason: %s, Result: %s',
			$snippet_id,
			$reason,
			$result ? 'ALLOW' : 'DENY'
		) );
	}
}