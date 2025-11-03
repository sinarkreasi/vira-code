<?php
/**
 * Library File Storage Service
 *
 * @package ViraCode
 */

namespace ViraCode\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * LibraryFileStorageService Class
 *
 * Handles file-based storage for library snippets.
 */
class LibraryFileStorageService {

	/**
	 * Base directory for library storage.
	 *
	 * @var string
	 */
	protected $base_dir;

	/**
	 * WooCommerce library directory.
	 *
	 * @var string
	 */
	protected $woocommerce_dir;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->base_dir = WP_CONTENT_DIR . '/vira-cache/library/';
		$this->woocommerce_dir = $this->base_dir . 'woocommerce/';
		
		// Ensure directories exist.
		$this->ensureDirectoriesExist();
	}

	/**
	 * Check if library file storage is available.
	 *
	 * @return bool
	 */
	public function isAvailable() {
		return is_writable( WP_CONTENT_DIR ) && $this->ensureDirectoriesExist();
	}

	/**
	 * Scan library directory and load all snippets.
	 *
	 * @param string $library_path Optional library path.
	 * @return array
	 */
	public function scanLibrary( $library_path = '' ) {
		if ( empty( $library_path ) ) {
			$library_path = $this->woocommerce_dir;
		}

		$result = array();
		$categories = glob( $library_path . '*', GLOB_ONLYDIR );

		foreach ( $categories as $category_dir ) {
			$category = basename( $category_dir );
			$meta_file = $category_dir . '/meta.json';

			if ( file_exists( $meta_file ) ) {
				$meta_content = file_get_contents( $meta_file );
				$meta = json_decode( $meta_content, true );

				if ( $meta && isset( $meta['snippets'] ) ) {
					$result[ $category ] = $this->loadCategorySnippets( $category_dir, $meta['snippets'] );
				}
			}
		}

		return $result;
	}

	/**
	 * Load snippets for a specific category.
	 *
	 * @param string $category_dir Category directory path.
	 * @param array  $snippets_meta Snippets metadata.
	 * @return array
	 */
	protected function loadCategorySnippets( $category_dir, $snippets_meta ) {
		$snippets = array();

		foreach ( $snippets_meta as $snippet_meta ) {
			$file_path = $category_dir . '/' . $snippet_meta['file'];

			if ( file_exists( $file_path ) ) {
				$code = file_get_contents( $file_path );
				if ( false !== $code ) {
					$snippet = array_merge( $snippet_meta, array(
						'code' => $code,
						'category' => basename( $category_dir ),
						'storage_type' => 'library_file',
					) );
					$snippets[] = $snippet;
				}
			}
		}

		return $snippets;
	}

	/**
	 * Get all library snippets.
	 *
	 * @return array
	 */
	public function getAllSnippets() {
		$all_snippets = array();
		$categories = $this->scanLibrary();

		foreach ( $categories as $category_name => $category_snippets ) {
			$all_snippets = array_merge( $all_snippets, $category_snippets );
		}

		return $all_snippets;
	}

	/**
	 * Create library structure from hardcoded snippets.
	 *
	 * @param array $snippets Hardcoded snippets array.
	 * @return bool
	 */
	public function migrateHardcodedSnippets( $snippets ) {
		if ( ! $this->isAvailable() ) {
			return false;
		}

		// Group snippets by category.
		$categories = array();
		foreach ( $snippets as $snippet ) {
			$category = $this->sanitizeCategoryName( $snippet['category'] );
			if ( ! isset( $categories[ $category ] ) ) {
				$categories[ $category ] = array(
					'category' => $category,
					'description' => sprintf( 'Snippets related to %s customization', $snippet['category'] ),
					'snippets' => array(),
				);
			}
			$categories[ $category ]['snippets'][] = $snippet;
		}

		// Create directory structure and files.
		foreach ( $categories as $category_name => $category_data ) {
			$category_dir = $this->woocommerce_dir . $category_name . '/';

			// Create category directory.
			if ( ! wp_mkdir_p( $category_dir ) ) {
				continue;
			}

			$meta_snippets = array();

			// Create snippet files.
			foreach ( $category_data['snippets'] as $snippet ) {
				$slug = $this->generateSlug( $snippet['title'] );
				$file_extension = $this->getFileExtension( $snippet['type'] );
				$file_name = $slug . '.' . $file_extension;
				$file_path = $category_dir . $file_name;

				// Create snippet file with header comment.
				$file_content = $this->generateSnippetFileContent( $snippet );
				if ( false !== file_put_contents( $file_path, $file_content, LOCK_EX ) ) {
					$meta_snippets[] = array(
						'slug' => $slug,
						'name' => $snippet['title'],
						'type' => $snippet['type'],
						'file' => $file_name,
						'tags' => explode( ', ', $snippet['tags'] ),
						'description' => $snippet['description'],
						'scope' => $snippet['scope'],
						'icon' => $snippet['icon'],
					);
				}
			}

			// Create meta.json file.
			$meta_data = array(
				'category' => $category_name,
				'description' => $category_data['description'],
				'snippets' => $meta_snippets,
			);

			$meta_content = wp_json_encode( $meta_data, JSON_PRETTY_PRINT );
			file_put_contents( $category_dir . 'meta.json', $meta_content, LOCK_EX );
		}

		// Create main index.json.
		$this->createMainIndex();

		return true;
	}

	/**
	 * Generate snippet file content with proper header.
	 *
	 * @param array $snippet Snippet data.
	 * @return string
	 */
	protected function generateSnippetFileContent( $snippet ) {
		$header = "<?php\n";
		$header .= "/**\n";
		$header .= " * Plugin: Vira Code\n";
		$header .= " * Category: " . $snippet['category'] . "\n";
		$header .= " * Snippet: " . $snippet['title'] . "\n";
		$header .= " * Description: " . $snippet['description'] . "\n";
		$header .= " */\n\n";

		// Clean the code (remove PHP opening tags if present).
		$code = $snippet['code'];
		$code = preg_replace( '/^<\?php\s*/i', '', $code );
		$code = preg_replace( '/\?>\s*$/', '', $code );

		return $header . $code;
	}

	/**
	 * Create main index.json file.
	 *
	 * @return bool
	 */
	protected function createMainIndex() {
		$categories = glob( $this->woocommerce_dir . '*', GLOB_ONLYDIR );
		$index_data = array(
			'library' => 'woocommerce',
			'version' => '1.0.0',
			'categories' => array(),
			'total_snippets' => 0,
			'created_at' => current_time( 'mysql' ),
		);

		$total_snippets = 0;

		foreach ( $categories as $category_dir ) {
			$category = basename( $category_dir );
			$meta_file = $category_dir . '/meta.json';

			if ( file_exists( $meta_file ) ) {
				$meta_content = file_get_contents( $meta_file );
				$meta = json_decode( $meta_content, true );

				if ( $meta && isset( $meta['snippets'] ) ) {
					$snippet_count = count( $meta['snippets'] );
					$total_snippets += $snippet_count;

					$index_data['categories'][] = array(
						'name' => $category,
						'description' => $meta['description'],
						'snippet_count' => $snippet_count,
					);
				}
			}
		}

		$index_data['total_snippets'] = $total_snippets;

		$index_content = wp_json_encode( $index_data, JSON_PRETTY_PRINT );
		return false !== file_put_contents( $this->woocommerce_dir . 'index.json', $index_content, LOCK_EX );
	}

	/**
	 * Sanitize category name for directory.
	 *
	 * @param string $category Category name.
	 * @return string
	 */
	protected function sanitizeCategoryName( $category ) {
		return strtolower( str_replace( array( ' ', '-' ), '_', sanitize_title( $category ) ) );
	}

	/**
	 * Generate slug from title.
	 *
	 * @param string $title Snippet title.
	 * @return string
	 */
	protected function generateSlug( $title ) {
		$slug = sanitize_title( $title );
		$slug = str_replace( '-', '_', $slug );
		return $slug;
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

		return isset( $extensions[ $type ] ) ? $extensions[ $type ] : 'php';
	}

	/**
	 * Ensure directories exist and are secure.
	 *
	 * @return bool
	 */
	protected function ensureDirectoriesExist() {
		// Create base library directory.
		if ( ! wp_mkdir_p( $this->base_dir ) ) {
			return false;
		}

		// Create WooCommerce directory.
		if ( ! wp_mkdir_p( $this->woocommerce_dir ) ) {
			return false;
		}

		// Create security files.
		$this->createSecurityFiles( $this->base_dir );
		$this->createSecurityFiles( $this->woocommerce_dir );

		return true;
	}

	/**
	 * Create security files (.htaccess and index.php).
	 *
	 * @param string $directory Directory path.
	 * @return void
	 */
	protected function createSecurityFiles( $directory ) {
		// Create .htaccess file.
		$htaccess_file = $directory . '.htaccess';
		if ( ! file_exists( $htaccess_file ) ) {
			$htaccess_content = "# Vira Code Library Security\n";
			$htaccess_content .= "<FilesMatch \"\\.(php|js|css|html)$\">\n";
			$htaccess_content .= "    Require all denied\n";
			$htaccess_content .= "</FilesMatch>\n";
			$htaccess_content .= "<Files \"*.json\">\n";
			$htaccess_content .= "    Require all denied\n";
			$htaccess_content .= "</Files>\n";

			@file_put_contents( $htaccess_file, $htaccess_content );
		}

		// Create index.php file.
		$index_php_file = $directory . 'index.php';
		if ( ! file_exists( $index_php_file ) ) {
			@file_put_contents( $index_php_file, "<?php\n// Silence is golden.\n" );
		}
	}

	/**
	 * Get library statistics.
	 *
	 * @return array
	 */
	public function getStats() {
		$categories = $this->scanLibrary();
		$total_snippets = 0;
		$category_count = count( $categories );

		foreach ( $categories as $category_snippets ) {
			$total_snippets += count( $category_snippets );
		}

		return array(
			'total_snippets' => $total_snippets,
			'categories' => $category_count,
			'storage_available' => $this->isAvailable(),
			'base_directory' => $this->base_dir,
			'woocommerce_directory' => $this->woocommerce_dir,
		);
	}

	/**
	 * Get base directory path.
	 *
	 * @return string
	 */
	public function getBaseDirectory() {
		return $this->base_dir;
	}

	/**
	 * Get WooCommerce directory path.
	 *
	 * @return string
	 */
	public function getWooCommerceDirectory() {
		return $this->woocommerce_dir;
	}
}