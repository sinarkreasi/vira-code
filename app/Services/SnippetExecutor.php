<?php
/**
 * Snippet Executor Service
 *
 * @package ViraCode
 */

namespace ViraCode\Services;

use ViraCode\Models\SnippetModel;
use ViraCode\Services\ConditionalLogicService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SnippetExecutor Class
 *
 * Validates and executes code snippets safely.
 */
class SnippetExecutor {

	/**
	 * Snippet model instance.
	 *
	 * @var SnippetModel
	 */
	protected $model;

	/**
	 * Conditional logic service instance.
	 *
	 * @var ConditionalLogicService
	 */
	protected $conditional_logic_service;

	/**
	 * Executed snippets cache.
	 *
	 * @var array
	 */
	protected $executed = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->model = new SnippetModel();
		$this->conditional_logic_service = new ConditionalLogicService();
	}

	/**
	 * Execute snippets by scope and type.
	 *
	 * @param string $scope Snippet scope.
	 * @param string $type  Snippet type.
	 * @return void
	 */
	public function executeByScope( $scope, $type = '' ) {
		// Check if safe mode is enabled.
		if ( \ViraCode\vira_code_is_safe_mode() ) {
			return;
		}

		$snippets = $this->model->getByScope( $scope, $type );

		foreach ( $snippets as $snippet ) {
			$this->execute( $snippet );
		}
	}

	/**
	 * Execute a specific snippet by ID.
	 *
	 * @param int $snippet_id Snippet ID.
	 * @return array|false Execution result.
	 */
	public function executeById( $snippet_id ) {
		$snippet = $this->model->getById( $snippet_id );

		if ( ! $snippet ) {
			return false;
		}

		return $this->execute( $snippet );
	}

	/**
	 * Execute a snippet.
	 *
	 * @param array $snippet Snippet data.
	 * @return array Execution result.
	 */
	public function execute( $snippet ) {
		// Check if already executed in this request.
		$cache_key = $snippet['id'] . '_' . $snippet['type'];
		if ( isset( $this->executed[ $cache_key ] ) ) {
			return $this->executed[ $cache_key ];
		}

		// Check conditional logic before execution.
		$should_execute = $this->shouldExecuteSnippet( $snippet );
		if ( ! $should_execute ) {
			$result = array(
				'success' => true,
				'output'  => '',
				'error'   => '',
				'skipped' => true,
				'reason'  => 'Conditional logic conditions not met',
			);

			// Allow filtering of skip result.
			$result = apply_filters( 'vira_code/snippet_execution_skip_result', $result, $snippet );

			// Cache the result.
			$this->executed[ $cache_key ] = $result;

			// Fire conditional logic skip action with detailed information.
			do_action( 'vira_code/snippet_execution_skipped', $snippet, 'conditional_logic', $result );

			// Fire specific action for conditional logic skips.
			do_action( 'vira_code/snippet_skipped_by_conditional_logic', $snippet, $result );

			return $result;
		}

		// Apply pre-execution filter.
		$snippet = apply_filters( 'vira_code/snippet_before_execute', $snippet );

		$result = array(
			'success' => false,
			'output'  => '',
			'error'   => '',
		);

		try {
			// Execute based on snippet type.
			switch ( $snippet['type'] ) {
				case 'php':
					$result = $this->executePhp( $snippet );
					break;

				case 'js':
					$result = $this->executeJs( $snippet );
					break;

				case 'css':
					$result = $this->executeCss( $snippet );
					break;

				case 'html':
					$result = $this->executeHtml( $snippet );
					break;

				default:
					$result['error'] = sprintf(
						/* translators: %s: snippet type */
						__( 'Unknown snippet type: %s', 'vira-code' ),
						$snippet['type']
					);
					break;
			}

			// Log successful execution.
			if ( $result['success'] ) {
				\ViraCode\vira_code_log_execution( $snippet['id'], 'success' );

				// Clear any previous errors.
				if ( 'error' === $snippet['status'] ) {
					$this->model->clearError( $snippet['id'] );
					$this->model->updateStatus( $snippet['id'], 'active' );
				}
			} else {
				// Log error execution.
				\ViraCode\vira_code_log_execution( $snippet['id'], 'error', $result['error'] );
				$this->model->logError( $snippet['id'], $result['error'] );
			}
		} catch ( \Exception $e ) {
			$result['success'] = false;
			$result['error']   = $e->getMessage();

			// Log exception.
			\ViraCode\vira_code_log_execution( $snippet['id'], 'error', $e->getMessage() );
			$this->model->logError( $snippet['id'], $e->getMessage() );
		} catch ( \Error $e ) {
			$result['success'] = false;
			$result['error']   = $e->getMessage();

			// Log fatal error.
			\ViraCode\vira_code_log_execution( $snippet['id'], 'error', $e->getMessage() );
			$this->model->logError( $snippet['id'], $e->getMessage() );
		}

		// Fire post-execution action.
		do_action( 'vira_code/snippet_executed', $snippet, $result );

		// Cache the result.
		$this->executed[ $cache_key ] = $result;

		return $result;
	}

	/**
	 * Execute PHP snippet.
	 *
	 * @param array $snippet Snippet data.
	 * @return array
	 */
	protected function executePhp( $snippet ) {
		$result = array(
			'success' => false,
			'output'  => '',
			'error'   => '',
		);

		// Validate PHP code.
		$validation = $this->validatePhp( $snippet['code'] );
		if ( ! $validation['valid'] ) {
			$result['error'] = $validation['error'];
			return $result;
		}

		// Start output buffering.
		ob_start();

		try {
			// Set error handler.
			set_error_handler( function( $errno, $errstr, $errfile, $errline ) {
				throw new \ErrorException( $errstr, 0, $errno, $errfile, $errline );
			} );

			// Prepare code for execution.
			$code = $snippet['code'];

			// Remove PHP opening tags if present.
			$code = preg_replace( '/^<\?php\s+/i', '', $code );
			$code = preg_replace( '/^<\?\s+/i', '', $code );
			$code = preg_replace( '/\?>\s*$/', '', $code );

			// Execute the code in isolated scope.
			$this->executePhpCode( $code );

			// Restore error handler.
			restore_error_handler();

			$result['success'] = true;
			$result['output']  = ob_get_clean();

		} catch ( \ParseError $e ) {
			restore_error_handler();
			ob_end_clean();
			$result['error'] = sprintf(
				/* translators: 1: error message, 2: line number */
				__( 'Parse Error: %1$s on line %2$d', 'vira-code' ),
				$e->getMessage(),
				$e->getLine()
			);
		} catch ( \ErrorException $e ) {
			restore_error_handler();
			ob_end_clean();
			$result['error'] = sprintf(
				/* translators: 1: error message, 2: line number */
				__( 'Error: %1$s on line %2$d', 'vira-code' ),
				$e->getMessage(),
				$e->getLine()
			);
		} catch ( \Throwable $e ) {
			restore_error_handler();
			ob_end_clean();
			$result['error'] = sprintf(
				/* translators: 1: error type, 2: error message */
				__( '%1$s: %2$s', 'vira-code' ),
				get_class( $e ),
				$e->getMessage()
			);
		}

		return apply_filters( 'vira_code/php_executed', $result, $snippet );
	}

	/**
	 * Execute PHP code in isolated scope.
	 *
	 * @param string $code PHP code to execute.
	 * @return void
	 */
	protected function executePhpCode( $code ) {
		eval( $code ); // phpcs:ignore Squiz.PHP.Eval.Discouraged
	}

	/**
	 * Validate PHP code.
	 *
	 * @param string $code PHP code.
	 * @return array
	 */
	protected function validatePhp( $code ) {
		$result = array(
			'valid' => true,
			'error' => '',
		);

		// Check for dangerous functions (optional - can be disabled via filter).
		$dangerous_functions = apply_filters(
			'vira_code/php_dangerous_functions',
			array(
				// File system functions that could be dangerous.
				// Uncomment to restrict:
				// 'unlink',
				// 'rmdir',
				// 'file_put_contents',
			)
		);

		foreach ( $dangerous_functions as $function ) {
			if ( preg_match( '/\b' . preg_quote( $function, '/' ) . '\s*\(/i', $code ) ) {
				$result['valid'] = false;
				$result['error'] = sprintf(
					/* translators: %s: function name */
					__( 'Dangerous function not allowed: %s', 'vira-code' ),
					$function
				);
				return $result;
			}
		}

		// Basic syntax check using php -l if a CLI is available.
		// Prefer PHP_BINARY if defined, otherwise try 'php'. If exec isn't available we skip this check.
		if ( function_exists( 'exec' ) ) {
			$php_cmd = defined( 'PHP_BINARY' ) ? PHP_BINARY : 'php';
			// Ensure we have a usable PHP CLI by attempting to run php -v quietly.
			$can_run_php = false;
			@exec( escapeshellarg( $php_cmd ) . ' -v 2>&1', $dummy, $php_ret );
			if ( 0 === $php_ret ) {
				$can_run_php = true;
			}

			if ( $can_run_php ) {
				$temp_file = tempnam( sys_get_temp_dir(), 'vira_code_' );
				if ( $temp_file ) {
					// Prepare code with PHP tags.
					$code_to_check = "<?php\n" . $code;
					file_put_contents( $temp_file, $code_to_check );

					$output = array();
					$return_var = 0;
					@exec( escapeshellarg( $php_cmd ) . " -l " . escapeshellarg( $temp_file ) . " 2>&1", $output, $return_var );

					@unlink( $temp_file );

					if ( 0 !== $return_var ) {
						$result['valid'] = false;
						$result['error'] = implode( "\n", $output );
					}
				}
			} else {
				// Can't run php CLI here (common on some Windows setups). Skip strict syntax check.
				// Rely on runtime validation during test/execute.
			}
		}

		return apply_filters( 'vira_code/php_validated', $result, $code );
	}

	/**
	 * Execute JavaScript snippet.
	 *
	 * @param array $snippet Snippet data.
	 * @return array
	 */
	protected function executeJs( $snippet ) {
		$result = array(
			'success' => true,
			'output'  => '',
			'error'   => '',
		);

		// Sanitize the JavaScript code.
		$code = apply_filters( 'vira_code/js_code', $snippet['code'], $snippet );

		// Output inline script.
		echo "\n<script type=\"text/javascript\">\n";
		echo "/* Vira Code Snippet: " . esc_attr( $snippet['title'] ) . " */\n";
		echo $code; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo "\n</script>\n";

		$result['output'] = 'JavaScript snippet enqueued.';

		return apply_filters( 'vira_code/js_executed', $result, $snippet );
	}

	/**
	 * Execute CSS snippet.
	 *
	 * @param array $snippet Snippet data.
	 * @return array
	 */
	protected function executeCss( $snippet ) {
		$result = array(
			'success' => true,
			'output'  => '',
			'error'   => '',
		);

		// Sanitize the CSS code.
		$code = apply_filters( 'vira_code/css_code', $snippet['code'], $snippet );

		// Output inline style.
		echo "\n<style type=\"text/css\">\n";
		echo "/* Vira Code Snippet: " . esc_attr( $snippet['title'] ) . " */\n";
		echo $code; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo "\n</style>\n";

		$result['output'] = 'CSS snippet enqueued.';

		return apply_filters( 'vira_code/css_executed', $result, $snippet );
	}

	/**
	 * Execute HTML snippet.
	 *
	 * @param array $snippet Snippet data.
	 * @return array
	 */
	protected function executeHtml( $snippet ) {
		$result = array(
			'success' => true,
			'output'  => '',
			'error'   => '',
		);

		// Sanitize the HTML code based on context.
		$code = apply_filters( 'vira_code/html_code', $snippet['code'], $snippet );

		// Output HTML.
		echo "\n<!-- Vira Code Snippet: " . esc_attr( $snippet['title'] ) . " -->\n";
		echo $code; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo "\n<!-- End Vira Code Snippet -->\n";

		$result['output'] = 'HTML snippet outputted.';

		return apply_filters( 'vira_code/html_executed', $result, $snippet );
	}

	/**
	 * Test snippet execution without actually running it.
	 *
	 * @param array $snippet Snippet data.
	 * @return array
	 */
	public function test( $snippet ) {
		$result = array(
			'valid'   => true,
			'message' => __( 'Snippet validation passed.', 'vira-code' ),
			'errors'  => array(),
		);

		// Only validate PHP snippets.
		if ( 'php' === $snippet['type'] ) {
			$validation = $this->validatePhp( $snippet['code'] );
			if ( ! $validation['valid'] ) {
				$result['valid']    = false;
				$result['message']  = __( 'Snippet validation failed.', 'vira-code' );
				$result['errors'][] = $validation['error'];
			}
		}

		return apply_filters( 'vira_code/snippet_tested', $result, $snippet );
	}

	/**
	 * Get executed snippets cache.
	 *
	 * @return array
	 */
	public function getExecuted() {
		return $this->executed;
	}

	/**
	 * Clear executed snippets cache.
	 *
	 * @return void
	 */
	public function clearCache() {
		$this->executed = array();
	}

	/**
	 * Check if snippet should execute based on conditional logic.
	 *
	 * @param array $snippet Snippet data.
	 * @return bool True if snippet should execute, false otherwise.
	 */
	protected function shouldExecuteSnippet( $snippet ) {
		// Allow filtering to bypass conditional logic check entirely.
		$bypass_conditional_logic = apply_filters( 'vira_code/bypass_conditional_logic', false, $snippet );
		if ( $bypass_conditional_logic ) {
			// Fire action for bypassed evaluation.
			do_action( 'vira_code/conditional_logic_bypassed', $snippet );
			return true;
		}

		// For backward compatibility, if no conditional logic service is available, execute the snippet.
		if ( ! $this->conditional_logic_service ) {
			// Fire action for missing service.
			do_action( 'vira_code/conditional_logic_service_unavailable', $snippet );
			return true;
		}

		// Check if snippet has conditional logic rules.
		$has_rules = $this->conditional_logic_service->hasRules( $snippet['id'] );
		if ( ! $has_rules ) {
			// Fire action for snippets without rules.
			do_action( 'vira_code/snippet_no_conditional_rules', $snippet );
			return true;
		}

		try {
			// Fire pre-evaluation action with timing.
			$start_time = microtime( true );
			do_action( 'vira_code/before_conditional_evaluation', $snippet );

			// Allow filtering of evaluation behavior.
			$force_evaluation_result = apply_filters( 'vira_code/force_conditional_evaluation_result', null, $snippet );
			if ( null !== $force_evaluation_result ) {
				// Fire action for forced result.
				do_action( 'vira_code/conditional_evaluation_forced', $snippet, $force_evaluation_result );
				return (bool) $force_evaluation_result;
			}

			// Evaluate conditional logic.
			$evaluation_result = $this->conditional_logic_service->evaluate( $snippet['id'] );

			// Calculate evaluation time.
			$evaluation_time = microtime( true ) - $start_time;

			// Fire post-evaluation action with timing.
			do_action( 'vira_code/after_conditional_evaluation', $snippet, $evaluation_result, $evaluation_time );

			// Log evaluation result with performance data.
			$this->logConditionalEvaluation( $snippet['id'], $evaluation_result, $evaluation_time );

			// Fire specific actions based on result.
			if ( $evaluation_result ) {
				do_action( 'vira_code/conditional_evaluation_passed', $snippet, $evaluation_time );
			} else {
				do_action( 'vira_code/conditional_evaluation_failed', $snippet, $evaluation_time );
			}

			// Allow filtering of final result.
			$final_result = apply_filters( 'vira_code/conditional_evaluation_result', $evaluation_result, $snippet, $evaluation_time );

			return (bool) $final_result;

		} catch ( \Exception $e ) {
			// Log error and default to not executing for safety.
			$this->logConditionalEvaluationError( $snippet['id'], $e->getMessage() );

			// Fire error action with exception details.
			do_action( 'vira_code/conditional_evaluation_error', $snippet, $e );

			// Apply filter to determine behavior on evaluation error.
			$execute_on_error = apply_filters( 'vira_code/execute_on_conditional_error', false, $snippet, $e );

			// Fire action based on error handling decision.
			if ( $execute_on_error ) {
				do_action( 'vira_code/conditional_error_execution_allowed', $snippet, $e );
			} else {
				do_action( 'vira_code/conditional_error_execution_blocked', $snippet, $e );
			}

			return (bool) $execute_on_error;
		}
	}

	/**
	 * Log conditional logic evaluation result.
	 *
	 * @param int   $snippet_id      Snippet ID.
	 * @param bool  $result          Evaluation result.
	 * @param float $evaluation_time Optional evaluation time in seconds.
	 * @return void
	 */
	protected function logConditionalEvaluation( $snippet_id, $result, $evaluation_time = null ) {
		// Use existing logging function if available, otherwise use WordPress error_log.
		if ( function_exists( '\ViraCode\vira_code_log_conditional_logic' ) ) {
			$message = null;
			if ( null !== $evaluation_time ) {
				$message = sprintf( 'Evaluation time: %.4f seconds', $evaluation_time );
			}
			\ViraCode\vira_code_log_conditional_logic( $snippet_id, 'evaluation', $result, $message );
		} else {
			$time_info = $evaluation_time ? sprintf( ' (%.4f seconds)', $evaluation_time ) : '';
			error_log( sprintf( 'Vira Code: Conditional logic evaluation for snippet %d: %s%s', $snippet_id, $result ? 'passed' : 'failed', $time_info ) );
		}

		// Fire action for custom logging integrations.
		do_action( 'vira_code/conditional_evaluation_logged', $snippet_id, $result, $evaluation_time );
	}

	/**
	 * Log conditional logic evaluation error.
	 *
	 * @param int    $snippet_id Snippet ID.
	 * @param string $error      Error message.
	 * @return void
	 */
	protected function logConditionalEvaluationError( $snippet_id, $error ) {
		// Use existing logging function if available, otherwise use WordPress error_log.
		if ( function_exists( '\ViraCode\vira_code_log_conditional_logic' ) ) {
			\ViraCode\vira_code_log_conditional_logic( $snippet_id, 'error', false, $error );
		} else {
			error_log( sprintf( 'Vira Code: Conditional logic evaluation error for snippet %d: %s', $snippet_id, $error ) );
		}

		// Fire action for custom error handling integrations.
		do_action( 'vira_code/conditional_evaluation_error_logged', $snippet_id, $error );
	}

	/**
	 * Get conditional logic statistics for debugging.
	 *
	 * @return array
	 */
	public function getConditionalLogicStats() {
		if ( ! $this->conditional_logic_service ) {
			return array(
				'service_available' => false,
				'message'           => 'Conditional logic service not available',
			);
		}

		$stats = $this->conditional_logic_service->getCacheStats();
		$stats['service_available'] = true;

		// Add execution statistics.
		$executed_with_conditions = 0;
		$skipped_by_conditions = 0;

		foreach ( $this->executed as $result ) {
			if ( isset( $result['skipped'] ) && $result['skipped'] && 'Conditional logic conditions not met' === $result['reason'] ) {
				$skipped_by_conditions++;
			} elseif ( ! isset( $result['skipped'] ) ) {
				$executed_with_conditions++;
			}
		}

		$stats['execution_stats'] = array(
			'total_executed'           => count( $this->executed ),
			'executed_with_conditions' => $executed_with_conditions,
			'skipped_by_conditions'    => $skipped_by_conditions,
		);

		return apply_filters( 'vira_code/conditional_logic_stats', $stats );
	}

	/**
	 * Check if conditional logic is enabled for the plugin.
	 *
	 * @return bool
	 */
	public function isConditionalLogicEnabled() {
		$enabled = null !== $this->conditional_logic_service;
		return apply_filters( 'vira_code/conditional_logic_enabled', $enabled );
	}
}
