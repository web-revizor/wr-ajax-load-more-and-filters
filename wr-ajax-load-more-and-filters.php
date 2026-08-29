<?php
/*
Plugin Name:  Web Revizor: Ajax Load More & Filters
Plugin URI:
Description:  All posts ajax load more, search and filter
Version:      1.4.0
Author:       WebRevizor
Author URI:   https://github.com/web-revizor/
License:      GPL2
License URI:
Text Domain:  wr-ajax-load-more-and-filters
*/

if (!defined('ABSPATH')) {
    exit;
}

define('WRALM_PATH', plugin_dir_path(__FILE__));
define('WRALM_URL', plugin_dir_url(__FILE__));
define('WRALM_VERSION', '1.4.0');

require_once WRALM_PATH . 'inc/class-pagination.php';
require_once WRALM_PATH . 'inc/class-query-config.php';
require_once WRALM_PATH . 'inc/class-shortcode.php';
require_once WRALM_PATH . 'inc/class-filter-config.php';
require_once WRALM_PATH . 'inc/class-load-more.php';
require_once WRALM_PATH . 'inc/class-admin.php';
require_once WRALM_PATH . 'inc/class-hide-meta-box.php';
require_once WRALM_PATH . 'inc/class-search-acf.php';

/**
 * Main plugin class. Wires up every subsystem; each subsystem class
 * registers its own hooks in its own constructor.
 */
class Web_Revizor_Ajax_Load_More
{
    public function __construct()
    {
        new WRALM_Shortcode();
        new WRALM_Load_More();
        new WRALM_Admin();
        new WRALM_Hide_Meta_Box();
        new WRALM_Search_ACF();
    }
}

register_activation_hook(__FILE__, ['WRALM_Admin', 'create_card_template']);
register_activation_hook(__FILE__, ['WRALM_Search_ACF', 'on_activate']);

new Web_Revizor_Ajax_Load_More();
