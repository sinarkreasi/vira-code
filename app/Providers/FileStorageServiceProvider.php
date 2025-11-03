<?php
/**
 * File Storage Service Provider
 *
 * @package ViraCode
 */

namespace ViraCode\Providers;

use ViraCode\App;
use ViraCode\Services\FileStorageService;
use ViraCode\Services\FileLoaderService;
use ViraCode\Services\MigrationService;
use ViraCode\Services\SnippetManagerService;
use ViraCode\Http\MigrationController;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * FileStorageServiceProvider Class
 *
 * Registers file storage related services and hooks.
 */
class FileStorageServiceProvider {

	/**
	 * App instance.
	 *
	 * @var App
	 */
	protected $app;

	/**
	 * Constructor.
	 *
	 * @param App $app App instance.
	 */
	public function __construct( App $app ) {
		$this->app = $app;
	}

	/**
	 * Register services.
	 *
	 * @return void
	 */
	public function register() {
		// Register file storage service.
		$this->app->fileStorageService = new FileStorageService();

		// Register file loader service.
		$this->app->fileLoaderService = new FileLoaderService();

		// Register migration service.
		$this->app->migrationService = new MigrationService();

		// Register snippet manager service.
		$this->app->snippetManagerService = new SnippetManagerService();

		// Register migration controller.
		$this->app->migrationController = new MigrationController();

		// Register library file storage service.
		$this->app->libraryFileStorageService = new \ViraCode\Services\LibraryFileStorageService();
	}

	/**
	 * Boot services.
	 *
	 * @return void
	 */
	public function boot() {
		// Register AJAX handlers.
		add_action( 'wp_ajax_vira_code_migration', array( $this->app->migrationController, 'handleMigration' ) );
		add_action( 'wp_ajax_vira_code_switch_storage_mode', array( $this->app->migrationController, 'handleStorageModeSwitch' ) );
		
		// Register library migration AJAX handlers.
		add_action( 'wp_ajax_vira_code_migrate_library_to_files', array( $this->app->adminController, 'migrateLibraryToFiles' ) );
		add_action( 'wp_ajax_vira_code_get_library_stats', array( $this->app->adminController, 'getLibraryStats' ) );

		// Register admin menu for migration.
		add_action( 'admin_menu', array( $this, 'registerMigrationMenu' ), 20 );

		// Hook into snippet execution based on storage mode.
		add_action( 'init', array( $this, 'initializeSnippetExecution' ), 5 );

		// Add storage mode to snippet data.
		add_filter( 'vira_code/snippets_retrieved', array( $this, 'addStorageTypeToSnippets' ), 10, 2 );
		add_filter( 'vira_code/snippet_retrieved', array( $this, 'addStorageTypeToSnippet' ), 10, 2 );
	}

	/**
	 * Register migration admin menu.
	 *
	 * @return void
	 */
	public function registerMigrationMenu() {
		add_submenu_page(
			'vira-code',
			__( 'Storage Migration', 'vira-code' ),
			__( 'Migration', 'vira-code' ),
			'manage_options',
			'vira-code-migration',
			array( $this, 'renderMigrationPage' )
		);
	}

	/**
	 * Render migration page.
	 *
	 * @return void
	 */
	public function renderMigrationPage() {
		include VIRA_CODE_PATH . 'app/Views/migration.php';
	}

	/**
	 * Initialize snippet execution based on storage mode.
	 *
	 * @return void
	 */
	public function initializeSnippetExecution() {
		$storage_mode = get_option( 'vira_code_storage_mode', 'database' );

		if ( 'file' === $storage_mode && $this->app->fileLoaderService->isAvailable() ) {
			// Use file-based execution.
			add_action( 'wp_head', array( $this, 'executeHeadSnippets' ), 1 );
			add_action( 'wp_footer', array( $this, 'executeFooterSnippets' ), 999 );
			add_action( 'admin_head', array( $this, 'executeAdminHeadSnippets' ), 1 );
			add_action( 'admin_footer', array( $this, 'executeAdminFooterSnippets' ), 999 );
		}
		// Database execution is handled by the existing SnippetExecutor via HookServiceProvider.
	}

	/**
	 * Execute head snippets from file storage.
	 *
	 * @return void
	 */
	public function executeHeadSnippets() {
		$this->app->fileLoaderService->executeByScope( 'frontend', 'css' );
		$this->app->fileLoaderService->executeByScope( 'frontend', 'js' );
		$this->app->fileLoaderService->executeByScope( 'frontend', 'html' );
		$this->app->fileLoaderService->executeByScope( 'frontend', 'php' );
	}

	/**
	 * Execute footer snippets from file storage.
	 *
	 * @return void
	 */
	public function executeFooterSnippets() {
		$this->app->fileLoaderService->executeByScope( 'frontend' );
	}

	/**
	 * Execute admin head snippets from file storage.
	 *
	 * @return void
	 */
	public function executeAdminHeadSnippets() {
		$this->app->fileLoaderService->executeByScope( 'admin', 'css' );
		$this->app->fileLoaderService->executeByScope( 'admin', 'js' );
		$this->app->fileLoaderService->executeByScope( 'admin', 'html' );
		$this->app->fileLoaderService->executeByScope( 'admin', 'php' );
	}

	/**
	 * Execute admin footer snippets from file storage.
	 *
	 * @return void
	 */
	public function executeAdminFooterSnippets() {
		$this->app->fileLoaderService->executeByScope( 'admin' );
	}

	/**
	 * Add storage type to snippets array.
	 *
	 * @param array $snippets Snippets array.
	 * @param array $args     Query arguments.
	 * @return array
	 */
	public function addStorageTypeToSnippets( $snippets, $args ) {
		$storage_mode = get_option( 'vira_code_storage_mode', 'database' );

		foreach ( $snippets as &$snippet ) {
			if ( ! isset( $snippet['storage_type'] ) ) {
				$snippet['storage_type'] = $storage_mode;
			}
		}

		return $snippets;
	}

	/**
	 * Add storage type to single snippet.
	 *
	 * @param array $snippet Snippet data.
	 * @param int   $id      Snippet ID.
	 * @return array
	 */
	public function addStorageTypeToSnippet( $snippet, $id ) {
		if ( $snippet && ! isset( $snippet['storage_type'] ) ) {
			$storage_mode = get_option( 'vira_code_storage_mode', 'database' );
			$snippet['storage_type'] = $storage_mode;
		}

		return $snippet;
	}
}