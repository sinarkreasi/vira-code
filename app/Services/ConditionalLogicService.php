<?php
/**
 * Conditional Logic Service
 *
 * @package ViraCode
 */

namespace ViraCode\Services;

use ViraCode\Models\ConditionalLogicModel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ConditionalLogicService Class
 *
 * Core service for evaluating conditional logic rules and managing rule caching.
 */
class ConditionalLogicService {

	/**
	 * Conditional logic model instance.
	 *
	 * @var ConditionalLogicModel
	 */
	protected $model;

	/**
	 * Rule cache for performance optimization.
	 *
	 * @var array
	 */
	protected $rule_cache = array();

	/**
	 * Evaluation result cache.
	 *
	 * @var array
	 */
	protected $evaluation_cache = array();

	/**
	 * Cache group for WordPress object cache.
	 *
	 * @var string
	 */
	protected $cache_group = 'vira_code_conditional_logic';

	/**
	 * Cache expiration time in seconds.
	 *
	 * @var int
	 */
	protected $cache_expiration = 3600; // 1 hour

	/**
	 * Registered condition evaluators.
	 *
	 * @var array
	 */
	protected $evaluators = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->model = new ConditionalLogicModel();
	}

	/**
	 * Evaluate conditional logic rules for a snippet.
	 *
	 * @param int $snippet_id Snippet ID.
	 * @return bool True if all conditions pass, false otherwise.
	 */
	public function evaluate( $snippet_id ) {
		$start_time = microtime( true );
		$error_context = array(
			'snippet_id' => $snippet_id,
			'method' => 'evaluate',
		);

		try {
			// Check memory evaluation cache first.
			$memory_cache_key = 'eval_' . $snippet_id;
			if ( isset( $this->evaluation_cache[ $memory_cache_key ] ) ) {
				$this->logPerformanceMetric( 'cache_hit_memory', microtime( true ) - $start_time, $snippet_id );
				return $this->evaluation_cache[ $memory_cache_key ];
			}

			// Create context-aware cache key for evaluation results.
			$context_hash = $this->getContextHash();
			$object_cache_key = 'snippet_eval_' . $snippet_id . '_' . $context_hash;
			
			// Check WordPress object cache for evaluation result.
			$cached_result = wp_cache_get( $object_cache_key, $this->cache_group );
			if ( false !== $cached_result ) {
				$this->evaluation_cache[ $memory_cache_key ] = $cached_result;
				$this->logPerformanceMetric( 'cache_hit_object', microtime( true ) - $start_time, $snippet_id );
				return $cached_result;
			}

			// Get rules for the snippet with error handling.
			$rules = $this->getRulesWithErrorHandling( $snippet_id );

			// If no rules exist, allow execution.
			if ( empty( $rules ) ) {
				$result = true;
				$this->evaluation_cache[ $memory_cache_key ] = $result;
				// Cache for shorter time since no rules means always true.
				wp_cache_set( $object_cache_key, $result, $this->cache_group, 300 ); // 5 minutes
				$this->logPerformanceMetric( 'evaluation_no_rules', microtime( true ) - $start_time, $snippet_id );
				return $result;
			}

			// Convert database rules to structured format with error handling.
			$structured_rules = $this->convertDbRulesToStructuredWithErrorHandling( $rules );
			if ( false === $structured_rules ) {
				// Rule conversion failed, apply fallback behavior.
				$result = $this->applyFallbackBehavior( $snippet_id, 'rule_conversion_failed', $error_context );
				$this->evaluation_cache[ $memory_cache_key ] = $result;
				return $result;
			}

			// Evaluate the structured rules with comprehensive error handling.
			$result = $this->evaluateStructuredRulesWithErrorHandling( $structured_rules, $snippet_id );

			// Cache the result in memory.
			$this->evaluation_cache[ $memory_cache_key ] = $result;

			// Cache evaluation result in object cache for shorter time (context-dependent).
			wp_cache_set( $object_cache_key, $result, $this->cache_group, 600 ); // 10 minutes
			
			// Track this cache key for invalidation.
			$this->trackEvaluationCacheKey( $snippet_id, $object_cache_key );

			// Log performance metrics.
			$evaluation_time = microtime( true ) - $start_time;
			$this->logPerformanceMetric( 'evaluation_complete', $evaluation_time, $snippet_id );

			// Fire action for logging/debugging.
			do_action( 'vira_code/conditional_logic_evaluated', $snippet_id, $result, $structured_rules, $evaluation_time );

			return $result;

		} catch ( \Exception $e ) {
			return $this->handleEvaluationException( $e, $snippet_id, $error_context, microtime( true ) - $start_time );
		} catch ( \Error $e ) {
			return $this->handleEvaluationFatalError( $e, $snippet_id, $error_context, microtime( true ) - $start_time );
		} catch ( \Throwable $e ) {
			return $this->handleEvaluationThrowable( $e, $snippet_id, $error_context, microtime( true ) - $start_time );
		}
	}

	/**
	 * Get rules for a specific snippet.
	 *
	 * @param int $snippet_id Snippet ID.
	 * @return array
	 */
	public function getRules( $snippet_id ) {
		// Check memory cache first.
		$memory_cache_key = 'rules_' . $snippet_id;
		if ( isset( $this->rule_cache[ $memory_cache_key ] ) ) {
			return $this->rule_cache[ $memory_cache_key ];
		}

		// Check WordPress object cache.
		$object_cache_key = 'snippet_rules_' . $snippet_id;
		$rules = wp_cache_get( $object_cache_key, $this->cache_group );

		if ( false === $rules ) {
			// Get rules from database.
			$rules = $this->model->getRules( $snippet_id );

			// Cache in WordPress object cache.
			wp_cache_set( $object_cache_key, $rules, $this->cache_group, $this->cache_expiration );
		}

		// Cache in memory for this request.
		$this->rule_cache[ $memory_cache_key ] = $rules;

		return $rules;
	}

	/**
	 * Save rules for a specific snippet.
	 *
	 * @param int   $snippet_id Snippet ID.
	 * @param array $rules      Array of rule data.
	 * @return bool
	 */
	public function saveRules( $snippet_id, $rules ) {
		// Validate rules before saving.
		$validation_result = $this->validateRules( $rules );
		if ( ! $validation_result['valid'] ) {
			do_action( 'vira_code/conditional_logic_validation_failed', $snippet_id, $rules, $validation_result['errors'] );
			return false;
		}

		// Convert structured rules to database format if needed.
		$db_rules = $this->convertStructuredRulesToDb( $rules, $snippet_id );

		// Save to database.
		$result = $this->model->saveRules( $snippet_id, $db_rules );

		if ( $result ) {
			// Clear caches for this snippet.
			$this->clearCache( $snippet_id );
			
			// Invalidate related caches.
			$this->invalidateRelatedCaches( $snippet_id );
			
			do_action( 'vira_code/conditional_logic_rules_saved', $snippet_id, $rules );
		}

		return $result;
	}

	/**
	 * Delete rules for a specific snippet.
	 *
	 * @param int $snippet_id Snippet ID.
	 * @return bool
	 */
	public function deleteRules( $snippet_id ) {
		$result = $this->model->deleteRules( $snippet_id );

		if ( $result ) {
			// Clear caches for this snippet.
			$this->clearCache( $snippet_id );
			
			// Invalidate related caches.
			$this->invalidateRelatedCaches( $snippet_id );
			
			do_action( 'vira_code/conditional_logic_rules_deleted', $snippet_id );
		}

		return $result;
	}

	/**
	 * Register a condition evaluator.
	 *
	 * @param string $type      Condition type.
	 * @param object $evaluator Evaluator instance.
	 * @return void
	 */
	public function registerEvaluator( $type, $evaluator ) {
		$this->evaluators[ $type ] = $evaluator;
	}

	/**
	 * Get registered evaluator for a condition type.
	 *
	 * @param string $type Condition type.
	 * @return object|null
	 */
	public function getEvaluator( $type ) {
		return isset( $this->evaluators[ $type ] ) ? $this->evaluators[ $type ] : null;
	}

	/**
	 * Convert database rules to structured format.
	 *
	 * @param array $db_rules Database rules.
	 * @return array
	 */
	protected function convertDbRulesToStructured( $db_rules ) {
		$structured = array(
			'enabled' => true,
			'logic'   => 'AND',
			'groups'  => array(),
		);

		// Group rules by rule_group.
		$groups = array();
		foreach ( $db_rules as $rule ) {
			$group_id = $rule['rule_group'];
			if ( ! isset( $groups[ $group_id ] ) ) {
				$groups[ $group_id ] = array(
					'logic'      => $rule['logic_operator'],
					'conditions' => array(),
				);
			}

			$groups[ $group_id ]['conditions'][] = array(
				'type'     => $rule['condition_type'],
				'operator' => $rule['condition_operator'],
				'value'    => $rule['condition_value'],
			);
		}

		$structured['groups'] = array_values( $groups );

		return $structured;
	}

	/**
	 * Convert structured rules to database format.
	 *
	 * @param array $structured_rules Structured rules.
	 * @param int   $snippet_id       Snippet ID.
	 * @return array
	 */
	protected function convertStructuredRulesToDb( $structured_rules, $snippet_id ) {
		$db_rules = array();
		$priority = 0;

		if ( isset( $structured_rules['groups'] ) && is_array( $structured_rules['groups'] ) ) {
			foreach ( $structured_rules['groups'] as $group_index => $group ) {
				$logic_operator = isset( $group['logic'] ) ? strtoupper( $group['logic'] ) : 'AND';
				
				if ( isset( $group['conditions'] ) && is_array( $group['conditions'] ) ) {
					foreach ( $group['conditions'] as $condition ) {
						$db_rules[] = array(
							'snippet_id'         => $snippet_id,
							'rule_group'         => $group_index,
							'condition_type'     => $condition['type'],
							'condition_operator' => $condition['operator'],
							'condition_value'    => $condition['value'],
							'logic_operator'     => $logic_operator,
							'priority'           => $priority++,
						);
					}
				}
			}
		}

		return $db_rules;
	}

	/**
	 * Evaluate structured rules.
	 *
	 * @param array $structured_rules Structured rules.
	 * @return bool
	 */
	protected function evaluateStructuredRules( $structured_rules ) {
		if ( ! isset( $structured_rules['enabled'] ) || ! $structured_rules['enabled'] ) {
			return true;
		}

		if ( empty( $structured_rules['groups'] ) ) {
			return true;
		}

		$group_results = array();

		// Evaluate each group.
		foreach ( $structured_rules['groups'] as $group ) {
			$group_result = $this->evaluateGroup( $group );
			$group_results[] = $group_result;
		}

		// Apply top-level logic (default AND between groups).
		$top_logic = isset( $structured_rules['logic'] ) ? strtoupper( $structured_rules['logic'] ) : 'AND';

		if ( 'OR' === $top_logic ) {
			return in_array( true, $group_results, true );
		} else {
			return ! in_array( false, $group_results, true );
		}
	}

	/**
	 * Evaluate a single group of conditions.
	 *
	 * @param array $group Group data.
	 * @return bool
	 */
	protected function evaluateGroup( $group ) {
		if ( empty( $group['conditions'] ) ) {
			return true;
		}

		$condition_results = array();
		$logic = isset( $group['logic'] ) ? strtoupper( $group['logic'] ) : 'AND';

		// Evaluate each condition in the group.
		foreach ( $group['conditions'] as $condition ) {
			$result = $this->evaluateCondition( $condition );
			$condition_results[] = $result;

			// Early exit optimization for AND logic.
			if ( 'AND' === $logic && false === $result ) {
				return false;
			}

			// Early exit optimization for OR logic.
			if ( 'OR' === $logic && true === $result ) {
				return true;
			}
		}

		// Apply group logic.
		if ( 'OR' === $logic ) {
			return in_array( true, $condition_results, true );
		} else {
			return ! in_array( false, $condition_results, true );
		}
	}

	/**
	 * Evaluate a single condition.
	 *
	 * @param array $condition Condition data.
	 * @return bool
	 */
	protected function evaluateCondition( $condition ) {
		$start_time = microtime( true );
		$type = isset( $condition['type'] ) ? $condition['type'] : 'unknown';
		$error_context = array(
			'condition_type' => $type,
			'condition' => $condition,
			'method' => 'evaluateCondition',
		);

		try {
			// Validate condition structure.
			if ( ! $this->isValidConditionStructure( $condition ) ) {
				$this->logConditionalError( 'invalid_condition_structure', 'Condition structure is invalid', $error_context );
				return $this->applyConditionFallbackBehavior( $condition, 'invalid_structure' );
			}

			$evaluator = $this->getEvaluator( $type );

			if ( ! $evaluator ) {
				// Log missing evaluator with detailed context.
				$this->logConditionalError( 'missing_evaluator', "No evaluator found for condition type: {$type}", $error_context );
				do_action( 'vira_code/conditional_logic_missing_evaluator', $type, $condition );
				
				// Apply fallback behavior for missing evaluators.
				return $this->applyConditionFallbackBehavior( $condition, 'missing_evaluator' );
			}

			// Get current context with error handling.
			$context = $this->getCurrentContextWithErrorHandling();
			if ( false === $context ) {
				$this->logConditionalError( 'context_failure', 'Failed to get current context', $error_context );
				return $this->applyConditionFallbackBehavior( $condition, 'context_failure' );
			}
			
			// Evaluate the condition with timeout protection.
			$result = $this->evaluateConditionWithTimeout( $evaluator, $condition, $context );
			
			// Log evaluation for debugging.
			$evaluation_time = microtime( true ) - $start_time;
			$this->logConditionEvaluation( $type, $condition, $result, $context, $evaluation_time );
			do_action( 'vira_code/conditional_logic_condition_evaluated', $type, $condition, $result, $context, $evaluation_time );
			
			return (bool) $result;
			
		} catch ( \Exception $e ) {
			return $this->handleConditionEvaluationException( $e, $condition, $error_context, microtime( true ) - $start_time );
		} catch ( \Error $e ) {
			return $this->handleConditionEvaluationFatalError( $e, $condition, $error_context, microtime( true ) - $start_time );
		} catch ( \Throwable $e ) {
			return $this->handleConditionEvaluationThrowable( $e, $condition, $error_context, microtime( true ) - $start_time );
		}
	}

	/**
	 * Get current context for condition evaluation.
	 *
	 * @return array
	 */
	protected function getCurrentContext() {
		static $context = null;

		if ( null === $context ) {
			$context = array(
				'request_uri'    => $_SERVER['REQUEST_URI'] ?? '',
				'is_home'        => is_home(),
				'is_front_page'  => is_front_page(),
				'is_single'      => is_single(),
				'is_page'        => is_page(),
				'is_archive'     => is_archive(),
				'is_category'    => is_category(),
				'is_tag'         => is_tag(),
				'is_admin'       => is_admin(),
				'is_user_logged_in' => is_user_logged_in(),
				'current_user_id'   => get_current_user_id(),
				'user_agent'     => $_SERVER['HTTP_USER_AGENT'] ?? '',
				'current_time'   => current_time( 'timestamp' ),
				'wp_is_mobile'   => wp_is_mobile(),
			);

			// Add current user roles.
			if ( $context['is_user_logged_in'] ) {
				$user = wp_get_current_user();
				$context['user_roles'] = $user->roles;
				$context['user_capabilities'] = array_keys( $user->allcaps );
			} else {
				$context['user_roles'] = array();
				$context['user_capabilities'] = array();
			}

			// Allow filtering of context.
			$context = apply_filters( 'vira_code/conditional_logic_context', $context );
		}

		return $context;
	}

	/**
	 * Validate rules structure and content.
	 *
	 * @param array $rules Rules to validate.
	 * @return array
	 */
	protected function validateRules( $rules ) {
		$result = array(
			'valid'  => true,
			'errors' => array(),
		);

		// If rules is a structured format, validate it.
		if ( isset( $rules['groups'] ) ) {
			return $this->validateStructuredRules( $rules );
		}

		// If rules is a flat array (database format), validate each rule.
		foreach ( $rules as $rule ) {
			$rule_validation = $this->validateSingleRule( $rule );
			if ( ! $rule_validation['valid'] ) {
				$result['valid'] = false;
				$result['errors'] = array_merge( $result['errors'], $rule_validation['errors'] );
			}
		}

		return $result;
	}

	/**
	 * Validate structured rules format.
	 *
	 * @param array $structured_rules Structured rules.
	 * @return array
	 */
	protected function validateStructuredRules( $structured_rules ) {
		$result = array(
			'valid'  => true,
			'errors' => array(),
		);

		if ( ! isset( $structured_rules['groups'] ) || ! is_array( $structured_rules['groups'] ) ) {
			$result['valid'] = false;
			$result['errors'][] = 'Rules must contain a groups array';
			return $result;
		}

		foreach ( $structured_rules['groups'] as $group_index => $group ) {
			if ( ! isset( $group['conditions'] ) || ! is_array( $group['conditions'] ) ) {
				$result['valid'] = false;
				$result['errors'][] = "Group {$group_index} must contain a conditions array";
				continue;
			}

			foreach ( $group['conditions'] as $condition_index => $condition ) {
				$condition_validation = $this->validateCondition( $condition );
				if ( ! $condition_validation['valid'] ) {
					$result['valid'] = false;
					foreach ( $condition_validation['errors'] as $error ) {
						$result['errors'][] = "Group {$group_index}, Condition {$condition_index}: {$error}";
					}
				}
			}
		}

		return $result;
	}

	/**
	 * Validate a single rule (database format).
	 *
	 * @param array $rule Rule data.
	 * @return array
	 */
	protected function validateSingleRule( $rule ) {
		$result = array(
			'valid'  => true,
			'errors' => array(),
		);

		$required_fields = array( 'condition_type', 'condition_operator', 'condition_value' );
		foreach ( $required_fields as $field ) {
			if ( ! isset( $rule[ $field ] ) ) {
				$result['valid'] = false;
				$result['errors'][] = "Missing required field: {$field}";
			}
		}

		if ( $result['valid'] ) {
			$condition = array(
				'type'     => $rule['condition_type'],
				'operator' => $rule['condition_operator'],
				'value'    => $rule['condition_value'],
			);

			$condition_validation = $this->validateCondition( $condition );
			if ( ! $condition_validation['valid'] ) {
				$result['valid'] = false;
				$result['errors'] = array_merge( $result['errors'], $condition_validation['errors'] );
			}
		}

		return $result;
	}

	/**
	 * Validate a single condition.
	 *
	 * @param array $condition Condition data.
	 * @return array
	 */
	protected function validateCondition( $condition ) {
		$result = array(
			'valid'  => true,
			'errors' => array(),
		);

		if ( ! isset( $condition['type'] ) || empty( $condition['type'] ) ) {
			$result['valid'] = false;
			$result['errors'][] = 'Condition type is required';
			return $result;
		}

		$evaluator = $this->getEvaluator( $condition['type'] );
		if ( ! $evaluator ) {
			$result['valid'] = false;
			$result['errors'][] = "Unknown condition type: {$condition['type']}";
			return $result;
		}

		// Use evaluator's validation if available.
		if ( method_exists( $evaluator, 'validate' ) ) {
			try {
				$evaluator_validation = $evaluator->validate( $condition );
				if ( is_array( $evaluator_validation ) && ! $evaluator_validation['valid'] ) {
					$result['valid'] = false;
					$result['errors'] = array_merge( $result['errors'], $evaluator_validation['errors'] );
				}
			} catch ( \Exception $e ) {
				$result['valid'] = false;
				$result['errors'][] = "Validation error: {$e->getMessage()}";
			}
		}

		return $result;
	}

	/**
	 * Clear cache for a specific snippet or all cache.
	 *
	 * @param int|null $snippet_id Snippet ID or null to clear all.
	 * @return void
	 */
	public function clearCache( $snippet_id = null ) {
		if ( null === $snippet_id ) {
			// Clear all memory caches.
			$this->rule_cache = array();
			$this->evaluation_cache = array();
			
			// Clear all object cache entries for this cache group.
			wp_cache_flush_group( $this->cache_group );
		} else {
			// Clear memory cache for specific snippet.
			unset( $this->rule_cache[ 'rules_' . $snippet_id ] );
			unset( $this->evaluation_cache[ 'eval_' . $snippet_id ] );
			
			// Clear object cache for specific snippet rules.
			wp_cache_delete( 'snippet_rules_' . $snippet_id, $this->cache_group );
			
			// Clear all evaluation caches for this snippet (all contexts).
			$this->clearEvaluationCacheForSnippet( $snippet_id );
		}

		do_action( 'vira_code/conditional_logic_cache_cleared', $snippet_id );
	}

	/**
	 * Get cache statistics for debugging.
	 *
	 * @return array
	 */
	public function getCacheStats() {
		return array(
			'rule_cache_count'       => count( $this->rule_cache ),
			'evaluation_cache_count' => count( $this->evaluation_cache ),
			'registered_evaluators'  => array_keys( $this->evaluators ),
			'cache_group'            => $this->cache_group,
			'cache_expiration'       => $this->cache_expiration,
		);
	}

	/**
	 * Get context hash for cache key generation.
	 *
	 * @return string
	 */
	protected function getContextHash() {
		$context = $this->getCurrentContext();
		
		// Create a hash based on relevant context data that affects evaluation.
		$cache_context = array(
			'request_uri'       => $context['request_uri'],
			'is_home'          => $context['is_home'],
			'is_front_page'    => $context['is_front_page'],
			'is_single'        => $context['is_single'],
			'is_page'          => $context['is_page'],
			'is_archive'       => $context['is_archive'],
			'is_category'      => $context['is_category'],
			'is_tag'           => $context['is_tag'],
			'is_admin'         => $context['is_admin'],
			'is_user_logged_in' => $context['is_user_logged_in'],
			'current_user_id'  => $context['current_user_id'],
			'user_roles'       => $context['user_roles'],
			'wp_is_mobile'     => $context['wp_is_mobile'],
			'date_ymd'         => date( 'Y-m-d', $context['current_time'] ), // Date without time for daily cache
		);
		
		return md5( serialize( $cache_context ) );
	}

	/**
	 * Clear evaluation cache for a specific snippet across all contexts.
	 *
	 * @param int $snippet_id Snippet ID.
	 * @return void
	 */
	protected function clearEvaluationCacheForSnippet( $snippet_id ) {
		// Since we can't enumerate all possible context hashes,
		// we'll use a different approach: store a list of active cache keys.
		$cache_keys_key = 'snippet_eval_keys_' . $snippet_id;
		$cached_keys = wp_cache_get( $cache_keys_key, $this->cache_group );
		
		if ( is_array( $cached_keys ) ) {
			foreach ( $cached_keys as $cache_key ) {
				wp_cache_delete( $cache_key, $this->cache_group );
			}
			wp_cache_delete( $cache_keys_key, $this->cache_group );
		}
	}

	/**
	 * Track evaluation cache keys for invalidation.
	 *
	 * @param int    $snippet_id Snippet ID.
	 * @param string $cache_key  Cache key.
	 * @return void
	 */
	protected function trackEvaluationCacheKey( $snippet_id, $cache_key ) {
		$cache_keys_key = 'snippet_eval_keys_' . $snippet_id;
		$cached_keys = wp_cache_get( $cache_keys_key, $this->cache_group );
		
		if ( ! is_array( $cached_keys ) ) {
			$cached_keys = array();
		}
		
		if ( ! in_array( $cache_key, $cached_keys, true ) ) {
			$cached_keys[] = $cache_key;
			wp_cache_set( $cache_keys_key, $cached_keys, $this->cache_group, $this->cache_expiration );
		}
	}

	/**
	 * Invalidate related caches when rules are updated.
	 *
	 * @param int $snippet_id Snippet ID.
	 * @return void
	 */
	protected function invalidateRelatedCaches( $snippet_id ) {
		// Clear snippet list caches.
		wp_cache_delete( 'snippets_with_rules', $this->cache_group );
		wp_cache_delete( 'snippets_with_rules_optimized', $this->cache_group );
		
		// Clear cached rule counts.
		wp_cache_delete( 'snippet_rule_count_' . $snippet_id, $this->cache_group );
		
		// Clear performance statistics cache.
		wp_cache_delete( 'conditional_logic_performance_stats', $this->cache_group );
		
		// Fire action to allow other components to clear related caches.
		do_action( 'vira_code/conditional_logic_invalidate_caches', $snippet_id );
	}

	/**
	 * Batch load rules for multiple snippets.
	 *
	 * @param array $snippet_ids Array of snippet IDs.
	 * @return array Associative array of snippet_id => rules.
	 */
	public function batchLoadRules( $snippet_ids ) {
		if ( empty( $snippet_ids ) ) {
			return array();
		}

		$results = array();
		$uncached_ids = array();

		// Check cache for each snippet.
		foreach ( $snippet_ids as $snippet_id ) {
			$memory_cache_key = 'rules_' . $snippet_id;
			$object_cache_key = 'snippet_rules_' . $snippet_id;

			// Check memory cache first.
			if ( isset( $this->rule_cache[ $memory_cache_key ] ) ) {
				$results[ $snippet_id ] = $this->rule_cache[ $memory_cache_key ];
				continue;
			}

			// Check object cache.
			$cached_rules = wp_cache_get( $object_cache_key, $this->cache_group );
			if ( false !== $cached_rules ) {
				$results[ $snippet_id ] = $cached_rules;
				$this->rule_cache[ $memory_cache_key ] = $cached_rules;
				continue;
			}

			// Mark as needing database lookup.
			$uncached_ids[] = $snippet_id;
		}

		// Batch load uncached rules from database.
		if ( ! empty( $uncached_ids ) ) {
			$batch_rules = $this->model->batchGetRules( $uncached_ids );

			foreach ( $uncached_ids as $snippet_id ) {
				$rules = isset( $batch_rules[ $snippet_id ] ) ? $batch_rules[ $snippet_id ] : array();
				
				// Cache the results.
				$memory_cache_key = 'rules_' . $snippet_id;
				$object_cache_key = 'snippet_rules_' . $snippet_id;
				
				$this->rule_cache[ $memory_cache_key ] = $rules;
				wp_cache_set( $object_cache_key, $rules, $this->cache_group, $this->cache_expiration );
				
				$results[ $snippet_id ] = $rules;
			}
		}

		return $results;
	}

	/**
	 * Check if a snippet has conditional logic rules.
	 *
	 * @param int $snippet_id Snippet ID.
	 * @return bool
	 */
	public function hasRules( $snippet_id ) {
		return $this->model->hasRules( $snippet_id );
	}

	/**
	 * Get rule count for a snippet.
	 *
	 * @param int $snippet_id Snippet ID.
	 * @return int
	 */
	public function getRuleCount( $snippet_id ) {
		// Check cache first.
		$cache_key = 'snippet_rule_count_' . $snippet_id;
		$cached_count = wp_cache_get( $cache_key, $this->cache_group );

		if ( false !== $cached_count ) {
			return (int) $cached_count;
		}

		$count = $this->model->getRuleCount( $snippet_id );

		// Cache the count.
		wp_cache_set( $cache_key, $count, $this->cache_group, $this->cache_expiration );

		return $count;
	}

	/**
	 * Batch get rule counts for multiple snippets.
	 *
	 * @param array $snippet_ids Array of snippet IDs.
	 * @return array Associative array of snippet_id => count.
	 */
	public function batchGetRuleCounts( $snippet_ids ) {
		if ( empty( $snippet_ids ) ) {
			return array();
		}

		$results = array();
		$uncached_ids = array();

		// Check cache for each snippet.
		foreach ( $snippet_ids as $snippet_id ) {
			$cache_key = 'snippet_rule_count_' . $snippet_id;
			$cached_count = wp_cache_get( $cache_key, $this->cache_group );

			if ( false !== $cached_count ) {
				$results[ $snippet_id ] = (int) $cached_count;
			} else {
				$uncached_ids[] = $snippet_id;
			}
		}

		// Batch load uncached counts from database.
		if ( ! empty( $uncached_ids ) ) {
			$batch_counts = $this->model->batchGetRuleCounts( $uncached_ids );

			foreach ( $batch_counts as $snippet_id => $count ) {
				$cache_key = 'snippet_rule_count_' . $snippet_id;
				wp_cache_set( $cache_key, $count, $this->cache_group, $this->cache_expiration );
				$results[ $snippet_id ] = $count;
			}
		}

		return $results;
	}

	/**
	 * Get snippets with conditional logic using optimized query.
	 *
	 * @return array
	 */
	public function getSnippetsWithRulesOptimized() {
		$cache_key = 'snippets_with_rules_optimized';
		$cached_results = wp_cache_get( $cache_key, $this->cache_group );

		if ( false !== $cached_results ) {
			return $cached_results;
		}

		$results = $this->model->getSnippetsWithRulesOptimized();

		// Cache for shorter time since this includes snippet status.
		wp_cache_set( $cache_key, $results, $this->cache_group, 900 ); // 15 minutes

		return $results;
	}

	/**
	 * Get performance statistics for conditional logic.
	 *
	 * @return array
	 */
	public function getPerformanceStatistics() {
		$cache_key = 'conditional_logic_performance_stats';
		$cached_stats = wp_cache_get( $cache_key, $this->cache_group );

		if ( false !== $cached_stats ) {
			return $cached_stats;
		}

		$db_stats = $this->model->getRuleStatistics();
		$cache_stats = $this->getCacheStats();

		$stats = array_merge( $db_stats, array(
			'cache_stats' => $cache_stats,
			'generated_at' => current_time( 'mysql' ),
		) );

		// Cache for longer time since these are aggregate stats.
		wp_cache_set( $cache_key, $stats, $this->cache_group, 1800 ); // 30 minutes

		return $stats;
	}

	/**
	 * Optimize rule evaluation by pre-loading related data.
	 *
	 * @param array $snippet_ids Array of snippet IDs to optimize for.
	 * @return void
	 */
	public function preloadRulesForSnippets( $snippet_ids ) {
		if ( empty( $snippet_ids ) ) {
			return;
		}

		// Batch load rules to warm the cache.
		$this->batchLoadRules( $snippet_ids );

		// Pre-load rule counts.
		$this->batchGetRuleCounts( $snippet_ids );

		do_action( 'vira_code/conditional_logic_rules_preloaded', $snippet_ids );
	}

	/**
	 * Get rules with comprehensive error handling.
	 *
	 * @param int $snippet_id Snippet ID.
	 * @return array|false Rules array or false on failure.
	 */
	protected function getRulesWithErrorHandling( $snippet_id ) {
		try {
			return $this->getRules( $snippet_id );
		} catch ( \Exception $e ) {
			$this->logConditionalError( 'get_rules_exception', $e->getMessage(), array( 'snippet_id' => $snippet_id ) );
			return false;
		} catch ( \Error $e ) {
			$this->logConditionalError( 'get_rules_fatal_error', $e->getMessage(), array( 'snippet_id' => $snippet_id ) );
			return false;
		}
	}

	/**
	 * Convert database rules to structured format with error handling.
	 *
	 * @param array $db_rules Database rules.
	 * @return array|false Structured rules or false on failure.
	 */
	protected function convertDbRulesToStructuredWithErrorHandling( $db_rules ) {
		try {
			if ( ! is_array( $db_rules ) ) {
				$this->logConditionalError( 'invalid_rules_format', 'Rules must be an array', array( 'rules_type' => gettype( $db_rules ) ) );
				return false;
			}

			return $this->convertDbRulesToStructured( $db_rules );
		} catch ( \Exception $e ) {
			$this->logConditionalError( 'rule_conversion_exception', $e->getMessage(), array( 'rules_count' => count( $db_rules ) ) );
			return false;
		} catch ( \Error $e ) {
			$this->logConditionalError( 'rule_conversion_fatal_error', $e->getMessage(), array( 'rules_count' => count( $db_rules ) ) );
			return false;
		}
	}

	/**
	 * Evaluate structured rules with comprehensive error handling.
	 *
	 * @param array $structured_rules Structured rules.
	 * @param int   $snippet_id       Snippet ID for context.
	 * @return bool
	 */
	protected function evaluateStructuredRulesWithErrorHandling( $structured_rules, $snippet_id ) {
		try {
			return $this->evaluateStructuredRules( $structured_rules );
		} catch ( \Exception $e ) {
			$this->logConditionalError( 'structured_evaluation_exception', $e->getMessage(), array( 'snippet_id' => $snippet_id ) );
			return $this->applyFallbackBehavior( $snippet_id, 'structured_evaluation_exception', array( 'error' => $e->getMessage() ) );
		} catch ( \Error $e ) {
			$this->logConditionalError( 'structured_evaluation_fatal_error', $e->getMessage(), array( 'snippet_id' => $snippet_id ) );
			return $this->applyFallbackBehavior( $snippet_id, 'structured_evaluation_fatal_error', array( 'error' => $e->getMessage() ) );
		}
	}

	/**
	 * Get current context with error handling.
	 *
	 * @return array|false Context array or false on failure.
	 */
	protected function getCurrentContextWithErrorHandling() {
		try {
			return $this->getCurrentContext();
		} catch ( \Exception $e ) {
			$this->logConditionalError( 'context_exception', $e->getMessage(), array() );
			return false;
		} catch ( \Error $e ) {
			$this->logConditionalError( 'context_fatal_error', $e->getMessage(), array() );
			return false;
		}
	}

	/**
	 * Evaluate condition with timeout protection.
	 *
	 * @param object $evaluator Condition evaluator.
	 * @param array  $condition Condition data.
	 * @param array  $context   Current context.
	 * @return bool
	 */
	protected function evaluateConditionWithTimeout( $evaluator, $condition, $context ) {
		$timeout = apply_filters( 'vira_code/condition_evaluation_timeout', 5 ); // 5 seconds default
		$start_time = microtime( true );

		// Set time limit for condition evaluation.
		$old_time_limit = ini_get( 'max_execution_time' );
		if ( function_exists( 'set_time_limit' ) && $timeout > 0 ) {
			@set_time_limit( $timeout );
		}

		try {
			$result = $evaluator->evaluate( $condition, $context );

			// Check if evaluation took too long.
			$evaluation_time = microtime( true ) - $start_time;
			if ( $evaluation_time > $timeout ) {
				$this->logConditionalError( 'evaluation_timeout', "Condition evaluation exceeded timeout ({$evaluation_time}s)", array(
					'condition_type' => $condition['type'],
					'timeout' => $timeout,
					'actual_time' => $evaluation_time,
				) );
			}

			return $result;

		} finally {
			// Restore time limit.
			if ( function_exists( 'set_time_limit' ) && $old_time_limit !== false ) {
				@set_time_limit( (int) $old_time_limit );
			}
		}
	}

	/**
	 * Validate condition structure.
	 *
	 * @param array $condition Condition to validate.
	 * @return bool
	 */
	protected function isValidConditionStructure( $condition ) {
		if ( ! is_array( $condition ) ) {
			return false;
		}

		$required_fields = array( 'type', 'operator', 'value' );
		foreach ( $required_fields as $field ) {
			if ( ! isset( $condition[ $field ] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Apply fallback behavior when evaluation fails.
	 *
	 * @param int    $snippet_id Snippet ID.
	 * @param string $reason     Failure reason.
	 * @param array  $context    Additional context.
	 * @return bool
	 */
	protected function applyFallbackBehavior( $snippet_id, $reason, $context = array() ) {
		// Get fallback behavior from settings or filter.
		$fallback_behavior = apply_filters( 'vira_code/conditional_logic_fallback_behavior', 'deny', $snippet_id, $reason, $context );

		$result = false;
		switch ( $fallback_behavior ) {
			case 'allow':
				$result = true;
				break;
			case 'deny':
			default:
				$result = false;
				break;
		}

		// Log fallback application.
		$this->logConditionalError( 'fallback_applied', "Applied fallback behavior: {$fallback_behavior}", array(
			'snippet_id' => $snippet_id,
			'reason' => $reason,
			'result' => $result,
			'context' => $context,
		) );

		// Fire action for fallback behavior.
		do_action( 'vira_code/conditional_logic_fallback_applied', $snippet_id, $reason, $result, $context );

		return $result;
	}

	/**
	 * Apply fallback behavior for individual condition failures.
	 *
	 * @param array  $condition Condition data.
	 * @param string $reason    Failure reason.
	 * @return bool
	 */
	protected function applyConditionFallbackBehavior( $condition, $reason ) {
		$condition_type = isset( $condition['type'] ) ? $condition['type'] : 'unknown';
		
		// Get condition-specific fallback behavior.
		$fallback_behavior = apply_filters( 'vira_code/condition_fallback_behavior', 'deny', $condition_type, $reason, $condition );

		$result = false;
		switch ( $fallback_behavior ) {
			case 'allow':
				$result = true;
				break;
			case 'deny':
			default:
				$result = false;
				break;
		}

		// Log condition fallback.
		$this->logConditionalError( 'condition_fallback_applied', "Applied condition fallback: {$fallback_behavior}", array(
			'condition_type' => $condition_type,
			'reason' => $reason,
			'result' => $result,
		) );

		// Fire action for condition fallback.
		do_action( 'vira_code/condition_fallback_applied', $condition, $reason, $result );

		return $result;
	}

	/**
	 * Handle evaluation exceptions.
	 *
	 * @param \Exception $e             Exception object.
	 * @param int        $snippet_id    Snippet ID.
	 * @param array      $error_context Error context.
	 * @param float      $elapsed_time  Elapsed time.
	 * @return bool
	 */
	protected function handleEvaluationException( $e, $snippet_id, $error_context, $elapsed_time ) {
		$this->logConditionalError( 'evaluation_exception', $e->getMessage(), array_merge( $error_context, array(
			'exception_class' => get_class( $e ),
			'file' => $e->getFile(),
			'line' => $e->getLine(),
			'elapsed_time' => $elapsed_time,
		) ) );

		do_action( 'vira_code/conditional_logic_evaluation_exception', $snippet_id, $e, $error_context );

		return $this->applyFallbackBehavior( $snippet_id, 'evaluation_exception', array( 'exception' => $e->getMessage() ) );
	}

	/**
	 * Handle evaluation fatal errors.
	 *
	 * @param \Error $e             Error object.
	 * @param int    $snippet_id    Snippet ID.
	 * @param array  $error_context Error context.
	 * @param float  $elapsed_time  Elapsed time.
	 * @return bool
	 */
	protected function handleEvaluationFatalError( $e, $snippet_id, $error_context, $elapsed_time ) {
		$this->logConditionalError( 'evaluation_fatal_error', $e->getMessage(), array_merge( $error_context, array(
			'error_class' => get_class( $e ),
			'file' => $e->getFile(),
			'line' => $e->getLine(),
			'elapsed_time' => $elapsed_time,
		) ) );

		do_action( 'vira_code/conditional_logic_evaluation_fatal_error', $snippet_id, $e, $error_context );

		return $this->applyFallbackBehavior( $snippet_id, 'evaluation_fatal_error', array( 'error' => $e->getMessage() ) );
	}

	/**
	 * Handle evaluation throwables.
	 *
	 * @param \Throwable $e             Throwable object.
	 * @param int        $snippet_id    Snippet ID.
	 * @param array      $error_context Error context.
	 * @param float      $elapsed_time  Elapsed time.
	 * @return bool
	 */
	protected function handleEvaluationThrowable( $e, $snippet_id, $error_context, $elapsed_time ) {
		$this->logConditionalError( 'evaluation_throwable', $e->getMessage(), array_merge( $error_context, array(
			'throwable_class' => get_class( $e ),
			'file' => $e->getFile(),
			'line' => $e->getLine(),
			'elapsed_time' => $elapsed_time,
		) ) );

		do_action( 'vira_code/conditional_logic_evaluation_throwable', $snippet_id, $e, $error_context );

		return $this->applyFallbackBehavior( $snippet_id, 'evaluation_throwable', array( 'throwable' => $e->getMessage() ) );
	}

	/**
	 * Handle condition evaluation exceptions.
	 *
	 * @param \Exception $e             Exception object.
	 * @param array      $condition     Condition data.
	 * @param array      $error_context Error context.
	 * @param float      $elapsed_time  Elapsed time.
	 * @return bool
	 */
	protected function handleConditionEvaluationException( $e, $condition, $error_context, $elapsed_time ) {
		$this->logConditionalError( 'condition_evaluation_exception', $e->getMessage(), array_merge( $error_context, array(
			'exception_class' => get_class( $e ),
			'file' => $e->getFile(),
			'line' => $e->getLine(),
			'elapsed_time' => $elapsed_time,
		) ) );

		do_action( 'vira_code/conditional_logic_condition_exception', $condition, $e, $error_context );

		return $this->applyConditionFallbackBehavior( $condition, 'evaluation_exception' );
	}

	/**
	 * Handle condition evaluation fatal errors.
	 *
	 * @param \Error $e             Error object.
	 * @param array  $condition     Condition data.
	 * @param array  $error_context Error context.
	 * @param float  $elapsed_time  Elapsed time.
	 * @return bool
	 */
	protected function handleConditionEvaluationFatalError( $e, $condition, $error_context, $elapsed_time ) {
		$this->logConditionalError( 'condition_evaluation_fatal_error', $e->getMessage(), array_merge( $error_context, array(
			'error_class' => get_class( $e ),
			'file' => $e->getFile(),
			'line' => $e->getLine(),
			'elapsed_time' => $elapsed_time,
		) ) );

		do_action( 'vira_code/conditional_logic_condition_fatal_error', $condition, $e, $error_context );

		return $this->applyConditionFallbackBehavior( $condition, 'evaluation_fatal_error' );
	}

	/**
	 * Handle condition evaluation throwables.
	 *
	 * @param \Throwable $e             Throwable object.
	 * @param array      $condition     Condition data.
	 * @param array      $error_context Error context.
	 * @param float      $elapsed_time  Elapsed time.
	 * @return bool
	 */
	protected function handleConditionEvaluationThrowable( $e, $condition, $error_context, $elapsed_time ) {
		$this->logConditionalError( 'condition_evaluation_throwable', $e->getMessage(), array_merge( $error_context, array(
			'throwable_class' => get_class( $e ),
			'file' => $e->getFile(),
			'line' => $e->getLine(),
			'elapsed_time' => $elapsed_time,
		) ) );

		do_action( 'vira_code/conditional_logic_condition_throwable', $condition, $e, $error_context );

		return $this->applyConditionFallbackBehavior( $condition, 'evaluation_throwable' );
	}

	/**
	 * Log conditional logic errors with structured data.
	 *
	 * @param string $error_type Error type identifier.
	 * @param string $message    Error message.
	 * @param array  $context    Additional context data.
	 * @return void
	 */
	protected function logConditionalError( $error_type, $message, $context = array() ) {
		$log_entry = array(
			'timestamp' => current_time( 'mysql' ),
			'error_type' => $error_type,
			'message' => $message,
			'context' => $context,
			'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
			'user_id' => get_current_user_id(),
		);

		// Store in transient for debugging.
		$error_logs = get_transient( 'vira_code_conditional_error_logs' );
		if ( ! is_array( $error_logs ) ) {
			$error_logs = array();
		}

		$error_logs[] = $log_entry;

		// Keep only last 50 error logs.
		if ( count( $error_logs ) > 50 ) {
			$error_logs = array_slice( $error_logs, -50 );
		}

		set_transient( 'vira_code_conditional_error_logs', $error_logs, 24 * HOUR_IN_SECONDS );

		// Fire action for custom error handling.
		do_action( 'vira_code/conditional_logic_error_logged', $log_entry );

		// Log to WordPress error log if enabled.
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( sprintf( 'Vira Code Conditional Logic Error [%s]: %s - Context: %s', $error_type, $message, wp_json_encode( $context ) ) );
		}
	}

	/**
	 * Log condition evaluation with performance data.
	 *
	 * @param string $type            Condition type.
	 * @param array  $condition       Condition data.
	 * @param bool   $result          Evaluation result.
	 * @param array  $context         Current context.
	 * @param float  $evaluation_time Evaluation time in seconds.
	 * @return void
	 */
	protected function logConditionEvaluation( $type, $condition, $result, $context, $evaluation_time ) {
		// Only log if debug mode is enabled or evaluation is slow.
		$log_threshold = apply_filters( 'vira_code/condition_evaluation_log_threshold', 0.1 ); // 100ms
		$debug_enabled = defined( 'WP_DEBUG' ) && WP_DEBUG;

		if ( ! $debug_enabled && $evaluation_time < $log_threshold ) {
			return;
		}

		$log_entry = array(
			'timestamp' => current_time( 'mysql' ),
			'condition_type' => $type,
			'result' => $result,
			'evaluation_time' => $evaluation_time,
			'is_slow' => $evaluation_time >= $log_threshold,
		);

		// Add condition details if debug is enabled.
		if ( $debug_enabled ) {
			$log_entry['condition'] = $condition;
			$log_entry['context_keys'] = array_keys( $context );
		}

		// Store in transient for performance monitoring.
		$evaluation_logs = get_transient( 'vira_code_condition_evaluation_logs' );
		if ( ! is_array( $evaluation_logs ) ) {
			$evaluation_logs = array();
		}

		$evaluation_logs[] = $log_entry;

		// Keep only last 100 evaluation logs.
		if ( count( $evaluation_logs ) > 100 ) {
			$evaluation_logs = array_slice( $evaluation_logs, -100 );
		}

		set_transient( 'vira_code_condition_evaluation_logs', $evaluation_logs, 12 * HOUR_IN_SECONDS );

		// Fire action for custom logging.
		do_action( 'vira_code/condition_evaluation_logged', $log_entry );
	}

	/**
	 * Log performance metrics.
	 *
	 * @param string $metric_type Metric type identifier.
	 * @param float  $time        Time in seconds.
	 * @param int    $snippet_id  Optional snippet ID.
	 * @return void
	 */
	protected function logPerformanceMetric( $metric_type, $time, $snippet_id = null ) {
		$log_entry = array(
			'timestamp' => current_time( 'mysql' ),
			'metric_type' => $metric_type,
			'time' => $time,
			'snippet_id' => $snippet_id,
		);

		// Store in transient for performance analysis.
		$performance_logs = get_transient( 'vira_code_conditional_performance_logs' );
		if ( ! is_array( $performance_logs ) ) {
			$performance_logs = array();
		}

		$performance_logs[] = $log_entry;

		// Keep only last 200 performance logs.
		if ( count( $performance_logs ) > 200 ) {
			$performance_logs = array_slice( $performance_logs, -200 );
		}

		set_transient( 'vira_code_conditional_performance_logs', $performance_logs, 6 * HOUR_IN_SECONDS );

		// Fire action for custom performance monitoring.
		do_action( 'vira_code/conditional_logic_performance_logged', $log_entry );
	}

	/**
	 * Get error logs for debugging.
	 *
	 * @param int $limit Optional limit.
	 * @return array
	 */
	public function getErrorLogs( $limit = 50 ) {
		$error_logs = get_transient( 'vira_code_conditional_error_logs' );
		if ( ! is_array( $error_logs ) ) {
			return array();
		}

		if ( $limit > 0 ) {
			return array_slice( $error_logs, -$limit );
		}

		return $error_logs;
	}

	/**
	 * Get condition evaluation logs for performance monitoring.
	 *
	 * @param int $limit Optional limit.
	 * @return array
	 */
	public function getEvaluationLogs( $limit = 100 ) {
		$evaluation_logs = get_transient( 'vira_code_condition_evaluation_logs' );
		if ( ! is_array( $evaluation_logs ) ) {
			return array();
		}

		if ( $limit > 0 ) {
			return array_slice( $evaluation_logs, -$limit );
		}

		return $evaluation_logs;
	}

	/**
	 * Get performance logs for analysis.
	 *
	 * @param int $limit Optional limit.
	 * @return array
	 */
	public function getPerformanceLogs( $limit = 200 ) {
		$performance_logs = get_transient( 'vira_code_conditional_performance_logs' );
		if ( ! is_array( $performance_logs ) ) {
			return array();
		}

		if ( $limit > 0 ) {
			return array_slice( $performance_logs, -$limit );
		}

		return $performance_logs;
	}

	/**
	 * Clear error and performance logs.
	 *
	 * @return bool
	 */
	public function clearLogs() {
		$result1 = delete_transient( 'vira_code_conditional_error_logs' );
		$result2 = delete_transient( 'vira_code_condition_evaluation_logs' );
		$result3 = delete_transient( 'vira_code_conditional_performance_logs' );

		do_action( 'vira_code/conditional_logic_logs_cleared' );

		return $result1 && $result2 && $result3;
	}
}