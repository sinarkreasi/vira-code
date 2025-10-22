<?php
/**
 * Abstract Condition Evaluator
 *
 * @package ViraCode
 */

namespace ViraCode\Services\Evaluators;

use ViraCode\Services\Interfaces\ConditionEvaluatorInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AbstractConditionEvaluator Class
 *
 * Base class providing common functionality for all condition evaluators.
 */
abstract class AbstractConditionEvaluator implements ConditionEvaluatorInterface {

	/**
	 * Condition type identifier.
	 *
	 * @var string
	 */
	protected $type;

	/**
	 * Supported operators for this condition type.
	 *
	 * @var array
	 */
	protected $supported_operators = array( 'equals', 'not_equals' );

	/**
	 * Human-readable description.
	 *
	 * @var string
	 */
	protected $description = '';

	/**
	 * Get the condition type this evaluator handles.
	 *
	 * @return string
	 */
	public function getType() {
		return $this->type;
	}

	/**
	 * Get supported operators for this condition type.
	 *
	 * @return array
	 */
	public function getSupportedOperators() {
		return $this->supported_operators;
	}

	/**
	 * Get human-readable description of this condition type.
	 *
	 * @return string
	 */
	public function getDescription() {
		return $this->description;
	}

	/**
	 * Validate a condition structure and values.
	 *
	 * @param array $condition Condition data to validate.
	 * @return array
	 */
	public function validate( array $condition ) {
		$result = array(
			'valid'  => true,
			'errors' => array(),
		);

		// Check required fields.
		$required_fields = array( 'type', 'operator', 'value' );
		foreach ( $required_fields as $field ) {
			if ( ! isset( $condition[ $field ] ) ) {
				$result['valid'] = false;
				$result['errors'][] = "Missing required field: {$field}";
			}
		}

		if ( ! $result['valid'] ) {
			return $result;
		}

		// Validate condition type matches this evaluator.
		if ( $condition['type'] !== $this->getType() ) {
			$result['valid'] = false;
			$result['errors'][] = "Condition type '{$condition['type']}' does not match evaluator type '{$this->getType()}'";
			return $result;
		}

		// Validate operator is supported.
		if ( ! in_array( $condition['operator'], $this->getSupportedOperators(), true ) ) {
			$result['valid'] = false;
			$result['errors'][] = "Unsupported operator '{$condition['operator']}' for condition type '{$this->getType()}'";
		}

		// Validate condition value.
		$value_validation = $this->validateValue( $condition['value'], $condition['operator'] );
		if ( ! $value_validation['valid'] ) {
			$result['valid'] = false;
			$result['errors'] = array_merge( $result['errors'], $value_validation['errors'] );
		}

		return $result;
	}

	/**
	 * Validate condition value for this evaluator type.
	 * Override in child classes for specific validation logic.
	 *
	 * @param mixed  $value    Condition value to validate.
	 * @param string $operator Operator being used.
	 * @return array
	 */
	protected function validateValue( $value, $operator ) {
		$result = array(
			'valid'  => true,
			'errors' => array(),
		);

		// Basic validation - value should not be null for most operators.
		if ( null === $value || '' === $value ) {
			$result['valid'] = false;
			$result['errors'][] = 'Condition value cannot be empty';
		}

		return $result;
	}

