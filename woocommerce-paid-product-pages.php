<?php
/**
* Plugin Name: Woocommerce Paid Product Pages
* Plugin URI: https://github.com/DoramGreenblat/woocommerce-paid-product-pages
* Description: This plugin will allow users to put the product content behind a pay wall
* Version: 1.1
* Author: Doram Greenblat
* Author URI: https://github.com/DoramGreenblat
**/

// Remove BreadCrumbs
remove_action('woocommerce_before_main_content', 'woocommerce_breadcrumb', 20, 0);

// These may slow down all pages and may need to go to a more strategic location
// The Below ensures product cannot be purchased more than 1x
add_filter( 'woocommerce_variation_is_purchasable', 'disable_repeat_purchase_for_tamboo', 10, 2 );
add_filter( 'woocommerce_is_purchasable', 'disable_repeat_purchase_for_tamboo', 10, 2 );

# Run function at before_single_product Marker
add_action( 'woocommerce_before_single_product', 'handleProductChangeOncePurchased', 11 );

# Call the function to provide clickable link to product page on order email and payment complete page
add_filter( 'woocommerce_order_item_name', 'display_product_title_as_link', 10, 2 );

function handleProductChangeOncePurchased() {
    global $product,$woocommerce, $post;
	
    $product_id=$product->id;
    $string_values = $product->get_attribute('Purchase');

    if (( !empty($string_values) ))   { 	
    	if ( wc_customer_bought_product( wp_get_current_user()->user_email, get_current_user_id(), $product_id ) ) {
			removeAllButLongDescription();
	    }else{
	        add_filter( 'woocommerce_product_tabs', 'remove_description_tab_unless_purchased', 11 );
	
		}
	}
}

function remove_description_tab_unless_purchased( $tabs, $product, $woocommerce, $post ) {
    // Get the ID for the current product (passed in)
    $product_id = $product->get_id();
    $string_values = $product->get_attribute('Purchase');
    if (( ! empty($string_values) ) && ($string_values == 1 ))   { 
		
            unset( $tabs['description'] );
            return $tabs;
	}
}

function disable_repeat_purchase_for_tamboo( $purchasable, $product ) {
    // Get the ID for the current product (passed in)
    $product_id = $product->get_id();
    
    // Bailout if not a product with Attribute "Purchase"
  	$string_values = $product->get_attribute('Purchase');
    if (( empty($string_values) ))   { 
	    return $purchasable;
	}

    // return false if the customer has bought the product
    if ( wc_customer_bought_product( wp_get_current_user()->user_email, get_current_user_id(), $product_id ) ) {
        $purchasable = false;
    }
    
    // Double-check for variations: if parent is not purchasable, then variation is not
    if ( $purchasable && $product->is_type( 'variation' ) ) {
        $parent = wc_get_product( $product->get_parent_id() );
        $purchasable = $parent->is_purchasable();
    }
    return $purchasable;
}

function removeAllButLongDescription(){
    /*
     * I've removed everything above description field here, left most else intact
     * If more removals required:
     * Full list of single hooks available for removal at 
     * https://businessbloomer.com/woocommerce-visual-hook-guide-single-product-page/
    */
    # Remove Description Tab menu
    remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_product_data_tabs', 10 );
    add_action( 'woocommerce_after_single_product_summary', 'longDescriptionReplay', 10 );
    remove_action( 'woocommerce_before_single_product_summary', 'woocommerce_show_product_images', 20 );
    remove_action( 'woocommerce_product_thumbnails', 'woocommerce_show_product_thumbnails', 20 );
    
    // Right column
    remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_title', 5 );
    remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_rating', 10 );
    remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 10 );
    remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 20 );   
}

function longDescriptionReplay() {
    ?>
        <div class="woocommerce-tabs">
            <?php the_content(); ?>
        </div>
    <?php
}

function display_product_title_as_link( $item_name, $item ) {

    $_product = wc_get_product( $item['variation_id'] ? $item['variation_id'] : $item['product_id'] );

    $link = get_permalink( $_product->get_id() );

    return '<a href="'. $link .'"  rel="nofollow">'. $item_name .'</a>';
}

