<?php
/**
 * User Role Condition Evaluator
 *
 * @package ViraCode
 */

namespace ViraCode\Services\Evaluators;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * UserRoleEvaluator Class
 *
 * Evaluates user role and login status conditions.
 */
class UserRoleEvaluator extends AbstractConditionEvaluator {

	/**
	 * Condition type identifier.
	 *
	 * @var string
	 */
	protected $type = 'user_role';

	/**
	 * Supported operators for user role conditions.
	 *
	 * @var array
	 */
	protected $supported_operators = array(
		'equals',
		'not_equals',
		'has_capability',
		'in_array',
		'not_in_array',
	);

	/**
	 * Human-readable description.
	 *
	 * @var string
	 */
	protected $description = 'Evaluate conditions based on user roles, login status, and capabilities';

	/**
	 * Supported user role values.
	 *
	 * @var array
	 */
	protected $supported_roles = array(
		'administrator',
		'editor',
		'author',
		'contributor',
		'subscriber',
		'logged_in',
		'logged_out',
		'guest',
	);

	/**
	 * Evaluate user role condition.
	 *
	 * @param array $condition Condition data containing type, operator, and value.
	 * @param array $context   Current context data for evaluation.
	 * @return bool
	 */
	protected function doEvaluate( array $condition, array $context = array() ) {
		$expected_role = $condition['value'];
		$operator = $condition['operator'];

		// Get current user data.
		$user_data = $this->getCurrentUserData( $context );

		// Handle different types of role checks.
		return $this->evaluateUserRole( $user_data, $expected_role, $operator );
	}

	/**
	 * Validate user role value.
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
			$result['errors'][] = 'User role value cannot be empty';
			return $result;
		}

		// Handle array values for in_array operators.
		if ( in_array( $operator, array( 'in_array', 'not_in_array' ), true ) ) {
			if ( is_string( $value ) ) {
				$value = array_map( 'trim', explode( ',', $value ) );
			}

			if ( ! is_array( $value ) ) {
				$result['valid'] = false;
				$result['errors'][] = 'User role value must be an array for in_array operators';
				return $result;
			}

			foreach ( $value as $role ) {
				if ( ! $this->isValidRoleOrCapability( $role ) ) {
					$result['errors'][] = "Invalid user role or capability: '{$role}'";
				}
			}
		} else {
			if ( ! is_string( $value ) ) {
				$result['valid'] = false;
				$result['errors'][] = 'User role value must be a string';
				return $result;
			}

			if ( ! $this->isValidRoleOrCapability( $value ) ) {
				$result['valid'] = false;
				$result['errors'][] = "Invalid user role or capability: '{$value}'";
			}
		}

		if ( ! empty( $result['errors'] ) ) {
			$result['valid'] = false;
		}

		return $result;
	}

	/**
	 * Get current user data from context or WordPress functions.
	 *
	 * @param array $context Current context data.
	 * @return array
	 */
	protected function getCurrentUserData( $context ) {
		// Check if user data is provided in context (for testing).
		if ( isset( $context['user_id'] ) ) {
			$user_id = $context['user_id'];
			if ( 0 === $user_id ) {
				return array(
					'id'           => 0,
					'roles'        => array(),
					'capabilities' => array(),
					'logged_in'    => false,
				);
			}

			$user = get_userdata( $user_id );
			if ( $user ) {
				return array(
					'id'           => $user->ID,
					'roles'        => $user->roles,
					'capabilities' => array_keys( $user->allcaps ),
					'logged_in'    => true,
				);
			}
		}

		if ( isset( $context['user_roles'] ) ) {
			return array(
				'id'           => isset( $context['user_id'] ) ? $context['user_id'] : 1,
				'roles'        => $context['user_roles'],
				'capabilities' => isset( $context['user_capabilities'] ) ? $context['user_capabilities'] : array(),
				'logged_in'    => isset( $context['logged_in'] ) ? $context['logged_in'] : true,
			);
		}

		// Get current WordPress user.
		$current_user = wp_get_current_user();

		if ( ! $current_user || 0 === $current_user->ID ) {
			return array(
				'id'           => 0,
				'roles'        => array(),
				'capabilities' => array(),
				'logged_in'    => false,
			);
		}

		return array(
			'id'           => $current_user->ID,
			'roles'        => $current_user->roles,
			'capabilities' => array_keys( $current_user->allcaps ),
			'logged_in'    => true,
		);
	}

