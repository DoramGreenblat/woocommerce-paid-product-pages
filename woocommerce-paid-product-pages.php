<?php
/**
* Plugin Name: Woocommerce Paid Product Pages
* Plugin URI: https://github.com/DoramGreenblat/woocommerce-paid-product-pages
* Description: This plugin will allow users to put the product content behind a pay wall
* Version: 2.1
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

# Call functions to remove item pricing throughout.
add_filter( 'woocommerce_variable_sale_price_html', 'removeItemPricing', 10, 2 );
add_filter( 'woocommerce_variable_price_html', 'removeItemPricing', 10, 2 );
remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10 );
add_action( 'wp', 'removePricingOnShopAndProducts' );

# Ensure error not seen after purchasing product that it is removed from cart
# This is a bug introduced by the preventative measures taken to ensure product is not purchased multiple times
add_filter( 'woocommerce_add_error', 'customize_wc_errors' );

# Call the function to remove image zoom support
add_action( 'wp', 'removeImageZoomSupport', 100 );

#Ensure Menu items change when logged in 
add_filter( 'wp_nav_menu_items', 'add_loginout_link', 10, 2 );

# Call the functions to rebuild product listings and my lectures
add_action( 'woocommerce_after_shop_loop_item_title', 'rebuildProductListings', 40 );
add_action( 'after_setup_theme', 'removeUglyImageFromListings' );

# Set products per row to one
add_filter( 'woocommerce_output_related_products_args', 'setRelatedProductsItemsPerRow', 9999 );

function beenThereDoneThat(){
    if( ! is_user_logged_in() )
    {
        printf( '<p align=\'center\'><a href="%s">%s</a>', wp_login_url( get_permalink() ),
            __( 'I think you\'ve purchased this product before.<br> Please click here to login and view this content</p>' )
        );
		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 20);
	    remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_product_data_tabs', 10 );
        #add_action( 'woocommerce_after_single_product_summary', 'longDescriptionReplay', 10 );
	    # Remove related products on Products after purchase
	    remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 20 );
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
		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40 );
    }
}

function handleProductChangeOncePurchased() {
    global $product, $post, $woocommerce;
    $product_id=$product->id;
    $string_values = $product->get_attribute('Purchase');

    if (( !empty($string_values) ))   { 	
    	if ( wc_customer_bought_product( wp_get_current_user()->user_email, get_current_user_id(), $product_id ) ) {
  	    	removeAllButLongDescription();
		}else{
            add_filter( 'woocommerce_product_tabs', 'remove_description_tab_unless_purchased', 11 );
            add_filter( 'woocommerce_is_sold_individually', 'woo_remove_all_quantity_fields', 10, 2 );
		    # Remove related products on Products prior to purchase
		    remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 20 );
		    # Remove Price from under title and place above "Add to Cart Button"
		    remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 10 );
	 	    add_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 25 );
	        add_filter( 'wc_product_sku_enabled', '__return_false' );
            
			if (isset($_GET['dejaVu'])){
				beenThereDoneThat();
			}
			tambooReplaceImages($product, $post, $woocommerce);
		}		
    }
}

function tambooReplaceImages($product, $post, $woocommerce){
    if (has_post_thumbnail()) { 
        $image_title = esc_attr(get_the_title(get_post_thumbnail_id())); 
	    $image_link = wp_get_attachment_url(get_post_thumbnail_id()); 
		$exported = wp_get_attachment_url(get_post_thumbnail_id(), array(300,300)); 
	    $image = get_the_post_thumbnail($post->ID, apply_filters('single_product_large_thumbnail_size', 'shop_single'), array( 
	        'title' => $image_title 
 		)); 
        $attachment_count = count($product->get_gallery_image_ids()); 
		if ($attachment_count > 0) { 
	        $gallery = '[product-gallery]'; 
	    } else { 
    	    $gallery = ''; 
        } 
	}
	$regex = '/https?\:\/\/[^\" ]+/i';
	$imageHtml = apply_filters('woocommerce_single_product_image_html', sprintf('<li><a href="%s" itemprop="image" class="woocommerce-main-image zoom" title="%s" data-rel="prettyPhoto' . $gallery . '" rel="prettyPhoto">%s</a></li>', $image_link, $image_title, $image), $post->ID);
	preg_match($regex, $imageHtml, $matches);

	remove_action( 'woocommerce_before_single_product_summary', 'woocommerce_show_product_images', 20 );
	$imgParts = pathinfo( $matches[0] );
   	$img = $imgParts['dirname'] . "/" . $imgParts['filename'] . "-320x320." . $imgParts['extension'];
	$imagePath = get_home_path() . "/" . substr($imgParts['dirname'],strpos($imgParts['dirname'],"/wp-content"));

	if (!file_exists($imagePath . "/" . $imgParts['filename'] . "-320x320."  . $imgParts['extension'] ) ){
		$img = getAlternativeFile($img, $imgParts);	
	}
	echo apply_filters('woocommerce_single_product_image_html', sprintf("<img src='%s' style='width:300px;max-height:300px; align=left;border-radius: 10px;float:left;display: inline-block;'></li>", $img)); 
	
}

function getAlternativeFile($img, $imgParts){
	$imagePath = get_home_path() . "/" . substr($imgParts['dirname'],strpos($imgParts['dirname'],"/wp-content"));
	$scandir = scandir($imagePath);
	$alternativeFound = false;
	foreach ($scandir as $file){
		if ((strpos($file,$imgParts['filename'])) !== false){
			#echo $imgParts['filename'] . "<br>";
			$dimensions = explode("x",preg_replace("/(.jpeg|-)/", "", substr($file,strlen($imgParts['filename'])) ));
			$width = $dimensions[0];
			$height = $dimensions[1];
			if (($width > 300) && ($width < 330) && (abs((1 - $width / $height) * 100 ) < 15)){
				$alternativeFound = true;
				$img = $imgParts['dirname'] . "/" . $imgParts['filename'] . "-" . $width . "x" . $height . "." . $imgParts['extension'];
				break;
			}
		}
	}  
	if (!$alternativeFound){
		$img = $imgParts['dirname'] . "/" . $imgParts['filename'] . "." . $imgParts['extension'];
	}
	return $img;
}

/*
function _get_all_image_sizes() {
	global $_wp_additional_image_sizes;
    $image_sizes = array();
    $default_image_sizes = array( 'thumbnail', 'medium', 'large' );
    foreach ( $default_image_sizes as $size ) {
        $image_sizes[$size] = array(
            'width'  => intval( get_option( "{$size}_size_w" ) ),
            'height' => intval( get_option( "{$size}_size_h" ) ),
            'crop'   => get_option( "{$size}_crop" ) ? get_option( "{$size}_crop" ) : false,
        );
    }
    if ( isset( $_wp_additional_image_sizes ) && count( $_wp_additional_image_sizes ) ) {
        $image_sizes = array_merge( $image_sizes, $_wp_additional_image_sizes );
    }
	return $image_sizes ;
}
*/


