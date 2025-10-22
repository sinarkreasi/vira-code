<?php
/**
 * URL Pattern Condition Evaluator
 *
 * @package ViraCode
 */

namespace ViraCode\Services\Evaluators;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * UrlPatternEvaluator Class
 *
 * Evaluates URL pattern conditions with various matching operators.
 */
class UrlPatternEvaluator extends AbstractConditionEvaluator {

	/**
	 * Condition type identifier.
	 *
	 * @var string
	 */
	protected $type = 'url_pattern';

	/**
	 * Supported operators for URL pattern conditions.
	 *
	 * @var array
	 */
	protected $supported_operators = array(
		'equals',
		'not_equals',
		'contains',
		'not_contains',
		'starts_with',
		'ends_with',
		'regex',
	);

	/**
	 * Human-readable description.
	 *
	 * @var string
	 */
	protected $description = 'Evaluate conditions based on URL patterns and matching rules';

	/**
	 * Evaluate URL pattern condition.
	 *
	 * @param array $condition Condition data containing type, operator, and value.
	 * @param array $context   Current context data for evaluation.
	 * @return bool
	 */
	protected function doEvaluate( array $condition, array $context = array() ) {
		$pattern = $condition['value'];
		$operator = $condition['operator'];

		// Get current URL from context or WordPress functions.
		$current_url = $this->getCurrentUrl( $context );

		// Apply operator logic.
		return $this->applyOperator( $current_url, $pattern, $operator );
	}

	/**
	 * Validate URL pattern value.
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

		if ( empty( $value ) && 'regex' !== $operator ) {
			$result['valid'] = false;
			$result['errors'][] = 'URL pattern value cannot be empty';
			return $result;
		}

		if ( ! is_string( $value ) ) {
			$result['valid'] = false;
			$result['errors'][] = 'URL pattern value must be a string';
			return $result;
		}

		// Validate regex patterns.
		if ( 'regex' === $operator ) {
			$regex_validation = $this->validateRegexPattern( $value );
			if ( ! $regex_validation['valid'] ) {
				$result['valid'] = false;
				$result['errors'] = array_merge( $result['errors'], $regex_validation['errors'] );
			}
		}

		// Validate URL format for equals operator.
		if ( 'equals' === $operator && ! $this->isValidUrlFormat( $value ) ) {
			$result['errors'][] = 'URL pattern should be a valid URL format for equals operator';
		}

		return $result;
	}

	/**
	 * Get current URL from context or WordPress functions.
	 *
	 * @param array $context Current context data.
	 * @return string
	 */
	protected function getCurrentUrl( $context ) {
		// Check if URL is provided in context (for testing).
		if ( isset( $context['request_uri'] ) ) {
			return $context['request_uri'];
		}

		if ( isset( $context['current_url'] ) ) {
			return $context['current_url'];
		}

		// Get current request URI.
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';

		// Sanitize the URI.
		$request_uri = esc_url_raw( $request_uri );

		// Remove query parameters if needed.
		$parsed_uri = wp_parse_url( $request_uri );
		$clean_uri = isset( $parsed_uri['path'] ) ? $parsed_uri['path'] : '/';

		return $clean_uri;
	}

	/**
	 * Get full current URL including domain.
	 *
	 * @param array $context Current context data.
	 * @return string
	 */
	protected function getFullCurrentUrl( $context ) {
		// Check if full URL is provided in context.
		if ( isset( $context['full_url'] ) ) {
			return $context['full_url'];
		}

		// Build full URL.
		$protocol = is_ssl() ? 'https://' : 'http://';
		$host = isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : '';
		$request_uri = $this->getCurrentUrl( $context );

		return $protocol . $host . $request_uri;
	}

	/**
	 * Validate regex pattern.
	 *
	 * @param string $pattern Regex pattern to validate.
	 * @return array
	 */
	protected function validateRegexPattern( $pattern ) {
		$result = array(
			'valid'  => true,
			'errors' => array(),
		);

		if ( empty( $pattern ) ) {
			$result['valid'] = false;
			$result['errors'][] = 'Regex pattern cannot be empty';
			return $result;
		}

		// Add delimiters if not present.
		$test_pattern = $this->addRegexDelimiters( $pattern );

		// Test regex validity.
		$test_result = @preg_match( $test_pattern, '' );

		if ( false === $test_result ) {
			$result['valid'] = false;
			$result['errors'][] = 'Invalid regex pattern: ' . preg_last_error_msg();
		}

		return $result;
	}

	/**
	 * Add regex delimiters if not present.
	 *
	 * @param string $pattern Regex pattern.
	 * @return string
	 */
	protected function addRegexDelimiters( $pattern ) {
		// Check if pattern already has delimiters.
		if ( preg_match( '/^[\/~#%].*[\/~#%][imsxADSUXJu]*$/', $pattern ) ) {
			return $pattern;
		}

		// Add forward slash delimiters.
		return '/' . $pattern . '/i';
	}

	/**
	 * Check if value is a valid URL format.
	 *
	 * @param string $value Value to check.
	 * @return bool
	 */
	protected function isValidUrlFormat( $value ) {
		// Allow relative URLs starting with /.
		if ( 0 === strpos( $value, '/' ) ) {
			return true;
		}

		// Allow full URLs.
		return false !== filter_var( $value, FILTER_VALIDATE_URL );
	}

	/**
	 * Override regex comparison to handle delimiter addition.
	 *
	 * @param mixed $actual   Actual value.
	 * @param mixed $expected Expected regex pattern.
	 * @return bool
	 */
	protected function compareRegex( $actual, $expected ) {
		if ( ! is_string( $actual ) || ! is_string( $expected ) ) {
			return false;
		}

		// Add delimiters if needed.
		$pattern = $this->addRegexDelimiters( $expected );

		// Suppress warnings for invalid regex patterns.
		$result = @preg_match( $pattern, $actual );

		// Return false for invalid patterns or no match.
		return 1 === $result;
	}

	/**
	 * Sanitize URL pattern value.
	 *
	 * @param mixed $value Raw pattern value.
	 * @return string
	 */
	protected function sanitizeValue( $value ) {
		if ( ! is_string( $value ) ) {
			return '';
		}

		// Basic sanitization - remove dangerous characters but preserve regex special chars.
		$value = trim( $value );

		// Remove null bytes.
		$value = str_replace( "\0", '', $value );

		return $value;
	}

	/**
	 * Get URL matching examples for UI help.
	 *
	 * @return array
	 */
	public function getExamples() {
		return array(
			'equals' => array(
				'/shop/products/',
				'https://example.com/page/',
			),
			'contains' => array(
				'/shop/',
				'product',
			),
			'starts_with' => array(
				'/admin/',
				'/wp-',
			),
			'ends_with' => array(
				'.pdf',
				'/checkout/',
			),
			'regex' => array(
				'/product/\d+/',
				'^/category/[a-z-]+/$',
			),
		);
	}

	/**
	 * Get operator descriptions for UI.
	 *
	 * @return array
	 */
	public function getOperatorDescriptions() {
		return array(
			'equals'       => 'URL exactly matches the pattern',
			'not_equals'   => 'URL does not match the pattern',
			'contains'     => 'URL contains the pattern anywhere',
			'not_contains' => 'URL does not contain the pattern',
			'starts_with'  => 'URL starts with the pattern',
			'ends_with'    => 'URL ends with the pattern',
			'regex'        => 'URL matches the regular expression pattern',
		);
	}
}