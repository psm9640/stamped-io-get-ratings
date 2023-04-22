<?php

global $wpdb;
$date = new DateTimeImmutable();

$get_products = $wpdb->get_row("
			SELECT wp_postmeta.post_id, wp_posts.post_title FROM wp_postmeta
			LEFT JOIN wp_posts ON wp_postmeta.post_id = wp_posts.ID	
			WHERE wp_postmeta.meta_key = '_pm_stamped_update'
			AND wp_posts.post_status = 'publish' 
			AND wp_posts.post_type = 'product'
			ORDER BY wp_postmeta.meta_value ASC
			");

if($get_products):	
	
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
	  CURLOPT_POSTFIELDS =>'{
	    "productIds": [
	        {
	            "productId": "'.$get_products->post_id.'"
	        }
	    ],
	    "apiKey": "pubkey-YOURAPIKEYHERE",
	    "storeUrl": "www.yoursitedomain.com"
	}',
	  CURLOPT_HTTPHEADER => array(
	    'Content-Type: application/json'
	  ),
	));
	
	$response = curl_exec($curl);
	curl_close($curl); 

	if( get_post_meta($get_products->post_id, '_pm_stamped_rating', true) ):
		$rating = unserialize(get_post_meta($get_products->post_id, '_pm_stamped_rating', true));								
		$current_rating_count = $rating->count;	
	else:
		$current_rating_count = false;
	endif;

	
	$j_response = json_decode($response);

	
	foreach($j_response AS $r):
	
		$r_serialized = serialize($r);

		if( $r->productId == $get_products->post_id AND $r->count != $current_rating_count ):
			update_post_meta($r->productId, '_pm_stamped_rating', $r_serialized);
			
		else:
			echo 'No update needed!';
		endif;
		
		//log last update time	
		update_post_meta( $r->productId, '_pm_stamped_update', $date->getTimestamp() );	
		
	endforeach;

endif; 
