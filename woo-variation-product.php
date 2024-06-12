<?php 
/**
 * Plugin Name: Woo Variation Product for Woocommerce
 * Plugin URI:  almn.me/plugins/woo-variation-product
 * Description: To make individual products from variation, variable product type. This will help you to increase link for google.
 * Version:     1.0.0
 * Author:      Al Amin
 * Author URI:  https://almn.me
 * Text Domain: woovp
 * Domain Path: /languages
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 *
 * Requires at least: 5.4
 * Requires PHP: 7.0
 * Requires Plugins: Woocommerce
 *
 * @package     WooVariationProduct
 * @author      Al Amin
 * @copyright   2024 AwesomeDigitalSolution
 * @license     GPL-2.0+
 *
 * @wordpress-plugin
 *
 * Prefix:      woovp
 */

defined( 'WPINC' ) || die( 'No script kiddies please');
defined( 'ABSPATH' ) || die( 'No script kiddies please!' );

/**
 * Autoloader Main function
 *
 * @param [type] $class
 * @return void
 */
function woovp_autoloader( $class ) {
    $namespace = 'WooVP'; // Core namespace \ It could be ParentProject/SubProject;
    $base_dir  = __DIR__ . '/includes/';

    $class = ltrim( $class, '\\' );

    if ( strpos( $class, $namespace . '\\' ) === 0 ) {
        $relative_class = substr( $class, strlen( $namespace . '\\' ) );
        $file           = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';
        if ( file_exists( $file ) ) {
            require $file;
        }
    }
}

spl_autoload_register( 'woovp_autoloader' );

/**
 * Woo Variation Product Class
 *
 * Handles initialization, activation, and functionality for the Woo Variation Product plugin.
 *
 * @package WooVariationProduct
 */

 final class Woo_Variation_Product {
    /**
     * The single instance of the class.
     *
     * @var Woo_Variation_Product
     */
    private static $instance = null;

    /**
     * Constructor method.
     * Adds the necessary hooks and initializes the plugin.
     */
    public function __construct() {
        register_deactivation_hook( __FILE__, [ $this, 'deactivate_plugin' ] );

        add_action('plugins_loaded', [$this, 'woovp_plugin_init']);
    }

    /**
     * Ensures only one instance of the class is loaded or can be loaded.
     *
     * @return Woo_Variation_Product - Main instance.
     */
    public static function init() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Activates the plugin.
     * Defines constants and initializes necessary components.
     *
     * @return void
     */
    private function activate() {
        if ( ! $this->is_woocommerce_active() ) {
            add_action('admin_notices', [$this, 'woocommerce_inactive_notice']);
            deactivate_plugins(plugin_basename(__FILE__));
            return;
        }

        $this->define_constants();
        new \WooVP\Class_Woovp_Public();
        new \WooVP\Assets();
        new \WooVP\Ajax();
        // Additional methods can be called here, such as enqueue scripts or initializing classes.
    }

    /**
     * Defines constants used throughout the plugin.
     *
     * @return void
     */
    private function define_constants() {
        define('WOOVP_VERSION', '1.0.0');
        define('WOOVP_PLUGIN', __FILE__);
        define('WOOVP_PLUGIN_URL', plugin_dir_url(__FILE__));
        define('WOOVP_PLUGIN_PATH', plugin_dir_path(__FILE__));
    }

    /**
     * Loads the plugin's localization files.
     *
     * @return void
     */
    public function woovp_plugin_init() {
        load_plugin_textdomain('woovp', false, dirname(plugin_basename(__FILE__)) . '/languages');

        $this->activate();
    }

    public function deactivate_plugin() {
        // Clear the scheduled event on deactivation
        $timestamp = wp_next_scheduled('daily_variation_sitemap_generation');
        wp_unschedule_event($timestamp, 'daily_variation_sitemap_generation');
    }

        /**
     * Checks if WooCommerce is active.
     *
     * @return bool
     */
    private function is_woocommerce_active() {
        return in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')), true);
    }

    /**
     * Displays an admin notice if WooCommerce is not active.
     *
     * @return void
     */
    public function woocommerce_inactive_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php esc_html_e('Woo Variation Product requires WooCommerce to be active. Please activate WooCommerce first.', 'woovp'); ?></p>
        </div>
        <?php
    }
}

/**
 * Initializes the Woo Variation Product plugin.
 *
 * @return void
 */
function woo_variation_product() {
    Woo_Variation_Product::init();
}

// Kick-off the plugin
woo_variation_product();

function wsp_remove_slug( $post_link, $post, $leavename ) {
    if ( 'product' != $post->post_type || 'publish' != $post->post_status ) {
        return $post_link;
    }
    $post_link = str_replace( '/product/', '/', $post_link );
    return $post_link;
}
add_filter( 'post_type_link', 'wsp_remove_slug', 10, 3 );

function change_slug_structure( $query ) {
    if ( ! $query->is_main_query() || 2 != count( $query->query ) || ! isset( $query->query['page'] ) ) {
        return;
    }
    if ( ! empty( $query->query['name'] ) ) {
        $query->set( 'post_type', array( 'post', 'product', 'page' ) );
    } elseif ( ! empty( $query->query['pagename'] ) && false === strpos( $query->query['pagename'], '/' ) ) {
        $query->set( 'post_type', array( 'post', 'product', 'page' ) );
        // We also need to set the name query var since redirect_guess_404_permalink() relies on it.
        $query->set( 'name', $query->query['pagename'] );
    }
}
add_action( 'pre_get_posts', 'change_slug_structure', 99 );

add_filter('request', function( $vars ) {
	global $wpdb;
	if( ! empty( $vars['pagename'] ) || ! empty( $vars['category_name'] ) || ! empty( $vars['name'] ) || ! empty( $vars['attachment'] ) ) {
		$slug = ! empty( $vars['pagename'] ) ? $vars['pagename'] : ( ! empty( $vars['name'] ) ? $vars['name'] : ( !empty( $vars['category_name'] ) ? $vars['category_name'] : $vars['attachment'] ) );
		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT t.term_id FROM $wpdb->terms t LEFT JOIN $wpdb->term_taxonomy tt ON tt.term_id = t.term_id WHERE tt.taxonomy = 'product_cat' AND t.slug = %s" ,array( $slug )));
		if( $exists ){
			$old_vars = $vars;
			$vars = array('product_cat' => $slug );
			if ( !empty( $old_vars['paged'] ) || !empty( $old_vars['page'] ) )
				$vars['paged'] = ! empty( $old_vars['paged'] ) ? $old_vars['paged'] : $old_vars['page'];
			if ( !empty( $old_vars['orderby'] ) )
	 	        	$vars['orderby'] = $old_vars['orderby'];
      			if ( !empty( $old_vars['order'] ) )
 			        $vars['order'] = $old_vars['order'];	
		}
	}
	return $vars;
});
















// add_action('init', 'custom_dynamic_rewrite_rules');

// function custom_dynamic_rewrite_rules() {
//     $product_slugs = get_all_product_slugs();
//     $product_slugs_regex = implode('|', array_map('preg_quote', $product_slugs));

//     if ($product_slugs_regex) {
//         add_rewrite_rule(
//             '^(' . $product_slugs_regex . ')/([^/]+)/?$',
//             'index.php?product=$matches[1]&attribute_pa_flavour=$matches[2]',
//             'top'
//         );
//     }
// }

// function get_all_product_slugs() {
//     global $wpdb;
//     $product_slugs = $wpdb->get_col("
//         SELECT post_name
//         FROM $wpdb->posts
//         WHERE post_type = 'product' AND post_status = 'publish'
//     ");
//     return $product_slugs;
// }