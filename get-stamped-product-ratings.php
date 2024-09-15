<?php
/**
 * Plugin Name: Stamped.io Rating Sync
 * Description: Sync WooCommerce product ratings with Stamped.io API every minute, and initialize meta fields on product creation.
 * Version: 1.1
 * Author: Your Name
 */

// Hook into WordPress initialization to schedule the cron job
register_activation_hook(__FILE__, 'stamped_sync_schedule_cron');
register_deactivation_hook(__FILE__, 'stamped_sync_clear_cron');

// Schedule the cron job on plugin activation
function stamped_sync_schedule_cron() {
    if (!wp_next_scheduled('stamped_sync_cron_event')) {
        wp_schedule_event(time(), 'minute', 'stamped_sync_cron_event');
    }
}

// Clear the cron job on plugin deactivation
function stamped_sync_clear_cron() {
    wp_clear_scheduled_hook('stamped_sync_cron_event');
}

// Add custom interval of 1 minute to cron schedules
add_filter('cron_schedules', 'stamped_sync_add_cron_interval');
function stamped_sync_add_cron_interval($schedules) {
    $schedules['minute'] = array(
        'interval' => 60, // Run every 60 seconds (1 minute)
        'display'  => __('Every Minute')
    );
    return $schedules;
}

// The function triggered by the cron job
add_action('stamped_sync_cron_event', 'stamped_sync_update_ratings');

function stamped_sync_update_ratings() {
    global $wpdb;
    $date = new DateTimeImmutable();

    // Query the products that need updates
    $get_products = $wpdb->get_row("
        SELECT wp_postmeta.post_id, wp_posts.post_title FROM wp_postmeta
        LEFT JOIN wp_posts ON wp_postmeta.post_id = wp_posts.ID    
        WHERE wp_postmeta.meta_key = '_pm_stamped_update'
        AND wp_posts.post_status = 'publish' 
        AND wp_posts.post_type = 'product'
        ORDER BY wp_postmeta.meta_value ASC
    ");

    if ($get_products) {
        // Set up the CURL request to Stamped.io API
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://stamped.io/api/widget/badges?isIncludeBreakdown=true&isincludehtml=false',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 6,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode(array(
                "productIds" => array(
                    array("productId" => $get_products->post_id)
                ),
                "apiKey" => "pubkey-YOURAPIKEYHERE",
                "storeUrl" => "www.yoursitedomain.com"
            )),
            CURLOPT_HTTPHEADER => array('Content-Type: application/json'),
        ));

        $response = curl_exec($curl);
        curl_close($curl);

        // Process the API response
        $j_response = json_decode($response);

        if (get_post_meta($get_products->post_id, '_pm_stamped_rating', true)) {
            $rating = unserialize(get_post_meta($get_products->post_id, '_pm_stamped_rating', true));                                
            $current_rating_count = $rating->count;    
        } else {
            $current_rating_count = false;
        }

        foreach ($j_response as $r) {
            $r_serialized = serialize($r);

            // Update the rating if there are changes
            if ($r->productId == $get_products->post_id && $r->count != $current_rating_count) {
                update_post_meta($r->productId, '_pm_stamped_rating', $r_serialized);
            }

            // Log the last update time
            update_post_meta($r->productId, '_pm_stamped_update', $date->getTimestamp());
        }
    }
}

// Hook to add custom metafields upon the initial save of a WooCommerce product
add_action('save_post_product', 'stamped_sync_add_initial_metafields', 10, 3);

function stamped_sync_add_initial_metafields($post_id, $post, $update) {
    // Only proceed for new products (not updates)
    if (!$update) {
        // Check if the metafields already exist
        if (!get_post_meta($post_id, '_pm_stamped_update', true)) {
            // Add the initial stamped update timestamp (current time)
            update_post_meta($post_id, '_pm_stamped_update', time());
        }

        if (!get_post_meta($post_id, '_pm_stamped_rating', true)) {
            // Add an empty serialized object to '_pm_stamped_rating'
            $initial_rating = serialize((object) ['count' => 0, 'rating' => 0]);
            update_post_meta($post_id, '_pm_stamped_rating', $initial_rating);
        }
    }
}
