<?php
/**
 * Plugin Name: FPD Catalog V1
 * Description: Dynamic Elementor widget combining FPD Base Products and Designs into a filterable catalog.
 * Version: 1.1.0
 * Author: Elite Developer
 * Text Domain: fpd-dynamic-archive
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'FPD_DYN_ARCHIVE_VERSION', '1.1.0' );
define( 'FPD_DYN_ARCHIVE_URL', plugin_dir_url( __FILE__ ) );
define( 'FPD_DYN_ARCHIVE_PATH', plugin_dir_path( __FILE__ ) );

class FPD_Dynamic_Archive_Plugin {
    public function __construct() {
        add_action( 'elementor/widgets/register', [ $this, 'register_widgets' ] );
        add_action( 'elementor/frontend/after_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        
        require_once FPD_DYN_ARCHIVE_PATH . 'includes/class-ajax-handlers.php';
        new FPD_Dyn_Ajax_Handlers();
    }

    public function register_widgets( $widgets_manager ) {
        require_once FPD_DYN_ARCHIVE_PATH . 'includes/class-fpd-archive-widget.php';
        $widgets_manager->register( new \FPD_Archive_Widget() );
    }

    public function enqueue_scripts() {
        wp_enqueue_style( 'fpd-dyn-archive-css', FPD_DYN_ARCHIVE_URL . 'assets/css/fpd-archive.css', [], FPD_DYN_ARCHIVE_VERSION );
        wp_enqueue_script( 'fpd-dyn-archive-js', FPD_DYN_ARCHIVE_URL . 'assets/js/fpd-renderer.js', [], FPD_DYN_ARCHIVE_VERSION, true );
    }
}

add_action( 'plugins_loaded', function() {
    new FPD_Dynamic_Archive_Plugin();
} );
