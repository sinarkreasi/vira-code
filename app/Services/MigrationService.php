<?php
/**
 * Migration Service
 *
 * @package ViraCode
 */

namespace ViraCode\Services;

use ViraCode\Models\SnippetModel;
use ViraCode\Services\FileStorageService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MigrationService Class
 *
 * Handles migration between database and file storage.
 */
class MigrationService {

	/**
	 * Snippet model instance.
	 *
	 * @var SnippetModel
	 */
	protected $model;

	/**
	 * File storage service instance.
	 *
	 * @var FileStorageService
	 */
	protected $storage;

	/**
	 * Migration log.
	 *
	 * @var array
	 */
	protected $log = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->model = new SnippetModel();
		$this->storage = new FileStorageService();
	}

	/**
	 * Migrate all snippets from database to file storage.
	 *
	 * @return array Migration result.
	 */
	public function migrateToFiles() {
		$this->log = array();
		$result = array(
			'success'   => false,
			'migrated'  => 0,
			'failed'    => 0,
			'errors'    => array(),
			'log'       => array(),
		);

		// Check if file storage is available.
		if ( ! $this->storage->isAvailable() ) {
			$result['errors'][] = __( 'File storage is not available. Check directory permissions.', 'vira-code' );
			return $result;
		}

		// Get all snippets from database.
		$db_snippets = $this->model->getAll();

		if ( empty( $db_snippets ) ) {
			$this->addLog( 'info', __( 'No snippets found in database to migrate.', 'vira-code' ) );
			$result['success'] = true;
			$result['log'] = $this->log;
			return $result;
		}

		$this->addLog( 'info', sprintf( __( 'Starting migration of %d snippets to file storage.', 'vira-code' ), count( $db_snippets ) ) );

		foreach ( $db_snippets as $db_snippet ) {
			try {
				// Migrate snippet to file storage.
				$file_id = $this->storage->migrateFromDatabase( $db_snippet );

				if ( false !== $file_id ) {
					$result['migrated']++;
					$this->addLog( 'success', sprintf( __( 'Migrated snippet #%d: %s', 'vira-code' ), $db_snippet['id'], $db_snippet['title'] ) );

					// Mark as migrated in database (optional - for tracking).
					$this->model->update( $db_snippet['id'], array( 'status' => 'migrated' ) );
				} else {
					$result['failed']++;
					$error_msg = sprintf( __( 'Failed to migrate snippet #%d: %s', 'vira-code' ), $db_snippet['id'], $db_snippet['title'] );
					$result['errors'][] = $error_msg;
					$this->addLog( 'error', $error_msg );
				}
			} catch ( \Exception $e ) {
				$result['failed']++;
				$error_msg = sprintf( __( 'Exception migrating snippet #%d: %s', 'vira-code' ), $db_snippet['id'], $e->getMessage() );
				$result['errors'][] = $error_msg;
				$this->addLog( 'error', $error_msg );
			}
		}

		$result['success'] = $result['failed'] === 0;
		$result['log'] = $this->log;

		$this->addLog( 'info', sprintf( __( 'Migration completed. %d migrated, %d failed.', 'vira-code' ), $result['migrated'], $result['failed'] ) );

		// Update storage mode setting.
		if ( $result['success'] ) {
			update_option( 'vira_code_storage_mode', 'file' );
			$this->addLog( 'info', __( 'Storage mode updated to file-based.', 'vira-code' ) );
		}

		do_action( 'vira_code/migration_to_files_completed', $result );

		return $result;
	}

	/**
	 * Migrate all snippets from file storage to database.
	 *
	 * @return array Migration result.
	 */
	public function migrateToDatabase() {
		$this->log = array();
		$result = array(
			'success'   => false,
			'migrated'  => 0,
			'failed'    => 0,
			'errors'    => array(),
			'log'       => array(),
		);

		// Get all snippets from file storage.
		$file_snippets = $this->storage->getAll();

		if ( empty( $file_snippets ) ) {
			$this->addLog( 'info', __( 'No snippets found in file storage to migrate.', 'vira-code' ) );
			$result['success'] = true;
			$result['log'] = $this->log;
			return $result;
		}

		$this->addLog( 'info', sprintf( __( 'Starting migration of %d snippets to database.', 'vira-code' ), count( $file_snippets ) ) );

		foreach ( $file_snippets as $file_snippet ) {
			try {
				// Prepare data for database.
				$db_data = array(
					'title'       => $file_snippet['title'],
					'description' => $file_snippet['description'],
					'code'        => $file_snippet['code'],
					'type'        => $file_snippet['type'],
					'scope'       => $file_snippet['scope'],
					'status'      => $file_snippet['status'],
					'tags'        => isset( $file_snippet['tags'] ) ? $file_snippet['tags'] : '',
					'category'    => isset( $file_snippet['category'] ) ? $file_snippet['category'] : '',
					'priority'    => isset( $file_snippet['priority'] ) ? $file_snippet['priority'] : 10,
					'created_at'  => $file_snippet['created_at'],
					'updated_at'  => $file_snippet['updated_at'],
				);

				// Check if snippet already exists in database.
				$existing = $this->model->getById( $file_snippet['id'] );
				if ( $existing ) {
					// Update existing snippet.
					$success = $this->model->update( $file_snippet['id'], $db_data );
					$action = 'updated';
				} else {
					// Create new snippet.
					$success = $this->model->create( $db_data );
					$action = 'created';
				}

				if ( $success ) {
					$result['migrated']++;
					$this->addLog( 'success', sprintf( __( 'Migrated snippet #%d: %s (%s)', 'vira-code' ), $file_snippet['id'], $file_snippet['title'], $action ) );
				} else {
					$result['failed']++;
					$error_msg = sprintf( __( 'Failed to migrate snippet #%d: %s', 'vira-code' ), $file_snippet['id'], $file_snippet['title'] );
					$result['errors'][] = $error_msg;
					$this->addLog( 'error', $error_msg );
				}
			} catch ( \Exception $e ) {
				$result['failed']++;
				$error_msg = sprintf( __( 'Exception migrating snippet #%d: %s', 'vira-code' ), $file_snippet['id'], $e->getMessage() );
				$result['errors'][] = $error_msg;
				$this->addLog( 'error', $error_msg );
			}
		}

		$result['success'] = $result['failed'] === 0;
		$result['log'] = $this->log;

		$this->addLog( 'info', sprintf( __( 'Migration completed. %d migrated, %d failed.', 'vira-code' ), $result['migrated'], $result['failed'] ) );

		// Update storage mode setting.
		if ( $result['success'] ) {
			update_option( 'vira_code_storage_mode', 'database' );
			$this->addLog( 'info', __( 'Storage mode updated to database.', 'vira-code' ) );
		}

		do_action( 'vira_code/migration_to_database_completed', $result );

		return $result;
	}

	/**
	 * Get migration status.
	 *
	 * @return array
	 */
	public function getStatus() {
		$db_count = $this->model->count();
		$file_count = $this->storage->count();
		$current_mode = get_option( 'vira_code_storage_mode', 'database' );

		return array(
			'current_mode'        => $current_mode,
			'database_count'      => $db_count,
			'file_count'          => $file_count,
			'file_storage_available' => $this->storage->isAvailable(),
			'can_migrate_to_files'   => $db_count > 0 && $this->storage->isAvailable(),
			'can_migrate_to_db'      => $file_count > 0,
		);
	}

	/**
	 * Clean up migrated snippets from database.
	 *
	 * @return array Cleanup result.
	 */
	public function cleanupDatabase() {
		$this->log = array();
		$result = array(
			'success' => false,
			'deleted' => 0,
			'errors'  => array(),
			'log'     => array(),
		);

		// Get all migrated snippets.
		$migrated_snippets = $this->model->getAll( array( 'status' => 'migrated' ) );

		if ( empty( $migrated_snippets ) ) {
			$this->addLog( 'info', __( 'No migrated snippets found to clean up.', 'vira-code' ) );
			$result['success'] = true;
			$result['log'] = $this->log;
			return $result;
		}

		$this->addLog( 'info', sprintf( __( 'Starting cleanup of %d migrated snippets from database.', 'vira-code' ), count( $migrated_snippets ) ) );

		foreach ( $migrated_snippets as $snippet ) {
			try {
				// Verify snippet exists in file storage before deleting from database.
				$file_snippet = $this->storage->getById( $snippet['id'] );
				if ( $file_snippet ) {
					$success = $this->model->delete( $snippet['id'] );
					if ( $success ) {
						$result['deleted']++;
						$this->addLog( 'success', sprintf( __( 'Deleted migrated snippet #%d from database: %s', 'vira-code' ), $snippet['id'], $snippet['title'] ) );
					} else {
						$error_msg = sprintf( __( 'Failed to delete snippet #%d from database: %s', 'vira-code' ), $snippet['id'], $snippet['title'] );
						$result['errors'][] = $error_msg;
						$this->addLog( 'error', $error_msg );
					}
				} else {
					$error_msg = sprintf( __( 'Snippet #%d not found in file storage, skipping deletion: %s', 'vira-code' ), $snippet['id'], $snippet['title'] );
					$result['errors'][] = $error_msg;
					$this->addLog( 'warning', $error_msg );
				}
			} catch ( \Exception $e ) {
				$error_msg = sprintf( __( 'Exception cleaning up snippet #%d: %s', 'vira-code' ), $snippet['id'], $e->getMessage() );
				$result['errors'][] = $error_msg;
				$this->addLog( 'error', $error_msg );
			}
		}

		$result['success'] = empty( $result['errors'] );
		$result['log'] = $this->log;

		$this->addLog( 'info', sprintf( __( 'Cleanup completed. %d snippets deleted.', 'vira-code' ), $result['deleted'] ) );

		do_action( 'vira_code/database_cleanup_completed', $result );

		return $result;
	}

	/**
	 * Clean up file storage.
	 *
	 * @return array Cleanup result.
	 */
	public function cleanupFiles() {
		$this->log = array();
		$result = array(
			'success' => false,
			'deleted' => 0,
			'errors'  => array(),
			'log'     => array(),
		);

		if ( ! $this->storage->isAvailable() ) {
			$result['errors'][] = __( 'File storage is not available.', 'vira-code' );
			return $result;
		}

		// Get all file snippets.
		$file_snippets = $this->storage->getAll();

		if ( empty( $file_snippets ) ) {
			$this->addLog( 'info', __( 'No file snippets found to clean up.', 'vira-code' ) );
			$result['success'] = true;
			$result['log'] = $this->log;
			return $result;
		}

		$this->addLog( 'info', sprintf( __( 'Starting cleanup of %d snippets from file storage.', 'vira-code' ), count( $file_snippets ) ) );

		foreach ( $file_snippets as $snippet ) {
			try {
				$success = $this->storage->delete( $snippet['id'] );
				if ( $success ) {
					$result['deleted']++;
					$this->addLog( 'success', sprintf( __( 'Deleted snippet #%d from file storage: %s', 'vira-code' ), $snippet['id'], $snippet['title'] ) );
				} else {
					$error_msg = sprintf( __( 'Failed to delete snippet #%d from file storage: %s', 'vira-code' ), $snippet['id'], $snippet['title'] );
					$result['errors'][] = $error_msg;
					$this->addLog( 'error', $error_msg );
				}
			} catch ( \Exception $e ) {
				$error_msg = sprintf( __( 'Exception cleaning up snippet #%d: %s', 'vira-code' ), $snippet['id'], $e->getMessage() );
				$result['errors'][] = $error_msg;
				$this->addLog( 'error', $error_msg );
			}
		}

		$result['success'] = empty( $result['errors'] );
		$result['log'] = $this->log;

		$this->addLog( 'info', sprintf( __( 'File cleanup completed. %d snippets deleted.', 'vira-code' ), $result['deleted'] ) );

		do_action( 'vira_code/file_cleanup_completed', $result );

		return $result;
	}

	/**
	 * Validate migration integrity.
	 *
	 * @return array Validation result.
	 */
	public function validateMigration() {
		$this->log = array();
		$result = array(
			'success'    => true,
			'validated'  => 0,
			'mismatches' => 0,
			'errors'     => array(),
			'log'        => array(),
		);

		// Get snippets from both storages.
		$db_snippets = $this->model->getAll();
		$file_snippets = $this->storage->getAll();

		$this->addLog( 'info', sprintf( __( 'Validating migration integrity. DB: %d, Files: %d', 'vira-code' ), count( $db_snippets ), count( $file_snippets ) ) );

		// Create lookup arrays.
		$db_lookup = array();
		foreach ( $db_snippets as $snippet ) {
			$db_lookup[ $snippet['id'] ] = $snippet;
		}

		$file_lookup = array();
		foreach ( $file_snippets as $snippet ) {
			$file_lookup[ $snippet['id'] ] = $snippet;
		}

		// Check file snippets against database.
		foreach ( $file_snippets as $file_snippet ) {
			$id = $file_snippet['id'];
			$result['validated']++;

			if ( ! isset( $db_lookup[ $id ] ) ) {
				$this->addLog( 'warning', sprintf( __( 'Snippet #%d exists in files but not in database: %s', 'vira-code' ), $id, $file_snippet['title'] ) );
				continue;
			}

			$db_snippet = $db_lookup[ $id ];

			// Compare key fields.
			$fields_to_compare = array( 'title', 'type', 'scope', 'status', 'code' );
			foreach ( $fields_to_compare as $field ) {
				if ( isset( $file_snippet[ $field ] ) && isset( $db_snippet[ $field ] ) ) {
					if ( $file_snippet[ $field ] !== $db_snippet[ $field ] ) {
						$result['mismatches']++;
						$error_msg = sprintf( __( 'Mismatch in snippet #%d field "%s": File="%s", DB="%s"', 'vira-code' ), $id, $field, $file_snippet[ $field ], $db_snippet[ $field ] );
						$result['errors'][] = $error_msg;
						$this->addLog( 'error', $error_msg );
					}
				}
			}
		}

		$result['success'] = $result['mismatches'] === 0;
		$result['log'] = $this->log;

		$this->addLog( 'info', sprintf( __( 'Validation completed. %d validated, %d mismatches found.', 'vira-code' ), $result['validated'], $result['mismatches'] ) );

		return $result;
	}

	/**
	 * Add entry to migration log.
	 *
	 * @param string $level   Log level (info, success, warning, error).
	 * @param string $message Log message.
	 * @return void
	 */
	protected function addLog( $level, $message ) {
		$this->log[] = array(
			'level'     => $level,
			'message'   => $message,
			'timestamp' => current_time( 'mysql' ),
		);
	}

	/**
	 * Get migration log.
	 *
	 * @return array
	 */
	public function getLog() {
		return $this->log;
	}

	/**
	 * Clear migration log.
	 *
	 * @return void
	 */
	public function clearLog() {
		$this->log = array();
	}
}