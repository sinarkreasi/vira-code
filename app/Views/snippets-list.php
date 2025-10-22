<?php
/**
 * Snippets List View
 *
 * @package ViraCode
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap vira-code-admin">
	<h1 class="wp-heading-inline">
		<?php esc_html_e( 'Code Snippets', 'vira-code' ); ?>
	</h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=vira-code-new' ) ); ?>" class="page-title-action">
		<?php esc_html_e( 'Add New', 'vira-code' ); ?>
	</a>
	<hr class="wp-header-end">

	<?php if ( \ViraCode\vira_code_is_safe_mode() ) : ?>
		<div class="notice notice-warning">
			<p>
				<strong><?php esc_html_e( 'Safe Mode is Active', 'vira-code' ); ?></strong> -
				<?php esc_html_e( 'All snippets are currently disabled.', 'vira-code' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=vira-code-settings' ) ); ?>">
					<?php esc_html_e( 'Disable Safe Mode', 'vira-code' ); ?>
				</a>
			</p>
		</div>
	<?php endif; ?>

	<!-- Statistics Cards -->
	<div class="vira-stats-cards">
		<div class="vira-stat-card">
			<div class="vira-stat-value"><?php echo esc_html( $stats['total'] ); ?></div>
			<div class="vira-stat-label"><?php esc_html_e( 'Total Snippets', 'vira-code' ); ?></div>
		</div>
		<div class="vira-stat-card vira-stat-active">
			<div class="vira-stat-value"><?php echo esc_html( $stats['active'] ); ?></div>
			<div class="vira-stat-label"><?php esc_html_e( 'Active', 'vira-code' ); ?></div>
		</div>
		<div class="vira-stat-card vira-stat-inactive">
			<div class="vira-stat-value"><?php echo esc_html( $stats['inactive'] ); ?></div>
			<div class="vira-stat-label"><?php esc_html_e( 'Inactive', 'vira-code' ); ?></div>
		</div>
		<div class="vira-stat-card vira-stat-error">
			<div class="vira-stat-value"><?php echo esc_html( $stats['error'] ); ?></div>
			<div class="vira-stat-label"><?php esc_html_e( 'Errors', 'vira-code' ); ?></div>
		</div>
		<div class="vira-stat-card vira-stat-conditional">
			<div class="vira-stat-value"><?php echo esc_html( $stats['conditional_logic'] ); ?></div>
			<div class="vira-stat-label"><?php esc_html_e( 'Conditional Logic', 'vira-code' ); ?></div>
		</div>
	</div>

	<!-- Filters and Search -->
	<div class="vira-list-filters">
		<div class="vira-filter-controls">
			<div class="vira-filter-group">
				<label for="vira-filter-status"><?php esc_html_e( 'Status:', 'vira-code' ); ?></label>
				<select id="vira-filter-status" class="vira-filter-select">
					<option value=""><?php esc_html_e( 'All Statuses', 'vira-code' ); ?></option>
					<option value="active"><?php esc_html_e( 'Active', 'vira-code' ); ?></option>
					<option value="inactive"><?php esc_html_e( 'Inactive', 'vira-code' ); ?></option>
					<option value="error"><?php esc_html_e( 'Error', 'vira-code' ); ?></option>
				</select>
			</div>
			<div class="vira-filter-group">
				<label for="vira-filter-type"><?php esc_html_e( 'Type:', 'vira-code' ); ?></label>
				<select id="vira-filter-type" class="vira-filter-select">
					<option value=""><?php esc_html_e( 'All Types', 'vira-code' ); ?></option>
					<?php
					$snippet_types = \ViraCode\vira_code_snippet_types();
					foreach ( $snippet_types as $type => $label ) :
					?>
						<option value="<?php echo esc_attr( $type ); ?>"><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="vira-filter-group">
				<label for="vira-filter-conditional"><?php esc_html_e( 'Conditional Logic:', 'vira-code' ); ?></label>
				<select id="vira-filter-conditional" class="vira-filter-select">
					<option value=""><?php esc_html_e( 'All Snippets', 'vira-code' ); ?></option>
					<option value="enabled"><?php esc_html_e( 'With Conditional Logic', 'vira-code' ); ?></option>
					<option value="disabled"><?php esc_html_e( 'Without Conditional Logic', 'vira-code' ); ?></option>
				</select>
			</div>
			<div class="vira-filter-group">
				<label for="vira-search-input"><?php esc_html_e( 'Search:', 'vira-code' ); ?></label>
				<input type="text" id="vira-search-input" class="vira-search-input" placeholder="<?php esc_attr_e( 'Search snippets...', 'vira-code' ); ?>">
			</div>
			<div class="vira-filter-group">
				<button type="button" id="vira-clear-filters" class="button"><?php esc_html_e( 'Clear Filters', 'vira-code' ); ?></button>
			</div>
		</div>
		<div class="vira-bulk-actions">
			<div class="vira-bulk-group">
				<label for="vira-bulk-select-all">
					<input type="checkbox" id="vira-bulk-select-all">
					<?php esc_html_e( 'Select All', 'vira-code' ); ?>
				</label>
			</div>
			<div class="vira-bulk-group">
				<select id="vira-bulk-action" class="vira-bulk-select">
					<option value=""><?php esc_html_e( 'Bulk Actions', 'vira-code' ); ?></option>
					<option value="delete_rules"><?php esc_html_e( 'Delete Conditional Logic Rules', 'vira-code' ); ?></option>
					<option value="copy_rules"><?php esc_html_e( 'Copy Conditional Logic Rules', 'vira-code' ); ?></option>
					<option value="export_rules"><?php esc_html_e( 'Export Conditional Logic Rules', 'vira-code' ); ?></option>
				</select>
			</div>
			<div class="vira-bulk-group">
				<button type="button" id="vira-apply-bulk-action" class="button" disabled><?php esc_html_e( 'Apply', 'vira-code' ); ?></button>
			</div>
		</div>
	</div>

	<?php if ( empty( $snippets ) ) : ?>
		<div class="vira-empty-state">
			<div class="vira-empty-icon">
				<span class="dashicons dashicons-editor-code"></span>
			</div>
			<h2><?php esc_html_e( 'No Snippets Found', 'vira-code' ); ?></h2>
			<p><?php esc_html_e( 'Get started by creating your first code snippet.', 'vira-code' ); ?></p>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=vira-code-new' ) ); ?>" class="button button-primary button-hero">
				<?php esc_html_e( 'Add Your First Snippet', 'vira-code' ); ?>
			</a>
		</div>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped vira-snippets-table">
			<thead>
				<tr>
					<th class="check-column"><input type="checkbox" id="cb-select-all-1"></th>
					<th class="column-title column-primary"><?php esc_html_e( 'Title', 'vira-code' ); ?></th>
					<th class="column-type"><?php esc_html_e( 'Type', 'vira-code' ); ?></th>
					<th class="column-scope"><?php esc_html_e( 'Scope', 'vira-code' ); ?></th>
					<th class="column-status"><?php esc_html_e( 'Status', 'vira-code' ); ?></th>
					<th class="column-conditional"><?php esc_html_e( 'Conditional Logic', 'vira-code' ); ?></th>
					<th class="column-priority"><?php esc_html_e( 'Priority', 'vira-code' ); ?></th>
					<th class="column-updated"><?php esc_html_e( 'Last Updated', 'vira-code' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $snippets as $snippet ) : ?>
					<tr data-snippet-id="<?php echo esc_attr( $snippet['id'] ); ?>" 
						data-status="<?php echo esc_attr( $snippet['status'] ); ?>"
						data-type="<?php echo esc_attr( $snippet['type'] ); ?>"
						data-conditional="<?php echo $snippet['conditional_logic_enabled'] ? 'enabled' : 'disabled'; ?>">
						<th class="check-column">
							<input type="checkbox" name="snippet[]" value="<?php echo esc_attr( $snippet['id'] ); ?>" class="snippet-checkbox">
						</th>
						<td class="column-title column-primary" data-colname="<?php esc_attr_e( 'Title', 'vira-code' ); ?>">
							<strong>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=vira-code-new&id=' . $snippet['id'] ) ); ?>" class="row-title">
									<?php echo esc_html( $snippet['title'] ); ?>
								</a>
							</strong>
							<?php if ( ! empty( $snippet['description'] ) ) : ?>
								<p class="snippet-description"><?php echo esc_html( wp_trim_words( $snippet['description'], 15 ) ); ?></p>
							<?php endif; ?>
							<div class="row-actions">
								<span class="edit">
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=vira-code-new&id=' . $snippet['id'] ) ); ?>">
										<?php esc_html_e( 'Edit', 'vira-code' ); ?>
									</a> |
								</span>
								<span class="toggle">
									<a href="javascript:void(0);" class="vira-toggle-snippet" data-id="<?php echo esc_attr( $snippet['id'] ); ?>">
										<?php echo 'active' === $snippet['status'] ? esc_html__( 'Deactivate', 'vira-code' ) : esc_html__( 'Activate', 'vira-code' ); ?>
									</a> |
								</span>
								<span class="trash">
									<a href="javascript:void(0);" class="vira-delete-snippet" data-id="<?php echo esc_attr( $snippet['id'] ); ?>">
										<?php esc_html_e( 'Delete', 'vira-code' ); ?>
									</a>
								</span>
							</div>
						</td>
						<td class="column-type" data-colname="<?php esc_attr_e( 'Type', 'vira-code' ); ?>">
							<span class="vira-badge vira-badge-<?php echo esc_attr( $snippet['type'] ); ?>">
								<?php echo esc_html( strtoupper( $snippet['type'] ) ); ?>
							</span>
						</td>
						<td class="column-scope" data-colname="<?php esc_attr_e( 'Scope', 'vira-code' ); ?>">
							<?php
							$scopes = \ViraCode\vira_code_snippet_scopes();
							echo esc_html( $scopes[ $snippet['scope'] ] ?? $snippet['scope'] );
							?>
						</td>
						<td class="column-status" data-colname="<?php esc_attr_e( 'Status', 'vira-code' ); ?>">
							<?php
							$status_class = 'active' === $snippet['status'] ? 'success' : ( 'error' === $snippet['status'] ? 'error' : 'default' );
							$status_text  = ucfirst( $snippet['status'] );
							?>
							<span class="vira-status vira-status-<?php echo esc_attr( $status_class ); ?>">
								<?php echo esc_html( $status_text ); ?>
							</span>
							<?php if ( 'error' === $snippet['status'] && ! empty( $snippet['error_message'] ) ) : ?>
								<span class="dashicons dashicons-warning" title="<?php echo esc_attr( $snippet['error_message'] ); ?>"></span>
							<?php endif; ?>
						</td>
						<td class="column-conditional" data-colname="<?php esc_attr_e( 'Conditional Logic', 'vira-code' ); ?>">
							<?php if ( $snippet['conditional_logic_enabled'] ) : ?>
								<div class="vira-conditional-logic-indicator">
									<span class="vira-conditional-badge vira-conditional-enabled">
										<span class="dashicons dashicons-admin-settings"></span>
										<?php esc_html_e( 'Enabled', 'vira-code' ); ?>
									</span>
									<span class="vira-conditional-count">
										<?php
										printf(
											_n(
												'%d rule',
												'%d rules',
												$snippet['conditional_logic_count'],
												'vira-code'
											),
											$snippet['conditional_logic_count']
										);
										?>
									</span>
									<button type="button" class="vira-conditional-preview-btn" 
											data-snippet-id="<?php echo esc_attr( $snippet['id'] ); ?>"
											title="<?php esc_attr_e( 'Preview conditional logic rules', 'vira-code' ); ?>">
										<span class="dashicons dashicons-visibility"></span>
									</button>
								</div>
							<?php else : ?>
								<span class="vira-conditional-badge vira-conditional-disabled">
									<?php esc_html_e( 'Disabled', 'vira-code' ); ?>
								</span>
							<?php endif; ?>
						</td>
						<td class="column-priority" data-colname="<?php esc_attr_e( 'Priority', 'vira-code' ); ?>">
							<?php echo esc_html( $snippet['priority'] ); ?>
						</td>
						<td class="column-updated" data-colname="<?php esc_attr_e( 'Last Updated', 'vira-code' ); ?>">
							<?php
							$updated_time = strtotime( $snippet['updated_at'] );
							echo esc_html( human_time_diff( $updated_time, current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'vira-code' ) );
							?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
			<tfoot>
				<tr>
					<th class="check-column"><input type="checkbox" id="cb-select-all-2"></th>
					<th class="column-title column-primary"><?php esc_html_e( 'Title', 'vira-code' ); ?></th>
					<th class="column-type"><?php esc_html_e( 'Type', 'vira-code' ); ?></th>
					<th class="column-scope"><?php esc_html_e( 'Scope', 'vira-code' ); ?></th>
					<th class="column-status"><?php esc_html_e( 'Status', 'vira-code' ); ?></th>
					<th class="column-conditional"><?php esc_html_e( 'Conditional Logic', 'vira-code' ); ?></th>
					<th class="column-priority"><?php esc_html_e( 'Priority', 'vira-code' ); ?></th>
					<th class="column-updated"><?php esc_html_e( 'Last Updated', 'vira-code' ); ?></th>
				</tr>
			</tfoot>
		</table>
	<?php endif; ?>

	<!-- Conditional Logic Preview Modal -->
	<div id="vira-conditional-preview-modal" class="vira-modal" style="display: none;">
		<div class="vira-modal-content">
			<div class="vira-modal-header">
				<h3><?php esc_html_e( 'Conditional Logic Rules', 'vira-code' ); ?></h3>
				<button type="button" class="vira-modal-close">
					<span class="dashicons dashicons-no-alt"></span>
				</button>
			</div>
			<div class="vira-modal-body">
				<div id="vira-conditional-preview-content">
					<div class="vira-loading-spinner">
						<span class="dashicons dashicons-update-alt"></span>
						<?php esc_html_e( 'Loading rules...', 'vira-code' ); ?>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<script type="text/javascript">
// Ensure viraCode object is available before initializing
(function($) {
	// Create viraCode object immediately if it doesn't exist
	if (typeof window.viraCode === 'undefined') {
		window.viraCode = {
			ajaxUrl: '<?php echo admin_url("admin-ajax.php"); ?>',
			nonce: '<?php echo wp_create_nonce(\ViraCode\vira_code_nonce_action()); ?>',
			i18n: {
				confirm_delete: '<?php echo esc_js(__("Are you sure you want to delete this snippet?", "vira-code")); ?>',
				delete_error: '<?php echo esc_js(__("Error deleting snippet. Please try again.", "vira-code")); ?>'
			}
		};
		console.log('[Vira Code] Created viraCode object inline');
	}
	
	// Function to initialize the event handlers
	function initSnippetsList() {
		// Debug logging
		if (window.console && console.log) {
			console.log('[Vira Code] Initializing snippets list');
			console.log('[Vira Code] viraCode object available:', typeof viraCode !== 'undefined');
			if (typeof viraCode !== 'undefined') {
				console.log('[Vira Code] AJAX URL:', viraCode.ajaxUrl);
				console.log('[Vira Code] Nonce present:', !!viraCode.nonce);
			}
		}
		
		// Bind click events for toggle links
		$(document).on('click', '.vira-toggle-snippet', function(e) {
			e.preventDefault();
			e.stopPropagation();
			
			const link = $(this);
			const snippetId = link.data('id');
			const row = link.closest('tr');
			
			console.log('[Vira Code] Toggle clicked for snippet ID:', snippetId);
			
			// Send AJAX request
			$.ajax({
				url: viraCode.ajaxUrl,
				type: 'POST',
				data: {
					action: 'vira_code_toggle_snippet',
					nonce: viraCode.nonce,
					id: snippetId,
				},
				beforeSend: function() {
					row.addClass('vira-loading');
				},
				success: function(response) {
					console.log('[Vira Code] Toggle response:', response);
					
					if (response.success) {
						// Update link text
						const newText = response.data.status === 'active' ? 'Deactivate' : 'Activate';
						link.text(newText);
						
						// Update status badge
						const statusCell = row.find('.column-status');
						const statusClass = response.data.status === 'active' ? 'success' : 'default';
						const statusText = response.data.status.charAt(0).toUpperCase() + response.data.status.slice(1);
						statusCell.html('<span class="vira-status vira-status-' + statusClass + '">' + statusText + '</span>');
						
						// Update statistics
						updateStatistics();
						
						// Show success message
						showNotice('success', response.data.message);
					} else {
						showNotice('error', response.data.message || 'Failed to toggle snippet status.');
					}
					
					row.removeClass('vira-loading');
				},
				error: function(xhr, status, error) {
					console.error('[Vira Code] AJAX error:', { status: status, error: error, xhr: xhr });
					row.removeClass('vira-loading');
					showNotice('error', 'An error occurred. Please try again.');
				}
			});
		});
		
		// Bind click events for delete links
		$(document).on('click', '.vira-delete-snippet', function(e) {
			e.preventDefault();
			e.stopPropagation();
			
			if (!confirm(viraCode.i18n.confirm_delete || 'Are you sure you want to delete this snippet?')) {
				return;
			}
			
			const link = $(this);
			const snippetId = link.data('id');
			const row = link.closest('tr');
			
			console.log('[Vira Code] Delete clicked for snippet ID:', snippetId);
			
			// Send AJAX request
			$.ajax({
				url: viraCode.ajaxUrl,
				type: 'POST',
				data: {
					action: 'vira_code_delete_snippet',
					nonce: viraCode.nonce,
					id: snippetId,
				},
				beforeSend: function() {
					row.addClass('vira-loading');
				},
				success: function(response) {
					if (response.success) {
						row.fadeOut(300, function() {
							$(this).remove();
							updateStatistics();
							showNotice('success', response.data.message);
						});
					} else {
						row.removeClass('vira-loading');
						showNotice('error', response.data.message || viraCode.i18n.delete_error);
					}
				},
				error: function(xhr, status, error) {
					console.error('[Vira Code] AJAX error:', { status: status, error: error, xhr: xhr });
					row.removeClass('vira-loading');
					showNotice('error', 'An error occurred. Please try again.');
				}
			});
		});
	}
	
	// Function to update statistics
	function updateStatistics() {
		$.ajax({
			url: viraCode.ajaxUrl,
			type: 'POST',
			data: {
				action: 'vira_code_get_statistics',
				nonce: viraCode.nonce,
			},
			success: function(statsResponse) {
				if (statsResponse.success && statsResponse.data.stats) {
					$('.vira-stat-value').each(function(index) {
						const statKey = ['total', 'active', 'inactive', 'error'][index];
						if (statsResponse.data.stats[statKey] !== undefined) {
							$(this).text(statsResponse.data.stats[statKey]);
						}
					});
				}
			}
		});
	}
	
	// Function to show notices
	function showNotice(type, message) {
		if (window.ViraCodeAdmin && typeof ViraCodeAdmin.showNotice === 'function') {
			ViraCodeAdmin.showNotice(type, message);
		} else {
			alert(type.charAt(0).toUpperCase() + type.slice(1) + ': ' + message);
		}
	}

	// Function to handle conditional logic preview
	function initConditionalLogicPreview() {
		// Bind click events for preview buttons
		$(document).on('click', '.vira-conditional-preview-btn', function(e) {
			e.preventDefault();
			e.stopPropagation();
			
			const button = $(this);
			const snippetId = button.data('snippet-id');
			const modal = $('#vira-conditional-preview-modal');
			const content = $('#vira-conditional-preview-content');
			
			console.log('[Vira Code] Preview clicked for snippet ID:', snippetId);
			
			// Show modal
			modal.show();
			
			// Show loading state
			content.html('<div class="vira-loading-spinner"><span class="dashicons dashicons-update-alt"></span> <?php echo esc_js(__("Loading rules...", "vira-code")); ?></div>');
			
			// Send AJAX request
			$.ajax({
				url: viraCode.ajaxUrl,
				type: 'POST',
				data: {
					action: 'vira_code_get_conditional_logic_preview',
					nonce: viraCode.nonce,
					snippet_id: snippetId,
				},
				success: function(response) {
					console.log('[Vira Code] Preview response:', response);
					
					if (response.success) {
						let html = '<div class="vira-conditional-rules-list">';
						
						if (response.data.rules && response.data.rules.length > 0) {
							let currentGroup = null;
							
							response.data.rules.forEach(function(rule, index) {
								// Add group separator if needed
								if (currentGroup !== null && currentGroup !== rule.group) {
									html += '<div class="vira-rule-group-separator">' + rule.logic + '</div>';
								}
								
								html += '<div class="vira-rule-item">';
								html += '<span class="vira-rule-type">' + rule.type + '</span> ';
								html += '<span class="vira-rule-operator">' + rule.operator + '</span> ';
								html += '<span class="vira-rule-value">"' + rule.value + '"</span>';
								html += '</div>';
								
								currentGroup = rule.group;
							});
						} else {
							html += '<p><?php echo esc_js(__("No rules found.", "vira-code")); ?></p>';
						}
						
						html += '</div>';
						content.html(html);
					} else {
						content.html('<div class="vira-error-message">' + (response.data.message || '<?php echo esc_js(__("Failed to load rules.", "vira-code")); ?>') + '</div>');
					}
				},
				error: function(xhr, status, error) {
					console.error('[Vira Code] AJAX error:', { status: status, error: error, xhr: xhr });
					content.html('<div class="vira-error-message"><?php echo esc_js(__("An error occurred while loading rules.", "vira-code")); ?></div>');
				}
			});
		});
		
		// Bind modal close events
		$(document).on('click', '.vira-modal-close, .vira-modal', function(e) {
			if (e.target === this) {
				$('#vira-conditional-preview-modal').hide();
			}
		});
		
		// Close modal on escape key
		$(document).on('keydown', function(e) {
			if (e.keyCode === 27) { // Escape key
				$('#vira-conditional-preview-modal').hide();
			}
		});
	}
	
	// Function to handle filtering and search
	function initFiltersAndSearch() {
		let searchTimeout;
		
		// Search functionality
		$('#vira-search-input').on('input', function() {
			clearTimeout(searchTimeout);
			searchTimeout = setTimeout(function() {
				applyFilters();
			}, 300);
		});
		
		// Filter change handlers
		$('#vira-filter-status, #vira-filter-type, #vira-filter-conditional').on('change', function() {
			applyFilters();
		});
		
		// Clear filters
		$('#vira-clear-filters').on('click', function() {
			$('#vira-search-input').val('');
			$('#vira-filter-status, #vira-filter-type, #vira-filter-conditional').val('');
			applyFilters();
		});
		
		// Apply filters function
		function applyFilters() {
			const searchTerm = $('#vira-search-input').val().toLowerCase();
			const statusFilter = $('#vira-filter-status').val();
			const typeFilter = $('#vira-filter-type').val();
			const conditionalFilter = $('#vira-filter-conditional').val();
			
			let visibleCount = 0;
			
			$('.vira-snippets-table tbody tr').each(function() {
				const row = $(this);
				const title = row.find('.column-title .row-title').text().toLowerCase();
				const description = row.find('.snippet-description').text().toLowerCase();
				const status = row.data('status');
				const type = row.data('type');
				const conditional = row.data('conditional');
				
				let show = true;
				
				// Search filter
				if (searchTerm && !title.includes(searchTerm) && !description.includes(searchTerm)) {
					show = false;
				}
				
				// Status filter
				if (statusFilter && status !== statusFilter) {
					show = false;
				}
				
				// Type filter
				if (typeFilter && type !== typeFilter) {
					show = false;
				}
				
				// Conditional logic filter
				if (conditionalFilter && conditional !== conditionalFilter) {
					show = false;
				}
				
				if (show) {
					row.show();
					visibleCount++;
				} else {
					row.hide();
				}
			});
			
			// Show/hide empty state
			if (visibleCount === 0) {
				$('.vira-snippets-table').hide();
				if ($('.vira-no-results').length === 0) {
					$('.vira-snippets-table').after('<div class="vira-no-results vira-empty-state"><h3><?php echo esc_js(__("No snippets match your filters", "vira-code")); ?></h3><p><?php echo esc_js(__("Try adjusting your search criteria or clearing the filters.", "vira-code")); ?></p></div>');
				}
			} else {
				$('.vira-snippets-table').show();
				$('.vira-no-results').remove();
			}
		}
	}
	
	// Function to handle bulk operations
	function initBulkOperations() {
		// Select all functionality
		$('#vira-bulk-select-all, #cb-select-all-1, #cb-select-all-2').on('change', function() {
			const isChecked = $(this).prop('checked');
			$('.snippet-checkbox:visible').prop('checked', isChecked);
			updateBulkActionButton();
		});
		
		// Individual checkbox change
		$(document).on('change', '.snippet-checkbox', function() {
			updateBulkActionButton();
			
			// Update select all checkboxes
			const totalVisible = $('.snippet-checkbox:visible').length;
			const totalChecked = $('.snippet-checkbox:visible:checked').length;
			const selectAllChecked = totalVisible > 0 && totalVisible === totalChecked;
			
			$('#vira-bulk-select-all, #cb-select-all-1, #cb-select-all-2').prop('checked', selectAllChecked);
		});
		
		// Apply bulk action
		$('#vira-apply-bulk-action').on('click', function() {
			const action = $('#vira-bulk-action').val();
			const selectedIds = $('.snippet-checkbox:checked').map(function() {
				return $(this).val();
			}).get();
			
			if (!action || selectedIds.length === 0) {
				return;
			}
			
			switch (action) {
				case 'delete_rules':
					handleBulkDeleteRules(selectedIds);
					break;
				case 'copy_rules':
					handleBulkCopyRules(selectedIds);
					break;
				case 'export_rules':
					handleBulkExportRules(selectedIds);
					break;
			}
		});
		
		function updateBulkActionButton() {
			const hasSelected = $('.snippet-checkbox:checked').length > 0;
			$('#vira-apply-bulk-action').prop('disabled', !hasSelected);
		}
		
		function handleBulkDeleteRules(snippetIds) {
			if (!confirm('<?php echo esc_js(__("Are you sure you want to delete conditional logic rules from the selected snippets?", "vira-code")); ?>')) {
				return;
			}
			
			$.ajax({
				url: viraCode.ajaxUrl,
				type: 'POST',
				data: {
					action: 'vira_code_bulk_conditional_operations',
					nonce: viraCode.nonce,
					operation: 'delete_rules',
					snippet_ids: snippetIds
				},
				success: function(response) {
					if (response.success) {
						showNotice('success', response.data.message);
						location.reload(); // Refresh to update indicators
					} else {
						showNotice('error', response.data.message || '<?php echo esc_js(__("Failed to delete rules.", "vira-code")); ?>');
					}
				},
				error: function() {
					showNotice('error', '<?php echo esc_js(__("An error occurred while deleting rules.", "vira-code")); ?>');
				}
			});
		}
		
		function handleBulkCopyRules(snippetIds) {
			const sourceId = prompt('<?php echo esc_js(__("Enter the ID of the snippet to copy rules from:", "vira-code")); ?>');
			if (!sourceId) return;
			
			$.ajax({
				url: viraCode.ajaxUrl,
				type: 'POST',
				data: {
					action: 'vira_code_bulk_conditional_operations',
					nonce: viraCode.nonce,
					operation: 'copy_rules',
					snippet_ids: snippetIds,
					source_snippet_id: sourceId
				},
				success: function(response) {
					if (response.success) {
						showNotice('success', response.data.message);
						location.reload(); // Refresh to update indicators
					} else {
						showNotice('error', response.data.message || '<?php echo esc_js(__("Failed to copy rules.", "vira-code")); ?>');
					}
				},
				error: function() {
					showNotice('error', '<?php echo esc_js(__("An error occurred while copying rules.", "vira-code")); ?>');
				}
			});
		}
		
		function handleBulkExportRules(snippetIds) {
			$.ajax({
				url: viraCode.ajaxUrl,
				type: 'POST',
				data: {
					action: 'vira_code_export_conditional_rules',
					nonce: viraCode.nonce,
					snippet_ids: snippetIds
				},
				success: function(response) {
					if (response.success) {
						// Create and download file
						const blob = new Blob([JSON.stringify(response.data.data, null, 2)], {
							type: 'application/json'
						});
						const url = window.URL.createObjectURL(blob);
						const a = document.createElement('a');
						a.href = url;
						a.download = response.data.filename;
						document.body.appendChild(a);
						a.click();
						document.body.removeChild(a);
						window.URL.revokeObjectURL(url);
						
						showNotice('success', '<?php echo esc_js(__("Rules exported successfully!", "vira-code")); ?>');
					} else {
						showNotice('error', response.data.message || '<?php echo esc_js(__("Failed to export rules.", "vira-code")); ?>');
					}
				},
				error: function() {
					showNotice('error', '<?php echo esc_js(__("An error occurred while exporting rules.", "vira-code")); ?>');
				}
			});
		}
	}
	
	// Initialize immediately
	initSnippetsList();
	initConditionalLogicPreview();
	initFiltersAndSearch();
	initBulkOperations();
	
})(jQuery);
</script>
