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
	<!-- Page Header -->
	<div class="vira-page-header">
		<div class="vira-page-title">
			<h1 class="wp-heading-inline">
				<?php esc_html_e( 'Execution Logs', 'vira-code' ); ?>
			</h1>
			<span class="vira-page-subtitle">
				<?php esc_html_e( 'Monitor snippet execution history and debug issues', 'vira-code' ); ?>
			</span>
		</div>
		<div class="vira-page-actions">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=vira-code' ) ); ?>" class="button button-secondary">
				<span class="dashicons dashicons-arrow-left-alt"></span>
				<?php esc_html_e( 'Back to Snippets', 'vira-code' ); ?>
			</a>
			<button type="button" class="button button-primary" id="vira-clear-logs-btn">
				<span class="dashicons dashicons-trash"></span>
				<?php esc_html_e( 'Clear Logs', 'vira-code' ); ?>
			</button>
		</div>
	</div>
	<hr class="wp-header-end">

	<!-- Logs Content -->
	<div class="vira-logs-content">
		<?php if ( empty( $logs ) ) : ?>
			<!-- Empty State -->
			<div class="vira-empty-state">
				<div class="vira-empty-icon">
					<span class="dashicons dashicons-list-view"></span>
				</div>
				<h2><?php esc_html_e( 'No Logs Found', 'vira-code' ); ?></h2>
				<p><?php esc_html_e( 'Execution logs will appear here when snippets are executed. Logs help you monitor snippet performance and debug any issues.', 'vira-code' ); ?></p>
				<div class="vira-empty-actions">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=vira-code' ) ); ?>" class="button button-primary">
						<?php esc_html_e( 'View Snippets', 'vira-code' ); ?>
					</a>
				</div>
			</div>
		<?php else : ?>
			<!-- Logs Header with Stats -->
			<div class="vira-logs-stats">
				<div class="vira-stat-card">
					<div class="vira-stat-number"><?php echo esc_html( count( $logs ) ); ?></div>
					<div class="vira-stat-label">
						<?php echo esc_html( _n( 'Log Entry', 'Log Entries', count( $logs ), 'vira-code' ) ); ?>
					</div>
				</div>
				<div class="vira-stat-card">
					<?php
					$success_count = count( array_filter( $logs, function( $log ) {
						return $log['status'] === 'success';
					} ) );
					?>
					<div class="vira-stat-number vira-stat-success"><?php echo esc_html( $success_count ); ?></div>
					<div class="vira-stat-label"><?php esc_html_e( 'Successful', 'vira-code' ); ?></div>
				</div>
				<div class="vira-stat-card">
					<?php
					$error_count = count( $logs ) - $success_count;
					?>
					<div class="vira-stat-number vira-stat-error"><?php echo esc_html( $error_count ); ?></div>
					<div class="vira-stat-label"><?php esc_html_e( 'Errors', 'vira-code' ); ?></div>
				</div>
				<div class="vira-stat-card">
					<?php
					$log_limit = \ViraCode\vira_code_get_log_limit();
					?>
					<div class="vira-stat-number"><?php echo esc_html( $log_limit ); ?></div>
					<div class="vira-stat-label"><?php esc_html_e( 'Max Entries', 'vira-code' ); ?></div>
				</div>
			</div>

			<!-- Logs Table -->
			<div class="vira-logs-table-container">
				<table class="wp-list-table widefat fixed striped vira-logs-table">
					<thead>
						<tr>
							<th scope="col" class="column-timestamp">
								<span class="dashicons dashicons-clock"></span>
								<?php esc_html_e( 'Timestamp', 'vira-code' ); ?>
							</th>
							<th scope="col" class="column-snippet">
								<span class="dashicons dashicons-code-standards"></span>
								<?php esc_html_e( 'Snippet', 'vira-code' ); ?>
							</th>
							<th scope="col" class="column-status">
								<span class="dashicons dashicons-flag"></span>
								<?php esc_html_e( 'Status', 'vira-code' ); ?>
							</th>
							<th scope="col" class="column-message">
								<span class="dashicons dashicons-text"></span>
								<?php esc_html_e( 'Message', 'vira-code' ); ?>
							</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $logs as $index => $log ) : ?>
							<?php
							$status_class = 'success' === $log['status'] ? 'success' : 'error';
							$status_icon  = 'success' === $log['status'] ? 'yes-alt' : 'dismiss';
							$row_class = 'success' === $log['status'] ? 'vira-log-success' : 'vira-log-error';
							?>
							<tr class="vira-log-entry <?php echo esc_attr( $row_class ); ?>" data-log-index="<?php echo esc_attr( $index ); ?>">
								<td class="column-timestamp" data-colname="<?php esc_attr_e( 'Timestamp', 'vira-code' ); ?>">
									<div class="vira-timestamp-wrapper">
										<div class="vira-timestamp-main">
											<?php
											$timestamp = strtotime( $log['timestamp'] );
											echo esc_html( date_i18n( 'M j, Y', $timestamp ) );
											?>
										</div>
										<div class="vira-timestamp-time">
											<?php echo esc_html( date_i18n( 'g:i A', $timestamp ) ); ?>
										</div>
										<div class="vira-timestamp-relative">
											<?php echo esc_html( human_time_diff( $timestamp, current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'vira-code' ) ); ?>
										</div>
									</div>
								</td>
								<td class="column-snippet" data-colname="<?php esc_attr_e( 'Snippet', 'vira-code' ); ?>">
									<div class="vira-snippet-info">
										<?php
										$manager = new \ViraCode\Services\SnippetManagerService();
										$snippet = $manager->getById( $log['snippet_id'] );
										?>
										<div class="vira-snippet-id">
											<strong>#<?php echo esc_html( $log['snippet_id'] ); ?></strong>
										</div>
										<?php if ( $snippet ) : ?>
											<div class="vira-snippet-title">
												<a href="<?php echo esc_url( admin_url( 'admin.php?page=vira-code-new&id=' . $log['snippet_id'] ) ); ?>" class="vira-snippet-link">
													<?php echo esc_html( $snippet['title'] ); ?>
												</a>
											</div>
											<div class="vira-snippet-meta">
												<span class="vira-snippet-type vira-badge-<?php echo esc_attr( $snippet['type'] ?? 'php' ); ?>">
													<?php echo esc_html( strtoupper( $snippet['type'] ?? 'PHP' ) ); ?>
												</span>
												<span class="vira-storage-badge vira-storage-badge-<?php echo esc_attr( $snippet['storage_type'] ?? 'database' ); ?>">
													<?php echo esc_html( ucfirst( $snippet['storage_type'] ?? 'Database' ) ); ?>
												</span>
											</div>
										<?php else : ?>
											<div class="vira-snippet-deleted">
												<em><?php esc_html_e( 'Snippet Deleted', 'vira-code' ); ?></em>
											</div>
										<?php endif; ?>
									</div>
								</td>
								<td class="column-status" data-colname="<?php esc_attr_e( 'Status', 'vira-code' ); ?>">
									<div class="vira-status-wrapper">
										<span class="vira-log-status vira-log-status-<?php echo esc_attr( $status_class ); ?>">
											<span class="dashicons dashicons-<?php echo esc_attr( $status_icon ); ?>"></span>
											<span class="vira-status-text"><?php echo esc_html( ucfirst( $log['status'] ) ); ?></span>
										</span>
									</div>
								</td>
								<td class="column-message" data-colname="<?php esc_attr_e( 'Message', 'vira-code' ); ?>">
									<div class="vira-message-wrapper">
										<?php if ( ! empty( $log['message'] ) ) : ?>
											<div class="vira-log-message">
												<?php echo esc_html( $log['message'] ); ?>
											</div>
										<?php else : ?>
											<div class="vira-log-message vira-message-empty">
												<em><?php esc_html_e( 'No message recorded', 'vira-code' ); ?></em>
											</div>
										<?php endif; ?>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<!-- Logs Footer -->
			<div class="vira-logs-footer">
				<div class="vira-logs-info">
					<p class="description">
						<span class="dashicons dashicons-info"></span>
						<?php 
						printf(
							/* translators: %d: log limit number */
							esc_html__( 'Logs are automatically managed and limited to the last %d entries to maintain optimal performance.', 'vira-code' ),
							$log_limit
						);
						?>
					</p>
				</div>
				<div class="vira-logs-actions">
					<button type="button" class="button button-secondary" id="vira-refresh-logs">
						<span class="dashicons dashicons-update"></span>
						<?php esc_html_e( 'Refresh', 'vira-code' ); ?>
					</button>
				</div>
			</div>
		<?php endif; ?>
	</div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
	// Clear logs functionality
	$('#vira-clear-logs-btn').on('click', function(e) {
		e.preventDefault();

		if (!confirm('<?php esc_html_e( 'Are you sure you want to clear all execution logs? This action cannot be undone.', 'vira-code' ); ?>')) {
			return;
		}

		const button = $(this);
		const originalHtml = button.html();

		button.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> <?php esc_html_e( 'Clearing...', 'vira-code' ); ?>');

		$.ajax({
			url: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
			type: 'POST',
			data: {
				action: 'vira_code_clear_logs',
				nonce: '<?php echo esc_attr( wp_create_nonce( \ViraCode\vira_code_nonce_action() ) ); ?>'
			},
			success: function(response) {
				if (response.success) {
					// Show success message briefly before reload
					button.html('<span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Cleared!', 'vira-code' ); ?>');
					setTimeout(function() {
						location.reload();
					}, 800);
				} else {
					button.prop('disabled', false).html(originalHtml);
					alert(response.data.message || '<?php esc_html_e( 'An error occurred while clearing logs.', 'vira-code' ); ?>');
				}
			},
			error: function() {
				button.prop('disabled', false).html(originalHtml);
				alert('<?php esc_html_e( 'An error occurred. Please try again.', 'vira-code' ); ?>');
			}
		});
	});

	// Refresh logs functionality
	$('#vira-refresh-logs').on('click', function(e) {
		e.preventDefault();
		location.reload();
	});

	// Add hover effects to log entries
	$('.vira-log-entry').hover(
		function() {
			$(this).addClass('vira-log-hover');
		},
		function() {
			$(this).removeClass('vira-log-hover');
		}
	);

	// Add click to expand functionality for long messages
	$('.vira-log-message').each(function() {
		const message = $(this);
		const text = message.text();
		
		if (text.length > 100) {
			const shortText = text.substring(0, 100) + '...';
			const fullText = text;
			
			message.html(shortText + ' <button type="button" class="vira-expand-message button-link"><?php esc_html_e( 'Show More', 'vira-code' ); ?></button>');
			
			message.find('.vira-expand-message').on('click', function(e) {
				e.preventDefault();
				const isExpanded = message.hasClass('vira-message-expanded');
				
				if (isExpanded) {
					message.html(shortText + ' <button type="button" class="vira-expand-message button-link"><?php esc_html_e( 'Show More', 'vira-code' ); ?></button>');
					message.removeClass('vira-message-expanded');
				} else {
					message.html(fullText + ' <button type="button" class="vira-expand-message button-link"><?php esc_html_e( 'Show Less', 'vira-code' ); ?></button>');
					message.addClass('vira-message-expanded');
				}
				
				// Re-bind the click event
				message.find('.vira-expand-message').on('click', arguments.callee);
			});
		}
	});
});
</script>