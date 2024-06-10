<?php 
namespace WooVP;

class Ajax {
    public function __construct() {
        add_action('wp_ajax_handle_variation_change', [ $this, 'handle_variation_change' ] );
        add_action('wp_ajax_nopriv_handle_variation_change', [ $this, 'handle_variation_change' ] );
    }

    public function handle_variation_change() {
        // Check if nonce is set and valid
        // if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'variation_nonce' ) ) {
        //     wp_send_json_error( 'Invalid nonce' );
        //     wp_die();
        // }
    
        // Validate variation_id
        if ( ! isset( $_POST['variation_id'] ) || ! is_numeric( $_POST['variation_id'] ) ) {
            wp_send_json_error( 'Invalid variation ID' );
            wp_die();
        }
    
        $variation_id = intval( $_POST['variation_id'] );
    
        // Get the variation product
        $variation = wc_get_product( $variation_id );
        if ( ! $variation || 'variation' !== $variation->get_type() ) {
            wp_send_json_error( 'Invalid product variation' );
            wp_die();
        }
    
        // Get the parent product
        $parent_id = $variation->get_parent_id();
        $parent_product = wc_get_product( $parent_id );
        $parent_product_title = $parent_product ? $parent_product->get_title() : '';
        $flavour_value = $variation->get_attribute( 'flavour' );
        $colour_value = $variation->get_attribute( 'product-colour' );
        $color_flavour_value = $flavour_value ? $flavour_value : $colour_value;
        $has_color_flavour = $color_flavour_value ? $color_flavour_value : '';
    
        // Prepare response data
        $variation_data = array(
            'variation_id'   => $variation_id,
            'variation_name' => $variation->get_name(),
            'flavour_value' => str_replace( '&amp;', '&', $has_color_flavour ),
            'parent_product_title' => $parent_product_title,
        );
    
        // Send JSON response
        wp_send_json_success( $variation_data );
    
        wp_die(); // Required to terminate immediately and return a proper response
    }
}