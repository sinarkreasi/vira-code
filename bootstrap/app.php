<?php
/**
 * Bootstrap the Application
 *
 * @package ViraCode
 */

namespace ViraCode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load the main App class if not already loaded.
if ( ! class_exists( 'ViraCode\App' ) ) {
	require_once VIRA_CODE_PATH . 'app/App.php';
}

// Initialize the application.
App::getInstance()->boot();
