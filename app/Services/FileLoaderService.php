<?php
/**
 * File Loader Service
 *
 * @package ViraCode
 */

namespace ViraCode\Services;

use ViraCode\Services\FileStorageService;
use ViraCode\Services\ConditionalLogicService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * FileLoaderService Class
 *
 * Handles loading and execution of file-based snippets.
 */
class FileLoaderService {

	/**
	 * File storage service instance.
	 *
	 * @var FileStorageService
	 */
	protected $storage;

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
		$this->storage = new FileStorageService();
		$this->conditional_logic_service = new ConditionalLogicService();
	}

	/**
	 * Check if file loader is available.
	 *
	 * @return bool
	 */
	public function isAvailable() {
		return $this->storage->isAvailable();
	}

	/**
	 * Get all snippets.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public function getSnippets( $args = array() ) {
		return $this->storage->getAll( $args );
	}

	/**
	 * Get a snippet by ID.
	 *
	 * @param int $id Snippet ID.
	 * @return array|null
	 */
	public function getSnippet( $id ) {
		return $this->storage->getById( $id );
	}

	/**
	 * Execute a snippet by ID.
	 *
	 * @param int $id Snippet ID.
	 * @return array|false Execution result.
	 */
	public function executeSnippet( $id ) {
		$snippet = $this->getSnippet( $id );
		if ( ! $snippet ) {
			return false;
		}

		return $this->execute( $snippet );
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

		$snippets = $this->storage->getByScope( $scope, $type );

		foreach ( $snippets as $snippet ) {
			$this->execute( $snippet );
		}
	}

	/**
	 * Run all active snippets for the current context.
	 *
	 * @return void
	 */
	public function runAllSnippets() {
		// Check if safe mode is enabled.
		if ( \ViraCode\vira_code_is_safe_mode() ) {
			return;
		}

		// Determine current scope.
		$scope = $this->getCurrentScope();

		// Execute snippets for current scope.
		$this->executeByScope( $scope );
	}

	/**
	 * Execute a snippet.
	 *
	 * @param array $snippet Snippet data.
	 * @return array Execution result.
	 */
	protected function execute( $snippet ) {
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

			// Cache the result.
			$this->executed[ $cache_key ] = $result;

			do_action( 'vira_code/file_snippet_execution_skipped', $snippet, 'conditional_logic', $result );

			return $result;
		}

		// Apply pre-execution filter.
		$snippet = apply_filters( 'vira_code/file_snippet_before_execute', $snippet );

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
					$this->storage->updateStatus( $snippet['id'], 'active' );
				}
			} else {
				// Log error execution.
				\ViraCode\vira_code_log_execution( $snippet['id'], 'error', $result['error'] );
				$this->storage->updateStatus( $snippet['id'], 'error' );
			}
		} catch ( \Exception $e ) {
			$result['success'] = false;
			$result['error']   = $e->getMessage();

			// Log exception.
			\ViraCode\vira_code_log_execution( $snippet['id'], 'error', $e->getMessage() );
			$this->storage->updateStatus( $snippet['id'], 'error' );
		} catch ( \Error $e ) {
			$result['success'] = false;
			$result['error']   = $e->getMessage();

			// Log fatal error.
			\ViraCode\vira_code_log_execution( $snippet['id'], 'error', $e->getMessage() );
			$this->storage->updateStatus( $snippet['id'], 'error' );
		}

		// Fire post-execution action.
		do_action( 'vira_code/file_snippet_executed', $snippet, $result );

		// Cache the result.
		$this->executed[ $cache_key ] = $result;

		return $result;
	}

	/**
	 * Execute PHP snippet from file.
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

		// Get the snippet file path.
		$snippet_file = $this->storage->getBaseDirectory() . $snippet['id'] . '/' . $snippet['slug'] . '.php';

		if ( ! file_exists( $snippet_file ) ) {
			$result['error'] = __( 'Snippet file not found.', 'vira-code' );
			return $result;
		}

		// Start output buffering.
		ob_start();

		try {
			// Set error handler.
			set_error_handler( function( $errno, $errstr, $errfile, $errline ) {
				throw new \ErrorException( $errstr, 0, $errno, $errfile, $errline );
			} );

			// Include the file in isolated scope.
			$this->includeSnippetFile( $snippet_file );

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

		return apply_filters( 'vira_code/file_php_executed', $result, $snippet );
	}

	/**
	 * Include snippet file in isolated scope.
	 *
	 * @param string $file_path File path.
	 * @return void
	 */
	protected function includeSnippetFile( $file_path ) {
		include $file_path;
	}

	/**
	 * Execute JavaScript snippet from file.
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

		// Get the snippet file path.
		$snippet_file = $this->storage->getBaseDirectory() . $snippet['id'] . '/' . $snippet['slug'] . '.js';

		if ( ! file_exists( $snippet_file ) ) {
			$result['error'] = __( 'Snippet file not found.', 'vira-code' );
			$result['success'] = false;
			return $result;
		}

		$code = file_get_contents( $snippet_file );
		if ( false === $code ) {
			$result['error'] = __( 'Could not read snippet file.', 'vira-code' );
			$result['success'] = false;
			return $result;
		}

		// Sanitize the JavaScript code.
		$code = apply_filters( 'vira_code/file_js_code', $code, $snippet );

		// Output inline script.
		echo "\n<script type=\"text/javascript\">\n";
		echo "/* Vira Code Snippet: " . esc_attr( $snippet['title'] ) . " */\n";
		echo $code; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo "\n</script>\n";

		$result['output'] = 'JavaScript snippet enqueued from file.';

		return apply_filters( 'vira_code/file_js_executed', $result, $snippet );
	}

	/**
	 * Execute CSS snippet from file.
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

		// Get the snippet file path.
		$snippet_file = $this->storage->getBaseDirectory() . $snippet['id'] . '/' . $snippet['slug'] . '.css';

		if ( ! file_exists( $snippet_file ) ) {
			$result['error'] = __( 'Snippet file not found.', 'vira-code' );
			$result['success'] = false;
			return $result;
		}

		$code = file_get_contents( $snippet_file );
		if ( false === $code ) {
			$result['error'] = __( 'Could not read snippet file.', 'vira-code' );
			$result['success'] = false;
			return $result;
		}

		// Sanitize the CSS code.
		$code = apply_filters( 'vira_code/file_css_code', $code, $snippet );

		// Output inline style.
		echo "\n<style type=\"text/css\">\n";
		echo "/* Vira Code Snippet: " . esc_attr( $snippet['title'] ) . " */\n";
		echo $code; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo "\n</style>\n";

		$result['output'] = 'CSS snippet enqueued from file.';

		return apply_filters( 'vira_code/file_css_executed', $result, $snippet );
	}

	/**
	 * Execute HTML snippet from file.
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

		// Get the snippet file path.
		$snippet_file = $this->storage->getBaseDirectory() . $snippet['id'] . '/' . $snippet['slug'] . '.html';

		if ( ! file_exists( $snippet_file ) ) {
			$result['error'] = __( 'Snippet file not found.', 'vira-code' );
			$result['success'] = false;
			return $result;
		}

		$code = file_get_contents( $snippet_file );
		if ( false === $code ) {
			$result['error'] = __( 'Could not read snippet file.', 'vira-code' );
			$result['success'] = false;
			return $result;
		}

		// Sanitize the HTML code based on context.
		$code = apply_filters( 'vira_code/file_html_code', $code, $snippet );

		// Output HTML.
		echo "\n<!-- Vira Code Snippet: " . esc_attr( $snippet['title'] ) . " -->\n";
		echo $code; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo "\n<!-- End Vira Code Snippet -->\n";

		$result['output'] = 'HTML snippet outputted from file.';

		return apply_filters( 'vira_code/file_html_executed', $result, $snippet );
	}

	/**
	 * Get current scope based on WordPress context.
	 *
	 * @return string
	 */
	protected function getCurrentScope() {
		if ( is_admin() ) {
			return 'admin';
		}

		if ( wp_doing_ajax() ) {
			return 'ajax';
		}

		if ( wp_doing_cron() ) {
			return 'cron';
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return 'rest';
		}

		return 'frontend';
	}

	/**
	 * Check if snippet should execute based on conditional logic.
	 *
	 * @param array $snippet Snippet data.
	 * @return bool
	 */
	protected function shouldExecuteSnippet( $snippet ) {
		// Allow filtering to bypass conditional logic check entirely.
		$bypass_conditional_logic = apply_filters( 'vira_code/file_bypass_conditional_logic', false, $snippet );
		if ( $bypass_conditional_logic ) {
			return true;
		}

		// For backward compatibility, if no conditional logic service is available, execute the snippet.
		if ( ! $this->conditional_logic_service ) {
			return true;
		}

		// Check if snippet has conditional logic rules.
		$has_rules = $this->conditional_logic_service->hasRules( $snippet['id'] );
		if ( ! $has_rules ) {
			return true;
		}

		try {
			// Evaluate conditional logic.
			$evaluation_result = $this->conditional_logic_service->evaluate( $snippet['id'] );
			return (bool) $evaluation_result;
		} catch ( \Exception $e ) {
			// Log error and default to not executing for safety.
			error_log( sprintf( 'Vira Code File Loader: Conditional logic evaluation error for snippet %d: %s', $snippet['id'], $e->getMessage() ) );
			return false;
		}
	}

	/**
	 * Get storage service instance.
	 *
	 * @return FileStorageService
	 */
	public function getStorage() {
		return $this->storage;
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
		$this->storage->clearCache();
	}
}