	/**
	 * Apply operator logic to compare values.
	 *
	 * @param mixed  $actual_value   The actual value from context.
	 * @param mixed  $expected_value The expected value from condition.
	 * @param string $operator       The comparison operator.
	 * @return bool
	 */
	protected function applyOperator( $actual_value, $expected_value, $operator ) {
		switch ( $operator ) {
			case 'equals':
				return $this->compareEquals( $actual_value, $expected_value );

			case 'not_equals':
				return ! $this->compareEquals( $actual_value, $expected_value );

			case 'contains':
				return $this->compareContains( $actual_value, $expected_value );

			case 'not_contains':
				return ! $this->compareContains( $actual_value, $expected_value );

			case 'starts_with':
				return $this->compareStartsWith( $actual_value, $expected_value );

			case 'ends_with':
				return $this->compareEndsWith( $actual_value, $expected_value );

			case 'regex':
				return $this->compareRegex( $actual_value, $expected_value );

			case 'greater_than':
				return $this->compareGreaterThan( $actual_value, $expected_value );

			case 'less_than':
				return $this->compareLessThan( $actual_value, $expected_value );

			case 'greater_than_or_equal':
				return $this->compareGreaterThanOrEqual( $actual_value, $expected_value );

			case 'less_than_or_equal':
				return $this->compareLessThanOrEqual( $actual_value, $expected_value );

			case 'between':
				return $this->compareBetween( $actual_value, $expected_value );

			case 'in_array':
				return $this->compareInArray( $actual_value, $expected_value );

			case 'not_in_array':
				return ! $this->compareInArray( $actual_value, $expected_value );

			default:
				// Log unknown operator.
				do_action( 'vira_code/conditional_logic_unknown_operator', $operator, $this->getType() );
				return false;
		}
	}

	/**
	 * Compare values for equality.
	 *
	 * @param mixed $actual   Actual value.
	 * @param mixed $expected Expected value.
	 * @return bool
	 */
	protected function compareEquals( $actual, $expected ) {
		// Handle array comparison.
		if ( is_array( $actual ) && is_array( $expected ) ) {
			return array_intersect( $actual, $expected ) === $expected;
		}

		// Handle string comparison (case-insensitive).
		if ( is_string( $actual ) && is_string( $expected ) ) {
			return strcasecmp( $actual, $expected ) === 0;
		}

		// Handle boolean comparison.
		if ( is_bool( $actual ) || is_bool( $expected ) ) {
			return (bool) $actual === (bool) $expected;
		}

		// Default comparison.
		return $actual == $expected; // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
	}

	/**
	 * Check if actual value contains expected value.
	 *
	 * @param mixed $actual   Actual value.
	 * @param mixed $expected Expected value.
	 * @return bool
	 */
	protected function compareContains( $actual, $expected ) {
		if ( is_array( $actual ) ) {
			return in_array( $expected, $actual, false );
		}

		if ( is_string( $actual ) && is_string( $expected ) ) {
			return false !== stripos( $actual, $expected );
		}

		return false;
	}

	/**
	 * Check if actual value starts with expected value.
	 *
	 * @param mixed $actual   Actual value.
	 * @param mixed $expected Expected value.
	 * @return bool
	 */
	protected function compareStartsWith( $actual, $expected ) {
		if ( ! is_string( $actual ) || ! is_string( $expected ) ) {
			return false;
		}

		return 0 === stripos( $actual, $expected );
	}

	/**
	 * Check if actual value ends with expected value.
	 *
	 * @param mixed $actual   Actual value.
	 * @param mixed $expected Expected value.
	 * @return bool
	 */
	protected function compareEndsWith( $actual, $expected ) {
		if ( ! is_string( $actual ) || ! is_string( $expected ) ) {
			return false;
		}

		$length = strlen( $expected );
		if ( 0 === $length ) {
			return true;
		}

		return substr( $actual, -$length ) === $expected;
	}

	/**
	 * Check if actual value matches regex pattern.
	 *
	 * @param mixed $actual   Actual value.
	 * @param mixed $expected Expected regex pattern.
	 * @return bool
	 */
	protected function compareRegex( $actual, $expected ) {
		if ( ! is_string( $actual ) || ! is_string( $expected ) ) {
			return false;
		}

		// Suppress warnings for invalid regex patterns.
		$result = @preg_match( $expected, $actual );

		// Return false for invalid patterns or no match.
		return 1 === $result;
	}

	/**
	 * Check if actual value is greater than expected value.
	 *
	 * @param mixed $actual   Actual value.
	 * @param mixed $expected Expected value.
	 * @return bool
	 */
	protected function compareGreaterThan( $actual, $expected ) {
		if ( ! is_numeric( $actual ) || ! is_numeric( $expected ) ) {
			return false;
		}

		return (float) $actual > (float) $expected;
	}

