<?php
/**
 * Snippet Manager Service
 *
 * @package ViraCode
 */

namespace ViraCode\Services;

use ViraCode\Models\SnippetModel;
use ViraCode\Services\FileStorageService;
use ViraCode\Services\FileLoaderService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SnippetManagerService Class
 *
 * Unified interface for managing snippets across different storage backends.
 */
class SnippetManagerService {

	/**
	 * Database model instance.
	 *
	 * @var SnippetModel
	 */
	protected $db_model;

	/**
	 * File storage service instance.
	 *
	 * @var FileStorageService
	 */
	protected $file_storage;

	/**
	 * File loader service instance.
	 *
	 * @var FileLoaderService
	 */
	protected $file_loader;

	/**
	 * Current storage mode.
	 *
	 * @var string
	 */
	protected $storage_mode;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->db_model = new SnippetModel();
		$this->file_storage = new FileStorageService();
		$this->file_loader = new FileLoaderService();
		$this->storage_mode = get_option( 'vira_code_storage_mode', 'database' );
	}

	/**
	 * Get all snippets.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public function getAll( $args = array() ) {
		if ( 'file' === $this->storage_mode && $this->file_storage->isAvailable() ) {
			$snippets = $this->file_storage->getAll( $args );
			// Add storage type indicator.
			foreach ( $snippets as &$snippet ) {
				$snippet['storage_type'] = 'file';
			}
			return $snippets;
		}

		$snippets = $this->db_model->getAll( $args );
		// Add storage type indicator.
		foreach ( $snippets as &$snippet ) {
			$snippet['storage_type'] = 'database';
		}
		return $snippets;
	}

	/**
	 * Get a snippet by ID.
	 *
	 * @param int $id Snippet ID.
	 * @return array|null
	 */
	public function getById( $id ) {
		if ( 'file' === $this->storage_mode && $this->file_storage->isAvailable() ) {
			$snippet = $this->file_storage->getById( $id );
			if ( $snippet ) {
				$snippet['storage_type'] = 'file';
			}
			return $snippet;
		}

		$snippet = $this->db_model->getById( $id );
		if ( $snippet ) {
			$snippet['storage_type'] = 'database';
		}
		return $snippet;
	}

	/**
	 * Create a new snippet.
	 *
	 * @param array $data Snippet data.
	 * @return int|false Snippet ID on success, false on failure.
	 */
	public function create( $data ) {
		// Add storage type to data.
		$data['storage'] = $this->storage_mode;

		if ( 'file' === $this->storage_mode && $this->file_storage->isAvailable() ) {
			$result = $this->file_storage->save( $data );
			if ( $result ) {
				$this->clearSnippetCache();
				do_action( 'vira_code/snippet_created', $result, $data );
				do_action( 'vira_code/snippet_saved', $result, $data );
			}
			return $result;
		}

		$result = $this->db_model->create( $data );
		if ( $result ) {
			$this->clearSnippetCache();
		}
		return $result;
	}

	/**
	 * Update a snippet.
	 *
	 * @param int   $id   Snippet ID.
	 * @param array $data Snippet data.
	 * @return bool
	 */
	public function update( $id, $data ) {
		if ( 'file' === $this->storage_mode && $this->file_storage->isAvailable() ) {
			// Get existing snippet to preserve ID and other fields.
			$existing = $this->file_storage->getById( $id );
			if ( ! $existing ) {
				return false;
			}

			// Merge data with existing snippet.
			$updated_data = array_merge( $existing, $data );
			$updated_data['id'] = $id; // Ensure ID is preserved.

			$result = $this->file_storage->save( $updated_data );
			if ( $result ) {
				$this->clearSnippetCache();
				do_action( 'vira_code/snippet_updated', $id, $data );
				do_action( 'vira_code/snippet_saved', $id, $data );
				return true;
			}
			return false;
		}

		$result = $this->db_model->update( $id, $data );
		if ( $result ) {
			$this->clearSnippetCache();
		}
		return $result;
	}

	/**
	 * Delete a snippet.
	 *
	 * @param int $id Snippet ID.
	 * @return bool
	 */
	public function delete( $id ) {
		if ( 'file' === $this->storage_mode && $this->file_storage->isAvailable() ) {
			$snippet = $this->file_storage->getById( $id );
			if ( ! $snippet ) {
				return false;
			}

			do_action( 'vira_code/snippet_before_delete', $id, $snippet );

			$result = $this->file_storage->delete( $id );
			if ( $result ) {
				$this->clearSnippetCache();
				do_action( 'vira_code/snippet_deleted', $id, $snippet );
			}
			return $result;
		}

		$result = $this->db_model->delete( $id );
		if ( $result ) {
			$this->clearSnippetCache();
		}
		return $result;
	}

	/**
	 * Update snippet status.
	 *
	 * @param int    $id     Snippet ID.
	 * @param string $status New status.
	 * @return bool
	 */
	public function updateStatus( $id, $status ) {
		if ( 'file' === $this->storage_mode && $this->file_storage->isAvailable() ) {
			return $this->file_storage->updateStatus( $id, $status );
		}

		return $this->db_model->updateStatus( $id, $status );
	}

	/**
	 * Toggle snippet status.
	 *
	 * @param int $id Snippet ID.
	 * @return bool
	 */
	public function toggleStatus( $id ) {
		$snippet = $this->getById( $id );
		if ( ! $snippet ) {
			return false;
		}

		$new_status = ( 'active' === $snippet['status'] ) ? 'inactive' : 'active';
		return $this->updateStatus( $id, $new_status );
	}

	/**
	 * Get snippets by scope.
	 *
	 * @param string $scope Snippet scope.
	 * @param string $type  Snippet type.
	 * @return array
	 */
	public function getByScope( $scope, $type = '' ) {
		if ( 'file' === $this->storage_mode && $this->file_storage->isAvailable() ) {
			return $this->file_storage->getByScope( $scope, $type );
		}

		return $this->db_model->getByScope( $scope, $type );
	}

	/**
	 * Count snippets.
	 *
	 * @param array $args Query arguments.
	 * @return int
	 */
	public function count( $args = array() ) {
		if ( 'file' === $this->storage_mode && $this->file_storage->isAvailable() ) {
			return $this->file_storage->count( $args );
		}

		return $this->db_model->count( $args );
	}

	/**
	 * Search snippets.
	 *
	 * @param string $search_term Search term.
	 * @param array  $args        Additional query arguments.
	 * @return array
	 */
	public function search( $search_term, $args = array() ) {
		if ( 'file' === $this->storage_mode && $this->file_storage->isAvailable() ) {
			// File storage doesn't have built-in search, so we'll implement it here.
			$all_snippets = $this->file_storage->getAll( $args );
			$search_term = strtolower( $search_term );
			$results = array();

			foreach ( $all_snippets as $snippet ) {
				$title = strtolower( $snippet['title'] );
				$description = strtolower( isset( $snippet['description'] ) ? $snippet['description'] : '' );
				$tags = strtolower( isset( $snippet['tags'] ) ? $snippet['tags'] : '' );

				if ( strpos( $title, $search_term ) !== false ||
					 strpos( $description, $search_term ) !== false ||
					 strpos( $tags, $search_term ) !== false ) {
					$results[] = $snippet;
				}
			}

			return $results;
		}

		return $this->db_model->search( $search_term, $args );
	}

	/**
	 * Execute snippets by scope.
	 *
	 * @param string $scope Snippet scope.
	 * @param string $type  Snippet type.
	 * @return void
	 */
	public function executeByScope( $scope, $type = '' ) {
		if ( 'file' === $this->storage_mode && $this->file_loader->isAvailable() ) {
			$this->file_loader->executeByScope( $scope, $type );
			return;
		}

		// Fall back to database execution.
		$executor = new \ViraCode\Services\SnippetExecutor();
		$executor->executeByScope( $scope, $type );
	}

	/**
	 * Get storage statistics.
	 *
	 * @return array
	 */
	public function getStorageStats() {
		$stats = array(
			'current_mode' => $this->storage_mode,
			'database'     => array(
				'available' => true,
				'total'     => $this->db_model->count(),
				'active'    => $this->db_model->count( array( 'status' => 'active' ) ),
				'inactive'  => $this->db_model->count( array( 'status' => 'inactive' ) ),
				'error'     => $this->db_model->count( array( 'status' => 'error' ) ),
			),
			'file'         => array(
				'available' => $this->file_storage->isAvailable(),
				'total'     => 0,
				'active'    => 0,
				'inactive'  => 0,
				'error'     => 0,
			),
		);

		if ( $this->file_storage->isAvailable() ) {
			$file_stats = $this->file_storage->getStats();
			$stats['file']['total'] = $file_stats['total'];
			$stats['file']['active'] = $file_stats['active'];
			$stats['file']['inactive'] = $file_stats['inactive'];
			$stats['file']['error'] = $file_stats['error'];
			$stats['file']['directory'] = $file_stats['directory'];
			$stats['file']['writable'] = $file_stats['writable'];
		}

		return $stats;
	}

	/**
	 * Switch storage mode.
	 *
	 * @param string $mode Storage mode ('database' or 'file').
	 * @return bool
	 */
	public function switchStorageMode( $mode ) {
		$valid_modes = array( 'database', 'file' );
		if ( ! in_array( $mode, $valid_modes, true ) ) {
			return false;
		}

		// Check if file storage is available when switching to file mode.
		if ( 'file' === $mode && ! $this->file_storage->isAvailable() ) {
			return false;
		}

		$old_mode = $this->storage_mode;
		$this->storage_mode = $mode;
		update_option( 'vira_code_storage_mode', $mode );

		do_action( 'vira_code/storage_mode_changed', $mode, $old_mode );

		return true;
	}

	/**
	 * Get current storage mode.
	 *
	 * @return string
	 */
	public function getStorageMode() {
		return $this->storage_mode;
	}

	/**
	 * Check if file storage is available.
	 *
	 * @return bool
	 */
	public function isFileStorageAvailable() {
		return $this->file_storage->isAvailable();
	}

	/**
	 * Log snippet error.
	 *
	 * @param int    $id            Snippet ID.
	 * @param string $error_message Error message.
	 * @return bool
	 */
	public function logError( $id, $error_message ) {
		if ( 'file' === $this->storage_mode && $this->file_storage->isAvailable() ) {
			// For file storage, we update the snippet status and could store error in metadata.
			return $this->update( $id, array(
				'status'         => 'error',
				'error_message'  => $error_message,
				'last_error_at'  => current_time( 'mysql' ),
			) );
		}

		return $this->db_model->logError( $id, $error_message );
	}

	/**
	 * Clear snippet error.
	 *
	 * @param int $id Snippet ID.
	 * @return bool
	 */
	public function clearError( $id ) {
		if ( 'file' === $this->storage_mode && $this->file_storage->isAvailable() ) {
			return $this->update( $id, array(
				'error_message'  => null,
				'last_error_at'  => null,
			) );
		}

		return $this->db_model->clearError( $id );
	}

	/**
	 * Get file storage service instance.
	 *
	 * @return FileStorageService
	 */
	public function getFileStorage() {
		return $this->file_storage;
	}

	/**
	 * Get database model instance.
	 *
	 * @return SnippetModel
	 */
	public function getDatabaseModel() {
		return $this->db_model;
	}

	/**
	 * Get file loader service instance.
	 *
	 * @return FileLoaderService
	 */
	public function getFileLoader() {
		return $this->file_loader;
	}

	/**
	 * Clear snippet cache transients.
	 *
	 * This is called automatically when snippets are created, updated, or deleted.
	 *
	 * @return void
	 */
	protected function clearSnippetCache() {
		global $wpdb;

		// Delete all vira_code snippet transients.
		$wpdb->query(
			"DELETE FROM {$wpdb->options}
			WHERE option_name LIKE '_transient_vira_code_snippets_%'
			OR option_name LIKE '_transient_timeout_vira_code_snippets_%'"
		);

		do_action( 'vira_code/snippet_cache_cleared' );
	}
}