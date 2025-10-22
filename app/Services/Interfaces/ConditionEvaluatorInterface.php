<?php
/**
 * Condition Evaluator Interface
 *
 * @package ViraCode
 */

namespace ViraCode\Services\Interfaces;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ConditionEvaluatorInterface
 *
 * Interface contract for all condition evaluators.
 */
interface ConditionEvaluatorInterface {

	/**
	 * Evaluate a condition against the current context.
	 *
	 * @param array $condition Condition data containing type, operator, and value.
	 * @param array $context   Current context data for evaluation.
	 * @return bool True if condition passes, false otherwise.
	 */
	public function evaluate( array $condition, array $context = array() );

	/**
	 * Get the condition type this evaluator handles.
	 *
	 * @return string Condition type identifier.
	 */
	public function getType();

	/**
	 * Validate a condition structure and values.
	 *
	 * @param array $condition Condition data to validate.
	 * @return array Validation result with 'valid' boolean and 'errors' array.
	 */
	public function validate( array $condition );

	/**
	 * Get supported operators for this condition type.
	 *
	 * @return array Array of supported operator strings.
	 */
	public function getSupportedOperators();

	/**
	 * Get human-readable description of this condition type.
	 *
	 * @return string Description text.
	 */
	public function getDescription();
}