	/**
	 * Check if actual value is less than expected value.
	 *
	 * @param mixed $actual   Actual value.
	 * @param mixed $expected Expected value.
	 * @return bool
	 */
	protected function compareLessThan( $actual, $expected ) {
		if ( ! is_numeric( $actual ) || ! is_numeric( $expected ) ) {
			return false;
		}

		return (float) $actual < (float) $expected;
	}

	/**
	 * Check if actual value is greater than or equal to expected value.
	 *
	 * @param mixed $actual   Actual value.
	 * @param mixed $expected Expected value.
	 * @return bool
	 */
	protected function compareGreaterThanOrEqual( $actual, $expected ) {
		if ( ! is_numeric( $actual ) || ! is_numeric( $expected ) ) {
			return false;
		}

		return (float) $actual >= (float) $expected;
	}

	/**
	 * Check if actual value is less than or equal to expected value.
	 *
	 * @param mixed $actual   Actual value.
	 * @param mixed $expected Expected value.
	 * @return bool
	 */
	protected function compareLessThanOrEqual( $actual, $expected ) {
		if ( ! is_numeric( $actual ) || ! is_numeric( $expected ) ) {
			return false;
		}

		return (float) $actual <= (float) $expected;
	}

	/**
	 * Check if actual value is between two values.
	 *
	 * @param mixed $actual   Actual value.
	 * @param mixed $expected Expected range (array with min and max).
	 * @return bool
	 */
	protected function compareBetween( $actual, $expected ) {
		if ( ! is_numeric( $actual ) || ! is_array( $expected ) || count( $expected ) !== 2 ) {
			return false;
		}

		$min = (float) $expected[0];
		$max = (float) $expected[1];
		$value = (float) $actual;

		return $value >= $min && $value <= $max;
	}

	/**
	 * Check if actual value is in array of expected values.
	 *
	 * @param mixed $actual   Actual value.
	 * @param mixed $expected Expected array of values.
	 * @return bool
	 */
	protected function compareInArray( $actual, $expected ) {
		if ( ! is_array( $expected ) ) {
			// Convert comma-separated string to array.
			if ( is_string( $expected ) ) {
				$expected = array_map( 'trim', explode( ',', $expected ) );
			} else {
				return false;
			}
		}

		return in_array( $actual, $expected, false );
	}

	/**
	 * Sanitize and prepare condition value.
	 *
	 * @param mixed $value Raw condition value.
	 * @return mixed Sanitized value.
	 */
	protected function sanitizeValue( $value ) {
		// Handle JSON strings.
		if ( is_string( $value ) && $this->isJson( $value ) ) {
			$decoded = json_decode( $value, true );
			if ( json_last_error() === JSON_ERROR_NONE ) {
				return $decoded;
			}
		}

		// Handle comma-separated strings for array operations.
		if ( is_string( $value ) && strpos( $value, ',' ) !== false ) {
			return array_map( 'trim', explode( ',', $value ) );
		}

		// Handle boolean strings.
		if ( is_string( $value ) ) {
			$lower_value = strtolower( trim( $value ) );
			if ( in_array( $lower_value, array( 'true', 'false', '1', '0', 'yes', 'no' ), true ) ) {
				return in_array( $lower_value, array( 'true', '1', 'yes' ), true );
			}
		}

		return $value;
	}

	/**
	 * Check if string is valid JSON.
	 *
	 * @param string $string String to check.
	 * @return bool
	 */
	protected function isJson( $string ) {
		if ( ! is_string( $string ) ) {
			return false;
		}

		json_decode( $string );
		return json_last_error() === JSON_ERROR_NONE;
	}

	/**
	 * Log evaluation for debugging purposes.
	 *
	 * @param array $condition Condition being evaluated.
	 * @param array $context   Current context.
	 * @param bool  $result    Evaluation result.
	 * @return void
	 */
	protected function logEvaluation( $condition, $context, $result ) {
		do_action(
			'vira_code/conditional_logic_evaluator_debug',
			$this->getType(),
			$condition,
			$context,
			$result
		);
	}

