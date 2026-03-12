<?php
if ( ! defined( 'ABSPATH' ) ) exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class FPD_Archive_Widget extends Widget_Base {

    public function get_name() { return 'fpd_dynamic_archive'; }
    public function get_title() { return __( 'FPD Dynamic Archive', 'fpd-dynamic-archive' ); }
    public function get_icon() { return 'eicon-products'; }
    public function get_categories() { return [ 'general' ]; }

    protected function register_controls() {
        $this->start_controls_section( 'content_section', [
            'label' => __( 'Archive Settings', 'fpd-dynamic-archive' ),
            'tab' => Controls_Manager::TAB_CONTENT,
        ] );

        $this->add_control( 'fpd_base_product', [
            'label' => __( 'FPD Base Product', 'fpd-dynamic-archive' ),
            'type' => Controls_Manager::SELECT2,
            'options' => [],
            'select2options' => [
                'ajax' => [
                    'url' => admin_url( 'admin-ajax.php?action=fpd_get_base_products' ),
                    'dataType' => 'json',
                ],
            ],
            'description' => __( 'Select the FPD product to use as the base layer.', 'fpd-dynamic-archive' ),
        ] );

        $this->add_control( 'posts_per_page', [
            'label' => __( 'Products Per Page', 'fpd-dynamic-archive' ),
            'type' => Controls_Manager::NUMBER,
            'default' => 12,
        ] );

        $this->end_controls_section();
    }

    private function get_fpd_view_data( $product_id ) {
        global $wpdb;
        $views_table = $wpdb->prefix . 'fpd_views';
        $view = $wpdb->get_row( $wpdb->prepare( "SELECT elements, options FROM $views_table WHERE product_id = %d ORDER BY view_order ASC LIMIT 1", $product_id ) );
        
        if ( ! $view ) return false;

        $elements = json_decode( $view->elements, true );
        $options = json_decode( $view->options, true );
        
        $base_image = '';
        foreach ( $elements as $el ) {
            if ( isset( $el['type'] ) && $el['type'] === 'image' ) {
                $base_image = $el['source'];
                break; // Assume first image is base
            }
        }

        // Fallback printing box if not defined in options
        $printing_box = isset( $options['printingBox'] ) ? $options['printingBox'] : [
            'left' => 100, 'top' => 100, 'width' => 300, 'height' => 400
        ];

        return [
            'base_image' => $base_image,
            'box' => $printing_box,
            'stage_width' => isset($options['stageWidth']) ? $options['stageWidth'] : 800,
            'stage_height' => isset($options['stageHeight']) ? $options['stageHeight'] : 800,
        ];
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $fpd_id = $settings['fpd_base_product'];
        
        $fpd_data = $fpd_id ? $this->get_fpd_view_data( $fpd_id ) : false;

        // Standard WooCommerce Query to ensure 3rd party filter compatibility
        $paged = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;
        $args = [
            'post_type' => 'product',
            'posts_per_page' => $settings['posts_per_page'],
            'paged' => $paged,
            'post_status' => 'publish',
        ];

        $query = new \WP_Query( $args );

        if ( $query->have_posts() ) {
            echo '<ul class="products fpd-dynamic-grid">';
            while ( $query->have_posts() ) {
                $query->the_post();
                global $product;
                
                $design_image = wp_get_attachment_image_url( $product->get_image_id(), 'full' );
                
                echo '<li ' . wc_get_product_class( '', $product ) . '>';
                echo '<a href="' . get_permalink() . '" class="woocommerce-LoopProduct-link woocommerce-loop-product__link">';
                
                if ( $fpd_data && $design_image ) {
                    // Output Canvas for Client-Side Rendering
                    printf(
                        '<canvas class="fpd-render-canvas" width="%d" height="%d" 
                            data-base="%s" 
                            data-design="%s" 
                            data-box-x="%d" data-box-y="%d" data-box-w="%d" data-box-h="%d">
                        </canvas>',
                        esc_attr($fpd_data['stage_width']),
                        esc_attr($fpd_data['stage_height']),
                        esc_url($fpd_data['base_image']),
                        esc_url($design_image),
                        esc_attr($fpd_data['box']['left']),
                        esc_attr($fpd_data['box']['top']),
                        esc_attr($fpd_data['box']['width']),
                        esc_attr($fpd_data['box']['height'])
                    );
                } else {
                    // Fallback to standard image
                    echo $product->get_image( 'woocommerce_thumbnail' );
                }

                echo '<h2 class="woocommerce-loop-product__title">' . get_the_title() . '</h2>';
                echo '<span class="price">' . $product->get_price_html() . '</span>';
                echo '</a>';
                woocommerce_template_loop_add_to_cart();
                echo '</li>';
            }
            echo '</ul>';
            
            // Standard Pagination
            woocommerce_pagination();
            wp_reset_postdata();
        }
    }
}
