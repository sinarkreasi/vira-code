<?php
/**
 * Device Type Condition Evaluator
 *
 * @package ViraCode
 */

namespace ViraCode\Services\Evaluators;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * DeviceTypeEvaluator Class
 *
 * Evaluates device type conditions using WordPress mobile detection and user agent parsing.
 */
class DeviceTypeEvaluator extends AbstractConditionEvaluator {

	/**
	 * Condition type identifier.
	 *
	 * @var string
	 */
	protected $type = 'device_type';

	/**
	 * Supported operators for device type conditions.
	 *
	 * @var array
	 */
	protected $supported_operators = array( 'equals', 'not_equals', 'in_array', 'not_in_array' );

	/**
	 * Human-readable description.
	 *
	 * @var string
	 */
	protected $description = 'Evaluate conditions based on device type (desktop, mobile, tablet)';

	/**
	 * Supported device types.
	 *
	 * @var array
	 */
	protected $supported_device_types = array( 'desktop', 'mobile', 'tablet' );

	/**
	 * Device detection cache.
	 *
	 * @var array
	 */
	protected static $device_cache = array();

	/**
	 * Evaluate device type condition.
	 *
	 * @param array $condition Condition data containing type, operator, and value.
	 * @param array $context   Current context data for evaluation.
	 * @return bool
	 */
	protected function doEvaluate( array $condition, array $context = array() ) {
		$expected_device = $condition['value'];
		$operator = $condition['operator'];

		// Get current device type.
		$current_device = $this->getCurrentDeviceType( $context );

		// Apply operator logic.
		return $this->applyOperator( $current_device, $expected_device, $operator );
	}

	/**
	 * Validate device type value.
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
			$result['errors'][] = 'Device type value cannot be empty';
			return $result;
		}

		// Handle array values for in_array operators.
		if ( in_array( $operator, array( 'in_array', 'not_in_array' ), true ) ) {
			if ( is_string( $value ) ) {
				$value = array_map( 'trim', explode( ',', $value ) );
			}

			if ( ! is_array( $value ) ) {
				$result['valid'] = false;
				$result['errors'][] = 'Device type value must be an array for in_array operators';
				return $result;
			}

			foreach ( $value as $device_type ) {
				if ( ! in_array( $device_type, $this->supported_device_types, true ) ) {
					$result['errors'][] = "Invalid device type: '{$device_type}'. Supported types: " . implode( ', ', $this->supported_device_types );
				}
			}
		} else {
			if ( ! is_string( $value ) ) {
				$result['valid'] = false;
				$result['errors'][] = 'Device type value must be a string';
				return $result;
			}

			if ( ! in_array( $value, $this->supported_device_types, true ) ) {
				$result['valid'] = false;
				$result['errors'][] = "Invalid device type: '{$value}'. Supported types: " . implode( ', ', $this->supported_device_types );
			}
		}

		if ( ! empty( $result['errors'] ) ) {
			$result['valid'] = false;
		}

		return $result;
	}

	/**
	 * Get current device type from context or detection.
	 *
	 * @param array $context Current context data.
	 * @return string
	 */
	protected function getCurrentDeviceType( $context ) {
		// Check if device type is provided in context (for testing).
		if ( isset( $context['device_type'] ) ) {
			return $context['device_type'];
		}

		// Check cache first.
		$cache_key = $this->getDeviceCacheKey( $context );
		if ( isset( self::$device_cache[ $cache_key ] ) ) {
			return self::$device_cache[ $cache_key ];
		}

		// Detect device type.
		$device_type = $this->detectDeviceType( $context );

		// Cache the result.
		self::$device_cache[ $cache_key ] = $device_type;

		return $device_type;
	}

