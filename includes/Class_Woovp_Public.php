<?php 
namespace WooVP;

/**
 * Class_Woovp_Public
 *
 * Handles the public-facing functionalities of the Woo Variation Product plugin.
 *
 * @package WooVariationProduct
 */
class Class_Woovp_Public {
    /**
     * @var array List of target product IDs.
     */
    private array $target_products;

    /**
     * Constructor.
     * Initializes the class and sets up the necessary hooks.
     */
    public function __construct() {
        $this->target_products = [932,469];
        $this->add_hooks();
    }

    /**
     * Adds all necessary hooks for the public-facing functionalities.
     */
    private function add_hooks() {
        
        add_filter( 'query_vars', [ $this, 'add_custom_query_vars' ]);
        
        add_filter( 'woocommerce_display_product_attributes', [ $this, 'update_flavour_attribute' ], 10, 2);
        
        add_filter( 'the_title', [ $this, 'filter_product_variation_title' ], 10, 2);
        
        add_action( 'save_post_product', [ $this, 'vh_update_variation_ids_transient' ]);
        
        add_action( 'save_post_product_variation', [ $this, 'vh_update_variation_ids_transient' ]);
        
        add_action( 'pre_get_posts', [ $this, 'display_color_variations_as_individual_products' ]);
        
        // add_filter( 'post_type_link', [ $this, 'custom_product_permalink' ], 10, 2);
        
        add_filter( 'post_type_link', [ $this, 'custom_variation_permalink' ], 10, 2);
        
        add_action( 'init', [ $this, 'custom_remove_product_slug' ]);
        
        add_action( 'template_redirect', [ $this, 'custom_variation_description' ]);
        
        add_action( 'wp_footer', [ $this, 'custom_select_color_variation_on_page_load' ]);
        
        add_action( 'pre_get_posts', [ $this, 'include_product_variations_in_search_results' ]);
        
        add_action( 'woocommerce_product_query', [ $this, 'custom_product_category_query' ] );

        add_action( 'woocommerce_before_add_to_cart_quantity', [ $this, 'display_dropdown_variation_add_cart' ] );

        add_action( 'wp_footer', [ $this, 'add_inline_script_for_specific_product' ] );

        add_filter( 'post_type_link', [ $this, 'woo_variation_remove_product_slug' ], 10, 3 );

        add_action( 'pre_get_posts', [ $this, 'change_slug_structure' ], 99 );

        add_filter('request', [ $this, 'handle_taxonomy' ] );

        add_filter('woocommerce_dropdown_variation_attribute_options_args', [ $this, 'vh_select_default_option' ] ,10,1);

        add_filter('woocommerce_add_to_cart_redirect', [ $this, 'custom_add_to_cart_redirect' ] );

        add_action( 'init', [ $this, 'run_sitemap_cron' ] );

        add_action('daily_variation_sitemap_generation', [ $this, 'generate_variation_sitemap' ]);

    }

    /**
     * Schedule the daily variation sitemap generation cron job if it is not already scheduled.
     *
     * This function checks if the 'daily_variation_sitemap_generation' event is already scheduled. 
     * If not, it schedules the event to run daily.
     *
     * @return void
     */
    public function run_sitemap_cron() {
        if ( ! wp_next_scheduled( 'daily_variation_sitemap_generation' ) ) {
            wp_schedule_event( time(), 'daily', 'daily_variation_sitemap_generation' );
        }
    }

    /**
     * Removes the '/product/' base from WooCommerce product URLs.
     *
     * This method modifies the permalink for WooCommerce products by removing the '/product/' base,
     * allowing for cleaner URLs.
     *
     * @param string   $post_link The original post URL.
     * @param WP_Post  $post      The post object.
     * @param bool     $leavename Whether to keep the post name.
     * @return string The modified post URL without the '/product/' base.
     */
    public function woo_variation_remove_product_slug( $post_link, $post, $leavename ) {
        if ( 'product' != $post->post_type || 'publish' != $post->post_status ) {
            return $post_link;
        }
        $post_link = str_replace( '/product/', '/', $post_link );
        return $post_link;
    }

