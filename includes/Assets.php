<?php
namespace WooVP;

class Assets{
    public function __construct() {
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    public function enqueue_assets() {        
        if( is_singular( 'product' ) )
        wp_enqueue_script( 'woovp_frontend_script', WOOVP_PLUGIN_URL . 'assets/js/frontend.js', [ 'jquery' ], WOOVP_VERSION, true );
    }
}