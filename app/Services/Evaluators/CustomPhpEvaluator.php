<?php
/**
 * Custom PHP Condition Evaluator
 *
 * @package ViraCode
 */

namespace ViraCode\Services\Evaluators;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CustomPhpEvaluator Class
 *
 * Evaluates custom PHP conditions with security validation.
 */
class CustomPhpEvaluator extends AbstractConditionEvaluator {

	/**
	 * Condition type identifier.
	 *
	 * @var string
	 */
	protected $type = 'custom_php';

	/**
	 * Supported operators for custom PHP conditions.
	 *
	 * @var array
	 */
	protected $supported_operators = array( 'execute' );

	/**
	 * Human-readable description.
	 *
	 * @var string
	 */
	protected $description = 'Execute custom PHP code for advanced conditional logic';

	/**
	 * Dangerous PHP functions that are not allowed.
	 *
	 * @var array
	 */
	protected $dangerous_functions = array(
		'exec',
		'system',
		'shell_exec',
		'passthru',
		'eval',
		'file_get_contents',
		'file_put_contents',
		'fopen',
		'fwrite',
		'fread',
		'unlink',
		'rmdir',
		'mkdir',
		'chmod',
		'chown',
		'move_uploaded_file',
		'curl_exec',
		'curl_multi_exec',
		'proc_open',
		'popen',
		'mail',
		'wp_mail',
		'include',
		'include_once',
		'require',
		'require_once',
		'file',
		'readfile',
		'highlight_file',
		'show_source',
		'php_uname',
		'phpinfo',
		'assert',
		'create_function',
		'call_user_func',
		'call_user_func_array',
	);

	/**
	 * Evaluate custom PHP condition.
	 *
	 * @param array $condition Condition data containing type, operator, and value.
	 * @param array $context   Current context data for evaluation.
	 * @return bool
	 */
	protected function doEvaluate( array $condition, array $context = array() ) {
		$php_code = $condition['value'];

		// Execute the PHP code safely.
		return $this->executePhpCode( $php_code, $context );
	}	/**

	 * Validate custom PHP code value.
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
			$result['errors'][] = 'Custom PHP code cannot be empty';
			return $result;
		}

		if ( ! is_string( $value ) ) {
			$result['valid'] = false;
			$result['errors'][] = 'Custom PHP code must be a string';
			return $result;
		}

		// Security validation.
		$security_validation = $this->validatePhpSecurity( $value );
		if ( ! $security_validation['valid'] ) {
			$result['valid'] = false;
			$result['errors'] = array_merge( $result['errors'], $security_validation['errors'] );
		}

		// Syntax validation.
		$syntax_validation = $this->validatePhpSyntax( $value );
		if ( ! $syntax_validation['valid'] ) {
			$result['valid'] = false;
			$result['errors'] = array_merge( $result['errors'], $syntax_validation['errors'] );
		}

		return $result;
	}

	/**
	 * Validate PHP code for security risks.
	 *
	 * @param string $code PHP code to validate.
	 * @return array
	 */
	protected function validatePhpSecurity( $code ) {
		$result = array(
			'valid'  => true,
			'errors' => array(),
		);

		// Check for dangerous functions.
		foreach ( $this->dangerous_functions as $function ) {
			if ( false !== stripos( $code, $function ) ) {
				$result['valid'] = false;
				$result['errors'][] = "Dangerous function not allowed: {$function}";
			}
		}

		// Check for variable variables ($$var).
		if ( preg_match( '/\$\$\w+/', $code ) ) {
			$result['valid'] = false;
			$result['errors'][] = 'Variable variables ($$var) are not allowed for security reasons';
		}

		// Check for backticks (shell execution).
		if ( false !== strpos( $code, '`' ) ) {
			$result['valid'] = false;
			$result['errors'][] = 'Backticks (shell execution) are not allowed';
		}

		// Check for superglobals modification.
		$dangerous_globals = array( '$_GET', '$_POST', '$_COOKIE', '$_SESSION', '$_SERVER', '$_ENV', '$GLOBALS' );
		foreach ( $dangerous_globals as $global ) {
			if ( preg_match( '/' . preg_quote( $global, '/' ) . '\s*\[.*?\]\s*=/', $code ) ) {
				$result['valid'] = false;
				$result['errors'][] = "Modifying superglobal {$global} is not allowed";
			}
		}

		return $result;
	}