    /**
     * Modifies the main query to include products when the URL structure matches certain criteria.
     *
     * This method adjusts the main query to ensure that requests matching specific URL structures
     * include 'post', 'product', and 'page' post types. This is useful for ensuring that custom
     * URLs are correctly recognized as product pages.
     *
     * @param WP_Query $query The main query object.
     * @return void
     */
    public function change_slug_structure( $query ) {
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

    /**
     * Modifies query variables to handle custom taxonomy rewrites for 'product_cat'.
     *
     * This method checks if the current request matches a slug for a WooCommerce product category
     * ('product_cat') and adjusts the query variables accordingly. If a match is found, it modifies
     * the query to use 'product_cat' instead of the original parameters.
     *
     * Example:
     * - Original URL: domain.com/category-name/
     * - Modified query: array('product_cat' => 'category-name')
     *
     * @param array $vars The array of query variables.
     * @return array Modified array of query variables.
     */
    public function handle_taxonomy( $vars ) {
        global $wpdb;
        
        // Check if certain query variables are present
        if ( ! empty( $vars['pagename'] ) || ! empty( $vars['category_name'] ) || ! empty( $vars['name'] ) || ! empty( $vars['attachment'] ) ) {
            // Determine the slug to check against the 'product_cat' taxonomy
            $slug = ! empty( $vars['pagename'] ) ? $vars['pagename'] : ( ! empty( $vars['name'] ) ? $vars['name'] : ( !empty( $vars['category_name'] ) ? $vars['category_name'] : $vars['attachment'] ) );
            
            // Check if the slug exists in the 'product_cat' taxonomy
            $exists = $wpdb->get_var( $wpdb->prepare( "SELECT t.term_id FROM $wpdb->terms t LEFT JOIN $wpdb->term_taxonomy tt ON tt.term_id = t.term_id WHERE tt.taxonomy = 'product_cat' AND t.slug = %s", array( $slug ) ) );
            
            // If the slug exists in 'product_cat', modify the query variables
            if ( $exists ) {
                $old_vars = $vars;
                $vars = array('product_cat' => $slug);
                
                // Preserve certain original query variables if they exist
                if ( ! empty( $old_vars['paged'] ) || ! empty( $old_vars['page'] ) ) {
                    $vars['paged'] = ! empty( $old_vars['paged'] ) ? $old_vars['paged'] : $old_vars['page'];
                }
                if ( ! empty( $old_vars['orderby'] ) ) {
                    $vars['orderby'] = $old_vars['orderby'];
                }
                if ( ! empty( $old_vars['order'] ) ) {
                    $vars['order'] = $old_vars['order'];
                }
            }
        }
        
        return $vars;
    }

    /**
     * Filters the product variation title.
     *
     * @param string $title The original title.
     * @param int $post_id The post ID.
     * @return string The modified title.
     */
    public function filter_product_variation_title( $title, $post_id ) {
        if ( ! is_admin() && get_post_type( $post_id ) === 'product_variation' ) {
            $attributes = wc_get_product_variation_attributes( $post_id );
            
            if ( ! empty( $attributes ) ) {
                $flavour_key = 'attribute_pa_flavour';
                $color_key   = 'attribute_pa_product-color';
                
                if ( isset( $attributes[$flavour_key] ) || isset( $attributes[$color_key] ) ) {
                    $parent_id    = wp_get_post_parent_id( $post_id );
                    $parent_title = get_the_title( $parent_id );
                    
                    $attribute_names = array();
                    if ( isset( $attributes[$flavour_key] ) ) {
                        $flavour_value = $attributes[$flavour_key];
                        $flavour_term = get_term_by( 'slug', $flavour_value, 'pa_flavour' );
                        if ( $flavour_term ) {
                            $attribute_names[] = $flavour_term->name;
                        }
                    }
                    
                    if ( isset( $attributes[$color_key] ) ) {
                        $color_value = $attributes[$color_key];
                        $color_term = get_term_by( 'slug', $color_value, 'pa_product-color' );
                        if ( $color_term ) {
                            $attribute_names[] = $color_term->name;
                        }
                    }
                    
                    if ( ! empty( $attribute_names ) ) {
                        $title = implode( ' - ', $attribute_names ) . ' - ' . $parent_title;
                    } else {
                        $title = $parent_title;
                    }
                }
            }
        }

        return $title;
    }

    /**
     * Retrieves variation IDs for products within specific categories and having certain attributes.
     *
     * @return array The variation IDs.
     */
    public function vh_get_variation_ids() {
        // Check if the variation IDs are already cached
        // $cached_variation_ids = get_transient('cached_variation_ids');
        // if ($cached_variation_ids !== false) {
        //     return $cached_variation_ids;
        // }

        // If not cached, perform the query
        $category_ids_to_include = array(16,19); // Add the IDs of the categories to include
        $flavour_attribute_slug = 'pa_flavour';
        $color_attribute_slug = 'pa_product-color';

        $args = array(
            'post_type'      => 'product_variation',
            'posts_per_page' => -1,
            'post_parent__in' => $this->target_products,
            // 'tax_query'      => array(
            //     'relation' => 'OR',
            //     array(
            //         'taxonomy' => 'product_cat',
            //         'field'    => 'term_id',
            //         'terms'    => $category_ids_to_include,
            //         'operator' => 'IN',
            //     ),
            // ),
            'meta_query'     => array(
                'relation' => 'OR',
                array(
                    'key'     => 'attribute_' . $flavour_attribute_slug,
                    'compare' => 'EXISTS',
                ),
                array(
                    'key'     => 'attribute_' . $color_attribute_slug,
                    'compare' => 'EXISTS',
                ),
            ),
        );

        $variations_query = new \WP_Query( $args );

        $variation_ids = array();
        if ($variations_query->have_posts()) {
            while ($variations_query->have_posts()) {
                $variations_query->the_post();
                $variation_ids[] = get_the_ID();
            }
            wp_reset_postdata();
        }
        
        set_transient('cached_variation_ids', $variation_ids, DAY_IN_SECONDS);

        return $variation_ids;
    }

    public function generate_variation_sitemap() {
        // Get the variation IDs
        $variation_ids = $this->vh_get_variation_ids();
    
        if (empty($variation_ids)) {
            return;
        }
    
        // Start XML output
        $sitemap = '<?xml version="1.0" encoding="UTF-8"?>';
        $sitemap .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    
        // Loop through each variation and create a URL entry
        foreach ($variation_ids as $variation_id) {
            $permalink = get_permalink($variation_id);
            $lastmod = get_post_modified_time('Y-m-d\TH:i:sP', true, $variation_id);
            
            $sitemap .= '<url>';
            $sitemap .= '<loc>' . esc_url($permalink) . '</loc>';
            $sitemap .= '<lastmod>' . $lastmod . '</lastmod>';
            $sitemap .= '</url>';
        }
    
        // Close XML output
        $sitemap .= '</urlset>';
    
        // Save the sitemap to a file
        $sitemap_path = ABSPATH . 'sitemap-variations.xml';

        if (is_writable(ABSPATH)) {
            $result = file_put_contents($sitemap_path, $sitemap);
    
            if ($result === false) {
                error_log('Failed to write the sitemap file.');
            }
        } else {
            error_log('The directory is not writable.');
        }
    
        return $sitemap_path;
    }
    

    /**
     * Updates the variation IDs transient when a product is saved.
     *
     * @param int $post_id The post ID.
     */
    public function vh_update_variation_ids_transient( $post_id ) {
        $product = wc_get_product($post_id);
        if ($product && ($product->is_type('variable') || $product->is_type('variation'))) {
            $cached_variation_ids = get_transient('cached_variation_ids');
            if ($cached_variation_ids === false) {
                $this->vh_get_variation_ids();
            } else {
                if ($product->is_type('variable')) {
                    $variations = $product->get_children();
                    $new_variation_ids = array_merge($cached_variation_ids, $variations);
                    set_transient('cached_variation_ids', array_unique($new_variation_ids), DAY_IN_SECONDS);
                } elseif ($product->is_type('variation')) {
                    $variation_id = $product->get_id();
                    if (!in_array($variation_id, $cached_variation_ids)) {
                        $cached_variation_ids[] = $variation_id;
                        set_transient('cached_variation_ids', $cached_variation_ids, DAY_IN_SECONDS);
                    }
                }
            }
        }
    }

    /**
     * Displays color variations as individual products on shop and category pages.
     *
     * @param WP_Query $query The WP_Query instance.
     */
    public function display_color_variations_as_individual_products( $query ) {
        $is_shop_category_tag       = is_shop() || is_product_category() || is_product_tag() || is_tax( 'pwd-brand' );
        $is_main_query_not_admin    = $query->is_main_query() && ! is_admin();
        $is_not_disposable_category = is_product_category( 'disposables' );

        if ( ( $is_shop_category_tag ) && ( $is_main_query_not_admin ) && ! $is_not_disposable_category ) {
            $variation_ids = $this->vh_get_variation_ids();

            $product_args = array(
                'post_type'      => 'product',
                'posts_per_page' => -1,
                'fields'         => 'ids',
            );
            $product_ids = get_posts($product_args);

            $all_ids = array_merge($variation_ids, $product_ids);

            $query->set('post_type', array('product', 'product_variation'));
            $query->set('post__in', $all_ids);
        }
    }

    /**
     * Includes product variations in search results.
     *
     * @param WP_Query $query The WP_Query instance.
     * @since 1.0.1 ! is_admin() && $query->is_search() && $query->is_main_query() added
     */
    public function include_product_variations_in_search_results( $query ) {
        if (! is_admin() && $query->is_search() && $query->is_main_query() ) {
            $query->set('post_type', array('product', 'product_variation'));
        }
    }

    /**
     * Customizes the variation permalink structure.
     *
     * @param string $permalink The original permalink.
     * @param WP_Post $post The post object.
     * @return string The modified permalink.
     */
    public function custom_variation_permalink($permalink, $post) {
        if ($post->post_type == 'product_variation') {
            $product_id = wp_get_post_parent_id($post->ID);
            $product    = wc_get_product($product_id);

            if ($product) {
                $variation = wc_get_product($post->ID);
                $attributes = $variation->get_attributes();
                $attribute_slug = '';

                $color_attribute_slug = 'pa_product-colour';
                $flavour_attribute_slug = 'pa_flavour';

                if (isset($attributes[$flavour_attribute_slug])) {
                    $term_slug = $attributes[$flavour_attribute_slug];
                    $term = get_term_by('slug', $term_slug, $flavour_attribute_slug);
                    if ($term) {
                        $attribute_slug = $term->slug;
                    }
                }

                if (isset($attributes[$color_attribute_slug])) {
                    $term_slug = $attributes[$color_attribute_slug];
                    $term = get_term_by('slug', $term_slug, $color_attribute_slug);
                    if ($term) {
                        $attribute_slug = $term->slug;
                    }
                }

                $permalink = home_url('/' . $product->get_slug() . '/' . user_trailingslashit($attribute_slug));
            }
        }
        return $permalink;
    }

    private function get_attribute_slug( $product ) {
        if( ! $product ) return;

        $variation = wc_get_product($product);
        $attributes = $variation->get_attributes();
        $attribute_slug = '';
        $color_attribute_slug   = 'pa_product-colour';
        $flavour_attribute_slug = 'pa_flavour';

        if (isset($attributes[$flavour_attribute_slug])) {
            $term_slug = $attributes[$flavour_attribute_slug];
            $term = get_term_by('slug', $term_slug, $flavour_attribute_slug);
            if ($term) {
                $attribute_slug = $term->slug;
            }
        }

        if (isset($attributes[$color_attribute_slug])) {
            $term_slug = $attributes[$color_attribute_slug];
            $term = get_term_by('slug', $term_slug, $color_attribute_slug);
            if ($term) {
                $attribute_slug = $term->slug;
            }
        }

        return user_trailingslashit($attribute_slug);
    }

    /**
     * Adds custom rewrite rules to remove the product slug from WooCommerce product URLs.
     *
     * This method generates a regex pattern based on all existing product slugs and creates
     * custom rewrite rules to match URLs without the product base. The rules handle product
     * attributes such as flavour and product colour.
     *
     * Example:
     * - Original URL: domain.com/product/product-slug/
     * - New URL: domain.com/product-slug/attribute-value/
     *
     * The custom rewrite rules ensure that URLs without the product base are correctly
     * redirected to the appropriate product pages with the specified attributes.
     *
     * @return void
     */
    public function custom_remove_product_slug() {
        // Retrieve all product slugs
        $product_slugs = $this->get_all_product_slugs();
        $product_slugs_regex = implode('|', array_map('preg_quote', $product_slugs));

        // If there are product slugs, add the custom rewrite rules
        if ($product_slugs_regex) {
            add_rewrite_rule(
                '^(' . $product_slugs_regex . ')/([^/]+)/?$',
                'index.php?product=$matches[1]&attribute_pa_flavour=$matches[2]',
                'top'
            );

            add_rewrite_rule(
                '^(' . $product_slugs_regex . ')/([^/]+)/?$',
                'index.php?product=$matches[1]&attribute_pa_product-colour=$matches[2]',
                'top'
            );
        }
    }

    /**
     * Retrieves all slugs of published WooCommerce products.
     *
     * This method queries the WordPress database to fetch the slugs (post names) of all published
     * products. It returns an array of product slugs, which can be used for generating custom
     * rewrite rules or other purposes.
     *
     * @global wpdb $wpdb The WordPress database abstraction object.
     * @return array An array of slugs for all published WooCommerce products.
     */
    private function get_all_product_slugs() {
        global $wpdb;

        // Query to fetch slugs of all published WooCommerce products
        $product_slugs = $wpdb->get_col("
            SELECT post_name
            FROM $wpdb->posts
            WHERE post_type = 'product' AND post_status = 'publish'
        ");
        
        return $product_slugs;
    }

    /**
     * Adds custom query variables.
     *
     * @param array $vars The existing query variables.
     * @return array The modified query variables.
     */
    public function add_custom_query_vars($vars) {
        $vars[] = 'attribute_pa_flavour';
        $vars[] = 'attribute_pa_product-colour';
        return $vars;
    }

    /**
     * Retrieves the variation description instead of the parent product description.
     */
    public function custom_variation_description() {
        if ( is_singular('product') ) {
            global $post;
    
            $product_ids = $this->target_products;
            $product     = wc_get_product($post->ID);

            if ($product && $this->available_product( $product->get_id(), $product_ids ) ) {
                $product_data = $this->vh_get_variation_data();

                if ($product_data) {
                    $variation_id      = $product_data->ID;
                    $parent_product_id = $product_data->post_parent;

                    if ($variation_id) {
                        // Get the variation description
                        $variation_description = $this->get_variation_description($variation_id);

    
                        // Get the post content
                        $post_content = $post->post_content;
    
                        // Check if post content contains the FAQ block
                        $faq_block_pattern = '/<!--\s*wp:rank-math\/faq-block\s*(.*?)-->(.*?)<!--\s*\/wp:rank-math\/faq-block\s*-->/s';
                        if (preg_match($faq_block_pattern, $post_content, $matches)) {
                            // Concatenate FAQ block content with variation description
                            $variation_description .= '<div class="faq-block">' . $matches[0] . '</div>';
                        }
    
                        // Update the post content
                        $post->post_content = $variation_description;
                    }
                }
            }
        }
    }    

    /**
     * Checks if a given product ID is in the list of available product IDs.
     *
     * This function checks if the provided product ID exists within the specified
     * array of product IDs. It returns true if the product ID is found in the array,
     * and false otherwise.
     *
     * @param int $product The product ID to check.
     * @param array $product_ids Optional. An array of product IDs to check against. Default is an empty array.
     * @return bool True if the product ID is in the array, false otherwise.
     */
    private function available_product(int $product, array $product_ids = []) : bool {
        return in_array($product, $product_ids);
    }

    /**
     * Retrieves the product slug from the URL.
     *
     * @param string $url The URL.
     * @return string The product slug.
     */
    private function get_product_slug() {
        $current_url = home_url(add_query_arg(array(), $GLOBALS['wp']->request));

        $url_parts = explode('/', $current_url);
        return end($url_parts);
    }

    /**
     * Formats the product slug for database query.
     * This function sanitizes and formats the product slug retrieved
     * from the URL to match the format expected for querying variations
     * in the database. It replaces hyphens with spaces, capitalizes
     * the first letter of each word, and prefixes either "Product Colour: "
     * or "Flavour: " based on the product attribute.
     *
     * @return string The formatted product slug.
     */
    private function get_formatted_product_slug(){
        $product_slug = $this->get_product_slug();
        $product_slug = sanitize_text_field($product_slug);
        $product_slug = str_replace('-', ' ', $product_slug);
        $product_slug = ucwords($product_slug);

        $prefix = '';
        if ( $this->hasAttribute( 'product-colour' ) ) {
            $prefix = 'Product Colour: ';
        } elseif ( $this->hasAttribute( 'flavour' ) ) {
            $prefix = 'Flavour: ';
        }

        $formatted_slug = $prefix . $product_slug;

        return $formatted_slug;
    }

    /**
     * Checks if a WooCommerce variable product has a specified attribute term assigned.
     *
     * @param int $product_id The ID of the WooCommerce product.
     * @param string $attribute The attribute name to check (e.g., 'pa_flavour', 'pa_color').
     * @return bool True if the variable product has the specified attribute term assigned, false otherwise.
     */
    private function hasAttribute( $attribute ) {
        $product_id = get_queried_object_id();
        $product    = wc_get_product( $product_id );

        return !empty($product) && $product->get_attribute( $attribute );
    }

    /**
     * Retrieves variation description from the database.
     *
     * @param int $variation_id The variation ID.
     * @return string|null The variation description.
     */
    private function get_variation_description( $variation_id ) {
        global $wpdb;
        $variation_description = $wpdb->get_var($wpdb->prepare("
            SELECT meta_value
            FROM {$wpdb->postmeta}
            WHERE post_id = %d
            AND meta_key = '_variation_description'
        ", $variation_id));
        return $variation_description;
    }

    /**
     * Updates the product attributes to include custom attributes.
     *
     * @param array $attributes The existing attributes.
     * @param WC_Product $product The product object.
     * @return array The modified attributes.
     */
    public function update_flavour_attribute( $attributes, $product ) {
        $available_product = $this->available_product( $product->get_id(), $this->target_products );

        if ( $product->is_type( 'variable' ) && $available_product ) {
            $attrs = $this->vh_get_variation_data();
            if ( ! $attrs ) return $attributes;

            $flavour = str_replace('Flavour:', '', $attrs->post_excerpt);
            $color   = str_replace('Product Colour:', '', $attrs->post_excerpt);

            if ( isset( $attributes['attribute_pa_flavour'] ) ) {
                unset( $attributes['attribute_pa_flavour'] );
                $attributes['attribute_pa_flavour'] = array(
                    'label' => __('Flavour', 'woocommerce'),
                    'value' => $flavour,
                );
            }

            if ( isset( $attributes['attribute_pa_product-colour'] ) ) {
                $attributes['attribute_pa_product-colour'] = array(
                    'label' => __('Colour', 'woocommerce'),
                    'value' => $color,
                );
            }
        }

        return $attributes;
    }

    /**
     * Retrieves the data of a variation.
     *
     * @return WP_Post|null The variation data.
     */
    public function vh_get_variation_data() {
        global $wpdb;
    
        if ( ! is_singular( 'product' ) ) return;
    
        $product_id = get_queried_object_id();
    
        $product_slug = $this->get_formatted_product_slug();
    
        if ( ! $product_slug) return null;
    
        // Query the database for variation data
        $product_data = $wpdb->get_row($wpdb->prepare("
        SELECT ID, post_parent, post_excerpt
        FROM {$wpdb->posts}
        WHERE post_excerpt LIKE %s
        AND post_type = 'product_variation'
        AND post_parent = %d
        ", '%' . $wpdb->esc_like($product_slug) . '%', $product_id));
    
    
        return $product_data;
    }

    /**
     * Customizes the product variation selection on page load.
     */
    public function custom_select_color_variation_on_page_load() {
        if (is_singular('product')) {
            global $wp;
            $url = home_url(add_query_arg(array(), $wp->request));
            $url_parts = explode('/', $url);
            $attribute_slug = end($url_parts);
            if( count( $url_parts ) < 5 ) return; 

            if ($attribute_slug) {
                ?>
                <script type="text/javascript">
                    jQuery(document).ready(function($) {
                        function setAttributeValue(attributeName, attributeSlug) {
                            var variationForm = $('form.variations_form');
                            var attributeSelect = variationForm.find('select[name="attribute_' + attributeName + '"]');
                            if (attributeSelect.length) {
                                attributeSelect.val(attributeSlug);
                                attributeSelect.trigger('change');
                            }
                        }
                        setAttributeValue('pa_flavour', '<?php echo $attribute_slug; ?>');
                        setAttributeValue('pa_product-colour', '<?php echo $attribute_slug; ?>');
                    });
                </script>
                <?php
            }
        }
    }

    /**
	 * Exclude category products
	 * 
	 * @param array $exclude_category_fields
	 * @return array $variable_product_child_ids Child product ids of excluded category
	 */
	public function exclude_category_products ( $exclude_category_fields ) {
		//print_r($exclude_category_fields);
		$args = array(
			'type' => 'variable',
			'limit' => -1,
			'tax_query' => array(
				array(
					'taxonomy' => 'product_cat',
					'field'    => 'term_id',
					'terms'    => $exclude_category_fields,
				),
			)
		);

		$variable_product_parent_ids = wc_get_products( $args );

		$variable_product_child_ids = array();

		// Get child products of parent products
		foreach ($variable_product_parent_ids as $variable_product_parent_id) {
			$variable_product_child_ids = array_merge($variable_product_child_ids, $variable_product_parent_id->get_children());
		}

		return $variable_product_child_ids;
	}

    public function custom_product_category_query( $query ) {
        if ( ! is_admin() && $query->is_main_query() && is_product_category( 'disposables') ) {

            $exclude_ids = $this->exclude_category_products( 'disposables' );

            $query->set( 'post__not_in', array_unique($exclude_ids) );
            $query->set( 'post_type', array( 'product' ) );
        }
    }

    public function display_dropdown_variation_add_cart() {
        global $product;
    
        if ( $product->is_type( 'variable' ) ) {
            wc_enqueue_js( "
                $( 'input.variation_id' ).change( function() {
                    if ( $(this).val() !== '' ) {
                        var variationId = $(this).val();
                        var nonce = '" . wp_create_nonce( 'handle_variation_change' ) . "';
    
                        $.ajax({
                            url: woovpAjaxVar.url,
                            type: 'POST',
                            data: {
                                action: 'handle_variation_change',
                                variation_id: variationId,
                                // nonce: nonce
                            },
                            success: function(response) {
                                if (response.success) {
                                    // Use the flavour value from the response
                                    var flavourValue = response.data.flavour_value;
                                    var parentProductTitle = response.data.parent_product_title;
                                    if( flavourValue != ''){
                                        // Update the product title with the flavour value
                                        var newTitle = flavourValue + ' - ' + parentProductTitle;
                                        $('.product_title').text(newTitle);
                                    }
                                } else {
                                    console.error('Error:', response.data);
                                }
                            },
                            error: function(xhr, status, error) {
                                console.error('AJAX error:', status, error);
                            }
                        });
                    }
                });
            " );
        }
    }

    public function add_inline_script_for_specific_product() {
        if ( is_product() ) {
            $product_id = get_queried_object_id();
    
            if ( $this->available_product( $product_id, $this->target_products ) ) {
                ?>
               <script>
                    jQuery(document).ready(function($) {
                    // Function to copy text from one element to another
                    function copyVariationDescription() {
                        var variationDescription = $('.single-product-details .with-flavour-picker .woocommerce-variation-description').html();
                        if (variationDescription) {
                            $('#tab-description').html('');
                            $('#tab-description').html(variationDescription);
                            $('#tab-description').prepend('<h2>Description</h2>');
                        }
                    }

                    // Trigger the function when a variation is selected
                    $('.variations_form').on('show_variation', function(event, variation) {
                        copyVariationDescription();
                        copyFlavourToAdditionalInfo();
                    });

                    // Function to copy text from the selected flavour to the additional information tab
                    function copyFlavourToAdditionalInfo() {
                        var selectedFlavour = $('.single-product .selected_flavour').text();
                        $('.woocommerce-product-attributes-item--attribute_pa_flavour .woocommerce-product-attributes-item__value').html('<p>' + selectedFlavour + '</p>');
                    }

                    // Trigger the function on click of the additional information tab link
                    $('#tab-title-additional_information a').on('click', function(event) {
                        event.preventDefault(); // Prevent default anchor behavior
                        copyFlavourToAdditionalInfo();
                    });

                    // Trigger the function on page load in case a variation is pre-selected
                    // copyVariationDescription();
                    });
                </script>
                    <?php 
                // wp_add_inline_script('woovp_frontend_script', $inline_script);
            }
        }
    }

    public function vh_select_default_option( $args ){

        if(count($args['options']) > 0 && $args['attribute'] == 'pa_nicotine-strength' ) //Ensure product variation isn't empty
            $args['selected'] = $args['options'][0];
        return $args;
    }

    public function custom_add_to_cart_redirect($url) {
        // Get the product ID and variation ID from the request
        $product_id   = isset($_REQUEST['product_id']) ? $_REQUEST['product_id'] : 0;
        $variation_id = isset($_REQUEST['variation_id']) ? $_REQUEST['variation_id'] : 0;
        // $variation_id = $this->vh_get_variation_data();
        // $variation_id = $variation_id->ID;
        // $slug = $this->get_attribute_slug( get_the_ID() );

        // Check if the product is a variation
        if ($variation_id > 0) {
            // Get the parent product URL
            $product = wc_get_product($product_id);
            if ($product) {
                $url = $product->get_permalink();
            }
        }
    
        return $url;
    }
}