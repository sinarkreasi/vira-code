<?php
/**
 * Migration Controller
 *
 * @package ViraCode
 */

namespace ViraCode\Http;

use ViraCode\Services\MigrationService;
use ViraCode\Services\SnippetManagerService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MigrationController Class
 *
 * Handles AJAX requests for migration operations.
 */
class MigrationController {

	/**
	 * Migration service instance.
	 *
	 * @var MigrationService
	 */
	protected $migration_service;

	/**
	 * Snippet manager service instance.
	 *
	 * @var SnippetManagerService
	 */
	protected $manager;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->migration_service = new MigrationService();
		$this->manager = new SnippetManagerService();
	}

	/**
	 * Handle migration AJAX requests.
	 *
	 * @return void
	 */
	public function handleMigration() {
		// Verify nonce.
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', \ViraCode\vira_code_nonce_action() ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid nonce.', 'vira-code' ) ) );
		}

		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'vira-code' ) ) );
		}

		$action = sanitize_text_field( $_POST['migration_action'] ?? '' );

		switch ( $action ) {
			case 'migrate_to_files':
				$this->migrateToFiles();
				break;

			case 'migrate_to_database':
				$this->migrateToDatabase();
				break;

			case 'validate_migration':
				$this->validateMigration();
				break;

			case 'cleanup_database':
				$this->cleanupDatabase();
				break;

			case 'cleanup_files':
				$this->cleanupFiles();
				break;

			default:
				wp_send_json_error( array( 'message' => __( 'Invalid migration action.', 'vira-code' ) ) );
		}
	}

	/**
	 * Handle storage mode switch AJAX requests.
	 *
	 * @return void
	 */
	public function handleStorageModeSwitch() {
		// Verify nonce.
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', \ViraCode\vira_code_nonce_action() ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid nonce.', 'vira-code' ) ) );
		}

		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'vira-code' ) ) );
		}

		$storage_mode = sanitize_text_field( $_POST['storage_mode'] ?? '' );

		if ( ! in_array( $storage_mode, array( 'database', 'file' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid storage mode.', 'vira-code' ) ) );
		}

		$success = $this->manager->switchStorageMode( $storage_mode );

		if ( $success ) {
			wp_send_json_success( array(
				'message' => sprintf(
					/* translators: %s: storage mode */
					__( 'Storage mode switched to %s successfully.', 'vira-code' ),
					$storage_mode
				),
				'storage_mode' => $storage_mode,
			) );
		} else {
			wp_send_json_error( array(
				'message' => __( 'Failed to switch storage mode. File storage may not be available.', 'vira-code' ),
			) );
		}
	}

	/**
	 * Migrate snippets to file storage.
	 *
	 * @return void
	 */
	protected function migrateToFiles() {
		$result = $this->migration_service->migrateToFiles();

		if ( $result['success'] ) {
			wp_send_json_success( array(
				'message'  => sprintf(
					/* translators: %d: number of migrated snippets */
					__( 'Successfully migrated %d snippets to file storage.', 'vira-code' ),
					$result['migrated']
				),
				'migrated' => $result['migrated'],
				'failed'   => $result['failed'],
				'log'      => $result['log'],
			) );
		} else {
			wp_send_json_error( array(
				'message' => sprintf(
					/* translators: 1: migrated count, 2: failed count */
					__( 'Migration completed with errors. %1$d migrated, %2$d failed.', 'vira-code' ),
					$result['migrated'],
					$result['failed']
				),
				'migrated' => $result['migrated'],
				'failed'   => $result['failed'],
				'errors'   => $result['errors'],
				'log'      => $result['log'],
			) );
		}
	}

	/**
	 * Migrate snippets to database storage.
	 *
	 * @return void
	 */
	protected function migrateToDatabase() {
		$result = $this->migration_service->migrateToDatabase();

		if ( $result['success'] ) {
			wp_send_json_success( array(
				'message'  => sprintf(
					/* translators: %d: number of migrated snippets */
					__( 'Successfully migrated %d snippets to database storage.', 'vira-code' ),
					$result['migrated']
				),
				'migrated' => $result['migrated'],
				'failed'   => $result['failed'],
				'log'      => $result['log'],
			) );
		} else {
			wp_send_json_error( array(
				'message' => sprintf(
					/* translators: 1: migrated count, 2: failed count */
					__( 'Migration completed with errors. %1$d migrated, %2$d failed.', 'vira-code' ),
					$result['migrated'],
					$result['failed']
				),
				'migrated' => $result['migrated'],
				'failed'   => $result['failed'],
				'errors'   => $result['errors'],
				'log'      => $result['log'],
			) );
		}
	}

	/**
	 * Validate migration integrity.
	 *
	 * @return void
	 */
	protected function validateMigration() {
		$result = $this->migration_service->validateMigration();

		if ( $result['success'] ) {
			wp_send_json_success( array(
				'message'    => sprintf(
					/* translators: %d: number of validated snippets */
					__( 'Migration validation completed successfully. %d snippets validated.', 'vira-code' ),
					$result['validated']
				),
				'validated'  => $result['validated'],
				'mismatches' => $result['mismatches'],
				'log'        => $result['log'],
			) );
		} else {
			wp_send_json_error( array(
				'message' => sprintf(
					/* translators: 1: validated count, 2: mismatches count */
					__( 'Migration validation found issues. %1$d validated, %2$d mismatches found.', 'vira-code' ),
					$result['validated'],
					$result['mismatches']
				),
				'validated'  => $result['validated'],
				'mismatches' => $result['mismatches'],
				'errors'     => $result['errors'],
				'log'        => $result['log'],
			) );
		}
	}

	/**
	 * Clean up database after migration.
	 *
	 * @return void
	 */
	protected function cleanupDatabase() {
		$result = $this->migration_service->cleanupDatabase();

		if ( $result['success'] ) {
			wp_send_json_success( array(
				'message' => sprintf(
					/* translators: %d: number of deleted snippets */
					__( 'Successfully cleaned up %d migrated snippets from database.', 'vira-code' ),
					$result['deleted']
				),
				'deleted' => $result['deleted'],
				'log'     => $result['log'],
			) );
		} else {
			wp_send_json_error( array(
				'message' => sprintf(
					/* translators: %d: number of deleted snippets */
					__( 'Database cleanup completed with errors. %d snippets deleted.', 'vira-code' ),
					$result['deleted']
				),
				'deleted' => $result['deleted'],
				'errors'  => $result['errors'],
				'log'     => $result['log'],
			) );
		}
	}

	/**
	 * Clean up file storage.
	 *
	 * @return void
	 */
	protected function cleanupFiles() {
		$result = $this->migration_service->cleanupFiles();

		if ( $result['success'] ) {
			wp_send_json_success( array(
				'message' => sprintf(
					/* translators: %d: number of deleted snippets */
					__( 'Successfully cleaned up %d snippets from file storage.', 'vira-code' ),
					$result['deleted']
				),
				'deleted' => $result['deleted'],
				'log'     => $result['log'],
			) );
		} else {
			wp_send_json_error( array(
				'message' => sprintf(
					/* translators: %d: number of deleted snippets */
					__( 'File cleanup completed with errors. %d snippets deleted.', 'vira-code' ),
					$result['deleted']
				),
				'deleted' => $result['deleted'],
				'errors'  => $result['errors'],
				'log'     => $result['log'],
			) );
		}
	}
}