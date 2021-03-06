<?php
/**
* Plugin Name: Woocommerce Paid Product Pages
* Plugin URI: https://github.com/DoramGreenblat/woocommerce-paid-product-pages
* Description: This plugin will allow users to put the product content behind a pay wall
* Version: 1.3
* Author: Doram Greenblat
* Author URI: https://github.com/DoramGreenblat
**/

// Remove BreadCrumbs
remove_action('woocommerce_before_main_content', 'woocommerce_breadcrumb', 20, 0);

// The Below ensures product cannot be purchased more than 1x
add_filter( 'woocommerce_variation_is_purchasable', 'disable_repeat_purchase_for_tamboo', 10, 2 );
add_filter( 'woocommerce_is_purchasable', 'disable_repeat_purchase_for_tamboo', 10, 2 );

# Run function at before_single_product Marker
add_action( 'woocommerce_before_single_product', 'handleProductChangeOncePurchased', 11 );

# Call the function to provide clickable link to product page on order email and payment complete page
add_filter( 'woocommerce_order_item_name', 'display_product_title_as_link', 10, 2 );

function handleProductChangeOncePurchased() {
    global $product, $post;
	
    $product_id=$product->id;
    $string_values = $product->get_attribute('Purchase');

    if (( !empty($string_values) ))   { 	
    	if ( wc_customer_bought_product( wp_get_current_user()->user_email, get_current_user_id(), $product_id ) ) {
  	    removeAllButLongDescription();
	}else{
	    add_filter( 'woocommerce_product_tabs', 'remove_description_tab_unless_purchased', 11 );
	    add_filter( 'woocommerce_is_sold_individually', 'woo_remove_all_quantity_fields', 10, 2 );
	    add_filter( 'wc_product_sku_enabled', '__return_false' );
	}
    }
}

function remove_description_tab_unless_purchased(  ) {
    global $product;
    global $tabs;

    $string_values = $product->get_attribute('Purchase');
    if ( ! empty($string_values) )    { 
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
    
    # Remove variations add to cart
    remove_action( 'woocommerce_single_variation', 'woocommerce_single_variation', 10 );
    remove_action( 'woocommerce_single_variation', 'woocommerce_single_variation_add_to_cart_button', 20 );
    remove_action( 'woocommerce_variable_add_to_cart', 'woocommerce_variable_add_to_cart', 30 );
 
    # Remove SKU
    add_filter( 'wc_product_sku_enabled', '__return_false' );
	
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

/** * @desc Remove in all product type */
function woo_remove_all_quantity_fields( $return, $product ) {
    return true;
}

/**
 * @snippet       Display All Products Purchased by User via Shortcode - WooCommerce
 * @how-to        Get CustomizeWoo.com FREE
 * @author        Rodolfo Melogli
 * @compatible    WooCommerce 3.6.3
 * @donate $9     https://businessbloomer.com/bloomer-armada/
 */
  
add_shortcode( 'my_purchased_products', 'bbloomer_products_bought_by_curr_user' );
   
function bbloomer_products_bought_by_curr_user() {
   
    // GET CURR USER
    $current_user = wp_get_current_user();
    if ( 0 == $current_user->ID ) return;
   
    // GET USER ORDERS (COMPLETED + PROCESSING)
    $customer_orders = get_posts( array(
        'numberposts' => -1,
        'meta_key'    => '_customer_user',
        'meta_value'  => $current_user->ID,
        'post_type'   => wc_get_order_types(),
        'post_status' => array_keys( wc_get_is_paid_statuses() ),
    ) );
   
    // LOOP THROUGH ORDERS AND GET PRODUCT IDS
    if ( ! $customer_orders ) return;
    $product_ids = array();
    foreach ( $customer_orders as $customer_order ) {
        $order = wc_get_order( $customer_order->ID );
        $items = $order->get_items();
        foreach ( $items as $item ) {
            $product_id = $item->get_product_id();
            $product_ids[] = $product_id;
        }
    }
    $product_ids = array_unique( $product_ids );
    $product_ids_str = implode( ",", $product_ids );
   
	add_filter('loop_shop_columns', 'loop_columns', 999);
    if (!function_exists('loop_columns')) {
	    function loop_columns() {
		    return 3; // 3 products per row
	    }
    }
	remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10 );
    // PASS PRODUCT IDS TO PRODUCTS SHORTCODE
    return do_shortcode("[products ids='$product_ids_str']");
   
}



