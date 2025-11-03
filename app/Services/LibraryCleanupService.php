<?php
/**
 * Library Cleanup Service
 *
 * @package ViraCode
 */

namespace ViraCode\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * LibraryCleanupService Class
 *
 * Safely manages cleanup of hardcoded library snippets after migration.
 */
class LibraryCleanupService {

	/**
	 * Library file storage service instance.
	 *
	 * @var LibraryFileStorageService
	 */
	protected $library_storage;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->library_storage = new LibraryFileStorageService();
	}

	/**
	 * Check if cleanup is safe to perform.
	 *
	 * @return array
	 */
	public function canCleanup() {
		$result = array(
			'safe' => false,
			'reason' => '',
			'file_snippets_count' => 0,
			'hardcoded_snippets_count' => 0,
		);

		// Check if file storage is available.
		if ( ! $this->library_storage->isAvailable() ) {
			$result['reason'] = __( 'File storage is not available.', 'vira-code' );
			return $result;
		}

		// Get file-based snippets.
		$file_snippets = $this->library_storage->getAllSnippets();
		$result['file_snippets_count'] = count( $file_snippets );

		// Check if we have file-based snippets.
		if ( empty( $file_snippets ) ) {
			$result['reason'] = __( 'No file-based snippets found. Migration may not have completed successfully.', 'vira-code' );
			return $result;
		}

		// Get hardcoded snippets count (simulate).
		$admin_controller = new \ViraCode\Http\Controllers\AdminController();
		$hardcoded_snippets = $admin_controller->getHardcodedLibrarySnippets();
		$result['hardcoded_snippets_count'] = count( $hardcoded_snippets );

		// Check if counts match (basic integrity check).
		if ( $result['file_snippets_count'] < $result['hardcoded_snippets_count'] ) {
			$result['reason'] = sprintf(
				/* translators: 1: file count, 2: hardcoded count */
				__( 'File storage has fewer snippets (%1$d) than hardcoded (%2$d). Migration may be incomplete.', 'vira-code' ),
				$result['file_snippets_count'],
				$result['hardcoded_snippets_count']
			);
			return $result;
		}

		// All checks passed.
		$result['safe'] = true;
		$result['reason'] = __( 'Cleanup is safe to perform.', 'vira-code' );

		return $result;
	}

	/**
	 * Get cleanup recommendations.
	 *
	 * @return array
	 */
	public function getRecommendations() {
		$cleanup_check = $this->canCleanup();
		$recommendations = array();

		if ( $cleanup_check['safe'] ) {
			$recommendations[] = array(
				'type' => 'success',
				'message' => __( 'Migration appears successful. Hardcoded snippets can be safely removed.', 'vira-code' ),
			);
			$recommendations[] = array(
				'type' => 'info',
				'message' => __( 'Removing hardcoded snippets will reduce plugin file size and improve performance.', 'vira-code' ),
			);
			$recommendations[] = array(
				'type' => 'warning',
				'message' => __( 'Always backup your site before performing cleanup operations.', 'vira-code' ),
			);
		} else {
			$recommendations[] = array(
				'type' => 'error',
				'message' => $cleanup_check['reason'],
			);
			$recommendations[] = array(
				'type' => 'info',
				'message' => __( 'Complete the migration to file storage before attempting cleanup.', 'vira-code' ),
			);
		}

		return $recommendations;
	}

	/**
	 * Create a backup of hardcoded snippets before cleanup.
	 *
	 * @return array
	 */
	public function createBackup() {
		$admin_controller = new \ViraCode\Http\Controllers\AdminController();
		$hardcoded_snippets = $admin_controller->getHardcodedLibrarySnippets();

		$backup_data = array(
			'created_at' => current_time( 'mysql' ),
			'plugin_version' => VIRA_CODE_VERSION,
			'snippets_count' => count( $hardcoded_snippets ),
			'snippets' => $hardcoded_snippets,
		);

		// Save backup to uploads directory.
		$upload_dir = wp_upload_dir();
		$backup_dir = $upload_dir['basedir'] . '/vira-code-backups/';

		if ( ! wp_mkdir_p( $backup_dir ) ) {
			return array(
				'success' => false,
				'message' => __( 'Could not create backup directory.', 'vira-code' ),
			);
		}

		$backup_filename = 'hardcoded-library-backup-' . date( 'Y-m-d-H-i-s' ) . '.json';
		$backup_path = $backup_dir . $backup_filename;

		$backup_content = wp_json_encode( $backup_data, JSON_PRETTY_PRINT );
		$result = file_put_contents( $backup_path, $backup_content, LOCK_EX );

		if ( false === $result ) {
			return array(
				'success' => false,
				'message' => __( 'Could not write backup file.', 'vira-code' ),
			);
		}

		return array(
			'success' => true,
			'message' => sprintf(
				/* translators: %s: backup filename */
				__( 'Backup created successfully: %s', 'vira-code' ),
				$backup_filename
			),
			'backup_path' => $backup_path,
			'backup_filename' => $backup_filename,
		);
	}

	/**
	 * Get cleanup instructions for manual removal.
	 *
	 * @return array
	 */
	public function getCleanupInstructions() {
		return array(
			'title' => __( 'Manual Cleanup Instructions', 'vira-code' ),
			'description' => __( 'To safely remove hardcoded library snippets, follow these steps:', 'vira-code' ),
			'steps' => array(
				__( '1. Ensure file-based library is working correctly', 'vira-code' ),
				__( '2. Create a full site backup', 'vira-code' ),
				__( '3. Create a plugin backup using the backup function', 'vira-code' ),
				__( '4. Edit app/Http/Controllers/AdminController.php', 'vira-code' ),
				__( '5. Remove or comment out the getHardcodedLibrarySnippets() method', 'vira-code' ),
				__( '6. Update getLibrarySnippets() to only use file storage', 'vira-code' ),
				__( '7. Test the library functionality thoroughly', 'vira-code' ),
				__( '8. If issues occur, restore from backup', 'vira-code' ),
			),
			'warning' => __( 'This is an advanced operation. Only proceed if you are comfortable editing PHP files.', 'vira-code' ),
		);
	}

	/**
	 * Validate file storage integrity.
	 *
	 * @return array
	 */
	public function validateFileStorage() {
		$result = array(
			'valid' => false,
			'issues' => array(),
			'stats' => array(),
		);

		// Check if file storage is available.
		if ( ! $this->library_storage->isAvailable() ) {
			$result['issues'][] = __( 'File storage is not available.', 'vira-code' );
			return $result;
		}

		// Get file storage stats.
		$stats = $this->library_storage->getStats();
		$result['stats'] = $stats;

		// Check if directory exists and is writable.
		if ( ! $stats['storage_available'] ) {
			$result['issues'][] = __( 'Storage directory is not writable.', 'vira-code' );
		}

		// Check if we have snippets.
		if ( $stats['total_snippets'] === 0 ) {
			$result['issues'][] = __( 'No snippets found in file storage.', 'vira-code' );
		}

		// Check if we have categories.
		if ( $stats['categories'] === 0 ) {
			$result['issues'][] = __( 'No categories found in file storage.', 'vira-code' );
		}

		// Validate individual snippet files.
		$categories = $this->library_storage->scanLibrary();
		foreach ( $categories as $category_name => $category_snippets ) {
			foreach ( $category_snippets as $snippet ) {
				// Check if required fields exist.
				$required_fields = array( 'name', 'type', 'code', 'description' );
				foreach ( $required_fields as $field ) {
					if ( empty( $snippet[ $field ] ) ) {
						$result['issues'][] = sprintf(
							/* translators: 1: field name, 2: snippet name, 3: category name */
							__( 'Missing %1$s in snippet "%2$s" (category: %3$s)', 'vira-code' ),
							$field,
							$snippet['name'] ?? 'Unknown',
							$category_name
						);
					}
				}
			}
		}

		// Set valid flag.
		$result['valid'] = empty( $result['issues'] );

		return $result;
	}
}