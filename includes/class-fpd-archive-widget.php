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

        $this->add_control( 'data_source', [
            'label' => __( 'Data Source', 'fpd-dynamic-archive' ),
            'type' => Controls_Manager::SELECT,
            'default' => 'fpd_table',
            'options' => [
                'fpd_table' => __( 'FPD Base Products (Direct Table)', 'fpd-dynamic-archive' ),
                'woo_query' => __( 'WooCommerce Products (Supports Filters)', 'fpd-dynamic-archive' ),
            ],
            'description' => __( 'Choose whether to directly list FPD Base Products, or loop through WooCommerce products.', 'fpd-dynamic-archive' ),
        ] );

        $this->add_control( 'grid_mode', [
            'label' => __( 'Grid Mode', 'fpd-dynamic-archive' ),
            'type' => Controls_Manager::SELECT,
            'default' => 'dynamic_base',
            'options' => [
                'dynamic_base'   => __( 'Woo Products are Base Products (Apply 1 Design)', 'fpd-dynamic-archive' ),
                'dynamic_design' => __( 'Woo Products are Designs (Apply to 1 Base Product)', 'fpd-dynamic-archive' ),
            ],
            'condition' => [ 'data_source' => 'woo_query' ],
        ] );

        $this->add_control( 'fpd_base_product', [
            'label' => __( 'Global FPD Base Product', 'fpd-dynamic-archive' ),
            'type' => Controls_Manager::SELECT2,
            'options' => [],
            'select2options' => [
                'ajax' => [
                    'url' => admin_url( 'admin-ajax.php?action=fpd_get_base_products' ),
                    'dataType' => 'json',
                ],
            ],
            'condition' => [
                'data_source' => 'woo_query',
                'grid_mode' => 'dynamic_design',
            ],
        ] );

        $this->add_control( 'global_design_image', [
            'label' => __( 'Global Design Image', 'fpd-dynamic-archive' ),
            'type' => Controls_Manager::MEDIA,
            'conditions' => [
                'relation' => 'or',
                'terms' => [
                    [ 'name' => 'data_source', 'operator' => '==', 'value' => 'fpd_table' ],
                    [ 'name' => 'grid_mode', 'operator' => '==', 'value' => 'dynamic_base' ],
                ]
            ],
            'description' => __( 'Select the FPD Design to perfectly place onto the printing boxes of the base products.', 'fpd-dynamic-archive' ),
        ] );

        $this->add_control( 'posts_per_page', [
            'label' => __( 'Items Per Page', 'fpd-dynamic-archive' ),
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
        
        $layers = [];
        $printing_box = null;
        $box_z = 50; // Default z-index for design if no box found

        if ( is_array( $elements ) ) {
            foreach ( $elements as $index => $el ) {
                $z = isset($el['parameters']['z']) ? (int)$el['parameters']['z'] : $index;
                
                // Check if an element acts as a bounding box
                if ( isset( $el['title'] ) && strtolower( $el['title'] ) === 'bounding box' ) {
                    $printing_box = [
                        'left' => isset($el['parameters']['left']) ? $el['parameters']['left'] : 0,
                        'top' => isset($el['parameters']['top']) ? $el['parameters']['top'] : 0,
                        'width' => isset($el['parameters']['width']) ? $el['parameters']['width'] : 300,
                        'height' => isset($el['parameters']['height']) ? $el['parameters']['height'] : 400,
                    ];
                    $box_z = $z;
                    continue; // Skip drawing the bounding box itself
                }

                if ( isset( $el['type'] ) && $el['type'] === 'image' ) {
                    $layers[] = [
                        'source' => $el['source'],
                        'params' => isset($el['parameters']) ? $el['parameters'] : [],
                        'z'      => $z
                    ];
                }
            }
        }

        // Fallback to global options printing box
        if ( ! $printing_box && isset( $options['printingBox'] ) ) {
            $printing_box = $options['printingBox'];
        }

        // Ultimate fallback
        if ( ! $printing_box ) {
            $printing_box = [ 'left' => 100, 'top' => 100, 'width' => 300, 'height' => 400 ];
        }

        return [
            'layers' => $layers,
            'box' => $printing_box,
            'box_z' => $box_z,
            'stage_width' => isset($options['stageWidth']) ? $options['stageWidth'] : 800,
            'stage_height' => isset($options['stageHeight']) ? $options['stageHeight'] : 800,
        ];
    }

    private function get_linked_fpd_id( $product_id ) {
        $fpd_products = get_post_meta( $product_id, 'fpd_products', true );
        if ( empty( $fpd_products ) ) {
            $fpd_products = get_post_meta( $product_id, '_fpd_products', true );
        }
        
        if ( is_string( $fpd_products ) ) {
            $decoded = json_decode( $fpd_products, true );
            $fpd_products = is_array( $decoded ) ? $decoded : explode( ',', $fpd_products );
        }
        
        if ( is_array( $fpd_products ) && ! empty( $fpd_products ) ) {
            return (int) $fpd_products[0];
        }
        
        return false;
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $source = $settings['data_source'];

        if ( $source === 'fpd_table' ) {
            $this->render_fpd_table_grid( $settings );
        } else {
            $this->render_woo_query_grid( $settings );
        }
    }

    private function render_fpd_table_grid( $settings ) {
        global $wpdb;
        $table = $wpdb->prefix . 'fpd_products';
        
        // Ensure table exists
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) != $table ) {
            echo '<p>FPD Products table not found.</p>';
            return;
        }

        $limit = $settings['posts_per_page'];
        $paged = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;
        $offset = ( $paged - 1 ) * $limit;

        $products = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table LIMIT %d OFFSET %d", $limit, $offset ) );
        $total = $wpdb->get_var( "SELECT COUNT(ID) FROM $table" );

        $design_image = isset($settings['global_design_image']['url']) ? $settings['global_design_image']['url'] : '';

        if ( $products ) {
            echo '<ul class="products fpd-dynamic-grid">';
            foreach ( $products as $fpd_prod ) {
                $fpd_data = $this->get_fpd_view_data( $fpd_prod->ID );
                if ( ! $fpd_data || empty( $fpd_data['layers'] ) ) continue;

                echo '<li class="product fpd-custom-product">';
                echo '<div class="fpd-product-inner" style="text-align:center;">';
                
                printf(
                    '<canvas class="fpd-render-canvas" width="%d" height="%d" 
                        data-layers="%s" data-design="%s" 
                        data-box-x="%d" data-box-y="%d" data-box-w="%d" data-box-h="%d" data-box-z="%d">
                    </canvas>',
                    esc_attr($fpd_data['stage_width']), esc_attr($fpd_data['stage_height']),
                    esc_attr(json_encode($fpd_data['layers'])), esc_url($design_image),
                    esc_attr($fpd_data['box']['left']), esc_attr($fpd_data['box']['top']),
                    esc_attr($fpd_data['box']['width']), esc_attr($fpd_data['box']['height']),
                    esc_attr($fpd_data['box_z'])
                );

                echo '<h2 class="woocommerce-loop-product__title" style="margin-top:15px;">' . esc_html( $fpd_prod->title ) . '</h2>';
                echo '</div></li>';
            }
            echo '</ul>';

            $num_pages = ceil( $total / $limit );
            if ( $num_pages > 1 ) {
                echo '<nav class="woocommerce-pagination">';
                echo paginate_links( [
                    'base' => get_pagenum_link(1) . '%_%',
                    'format' => 'page/%#%',
                    'current' => $paged,
                    'total' => $num_pages,
                ] );
                echo '</nav>';
            }
        } else {
            echo '<p>No FPD base products found.</p>';
        }
    }

    private function render_woo_query_grid( $settings ) {
        $grid_mode = $settings['grid_mode'];
        $global_design = isset($settings['global_design_image']['url']) ? $settings['global_design_image']['url'] : '';
        $global_fpd_id = $settings['fpd_base_product'];
        $global_fpd_data = $global_fpd_id ? $this->get_fpd_view_data( $global_fpd_id ) : false;

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
                
                $design_image = '';
                $fpd_data = false;

                if ( $grid_mode === 'dynamic_design' ) {
                    $design_image = wp_get_attachment_image_url( $product->get_image_id(), 'full' );
                    $fpd_data = $global_fpd_data;
                } else {
                    $design_image = $global_design;
                    $linked_fpd_id = $this->get_linked_fpd_id( $product->get_id() );
                    if ( $linked_fpd_id ) {
                        $fpd_data = $this->get_fpd_view_data( $linked_fpd_id );
                    }
                }
                
                echo '<li ' . wc_get_product_class( '', $product ) . '>';
                echo '<a href="' . get_permalink() . '" class="woocommerce-LoopProduct-link woocommerce-loop-product__link">';
                
                if ( $fpd_data && ! empty( $fpd_data['layers'] ) ) {
                    printf(
                        '<canvas class="fpd-render-canvas" width="%d" height="%d" 
                            data-layers="%s" data-design="%s" 
                            data-box-x="%d" data-box-y="%d" data-box-w="%d" data-box-h="%d" data-box-z="%d">
                        </canvas>',
                        esc_attr($fpd_data['stage_width']), esc_attr($fpd_data['stage_height']),
                        esc_attr(json_encode($fpd_data['layers'])), esc_url($design_image),
                        esc_attr($fpd_data['box']['left']), esc_attr($fpd_data['box']['top']),
                        esc_attr($fpd_data['box']['width']), esc_attr($fpd_data['box']['height']),
                        esc_attr($fpd_data['box_z'])
                    );
                } else {
                    echo $product->get_image( 'woocommerce_thumbnail' );
                }

                echo '<h2 class="woocommerce-loop-product__title">' . get_the_title() . '</h2>';
                echo '<span class="price">' . $product->get_price_html() . '</span>';
                echo '</a>';
                woocommerce_template_loop_add_to_cart();
                echo '</li>';
            }
            echo '</ul>';
            
            woocommerce_pagination();
            wp_reset_postdata();
        } else {
            echo '<p>No products found.</p>';
        }
    }
}
