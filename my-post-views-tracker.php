<?php

/**
 * Plugin Name: My Post Views Tracker
 * Description: A WordPress plugin to track and display unique post views.
 * Version: 1.0.0
 * Author: Rabindra Pantha
 * Text Domain: my-post-views-tracker
 */

defined('ABSPATH') or die('Direct script access disallowed.');

require_once __DIR__ . '/vendor/autoload.php';

use WPCreative\WordPress\MyPostViewsTracker\PostViewsTracker;

// Initialize the plugin.
register_activation_hook(__FILE__, ['WPCreative\WordPress\MyPostViewsTracker\PostViewsTracker', 'activate']);
PostViewsTracker::getInstance();
