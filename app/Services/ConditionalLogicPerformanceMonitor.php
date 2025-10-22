<?php
/**
 * Conditional Logic Performance Monitor
 *
 * @package ViraCode
 */

namespace ViraCode\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ConditionalLogicPerformanceMonitor Class
 *
 * Monitors and tracks performance metrics for conditional logic evaluation.
 */
class ConditionalLogicPerformanceMonitor {

	/**
	 * Performance thresholds in seconds.
	 *
	 * @var array
	 */
	protected $thresholds = array(
		'evaluation_slow' => 0.1,    // 100ms
		'evaluation_very_slow' => 0.5, // 500ms
		'condition_slow' => 0.05,    // 50ms
		'condition_very_slow' => 0.2, // 200ms
	);

	/**
	 * Active performance timers.
	 *
	 * @var array
	 */
	protected $timers = array();

	/**
	 * Performance statistics cache.
	 *
	 * @var array
	 */
	protected $stats_cache = array();

	/**
	 * Cache expiration time for statistics.
	 *
	 * @var int
	 */
	protected $stats_cache_expiration = 300; // 5 minutes

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Apply filters to allow customization of thresholds.
		$this->thresholds = apply_filters( 'vira_code/conditional_logic_performance_thresholds', $this->thresholds );

		// Hook into conditional logic actions for automatic monitoring.
		$this->setupHooks();
	}

	/**
	 * Setup WordPress hooks for automatic performance monitoring.
	 *
	 * @return void
	 */
	protected function setupHooks() {
		// Monitor overall evaluation performance.
		add_action( 'vira_code/before_conditional_evaluation', array( $this, 'startEvaluationTimer' ), 10, 1 );
		add_action( 'vira_code/after_conditional_evaluation', array( $this, 'endEvaluationTimer' ), 10, 3 );

		// Monitor individual condition evaluation performance.
		add_action( 'vira_code/conditional_logic_condition_evaluated', array( $this, 'trackConditionPerformance' ), 10, 5 );

		// Monitor cache performance.
		add_action( 'vira_code/conditional_logic_performance_logged', array( $this, 'trackCachePerformance' ), 10, 1 );

		// Monitor error rates.
		add_action( 'vira_code/conditional_logic_error_logged', array( $this, 'trackErrorRate' ), 10, 1 );
		add_action( 'vira_code/evaluator_error_logged', array( $this, 'trackEvaluatorErrorRate' ), 10, 1 );
	}

	/**
	 * Start evaluation timer for a snippet.
	 *
	 * @param array $snippet Snippet data.
	 * @return void
	 */
	public function startEvaluationTimer( $snippet ) {
		$snippet_id = $snippet['id'];
		$this->timers[ 'evaluation_' . $snippet_id ] = microtime( true );
	}

	/**
	 * End evaluation timer and log performance.
	 *
	 * @param array $snippet         Snippet data.
	 * @param bool  $result          Evaluation result.
	 * @param float $evaluation_time Evaluation time in seconds.
	 * @return void
	 */
	public function endEvaluationTimer( $snippet, $result, $evaluation_time ) {
		$snippet_id = $snippet['id'];
		$timer_key = 'evaluation_' . $snippet_id;

		// Calculate time if not provided.
		if ( null === $evaluation_time && isset( $this->timers[ $timer_key ] ) ) {
			$evaluation_time = microtime( true ) - $this->timers[ $timer_key ];
		}

		// Clean up timer.
		unset( $this->timers[ $timer_key ] );

		// Log performance metrics.
		$this->logEvaluationPerformance( $snippet_id, $evaluation_time, $result );

		// Check for slow evaluations.
		$this->checkSlowEvaluation( $snippet_id, $evaluation_time );
	}

	/**
	 * Track individual condition performance.
	 *
	 * @param string $type            Condition type.
	 * @param array  $condition       Condition data.
	 * @param bool   $result          Evaluation result.
	 * @param array  $context         Current context.
	 * @param float  $evaluation_time Evaluation time in seconds.
	 * @return void
	 */
	public function trackConditionPerformance( $type, $condition, $result, $context, $evaluation_time ) {
		// Log condition performance.
		$this->logConditionPerformance( $type, $evaluation_time, $result );

		// Check for slow conditions.
		$this->checkSlowCondition( $type, $evaluation_time );

		// Update condition performance statistics.
		$this->updateConditionStats( $type, $evaluation_time, $result );
	}

	/**
	 * Track cache performance metrics.
	 *
	 * @param array $log_entry Performance log entry.
	 * @return void
	 */
	public function trackCachePerformance( $log_entry ) {
		$metric_type = $log_entry['metric_type'];
		$time = $log_entry['time'];

		// Update cache performance statistics.
		$this->updateCacheStats( $metric_type, $time );
	}

	/**
	 * Track error rates for monitoring.
	 *
	 * @param array $log_entry Error log entry.
	 * @return void
	 */
	public function trackErrorRate( $log_entry ) {
		$error_type = $log_entry['error_type'];
		
		// Update error rate statistics.
		$this->updateErrorStats( 'conditional_logic', $error_type );
	}

	/**
	 * Track evaluator error rates.
	 *
	 * @param array $log_entry Evaluator error log entry.
	 * @return void
	 */
	public function trackEvaluatorErrorRate( $log_entry ) {
		$evaluator_type = $log_entry['evaluator_type'];
		
		// Update evaluator error statistics.
		$this->updateErrorStats( 'evaluator', $evaluator_type );
	}

	/**
	 * Log evaluation performance metrics.
	 *
	 * @param int   $snippet_id      Snippet ID.
	 * @param float $evaluation_time Evaluation time in seconds.
	 * @param bool  $result          Evaluation result.
	 * @return void
	 */
	protected function logEvaluationPerformance( $snippet_id, $evaluation_time, $result ) {
		$log_entry = array(
			'timestamp' => current_time( 'mysql' ),
			'type' => 'evaluation',
			'snippet_id' => $snippet_id,
			'time' => $evaluation_time,
			'result' => $result,
			'is_slow' => $evaluation_time > $this->thresholds['evaluation_slow'],
			'is_very_slow' => $evaluation_time > $this->thresholds['evaluation_very_slow'],
		);

		$this->storePerformanceLog( $log_entry );

		// Fire action for custom performance tracking.
		do_action( 'vira_code/evaluation_performance_tracked', $log_entry );
	}

	/**
	 * Log condition performance metrics.
	 *
	 * @param string $type            Condition type.
	 * @param float  $evaluation_time Evaluation time in seconds.
	 * @param bool   $result          Evaluation result.
	 * @return void
	 */
	protected function logConditionPerformance( $type, $evaluation_time, $result ) {
		$log_entry = array(
			'timestamp' => current_time( 'mysql' ),
			'type' => 'condition',
			'condition_type' => $type,
			'time' => $evaluation_time,
			'result' => $result,
			'is_slow' => $evaluation_time > $this->thresholds['condition_slow'],
			'is_very_slow' => $evaluation_time > $this->thresholds['condition_very_slow'],
		);

		$this->storePerformanceLog( $log_entry );

		// Fire action for custom performance tracking.
		do_action( 'vira_code/condition_performance_tracked', $log_entry );
	}

	/**
	 * Check for slow evaluation and log warning.
	 *
	 * @param int   $snippet_id      Snippet ID.
	 * @param float $evaluation_time Evaluation time in seconds.
	 * @return void
	 */
	protected function checkSlowEvaluation( $snippet_id, $evaluation_time ) {
		if ( $evaluation_time > $this->thresholds['evaluation_very_slow'] ) {
			$this->logSlowEvaluation( $snippet_id, $evaluation_time, 'very_slow' );
		} elseif ( $evaluation_time > $this->thresholds['evaluation_slow'] ) {
			$this->logSlowEvaluation( $snippet_id, $evaluation_time, 'slow' );
		}
	}

	/**
	 * Check for slow condition and log warning.
	 *
	 * @param string $type            Condition type.
	 * @param float  $evaluation_time Evaluation time in seconds.
	 * @return void
	 */
	protected function checkSlowCondition( $type, $evaluation_time ) {
		if ( $evaluation_time > $this->thresholds['condition_very_slow'] ) {
			$this->logSlowCondition( $type, $evaluation_time, 'very_slow' );
		} elseif ( $evaluation_time > $this->thresholds['condition_slow'] ) {
			$this->logSlowCondition( $type, $evaluation_time, 'slow' );
		}
	}

	/**
	 * Log slow evaluation warning.
	 *
	 * @param int    $snippet_id      Snippet ID.
	 * @param float  $evaluation_time Evaluation time in seconds.
	 * @param string $severity        Severity level (slow, very_slow).
	 * @return void
	 */
	protected function logSlowEvaluation( $snippet_id, $evaluation_time, $severity ) {
		$warning = array(
			'timestamp' => current_time( 'mysql' ),
			'type' => 'slow_evaluation',
			'snippet_id' => $snippet_id,
			'time' => $evaluation_time,
			'severity' => $severity,
			'threshold' => $this->thresholds[ 'evaluation_' . $severity ],
		);

		// Store in slow evaluation logs.
		$slow_logs = get_transient( 'vira_code_slow_evaluation_logs' );
		if ( ! is_array( $slow_logs ) ) {
			$slow_logs = array();
		}

		$slow_logs[] = $warning;

		// Keep only last 50 slow evaluation logs.
		if ( count( $slow_logs ) > 50 ) {
			$slow_logs = array_slice( $slow_logs, -50 );
		}

		set_transient( 'vira_code_slow_evaluation_logs', $slow_logs, 12 * HOUR_IN_SECONDS );

		// Fire action for slow evaluation warning.
		do_action( 'vira_code/slow_evaluation_detected', $warning );

		// Log to WordPress error log if enabled.
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( sprintf( 'Vira Code: Slow conditional logic evaluation detected for snippet %d: %.4fs (%s)', $snippet_id, $evaluation_time, $severity ) );
		}
	}

	/**
	 * Log slow condition warning.
	 *
	 * @param string $type            Condition type.
	 * @param float  $evaluation_time Evaluation time in seconds.
	 * @param string $severity        Severity level (slow, very_slow).
	 * @return void
	 */
	protected function logSlowCondition( $type, $evaluation_time, $severity ) {
		$warning = array(
			'timestamp' => current_time( 'mysql' ),
			'type' => 'slow_condition',
			'condition_type' => $type,
			'time' => $evaluation_time,
			'severity' => $severity,
			'threshold' => $this->thresholds[ 'condition_' . $severity ],
		);

		// Store in slow condition logs.
		$slow_logs = get_transient( 'vira_code_slow_condition_logs' );
		if ( ! is_array( $slow_logs ) ) {
			$slow_logs = array();
		}

		$slow_logs[] = $warning;

		// Keep only last 100 slow condition logs.
		if ( count( $slow_logs ) > 100 ) {
			$slow_logs = array_slice( $slow_logs, -100 );
		}

		set_transient( 'vira_code_slow_condition_logs', $slow_logs, 12 * HOUR_IN_SECONDS );

		// Fire action for slow condition warning.
		do_action( 'vira_code/slow_condition_detected', $warning );

		// Log to WordPress error log if enabled.
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( sprintf( 'Vira Code: Slow condition evaluation detected for type %s: %.4fs (%s)', $type, $evaluation_time, $severity ) );
		}
	}

	/**
	 * Store performance log entry.
	 *
	 * @param array $log_entry Performance log entry.
	 * @return void
	 */
	protected function storePerformanceLog( $log_entry ) {
		$performance_logs = get_transient( 'vira_code_detailed_performance_logs' );
		if ( ! is_array( $performance_logs ) ) {
			$performance_logs = array();
		}

		$performance_logs[] = $log_entry;

		// Keep only last 500 detailed performance logs.
		if ( count( $performance_logs ) > 500 ) {
			$performance_logs = array_slice( $performance_logs, -500 );
		}

		set_transient( 'vira_code_detailed_performance_logs', $performance_logs, 6 * HOUR_IN_SECONDS );
	}

	/**
	 * Update condition performance statistics.
	 *
	 * @param string $type            Condition type.
	 * @param float  $evaluation_time Evaluation time in seconds.
	 * @param bool   $result          Evaluation result.
	 * @return void
	 */
	protected function updateConditionStats( $type, $evaluation_time, $result ) {
		$stats_key = 'condition_stats_' . $type;
		$stats = get_transient( $stats_key );

		if ( ! is_array( $stats ) ) {
			$stats = array(
				'total_evaluations' => 0,
				'total_time' => 0.0,
				'min_time' => null,
				'max_time' => 0.0,
				'success_count' => 0,
				'failure_count' => 0,
				'slow_count' => 0,
				'very_slow_count' => 0,
			);
		}

		// Update statistics.
		$stats['total_evaluations']++;
		$stats['total_time'] += $evaluation_time;

		if ( null === $stats['min_time'] || $evaluation_time < $stats['min_time'] ) {
			$stats['min_time'] = $evaluation_time;
		}

		if ( $evaluation_time > $stats['max_time'] ) {
			$stats['max_time'] = $evaluation_time;
		}

		if ( $result ) {
			$stats['success_count']++;
		} else {
			$stats['failure_count']++;
		}

		if ( $evaluation_time > $this->thresholds['condition_slow'] ) {
			$stats['slow_count']++;
		}

		if ( $evaluation_time > $this->thresholds['condition_very_slow'] ) {
			$stats['very_slow_count']++;
		}

		// Calculate average time.
		$stats['average_time'] = $stats['total_time'] / $stats['total_evaluations'];

		// Store updated statistics.
		set_transient( $stats_key, $stats, 24 * HOUR_IN_SECONDS );
	}

	/**
	 * Update cache performance statistics.
	 *
	 * @param string $metric_type Cache metric type.
	 * @param float  $time        Time in seconds.
	 * @return void
	 */
	protected function updateCacheStats( $metric_type, $time ) {
		$stats_key = 'cache_stats';
		$stats = get_transient( $stats_key );

		if ( ! is_array( $stats ) ) {
			$stats = array();
		}

		if ( ! isset( $stats[ $metric_type ] ) ) {
			$stats[ $metric_type ] = array(
				'count' => 0,
				'total_time' => 0.0,
				'average_time' => 0.0,
			);
		}

		$stats[ $metric_type ]['count']++;
		$stats[ $metric_type ]['total_time'] += $time;
		$stats[ $metric_type ]['average_time'] = $stats[ $metric_type ]['total_time'] / $stats[ $metric_type ]['count'];

		set_transient( $stats_key, $stats, 12 * HOUR_IN_SECONDS );
	}

	/**
	 * Update error rate statistics.
	 *
	 * @param string $category   Error category (conditional_logic, evaluator).
	 * @param string $error_type Specific error type.
	 * @return void
	 */
	protected function updateErrorStats( $category, $error_type ) {
		$stats_key = 'error_rate_stats';
		$stats = get_transient( $stats_key );

		if ( ! is_array( $stats ) ) {
			$stats = array();
		}

		$key = $category . '_' . $error_type;

		if ( ! isset( $stats[ $key ] ) ) {
			$stats[ $key ] = array(
				'count' => 0,
				'last_occurrence' => null,
				'hourly_counts' => array(),
			);
		}

		$stats[ $key ]['count']++;
		$stats[ $key ]['last_occurrence'] = current_time( 'mysql' );

		// Track hourly error counts.
		$current_hour = date( 'Y-m-d H:00:00' );
		if ( ! isset( $stats[ $key ]['hourly_counts'][ $current_hour ] ) ) {
			$stats[ $key ]['hourly_counts'][ $current_hour ] = 0;
		}
		$stats[ $key ]['hourly_counts'][ $current_hour ]++;

		// Keep only last 24 hours of hourly data.
		$cutoff_time = strtotime( '-24 hours' );
		foreach ( $stats[ $key ]['hourly_counts'] as $hour => $count ) {
			if ( strtotime( $hour ) < $cutoff_time ) {
				unset( $stats[ $key ]['hourly_counts'][ $hour ] );
			}
		}

		set_transient( $stats_key, $stats, 24 * HOUR_IN_SECONDS );
	}

	/**
	 * Get performance statistics summary.
	 *
	 * @return array
	 */
	public function getPerformanceStatistics() {
		$cache_key = 'performance_statistics_summary';
		
		// Check cache first.
		if ( isset( $this->stats_cache[ $cache_key ] ) ) {
			$cached_time = $this->stats_cache[ $cache_key ]['timestamp'];
			if ( ( time() - $cached_time ) < $this->stats_cache_expiration ) {
				return $this->stats_cache[ $cache_key ]['data'];
			}
		}

		$stats = array(
			'evaluation_stats' => $this->getEvaluationStatistics(),
			'condition_stats' => $this->getConditionStatistics(),
			'cache_stats' => $this->getCacheStatistics(),
			'error_stats' => $this->getErrorStatistics(),
			'slow_operations' => $this->getSlowOperationsSummary(),
			'generated_at' => current_time( 'mysql' ),
		);

		// Cache the results.
		$this->stats_cache[ $cache_key ] = array(
			'data' => $stats,
			'timestamp' => time(),
		);

		return $stats;
	}

	/**
	 * Get evaluation performance statistics.
	 *
	 * @return array
	 */
	protected function getEvaluationStatistics() {
		$performance_logs = get_transient( 'vira_code_detailed_performance_logs' );
		if ( ! is_array( $performance_logs ) ) {
			return array();
		}

		$evaluation_logs = array_filter( $performance_logs, function( $log ) {
			return $log['type'] === 'evaluation';
		} );

		if ( empty( $evaluation_logs ) ) {
			return array();
		}

		$times = array_column( $evaluation_logs, 'time' );
		$slow_count = count( array_filter( $evaluation_logs, function( $log ) {
			return $log['is_slow'];
		} ) );
		$very_slow_count = count( array_filter( $evaluation_logs, function( $log ) {
			return $log['is_very_slow'];
		} ) );

		return array(
			'total_evaluations' => count( $evaluation_logs ),
			'average_time' => array_sum( $times ) / count( $times ),
			'min_time' => min( $times ),
			'max_time' => max( $times ),
			'slow_count' => $slow_count,
			'very_slow_count' => $very_slow_count,
			'slow_percentage' => ( $slow_count / count( $evaluation_logs ) ) * 100,
		);
	}

	/**
	 * Get condition performance statistics.
	 *
	 * @return array
	 */
	protected function getConditionStatistics() {
		$condition_stats = array();

		// Get all condition type statistics.
		$condition_types = array( 'page_type', 'url_pattern', 'user_role', 'device_type', 'date_range', 'custom_php' );
		
		foreach ( $condition_types as $type ) {
			$stats_key = 'condition_stats_' . $type;
			$stats = get_transient( $stats_key );
			
			if ( is_array( $stats ) && $stats['total_evaluations'] > 0 ) {
				$condition_stats[ $type ] = $stats;
			}
		}

		return $condition_stats;
	}

	/**
	 * Get cache performance statistics.
	 *
	 * @return array
	 */
	protected function getCacheStatistics() {
		return get_transient( 'cache_stats' ) ?: array();
	}

	/**
	 * Get error rate statistics.
	 *
	 * @return array
	 */
	protected function getErrorStatistics() {
		return get_transient( 'error_rate_stats' ) ?: array();
	}

	/**
	 * Get slow operations summary.
	 *
	 * @return array
	 */
	protected function getSlowOperationsSummary() {
		$slow_evaluations = get_transient( 'vira_code_slow_evaluation_logs' ) ?: array();
		$slow_conditions = get_transient( 'vira_code_slow_condition_logs' ) ?: array();

		return array(
			'slow_evaluations' => count( $slow_evaluations ),
			'slow_conditions' => count( $slow_conditions ),
			'recent_slow_evaluations' => $this->getRecentSlowOperations( $slow_evaluations ),
			'recent_slow_conditions' => $this->getRecentSlowOperations( $slow_conditions ),
		);
	}

	/**
	 * Get recent slow operations (last hour).
	 *
	 * @param array $operations Slow operations array.
	 * @return int
	 */
	protected function getRecentSlowOperations( $operations ) {
		$one_hour_ago = strtotime( '-1 hour' );
		$recent_count = 0;

		foreach ( $operations as $operation ) {
			if ( strtotime( $operation['timestamp'] ) > $one_hour_ago ) {
				$recent_count++;
			}
		}

		return $recent_count;
	}

	/**
	 * Get detailed performance logs.
	 *
	 * @param int $limit Optional limit.
	 * @return array
	 */
	public function getDetailedPerformanceLogs( $limit = 100 ) {
		$performance_logs = get_transient( 'vira_code_detailed_performance_logs' );
		if ( ! is_array( $performance_logs ) ) {
			return array();
		}

		if ( $limit > 0 ) {
			return array_slice( $performance_logs, -$limit );
		}

		return $performance_logs;
	}

	/**
	 * Get slow evaluation logs.
	 *
	 * @param int $limit Optional limit.
	 * @return array
	 */
	public function getSlowEvaluationLogs( $limit = 50 ) {
		$slow_logs = get_transient( 'vira_code_slow_evaluation_logs' );
		if ( ! is_array( $slow_logs ) ) {
			return array();
		}

		if ( $limit > 0 ) {
			return array_slice( $slow_logs, -$limit );
		}

		return $slow_logs;
	}

	/**
	 * Get slow condition logs.
	 *
	 * @param int $limit Optional limit.
	 * @return array
	 */
	public function getSlowConditionLogs( $limit = 100 ) {
		$slow_logs = get_transient( 'vira_code_slow_condition_logs' );
		if ( ! is_array( $slow_logs ) ) {
			return array();
		}

		if ( $limit > 0 ) {
			return array_slice( $slow_logs, -$limit );
		}

		return $slow_logs;
	}

	/**
	 * Clear all performance monitoring data.
	 *
	 * @return bool
	 */
	public function clearPerformanceData() {
		$result1 = delete_transient( 'vira_code_detailed_performance_logs' );
		$result2 = delete_transient( 'vira_code_slow_evaluation_logs' );
		$result3 = delete_transient( 'vira_code_slow_condition_logs' );
		$result4 = delete_transient( 'cache_stats' );
		$result5 = delete_transient( 'error_rate_stats' );

		// Clear condition-specific statistics.
		$condition_types = array( 'page_type', 'url_pattern', 'user_role', 'device_type', 'date_range', 'custom_php' );
		foreach ( $condition_types as $type ) {
			delete_transient( 'condition_stats_' . $type );
		}

		// Clear cache.
		$this->stats_cache = array();

		do_action( 'vira_code/performance_data_cleared' );

		return $result1 && $result2 && $result3 && $result4 && $result5;
	}

	/**
	 * Generate performance report.
	 *
	 * @return array
	 */
	public function generatePerformanceReport() {
		$stats = $this->getPerformanceStatistics();
		
		$report = array(
			'summary' => array(
				'total_evaluations' => $stats['evaluation_stats']['total_evaluations'] ?? 0,
				'average_evaluation_time' => $stats['evaluation_stats']['average_time'] ?? 0,
				'slow_evaluations_percentage' => $stats['evaluation_stats']['slow_percentage'] ?? 0,
				'total_errors' => array_sum( array_column( $stats['error_stats'], 'count' ) ),
				'cache_hit_rate' => $this->calculateCacheHitRate( $stats['cache_stats'] ),
			),
			'recommendations' => $this->generateRecommendations( $stats ),
			'detailed_stats' => $stats,
			'generated_at' => current_time( 'mysql' ),
		);

		// Fire action to allow custom report modifications.
		return apply_filters( 'vira_code/performance_report', $report );
	}

	/**
	 * Calculate cache hit rate from cache statistics.
	 *
	 * @param array $cache_stats Cache statistics.
	 * @return float
	 */
	protected function calculateCacheHitRate( $cache_stats ) {
		$hits = ( $cache_stats['cache_hit_memory']['count'] ?? 0 ) + ( $cache_stats['cache_hit_object']['count'] ?? 0 );
		$total = $hits + ( $cache_stats['evaluation_complete']['count'] ?? 0 );

		return $total > 0 ? ( $hits / $total ) * 100 : 0;
	}

	/**
	 * Generate performance recommendations based on statistics.
	 *
	 * @param array $stats Performance statistics.
	 * @return array
	 */
	protected function generateRecommendations( $stats ) {
		$recommendations = array();

		// Check for slow evaluations.
		if ( isset( $stats['evaluation_stats']['slow_percentage'] ) && $stats['evaluation_stats']['slow_percentage'] > 10 ) {
			$recommendations[] = array(
				'type' => 'performance',
				'severity' => 'warning',
				'message' => sprintf( 'High percentage of slow evaluations detected (%.1f%%). Consider optimizing conditional logic rules.', $stats['evaluation_stats']['slow_percentage'] ),
			);
		}

		// Check for high error rates.
		$total_errors = array_sum( array_column( $stats['error_stats'], 'count' ) );
		if ( $total_errors > 50 ) {
			$recommendations[] = array(
				'type' => 'reliability',
				'severity' => 'error',
				'message' => sprintf( 'High error count detected (%d errors). Review conditional logic configuration and rules.', $total_errors ),
			);
		}

		// Check cache performance.
		$cache_hit_rate = $this->calculateCacheHitRate( $stats['cache_stats'] );
		if ( $cache_hit_rate < 50 ) {
			$recommendations[] = array(
				'type' => 'caching',
				'severity' => 'info',
				'message' => sprintf( 'Low cache hit rate detected (%.1f%%). Consider increasing cache expiration times.', $cache_hit_rate ),
			);
		}

		// Check for problematic condition types.
		foreach ( $stats['condition_stats'] as $type => $condition_stats ) {
			if ( $condition_stats['slow_count'] > ( $condition_stats['total_evaluations'] * 0.2 ) ) {
				$recommendations[] = array(
					'type' => 'condition_optimization',
					'severity' => 'warning',
					'message' => sprintf( 'Condition type "%s" has high slow evaluation rate. Consider optimizing or simplifying these conditions.', $type ),
				);
			}
		}

		return $recommendations;
	}
}