	/**
	 * Detect device type using WordPress functions and user agent parsing.
	 *
	 * @param array $context Current context data.
	 * @return string
	 */
	protected function detectDeviceType( $context ) {
		// Get user agent from context or server.
		$user_agent = $this->getUserAgent( $context );

		// Use WordPress mobile detection first.
		if ( function_exists( 'wp_is_mobile' ) && wp_is_mobile() ) {
			// Further distinguish between mobile and tablet.
			if ( $this->isTablet( $user_agent ) ) {
				return 'tablet';
			}
			return 'mobile';
		}

		// Fallback to user agent parsing if wp_is_mobile is not available or returns false.
		if ( $this->isTablet( $user_agent ) ) {
			return 'tablet';
		}

		if ( $this->isMobile( $user_agent ) ) {
			return 'mobile';
		}

		return 'desktop';
	}

	/**
	 * Get user agent from context or server.
	 *
	 * @param array $context Current context data.
	 * @return string
	 */
	protected function getUserAgent( $context ) {
		// Check if user agent is provided in context.
		if ( isset( $context['user_agent'] ) ) {
			return $context['user_agent'];
		}

		// Get from server.
		return isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '';
	}

	/**
	 * Check if user agent indicates a tablet device.
	 *
	 * @param string $user_agent User agent string.
	 * @return bool
	 */
	protected function isTablet( $user_agent ) {
		if ( empty( $user_agent ) ) {
			return false;
		}

		$tablet_patterns = array(
			'/iPad/i',
			'/Android.*Tablet/i',
			'/Android.*Tab/i',
			'/Kindle/i',
			'/PlayBook/i',
			'/Nexus\s*[0-9]+/i',
			'/Xoom/i',
			'/SM-T/i',
			'/GT-P/i',
			'/KFAPWI/i',
			'/Surface/i',
		);

		foreach ( $tablet_patterns as $pattern ) {
			if ( preg_match( $pattern, $user_agent ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if user agent indicates a mobile device.
	 *
	 * @param string $user_agent User agent string.
	 * @return bool
	 */
	protected function isMobile( $user_agent ) {
		if ( empty( $user_agent ) ) {
			return false;
		}

		$mobile_patterns = array(
			'/Mobile/i',
			'/Android/i',
			'/iPhone/i',
			'/iPod/i',
			'/BlackBerry/i',
			'/Windows Phone/i',
			'/webOS/i',
			'/Opera Mini/i',
			'/IEMobile/i',
			'/Mobile.*Firefox/i',
		);

		foreach ( $mobile_patterns as $pattern ) {
			if ( preg_match( $pattern, $user_agent ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Generate cache key for device detection.
	 *
	 * @param array $context Current context data.
	 * @return string
	 */
	protected function getDeviceCacheKey( $context ) {
		$user_agent = $this->getUserAgent( $context );
		return md5( $user_agent );
	}

	/**
	 * Clear device detection cache.
	 *
	 * @return void
	 */
	public static function clearCache() {
		self::$device_cache = array();
	}

	/**
	 * Get supported device types.
	 *
	 * @return array
	 */
	public function getSupportedDeviceTypes() {
		return $this->supported_device_types;
	}

	/**
	 * Get device type descriptions for UI.
	 *
	 * @return array
	 */
	public function getDeviceTypeDescriptions() {
		return array(
			'desktop' => 'Desktop computers and laptops',
			'mobile'  => 'Mobile phones and smartphones',
			'tablet'  => 'Tablets and iPad devices',
		);
	}

	/**
	 * Get detection examples for UI help.
	 *
	 * @return array
	 */
	public function getDetectionExamples() {
		return array(
			'mobile' => array(
				'iPhone, Android phones',
				'Windows Phone devices',
				'BlackBerry devices',
			),
			'tablet' => array(
				'iPad, Android tablets',
				'Kindle Fire devices',
				'Surface tablets',
			),
			'desktop' => array(
				'Windows computers',
				'Mac computers',
				'Linux desktops',
			),
		);
	}

	/**
	 * Test device detection with custom user agent.
	 *
	 * @param string $user_agent User agent to test.
	 * @return string
	 */
	public function testDeviceDetection( $user_agent ) {
		$context = array( 'user_agent' => $user_agent );
		return $this->detectDeviceType( $context );
	}
}