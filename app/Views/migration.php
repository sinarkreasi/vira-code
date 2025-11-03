<?php
/**
 * Migration View
 *
 * @package ViraCode
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ViraCode\Services\MigrationService;
use ViraCode\Services\SnippetManagerService;

$migration_service = new MigrationService();
$manager = new SnippetManagerService();
$status = $migration_service->getStatus();
$storage_stats = $manager->getStorageStats();
?>

<div class="wrap vira-code-admin vira-migration-page">
	<h1 class="wp-heading-inline">
		<?php esc_html_e( 'Storage Migration', 'vira-code' ); ?>
	</h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=vira-code' ) ); ?>" class="page-title-action">
		<?php esc_html_e( 'Back to Snippets', 'vira-code' ); ?>
	</a>
	<hr class="wp-header-end">

	<div class="vira-migration-container">
		
		<!-- Current Status -->
		<div class="vira-migration-section">
			<h2><?php esc_html_e( 'Current Storage Status', 'vira-code' ); ?></h2>
			
			<div class="vira-storage-overview">
				<div class="vira-storage-card vira-storage-<?php echo esc_attr( $status['current_mode'] ); ?>">
					<h3><?php esc_html_e( 'Active Storage Mode', 'vira-code' ); ?></h3>
					<div class="vira-storage-mode">
						<span class="vira-mode-badge vira-mode-<?php echo esc_attr( $status['current_mode'] ); ?>">
							<?php echo esc_html( ucfirst( $status['current_mode'] ) ); ?>
						</span>
					</div>
				</div>

				<div class="vira-storage-stats">
					<div class="vira-stat-item">
						<div class="vira-stat-label"><?php esc_html_e( 'Database Snippets', 'vira-code' ); ?></div>
						<div class="vira-stat-value"><?php echo esc_html( $status['database_count'] ); ?></div>
					</div>
					<div class="vira-stat-item">
						<div class="vira-stat-label"><?php esc_html_e( 'File Snippets', 'vira-code' ); ?></div>
						<div class="vira-stat-value"><?php echo esc_html( $status['file_count'] ); ?></div>
					</div>
					<div class="vira-stat-item">
						<div class="vira-stat-label"><?php esc_html_e( 'File Storage', 'vira-code' ); ?></div>
						<div class="vira-stat-value">
							<?php if ( $status['file_storage_available'] ) : ?>
								<span class="vira-status-available"><?php esc_html_e( 'Available', 'vira-code' ); ?></span>
							<?php else : ?>
								<span class="vira-status-unavailable"><?php esc_html_e( 'Unavailable', 'vira-code' ); ?></span>
							<?php endif; ?>
						</div>
					</div>
				</div>
			</div>

			<?php if ( ! $status['file_storage_available'] ) : ?>
				<div class="notice notice-warning">
					<p>
						<strong><?php esc_html_e( 'File Storage Not Available', 'vira-code' ); ?></strong><br>
						<?php esc_html_e( 'The wp-content directory is not writable or there was an error creating the vira-cache directory.', 'vira-code' ); ?>
						<?php esc_html_e( 'Please check your file permissions before attempting to migrate to file storage.', 'vira-code' ); ?>
					</p>
				</div>
			<?php endif; ?>
		</div>

		<!-- Migration Actions -->
		<div class="vira-migration-section">
			<h2><?php esc_html_e( 'Migration Actions', 'vira-code' ); ?></h2>

			<div class="vira-migration-actions">
				
				<!-- Migrate to Files -->
				<div class="vira-migration-card">
					<h3><?php esc_html_e( 'Migrate to File Storage', 'vira-code' ); ?></h3>
					<p><?php esc_html_e( 'Move all snippets from database to individual files for better performance and easier debugging.', 'vira-code' ); ?></p>
					
					<?php if ( $status['can_migrate_to_files'] ) : ?>
						<button type="button" class="button button-primary" id="vira-migrate-to-files">
							<?php esc_html_e( 'Migrate to Files', 'vira-code' ); ?>
							<span class="vira-count">(<?php echo esc_html( $status['database_count'] ); ?> snippets)</span>
						</button>
					<?php else : ?>
						<button type="button" class="button" disabled>
							<?php esc_html_e( 'Migration Not Available', 'vira-code' ); ?>
						</button>
						<p class="description">
							<?php if ( ! $status['file_storage_available'] ) : ?>
								<?php esc_html_e( 'File storage is not available.', 'vira-code' ); ?>
							<?php elseif ( 0 === $status['database_count'] ) : ?>
								<?php esc_html_e( 'No snippets found in database to migrate.', 'vira-code' ); ?>
							<?php endif; ?>
						</p>
					<?php endif; ?>
				</div>

				<!-- Migrate to Database -->
				<div class="vira-migration-card">
					<h3><?php esc_html_e( 'Migrate to Database', 'vira-code' ); ?></h3>
					<p><?php esc_html_e( 'Move all snippets from files back to the database for traditional storage.', 'vira-code' ); ?></p>
					
					<?php if ( $status['can_migrate_to_db'] ) : ?>
						<button type="button" class="button button-secondary" id="vira-migrate-to-database">
							<?php esc_html_e( 'Migrate to Database', 'vira-code' ); ?>
							<span class="vira-count">(<?php echo esc_html( $status['file_count'] ); ?> snippets)</span>
						</button>
					<?php else : ?>
						<button type="button" class="button" disabled>
							<?php esc_html_e( 'Migration Not Available', 'vira-code' ); ?>
						</button>
						<p class="description">
							<?php esc_html_e( 'No snippets found in file storage to migrate.', 'vira-code' ); ?>
						</p>
					<?php endif; ?>
				</div>

				<!-- Validate Migration -->
				<div class="vira-migration-card">
					<h3><?php esc_html_e( 'Validate Migration', 'vira-code' ); ?></h3>
					<p><?php esc_html_e( 'Check the integrity of migrated snippets by comparing database and file storage.', 'vira-code' ); ?></p>
					
					<button type="button" class="button" id="vira-validate-migration">
						<?php esc_html_e( 'Validate Migration', 'vira-code' ); ?>
					</button>
				</div>
			</div>
		</div>

		<!-- Storage Mode Switch -->
		<div class="vira-migration-section">
			<h2><?php esc_html_e( 'Storage Mode', 'vira-code' ); ?></h2>
			
			<div class="vira-storage-mode-switch">
				<p><?php esc_html_e( 'Switch between database and file storage modes. This determines which storage backend is used for new snippets and execution.', 'vira-code' ); ?></p>
				
				<div class="vira-mode-options">
					<label class="vira-mode-option">
						<input type="radio" name="storage_mode" value="database" <?php checked( $status['current_mode'], 'database' ); ?>>
						<span class="vira-mode-label">
							<strong><?php esc_html_e( 'Database Storage', 'vira-code' ); ?></strong>
							<span class="vira-mode-description"><?php esc_html_e( 'Traditional WordPress database storage', 'vira-code' ); ?></span>
						</span>
					</label>
					
					<label class="vira-mode-option">
						<input type="radio" name="storage_mode" value="file" <?php checked( $status['current_mode'], 'file' ); ?> <?php disabled( ! $status['file_storage_available'] ); ?>>
						<span class="vira-mode-label">
							<strong><?php esc_html_e( 'File Storage', 'vira-code' ); ?></strong>
							<span class="vira-mode-description"><?php esc_html_e( 'Individual files for better performance', 'vira-code' ); ?></span>
						</span>
					</label>
				</div>
				
				<button type="button" class="button button-primary" id="vira-switch-storage-mode">
					<?php esc_html_e( 'Switch Storage Mode', 'vira-code' ); ?>
				</button>
			</div>
		</div>

		<!-- Cleanup Actions -->
		<div class="vira-migration-section vira-danger-zone">
			<h2><?php esc_html_e( 'Cleanup Actions', 'vira-code' ); ?></h2>
			<p class="description"><?php esc_html_e( 'These actions permanently delete data. Use with caution!', 'vira-code' ); ?></p>
			
			<div class="vira-cleanup-actions">
				<div class="vira-cleanup-card">
					<h4><?php esc_html_e( 'Clean Database', 'vira-code' ); ?></h4>
					<p><?php esc_html_e( 'Remove migrated snippets from database after successful file migration.', 'vira-code' ); ?></p>
					<button type="button" class="button button-secondary" id="vira-cleanup-database">
						<?php esc_html_e( 'Clean Database', 'vira-code' ); ?>
					</button>
				</div>
				
				<div class="vira-cleanup-card">
					<h4><?php esc_html_e( 'Clean Files', 'vira-code' ); ?></h4>
					<p><?php esc_html_e( 'Remove all snippet files from file storage. after successful file migration.', 'vira-code' ); ?></p>
					<button type="button" class="button button-secondary" id="vira-cleanup-files">
						<?php esc_html_e( 'Clean Files', 'vira-code' ); ?>
					</button>
				</div>
				
				<div class="vira-cleanup-card">
					<h4><?php esc_html_e( 'Library Cleanup', 'vira-code' ); ?></h4>
					<p><?php esc_html_e( 'Advanced: Remove hardcoded library snippets after successful migration to file storage.', 'vira-code' ); ?></p>
					<button type="button" class="button button-secondary" id="vira-library-cleanup-info">
						<?php esc_html_e( 'View Cleanup Info', 'vira-code' ); ?>
					</button>
				</div>
			</div>
		</div>

		<!-- Migration Log -->
		<div class="vira-migration-section">
			<h2><?php esc_html_e( 'Migration Log', 'vira-code' ); ?></h2>
			<div id="vira-migration-log" class="vira-migration-log">
				<p class="vira-log-empty"><?php esc_html_e( 'No migration operations performed yet.', 'vira-code' ); ?></p>
			</div>
		</div>
	</div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
	const ajaxUrl = "<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>";
	const viraNonce = "<?php echo esc_js( wp_create_nonce( \ViraCode\vira_code_nonce_action() ) ); ?>";
	
	// Migration functions
	function performMigration(action, button) {
		const originalText = button.text();
		button.prop('disabled', true).text('<?php esc_html_e( 'Processing...', 'vira-code' ); ?>');
		
		clearLog();
		addLogEntry('info', '<?php esc_html_e( 'Starting migration...', 'vira-code' ); ?>');
		
		$.ajax({
			url: ajaxUrl,
			type: 'POST',
			data: {
				action: 'vira_code_migration',
				migration_action: action,
				nonce: viraNonce
			},
			success: function(response) {
				button.prop('disabled', false).text(originalText);
				
				if (response.success) {
					addLogEntry('success', response.data.message || '<?php esc_html_e( 'Migration completed successfully.', 'vira-code' ); ?>');
					
					// Display detailed log
					if (response.data.log && response.data.log.length > 0) {
						response.data.log.forEach(function(entry) {
							addLogEntry(entry.level, entry.message, entry.timestamp);
						});
					}
					
					// Show success stats
					if (response.data.migrated !== undefined) {
						addLogEntry('info', '<?php esc_html_e( 'Migrated:', 'vira-code' ); ?> ' + response.data.migrated + ' <?php esc_html_e( 'snippets', 'vira-code' ); ?>');
					}
					
					// Reload page after successful migration
					setTimeout(function() {
						location.reload();
					}, 2000);
				} else {
					addLogEntry('error', response.data.message || '<?php esc_html_e( 'Migration failed.', 'vira-code' ); ?>');
					
					// Display errors
					if (response.data.errors && response.data.errors.length > 0) {
						response.data.errors.forEach(function(error) {
							addLogEntry('error', error);
						});
					}
				}
			},
			error: function(xhr, status, error) {
				button.prop('disabled', false).text(originalText);
				addLogEntry('error', '<?php esc_html_e( 'AJAX error:', 'vira-code' ); ?> ' + error);
			}
		});
	}
	
	// Log management
	function clearLog() {
		$('#vira-migration-log').empty();
	}
	
	function addLogEntry(level, message, timestamp) {
		const logContainer = $('#vira-migration-log');
		const time = timestamp || new Date().toLocaleTimeString();
		const levelClass = 'vira-log-' + level;
		const levelIcon = getLogIcon(level);
		
		const entry = $('<div class="vira-log-entry ' + levelClass + '">' +
			'<span class="vira-log-time">' + time + '</span>' +
			'<span class="vira-log-icon">' + levelIcon + '</span>' +
			'<span class="vira-log-message">' + message + '</span>' +
		'</div>');
		
		logContainer.append(entry);
		logContainer.scrollTop(logContainer[0].scrollHeight);
	}
	
	function getLogIcon(level) {
		const icons = {
			'info': '&#8505;',
			'success': '&#10004;',
			'warning': '&#9888;',
			'error': '&#10006;'
		};
		return icons[level] || '&#8226;';
	}
	
	// Event handlers
	$('#vira-migrate-to-files').on('click', function() {
		if (confirm('<?php esc_html_e( 'Are you sure you want to migrate all snippets to file storage?', 'vira-code' ); ?>')) {
			performMigration('migrate_to_files', $(this));
		}
	});
	
	$('#vira-migrate-to-database').on('click', function() {
		if (confirm('<?php esc_html_e( 'Are you sure you want to migrate all snippets to database storage?', 'vira-code' ); ?>')) {
			performMigration('migrate_to_database', $(this));
		}
	});
	
	$('#vira-validate-migration').on('click', function() {
		performMigration('validate_migration', $(this));
	});
	
	$('#vira-cleanup-database').on('click', function() {
		if (confirm('<?php esc_html_e( 'Are you sure you want to permanently delete migrated snippets from the database? This cannot be undone!', 'vira-code' ); ?>')) {
			performMigration('cleanup_database', $(this));
		}
	});
	
	$('#vira-cleanup-files').on('click', function() {
		if (confirm('<?php esc_html_e( 'Are you sure you want to permanently delete all snippet files? This cannot be undone!', 'vira-code' ); ?>')) {
			performMigration('cleanup_files', $(this));
		}
	});
	
	// Storage mode switch
	$('#vira-switch-storage-mode').on('click', function() {
		const selectedMode = $('input[name="storage_mode"]:checked').val();
		const currentMode = '<?php echo esc_js( $status['current_mode'] ); ?>';
		
		if (selectedMode === currentMode) {
			alert('<?php esc_html_e( 'The selected storage mode is already active.', 'vira-code' ); ?>');
			return;
		}
		
		const button = $(this);
		const originalText = button.text();
		button.prop('disabled', true).text('<?php esc_html_e( 'Switching...', 'vira-code' ); ?>');
		
		$.ajax({
			url: ajaxUrl,
			type: 'POST',
			data: {
				action: 'vira_code_switch_storage_mode',
				storage_mode: selectedMode,
				nonce: viraNonce
			},
			success: function(response) {
				button.prop('disabled', false).text(originalText);
				
				if (response.success) {
					addLogEntry('success', response.data.message || '<?php esc_html_e( 'Storage mode switched successfully.', 'vira-code' ); ?>');
					setTimeout(function() {
						location.reload();
					}, 1000);
				} else {
					addLogEntry('error', response.data.message || '<?php esc_html_e( 'Failed to switch storage mode.', 'vira-code' ); ?>');
				}
			},
			error: function() {
				button.prop('disabled', false).text(originalText);
				addLogEntry('error', '<?php esc_html_e( 'An error occurred while switching storage mode.', 'vira-code' ); ?>');
			}
		});
	});
	
	// Library cleanup info
	$('#vira-library-cleanup-info').on('click', function() {
		const button = $(this);
		const originalText = button.text();
		button.prop('disabled', true).text('<?php esc_html_e( 'Loading...', 'vira-code' ); ?>');
		
		$.ajax({
			url: ajaxUrl,
			type: 'POST',
			data: {
				action: 'vira_code_get_library_cleanup_info',
				nonce: viraNonce
			},
			success: function(response) {
				button.prop('disabled', false).text(originalText);
				
				if (response.success) {
					showLibraryCleanupModal(response.data);
				} else {
					alert(response.data.message || '<?php esc_html_e( 'Failed to load cleanup information.', 'vira-code' ); ?>');
				}
			},
			error: function() {
				button.prop('disabled', false).text(originalText);
				alert('<?php esc_html_e( 'An error occurred while loading cleanup information.', 'vira-code' ); ?>');
			}
		});
	});
	
	// Show library cleanup modal
	function showLibraryCleanupModal(data) {
		const modal = $('<div class="vira-modal" id="vira-cleanup-modal">' +
			'<div class="vira-modal-overlay"></div>' +
			'<div class="vira-modal-content">' +
				'<div class="vira-modal-header">' +
					'<h3><?php esc_html_e( 'Library Cleanup Information', 'vira-code' ); ?></h3>' +
					'<button type="button" class="vira-modal-close">' +
						'<span class="dashicons dashicons-no-alt"></span>' +
					'</button>' +
				'</div>' +
				'<div class="vira-modal-body">' +
					'<div class="vira-cleanup-status">' +
						'<h4><?php esc_html_e( 'Cleanup Status', 'vira-code' ); ?></h4>' +
						'<div class="vira-status-indicator vira-status-' + (data.cleanup_check.safe ? 'safe' : 'unsafe') + '">' +
							'<span class="dashicons dashicons-' + (data.cleanup_check.safe ? 'yes-alt' : 'warning') + '"></span>' +
							'<span>' + data.cleanup_check.reason + '</span>' +
						'</div>' +
						'<div class="vira-cleanup-stats">' +
							'<div class="vira-stat-row">' +
								'<span><?php esc_html_e( 'File Snippets:', 'vira-code' ); ?></span>' +
								'<span>' + data.cleanup_check.file_snippets_count + '</span>' +
							'</div>' +
							'<div class="vira-stat-row">' +
								'<span><?php esc_html_e( 'Hardcoded Snippets:', 'vira-code' ); ?></span>' +
								'<span>' + data.cleanup_check.hardcoded_snippets_count + '</span>' +
							'</div>' +
						'</div>' +
					'</div>' +
					'<div class="vira-cleanup-recommendations">' +
						'<h4><?php esc_html_e( 'Recommendations', 'vira-code' ); ?></h4>' +
						buildRecommendationsList(data.recommendations) +
					'</div>' +
					'<div class="vira-cleanup-instructions">' +
						'<h4>' + data.instructions.title + '</h4>' +
						'<p>' + data.instructions.description + '</p>' +
						'<ol>' +
							data.instructions.steps.map(step => '<li>' + step + '</li>').join('') +
						'</ol>' +
						'<div class="notice notice-warning inline">' +
							'<p><strong><?php esc_html_e( 'Warning:', 'vira-code' ); ?></strong> ' + data.instructions.warning + '</p>' +
						'</div>' +
					'</div>' +
				'</div>' +
				'<div class="vira-modal-footer">' +
					'<button type="button" class="button button-secondary vira-modal-close"><?php esc_html_e( 'Close', 'vira-code' ); ?></button>' +
				'</div>' +
			'</div>' +
		'</div>');
		
		$('body').append(modal);
		
		// Close modal handlers
		modal.find('.vira-modal-close, .vira-modal-overlay').on('click', function() {
			modal.remove();
		});
		
		// Prevent modal content clicks from closing modal
		modal.find('.vira-modal-content').on('click', function(e) {
			e.stopPropagation();
		});
	}
	
	// Build recommendations list
	function buildRecommendationsList(recommendations) {
		let html = '<div class="vira-recommendations-list">';
		recommendations.forEach(function(rec) {
			html += '<div class="vira-cleanup-recommendation recommendation-' + rec.type + '">' +
				'<span class="dashicons dashicons-' + getRecommendationIcon(rec.type) + '"></span>' +
				'<span>' + rec.message + '</span>' +
			'</div>';
		});
		html += '</div>';
		return html;
	}
	
	// Get recommendation icon
	function getRecommendationIcon(type) {
		const icons = {
			'success': 'yes-alt',
			'warning': 'warning',
			'error': 'dismiss',
			'info': 'info'
		};
		return icons[type] || 'info';
	}
});
</script>