	/**
	 * Validate PHP code syntax.
	 *
	 * @param string $code PHP code to validate.
	 * @return array
	 */
	protected function validatePhpSyntax( $code ) {
		$result = array(
			'valid'  => true,
			'errors' => array(),
		);

		// Prepare code for syntax check.
		$test_code = $this->prepareCodeForSyntaxCheck( $code );

		// Check syntax using php -l equivalent.
		$syntax_error = $this->checkPhpSyntax( $test_code );
		if ( $syntax_error ) {
			$result['valid'] = false;
			$result['errors'][] = "PHP syntax error: {$syntax_error}";
		}

		return $result;
	}	/**

	 * Execute PHP code safely.
	 *
	 * @param string $code    PHP code to execute.
	 * @param array  $context Current context data.
	 * @return bool
	 */
	protected function executePhpCode( $code, $context ) {
		// Set up error handling.
		$old_error_handler = set_error_handler( array( $this, 'handlePhpError' ) );
		$old_error_reporting = error_reporting( E_ALL );

		try {
			// Prepare code for execution.
			$executable_code = $this->prepareCodeForExecution( $code, $context );

			// Execute the code and capture result.
			ob_start();
			$result = eval( $executable_code );
			$output = ob_get_clean();

			// Convert result to boolean.
			$boolean_result = $this->convertToBoolean( $result );

			// Log execution details.
			do_action( 'vira_code/custom_php_executed', $code, $context, $boolean_result, $output );

			return $boolean_result;

		} catch ( \ParseError $e ) {
			ob_end_clean();
			do_action( 'vira_code/custom_php_parse_error', $code, $e->getMessage() );
			return false;

		} catch ( \Error $e ) {
			ob_end_clean();
			do_action( 'vira_code/custom_php_fatal_error', $code, $e->getMessage() );
			return false;

		} catch ( \Exception $e ) {
			ob_end_clean();
			do_action( 'vira_code/custom_php_exception', $code, $e->getMessage() );
			return false;

		} finally {
			// Restore error handling.
			error_reporting( $old_error_reporting );
			if ( $old_error_handler ) {
				set_error_handler( $old_error_handler );
			} else {
				restore_error_handler();
			}
		}
	}

	/**
	 * Prepare code for syntax checking.
	 *
	 * @param string $code Raw PHP code.
	 * @return string
	 */
	protected function prepareCodeForSyntaxCheck( $code ) {
		// Remove PHP opening tags if present.
		$code = preg_replace( '/^\s*<\?php\s*/', '', $code );
		$code = preg_replace( '/^\s*<\?\s*/', '', $code );

		// Wrap in return statement if not present.
		if ( ! preg_match( '/\breturn\b/', $code ) && ! preg_match( '/[;}]\s*$/', $code ) ) {
			$code = "return ({$code});";
		}

		return "<?php {$code}";
	}

