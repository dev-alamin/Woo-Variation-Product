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

// add_action( 'woocommerce_before_add_to_cart_quantity', 'bbloomer_display_dropdown_variation_add_cart' );
 
function bbloomer_display_dropdown_variation_add_cart() {
   global $product;
   if ( $product->is_type( 'variable' ) ) {
      wc_enqueue_js( "
         $( 'input.variation_id' ).change( function(){
            if( '' != $(this).val() ) {
               var var_id = $(this).val();
               alert('You just selected variation #' + var_id);
            }
         });
      " );
   }
}