	/**
	 * Handle evaluation errors gracefully.
	 *
	 * @param \Exception $exception Exception that occurred.
	 * @param array      $condition Condition being evaluated.
	 * @param array      $context   Current context.
	 * @return bool
	 */
	protected function handleEvaluationError( $exception, $condition, $context ) {
		$error_context = array(
			'evaluator_type' => $this->getType(),
			'condition' => $condition,
			'context_keys' => array_keys( $context ),
			'exception_class' => get_class( $exception ),
			'file' => $exception->getFile(),
			'line' => $exception->getLine(),
		);

		// Log the error with detailed context.
		$this->logEvaluatorError( $exception->getMessage(), $error_context );

		// Fire action for custom error handling.
		do_action(
			'vira_code/conditional_logic_evaluator_error',
			$this->getType(),
			$exception->getMessage(),
			$condition,
			$context,
			$error_context
		);

		// Apply fallback behavior based on error type and configuration.
		return $this->applyEvaluatorFallbackBehavior( $exception, $condition, $context );
	}

	/**
	 * Handle fatal errors during evaluation.
	 *
	 * @param \Error $error     Fatal error that occurred.
	 * @param array  $condition Condition being evaluated.
	 * @param array  $context   Current context.
	 * @return bool
	 */
	protected function handleEvaluationFatalError( $error, $condition, $context ) {
		$error_context = array(
			'evaluator_type' => $this->getType(),
			'condition' => $condition,
			'context_keys' => array_keys( $context ),
			'error_class' => get_class( $error ),
			'file' => $error->getFile(),
			'line' => $error->getLine(),
		);

		// Log the fatal error.
		$this->logEvaluatorError( 'Fatal Error: ' . $error->getMessage(), $error_context );

		// Fire action for fatal error handling.
		do_action(
			'vira_code/conditional_logic_evaluator_fatal_error',
			$this->getType(),
			$error->getMessage(),
			$condition,
			$context,
			$error_context
		);

		// Always return false for fatal errors.
		return false;
	}

	/**
	 * Handle throwables during evaluation.
	 *
	 * @param \Throwable $throwable Throwable that occurred.
	 * @param array      $condition Condition being evaluated.
	 * @param array      $context   Current context.
	 * @return bool
	 */
	protected function handleEvaluationThrowable( $throwable, $condition, $context ) {
		$error_context = array(
			'evaluator_type' => $this->getType(),
			'condition' => $condition,
			'context_keys' => array_keys( $context ),
			'throwable_class' => get_class( $throwable ),
			'file' => $throwable->getFile(),
			'line' => $throwable->getLine(),
		);

		// Log the throwable.
		$this->logEvaluatorError( 'Throwable: ' . $throwable->getMessage(), $error_context );

		// Fire action for throwable handling.
		do_action(
			'vira_code/conditional_logic_evaluator_throwable',
			$this->getType(),
			$throwable->getMessage(),
			$condition,
			$context,
			$error_context
		);

		// Apply fallback behavior.
		return $this->applyEvaluatorFallbackBehavior( $throwable, $condition, $context );
	}

	/**
	 * Apply fallback behavior for evaluator errors.
	 *
	 * @param \Throwable $throwable Throwable that occurred.
	 * @param array      $condition Condition being evaluated.
	 * @param array      $context   Current context.
	 * @return bool
	 */
	protected function applyEvaluatorFallbackBehavior( $throwable, $condition, $context ) {
		// Get evaluator-specific fallback behavior.
		$fallback_behavior = apply_filters( 
			'vira_code/evaluator_fallback_behavior', 
			'deny', 
			$this->getType(), 
			$throwable, 
			$condition, 
			$context 
		);

		// Get type-specific fallback behavior.
		$fallback_behavior = apply_filters( 
			"vira_code/evaluator_fallback_behavior/{$this->getType()}", 
			$fallback_behavior, 
			$throwable, 
			$condition, 
			$context 
		);

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
		$this->logEvaluatorError( "Applied evaluator fallback behavior: {$fallback_behavior}", array(
			'evaluator_type' => $this->getType(),
			'fallback_result' => $result,
			'throwable_class' => get_class( $throwable ),
		) );

		// Fire action for fallback application.
		do_action( 
			'vira_code/evaluator_fallback_applied', 
			$this->getType(), 
			$fallback_behavior, 
			$result, 
			$throwable, 
			$condition, 
			$context 
		);

		return $result;
	}

