<?php
/**
 * Date Range Condition Evaluator
 *
 * @package ViraCode
 */

namespace ViraCode\Services\Evaluators;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * DateRangeEvaluator Class
 *
 * Evaluates date range conditions using WordPress time functions.
 */
class DateRangeEvaluator extends AbstractConditionEvaluator {

	/**
	 * Condition type identifier.
	 *
	 * @var string
	 */
	protected $type = 'date_range';

	/**
	 * Supported operators for date range conditions.
	 *
	 * @var array
	 */
	protected $supported_operators = array(
		'equals',
		'not_equals',
		'before',
		'after',
		'between',
		'greater_than',
		'less_than',
		'greater_than_or_equal',
		'less_than_or_equal',
	);

	/**
	 * Human-readable description.
	 *
	 * @var string
	 */
	protected $description = 'Evaluate conditions based on date ranges and time comparisons';

	/**
	 * Evaluate date range condition.
	 *
	 * @param array $condition Condition data containing type, operator, and value.
	 * @param array $context   Current context data for evaluation.
	 * @return bool
	 */
	protected function doEvaluate( array $condition, array $context = array() ) {
		$expected_date = $condition['value'];
		$operator = $condition['operator'];

		// Get current date/time.
		$current_date = $this->getCurrentDateTime( $context );

		// Apply operator logic.
		return $this->applyOperator( $current_date, $expected_date, $operator );
	}	
/**
	 * Validate date range value.
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

		if ( empty( $value ) ) {
			$result['valid'] = false;
			$result['errors'][] = 'Date value cannot be empty';
			return $result;
		}

		// Handle between operator which expects array with start and end dates.
		if ( 'between' === $operator ) {
			if ( ! is_array( $value ) || count( $value ) !== 2 ) {
				$result['valid'] = false;
				$result['errors'][] = 'Between operator requires array with start and end dates';
				return $result;
			}

			foreach ( $value as $date ) {
				if ( ! $this->isValidDateFormat( $date ) ) {
					$result['valid'] = false;
					$result['errors'][] = "Invalid date format: '{$date}'. Use Y-m-d H:i:s or Y-m-d format";
				}
			}
		} else {
			if ( ! $this->isValidDateFormat( $value ) ) {
				$result['valid'] = false;
				$result['errors'][] = "Invalid date format: '{$value}'. Use Y-m-d H:i:s or Y-m-d format";
			}
		}

		return $result;
	}

	/**
	 * Get current date/time from context or WordPress functions.
	 *
	 * @param array $context Current context data.
	 * @return string
	 */
	protected function getCurrentDateTime( $context ) {
		// Check if current date is provided in context (for testing).
		if ( isset( $context['current_date'] ) ) {
			return $context['current_date'];
		}

		if ( isset( $context['current_time'] ) ) {
			return $context['current_time'];
		}

		// Use WordPress current_time function with proper timezone handling.
		return current_time( 'Y-m-d H:i:s' );
	}

	/**
	 * Check if date format is valid.
	 *
	 * @param mixed $date Date to validate.
	 * @return bool
	 */
	protected function isValidDateFormat( $date ) {
		if ( ! is_string( $date ) ) {
			return false;
		}

		// Try to parse the date.
		$timestamp = strtotime( $date );
		return false !== $timestamp;
	}

	/**
	 * Convert date string to timestamp for comparison.
	 *
	 * @param string $date Date string.
	 * @return int
	 */
	protected function dateToTimestamp( $date ) {
		if ( is_numeric( $date ) ) {
			return (int) $date;
		}

		$timestamp = strtotime( $date );
		return false !== $timestamp ? $timestamp : 0;
	}	
/**
	 * Override comparison methods for date-specific logic.
	 *
	 * @param mixed $actual   Actual date value.
	 * @param mixed $expected Expected date value.
	 * @return bool
	 */
	protected function compareEquals( $actual, $expected ) {
		$actual_timestamp = $this->dateToTimestamp( $actual );
		$expected_timestamp = $this->dateToTimestamp( $expected );

		// Compare dates by day (ignore time).
		$actual_date = date( 'Y-m-d', $actual_timestamp );
		$expected_date = date( 'Y-m-d', $expected_timestamp );

		return $actual_date === $expected_date;
	}

	/**
	 * Override greater than comparison for dates.
	 *
	 * @param mixed $actual   Actual date value.
	 * @param mixed $expected Expected date value.
	 * @return bool
	 */
	protected function compareGreaterThan( $actual, $expected ) {
		$actual_timestamp = $this->dateToTimestamp( $actual );
		$expected_timestamp = $this->dateToTimestamp( $expected );

		return $actual_timestamp > $expected_timestamp;
	}