	/**
	 * Check PHP syntax using token parsing.
	 *
	 * @param string $code PHP code to check.
	 * @return string|null Error message or null if valid.
	 */
	protected function checkPhpSyntax( $code ) {
		// Use token_get_all to check basic syntax.
		$tokens = @token_get_all( $code );

		if ( false === $tokens ) {
			return 'Invalid PHP syntax';
		}

		// Check for balanced brackets.
		$bracket_count = 0;
		$paren_count = 0;
		$brace_count = 0;

		foreach ( $tokens as $token ) {
			if ( is_string( $token ) ) {
				switch ( $token ) {
					case '[':
						$bracket_count++;
						break;
					case ']':
						$bracket_count--;
						break;
					case '(':
						$paren_count++;
						break;
					case ')':
						$paren_count--;
						break;
					case '{':
						$brace_count++;
						break;
					case '}':
						$brace_count--;
						break;
				}
			}
		}

		if ( 0 !== $bracket_count ) {
			return 'Unbalanced square brackets';
		}

		if ( 0 !== $paren_count ) {
			return 'Unbalanced parentheses';
		}

		if ( 0 !== $brace_count ) {
			return 'Unbalanced curly braces';
		}

		return null;
	}	/**
	
 * Prepare code for execution.
	 *
	 * @param string $code    Raw PHP code.
	 * @param array  $context Current context data.
	 * @return string
	 */
	protected function prepareCodeForExecution( $code, $context ) {
		// Remove PHP opening tags if present.
		$code = preg_replace( '/^\s*<\?php\s*/', '', $code );
		$code = preg_replace( '/^\s*<\?\s*/', '', $code );

		// Make context variables available.
		$context_vars = '';
		foreach ( $context as $key => $value ) {
			if ( is_scalar( $value ) || is_null( $value ) ) {
				$context_vars .= '$' . $key . ' = ' . var_export( $value, true ) . '; ';
			}
		}

		// Wrap in return statement if not present.
		if ( ! preg_match( '/\breturn\b/', $code ) && ! preg_match( '/[;}]\s*$/', $code ) ) {
			$code = "return ({$code});";
		}

		return $context_vars . $code;
	}

	/**
	 * Convert execution result to boolean.
	 *
	 * @param mixed $result Execution result.
	 * @return bool
	 */
	protected function convertToBoolean( $result ) {
		// Handle null result.
		if ( null === $result ) {
			return false;
		}

		// Handle boolean result.
		if ( is_bool( $result ) ) {
			return $result;
		}

		// Handle numeric result.
		if ( is_numeric( $result ) ) {
			return 0 !== (float) $result;
		}

		// Handle string result.
		if ( is_string( $result ) ) {
			$lower_result = strtolower( trim( $result ) );
			return ! in_array( $lower_result, array( '', '0', 'false', 'no', 'off' ), true );
		}

		// Handle array result.
		if ( is_array( $result ) ) {
			return ! empty( $result );
		}

		// Handle object result.
		if ( is_object( $result ) ) {
			return true;
		}

		// Default to false.
		return false;
	}

	/**
	 * Handle PHP errors during execution.
	 *
	 * @param int    $errno   Error number.
	 * @param string $errstr  Error message.
	 * @param string $errfile Error file.
	 * @param int    $errline Error line.
	 * @return bool
	 */
	public function handlePhpError( $errno, $errstr, $errfile, $errline ) {
		// Log the error.
		do_action( 'vira_code/custom_php_error', $errno, $errstr, $errfile, $errline );

		// Don't execute PHP internal error handler.
		return true;
	}

	/**
	 * Sanitize PHP code value.
	 *
	 * @param mixed $value Raw PHP code.
	 * @return string
	 */
	protected function sanitizeValue( $value ) {
		if ( ! is_string( $value ) ) {
			return '';
		}

		// Basic sanitization.
		$value = trim( $value );

		// Remove null bytes.
		$value = str_replace( "\0", '', $value );

		return $value;
	}

	/**
	 * Get security warnings for UI.
	 *
	 * @return array
	 */
	public function getSecurityWarnings() {
		return array(
			'Custom PHP conditions can execute arbitrary code on your server.',
			'Only use this feature if you understand the security implications.',
			'Never use untrusted or user-provided code in custom PHP conditions.',
			'The following functions are blocked for security: ' . implode( ', ', array_slice( $this->dangerous_functions, 0, 10 ) ) . '...',
		);
	}

	/**
	 * Get code examples for UI help.
	 *
	 * @return array
	 */
	public function getCodeExamples() {
		return array(
			'Simple boolean' => 'true',
			'Check option'   => "get_option('my_option') === 'enabled'",
			'User check'     => 'is_user_logged_in() && current_user_can("manage_options")',
			'Date check'     => 'date("N") >= 6', // Weekend check
			'Custom logic'   => 'function_exists("my_custom_function") && my_custom_function()',
		);
	}
}