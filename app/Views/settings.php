<?php
/**
 * Settings View
 *
 * @package ViraCode
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap vira-code-admin vira-settings-page">
	<h1 class="wp-heading-inline">
		<?php esc_html_e( 'Vira Code Settings', 'vira-code' ); ?>
	</h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=vira-code' ) ); ?>" class="page-title-action">
		<?php esc_html_e( 'Back to Snippets', 'vira-code' ); ?>
	</a>
	<hr class="wp-header-end">

	<?php settings_errors( 'vira_code_settings' ); ?>

	<div class="vira-settings-container">
		<form method="post" action="" class="vira-settings-form">
			<?php wp_nonce_field( 'vira_code_save_settings', 'vira_code_settings_nonce' ); ?>

			<!-- Safe Mode Section -->
			<div class="vira-settings-section">
				<h2><?php esc_html_e( 'Safe Mode', 'vira-code' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Safe Mode disables all code snippets. Use this if a snippet is causing issues on your site.', 'vira-code' ); ?>
				</p>

				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">
								<label for="safe_mode">
									<?php esc_html_e( 'Enable Safe Mode', 'vira-code' ); ?>
								</label>
							</th>
							<td>
								<fieldset>
									<label for="safe_mode">
										<input
											type="checkbox"
											id="safe_mode"
											name="safe_mode"
											value="1"
											<?php checked( $settings['safe_mode'], true ); ?>
										>
										<?php esc_html_e( 'Disable all snippets temporarily', 'vira-code' ); ?>
									</label>
									<p class="description">
										<?php esc_html_e( 'When enabled, no snippets will execute on your site. This is useful for troubleshooting.', 'vira-code' ); ?>
									</p>
								</fieldset>

								<?php if ( $settings['safe_mode'] ) : ?>
									<div class="notice notice-warning inline">
										<p>
											<strong><?php esc_html_e( 'Safe Mode is Currently Active', 'vira-code' ); ?></strong><br>
											<?php esc_html_e( 'All code snippets are disabled. Uncheck the option above and save to resume normal operation.', 'vira-code' ); ?>
										</p>
									</div>
								<?php endif; ?>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<?php esc_html_e( 'Quick Safe Mode Toggle', 'vira-code' ); ?>
							</th>
							<td>
								<button type="button" class="button button-secondary" id="vira-toggle-safe-mode">
									<?php echo $settings['safe_mode'] ? esc_html__( 'Disable Safe Mode Now', 'vira-code' ) : esc_html__( 'Enable Safe Mode Now', 'vira-code' ); ?>
								</button>
								<p class="description">
									<?php esc_html_e( 'Toggle Safe Mode instantly via AJAX without reloading the page.', 'vira-code' ); ?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<?php esc_html_e( 'Safe Mode URL', 'vira-code' ); ?>
							</th>
							<td>
								<p class="description">
									<?php
									$safe_mode_url = add_query_arg(
										array(
											'vira_safe_mode' => '1',
										),
										admin_url( 'admin.php?page=vira-code-settings' )
									);
									printf(
										/* translators: %s: Safe Mode URL */
										esc_html__( 'You can also enable Safe Mode by visiting: %s', 'vira-code' ),
										'<br><code>' . esc_url( $safe_mode_url ) . '</code>'
									);
									?>
								</p>
								<p class="description">
									<?php esc_html_e( 'Bookmark this URL in case you need emergency access to disable all snippets.', 'vira-code' ); ?>
								</p>
							</td>
						</tr>
					</tbody>
				</table>
			</div>

			<!-- Plugin Information -->
			<div class="vira-settings-section">
				<h2><?php esc_html_e( 'Plugin Information', 'vira-code' ); ?></h2>

				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">
								<?php esc_html_e( 'Version', 'vira-code' ); ?>
							</th>
							<td>
								<code><?php echo esc_html( VIRA_CODE_VERSION ); ?></code>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<?php esc_html_e( 'Database Table', 'vira-code' ); ?>
							</th>
							<td>
								<?php
								global $wpdb;
								$table_name = $wpdb->prefix . 'vira_snippets';
								?>
								<code><?php echo esc_html( $table_name ); ?></code>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<?php esc_html_e( 'PHP Version', 'vira-code' ); ?>
							</th>
							<td>
								<code><?php echo esc_html( PHP_VERSION ); ?></code>
								<?php if ( version_compare( PHP_VERSION, '8.2.0', '>=' ) ) : ?>
									<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
								<?php else : ?>
									<span class="dashicons dashicons-warning" style="color: #f56e28;"></span>
									<?php esc_html_e( 'PHP 8.2+ is recommended', 'vira-code' ); ?>
								<?php endif; ?>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<?php esc_html_e( 'WordPress Version', 'vira-code' ); ?>
							</th>
							<td>
								<code><?php echo esc_html( get_bloginfo( 'version' ) ); ?></code>
							</td>
						</tr>
					</tbody>
				</table>
			</div>

			<!-- System Status -->
			<div class="vira-settings-section">
				<h2><?php esc_html_e( 'System Status', 'vira-code' ); ?></h2>

				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">
								<?php esc_html_e( 'Storage Mode', 'vira-code' ); ?>
							</th>
							<td>
								<?php
								$storage_mode = get_option( 'vira_code_storage_mode', 'database' );
								$file_storage = new \ViraCode\Services\FileStorageService();
								$file_available = $file_storage->isAvailable();
								?>
								<div class="vira-storage-mode-indicator vira-mode-<?php echo esc_attr( $storage_mode ); ?>">
									<?php if ( 'file' === $storage_mode ) : ?>
										<span class="dashicons dashicons-media-document"></span>
										<?php esc_html_e( 'File Storage', 'vira-code' ); ?>
									<?php else : ?>
										<span class="dashicons dashicons-database"></span>
										<?php esc_html_e( 'Database Storage', 'vira-code' ); ?>
									<?php endif; ?>
								</div>
								<br>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=vira-code-migration' ) ); ?>" class="button button-secondary">
									<?php esc_html_e( 'Manage Storage', 'vira-code' ); ?>
								</a>
								<?php if ( 'file' === $storage_mode && ! $file_available ) : ?>
									<p class="description" style="color: #dc3232;">
										<?php esc_html_e( 'Warning: File storage is not available. Check directory permissions.', 'vira-code' ); ?>
									</p>
								<?php endif; ?>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<?php esc_html_e( 'Total Snippets', 'vira-code' ); ?>
							</th>
							<td>
								<?php
								$manager = new \ViraCode\Services\SnippetManagerService();
								$stats = array(
									'total'    => $manager->count(),
									'active'   => $manager->count( array( 'status' => 'active' ) ),
									'inactive' => $manager->count( array( 'status' => 'inactive' ) ),
									'error'    => $manager->count( array( 'status' => 'error' ) ),
								);
								?>
								<strong><?php echo esc_html( $stats['total'] ); ?></strong>
								(<?php
								printf(
									/* translators: 1: active count, 2: inactive count, 3: error count */
									esc_html__( '%1$d active, %2$d inactive, %3$d errors', 'vira-code' ),
									$stats['active'],
									$stats['inactive'],
									$stats['error']
								);
								?>)
							</td>
						</tr>

						<tr>
							<th scope="row">
								<?php esc_html_e( 'Execution Logs', 'vira-code' ); ?>
							</th>
							<td>
								<?php
								$logs = \ViraCode\vira_code_get_logs( $settings['log_limit'] );
								$log_count = count( $logs );
								$log_limit = (int) $settings['log_limit'];
								?>
								<strong><?php echo esc_html( $log_count ); ?></strong> 
								<?php
								if ( $log_count >= $log_limit ) {
									printf(
										/* translators: %d: log limit number */
										esc_html__( 'entries (showing last %d)', 'vira-code' ),
										$log_limit
									);
								} else {
									esc_html_e( 'entries', 'vira-code' );
								}
								?>
								<br>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=vira-code-logs' ) ); ?>" class="button button-secondary">
									<?php esc_html_e( 'View Logs', 'vira-code' ); ?>
								</a>
								<?php if ( $log_count >= $log_limit ) : ?>
									<p class="description">
										<?php
										printf(
											/* translators: %d: log limit number */
											esc_html__( 'Only the most recent %d log entries are displayed. Increase the limit below to show more entries.', 'vira-code' ),
											$log_limit
										);
										?>
									</p>
								<?php endif; ?>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<?php esc_html_e( 'REST API', 'vira-code' ); ?>
							</th>
							<td>
								<code><?php echo esc_url( rest_url( 'vira/v1/' ) ); ?></code>
								<p class="description">
									<?php esc_html_e( 'Use the REST API to manage snippets programmatically.', 'vira-code' ); ?>
								</p>
							</td>
						</tr>
					</tbody>
				</table>
			</div>

			<!-- Log Settings -->
			<div class="vira-settings-section">
				<h2><?php esc_html_e( 'Log Settings', 'vira-code' ); ?></h2>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">
								<label for="log_limit">
									<?php esc_html_e( 'Number of Logs to Display', 'vira-code' ); ?>
								</label>
							</th>
							<td>
								<input
									type="number"
									id="log_limit"
									name="log_limit"
									value="<?php echo esc_attr( $settings['log_limit'] ); ?>"
									min="10"
									step="10"
									class="small-text"
								>
								<p class="description">
									<?php esc_html_e( 'Set the maximum number of execution logs to display on the logs page and in the system status above.', 'vira-code' ); ?>
								</p>
							</td>
						</tr>
					</tbody>
				</table>
			</div>

			<p class="submit">
				<button type="submit" class="button button-primary button-large">
					<?php esc_html_e( 'Save Settings', 'vira-code' ); ?>
				</button>
			</p>
		</form>
	</div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
	// Ensure ajaxUrl and nonce are available even if localization hasn't run yet
	const ajaxUrl = "<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>";
	const viraNonce = "<?php echo esc_js( wp_create_nonce( \ViraCode\vira_code_nonce_action() ) ); ?>";
	
	// Toggle safe mode via AJAX
	$('#vira-toggle-safe-mode').on('click', function(e) {
		e.preventDefault();
		var button = $(this);
		var originalText = button.text();

		button.prop('disabled', true).text('<?php esc_html_e( 'Processing...', 'vira-code' ); ?>');

		$.ajax({
			url: ajaxUrl,
			type: 'POST',
			data: {
				action: 'vira_code_toggle_safe_mode',
				nonce: viraNonce
			},
			success: function(response) {
				if (response.success) {
					button.text(response.data.safe_mode ? '<?php esc_html_e( 'Disable Safe Mode Now', 'vira-code' ); ?>' : '<?php esc_html_e( 'Enable Safe Mode Now', 'vira-code' ); ?>');

					// Update checkbox
					$('#safe_mode').prop('checked', response.data.safe_mode);

					// Show success message
					$('.vira-settings-form').prepend(
						'<div class="notice notice-success is-dismissible"><p>' + response.data.message + '</p></div>'
					);

					// Reload page after 1 second
					setTimeout(function() {
						location.reload();
					}, 1000);
				} else {
					button.text(originalText);
					alert(response.data.message || '<?php esc_html_e( 'An error occurred.', 'vira-code' ); ?>');
				}
			},
			error: function() {
				button.prop('disabled', false).text(originalText);
				alert('<?php esc_html_e( 'An error occurred. Please try again.', 'vira-code' ); ?>');
			}
		});
	});
});
</script>
