<?php 
namespace WooVP;

defined( 'ABSPATH' ) || exit;

class Rankmath_Custom_Sitemap implements \RankMath\Sitemap\Providers\Provider {

    public function handles_type( $type ) {
        return 'custom' === $type;
    }

    public function get_index_links( $max_entries ) {
        return [
            [
                'loc'     => \RankMath\Sitemap\Router::get_base_url( 'custom-sitemap.xml' ),
                'lastmod' => '',
            ]
        ];
    }

    public function get_sitemap_links( $type, $max_entries, $current_page ) {
        $links = [];
    
        // Get variation IDs for sitemap
        $variation_ids = new \WooVP\Class_Woovp_Public();
    
        // Add variation URLs to the sitemap
        foreach ($variation_ids->vh_get_variation_ids() as $variation_id) {
            // Get the variation post object
            $variation_post = get_post($variation_id);
            if ($variation_post) {
                // Get the variation permalink
                $variation_permalink = $variation_ids->custom_variation_permalink('', $variation_post);
                
                // Get the variation image URLs
                $image_urls = $this->get_variation_image_urls($variation_id);
                
                // Add the variation permalink and image URLs to the sitemap
                $links[] = [
                    'loc'    => $variation_permalink,
                    'mod'    => date('Y-m-d'), // Use the current date as the last modified date
                    'images' => $image_urls,
                ];
            }
        }
    
        return $links;
    }
    

    /**
     * Retrieve the variation image URLs.
     *
     * @param int $variation_id The ID of the variation.
     * @return array An array of image URLs.
     */
    public function get_variation_image_urls($variation_id) {
        $image_urls = [];

        // Get the variation object
        $variation = wc_get_product($variation_id);

        if ($variation) {
            // Get the variation image ID
            $image_id = $variation->get_image_id();

            if ($image_id) {
                // Get the image URL
                $image_url = wp_get_attachment_image_url($image_id, 'full');
                if ($image_url) {
                    // Add the image URL to the array
                    $image_urls[] = [
                        'src'   => $image_url,
                        'title' => get_the_title($image_id), // You can adjust this based on your requirements
                    ];
                }
            }
        }

        return $image_urls;
    }

    
}