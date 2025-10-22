<?php
/**
 * Safe Mode Hook
 *
 * @package ViraCode
 */

namespace ViraCode\Hooks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SafeModeHook Class
 *
 * Monitors fatal errors and automatically disables all snippets if needed.
 */
class SafeModeHook {

	/**
	 * Error log file path.
	 *
	 * @var string
	 */
	protected $error_log;

	/**
	 * Maximum error count before enabling safe mode.
	 *
	 * @var int
	 */
	protected $max_errors = 3;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->error_log = VIRA_CODE_PATH . 'vira-code-errors.log';
	}

	/**
	 * Initialize safe mode monitoring.
	 *
	 * @return void
	 */
	public function init() {
		// Register shutdown function to catch fatal errors.
		register_shutdown_function( array( $this, 'handleShutdown' ) );

		// Register error handler.
		add_action( 'vira_code/snippet_executed', array( $this, 'monitorExecution' ), 10, 2 );

		// Check for safe mode URL parameter.
		add_action( 'init', array( $this, 'checkSafeModeParam' ), 1 );

		// Apply safe mode filter.
		add_filter( 'vira_code/safe_mode_enabled', array( $this, 'isSafeModeEnabled' ) );
	}

	/**
	 * Handle shutdown to catch fatal errors.
	 *
	 * @return void
	 */
	public function handleShutdown() {
		$error = error_get_last();

		if ( null === $error ) {
			return;
		}

		// Check if it's a fatal error.
		$fatal_errors = array(
			E_ERROR,
			E_PARSE,
			E_CORE_ERROR,
			E_COMPILE_ERROR,
			E_USER_ERROR,
		);

		if ( ! in_array( $error['type'], $fatal_errors, true ) ) {
			return;
		}

		// Check if error is from our plugin.
		if ( strpos( $error['file'], VIRA_CODE_PATH ) === false ) {
			return;
		}

		// Log the fatal error.
		$this->logFatalError( $error );

		// Enable safe mode if too many errors.
		$this->checkErrorThreshold();

		do_action( 'vira_code/fatal_error_detected', $error );
	}

	/**
	 * Monitor snippet execution for errors.
	 *
	 * @param array $snippet Snippet data.
	 * @param array $result  Execution result.
	 * @return void
	 */
	public function monitorExecution( $snippet, $result ) {
		if ( ! $result['success'] && ! empty( $result['error'] ) ) {
			$this->logExecutionError( $snippet, $result['error'] );
			$this->checkErrorThreshold();
		}
	}

	/**
	 * Check for safe mode URL parameter.
	 *
	 * @return void
	 */
	public function checkSafeModeParam() {
		// Check if user has permission.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Check for safe mode parameter.
		if ( isset( $_GET['vira_safe_mode'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$safe_mode = sanitize_text_field( wp_unslash( $_GET['vira_safe_mode'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			if ( '1' === $safe_mode ) {
				\ViraCode\vira_code_enable_safe_mode();
				add_action( 'admin_notices', array( $this, 'showSafeModeEnabledNotice' ) );
			} elseif ( '0' === $safe_mode ) {
				\ViraCode\vira_code_disable_safe_mode();
				add_action( 'admin_notices', array( $this, 'showSafeModeDisabledNotice' ) );
			}
		}
	}

	/**
	 * Show safe mode enabled notice.
	 *
	 * @return void
	 */
	public function showSafeModeEnabledNotice() {
		?>
		<div class="notice notice-success is-dismissible">
			<p>
				<strong><?php esc_html_e( 'Vira Code Safe Mode Enabled', 'vira-code' ); ?></strong>
				<?php esc_html_e( 'All snippets have been disabled.', 'vira-code' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Show safe mode disabled notice.
	 *
	 * @return void
	 */
	public function showSafeModeDisabledNotice() {
		?>
		<div class="notice notice-success is-dismissible">
			<p>
				<strong><?php esc_html_e( 'Vira Code Safe Mode Disabled', 'vira-code' ); ?></strong>
				<?php esc_html_e( 'Snippets will now execute normally.', 'vira-code' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Log fatal error.
	 *
	 * @param array $error Error details.
	 * @return void
	 */
	protected function logFatalError( $error ) {
		$log_entry = sprintf(
			"[%s] FATAL ERROR: %s in %s on line %d\n",
			current_time( 'mysql' ),
			$error['message'],
			$error['file'],
			$error['line']
		);

		$this->writeLog( $log_entry );

		// Also store in transient for admin display.
		$errors = get_transient( 'vira_code_fatal_errors' );
		if ( ! is_array( $errors ) ) {
			$errors = array();
		}

		$errors[] = array(
			'message'   => $error['message'],
			'file'      => $error['file'],
			'line'      => $error['line'],
			'timestamp' => current_time( 'mysql' ),
		);

		// Keep only last 10 errors.
		if ( count( $errors ) > 10 ) {
			$errors = array_slice( $errors, -10 );
		}

		set_transient( 'vira_code_fatal_errors', $errors, DAY_IN_SECONDS );
	}

	/**
	 * Log execution error.
	 *
	 * @param array  $snippet Snippet data.
	 * @param string $error   Error message.
	 * @return void
	 */
	protected function logExecutionError( $snippet, $error ) {
		$log_entry = sprintf(
			"[%s] EXECUTION ERROR in snippet #%d (%s): %s\n",
			current_time( 'mysql' ),
			$snippet['id'],
			$snippet['title'],
			$error
		);

		$this->writeLog( $log_entry );
	}

	/**
	 * Write to error log file.
	 *
	 * @param string $message Log message.
	 * @return void
	 */
	protected function writeLog( $message ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$handle = @fopen( $this->error_log, 'a' );
		if ( $handle ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
			fwrite( $handle, $message );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			fclose( $handle );
		}
	}

	/**
	 * Check error threshold and enable safe mode if needed.
	 *
	 * @return void
	 */
	protected function checkErrorThreshold() {
		$errors = get_transient( 'vira_code_recent_errors' );
		if ( ! is_array( $errors ) ) {
			$errors = array();
		}

		$errors[] = time();

		// Keep only errors from last 5 minutes.
		$five_minutes_ago = time() - ( 5 * MINUTE_IN_SECONDS );
		$errors = array_filter( $errors, function( $timestamp ) use ( $five_minutes_ago ) {
			return $timestamp > $five_minutes_ago;
		} );

		set_transient( 'vira_code_recent_errors', $errors, 5 * MINUTE_IN_SECONDS );

		// Enable safe mode if threshold exceeded.
		$max_errors = apply_filters( 'vira_code/safe_mode_error_threshold', $this->max_errors );
		if ( count( $errors ) >= $max_errors ) {
			\ViraCode\vira_code_enable_safe_mode();
			do_action( 'vira_code/safe_mode_auto_enabled', count( $errors ) );
		}
	}

	/**
	 * Check if safe mode is enabled.
	 *
	 * @return bool
	 */
	public function isSafeModeEnabled() {
		return \ViraCode\vira_code_is_safe_mode();
	}

	/**
	 * Get error log contents.
	 *
	 * @param int $lines Number of lines to read from the end.
	 * @return string
	 */
	public function getErrorLog( $lines = 50 ) {
		if ( ! file_exists( $this->error_log ) ) {
			return '';
		}

		// Read last N lines from file.
		$file = file( $this->error_log );
		if ( ! $file ) {
			return '';
		}

		$lines = array_slice( $file, -$lines );
		return implode( '', $lines );
	}

	/**
	 * Clear error log.
	 *
	 * @return bool
	 */
	public function clearErrorLog() {
		if ( file_exists( $this->error_log ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			return @unlink( $this->error_log );
		}

		return true;
	}

	/**
	 * Get recent fatal errors.
	 *
	 * @return array
	 */
	public function getRecentErrors() {
		$errors = get_transient( 'vira_code_fatal_errors' );
		if ( ! is_array( $errors ) ) {
			return array();
		}

		return $errors;
	}
}
