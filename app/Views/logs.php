<?php
/**
 * Logs View
 *
 * @package ViraCode
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap vira-code-admin vira-logs-page">
	<h1 class="wp-heading-inline">
		<?php esc_html_e( 'Execution Logs', 'vira-code' ); ?>
	</h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=vira-code' ) ); ?>" class="page-title-action">
		<?php esc_html_e( 'Back to Snippets', 'vira-code' ); ?>
	</a>
	<button type="button" class="page-title-action" id="vira-clear-logs-btn">
		<?php esc_html_e( 'Clear Logs', 'vira-code' ); ?>
	</button>
	<hr class="wp-header-end">

	<div class="vira-logs-container">
		<?php if ( empty( $logs ) ) : ?>
			<div class="vira-empty-state">
				<div class="vira-empty-icon">
					<span class="dashicons dashicons-list-view"></span>
				</div>
				<h2><?php esc_html_e( 'No Logs Found', 'vira-code' ); ?></h2>
				<p><?php esc_html_e( 'Execution logs will appear here when snippets are executed.', 'vira-code' ); ?></p>
			</div>
		<?php else : ?>
			<div class="vira-logs-header">
				<p class="description">
					<?php
					printf(
						/* translators: %d: number of log entries */
						esc_html( _n( 'Showing %d log entry', 'Showing %d log entries', count( $logs ), 'vira-code' ) ),
						count( $logs )
					);
					?>
				</p>
			</div>

			<table class="wp-list-table widefat fixed striped vira-logs-table">
				<thead>
					<tr>
						<th class="column-timestamp"><?php esc_html_e( 'Timestamp', 'vira-code' ); ?></th>
						<th class="column-snippet"><?php esc_html_e( 'Snippet ID', 'vira-code' ); ?></th>
						<th class="column-status"><?php esc_html_e( 'Status', 'vira-code' ); ?></th>
						<th class="column-message"><?php esc_html_e( 'Message', 'vira-code' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $logs as $log ) : ?>
						<?php
						$status_class = 'success' === $log['status'] ? 'success' : 'error';
						$status_icon  = 'success' === $log['status'] ? 'yes' : 'no';
						?>
						<tr class="vira-log-entry vira-log-<?php echo esc_attr( $status_class ); ?>">
							<td class="column-timestamp" data-colname="<?php esc_attr_e( 'Timestamp', 'vira-code' ); ?>">
								<?php
								$timestamp = strtotime( $log['timestamp'] );
								echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ) );
								?>
								<br>
								<small class="vira-time-ago">
									<?php echo esc_html( human_time_diff( $timestamp, current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'vira-code' ) ); ?>
								</small>
							</td>
							<td class="column-snippet" data-colname="<?php esc_attr_e( 'Snippet ID', 'vira-code' ); ?>">
								<?php
								$model = new \ViraCode\Models\SnippetModel();
								$snippet = $model->getById( $log['snippet_id'] );
								?>
								<strong>#<?php echo esc_html( $log['snippet_id'] ); ?></strong>
								<?php if ( $snippet ) : ?>
									<br>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=vira-code-new&id=' . $log['snippet_id'] ) ); ?>">
										<?php echo esc_html( $snippet['title'] ); ?>
									</a>
								<?php else : ?>
									<br>
									<em class="vira-deleted-snippet"><?php esc_html_e( '(Deleted)', 'vira-code' ); ?></em>
								<?php endif; ?>
							</td>
							<td class="column-status" data-colname="<?php esc_attr_e( 'Status', 'vira-code' ); ?>">
								<span class="vira-log-status vira-log-status-<?php echo esc_attr( $status_class ); ?>">
									<span class="dashicons dashicons-<?php echo esc_attr( $status_icon ); ?>"></span>
									<?php echo esc_html( ucfirst( $log['status'] ) ); ?>
								</span>
							</td>
							<td class="column-message" data-colname="<?php esc_attr_e( 'Message', 'vira-code' ); ?>">
								<?php if ( ! empty( $log['message'] ) ) : ?>
									<div class="vira-log-message">
										<?php echo esc_html( $log['message'] ); ?>
									</div>
								<?php else : ?>
									<em><?php esc_html_e( 'No message', 'vira-code' ); ?></em>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
				<tfoot>
					<tr>
						<th class="column-timestamp"><?php esc_html_e( 'Timestamp', 'vira-code' ); ?></th>
						<th class="column-snippet"><?php esc_html_e( 'Snippet ID', 'vira-code' ); ?></th>
						<th class="column-status"><?php esc_html_e( 'Status', 'vira-code' ); ?></th>
						<th class="column-message"><?php esc_html_e( 'Message', 'vira-code' ); ?></th>
					</tr>
				</tfoot>
			</table>

			<div class="vira-logs-footer">
				<p class="description">
					<?php esc_html_e( 'Logs are stored for 7 days and limited to the last amount entries.', 'vira-code' ); ?>
				</p>
			</div>
		<?php endif; ?>
	</div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
	// Clear logs
	$('#vira-clear-logs-btn').on('click', function(e) {
		e.preventDefault();

		if (!confirm('<?php esc_html_e( 'Are you sure you want to clear all execution logs? This action cannot be undone.', 'vira-code' ); ?>')) {
			return;
		}

		var button = $(this);
		var originalText = button.text();

		button.prop('disabled', true).text('<?php esc_html_e( 'Clearing...', 'vira-code' ); ?>');

		$.ajax({
			url: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
			type: 'POST',
			data: {
				action: 'vira_code_clear_logs',
				nonce: '<?php echo esc_attr( wp_create_nonce( \ViraCode\vira_code_nonce_action() ) ); ?>'
			},
			success: function(response) {
				button.prop('disabled', false).text(originalText);

				if (response.success) {
					// Reload page after a short delay to show success
					setTimeout(function() {
						location.reload();
					}, 500);
				} else {
					alert(response.data.message || '<?php esc_html_e( 'An error occurred.', 'vira-code' ); ?>');
				}
			},
			error: function(xhr, status, error) {
				button.prop('disabled', false).text(originalText);
				alert('<?php esc_html_e( 'An error occurred. Please try again.', 'vira-code' ); ?>');
				console.error('AJAX Error:', status, error);
			}
		});
	});

	// Auto-refresh logs every 30 seconds (optional)
	var autoRefresh = false; // Set to true to enable auto-refresh
	if (autoRefresh) {
		setInterval(function() {
			location.reload();
		}, 30000);
	}
});
</script>