function remove_description_tab_unless_purchased(  ) {
	/* As the title says:
	 * Remove description tab and some tweaks:
	 *   - Remove zoom over images
	 */ 
    global $product;
    global $tabs;
    $string_values = $product->get_attribute('Purchase');
    if ( ! empty($string_values) )    {		
        unset( $tabs['description'] );
        return $tabs;
    }
}

# Remove Zoom over images
function removeImageZoomSupport() {
    remove_theme_support( 'wc-product-gallery-zoom' );
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
	# Remove related products on Products after purchase
	remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 20 );
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
	/* Used in emails and order pages to ensure product is clickable
	 */
    $_product = wc_get_product( $item['variation_id'] ? $item['variation_id'] : $item['product_id'] );
    $link = get_permalink( $_product->get_id() );
	
	if (strpos($link, '?') != false ){
	    $link = substr($link, 0, strpos($link, '?'));

	} 
	$link = $link . "?dejaVu=true";	
    return '<a href="'. $link .'"  rel="nofollow">'. $item_name .'</a>';
}

/** * @desc Remove in all product type */
function woo_remove_all_quantity_fields( $return, $product ) {
    return true;
}

function setRelatedProductsItemsPerRow( $args ) {
    $args['posts_per_page'] = 8;
    $args['columns'] = 1;
    return $args;
}
 
function removeItemPricing( $price, $product ) {
    if ( ! is_admin() ){
		$price = '';
	}
    return $price;
}

function removePricingOnShopAndProducts() {    
	if ( is_shop() || is_product_category() )
    {
		add_filter( 'woocommerce_is_purchasable', '__return_false');
        add_filter( 'woocommerce_get_price_html', 'removeItemPricing', 10, 2 );
		remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10 );
    }
}

function customize_wc_errors( $error ) {
    if ( strpos( $error, 'entfernt' ) !== false ) {
        return '';
    } elseif ( strpos( $error, 'removed' ) !== false ) {
    return '';
    } else {
        return $error; 
    }
}

