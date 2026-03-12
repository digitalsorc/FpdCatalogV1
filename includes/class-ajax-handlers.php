<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class FPD_Dyn_Ajax_Handlers {
    public function __construct() {
        add_action( 'wp_ajax_fpd_get_base_products', [ $this, 'get_base_products' ] );
    }

    public function get_base_products() {
        global $wpdb;
        $search = isset( $_POST['q'] ) ? sanitize_text_field( $_POST['q'] ) : '';
        
        $table = $wpdb->prefix . 'fpd_products';
        // Ensure table exists before querying
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) != $table ) {
            wp_send_json_success( ['results' => []] );
        }

        $query = "SELECT ID as id, title as text FROM $table";
        if ( ! empty( $search ) ) {
            $query .= $wpdb->prepare( " WHERE title LIKE %s", '%' . $wpdb->esc_like( $search ) . '%' );
        }
        $query .= " LIMIT 50";

        $results = $wpdb->get_results( $query, ARRAY_A );
        wp_send_json_success( ['results' => $results] );
    }
}