	/**
	 * Log evaluator errors with structured data.
	 *
	 * @param string $message Error message.
	 * @param array  $context Error context.
	 * @return void
	 */
	protected function logEvaluatorError( $message, $context = array() ) {
		$log_entry = array(
			'timestamp' => current_time( 'mysql' ),
			'evaluator_type' => $this->getType(),
			'message' => $message,
			'context' => $context,
			'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
			'user_id' => get_current_user_id(),
		);

		// Store in transient for debugging.
		$error_logs = get_transient( 'vira_code_evaluator_error_logs' );
		if ( ! is_array( $error_logs ) ) {
			$error_logs = array();
		}

		$error_logs[] = $log_entry;

		// Keep only last 50 error logs.
		if ( count( $error_logs ) > 50 ) {
			$error_logs = array_slice( $error_logs, -50 );
		}

		set_transient( 'vira_code_evaluator_error_logs', $error_logs, 24 * HOUR_IN_SECONDS );

		// Fire action for custom error handling.
		do_action( 'vira_code/evaluator_error_logged', $log_entry );

		// Log to WordPress error log if enabled.
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( sprintf( 'Vira Code Evaluator Error [%s]: %s - Context: %s', $this->getType(), $message, wp_json_encode( $context ) ) );
		}
	}

	/**
	 * Get context value with fallback.
	 *
	 * @param array  $context Current context.
	 * @param string $key     Context key to retrieve.
	 * @param mixed  $default Default value if key not found.
	 * @return mixed
	 */
	protected function getContextValue( $context, $key, $default = null ) {
		return isset( $context[ $key ] ) ? $context[ $key ] : $default;
	}

	/**
	 * Evaluate condition with comprehensive error handling.
	 *
	 * @param array $condition Condition data.
	 * @param array $context   Current context.
	 * @return bool
	 */
	public function evaluate( array $condition, array $context = array() ) {
		$start_time = microtime( true );

		try {
			// Validate condition before evaluation.
			$validation = $this->validate( $condition );
			if ( ! $validation['valid'] ) {
				$this->logEvaluatorError( 'Condition validation failed: ' . implode( ', ', $validation['errors'] ), array(
					'condition' => $condition,
					'validation_errors' => $validation['errors'],
				) );
				return false;
			}

			// Sanitize condition value.
			$condition['value'] = $this->sanitizeValue( $condition['value'] );

			// Perform the actual evaluation.
			$result = $this->doEvaluate( $condition, $context );

			// Log successful evaluation.
			$evaluation_time = microtime( true ) - $start_time;
			$this->logEvaluation( $condition, $context, $result );

			// Log performance if slow.
			$slow_threshold = apply_filters( 'vira_code/evaluator_slow_threshold', 0.1 ); // 100ms
			if ( $evaluation_time > $slow_threshold ) {
				$this->logEvaluatorError( "Slow evaluation detected", array(
					'evaluation_time' => $evaluation_time,
					'threshold' => $slow_threshold,
					'condition_type' => $condition['type'],
				) );
			}

			return (bool) $result;

		} catch ( \Exception $e ) {
			return $this->handleEvaluationError( $e, $condition, $context );
		} catch ( \Error $e ) {
			return $this->handleEvaluationFatalError( $e, $condition, $context );
		} catch ( \Throwable $e ) {
			return $this->handleEvaluationThrowable( $e, $condition, $context );
		}
	}

	/**
	 * Abstract method for evaluating conditions.
	 * Must be implemented by child classes.
	 *
	 * @param array $condition Condition data.
	 * @param array $context   Current context.
	 * @return bool
	 */
	abstract protected function doEvaluate( array $condition, array $context = array() );
}