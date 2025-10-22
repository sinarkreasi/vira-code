<?php
/**
 * Hook Service Provider
 *
 * @package ViraCode
 */

namespace ViraCode\Providers;

use ViraCode\Services\SnippetExecutor;
use ViraCode\Hooks\SafeModeHook;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * HookServiceProvider Class
 *
 * Handles registration of hooks for snippet execution.
 */
class HookServiceProvider extends ServiceProvider {

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register() {
		// Register snippet executor service.
		$this->app->snippetExecutor = new SnippetExecutor();

		// Register safe mode hook.
		$this->app->safeModeHook = new SafeModeHook();
	}

	/**
	 * Boot the service provider.
	 *
	 * @return void
	 */
	public function boot() {
		// Don't execute snippets if safe mode is enabled.
		if ( \ViraCode\vira_code_is_safe_mode() ) {
			return;
		}

		// Initialize safe mode monitoring.
		$this->app->safeModeHook->init();

		// Execute snippets on appropriate hooks.
		add_action( 'init', array( $this, 'executeSnippets' ), 5 );
		add_action( 'wp_enqueue_scripts', array( $this, 'executeFrontendSnippets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'executeAdminSnippets' ) );
		add_action( 'wp_head', array( $this, 'executeFrontendHeadSnippets' ) );
		add_action( 'wp_footer', array( $this, 'executeFrontendFooterSnippets' ) );
		add_action( 'admin_head', array( $this, 'executeAdminHeadSnippets' ) );
		add_action( 'admin_footer', array( $this, 'executeAdminFooterSnippets' ) );

		// Custom action for manual snippet execution.
		add_action( 'vira_code/execute_snippet', array( $this, 'executeSnippetById' ), 10, 1 );
	}

	/**
	 * Execute snippets that run everywhere.
	 *
	 * @return void
	 */
	public function executeSnippets() {
		$executor = $this->app->snippetExecutor;

		// Execute PHP snippets with 'everywhere' scope.
		$executor->executeByScope( 'everywhere', 'php' );
	}

	/**
	 * Execute frontend snippets.
	 *
	 * @return void
	 */
	public function executeFrontendSnippets() {
		if ( is_admin() ) {
			return;
		}

		$executor = $this->app->snippetExecutor;

		// Execute JS and CSS snippets for frontend.
		$executor->executeByScope( 'frontend', 'js' );
		$executor->executeByScope( 'frontend', 'css' );
		$executor->executeByScope( 'both', 'js' );
		$executor->executeByScope( 'both', 'css' );
	}

	/**
	 * Execute admin snippets.
	 *
	 * @return void
	 */
	public function executeAdminSnippets() {
		if ( ! is_admin() ) {
			return;
		}

		$executor = $this->app->snippetExecutor;

		// Execute JS and CSS snippets for admin.
		$executor->executeByScope( 'admin', 'js' );
		$executor->executeByScope( 'admin', 'css' );
		$executor->executeByScope( 'both', 'js' );
		$executor->executeByScope( 'both', 'css' );
	}

	/**
	 * Execute frontend head snippets.
	 *
	 * @return void
	 */
	public function executeFrontendHeadSnippets() {
		if ( is_admin() ) {
			return;
		}

		$executor = $this->app->snippetExecutor;

		// Execute PHP snippets for frontend.
		$executor->executeByScope( 'frontend', 'php' );
		$executor->executeByScope( 'both', 'php' );

		// Execute HTML snippets for frontend.
		$executor->executeByScope( 'frontend', 'html' );
		$executor->executeByScope( 'both', 'html' );
	}

	/**
	 * Execute frontend footer snippets.
	 *
	 * @return void
	 */
	public function executeFrontendFooterSnippets() {
		if ( is_admin() ) {
			return;
		}

		// Additional execution point for late-loading snippets.
		do_action( 'vira_code/frontend_footer_executed' );
	}

	/**
	 * Execute admin head snippets.
	 *
	 * @return void
	 */
	public function executeAdminHeadSnippets() {
		if ( ! is_admin() ) {
			return;
		}

		$executor = $this->app->snippetExecutor;

		// Execute PHP snippets for admin.
		$executor->executeByScope( 'admin', 'php' );
		$executor->executeByScope( 'both', 'php' );

		// Execute HTML snippets for admin.
		$executor->executeByScope( 'admin', 'html' );
		$executor->executeByScope( 'both', 'html' );
	}

	/**
	 * Execute admin footer snippets.
	 *
	 * @return void
	 */
	public function executeAdminFooterSnippets() {
		if ( ! is_admin() ) {
			return;
		}

		// Additional execution point for late-loading snippets.
		do_action( 'vira_code/admin_footer_executed' );
	}

	/**
	 * Execute a specific snippet by ID.
	 *
	 * @param int $snippet_id Snippet ID.
	 * @return void
	 */
	public function executeSnippetById( $snippet_id ) {
		$executor = $this->app->snippetExecutor;
		$executor->executeById( $snippet_id );
	}
}