add_filter( 'wpex_get_sidebar', function( $sidebar ) {
	if ( ( function_exists( 'is_cart' ) && is_cart() ) || ( function_exists( 'is_checkout' ) && is_checkout() )    ) {
		$sidebar = 'STUDENT ACCESS';
	}
	return $sidebar;
} );


function add_loginout_link( $items, $args ) {
   if (is_user_logged_in() && $args->menu->name === 'STUDENT ACCESS') {
       $items .= '<li><a href="'. wp_logout_url( get_permalink( woocommerce_get_page_id( 'myaccount' ) ) ) .'">Logout</a></li>';
   }
   elseif (!is_user_logged_in() && $args->menu->name === 'STUDENT ACCESS') {
       $items .= '<li><a href="' . get_permalink( woocommerce_get_page_id( 'myaccount' ) ) . '">Login</a></li>';
   }
   return $items;
}

function getShortDescription(){
    /* 
     * Function to obtain the short description from product and format
     * Used in my products and categories pages
    */
    $excerpt = get_the_excerpt();
	$excerpt = strip_tags($excerpt, '<p><strong><h><h1><h2><h3><br>');
    $excerpt = substr($excerpt, 0, 250);
    return $excerpt."<br>";
}

function rebuildProductListings() {
	/*
	 * Function to rebuild content in my lectures + categories pages
	 * Thumbnail resize and round edges
	*/
	global $product;
	$imgParts = pathinfo(wp_get_attachment_url( $product->get_image_id() ));
	$img = $imgParts['dirname'] . "/" . $imgParts['filename'] . "-150x150." . $imgParts['extension'];
	echo "<img src='" . $img . "' style='max-height:150px;width: auto;height: auto; padding: 20px; align=left;border-radius: 30px;float:left;'></a>";
    echo getShortDescription();
}

add_filter( 'woocommerce_product_add_to_cart_text', function( $text ) {
	global $product;
	if ( $product->is_type( 'variable' ) ) {
		$text = $product->is_purchasable() ? __( 'Read more', 'woocommerce' ) : __( 'Read more', 'woocommerce' );
	}
	if ( is_shop() || is_product_category() ) {
		$text = $product->is_purchasable() ? __( 'Read more', 'woocommerce' ) : __( 'Read more', 'woocommerce' );
    }
	return $text;
}, 10 );

function removeUglyImageFromListings() {
    remove_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_template_loop_product_thumbnail', 10 );
}

# My Products section is all handled below

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
		    return 1; // 3 products per row
	    }
    }
	remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10 );
    // PASS PRODUCT IDS TO PRODUCTS SHORTCODE
    return do_shortcode("[products ids='$product_ids_str']");
   
}



/**
 * @snippet       WooCommerce Add New Tab @ My Account
 * @how-to        Get CustomizeWoo.com FREE
 * @author        Rodolfo Melogli
 * @compatible    WooCommerce 3.5.7
 * @donate $9     https://businessbloomer.com/bloomer-armada/
 */
  
// ------------------
// 1. Register new endpoint to use for My Account page
// Note: Resave Permalinks or it will give 404 error
  
function addPurchasedProductsMenuItem() {
    add_rewrite_endpoint( 'purchased-products', EP_ROOT | EP_PAGES );
}
  
add_action( 'init', 'addPurchasedProductsMenuItem' );
  
  
// ------------------
// 2. Add new query var
function bbloomer_purchased_products_query_vars( $vars ) {
    $vars[] = 'purchased-products';
    return $vars;
}
  
add_filter( 'query_vars', 'bbloomer_purchased_products_query_vars', 0 );  
// ------------------
// 3. Insert the new endpoint into the My Account menu
  
function bbloomer_add_purchased_products_link_my_account( $items ) {
	$reorderedAppended = array_slice($items, 0, 1, true) + array("purchased-products" => "My Lectures") + array_slice($items, 1, count($items) - 1, true) ;
    return $reorderedAppended;
}
  
add_filter( 'woocommerce_account_menu_items', 'bbloomer_add_purchased_products_link_my_account' );
// ------------------
// 4. Add content to the new endpoint  
function bbloomer_purchased_products_content() {
    echo '<h3>My lectures</h3>';
    echo do_shortcode( '[my_purchased_products]' );
}
  
add_action( 'woocommerce_account_purchased-products_endpoint', 'bbloomer_purchased_products_content' );
// Note: add_action must follow 'woocommerce_account_{your-endpoint-slug}_endpoint' format