	/**
	 * Override less than comparison for dates.
	 *
	 * @param mixed $actual   Actual date value.
	 * @param mixed $expected Expected date value.
	 * @return bool
	 */
	protected function compareLessThan( $actual, $expected ) {
		$actual_timestamp = $this->dateToTimestamp( $actual );
		$expected_timestamp = $this->dateToTimestamp( $expected );

		return $actual_timestamp < $expected_timestamp;
	}

	/**
	 * Override greater than or equal comparison for dates.
	 *
	 * @param mixed $actual   Actual date value.
	 * @param mixed $expected Expected date value.
	 * @return bool
	 */
	protected function compareGreaterThanOrEqual( $actual, $expected ) {
		$actual_timestamp = $this->dateToTimestamp( $actual );
		$expected_timestamp = $this->dateToTimestamp( $expected );

		return $actual_timestamp >= $expected_timestamp;
	}

	/**
	 * Override less than or equal comparison for dates.
	 *
	 * @param mixed $actual   Actual date value.
	 * @param mixed $expected Expected date value.
	 * @return bool
	 */
	protected function compareLessThanOrEqual( $actual, $expected ) {
		$actual_timestamp = $this->dateToTimestamp( $actual );
		$expected_timestamp = $this->dateToTimestamp( $expected );

		return $actual_timestamp <= $expected_timestamp;
	}	/**

	 * Override between comparison for date ranges.
	 *
	 * @param mixed $actual   Actual date value.
	 * @param mixed $expected Expected date range (array with start and end).
	 * @return bool
	 */
	protected function compareBetween( $actual, $expected ) {
		if ( ! is_array( $expected ) || count( $expected ) !== 2 ) {
			return false;
		}

		$actual_timestamp = $this->dateToTimestamp( $actual );
		$start_timestamp = $this->dateToTimestamp( $expected[0] );
		$end_timestamp = $this->dateToTimestamp( $expected[1] );

		return $actual_timestamp >= $start_timestamp && $actual_timestamp <= $end_timestamp;
	}

	/**
	 * Sanitize date value.
	 *
	 * @param mixed $value Raw date value.
	 * @return mixed
	 */
	protected function sanitizeValue( $value ) {
		// Handle array for between operator.
		if ( is_array( $value ) ) {
			return array_map( array( $this, 'sanitizeDateString' ), $value );
		}

		return $this->sanitizeDateString( $value );
	}

	/**
	 * Sanitize individual date string.
	 *
	 * @param mixed $date Date string to sanitize.
	 * @return string
	 */
	protected function sanitizeDateString( $date ) {
		if ( ! is_string( $date ) ) {
			return '';
		}

		// Remove any dangerous characters.
		$date = trim( $date );
		$date = str_replace( array( "\0", "\r", "\n" ), '', $date );

		return $date;
	}

	/**
	 * Get date format examples for UI help.
	 *
	 * @return array
	 */
	public function getDateFormatExamples() {
		return array(
			'Y-m-d'       => '2024-12-25 (Date only)',
			'Y-m-d H:i:s' => '2024-12-25 14:30:00 (Date and time)',
			'relative'    => 'tomorrow, next week, +1 month',
		);
	}

	/**
	 * Get operator descriptions for UI.
	 *
	 * @return array
	 */
	public function getOperatorDescriptions() {
		return array(
			'equals'                => 'Date equals the specified date (ignores time)',
			'not_equals'            => 'Date does not equal the specified date',
			'before'                => 'Current date/time is before the specified date/time',
			'after'                 => 'Current date/time is after the specified date/time',
			'between'               => 'Current date/time is between two specified dates',
			'greater_than'          => 'Same as after',
			'less_than'             => 'Same as before',
			'greater_than_or_equal' => 'Current date/time is after or equal to specified date/time',
			'less_than_or_equal'    => 'Current date/time is before or equal to specified date/time',
		);
	}

	/**
	 * Get timezone information for UI.
	 *
	 * @return array
	 */
	public function getTimezoneInfo() {
		$timezone = wp_timezone_string();
		$current_time = current_time( 'Y-m-d H:i:s' );

		return array(
			'timezone'     => $timezone,
			'current_time' => $current_time,
			'utc_offset'   => get_option( 'gmt_offset' ),
		);
	}
}