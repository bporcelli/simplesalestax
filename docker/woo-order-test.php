<?php
/**
 * Plugin Name: WooCommerce Order Test - WP Fix It
 * Plugin URI:  https://www.wpfixit.com
 * Description: A testing payment gateway for WooCommerce to see if your checkout works like it should. You can complete a full and real checkout on your site to see if everything is running smoothly.
 * Author:      WP Fix It
 * Author URI:  https://www.wpfixit.com
 * Version:     2.2
 */
 
//Load up styling for plugin needs

function wpfi_order_test_css() {

    wp_enqueue_style( 'myCSS', plugins_url( 'wcot.css', __FILE__ ) );

}

add_action('admin_print_styles', 'wpfi_order_test_css'); 

function sb_wc_test_init() {
	if (!class_exists('WC_Payment_Gateway')) {
		return;
	}
	
	class WC_Gateway_wpfi_test extends WC_Payment_Gateway {
	
		public function __construct() {
			$this->id = 'wpfi_test';
			$this->has_fields = false;
			$this->method_title = __( 'Test Mode', 'woocommerce' );
			$this->init_form_fields();
			$this->init_settings();
			$this->title = 'Order Testing by <a href="https://www.wpfixit.com/" target="_blank"><strong> WP Fix It</strong></a>';
	
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		}
		
		function init_form_fields() {
			$this->form_fields = array(
				'enabled' => array(
					'title' => __( 'Enable or Disable', 'woocommerce' ),
					'type' => 'checkbox',
					'label' => __( 'Enable this testing gateway', 'woocommerce' ),
					'default' => 'yes'
				)
			);
		}
	    
		
		public function admin_options() {
			echo '	<h3><span class="dashicons dashicons-money-alt"></span> Order Testing Gateway</h3>
			<p>Enable this below to test the checkout process on your site.  Only admin users will see this option on the checkout page.<br><br>
			This feature is brought to you by <a title="WP Fix It Reviews" href="https://www.wpfixit.com" target="_blank" ><b>WP Fix It</b></a> proving 24/7 instant WordPress support since 2009.</p>
				<table class="form-table">';
				
			$this->generate_settings_html();
			
			echo '	</table>';
		}
	
		public function process_payment( $order_id ) {
			global $woocommerce;
	    
			$order = new WC_Order( $order_id );
			$order->payment_complete();
			$order->reduce_order_stock();
			$woocommerce->cart->empty_cart();
	
			return array(
				'result' => 'success',
				//'redirect' => add_query_arg('key', $order->order_key, add_query_arg('order', $order->id, get_permalink(woocommerce_get_page_id('thanks')))),
				'redirect' => $order->get_checkout_order_received_url()
			);
		}
	
	}	
	function add_wpfi_test_gateway( $methods ) {
		if (current_user_can('administrator') || WP_DEBUG) {
			$methods[] = 'WC_Gateway_wpfi_test';
		}
		
		return $methods;
	}
	
	add_filter('woocommerce_payment_gateways', 'add_wpfi_test_gateway' );
	
}
add_filter('plugins_loaded', 'sb_wc_test_init' );
/* Activate the plugin and do something. */
function wcot_plugin_action_links( $links ) {
$links = array_merge( array(
'<a href="' . esc_url( admin_url( '/admin.php?page=wc-settings&tab=checkout&section=wpfi_test' ) ) . '">' . __( '<b>Settings</b>', 'textdomain' ) . '</a>'
), $links );
$links = array_merge( array(
'<a href="https://www.wpfixit.com/" target="_blank">' . __( '<b><span id="p-icon" class="dashicons dashicons-money-alt"></span>  <span class="ticket-link" >GET HELP</span></b>', 'textdomain' ) . '</a>'
), $links );
return $links;
}
add_action( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wcot_plugin_action_links' );
/* Activate the plugin and do something. */
register_activation_hook( __FILE__, 'wcot_welcome_message' );
function wcot_welcome_message() {
set_transient( 'wcot_welcome_message_notice', true, 5 );
}
add_action( 'admin_notices', 'wcot_welcome_message_notice' );
function wcot_welcome_message_notice(){
/* Check transient, if available display notice */
if( get_transient( 'wcot_welcome_message_notice' ) ){
?>
<div class="updated notice is-dismissible">
	<style>div#message {display: none}</style>
<p>&#127881; <strong>WP Fix It - WooCommerce Order Test</strong> has been activated and you can now test your checkout process the easy way.
</div>
<?php
/* Delete transient, only display this notice once. */
delete_transient( 'wcot_welcome_message_notice' );
}
}

// Check if WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	function wcot_needed_notice() {
		$message = sprintf(
		/* translators: Placeholders: %1$s and %2$s are <strong> tags. %3$s and %4$s are <a> tags */
			esc_html__( '%1$sWooCommerce Order Test %2$s requires WooCommerce to function. Please %3$sinstall WooCommerce%4$s.', 'wcot_' ),
			'<strong>',
			'</strong>',
			'<a href="' . admin_url( 'plugins.php' ) . '">',
			'&nbsp;&raquo;</a>'
		);
		echo sprintf( '<div class="error"><p>%s</p></div>', $message );
	}
	add_action( 'admin_notices', 'wcot_needed_notice' );
	return;
}
?>