<?php

/**
 * WF_Shipping_USPS class.
 *
 * @extends WC_Shipping_Method
 */
class WF_Shipping_USPS extends WC_Shipping_Method {

	private $endpoint        = 'http://production.shippingapis.com/shippingapi.dll';
	//private $endpoint        = 'http://stg-production.shippingapis.com/ShippingApi.dll';
	private $default_user_id = '570CYDTE1766';
	private $domestic        = array( "US", "PR", "VI" );
	private $found_rates;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id                 = WF_USPS_ID;
		$this->method_title       = __( 'USPS Basic', 'usps-woocommerce-shipping' );
		$this->method_description = __( 'The <strong>USPS Basic Version</strong> extension obtains rates dynamically from the USPS API during cart/checkout.Upgrade to Premium version for Print Shipping labels and Track Shipment feature.', 'usps-woocommerce-shipping' );
		$this->services           = include( 'data-wf-services.php' );
		$this->flat_rate_boxes    = null;
		$this->flat_rate_pricing  = null;
		$this->init();
	}

    /**
     * init function.
     *
     * @access public
     * @return void
     */
    private function init() {
		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables
		$this->enabled                  = isset( $this->settings['enabled'] ) ? $this->settings['enabled'] : $this->enabled;
		$this->title                    = isset( $this->settings['title'] ) ? $this->settings['title'] : $this->method_title;
		$this->availability             = isset( $this->settings['availability'] ) ? $this->settings['availability'] : 'all';
		$this->countries                = isset( $this->settings['countries'] ) ? $this->settings['countries'] : array();
		$this->origin                   = isset( $this->settings['origin'] ) ? $this->settings['origin'] : '';
		// WF Shipping Label: New fields - START
		$this->disbleShipmentTracking	= isset( $this->settings['disbleShipmentTracking'] ) ? $this->settings['disbleShipmentTracking'] : 'TrueForCustomer';
		$this->fillShipmentTracking		= isset( $this->settings['fillShipmentTracking'] ) ? $this->settings['fillShipmentTracking'] : 'Manual';
		$this->disblePrintLabel			= isset( $this->settings['disblePrintLabel'] ) ? $this->settings['disblePrintLabel'] : '';
		$this->manual_weight_dimensions	= isset( $this->settings['manual_weight_dimensions'] ) ? $this->settings['manual_weight_dimensions'] : 'no';
		$this->defaultPrintService      = isset( $this->settings['defaultPrintService'] ) ? $this->settings['defaultPrintService'] : 'None';
		$this->printLabelSize      		= isset( $this->settings['printLabelSize'] ) ? $this->settings['printLabelSize'] : 'Default';
		$this->printLabelType      		= isset( $this->settings['printLabelType'] ) ? $this->settings['printLabelType'] : 'PDF';
		$this->senderName        		= isset( $this->settings['senderName'] ) ? $this->settings['senderName'] : '';
		$this->senderCompanyName        = isset( $this->settings['senderCompanyName'] ) ? $this->settings['senderCompanyName'] : '';
		$this->senderAddressLine1       = isset( $this->settings['senderAddressLine1'] ) ? $this->settings['senderAddressLine1'] : '';
		$this->senderAddressLine2       = isset( $this->settings['senderAddressLine2'] ) ? $this->settings['senderAddressLine2'] : '';
		$this->senderCity               = isset( $this->settings['senderCity'] ) ? $this->settings['senderCity'] : '';
		$this->senderState              = isset( $this->settings['senderState'] ) ? $this->settings['senderState'] : '';
		$this->senderEmail              = isset( $this->settings['senderEmail'] ) ? $this->settings['senderEmail'] : '';
		$this->senderPhone              = isset( $this->settings['senderPhone'] ) ? $this->settings['senderPhone'] : '';
		// WF Shipping Label: New fields - END.
		$this->user_id                  = ! empty( $this->settings['user_id'] ) ? $this->settings['user_id'] : $this->default_user_id;
		$this->packing_method           = isset( $this->settings['packing_method'] ) ? $this->settings['packing_method'] : 'per_item';
		$this->boxes                    = isset( $this->settings['boxes'] ) ? $this->settings['boxes'] : array();
		$this->custom_services          = isset( $this->settings['services'] ) ? $this->settings['services'] : array();
		$this->offer_rates              = isset( $this->settings['offer_rates'] ) ? $this->settings['offer_rates'] : 'all';
		$this->fallback                 = ! empty( $this->settings['fallback'] ) ? $this->settings['fallback'] : '';
		$this->flat_rate_fee            = ! empty( $this->settings['flat_rate_fee'] ) ? $this->settings['flat_rate_fee'] : '';
		$this->mediamail_restriction    = isset( $this->settings['mediamail_restriction'] ) ? $this->settings['mediamail_restriction'] : array();
		$this->mediamail_restriction    = array_filter( (array) $this->mediamail_restriction );
		$this->unpacked_item_handling   = ! empty( $this->settings['unpacked_item_handling'] ) ? $this->settings['unpacked_item_handling'] : '';
		$this->enable_standard_services = isset( $this->settings['enable_standard_services'] ) && $this->settings['enable_standard_services'] == 'yes' ? true : false;
		$this->enable_flat_rate_boxes   = isset( $this->settings['enable_flat_rate_boxes'] ) ? $this->settings['enable_flat_rate_boxes'] : 'yes';
		$this->debug                    = isset( $this->settings['debug_mode'] ) && $this->settings['debug_mode'] == 'yes' ? true : false;
		$this->flat_rate_boxes          = apply_filters( 'usps_flat_rate_boxes', $this->flat_rate_boxes );

		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'clear_transients' ) );
	}

	/**
	 * environment_check function.
	 *
	 * @access public
	 * @return void
	 */
	private function environment_check() {
		global $woocommerce;

		$admin_page = version_compare( WOOCOMMERCE_VERSION, '2.1', '>=' ) ? 'wc-settings' : 'woocommerce_settings';

		if ( get_woocommerce_currency() != "USD" ) {
			echo '<div class="error">
				<p>' . sprintf( __( 'USPS requires that the <a href="%s">currency</a> is set to US Dollars.', 'usps-woocommerce-shipping' ), admin_url( 'admin.php?page=' . $admin_page . '&tab=general' ) ) . '</p>
			</div>';
		}

		elseif ( ! in_array( $woocommerce->countries->get_base_country(), $this->domestic ) ) {
			echo '<div class="error">
				<p>' . sprintf( __( 'USPS requires that the <a href="%s">base country/region</a> is the United States.', 'usps-woocommerce-shipping' ), admin_url( 'admin.php?page=' . $admin_page . '&tab=general' ) ) . '</p>
			</div>';
		}

		elseif ( ! $this->origin && $this->enabled == 'yes' ) {
			echo '<div class="error">
				<p>' . __( 'USPS is enabled, but the origin postcode has not been set.', 'usps-woocommerce-shipping' ) . '</p>
			</div>';
		}
	}

	/**
	 * admin_options function.
	 *
	 * @access public
	 * @return void
	 */
	public function admin_options() {
		// Check users environment supports this method
		$this->environment_check();
		?>
		<div class="wf-banner updated below-h2">
			<img class="scale-with-grid" src="http://www.wooforce.com/wp-content/uploads/2015/07/WooForce-Logo-Admin-Banner-Basic.png" alt="Wordpress / WooCommerce USPS, Canada Post Shipping | WooForce">
  			<p class="main"><strong>USPS Premium version streamlines your complete shipping process and saves time</strong></p>
			<p>&nbsp;-&nbsp;Print shipping label (no postage).<br>
			&nbsp;-&nbsp;Auto Shipment Tracking: It happens automatically while generating the label.<br>
			&nbsp;-&nbsp;Box packing.<br>
			&nbsp;-&nbsp;Enable/disable, edit the names of, and add handling costs to shipping services.<br>
			&nbsp;-&nbsp;Express & Priority flat rate services.<br>
			&nbsp;-&nbsp;Excellent Support for setting it up!</p>
			<p><a href="http://www.wooforce.com/product/usps-woocommerce-shipping-with-print-label/" target="_blank" class="button button-primary">Upgrade to Premium Version</a> <a href="http://usps.wooforce.com/wp-admin/admin.php?page=wc-settings&tab=shipping&section=wf_shipping_usps" target="_blank" class="button">Live Demo</a></p>
		</div>
		<style>
		.wf-banner img {
			float: right;
			margin-left: 1em;
			padding: 15px 0
		}
		</style>
		<?php 
		// Show settings
		parent::admin_options();
	}

	/**
	 * generate_services_html function.
	 */
	public function generate_services_html() {
		return '';
	}

	

	/**
	 * validate_services_field function.
	 *
	 * @access public
	 * @param mixed $key
	 * @return void
	 */
	public function validate_services_field( $key ) {
		$services         = array();
		$posted_services  = $this->services;
		foreach ( $posted_services as $code => $settings ) {

			$services[ $code ] = array(
				'name'               => wc_clean( $settings['name'] ),
				'order'              => ''
			);

			foreach ( $this->services[$code]['services'] as $key => $name ) {
				$services[ $code ][ $key ]['enabled'] = true;
				$services[ $code ][ $key ]['adjustment'] = '';
				$services[ $code ][ $key ]['adjustment_percent'] = '';
			}
		}
		return $services;
	}

	/**
	 * clear_transients function.
	 *
	 * @access public
	 * @return void
	 */
	public function clear_transients() {
		global $wpdb;

		$wpdb->query( "DELETE FROM `$wpdb->options` WHERE `option_name` LIKE ('_transient_usps_quote_%') OR `option_name` LIKE ('_transient_timeout_usps_quote_%')" );
	}

    /**
     * init_form_fields function.
     *
     * @access public
     * @return void
     */
    public function init_form_fields() {
	    global $woocommerce;

	    $shipping_classes = array();
	    $classes = ( $classes = get_terms( 'product_shipping_class', array( 'hide_empty' => '0' ) ) ) ? $classes : array();

	    foreach ( $classes as $class )
	    	$shipping_classes[ $class->term_id ] = $class->name;

    	$this->form_fields  = array(
			'enabled'          => array(
				'title'           => __( 'Enable/Disable', 'usps-woocommerce-shipping' ),
				'type'            => 'checkbox',
				'label'           => __( 'Enable this shipping method', 'usps-woocommerce-shipping' ),
				'default'         => 'no'
			),
			'title'            => array(
				'title'           => __( 'Method Title', 'usps-woocommerce-shipping' ),
				'type'            => 'text',
				'description'     => __( 'This controls the title which the user sees during checkout.', 'usps-woocommerce-shipping' ),
				'default'         => __( 'USPS Basic Version', 'usps-woocommerce-shipping' )
			),
			'origin'           => array(
				'title'           => __( 'Origin Postcode', 'usps-woocommerce-shipping' ),
				'type'            => 'text',
				'description'     => __( 'Enter the postcode for the <strong>sender</strong>.', 'usps-woocommerce-shipping' ),
				'default'         => ''
		    ), // WF Shipping Label: New fields - START.
			'disbleShipmentTracking'    => array(
				'title'           => __( 'Shipment Tracking', 'usps-woocommerce-shipping' ),
				'type'            => 'select',
				'default'         => 'True',
				'options'         => array(
					'True'         => __( 'Disable', 'usps-woocommerce-shipping' ),
				),
				'description'     => __( 'Selecting Disable for customer will hide shipment tracking info from customer side order details page. Upgrade to premium version for Shipment Tracking feature', 'usps-woocommerce-shipping' )
			),
			'fillShipmentTracking'    => array(
				'title'           => __( 'Fill Shipment Tracking', 'usps-woocommerce-shipping' ),
				'type'            => 'select',
				'default'         => 'yes',
				'options'         => array(
					'Manual'       		=> __( 'Manually after generating label', 'usps-woocommerce-shipping' ),
					'Auto'         		=> __( 'Automatically while creating label', 'usps-woocommerce-shipping' ),
				),
				'description'     => __( 'Even though Manual option is selected, shipment detail can be auto filled by a click on a button.', 'usps-woocommerce-shipping' )
			),
			'disblePrintLabel'          => array(
				'title'           => __( 'Enable/Disable Shipping Label', 'usps-woocommerce-shipping' ),
				'type'            => 'checkbox',
				'label'           => __( 'Disable Print Shipping Label', 'usps-woocommerce-shipping' ),
				'default'         => 'no',
				'description'     => __( 'Upgrade to premium version for Print Shipping Label feature', 'usps-woocommerce-shipping' )
			
			),
			'manual_weight_dimensions' => array(
				'title'           => __( 'Manual Label Dimensions', 'usps-woocommerce-shipping' ),
				'label'           => __( 'Manually enter weight and dimensions while label printing.', 'usps-woocommerce-shipping' ),
				'type'            => 'checkbox',
				'default'         => 'no'
			),
			'defaultPrintService'  => array(
				'title'           => __( 'Print Label Service Preference', 'usps-woocommerce-shipping' ),
				'type'            => 'select',
				'default'         => 'yes',
				'options'         => array(
					'None'         => __( 'None', 'usps-woocommerce-shipping' ),
					'Priority'         => __( 'Priority', 'usps-woocommerce-shipping' ),
					'First Class'          => __( 'First Class', 'usps-woocommerce-shipping' ),
					'Standard Post'    => __( 'Standard Post', 'usps-woocommerce-shipping' ),
					'Media Mail'     => __( 'Media Mail', 'usps-woocommerce-shipping' ),
					'Library Mail'     => __( 'Library Mail', 'usps-woocommerce-shipping' ),
				),
				'description'     => __( 'Set default print service. The selected service will be chosen as desired service for printing label. Not applicable for International Labels', 'usps-woocommerce-shipping' )
			),
			'printLabelSize'  => array(
				'title'           => __( 'Print Label Size', 'usps-woocommerce-shipping' ),
				'type'            => 'select',
				'default'         => 'yes',
				'options'         => array(
					'Default'         => __( 'Default', 'usps-woocommerce-shipping' ),
					'Compact'         => __( 'Compact', 'usps-woocommerce-shipping' ),
				),
				'description'     => __( 'Default size should be ~8x11. Compact means barcode only for domestic. ~4x6 for international.', 'usps-woocommerce-shipping' )
			),
			'printLabelType'  => array(
				'title'           => __( 'Print Label Type', 'usps-woocommerce-shipping' ),
				'type'            => 'select',
				'default'         => 'yes',
				'options'         => array(
					'PDF'         => __( 'PDF', 'usps-woocommerce-shipping' ),
					'TIF'         => __( 'TIF', 'usps-woocommerce-shipping' ),
				),
				'description'     => __( 'Set print label file type.', 'usps-woocommerce-shipping' )
			),
			'senderName' => array(
				  'title' => __( 'Sender Name', 'usps-woocommerce-shipping' ),
				  'type' => 'text',
				  'description' => __( 'Name to be printed in the shipping label <strong>[ Required for Print Shipping Label ]</strong>', 'usps-woocommerce-shipping' ),
				  'default' => ''
				  ),
			'senderCompanyName' => array(
				  'title' => __( 'Sender Company Name', 'usps-woocommerce-shipping' ),
				  'type' => 'text',
				  'description' => __( 'Company Name to be printed in the shipping label <strong>[ Required for Print Shipping Label ]</strong>', 'usps-woocommerce-shipping' ),
				  'default' => ''
				  ),
			'senderAddressLine1' => array(
				  'title' => __( 'Sender Address Line1', 'usps-woocommerce-shipping' ),
				  'type' => 'text',
				  'description' => __( 'Address Line1 to be printed in the shipping label <strong>[ Required for Print Shipping Label ]</strong>', 'usps-woocommerce-shipping' ),
				  'default' => ''
				  ),
			'senderAddressLine2' => array(
				  'title' => __( 'Sender Address Line2', 'usps-woocommerce-shipping' ),
				  'type' => 'text',
				  'description' => __( 'Address Line2 to be printed in the shipping label <strong>[ Required for Print Shipping Label ]</strong>', 'usps-woocommerce-shipping' ),
				  'default' => ''
				  ),
			'senderCity' => array(
				  'title' => __( 'Sender City', 'usps-woocommerce-shipping' ),
				  'type' => 'text',
				  'description' => __( 'City to be printed in the shipping label <strong>[ Required for Print Shipping Label ]</strong>', 'usps-woocommerce-shipping' ),
				  'default' => ''
				  ),
			'senderState' => array(
				  'title' => __( 'Sender State', 'usps-woocommerce-shipping' ),
				  'type' => 'text',
				  'description' => __( 'State short code (Eg: CA) to be printed in the shipping label. <strong>[ Required for Print Shipping Label ]</strong>', 'usps-woocommerce-shipping' ),
				  'default' => ''
				  ), 
			'senderEmail' => array(
				  'title' => __( 'Sender Email', 'usps-woocommerce-shipping' ),
				  'type' => 'email',
				  'description' => __( 'Enter Sender Email <strong>[ to trigger email notifications while creating Shipping Label. ]</strong>', 'usps-woocommerce-shipping' ),
				  'default' => ''
				  ),
			'senderPhone' => array(
				  'title' => __( 'Sender Phone', 'usps-woocommerce-shipping' ),
				  'type' => 'text',
				  'description' => __( 'Sender Phone <strong>[ Required for Print Shipping Label ]</strong>', 'usps-woocommerce-shipping' ),
				  'default' => ''
				  ),// WF Shipping Label: New fields - END.
		    'availability'  => array(
				'title'           => __( 'Method Available to', 'usps-woocommerce-shipping' ),
				'type'            => 'select',
				'default'         => 'all',
				'class'           => 'availability',
				'options'         => array(
					'all'            => __( 'All Countries', 'usps-woocommerce-shipping' ),
					'specific'       => __( 'Specific Countries', 'usps-woocommerce-shipping' ),
				),
			),
			'countries'        => array(
				'title'           => __( 'Specific Countries', 'usps-woocommerce-shipping' ),
				'type'            => 'multiselect',
				'class'           => 'chosen_select',
				'css'             => 'width: 450px;',
				'default'         => '',
				'options'         => $woocommerce->countries->get_allowed_countries(),
			),
		    'api'           => array(
				'title'           => __( 'API Settings:', 'usps-woocommerce-shipping' ),
				'type'            => 'title',
				'description'     => sprintf( __( 'You can obtain a USPS user ID by %s, or just use ours by leaving the field blank.', 'usps-woocommerce-shipping' ), '<a href="https://www.usps.com/">' . __( 'signing up on the USPS website', 'usps-woocommerce-shipping' ) . '</a>' ),
		    ),
		    'user_id'           => array(
				'title'           => __( 'User ID', 'usps-woocommerce-shipping' ),
				'type'            => 'text',
				'description'     => __( 'Obtained from USPS after getting an account.', 'usps-woocommerce-shipping' ),
				'default'         => '',
				'placeholder'     => $this->default_user_id
		    ),
		    'debug_mode'  => array(
				'title'           => __( 'Debug', 'usps-woocommerce-shipping' ),
				'label'           => __( 'Enable debug mode', 'usps-woocommerce-shipping' ),
				'type'            => 'checkbox',
				'default'         => 'no',
				'description'     => __( 'Enable debug mode to show debugging information on your cart/checkout.', 'usps-woocommerce-shipping' )
			),
		    'rates'           => array(
				'title'           => __( 'Rates:', 'usps-woocommerce-shipping' ),
				'type'            => 'title',
				'description'     => __( 'The following settings determine the rates you offer your customers.', 'usps-woocommerce-shipping' ),
		    ),
			'shippingrates'  => array(
				'title'           => __( 'Shipping', 'usps-woocommerce-shipping' ),
				'type'            => 'select',
				'default'         => 'ONLINE',
				'options'         => array(
					'ONLINE'      => __( 'Use ONLINE Rates', 'usps-woocommerce-shipping' ),
					'ALL'         => __( 'Use OFFLINE rates', 'usps-woocommerce-shipping' ),
				),
				'description'     => __( 'Choose which rates to show your customers, ONLINE rates are normally cheaper than OFFLINE', 'usps-woocommerce-shipping' ),
			),
			 'fallback' => array(
				'title'       => __( 'Fallback', 'usps-woocommerce-shipping' ),
				'type'        => 'text',
				'description' => __( 'If USPS returns no matching rates, offer this amount for shipping so that the user can still checkout. Leave blank to disable.', 'usps-woocommerce-shipping' ),
				'default'     => ''
			),
			'flat_rates'           => array(
				'title'           => __( 'Flat Rate:', 'usps-woocommerce-shipping' ),
				'type'            => 'title',
				'description' => __( 'Upgrade to Premium for Express & Priority flat rate services.', 'usps-woocommerce-shipping' ),
			),
		    'enable_flat_rate_boxes'  => array(
				'title'           => __( 'Boxes &amp; envelopes', 'usps-woocommerce-shipping' ),
				'type'            => 'select',
				'default'         => 'no',
				'options'         => array(
					'no'          => __( 'No - Disable flat rate services', 'usps-woocommerce-shipping' ),
				),
				'description'     => __( 'Enable this option to offer shipping using USPS Flat Rate services. Items will be packed into the boxes/envelopes and the customer will be offered a single rate from these.', 'usps-woocommerce-shipping' )
			),
			'flat_rate_express_title'           => array(
				'title'           => __( 'Express Flat Rate Service', 'usps-woocommerce-shipping' ),
				'type'            => 'text',
				'description'     => '',
				'default'         => '',
				'placeholder'     => 'Priority Mail Express Flat Rate&#0174;'
		    ),
		    'flat_rate_priority_title'           => array(
				'title'           => __( 'Priority Flat Rate Service', 'usps-woocommerce-shipping' ),
				'type'            => 'text',
				'description'     => '',
				'default'         => '',
				'placeholder'     => 'Priority Mail Flat Rate&#0174;'
		    ),
		    'flat_rate_fee' => array(
				'title' 		=> __( 'Flat Rate Fee', 'woocommerce' ),
				'type' 			=> 'text',
				'description'	=> __( 'Fee per-box excluding tax. Enter an amount, e.g. 2.50, or a percentage, e.g. 5%. Leave blank to disable.', 'woocommerce' ),
				'default'		=> '',
			),
		    'standard_rates'           => array(
				'title'           => __( 'API Rate:', 'usps-woocommerce-shipping' ),
				'type'            => 'title',
		    ),
			'enable_standard_services'  => array(
				'title'           => __( 'Standard Services', 'usps-woocommerce-shipping' ),
				'label'           => __( 'Enable Standard Services from the API', 'usps-woocommerce-shipping' ),
				'type'            => 'checkbox',
				'default'         => 'no',
				'description'     => __( 'Enable non-flat rate services.', 'usps-woocommerce-shipping' )
			),
			'packing_method'  => array(
				'title'           => __( 'Parcel Packing', 'usps-woocommerce-shipping' ),
				'type'            => 'select',
				'default'         => 'per_item',
				'class'           => 'packing_method',
				'options'         => array(
					'per_item'       => __( 'Default: Pack items individually', 'usps-woocommerce-shipping' ),
					'weight_based'    => __( 'Weight based: Regular sized items (< 12 inches) are grouped and quoted for weights only. Large items are quoted individually.', 'usps-woocommerce-shipping' ),
				
				),
				'description' => __( 'Upgrade to Premium for Box packing feature.', 'usps-woocommerce-shipping' ),
			),
			'unpacked_item_handling'   => array(
				'title'           => __( 'Unpacked item', 'usps-woocommerce-shipping' ),
				'type'            => 'select',
				'description'     => '',
				'default'         => 'all',
				'options'         => array(
					''         => __( 'Get a quote for the unpacked item by itself', 'usps-woocommerce-shipping' ),
					'ingore'   => __( 'Ignore the item - do not quote', 'usps-woocommerce-shipping' ),
					'fallback' => __( 'Use the fallback price (above)', 'usps-woocommerce-shipping' ),
					'abort'    => __( 'Abort - do not return any quotes for the standard services', 'usps-woocommerce-shipping' ),
				),
		    ),
			'offer_rates'   => array(
				'title'           => __( 'Offer Rates', 'usps-woocommerce-shipping' ),
				'type'            => 'select',
				'description'     => '',
				'default'         => 'all',
				'options'         => array(
				    'all'         => __( 'Offer the customer all returned rates', 'usps-woocommerce-shipping' ),
				    'cheapest'    => __( 'Offer the customer the cheapest rate only', 'usps-woocommerce-shipping' ),
				),
		    ),
			'services'  => array(
				'type'            => 'services'
			),
			'mediamail_restriction'        => array(
				'title'           => __( 'Restrict Media Mail', 'usps-woocommerce-shipping' ),
				'type'            => 'multiselect',
				'class'           => 'chosen_select',
				'css'             => 'width: 450px;',
				'default'         => '',
				'options'         => $shipping_classes,
				'custom_attributes'      => array(
					'data-placeholder' => __( 'No restrictions', 'usps-woocommerce-shipping' ),
				)
			),
		);
    }

    /**
     * calculate_shipping function.
     *
     * @access public
     * @param mixed $package
     * @return void
     */
    public function calculate_shipping( $package ) {
    	global $woocommerce;

		$this->rates               = array();
		$this->unpacked_item_costs = 0;
		$domestic                  = in_array( $package['destination']['country'], $this->domestic ) ? true : false;

    	$this->debug( __( 'USPS debug mode is on - to hide these messages, turn debug mode off in the settings.', 'usps-woocommerce-shipping' ) );

    	if ( $this->enable_standard_services ) {

	    	$package_requests = $this->get_package_requests( $package );
	    	$api              = $domestic ? 'RateV4' : 'IntlRateV2';
	    	libxml_use_internal_errors( true );

	    	if ( $package_requests ) {

	    		$request  = '<' . $api . 'Request USERID="' . $this->user_id . '">' . "\n";
	    		$request .= '<Revision>2</Revision>' . "\n";

	    		foreach ( $package_requests as $key => $package_request ) {
	    			$request .= $package_request;
	    		}

	    		$request .= '</' . $api . 'Request>' . "\n";
	    		$request = 'API=' . $api . '&XML=' . str_replace( array( "\n", "\r" ), '', $request );

	    		$transient       = 'usps_quote_' . md5( $request );
				$cached_response = get_transient( $transient );

				$this->debug( 'USPS REQUEST: <pre>' . print_r( htmlspecialchars( $request ), true ) . '</pre>' );

				if ( $cached_response !== false ) {
					$response = $cached_response;

			    	$this->debug( 'USPS CACHED RESPONSE: <pre style="height: 200px; overflow:auto;">' . print_r( htmlspecialchars( $response ), true ) . '</pre>' );
				} else {
					$response = wp_remote_post( $this->endpoint,
			    		array(
							'timeout'   => 70,
							'sslverify' => 0,
							'body'      => $request
					    )
					);

					if ( is_wp_error( $response ) ) {
		    			$this->debug( 'USPS REQUEST FAILED' );

		    			$response = false;
		    		} else {
			    		$response = $response['body'];

			    		$this->debug( 'USPS RESPONSE: <pre style="height: 200px; overflow:auto;">' . print_r( htmlspecialchars( $response ), true ) . '</pre>' );

						set_transient( $transient, $response, YEAR_IN_SECONDS );
					}
				}

	    		if ( $response ) {

					$xml = simplexml_load_string( '<root>' . preg_replace('/<\?xml.*\?>/', '', $response ) . '</root>' );

					if ( ! $xml ) {
						$this->debug( 'Failed loading XML', 'error' );
					}

					if ( ! empty( $xml->{ $api . 'Response' } ) ) {
						
						$usps_packages = $xml->{ $api . 'Response' }->children();

						if ( $usps_packages ) {

							$index = 0;

							foreach ( $usps_packages as $usps_package ) {

								// Get package data
								list( $package_item_id, $cart_item_qty, $package_length, $package_width, $package_height, $package_weight ) = explode( ':', $usps_package->attributes()->ID );
								$quotes              = $usps_package->children();

								if ( $this->debug ) {
									$found_quotes = array();

									foreach ( $quotes as $quote ) {
										if ( $domestic ) {
											$code = strval( $quote->attributes()->CLASSID );
											$name = strip_tags( htmlspecialchars_decode( (string) $quote->{'MailService'} ) );
										} else {
											$code = strval( $quote->attributes()->ID );
											$name = strip_tags( htmlspecialchars_decode( (string) $quote->{'SvcDescription'} ) );
										}

										if ( $name && $code ) {
											$found_quotes[ $code ] = $name;
										} elseif ( $name ) {
											$found_quotes[ $code . '-' . sanitize_title( $name ) ] = $name;
										}
									}

									if ( $found_quotes ) {
										ksort( $found_quotes );
										$found_quotes_html = '';
										foreach ( $found_quotes as $code => $name ) {
											if ( ! strstr( $name, "Flat Rate" ) ) {
												$found_quotes_html .= '<li>' . $code . ' - ' . $name . '</li>';
											}
										}
										$this->debug( 'The following quotes were returned by USPS: <ul>' . $found_quotes_html . '</ul> If any of these do not display, they may not be enabled in USPS settings.', 'success' );
									}
								}

								// Loop our known services
								foreach ( $this->services as $service => $values ) {

									if ( $domestic && strpos( $service, 'D_' ) !== 0 ) {
										continue;
									}

									if ( ! $domestic && strpos( $service, 'I_' ) !== 0 ) {
										continue;
									}

									$rate_code = (string) $service;
									$rate_id   = $this->id . ':' . $rate_code;
									$rate_name = (string) $values['name'] . ' (' . $this->title . ')';
									$rate_cost = null;

									foreach ( $quotes as $quote ) {

										if ( $domestic ) {
											$code = strval( $quote->attributes()->CLASSID );
										} else {
											$code = strval( $quote->attributes()->ID );
										}

										if ( $code !== "" && in_array( $code, array_keys( $values['services'] ) ) ) {

											if ( $domestic ) {

												if ( ! empty( $quote->{'CommercialRate'} ) ) {
													$cost = (float) $quote->{'CommercialRate'} * $cart_item_qty;
												} else {
													$cost = (float) $quote->{'Rate'} * $cart_item_qty;
												}

											} else {

												if ( ! empty( $quote->{'CommercialPostage'} ) ) {
													$cost = (float) $quote->{'CommercialPostage'} * $cart_item_qty;
												} else {
													$cost = (float) $quote->{'Postage'} * $cart_item_qty;
												}

											}

											// Cost adjustment %
											if ( ! empty( $this->custom_services[ $rate_code ][ $code ]['adjustment_percent'] ) )
												$cost = $cost + ( $cost * ( floatval( $this->custom_services[ $rate_code ][ $code ]['adjustment_percent'] ) / 100 ) );

											// Cost adjustment
											if ( ! empty( $this->custom_services[ $rate_code ][ $code ]['adjustment'] ) )
												$cost = $cost + floatval( $this->custom_services[ $rate_code ][ $code ]['adjustment'] );

											// Enabled check
											if ( isset( $this->custom_services[ $rate_code ][ $code ] ) && empty( $this->custom_services[ $rate_code ][ $code ]['enabled'] ) )
												continue;

											if ( $domestic ) {
												switch ( $code ) {
													// Handle first class - there are multiple d0 rates and we need to handle size retrictions because the API is lame
													case "0" :
														$service_name = strip_tags( htmlspecialchars_decode( (string) $quote->{'MailService'} ) );

														if ( apply_filters( 'usps_disable_first_class_rate_' . sanitize_title( $service_name ), false) ) {
															continue 2;
														}
													break;
													// Media mail has restrictions - check here
													case "6" :
														if ( sizeof( $this->mediamail_restriction ) > 0 ) {
															$invalid = false;

															foreach ( $package['contents'] as $package_item ) {
																if ( ! in_array( $package_item['data']->get_shipping_class_id(), $this->mediamail_restriction ) ) {
																	$invalid = true;
																}
															}

															if ( $invalid ) {
																$this->debug( 'Skipping media mail' );
															}

															if ( $invalid ) {
																continue 2;
															}
														}
													break;
												}
											}

											if ( $domestic && $package_length && $package_width && $package_height ) {
												switch ( $code ) {
													// Regional rate boxes need additonal checks to deal with USPS's crap API
													case "47" :
														if ( $package_length > 10.125 || $package_width > 7.125 || $package_height > 5 ) {
															continue 2;
														} else {
															// Valid
															break;
														}
														if ( $package_length > 13.0625 || $package_width > 11.0625 || $package_height > 2.5 ) {
															continue 2;
														} else {
															// Valid
															break;
														}
													break;
													case "49" :
														if ( $package_length > 12.25 || $package_width > 10.5 || $package_height > 5.5 ) {
															continue 2;
														} else {
															// Valid
															break;
														}
														if ( $package_length > 16.25 || $package_width > 14.5 || $package_height > 3 ) {
															continue 2;
														} else {
															// Valid
															break;
														}
													break;
													case "58" :
														if ( $package_length > 15 || $package_width > 12 || $package_height > 12 ) {
															continue 2;
														} else {
															// Valid
															break;
														}
													break;
													// Handle first class - there are multiple d0 rates and we need to handle size retrictions because the API is lame
													case "0" :
														$service_name = strip_tags( htmlspecialchars_decode( (string) $quote->{'MailService'} ) );

														if ( strstr( $service_name, 'Postcards' ) ) {

															if ( $package_length > 6 || $package_length < 5 ) {
																continue 2;
															}
															if ( $package_width > 4.25 || $package_width < 3.5 ) {
																continue 2;
															}
															if ( $package_height > 0.016 || $package_height < 0.007 ) {
																continue 2;
															}

														} elseif ( strstr( $service_name, 'Large Envelope' ) ) {

															if ( $package_length > 15 || $package_length < 11.5 ) {
																continue 2;
															}
															if ( $package_width > 12 || $package_width < 6 ) {
																continue 2;
															}
															if ( $package_height > 0.75 || $package_height < 0.25 ) {
																continue 2;
															}

														} elseif ( strstr( $service_name, 'Letter' ) ) {

															if ( $package_length > 11.5 || $package_length < 5 ) {
																continue 2;
															}
															if ( $package_width > 6.125 || $package_width < 3.5 ) {
																continue 2;
															}
															if ( $package_height > 0.25 || $package_height < 0.007 ) {
																continue 2;
															}

														} elseif ( strstr( $service_name, 'Parcel' ) ) {

															$girth = ( $package_width + $package_height ) * 2;

															if ( $girth + $package_length > 108 ) {
																continue 2;
															}

														} else {
															continue 2;
														}
													break;
												}
											}

											if ( is_null( $rate_cost ) ) {
												$rate_cost = $cost;
											} elseif ( $cost < $rate_cost ) {
												$rate_cost = $cost;
											}
										}
									}

									if ( $rate_cost ) {
										$this->prepare_rate( $rate_code, $rate_id, $rate_name, $rate_cost );
									}
								}

								$index++;
							}
						}

					} else {
						// No rates
						$this->debug( 'Invalid request; no rates returned', 'error' );
					}
				}
			}

			// Ensure rates were found for all packages
			if ( $this->found_rates ) {
				foreach ( $this->found_rates as $key => $value ) {
					if ( $value['packages'] < sizeof( $package_requests ) ) {
						unset( $this->found_rates[ $key ] );
					}

					if ( $this->unpacked_item_costs ) {
						$this->debug( sprintf( __( 'Adding unpacked item costs to rate %s', 'usps-woocommerce-shipping' ), $key ) );
						$this->found_rates[ $key ]['cost'] += $this->unpacked_item_costs;
					}
				}
			}
		}

		// Flat Rate boxes quote
		if ( $this->enable_flat_rate_boxes == 'yes' || $this->enable_flat_rate_boxes == 'priority' ) {
			// Priority
			$flat_rate = $this->calculate_flat_rate_box_rate( $package, 'priority' );
			if ( $flat_rate )
				$this->found_rates[ $flat_rate['id'] ] = $flat_rate;
		}
		if ( $this->enable_flat_rate_boxes == 'yes' || $this->enable_flat_rate_boxes == 'express' ) {
			// Express
			$flat_rate = $this->calculate_flat_rate_box_rate( $package, 'express' );
			if ( $flat_rate )
				$this->found_rates[ $flat_rate['id'] ] = $flat_rate;
		}
			
		// Add rates
		if ( $this->found_rates ) {
			
			if ( $this->offer_rates == 'all' ) {

				uasort( $this->found_rates, array( $this, 'sort_rates' ) );

				foreach ( $this->found_rates as $key => $rate ) {
					$this->add_rate( $rate );
				}

			} else {

				$cheapest_rate = '';

				foreach ( $this->found_rates as $key => $rate ) {
					if ( ! $cheapest_rate || $cheapest_rate['cost'] > $rate['cost'] )
						$cheapest_rate = $rate;
				}

				$cheapest_rate['label'] = $this->title;

				$this->add_rate( $cheapest_rate );

			}

		// Fallback
		} elseif ( $this->fallback ) {
			$this->add_rate( array(
				'id' 	=> $this->id . '_fallback',
				'label' => $this->title,
				'cost' 	=> $this->fallback,
				'sort'  => 0
			) );
		}

    }

    /**
     * prepare_rate function.
     *
     * @access private
     * @param mixed $rate_code
     * @param mixed $rate_id
     * @param mixed $rate_name
     * @param mixed $rate_cost
     * @return void
     */
    private function prepare_rate( $rate_code, $rate_id, $rate_name, $rate_cost ) {

	    // Name adjustment
		if ( ! empty( $this->custom_services[ $rate_code ]['name'] ) )
			$rate_name = $this->custom_services[ $rate_code ]['name'];

		// Merging
		if ( isset( $this->found_rates[ $rate_id ] ) ) {
			$rate_cost = $rate_cost + $this->found_rates[ $rate_id ]['cost'];
			$packages  = 1 + $this->found_rates[ $rate_id ]['packages'];
		} else {
			$packages = 1;
		}

		// Sort
		if ( isset( $this->custom_services[ $rate_code ]['order'] ) ) {
			$sort = $this->custom_services[ $rate_code ]['order'];
		} else {
			$sort = 999;
		}

		$this->found_rates[ $rate_id ] = array(
			'id'       => $rate_id,
			'label'    => $rate_name,
			'cost'     => $rate_cost,
			'sort'     => $sort,
			'packages' => $packages
		);
    }

    /**
     * sort_rates function.
     *
     * @access public
     * @param mixed $a
     * @param mixed $b
     * @return void
     */
    public function sort_rates( $a, $b ) {
		if ( $a['sort'] == $b['sort'] ) return 0;
		return ( $a['sort'] < $b['sort'] ) ? -1 : 1;
    }

    /**
     * get_request function.
     *
     * @access private
     * @return void
     */
    private function get_package_requests( $package ) {

	    // Choose selected packing
    	switch ( $this->packing_method ) {
	    	case 'weight_based' :
	    		$requests = $this->weight_based_shipping( $package );
	    	break;
	    	case 'per_item' :
	    	default :
	    		$requests = $this->per_item_shipping( $package );
	    	break;
    	}

    	return $requests;
    }

    /**
     * per_item_shipping function.
     *
     * @access private
     * @param mixed $package
     * @return void
     */
    private function per_item_shipping( $package ) {
	    global $woocommerce;

	    $requests = array();
	    $domestic = in_array( $package['destination']['country'], $this->domestic ) ? true : false;

    	// Get weight of order
    	foreach ( $package['contents'] as $item_id => $values ) {

    		if ( ! $values['data']->needs_shipping() ) {
    			$this->debug( sprintf( __( 'Product # is virtual. Skipping.', 'usps-woocommerce-shipping' ), $item_id ) );
    			continue;
    		}

    		if ( ! $values['data']->get_weight() ) {
	    		$this->debug( sprintf( __( 'Product # is missing weight. Using 1lb.', 'usps-woocommerce-shipping' ), $item_id ) );

	    		$weight = 1;
    		} else {
    			$weight = wc_get_weight( $values['data']->get_weight(), 'lbs' );
    		}

    		$size   = 'REGULAR';

    		if ( $values['data']->length && $values['data']->height && $values['data']->width ) {

				$dimensions = array( wc_get_dimension( $values['data']->length, 'in' ), wc_get_dimension( $values['data']->height, 'in' ), wc_get_dimension( $values['data']->width, 'in' ) );

				sort( $dimensions );

				if ( max( $dimensions ) > 12 ) {
					$size   = 'LARGE';
				}

				$girth = $dimensions[0] + $dimensions[0] + $dimensions[1] + $dimensions[1];
			} else {
				$dimensions = array( 0, 0, 0 );
				$girth      = 0;
			}

			if ( $domestic ) {

				$request  = '<Package ID="' . $this->generate_package_id( $item_id, $values['quantity'], $dimensions[2], $dimensions[1], $dimensions[0], $weight ) . '">' . "\n";
				$request .= '	<Service>' . ( ! $this->settings['shippingrates'] ? 'ONLINE' : $this->settings['shippingrates'] ) . '</Service>' . "\n";
				$request .= '	<ZipOrigination>' . str_replace( ' ', '', strtoupper( $this->origin ) ) . '</ZipOrigination>' . "\n";
				$request .= '	<ZipDestination>' . strtoupper( substr( $package['destination']['postcode'], 0, 5 ) ) . '</ZipDestination>' . "\n";
				$request .= '	<Pounds>' . floor( $weight ) . '</Pounds>' . "\n";
				$request .= '	<Ounces>' . number_format( ( $weight - floor( $weight ) ) * 16, 2 ) . '</Ounces>' . "\n";

				if ( 'LARGE' === $size ) {
					$request .= '	<Container>RECTANGULAR</Container>' . "\n";
				} else {
					$request .= '	<Container />' . "\n";
				}

				$request .= '	<Size>' . $size . '</Size>' . "\n";
				$request .= '	<Width>' . $dimensions[1] . '</Width>' . "\n";
				$request .= '	<Length>' . $dimensions[2] . '</Length>' . "\n";
				$request .= '	<Height>' . $dimensions[0] . '</Height>' . "\n";
				$request .= '	<Girth>' . round( $girth ) . '</Girth>' . "\n";
				$request .= '	<Machinable>true</Machinable> ' . "\n";
				$request .= '	<ShipDate>' . date( "d-M-Y", ( current_time('timestamp') + (60 * 60 * 24) ) ) . '</ShipDate>' . "\n";
				$request .= '</Package>' . "\n";

			} else {

				$request  = '<Package ID="' . $this->generate_package_id( $item_id, $values['quantity'], $dimensions[2], $dimensions[1], $dimensions[0], $weight ) . '">' . "\n";
				$request .= '	<Pounds>' . floor( $weight ) . '</Pounds>' . "\n";
				$request .= '	<Ounces>' . number_format( ( $weight - floor( $weight ) ) * 16, 2 ) . '</Ounces>' . "\n";
				$request .= '	<Machinable>true</Machinable> ' . "\n";
				$request .= '	<MailType>Package</MailType>' . "\n";
				$request .= '	<ValueOfContents>' . $values['data']->get_price() . '</ValueOfContents>' . "\n";
				$request .= '	<Country>' . $this->get_country_name( $package['destination']['country'] ) . '</Country>' . "\n";

				$request .= '	<Container>RECTANGULAR</Container>' . "\n";
				
				$request .= '	<Size>' . $size . '</Size>' . "\n";
				$request .= '	<Width>' . $dimensions[1] . '</Width>' . "\n";
				$request .= '	<Length>' . $dimensions[2] . '</Length>' . "\n";
				$request .= '	<Height>' . $dimensions[0] . '</Height>' . "\n";
				$request .= '	<Girth>' . round( $girth ) . '</Girth>' . "\n";
				$request .= '	<OriginZip>' . str_replace( ' ', '', strtoupper( $this->origin ) ) . '</OriginZip>' . "\n";
				$request .= '	<CommercialFlag>' . ( $this->settings['shippingrates'] == "ONLINE" ? 'Y' : 'N' ) . '</CommercialFlag>' . "\n";
				$request .= '</Package>' . "\n";

			}

			$requests[] = $request;
    	}

		return $requests;
    }

    /**
     * Generate shipping request for weights only
     * @param  array $package
     * @return array
     */
    private function weight_based_shipping( $package ) {
    	global $woocommerce;

		$requests                  = array();
		$domestic                  = in_array( $package['destination']['country'], $this->domestic ) ? true : false;
		$total_regular_item_weight = 0;

    	// Add requests for larger items
    	foreach ( $package['contents'] as $item_id => $values ) {

    		if ( ! $values['data']->needs_shipping() ) {
    			$this->debug( sprintf( __( 'Product #%d is virtual. Skipping.', 'usps-woocommerce-shipping' ), $item_id ) );
    			continue;
    		}

    		if ( ! $values['data']->get_weight() ) {
	    		$this->debug( sprintf( __( 'Product #%d is missing weight. Using 1lb.', 'usps-woocommerce-shipping' ), $item_id ), 'error' );

	    		$weight = 1;
    		} else {
    			$weight = wc_get_weight( $values['data']->get_weight(), 'lbs' );
    		}

			$dimensions = array( wc_get_dimension( $values['data']->length, 'in' ), wc_get_dimension( $values['data']->height, 'in' ), wc_get_dimension( $values['data']->width, 'in' ) );

			sort( $dimensions );

			if ( max( $dimensions ) <= 12 ) {
				$total_regular_item_weight += ( $weight * $values['quantity'] );
    			continue;
			}

			$girth = $dimensions[0] + $dimensions[0] + $dimensions[1] + $dimensions[1];

			if ( $domestic ) {
				$request  = '<Package ID="' . $this->generate_package_id( $item_id, $values['quantity'], $dimensions[2], $dimensions[1], $dimensions[0], $weight ) . '">' . "\n";
				$request .= '	<Service>' . ( !$this->settings['shippingrates'] ? 'ONLINE' : $this->settings['shippingrates'] ) . '</Service>' . "\n";
				$request .= '	<ZipOrigination>' . str_replace( ' ', '', strtoupper( $this->origin ) ) . '</ZipOrigination>' . "\n";
				$request .= '	<ZipDestination>' . strtoupper( substr( $package['destination']['postcode'], 0, 5 ) ) . '</ZipDestination>' . "\n";
				$request .= '	<Pounds>' . floor( $weight ) . '</Pounds>' . "\n";
				$request .= '	<Ounces>' . number_format( ( $weight - floor( $weight ) ) * 16, 2 ) . '</Ounces>' . "\n";
				$request .= '	<Container>RECTANGULAR</Container>' . "\n";
				$request .= '	<Size>LARGE</Size>' . "\n";
				$request .= '	<Width>' . $dimensions[1] . '</Width>' . "\n";
				$request .= '	<Length>' . $dimensions[2] . '</Length>' . "\n";
				$request .= '	<Height>' . $dimensions[0] . '</Height>' . "\n";
				$request .= '	<Girth>' . round( $girth ) . '</Girth>' . "\n";
				$request .= '	<Machinable>true</Machinable> ' . "\n";
				$request .= '	<ShipDate>' . date( "d-M-Y", ( current_time('timestamp') + (60 * 60 * 24) ) ) . '</ShipDate>' . "\n";
				$request .= '</Package>' . "\n";
			} else {
				$request  = '<Package ID="' . $this->generate_package_id( $item_id, $values['quantity'], $dimensions[2], $dimensions[1], $dimensions[0], $weight ) . '">' . "\n";
				$request .= '	<Pounds>' . floor( $weight ) . '</Pounds>' . "\n";
				$request .= '	<Ounces>' . number_format( ( $weight - floor( $weight ) ) * 16, 2 ) . '</Ounces>' . "\n";
				$request .= '	<Machinable>true</Machinable> ' . "\n";
				$request .= '	<MailType>Package</MailType>' . "\n";
				$request .= '	<ValueOfContents>' . $values['data']->get_price() . '</ValueOfContents>' . "\n";
				$request .= '	<Country>' . $this->get_country_name( $package['destination']['country'] ) . '</Country>' . "\n";
				$request .= '	<Container>RECTANGULAR</Container>' . "\n";
				$request .= '	<Size>LARGE</Size>' . "\n";
				$request .= '	<Width>' . $dimensions[1] . '</Width>' . "\n";
				$request .= '	<Length>' . $dimensions[2] . '</Length>' . "\n";
				$request .= '	<Height>' . $dimensions[0] . '</Height>' . "\n";
				$request .= '	<Girth>' . round( $girth ) . '</Girth>' . "\n";
				$request .= '	<OriginZip>' . str_replace( ' ', '', strtoupper( $this->origin ) ) . '</OriginZip>' . "\n";
				$request .= '	<CommercialFlag>' . ( $this->settings['shippingrates'] == "ONLINE" ? 'Y' : 'N' ) . '</CommercialFlag>' . "\n";
				$request .= '</Package>' . "\n";
			}

			$requests[] = $request;
    	}

    	// Regular package
    	if ( $total_regular_item_weight > 0 ) {
    		$max_package_weight = ( $domestic || $package['destination']['country'] == 'MX' ) ? 70 : 44;
    		$package_weights    = array();

    		$full_packages      = floor( $total_regular_item_weight / $max_package_weight );
    		for ( $i = 0; $i < $full_packages; $i ++ )
    			$package_weights[] = $max_package_weight;

    		if ( $remainder = fmod( $total_regular_item_weight, $max_package_weight ) )
    			$package_weights[] = $remainder;

    		foreach ( $package_weights as $key => $weight ) {
				if ( $domestic ) {
					$request  = '<Package ID="' . $this->generate_package_id( 'regular_' . $key, 1, 0, 0, 0, 0 ) . '">' . "\n";
					$request .= '	<Service>' . ( !$this->settings['shippingrates'] ? 'ONLINE' : $this->settings['shippingrates'] ) . '</Service>' . "\n";
					$request .= '	<ZipOrigination>' . str_replace( ' ', '', strtoupper( $this->origin ) ) . '</ZipOrigination>' . "\n";
					$request .= '	<ZipDestination>' . strtoupper( substr( $package['destination']['postcode'], 0, 5 ) ) . '</ZipDestination>' . "\n";
					$request .= '	<Pounds>' . floor( $weight ) . '</Pounds>' . "\n";
					$request .= '	<Ounces>' . number_format( ( $weight - floor( $weight ) ) * 16, 2 ) . '</Ounces>' . "\n";
					$request .= '	<Container />' . "\n";
					$request .= '	<Size>REGULAR</Size>' . "\n";
					$request .= '	<Machinable>true</Machinable> ' . "\n";
					$request .= '	<ShipDate>' . date( "d-M-Y", ( current_time('timestamp') + (60 * 60 * 24) ) ) . '</ShipDate>' . "\n";
					$request .= '</Package>' . "\n";
				} else {
					$request  = '<Package ID="' . $this->generate_package_id( 'regular_' . $key, 1, 0, 0, 0, 0 ) . '">' . "\n";
					$request .= '	<Pounds>' . floor( $weight ) . '</Pounds>' . "\n";
					$request .= '	<Ounces>' . number_format( ( $weight - floor( $weight ) ) * 16, 2 ) . '</Ounces>' . "\n";
					$request .= '	<Machinable>true</Machinable> ' . "\n";
					$request .= '	<MailType>Package</MailType>' . "\n";
					$request .= '	<ValueOfContents>' . $values['data']->get_price() . '</ValueOfContents>' . "\n";
					$request .= '	<Country>' . $this->get_country_name( $package['destination']['country'] ) . '</Country>' . "\n";
					$request .= '	<Container />' . "\n";
					$request .= '	<Size>REGULAR</Size>' . "\n";
					$request .= '	<Width />' . "\n";
					$request .= '	<Length />' . "\n";
					$request .= '	<Height />' . "\n";
					$request .= '	<Girth />' . "\n";
					$request .= '	<OriginZip>' . str_replace( ' ', '', strtoupper( $this->origin ) ) . '</OriginZip>' . "\n";
					$request .= '	<CommercialFlag>' . ( $this->settings['shippingrates'] == "ONLINE" ? 'Y' : 'N' ) . '</CommercialFlag>' . "\n";
					$request .= '</Package>' . "\n";
				}

				$requests[] = $request;
			}
    	}

		return $requests;
    }

    /**
     * Generate a package ID for the request
     *
     * Contains qty and dimension info so we can look at it again later when it comes back from USPS if needed
     *
     * @return string
     */
    public function generate_package_id( $id, $qty, $l, $w, $h, $w ) {
    	return implode( ':', array( $id, $qty, $l, $w, $h, $w ) );
    }

    /**
     * get_country_name function.
     *
     * @access private
     * @return void
     */
    private function get_country_name( $code ) {
		$countries = apply_filters( 'usps_countries', array(
			'AF' => __( 'Afghanistan', 'usps-woocommerce-shipping' ),
			'AX' => __( '&#197;land Islands', 'usps-woocommerce-shipping' ),
			'AL' => __( 'Albania', 'usps-woocommerce-shipping' ),
			'DZ' => __( 'Algeria', 'usps-woocommerce-shipping' ),
			'AD' => __( 'Andorra', 'usps-woocommerce-shipping' ),
			'AO' => __( 'Angola', 'usps-woocommerce-shipping' ),
			'AI' => __( 'Anguilla', 'usps-woocommerce-shipping' ),
			'AQ' => __( 'Antarctica', 'usps-woocommerce-shipping' ),
			'AG' => __( 'Antigua and Barbuda', 'usps-woocommerce-shipping' ),
			'AR' => __( 'Argentina', 'usps-woocommerce-shipping' ),
			'AM' => __( 'Armenia', 'usps-woocommerce-shipping' ),
			'AW' => __( 'Aruba', 'usps-woocommerce-shipping' ),
			'AU' => __( 'Australia', 'usps-woocommerce-shipping' ),
			'AT' => __( 'Austria', 'usps-woocommerce-shipping' ),
			'AZ' => __( 'Azerbaijan', 'usps-woocommerce-shipping' ),
			'BS' => __( 'Bahamas', 'usps-woocommerce-shipping' ),
			'BH' => __( 'Bahrain', 'usps-woocommerce-shipping' ),
			'BD' => __( 'Bangladesh', 'usps-woocommerce-shipping' ),
			'BB' => __( 'Barbados', 'usps-woocommerce-shipping' ),
			'BY' => __( 'Belarus', 'usps-woocommerce-shipping' ),
			'BE' => __( 'Belgium', 'usps-woocommerce-shipping' ),
			'PW' => __( 'Belau', 'usps-woocommerce-shipping' ),
			'BZ' => __( 'Belize', 'usps-woocommerce-shipping' ),
			'BJ' => __( 'Benin', 'usps-woocommerce-shipping' ),
			'BM' => __( 'Bermuda', 'usps-woocommerce-shipping' ),
			'BT' => __( 'Bhutan', 'usps-woocommerce-shipping' ),
			'BO' => __( 'Bolivia', 'usps-woocommerce-shipping' ),
			'BQ' => __( 'Bonaire, Saint Eustatius and Saba', 'usps-woocommerce-shipping' ),
			'BA' => __( 'Bosnia and Herzegovina', 'usps-woocommerce-shipping' ),
			'BW' => __( 'Botswana', 'usps-woocommerce-shipping' ),
			'BV' => __( 'Bouvet Island', 'usps-woocommerce-shipping' ),
			'BR' => __( 'Brazil', 'usps-woocommerce-shipping' ),
			'IO' => __( 'British Indian Ocean Territory', 'usps-woocommerce-shipping' ),
			'VG' => __( 'British Virgin Islands', 'usps-woocommerce-shipping' ),
			'BN' => __( 'Brunei', 'usps-woocommerce-shipping' ),
			'BG' => __( 'Bulgaria', 'usps-woocommerce-shipping' ),
			'BF' => __( 'Burkina Faso', 'usps-woocommerce-shipping' ),
			'BI' => __( 'Burundi', 'usps-woocommerce-shipping' ),
			'KH' => __( 'Cambodia', 'usps-woocommerce-shipping' ),
			'CM' => __( 'Cameroon', 'usps-woocommerce-shipping' ),
			'CA' => __( 'Canada', 'usps-woocommerce-shipping' ),
			'CV' => __( 'Cape Verde', 'usps-woocommerce-shipping' ),
			'KY' => __( 'Cayman Islands', 'usps-woocommerce-shipping' ),
			'CF' => __( 'Central African Republic', 'usps-woocommerce-shipping' ),
			'TD' => __( 'Chad', 'usps-woocommerce-shipping' ),
			'CL' => __( 'Chile', 'usps-woocommerce-shipping' ),
			'CN' => __( 'China', 'usps-woocommerce-shipping' ),
			'CX' => __( 'Christmas Island', 'usps-woocommerce-shipping' ),
			'CC' => __( 'Cocos (Keeling) Islands', 'usps-woocommerce-shipping' ),
			'CO' => __( 'Colombia', 'usps-woocommerce-shipping' ),
			'KM' => __( 'Comoros', 'usps-woocommerce-shipping' ),
			'CG' => __( 'Congo (Brazzaville)', 'usps-woocommerce-shipping' ),
			'CD' => __( 'Congo (Kinshasa)', 'usps-woocommerce-shipping' ),
			'CK' => __( 'Cook Islands', 'usps-woocommerce-shipping' ),
			'CR' => __( 'Costa Rica', 'usps-woocommerce-shipping' ),
			'HR' => __( 'Croatia', 'usps-woocommerce-shipping' ),
			'CU' => __( 'Cuba', 'usps-woocommerce-shipping' ),
			'CW' => __( 'Cura&Ccedil;ao', 'usps-woocommerce-shipping' ),
			'CY' => __( 'Cyprus', 'usps-woocommerce-shipping' ),
			'CZ' => __( 'Czech Republic', 'usps-woocommerce-shipping' ),
			'DK' => __( 'Denmark', 'usps-woocommerce-shipping' ),
			'DJ' => __( 'Djibouti', 'usps-woocommerce-shipping' ),
			'DM' => __( 'Dominica', 'usps-woocommerce-shipping' ),
			'DO' => __( 'Dominican Republic', 'usps-woocommerce-shipping' ),
			'EC' => __( 'Ecuador', 'usps-woocommerce-shipping' ),
			'EG' => __( 'Egypt', 'usps-woocommerce-shipping' ),
			'SV' => __( 'El Salvador', 'usps-woocommerce-shipping' ),
			'GQ' => __( 'Equatorial Guinea', 'usps-woocommerce-shipping' ),
			'ER' => __( 'Eritrea', 'usps-woocommerce-shipping' ),
			'EE' => __( 'Estonia', 'usps-woocommerce-shipping' ),
			'ET' => __( 'Ethiopia', 'usps-woocommerce-shipping' ),
			'FK' => __( 'Falkland Islands', 'usps-woocommerce-shipping' ),
			'FO' => __( 'Faroe Islands', 'usps-woocommerce-shipping' ),
			'FJ' => __( 'Fiji', 'usps-woocommerce-shipping' ),
			'FI' => __( 'Finland', 'usps-woocommerce-shipping' ),
			'FR' => __( 'France', 'usps-woocommerce-shipping' ),
			'GF' => __( 'French Guiana', 'usps-woocommerce-shipping' ),
			'PF' => __( 'French Polynesia', 'usps-woocommerce-shipping' ),
			'TF' => __( 'French Southern Territories', 'usps-woocommerce-shipping' ),
			'GA' => __( 'Gabon', 'usps-woocommerce-shipping' ),
			'GM' => __( 'Gambia', 'usps-woocommerce-shipping' ),
			'GE' => __( 'Georgia', 'usps-woocommerce-shipping' ),
			'DE' => __( 'Germany', 'usps-woocommerce-shipping' ),
			'GH' => __( 'Ghana', 'usps-woocommerce-shipping' ),
			'GI' => __( 'Gibraltar', 'usps-woocommerce-shipping' ),
			'GR' => __( 'Greece', 'usps-woocommerce-shipping' ),
			'GL' => __( 'Greenland', 'usps-woocommerce-shipping' ),
			'GD' => __( 'Grenada', 'usps-woocommerce-shipping' ),
			'GP' => __( 'Guadeloupe', 'usps-woocommerce-shipping' ),
			'GT' => __( 'Guatemala', 'usps-woocommerce-shipping' ),
			'GG' => __( 'Guernsey', 'usps-woocommerce-shipping' ),
			'GN' => __( 'Guinea', 'usps-woocommerce-shipping' ),
			'GW' => __( 'Guinea-Bissau', 'usps-woocommerce-shipping' ),
			'GY' => __( 'Guyana', 'usps-woocommerce-shipping' ),
			'HT' => __( 'Haiti', 'usps-woocommerce-shipping' ),
			'HM' => __( 'Heard Island and McDonald Islands', 'usps-woocommerce-shipping' ),
			'HN' => __( 'Honduras', 'usps-woocommerce-shipping' ),
			'HK' => __( 'Hong Kong', 'usps-woocommerce-shipping' ),
			'HU' => __( 'Hungary', 'usps-woocommerce-shipping' ),
			'IS' => __( 'Iceland', 'usps-woocommerce-shipping' ),
			'IN' => __( 'India', 'usps-woocommerce-shipping' ),
			'ID' => __( 'Indonesia', 'usps-woocommerce-shipping' ),
			'IR' => __( 'Iran', 'usps-woocommerce-shipping' ),
			'IQ' => __( 'Iraq', 'usps-woocommerce-shipping' ),
			'IE' => __( 'Ireland', 'usps-woocommerce-shipping' ),
			'IM' => __( 'Isle of Man', 'usps-woocommerce-shipping' ),
			'IL' => __( 'Israel', 'usps-woocommerce-shipping' ),
			'IT' => __( 'Italy', 'usps-woocommerce-shipping' ),
			'CI' => __( 'Ivory Coast', 'usps-woocommerce-shipping' ),
			'JM' => __( 'Jamaica', 'usps-woocommerce-shipping' ),
			'JP' => __( 'Japan', 'usps-woocommerce-shipping' ),
			'JE' => __( 'Jersey', 'usps-woocommerce-shipping' ),
			'JO' => __( 'Jordan', 'usps-woocommerce-shipping' ),
			'KZ' => __( 'Kazakhstan', 'usps-woocommerce-shipping' ),
			'KE' => __( 'Kenya', 'usps-woocommerce-shipping' ),
			'KI' => __( 'Kiribati', 'usps-woocommerce-shipping' ),
			'KW' => __( 'Kuwait', 'usps-woocommerce-shipping' ),
			'KG' => __( 'Kyrgyzstan', 'usps-woocommerce-shipping' ),
			'LA' => __( 'Laos', 'usps-woocommerce-shipping' ),
			'LV' => __( 'Latvia', 'usps-woocommerce-shipping' ),
			'LB' => __( 'Lebanon', 'usps-woocommerce-shipping' ),
			'LS' => __( 'Lesotho', 'usps-woocommerce-shipping' ),
			'LR' => __( 'Liberia', 'usps-woocommerce-shipping' ),
			'LY' => __( 'Libya', 'usps-woocommerce-shipping' ),
			'LI' => __( 'Liechtenstein', 'usps-woocommerce-shipping' ),
			'LT' => __( 'Lithuania', 'usps-woocommerce-shipping' ),
			'LU' => __( 'Luxembourg', 'usps-woocommerce-shipping' ),
			'MO' => __( 'Macao S.A.R., China', 'usps-woocommerce-shipping' ),
			'MK' => __( 'Macedonia', 'usps-woocommerce-shipping' ),
			'MG' => __( 'Madagascar', 'usps-woocommerce-shipping' ),
			'MW' => __( 'Malawi', 'usps-woocommerce-shipping' ),
			'MY' => __( 'Malaysia', 'usps-woocommerce-shipping' ),
			'MV' => __( 'Maldives', 'usps-woocommerce-shipping' ),
			'ML' => __( 'Mali', 'usps-woocommerce-shipping' ),
			'MT' => __( 'Malta', 'usps-woocommerce-shipping' ),
			'MH' => __( 'Marshall Islands', 'usps-woocommerce-shipping' ),
			'MQ' => __( 'Martinique', 'usps-woocommerce-shipping' ),
			'MR' => __( 'Mauritania', 'usps-woocommerce-shipping' ),
			'MU' => __( 'Mauritius', 'usps-woocommerce-shipping' ),
			'YT' => __( 'Mayotte', 'usps-woocommerce-shipping' ),
			'MX' => __( 'Mexico', 'usps-woocommerce-shipping' ),
			'FM' => __( 'Micronesia', 'usps-woocommerce-shipping' ),
			'MD' => __( 'Moldova', 'usps-woocommerce-shipping' ),
			'MC' => __( 'Monaco', 'usps-woocommerce-shipping' ),
			'MN' => __( 'Mongolia', 'usps-woocommerce-shipping' ),
			'ME' => __( 'Montenegro', 'usps-woocommerce-shipping' ),
			'MS' => __( 'Montserrat', 'usps-woocommerce-shipping' ),
			'MA' => __( 'Morocco', 'usps-woocommerce-shipping' ),
			'MZ' => __( 'Mozambique', 'usps-woocommerce-shipping' ),
			'MM' => __( 'Myanmar', 'usps-woocommerce-shipping' ),
			'NA' => __( 'Namibia', 'usps-woocommerce-shipping' ),
			'NR' => __( 'Nauru', 'usps-woocommerce-shipping' ),
			'NP' => __( 'Nepal', 'usps-woocommerce-shipping' ),
			'NL' => __( 'Netherlands', 'usps-woocommerce-shipping' ),
			'AN' => __( 'Netherlands Antilles', 'usps-woocommerce-shipping' ),
			'NC' => __( 'New Caledonia', 'usps-woocommerce-shipping' ),
			'NZ' => __( 'New Zealand', 'usps-woocommerce-shipping' ),
			'NI' => __( 'Nicaragua', 'usps-woocommerce-shipping' ),
			'NE' => __( 'Niger', 'usps-woocommerce-shipping' ),
			'NG' => __( 'Nigeria', 'usps-woocommerce-shipping' ),
			'NU' => __( 'Niue', 'usps-woocommerce-shipping' ),
			'NF' => __( 'Norfolk Island', 'usps-woocommerce-shipping' ),
			'KP' => __( 'North Korea', 'usps-woocommerce-shipping' ),
			'NO' => __( 'Norway', 'usps-woocommerce-shipping' ),
			'OM' => __( 'Oman', 'usps-woocommerce-shipping' ),
			'PK' => __( 'Pakistan', 'usps-woocommerce-shipping' ),
			'PS' => __( 'Palestinian Territory', 'usps-woocommerce-shipping' ),
			'PA' => __( 'Panama', 'usps-woocommerce-shipping' ),
			'PG' => __( 'Papua New Guinea', 'usps-woocommerce-shipping' ),
			'PY' => __( 'Paraguay', 'usps-woocommerce-shipping' ),
			'PE' => __( 'Peru', 'usps-woocommerce-shipping' ),
			'PH' => __( 'Philippines', 'usps-woocommerce-shipping' ),
			'PN' => __( 'Pitcairn', 'usps-woocommerce-shipping' ),
			'PL' => __( 'Poland', 'usps-woocommerce-shipping' ),
			'PT' => __( 'Portugal', 'usps-woocommerce-shipping' ),
			'QA' => __( 'Qatar', 'usps-woocommerce-shipping' ),
			'RE' => __( 'Reunion', 'usps-woocommerce-shipping' ),
			'RO' => __( 'Romania', 'usps-woocommerce-shipping' ),
			'RU' => __( 'Russia', 'usps-woocommerce-shipping' ),
			'RW' => __( 'Rwanda', 'usps-woocommerce-shipping' ),
			'BL' => __( 'Saint Barth&eacute;lemy', 'usps-woocommerce-shipping' ),
			'SH' => __( 'Saint Helena', 'usps-woocommerce-shipping' ),
			'KN' => __( 'Saint Kitts and Nevis', 'usps-woocommerce-shipping' ),
			'LC' => __( 'Saint Lucia', 'usps-woocommerce-shipping' ),
			'MF' => __( 'Saint Martin (French part)', 'usps-woocommerce-shipping' ),
			'SX' => __( 'Saint Martin (Dutch part)', 'usps-woocommerce-shipping' ),
			'PM' => __( 'Saint Pierre and Miquelon', 'usps-woocommerce-shipping' ),
			'VC' => __( 'Saint Vincent and the Grenadines', 'usps-woocommerce-shipping' ),
			'SM' => __( 'San Marino', 'usps-woocommerce-shipping' ),
			'ST' => __( 'S&atilde;o Tom&eacute; and Pr&iacute;ncipe', 'usps-woocommerce-shipping' ),
			'SA' => __( 'Saudi Arabia', 'usps-woocommerce-shipping' ),
			'SN' => __( 'Senegal', 'usps-woocommerce-shipping' ),
			'RS' => __( 'Serbia', 'usps-woocommerce-shipping' ),
			'SC' => __( 'Seychelles', 'usps-woocommerce-shipping' ),
			'SL' => __( 'Sierra Leone', 'usps-woocommerce-shipping' ),
			'SG' => __( 'Singapore', 'usps-woocommerce-shipping' ),
			'SK' => __( 'Slovakia', 'usps-woocommerce-shipping' ),
			'SI' => __( 'Slovenia', 'usps-woocommerce-shipping' ),
			'SB' => __( 'Solomon Islands', 'usps-woocommerce-shipping' ),
			'SO' => __( 'Somalia', 'usps-woocommerce-shipping' ),
			'ZA' => __( 'South Africa', 'usps-woocommerce-shipping' ),
			'GS' => __( 'South Georgia/Sandwich Islands', 'usps-woocommerce-shipping' ),
			'KR' => __( 'South Korea', 'usps-woocommerce-shipping' ),
			'SS' => __( 'South Sudan', 'usps-woocommerce-shipping' ),
			'ES' => __( 'Spain', 'usps-woocommerce-shipping' ),
			'LK' => __( 'Sri Lanka', 'usps-woocommerce-shipping' ),
			'SD' => __( 'Sudan', 'usps-woocommerce-shipping' ),
			'SR' => __( 'Suriname', 'usps-woocommerce-shipping' ),
			'SJ' => __( 'Svalbard and Jan Mayen', 'usps-woocommerce-shipping' ),
			'SZ' => __( 'Swaziland', 'usps-woocommerce-shipping' ),
			'SE' => __( 'Sweden', 'usps-woocommerce-shipping' ),
			'CH' => __( 'Switzerland', 'usps-woocommerce-shipping' ),
			'SY' => __( 'Syria', 'usps-woocommerce-shipping' ),
			'TW' => __( 'Taiwan', 'usps-woocommerce-shipping' ),
			'TJ' => __( 'Tajikistan', 'usps-woocommerce-shipping' ),
			'TZ' => __( 'Tanzania', 'usps-woocommerce-shipping' ),
			'TH' => __( 'Thailand', 'usps-woocommerce-shipping' ),
			'TL' => __( 'Timor-Leste', 'usps-woocommerce-shipping' ),
			'TG' => __( 'Togo', 'usps-woocommerce-shipping' ),
			'TK' => __( 'Tokelau', 'usps-woocommerce-shipping' ),
			'TO' => __( 'Tonga', 'usps-woocommerce-shipping' ),
			'TT' => __( 'Trinidad and Tobago', 'usps-woocommerce-shipping' ),
			'TN' => __( 'Tunisia', 'usps-woocommerce-shipping' ),
			'TR' => __( 'Turkey', 'usps-woocommerce-shipping' ),
			'TM' => __( 'Turkmenistan', 'usps-woocommerce-shipping' ),
			'TC' => __( 'Turks and Caicos Islands', 'usps-woocommerce-shipping' ),
			'TV' => __( 'Tuvalu', 'usps-woocommerce-shipping' ),
			'UG' => __( 'Uganda', 'usps-woocommerce-shipping' ),
			'UA' => __( 'Ukraine', 'usps-woocommerce-shipping' ),
			'AE' => __( 'United Arab Emirates', 'usps-woocommerce-shipping' ),
			'GB' => __( 'United Kingdom', 'usps-woocommerce-shipping' ),
			'US' => __( 'United States', 'usps-woocommerce-shipping' ),
			'UY' => __( 'Uruguay', 'usps-woocommerce-shipping' ),
			'UZ' => __( 'Uzbekistan', 'usps-woocommerce-shipping' ),
			'VU' => __( 'Vanuatu', 'usps-woocommerce-shipping' ),
			'VA' => __( 'Vatican', 'usps-woocommerce-shipping' ),
			'VE' => __( 'Venezuela', 'usps-woocommerce-shipping' ),
			'VN' => __( 'Vietnam', 'usps-woocommerce-shipping' ),
			'WF' => __( 'Wallis and Futuna', 'usps-woocommerce-shipping' ),
			'EH' => __( 'Western Sahara', 'usps-woocommerce-shipping' ),
			'WS' => __( 'Western Samoa', 'usps-woocommerce-shipping' ),
			'YE' => __( 'Yemen', 'usps-woocommerce-shipping' ),
			'ZM' => __( 'Zambia', 'usps-woocommerce-shipping' ),
			'ZW' => __( 'Zimbabwe', 'woocommerce' )
		));

	    if ( isset( $countries[ $code ] ) ) {
		    return strtoupper( $countries[ $code ] );
	    } else {
		    return false;
	    }
    }

    /**
     * calculate_flat_rate_box_rate function.
     *
     * @access private
     * @param mixed $package
     * @return void
     */
    private function calculate_flat_rate_box_rate( $package, $box_type = 'priority' ) {
	    global $woocommerce;

	    $cost = 0;

	  	if ( ! class_exists( 'WF_Boxpack' ) )
	  		include_once 'class-wf-packing.php';

	    $boxpack  = new WF_Boxpack();
	    $domestic = in_array( $package['destination']['country'], $this->domestic ) ? true : false;
	    $added    = array();

	    // Define boxes
		foreach ( $this->flat_rate_boxes as $service_code => $box ) {

			if ( $box['box_type'] != $box_type )
				continue;

			$domestic_service = substr( $service_code, 0, 1 ) == 'd' ? true : false;

			if ( $domestic && $domestic_service || ! $domestic && ! $domestic_service ) {
				$newbox = $boxpack->add_box( $box['length'], $box['width'], $box['height'] );

				$newbox->set_max_weight( $box['weight'] );
				$newbox->set_id( $service_code );

				if ( isset( $box['volume'] ) && method_exists( $newbox, 'set_volume' ) ) {
					$newbox->set_volume( $box['volume'] );
				}

				if ( isset( $box['type'] ) && method_exists( $newbox, 'set_type' ) ) {
					$newbox->set_type( $box['type'] );
				}

				$added[] = $service_code . ' - ' . $box['name'] . ' (' . $box['length'] . 'x' . $box['width'] . 'x' . $box['height'] . ')';
			}
		}

		$this->debug( 'Calculating USPS Flat Rate with boxes: ' . implode( ', ', $added ) );

		// Add items
		foreach ( $package['contents'] as $item_id => $values ) {

			if ( ! $values['data']->needs_shipping() )
				continue;

			if ( $values['data']->length && $values['data']->height && $values['data']->width && $values['data']->weight ) {

				$dimensions = array( $values['data']->length, $values['data']->height, $values['data']->width );

			} else {
				$this->debug( sprintf( __( 'Product #%d is missing dimensions! Using 1x1x1.', 'usps-woocommerce-shipping' ), $item_id ), 'error' );

				$dimensions = array( 1, 1, 1 );
			}

			for ( $i = 0; $i < $values['quantity']; $i ++ ) {
				$boxpack->add_item(
					wc_get_dimension( $dimensions[2], 'in' ),
					wc_get_dimension( $dimensions[1], 'in' ),
					wc_get_dimension( $dimensions[0], 'in' ),
					wc_get_weight( $values['data']->get_weight(), 'lbs' ),
					$values['data']->get_price(),
					$item_id //WF: Adding Item Id and Quantity as meta.
				);
			}
		}

		// Pack it
		$boxpack->pack();

		// Get packages
		$flat_packages = $boxpack->get_packages();

		if ( $flat_packages ) {
			foreach ( $flat_packages as $flat_package ) {

				if ( isset( $this->flat_rate_boxes[ $flat_package->id ] ) ) {

					$this->debug( 'Packed ' . $flat_package->id . ' - ' . $this->flat_rate_boxes[ $flat_package->id ]['name'] );

					// Get pricing
					$box_pricing  = $this->settings['shippingrates'] == 'ONLINE' && isset( $this->flat_rate_pricing[ $flat_package->id ]['online'] ) ? $this->flat_rate_pricing[ $flat_package->id ]['online'] : $this->flat_rate_pricing[ $flat_package->id ]['retail'];

					if ( is_array( $box_pricing ) ) {
						if ( isset( $box_pricing[ $package['destination']['country'] ] ) ) {
							$box_cost = $box_pricing[ $package['destination']['country'] ];
						} else {
							$box_cost = $box_pricing['*'];
						}
					} else {
						$box_cost = $box_pricing;
					}

					// Fees
					if ( ! empty( $this->flat_rate_fee ) ) {
						$sym = substr( $this->flat_rate_fee, 0, 1 );
						$fee = $sym == '-' ? substr( $this->flat_rate_fee, 1 ) : $this->flat_rate_fee;

						if ( strstr( $fee, '%' ) ) {
							$fee = str_replace( '%', '', $fee );

							if ( $sym == '-' )
								$box_cost = $box_cost - ( $box_cost * ( floatval( $fee ) / 100 ) );
							else
								$box_cost = $box_cost + ( $box_cost * ( floatval( $fee ) / 100 ) );
						} else {
							if ( $sym == '-' )
								$box_cost = $box_cost - $fee;
							else
								$box_cost += $fee;
						}

						if ( $box_cost < 0 )
							$box_cost = 0;
					}

					$cost += $box_cost;

				} else {
					return; // no match
				}

			}

			if ( $box_type == 'express' ) {
				$label = ! empty( $this->settings['flat_rate_express_title'] ) ? $this->settings['flat_rate_express_title'] : ( $domestic ? '' : 'International ' ) . 'Priority Mail Express Flat Rate&#0174;';
			} else {
				$label = ! empty( $this->settings['flat_rate_priority_title'] ) ? $this->settings['flat_rate_priority_title'] : ( $domestic ? '' : 'International ' ) . 'Priority Mail Flat Rate&#0174;';
			}

			return array(
				'id' 	=> $this->id . ':flat_rate_box_' . $box_type,
				'label' => $label,
				'cost' 	=> $cost,
				'sort'  => ( $box_type == 'express' ? -1 : -2 )
			);
		}
    }
	
	
    public function debug( $message, $type = 'notice' ) {
    	if ( $this->debug && !is_admin()) { //WF: is_admin check added.
    		if ( version_compare( WOOCOMMERCE_VERSION, '2.1', '>=' ) ) {
    			wc_add_notice( $message, $type );
    		} else {
    			global $woocommerce;
    			$woocommerce->add_message( $message );
    		}
		}
    }

	/**
     * wf_get_package_requests function.
     *
     * @access public
     * @return requests
     */
    public function wf_get_api_rate_box_data( $package, $packing_method ) {
	    $this->packing_method 	= $packing_method;
		$requests 				= $this->get_package_requests( $package );
		$package_data_array 	= array();
		
		if ( $requests ) {
			foreach ( $requests as $key => $request ) {
				$package_data 				= array();
				$xml_usps_package_data 		= simplexml_load_string($request);
				$package_data['ID'] 		= $xml_usps_package_data->attributes()->ID;
				
				// PS: Some of PHP versions doesn't allow to combining below two line of code as one. 
				// id_array must have value at this point. Force setting it to 1 if it is not.
				$id_array 							= explode( ":", $xml_usps_package_data->attributes()->ID );
				$package_data[ 'BoxCount' ] 		= isset($id_array[1]) ? $id_array[1] : 1;
				$package_data[ 'WeightInOunces' ] 	= ( $xml_usps_package_data->Pounds * 16 ) + $xml_usps_package_data->Ounces;
				$package_data[ 'POZipCode' ] 		= $xml_usps_package_data->ZipDestination;
				$package_data[ 'Container' ] 		= $xml_usps_package_data->Container;
				$package_data[ 'Width' ] 			= $xml_usps_package_data->Width;
				$package_data[ 'Length' ] 			= $xml_usps_package_data->Length;
				$package_data[ 'Height' ] 			= $xml_usps_package_data->Height;
				$package_data[ 'Girth' ] 			= $xml_usps_package_data->Girth;
				$package_data[ 'Size' ] 			= $xml_usps_package_data->Size;
				
				$package_data_array[] 				= $package_data; 
			}
		}
    	return $package_data_array;
    }
	
}