	/**
	 * Evaluate user role condition based on operator.
	 *
	 * @param array  $user_data     Current user data.
	 * @param mixed  $expected_role Expected role or capability.
	 * @param string $operator      Comparison operator.
	 * @return bool
	 */
	protected function evaluateUserRole( $user_data, $expected_role, $operator ) {
		switch ( $operator ) {
			case 'equals':
				return $this->checkUserRole( $user_data, $expected_role );

			case 'not_equals':
				return ! $this->checkUserRole( $user_data, $expected_role );

			case 'has_capability':
				return $this->checkUserCapability( $user_data, $expected_role );

			case 'in_array':
				if ( ! is_array( $expected_role ) ) {
					return false;
				}
				foreach ( $expected_role as $role ) {
					if ( $this->checkUserRole( $user_data, $role ) ) {
						return true;
					}
				}
				return false;

			case 'not_in_array':
				if ( ! is_array( $expected_role ) ) {
					return true;
				}
				foreach ( $expected_role as $role ) {
					if ( $this->checkUserRole( $user_data, $role ) ) {
						return false;
					}
				}
				return true;

			default:
				return false;
		}
	}

	/**
	 * Check if user has specific role or login status.
	 *
	 * @param array  $user_data Current user data.
	 * @param string $role      Role to check.
	 * @return bool
	 */
	protected function checkUserRole( $user_data, $role ) {
		switch ( $role ) {
			case 'logged_in':
				return $user_data['logged_in'];

			case 'logged_out':
			case 'guest':
				return ! $user_data['logged_in'];

			case 'administrator':
			case 'editor':
			case 'author':
			case 'contributor':
			case 'subscriber':
				return in_array( $role, $user_data['roles'], true );

			default:
				// Check if it's a custom role.
				return in_array( $role, $user_data['roles'], true );
		}
	}

	/**
	 * Check if user has specific capability.
	 *
	 * @param array  $user_data  Current user data.
	 * @param string $capability Capability to check.
	 * @return bool
	 */
	protected function checkUserCapability( $user_data, $capability ) {
		// If user is not logged in, they have no capabilities.
		if ( ! $user_data['logged_in'] ) {
			return false;
		}

		// Check if capability exists in user's capabilities.
		return in_array( $capability, $user_data['capabilities'], true );
	}

	/**
	 * Check if role or capability is valid.
	 *
	 * @param string $value Role or capability to validate.
	 * @return bool
	 */
	protected function isValidRoleOrCapability( $value ) {
		// Check supported roles.
		if ( in_array( $value, $this->supported_roles, true ) ) {
			return true;
		}

		// Check WordPress roles.
		$wp_roles = wp_roles();
		if ( $wp_roles && isset( $wp_roles->roles[ $value ] ) ) {
			return true;
		}

		// Check WordPress capabilities.
		$all_caps = array();
		if ( $wp_roles ) {
			foreach ( $wp_roles->roles as $role_data ) {
				if ( isset( $role_data['capabilities'] ) ) {
					$all_caps = array_merge( $all_caps, array_keys( $role_data['capabilities'] ) );
				}
			}
		}

		return in_array( $value, $all_caps, true );
	}

	/**
	 * Get all available user roles.
	 *
	 * @return array
	 */
	public function getAvailableRoles() {
		$roles = $this->supported_roles;

		// Add WordPress roles.
		$wp_roles = wp_roles();
		if ( $wp_roles ) {
			$roles = array_merge( $roles, array_keys( $wp_roles->roles ) );
		}

		return array_unique( $roles );
	}

	/**
	 * Get common capabilities for UI.
	 *
	 * @return array
	 */
	public function getCommonCapabilities() {
		return array(
			'read',
			'edit_posts',
			'edit_pages',
			'edit_others_posts',
			'edit_others_pages',
			'publish_posts',
			'publish_pages',
			'delete_posts',
			'delete_pages',
			'manage_categories',
			'manage_options',
			'moderate_comments',
			'upload_files',
			'edit_themes',
			'edit_plugins',
			'install_plugins',
			'update_plugins',
			'install_themes',
			'update_themes',
			'switch_themes',
		);
	}

	/**
	 * Get role descriptions for UI.
	 *
	 * @return array
	 */
	public function getRoleDescriptions() {
		return array(
			'administrator' => 'Full access to all WordPress features',
			'editor'        => 'Can publish and manage posts and pages',
			'author'        => 'Can publish and manage own posts',
			'contributor'   => 'Can write and manage own posts but cannot publish',
			'subscriber'    => 'Can only manage their profile',
			'logged_in'     => 'Any user who is currently logged in',
			'logged_out'    => 'Users who are not logged in (guests)',
			'guest'         => 'Same as logged_out - users who are not logged in',
		);
	}
}