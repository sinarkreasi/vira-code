<?php
/**
 * File Storage Service
 *
 * @package ViraCode
 */

namespace ViraCode\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * FileStorageService Class
 *
 * Handles file-based storage for code snippets.
 */
class FileStorageService {

	/**
	 * Base directory for snippet storage.
	 *
	 * @var string
	 */
	protected $base_dir;

	/**
	 * Index file path.
	 *
	 * @var string
	 */
	protected $index_file;

	/**
	 * Cache for loaded snippets.
	 *
	 * @var array
	 */
	protected $cache = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->base_dir   = WP_CONTENT_DIR . '/vira-cache/';
		$this->index_file = $this->base_dir . 'index.json';
		
		// Ensure directory exists.
		$this->ensureDirectoryExists();
	}

	/**
	 * Check if file storage is available.
	 *
	 * @return bool
	 */
	public function isAvailable() {
		return is_writable( WP_CONTENT_DIR ) && $this->ensureDirectoryExists();
	}

	/**
	 * Get all snippets from file storage.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public function getAll( $args = array() ) {
		$defaults = array(
			'type'     => '',
			'scope'    => '',
			'status'   => '',
			'category' => '',
			'orderby'  => 'priority',
			'order'    => 'ASC',
			'limit'    => -1,
			'offset'   => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		$index = $this->loadIndex();
		$snippets = array();

		foreach ( $index as $snippet_id => $snippet_info ) {
			$snippet = $this->getById( $snippet_id );
			if ( ! $snippet ) {
				continue;
			}

			// Apply filters.
			if ( ! empty( $args['type'] ) && $snippet['type'] !== $args['type'] ) {
				continue;
			}

			if ( ! empty( $args['scope'] ) && $snippet['scope'] !== $args['scope'] ) {
				continue;
			}

			if ( ! empty( $args['status'] ) && $snippet['status'] !== $args['status'] ) {
				continue;
			}

			if ( ! empty( $args['category'] ) && $snippet['category'] !== $args['category'] ) {
				continue;
			}

			$snippets[] = $snippet;
		}

		// Sort snippets.
		$this->sortSnippets( $snippets, $args['orderby'], $args['order'] );

		// Apply limit and offset.
		if ( $args['limit'] > 0 ) {
			$snippets = array_slice( $snippets, $args['offset'], $args['limit'] );
		}

		return apply_filters( 'vira_code/file_snippets_retrieved', $snippets, $args );
	}

	/**
	 * Get a snippet by ID.
	 *
	 * @param int $id Snippet ID.
	 * @return array|null
	 */
	public function getById( $id ) {
		// Check cache first.
		if ( isset( $this->cache[ $id ] ) ) {
			return $this->cache[ $id ];
		}

		$snippet_dir = $this->getSnippetDirectory( $id );
		$meta_file = $snippet_dir . 'meta.json';

		if ( ! file_exists( $meta_file ) ) {
			return null;
		}

		$meta_content = file_get_contents( $meta_file );
		if ( false === $meta_content ) {
			return null;
		}

		$meta = json_decode( $meta_content, true );
		if ( ! $meta || ! isset( $meta['slug'], $meta['type'] ) ) {
			return null;
		}

		// Load the code file.
		$code_file = $snippet_dir . $meta['slug'] . '.' . $this->getFileExtension( $meta['type'] );
		if ( ! file_exists( $code_file ) ) {
			return null;
		}

		$code = file_get_contents( $code_file );
		if ( false === $code ) {
			return null;
		}

		$snippet = array_merge( $meta, array( 'code' => $code ) );

		// Cache the snippet.
		$this->cache[ $id ] = $snippet;

		return apply_filters( 'vira_code/file_snippet_retrieved', $snippet, $id );
	}

	/**
	 * Save a snippet to file storage.
	 *
	 * @param array $snippet Snippet data.
	 * @return bool|int Snippet ID on success, false on failure.
	 */
	public function save( $snippet ) {
		if ( ! $this->isAvailable() ) {
			return false;
		}

		// Generate ID if not provided.
		if ( empty( $snippet['id'] ) ) {
			$snippet['id'] = $this->generateId();
		}

		$snippet_id = $snippet['id'];
		$snippet_dir = $this->getSnippetDirectory( $snippet_id );

		// Ensure snippet directory exists.
		if ( ! wp_mkdir_p( $snippet_dir ) ) {
			return false;
		}

		// Generate slug if not provided.
		if ( empty( $snippet['slug'] ) ) {
			$snippet['slug'] = $this->generateSlug( $snippet['title'] );
		}

		// Set timestamps.
		$current_time = current_time( 'mysql' );
		if ( empty( $snippet['created_at'] ) ) {
			$snippet['created_at'] = $current_time;
		}
		$snippet['updated_at'] = $current_time;

		// Separate code from metadata.
		$code = $snippet['code'];
		unset( $snippet['code'] );

		// Save metadata.
		$meta_file = $snippet_dir . 'meta.json';
		$meta_content = wp_json_encode( $snippet, JSON_PRETTY_PRINT );
		
		if ( false === file_put_contents( $meta_file, $meta_content, LOCK_EX ) ) {
			return false;
		}

		// Save code file.
		$code_file = $snippet_dir . $snippet['slug'] . '.' . $this->getFileExtension( $snippet['type'] );
		if ( false === file_put_contents( $code_file, $code, LOCK_EX ) ) {
			// Clean up meta file if code save failed.
			@unlink( $meta_file );
			return false;
		}

		// Update index.
		$this->updateIndex( $snippet_id, $snippet );

		// Clear cache.
		unset( $this->cache[ $snippet_id ] );

		do_action( 'vira_code/file_snippet_saved', $snippet_id, $snippet );

		return $snippet_id;
	}

	/**
	 * Delete a snippet from file storage.
	 *
	 * @param int $id Snippet ID.
	 * @return bool
	 */
	public function delete( $id ) {
		$snippet = $this->getById( $id );
		if ( ! $snippet ) {
			return false;
		}

		$snippet_dir = $this->getSnippetDirectory( $id );

		// Remove all files in the snippet directory.
		$files = glob( $snippet_dir . '*' );
		foreach ( $files as $file ) {
			if ( is_file( $file ) ) {
				@unlink( $file );
			}
		}

		// Remove directory.
		@rmdir( $snippet_dir );

		// Update index.
		$this->removeFromIndex( $id );

		// Clear cache.
		unset( $this->cache[ $id ] );

		do_action( 'vira_code/file_snippet_deleted', $id, $snippet );

		return true;
	}

	/**
	 * Update snippet status.
	 *
	 * @param int    $id     Snippet ID.
	 * @param string $status New status.
	 * @return bool
	 */
	public function updateStatus( $id, $status ) {
		$snippet = $this->getById( $id );
		if ( ! $snippet ) {
			return false;
		}

		$snippet['status'] = $status;
		$snippet['updated_at'] = current_time( 'mysql' );

		return false !== $this->save( $snippet );
	}

	/**
	 * Count snippets.
	 *
	 * @param array $args Query arguments.
	 * @return int
	 */
	public function count( $args = array() ) {
		$snippets = $this->getAll( $args );
		return count( $snippets );
	}

	/**
	 * Get snippets by scope.
	 *
	 * @param string $scope Snippet scope.
	 * @param string $type  Snippet type.
	 * @return array
	 */
	public function getByScope( $scope, $type = '' ) {
		$args = array(
			'scope'  => $scope,
			'status' => 'active',
		);

		if ( ! empty( $type ) ) {
			$args['type'] = $type;
		}

		return $this->getAll( $args );
	}

	/**
	 * Migrate snippet from database to file storage.
	 *
	 * @param array $db_snippet Database snippet data.
	 * @return bool|int
	 */
	public function migrateFromDatabase( $db_snippet ) {
		// Prepare snippet data for file storage.
		$snippet = array(
			'id'          => $db_snippet['id'],
			'title'       => $db_snippet['title'],
			'description' => $db_snippet['description'],
			'code'        => $db_snippet['code'],
			'type'        => $db_snippet['type'],
			'scope'       => $db_snippet['scope'],
			'status'      => $db_snippet['status'],
			'tags'        => $db_snippet['tags'],
			'category'    => $db_snippet['category'],
			'priority'    => (int) $db_snippet['priority'],
			'created_at'  => $db_snippet['created_at'],
			'updated_at'  => $db_snippet['updated_at'],
			'storage'     => 'file',
		);

		return $this->save( $snippet );
	}

	/**
	 * Get storage statistics.
	 *
	 * @return array
	 */
	public function getStats() {
		$index = $this->loadIndex();
		$total = count( $index );
		
		$stats = array(
			'total'     => $total,
			'active'    => 0,
			'inactive'  => 0,
			'error'     => 0,
			'directory' => $this->base_dir,
			'writable'  => is_writable( $this->base_dir ),
		);

		foreach ( $index as $snippet_id => $snippet_info ) {
			if ( isset( $snippet_info['status'] ) ) {
				$status = $snippet_info['status'];
				if ( isset( $stats[ $status ] ) ) {
					$stats[ $status ]++;
				}
			}
		}

		return $stats;
	}

	/**
	 * Ensure the base directory exists and is secure.
	 *
	 * @return bool
	 */
	protected function ensureDirectoryExists() {
		if ( ! wp_mkdir_p( $this->base_dir ) ) {
			return false;
		}

		// Create .htaccess file for security.
		$htaccess_file = $this->base_dir . '.htaccess';
		if ( ! file_exists( $htaccess_file ) ) {
			$htaccess_content = "# Vira Code Security\n";
			$htaccess_content .= "<FilesMatch \"\\.(php|js|css|html)$\">\n";
			$htaccess_content .= "    Require all denied\n";
			$htaccess_content .= "</FilesMatch>\n";
			$htaccess_content .= "<Files \"*.json\">\n";
			$htaccess_content .= "    Require all denied\n";
			$htaccess_content .= "</Files>\n";

			@file_put_contents( $htaccess_file, $htaccess_content );
		}

		// Create index.php file for additional security.
		$index_php_file = $this->base_dir . 'index.php';
		if ( ! file_exists( $index_php_file ) ) {
			@file_put_contents( $index_php_file, "<?php\n// Silence is golden.\n" );
		}

		return true;
	}

	/**
	 * Get snippet directory path.
	 *
	 * @param int $id Snippet ID.
	 * @return string
	 */
	protected function getSnippetDirectory( $id ) {
		return $this->base_dir . $id . '/';
	}

	/**
	 * Get file extension for snippet type.
	 *
	 * @param string $type Snippet type.
	 * @return string
	 */
	protected function getFileExtension( $type ) {
		$extensions = array(
			'php'  => 'php',
			'js'   => 'js',
			'css'  => 'css',
			'html' => 'html',
		);

		return isset( $extensions[ $type ] ) ? $extensions[ $type ] : 'txt';
	}

	/**
	 * Generate a unique ID.
	 *
	 * @return int
	 */
	protected function generateId() {
		$index = $this->loadIndex();
		$max_id = 0;

		foreach ( array_keys( $index ) as $id ) {
			$max_id = max( $max_id, (int) $id );
		}

		return $max_id + 1;
	}

	/**
	 * Generate a slug from title.
	 *
	 * @param string $title Snippet title.
	 * @return string
	 */
	protected function generateSlug( $title ) {
		$slug = sanitize_title( $title );
		if ( empty( $slug ) ) {
			$slug = 'snippet-' . time();
		}
		return $slug;
	}

	/**
	 * Load the index file.
	 *
	 * @return array
	 */
	protected function loadIndex() {
		if ( ! file_exists( $this->index_file ) ) {
			return array();
		}

		$content = file_get_contents( $this->index_file );
		if ( false === $content ) {
			return array();
		}

		$index = json_decode( $content, true );
		return is_array( $index ) ? $index : array();
	}

	/**
	 * Update the index file.
	 *
	 * @param int   $id      Snippet ID.
	 * @param array $snippet Snippet data.
	 * @return bool
	 */
	protected function updateIndex( $id, $snippet ) {
		$index = $this->loadIndex();

		$index[ $id ] = array(
			'slug'       => $snippet['slug'],
			'title'      => $snippet['title'],
			'type'       => $snippet['type'],
			'scope'      => $snippet['scope'],
			'status'     => $snippet['status'],
			'priority'   => $snippet['priority'],
			'updated_at' => $snippet['updated_at'],
		);

		$content = wp_json_encode( $index, JSON_PRETTY_PRINT );
		return false !== file_put_contents( $this->index_file, $content, LOCK_EX );
	}

	/**
	 * Remove snippet from index.
	 *
	 * @param int $id Snippet ID.
	 * @return bool
	 */
	protected function removeFromIndex( $id ) {
		$index = $this->loadIndex();
		unset( $index[ $id ] );

		$content = wp_json_encode( $index, JSON_PRETTY_PRINT );
		return false !== file_put_contents( $this->index_file, $content, LOCK_EX );
	}

	/**
	 * Sort snippets array.
	 *
	 * @param array  $snippets Snippets array (passed by reference).
	 * @param string $orderby  Order by field.
	 * @param string $order    Order direction.
	 * @return void
	 */
	protected function sortSnippets( &$snippets, $orderby, $order ) {
		$order = strtoupper( $order );

		usort( $snippets, function( $a, $b ) use ( $orderby, $order ) {
			$value_a = isset( $a[ $orderby ] ) ? $a[ $orderby ] : '';
			$value_b = isset( $b[ $orderby ] ) ? $b[ $orderby ] : '';

			if ( is_numeric( $value_a ) && is_numeric( $value_b ) ) {
				$result = $value_a <=> $value_b;
			} else {
				$result = strcmp( $value_a, $value_b );
			}

			return 'DESC' === $order ? -$result : $result;
		} );
	}

	/**
	 * Clear all caches.
	 *
	 * @return void
	 */
	public function clearCache() {
		$this->cache = array();
	}

	/**
	 * Get base directory path.
	 *
	 * @return string
	 */
	public function getBaseDirectory() {
		return $this->base_dir;
	}
}