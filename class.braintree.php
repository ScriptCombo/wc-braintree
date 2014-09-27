<?php
/*
 * Plugin Name: WooCommerce Braintree Payment Gateway
 * Plugin URI: http://www.scriptcombo.com/
 * Description: Braintree Payment Gateway for WooCommerce Extension
 * Version: 1.0.0
 * Author: Scripted++
 * Author URI: http://www.scriptcombo.com/
 *  
 */

include plugin_dir_path(__FILE__) . 'lib/Braintree.php';

function woocommerce_api_braintree_init(){
	
	if(!class_exists('WC_Payment_Gateway')) return;
	
	class WC_API_Braintree extends WC_Payment_Gateway{
		
		public function __construct()
		{	
			$this->id 				= 'braintree';
			$this->method_title 	= 'Braintree';
			$this->has_fields 		= false; 
			$this->supports[] 		= 'default_credit_card_form';
			
			$this->init_form_fields();
			$this->init_settings();
			
			$this->title 			= $this->settings[ 'title' ];
			$this->description 		= $this->settings[ 'description' ];
			$this->mode 			= $this->settings[ 'mode' ];
			$this->merchantId 		= $this->settings[ 'merchantId' ];
			$this->publicKey 		= $this->settings[ 'publicKey' ];
			$this->privateKey 		= $this->settings[ 'privateKey' ];
			//$this->cseKey 			= $this->settings[ 'cseKey' ];
			$this->returnUrl 		= $this->settings[ 'returnUrl' ];
			$this->debugMode  		= $this->settings[ 'debugMode' ];
			$this->msg['message'] 	= '';
			$this->msg['class'] 	= '';
			
			if ( $this->debugMode == 'on' ){
				$this->logs = new WC_Logger();
			}
			 	
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
			
		}
	    
		public function init_form_fields()
		{
			$this->form_fields = array(
					'enabled' 			=> array(
	                    'title' 		=> __( 'Enable/Disable', 'woocommerce' ),
	                    'type' 			=> 'checkbox',
	                    'label' 		=> __( 'Enable Braintree Payment Module.', 'woocommerce' ),
	                    'default' 		=> 'no'
	                    ),
	                'title' => array(
	                    'title' 		=> __( 'Title:', 'woocommerce' ),
	                    'type'			=> 'text',
	                    'description' 	=> __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
	                    'default' 		=> __( 'Braintree', 'woocommerce' )
	                    ),
	                'description' => array(
	                    'title' 		=> __( 'Description:', 'woocommerce' ),
	                    'type' 			=> 'textarea',
	                    'description' 	=> __( 'This controls the description which the user sees during checkout.', 'woocommerce' ),
	                    'default' 		=> __( 'Pay with your credit card via Braintree.', 'woocommerce' )
	                    ),
	                'mode' 	=> array(
	                    'title' 		=> __( 'Environment', 'woocommerce' ),
	                    'type' 			=> 'select',
	                    'description' 	=> '',
	       				'options'     	=> array(
	                    	'sandbox' 	=> __( 'Sandbox', 'woocommerce' ),
					        'production'=> __( 'Production', 'woocommerce' )
						)
					),    
	                'merchantId' => array(
	                    'title' 		=> __( 'Merchant ID', 'woocommerce' ),
	                    'type' 			=> 'text',
	                    'description' 	=> __( 'Your Braintree Merchant ID.', 'woocommerce' ),
	                    'desc_tip'      => true,
	                    ),  
	                'publicKey' => array(
	                    'title' 		=> __( 'Public Key', 'woocommerce' ),
	                    'type' 			=> 'text',
	                    'description' 	=> __( 'Your Public Key  (Production or Sandbox).', 'woocommerce' ),
	                    'desc_tip'      => true,
	                    ),
	                'privateKey' => array(
	                    'title' 		=> __( 'Private Key', 'woocommerce' ),
	                    'type' 			=> 'text',
	                    'description' 	=> __( 'Your Private Key (Production or Sandbox).', 'woocommerce' ),
	                    'desc_tip'      => true,
	                    ),
	                'returnUrl' => array(
	                    'title' 		=> __( 'Return Url' , 'woocommerce' ),
	                    'type' 			=> 'select',
	                    'desc_tip'      => true,
	                    'options' 		=> $this->getPages( 'Select Page' ),
	                    'description' 	=> __( 'URL of success page', 'woocommerce' )
	                    ),    
	                'debugMode' => array(
	                    'title' 		=> __( 'Debug Mode', 'woocommerce' ),
	                    'type' 			=> 'select',
	                    'description' 	=> '',
	       				'options'     	=> array(
					        'off' 		=> __( 'Off', 'woocommerce' ),
					        'on' 		=> __( 'On', 'woocommerce' )
	                    ))           
			);	
		}
		
		public function process_payment( $order_id )
		{
			global $woocommerce;
			global $wp_rewrite;
	
			$order 		 	= new WC_Order( $order_id );
			$card_number	= str_replace(' ', '' , woocommerce_clean($_POST['braintree-card-number'] ));
	        $card_cvc		= str_replace(' ', '' , woocommerce_clean($_POST['braintree-card-cvc'] ));
	        $card_exp_year 	= str_replace(' ', '' , woocommerce_clean($_POST['braintree-card-expiry'] ));
	        
	        try {
	
	        	Braintree_Configuration::environment( $this->mode );
				Braintree_Configuration::merchantId( $this->merchantId );
				Braintree_Configuration::publicKey( $this->publicKey );
				Braintree_Configuration::privateKey( $this->privateKey );
				
				$params = array(
				    'amount' 				=> $order->order_total,
					'orderId' 				=> $order_id ,
				    'creditCard' => array(
				        'number' 			=> $card_number,
				        'expirationDate' 	=> $card_exp_year,
						'cvv' 				=> $card_cvc
				    ),
				  		'billing' => array(
						    'firstName' 	=> $order->billing_first_name,
						    'lastName' 		=> $order->billing_last_name,
						    'company' 		=> $order->billing_company,
						    'streetAddress' => $order->billing_address_1,
						    'extendedAddress' => $order->billing_address_2,
						    'locality' 		=> $order->billing_city,
						    'region' 		=> $order->billing_state,
						    'postalCode' 	=> $order->billing_postcode,
						    'countryCodeAlpha2' => $order->billing_country
				  ),
				   'options' => array(
				        'submitForSettlement' => true
				    )
				);
				
				$result = Braintree_Transaction::sale($params);
							
		        if ($result->success) {
	
		        	$order->payment_complete();
				    $order->add_order_note(
			            sprintf(
			                "%s Payment Completed with Transaction Id of '%s'",
			                $this->method_title,
			                $result->transaction->id
			            )
			        );
			        
					$woocommerce->cart->empty_cart();
					
					if($this->returnUrl == '' || $this->returnUrl == 0 ){
						$redirect_url = $this->get_return_url( $order );
					}else{
						$redirect_url = get_permalink( $this->returnUrl );
					}
					
					return array(
							'result' => 'success',
							'redirect' => $redirect_url
						);
				
		        } else {
		        	
		        	if(!empty($result->errors->deepAll())){
		        	 foreach ($result->errors->deepAll() as $err ){
					 	$errMsg[] =  $err->message; 
					 	}
		        	}
		        	
				     $order->add_order_note(
			            sprintf(
			                "%s Payment Failed with message: '%s'",
			                $this->method_title,
			                implode(",", $errMsg)
			            )
			        );
			       
			        wc_add_notice(__( 'Transaction Error: Could not complete your payment' , 'woocommerce'), "error");
			        return false; 
				}			
				
	       } catch (Exception $e) {
	        	$order->add_order_note(
			            sprintf(
			                "%s Payment Failed with message: '%s'",
			                $this->method_title,
			                $e->getMessage()
			            )
			        );
			        
			    wc_add_notice(__( 'Transaction Error: Could not complete your payment' , 'woocommerce'), "error");
			    return false;    
	        }
			
		
		}
		
		public function admin_options()
		{	
			if($this->mode == 'production' && get_option('woocommerce_force_ssl_checkout') == 'no' && $this->enabled == 'yes'){
				echo '<div class="error"><p>'.sprintf(__('%s Sandbox testing is disabled and can performe live transactions but the <a href="%s">force SSL option</a> is disabled; your checkout is not secure! Please enable SSL and ensure your server has a valid SSL certificate.', 'woothemes'), $this->method_title, admin_url('admin.php?page=wc-settings&tab=checkout')).'</p></div>';	
			}
			
			if(get_option('woocommerce_currency') != 'USD'){
				echo '<div class="error"><p>'.__(	'In order to support non-USD currencies, you must contact Braintree support ( support@braintreepayments.com ,  accounts@braintreepayments.com  )  or call 877.434.2894.', 'woocommerce'	).'</p></div>';
			}
			
			echo '<h3>'.__(	'Braintree Payment Gateway', 'woocommerce'	).'</h3>';
			echo '<div class="updated">';
			echo '<p>'.__(	'Do you like this plugin?', 'woocommerce' ).' <a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=9CQRJBSQPPJHE">'.__('Please reward it with a little donation.', 'woocommerce' ).'</a> </p>';
			echo '<p><a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=9CQRJBSQPPJHE"><img src="https://www.paypal.com/en_US/i/btn/btn_donateCC_LG.gif" /> </a> </p>';
			echo '</div>';
			echo '<p>'.__(	'Merchant Details.', 'woocommerce' ).'</p>';
			echo '<table class="form-table">';
			$this->generate_settings_html();
			echo '</table>';
				
		}
		
		public function validate_fields()
		{	
		
			global $woocommerce;
			
	        $card_number 		 = isset($_POST['braintree-card-number']) ? woocommerce_clean($_POST['braintree-card-number']) : '';
	        $card_cvc    		 = isset($_POST['braintree-card-cvc']) ? woocommerce_clean($_POST['braintree-card-cvc']) : '';
	        $card_exp_year 		 = isset($_POST['braintree-card-expiry']) ? woocommerce_clean($_POST['braintree-card-expiry']) : '';
	        
	       
			$card_number = str_replace(' ', '', $card_number);
	        if (empty($card_number) || !ctype_digit($card_number)) {
	            wc_add_notice(__('Payment error: Card number is invalid', 'woocommerce'), "error");
	            return false;
	        }
			
	        if (!ctype_digit($card_cvc)) {
	        	 wc_add_notice(__('Payment error: Card security code is invalid (only digits are allowed)', 'woocommerce'), "error");
	        	 return false;
	        }
	        
	        if(strlen($card_cvc) > 4){
	        	wc_add_notice(__('Payment error: Card security code is invalid (wrong length)', 'woocommerce'), "error");
	        	return false;
	        }
	
	        if( !empty($card_exp_year) ){
	        	$exp = explode( "/" , $card_exp_year );
	        	if( count($exp) == 2 ){
	        		$card_exp_month =  str_replace(' ', '', $exp[0] ); 
	        		$card_exp_year  =  str_replace(' ', '', $exp[1] ); 
	        		if (
			            !ctype_digit($card_exp_month) ||
			            !ctype_digit($card_exp_year) ||
			            $card_exp_month > 12 ||
			            $card_exp_month < 1 ||
			            $card_exp_year < date('y') ||
			            $card_exp_year > date('y') + 20
			        ){
			        	wc_add_notice(__('Payment error: Card expiration date is invalid', 'woocommerce'), "error");
	            		return;
			        }	
	        	}else{
	        		 wc_add_notice(__('Payment error: Card expiration date is invalid', 'woocommerce'), "error");
	        		 return;
	        	}
	        }
	        
	        
	        return true;
		}
	
		public function payment_fields()
		{
			if ( $this->mode == 's' ){
				echo '<p>';
				echo wpautop( wptexturize(  __('TEST MODE/SANDBOX ENABLED', 'woocommerce') )). ' ';
				echo '<p>';
			}
			
			if( $this->description ){
				echo wpautop( wptexturize( $this->description ) );
			}
			
			 $this->credit_card_form();
	
		}
	
		public function showMessage( $content )
		{
			$html  = '';
			$html .= '<div class="box '.$this->msg['class'].'-box">';
			$html .= $this->msg['message'];
			$html .= '</div>';
			$html .= $content;
				
			return $html;
				
		}
	
		public function getPages( $title = false, $indent = true )
		{
			$wp_pages = get_pages( 'sort_column=menu_order' );
			$page_list = array();
			if ( $title ) $page_list[] = $title;
			foreach ( $wp_pages as $page ) {
				$prefix = '';
				if ( $indent ) {
					$has_parent = $page->post_parent;
					while( $has_parent ) {
						$prefix .=  ' - ';
						$next_page = get_page( $has_parent );
						$has_parent = $next_page->post_parent;
					}
				}
				$page_list[$page->ID] = $prefix . $page->post_title;
			}
			return $page_list;
		}
	}
	
	function woocommerce_add_api_braintree( $methods ) {
		$methods[] = 'WC_API_Braintree';
		return $methods;
	}
	add_filter( 'woocommerce_payment_gateways', 'woocommerce_add_api_braintree' );
	
	function braintree_action_links( $links ) {
			return array_merge( array(
				'<a href="' . esc_url( 'https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=9CQRJBSQPPJHE'  ) . '">' . __( 'Donation', 'woocommerce' ) . '</a>'
			), $links );
		}
		
	add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'braintree_action_links' );

}

add_action( 'plugins_loaded', 'woocommerce_api_braintree_init', 0 );