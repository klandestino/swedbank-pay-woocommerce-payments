<?php

defined( 'ABSPATH' ) || exit;

use SwedbankPay\Payments\WooCommerce\WC_Swedbank_Pay_Transactions;
use SwedbankPay\Core\Adapter\WC_Adapter;
use SwedbankPay\Core\Core;

class WC_Gateway_Swedbank_Pay_Trustly extends WC_Gateway_Swedbank_Pay_Cc {

	/**
	 * Merchant Token
	 * @var string
	 */
	public $merchant_token = '';

	/**
	 * Payee Id
	 * @var string
	 */
	public $payee_id = '';

	/**
	 * Subsite
	 * @var string
	 */
	public $subsite = '';

	/**
	 * Test Mode
	 * @var string
	 */
	public $testmode = 'yes';

	/**
	 * Debug Mode
	 * @var string
	 */
	public $debug = 'yes';

	/**
	 * Locale
	 * @var string
	 */
	public $culture = 'en-US';

	/**
	 * Checkout Method
	 * @var string
	 */
	public $method = 'redirect';

	/**
	 * Init
	 */
	public function __construct() {
		$this->transactions = WC_Swedbank_Pay_Transactions::instance();

		$this->id           = 'payex_psp_trustly';
		$this->has_fields   = true;
		$this->method_title = __( 'Trustly Payments', 'swedbank-pay-woocommerce-payments' );
		$this->icon         = apply_filters(
			'wc_swedbank_pay_trustly_icon',
			plugins_url( '/assets/images/trustly.png', dirname( __FILE__ ) )
		);
		$this->supports     = array(
			'products',
			'refunds',
		);

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Define user set variables
		$this->enabled        = isset( $this->settings['enabled'] ) ? $this->settings['enabled'] : 'no';
		$this->title          = isset( $this->settings['title'] ) ? $this->settings['title'] : '';
		$this->description    = isset( $this->settings['description'] ) ? $this->settings['description'] : '';
		$this->merchant_token = isset( $this->settings['merchant_token'] ) ? $this->settings['merchant_token'] : $this->merchant_token;
		$this->payee_id       = isset( $this->settings['payee_id'] ) ? $this->settings['payee_id'] : $this->payee_id;
		$this->subsite        = isset( $this->settings['subsite'] ) ? $this->settings['subsite'] : $this->subsite;
		$this->testmode       = isset( $this->settings['testmode'] ) ? $this->settings['testmode'] : $this->testmode;
		$this->debug          = isset( $this->settings['debug'] ) ? $this->settings['debug'] : $this->debug;
		$this->culture        = isset( $this->settings['culture'] ) ? $this->settings['culture'] : $this->culture;
		$this->method         = isset( $this->settings['method'] ) ? $this->settings['method'] : $this->method;
		$this->terms_url      = isset( $this->settings['terms_url'] ) ? $this->settings['terms_url'] : get_site_url();
		$this->logo_url       = isset( $this->settings['logo_url'] ) ? $this->settings['logo_url'] : $this->logo_url;

		// JS Scrips
		add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );

		// Actions
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );

		// Payment listener/API hook
		add_action( 'woocommerce_api_' . strtolower( __CLASS__ ), array( $this, 'return_handler' ) );

		// Payment confirmation
		add_action( 'the_post', array( $this, 'payment_confirm' ) );

		$this->adapter = new WC_Adapter( $this );
		$this->core    = new Core( $this->adapter );
	}

	/**
	 * Initialise Settings Form Fields
	 * @return string|void
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'        => array(
				'title'   => __( 'Enable/Disable', 'swedbank-pay-woocommerce-payments' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable plugin', 'swedbank-pay-woocommerce-payments' ),
				'default' => 'no',
			),
			'title'          => array(
				'title'       => __( 'Title', 'swedbank-pay-woocommerce-payments' ),
				'type'        => 'text',
				'description' => __(
					'This controls the title which the user sees during checkout.',
					'swedbank-pay-woocommerce-payments'
				),
				'default'     => __( 'Trustly Payments', 'swedbank-pay-woocommerce-payments' ),
			),
			'description'    => array(
				'title'       => __( 'Description', 'swedbank-pay-woocommerce-payments' ),
				'type'        => 'text',
				'description' => __(
					'This controls the description which the user sees during checkout.',
					'swedbank-pay-woocommerce-payments'
				),
				'default'     => __( 'Trustly Payments', 'swedbank-pay-woocommerce-payments' ),
			),
			'merchant_token' => array(
				'title'       => __( 'Merchant Token', 'swedbank-pay-woocommerce-payments' ),
				'type'        => 'text',
				'description' => __( 'Merchant Token', 'swedbank-pay-woocommerce-payments' ),
				'default'     => $this->merchant_token,
			),
			'payee_id'       => array(
				'title'       => __( 'Payee Id', 'swedbank-pay-woocommerce-payments' ),
				'type'        => 'text',
				'description' => __( 'Payee Id', 'swedbank-pay-woocommerce-payments' ),
				'default'     => $this->payee_id,
			),
			'subsite'        => array(
				'title'       => __( 'Subsite', 'woocommerce-gateway-payex-checkout' ),
				'type'        => 'text',
				'description' => __( 'Subsite', 'woocommerce-gateway-payex-checkout' ),
				'default'     => $this->subsite,
			),
			'testmode'       => array(
				'title'   => __( 'Test Mode', 'swedbank-pay-woocommerce-payments' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Swedbank Pay Test Mode', 'swedbank-pay-woocommerce-payments' ),
				'default' => $this->testmode,
			),
			'debug'          => array(
				'title'   => __( 'Debug', 'swedbank-pay-woocommerce-payments' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable logging', 'swedbank-pay-woocommerce-payments' ),
				'default' => $this->debug,
			),
			'culture'        => array(
				'title'       => __( 'Language', 'swedbank-pay-woocommerce-payments' ),
				'type'        => 'select',
				'options'     => array(
					'en-US' => 'English',
					'sv-SE' => 'Swedish',
					'nb-NO' => 'Norway',
					'fi-FI' => 'Finish',
				),
				'description' => __(
					'Language of pages displayed by Swedbank Pay during payment.',
					'swedbank-pay-woocommerce-payments'
				),
				'default'     => $this->culture,
			),
			'method'         => array(
				'title'       => __( 'Checkout Method', 'swedbank-pay-woocommerce-payments' ),
				'type'        => 'select',
				'options'     => array(
					'redirect'   => __( 'Redirect', 'swedbank-pay-woocommerce-payments' ),
					'seamless'   => __( 'Seamless View', 'swedbank-pay-woocommerce-payments' ),
				),
				'description' => __( 'Checkout Method', 'swedbank-pay-woocommerce-payments' ),
				'default'     => $this->method,
			),
			'terms_url'      => array(
				'title'       => __( 'Terms & Conditions Url', 'swedbank-pay-woocommerce-payments' ),
				'type'        => 'text',
				'description' => __( 'Requires https.', 'swedbank-pay-woocommerce-payments' ),
				'desc_tip'    => true,
				'default'     => get_site_url(),
				'sanitize_callback' => function( $value ) {
					if ( ! empty( $value ) ) {
						if ( ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
							throw new Exception( __( 'Terms & Conditions Url is invalid.', 'swedbank-pay-woocommerce-payments' ) );
						} elseif ( 'https' !== parse_url( $value, PHP_URL_SCHEME ) ) {
							throw new Exception( __( 'Terms & Conditions Url should use https scheme.', 'swedbank-pay-woocommerce-payments' ) );
						}
					}

					return $value;
				},
			),
			'logo_url'              => array(
				'title'       => __( 'Logo Url', 'swedbank-pay-woocommerce-payments' ),
				'type'        => 'text',
				'description' => __( 'The URL that will be used for showing the customer logo. Requires https.', 'swedbank-pay-woocommerce-payments' ),
				'desc_tip'    => true,
				'default'     => $this->get_custom_logo(),
				'sanitize_callback' => function( $value ) {
					if ( ! empty( $value ) ) {
						if ( ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
							throw new Exception( __( 'Logo Url is invalid.', 'swedbank-pay-woocommerce-payments' ) );
						} elseif ( 'https' !== parse_url( $value, PHP_URL_SCHEME ) ) {
							throw new Exception( __( 'Logo Url should use https scheme.', 'swedbank-pay-woocommerce-payments' ) );
						}
					}

					return $value;
				},
			),
		);
	}

	/**
	 * payment_scripts function.
	 *
	 * Outputs scripts used for payment
	 *
	 * @return void
	 */
	public function payment_scripts() {
		if ( ! is_checkout() || 'no' === $this->enabled || 'seamless' !== $this->method ) {
			return;
		}

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_enqueue_script(
			'featherlight',
			untrailingslashit(
				plugins_url(
					'/',
					__FILE__
				)
			) . '/../assets/js/featherlight/featherlight' . $suffix . '.js',
			array( 'jquery' ),
			'1.7.13',
			true
		);

		wp_enqueue_style(
			'featherlight-css',
			untrailingslashit(
				plugins_url(
					'/',
					__FILE__
				)
			) . '/../assets/js/featherlight/featherlight' . $suffix . '.css',
			array(),
			'1.7.13',
			'all'
		);

		wp_enqueue_style(
			'trustly-css',
			untrailingslashit(
				plugins_url(
					'/',
					__FILE__
				)
			) . '/../assets/css/trustly' . $suffix . '.css',
			array(),
			null,
			'all'
		);

		wp_register_script(
			'wc-sb-trustly',
			untrailingslashit( plugins_url( '/', __FILE__ ) ) . '/../assets/js/trustly' . $suffix . '.js',
			array(
				'jquery',
				'wc-checkout',
				'featherlight',
			),
			false,
			true
		);

		// Localize the script with new data
		wp_localize_script(
			'wc-sb-trustly',
			'WC_Gateway_Swedbank_Pay_Trustly',
			array(
				'culture' => $this->culture,
			)
		);

		wp_enqueue_script( 'wc-sb-trustly' );
	}

	/**
	 * If There are no payment fields show the description if set.
	 */
	public function payment_fields() {
		parent::payment_fields();
	}

	/**
	 * Thank you page
	 *
	 * @param $order_id
	 *
	 * @return void
	 */
	public function thankyou_page( $order_id ) {
		//
	}

	/**
	 * Process Payment
	 *
	 * @param int $order_id
	 *
	 * @return array|false
	 */
	public function process_payment( $order_id ) {
		// Process payment
		try {
			$result = $this->core->initiateTrustlyPayment(
				$order_id
			);
		} catch ( Exception $e ) {
			wc_add_notice( $e->getMessage(), 'error' );

			return false;
		}

		// Save payment ID
		update_post_meta( $order_id, '_payex_payment_id', $result['payment']['id'] );

		switch ( $this->method ) {
			case 'redirect':
				// Get Redirect

				return array(
					'result'   => 'success',
					'redirect' => $result->getOperationByRel( 'redirect-sale' ),
				);
			case 'seamless':
				return array(
					'result'                   => 'success',
					'redirect'                 => '#!swedbank-pay-trustly',
					'is_swedbank_pay_trustly'  => true,
					'js_url'                   => $result->getOperationByRel( 'view-sales' ),
				);

			default:
				wc_add_notice( __( 'Wrong method', 'swedbank-pay-woocommerce-payments' ), 'error' );

				return false;
		}
	}

	/**
	 * Process Refund
	 *
	 * If the gateway declares 'refunds' support, this will allow it to refund
	 * a passed in amount.
	 *
	 * @param int $order_id
	 * @param float $amount
	 * @param string $reason
	 *
	 * @return  bool|wp_error True or false based on success, or a WP_Error object
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return false;
		}

		// Full Refund
		if ( is_null( $amount ) ) {
			$amount = $order->get_total();
		}

		try {
			// Disable status change hook
			remove_action(
				'woocommerce_order_status_changed',
				'\SwedbankPay\Payments\WooCommerce\WC_Swedbank_Plugin::order_status_changed',
				10
			);

			$this->core->refund( $order->get_id(), $amount, $reason );

			return true;
		} catch ( \Exception $e ) {
			return new WP_Error( 'refund', $e->getMessage() );
		}
	}

	/**
	 * Capture
	 *
	 * @param WC_Order|int $order
	 * @param mixed $amount
	 * @param mixed $vat_amount
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function capture_payment( $order, $amount = false, $vat_amount = 0 ) {
		if ( is_int( $order ) ) {
			$order = wc_get_order( $order );
		}

		if ( is_int( $order ) ) {
			$order = wc_get_order( $order );
		}

		try {
			// Disable status change hook
			remove_action(
				'woocommerce_order_status_changed',
				'\SwedbankPay\Payments\WooCommerce\WC_Swedbank_Plugin::order_status_changed',
				10
			);

			$this->core->capture( $order->get_id(), $amount, $vat_amount );
		} catch ( \SwedbankPay\Core\Exception $e ) {
			throw new Exception( $e->getMessage() );
		}
	}

	/**
	 * Cancel
	 *
	 * @param WC_Order|int $order
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function cancel_payment( $order ) {
		if ( is_int( $order ) ) {
			$order = wc_get_order( $order );
		}

		try {
			// Disable status change hook
			remove_action(
				'woocommerce_order_status_changed',
				'\SwedbankPay\Payments\WooCommerce\WC_Swedbank_Plugin::order_status_changed',
				10
			);

			$this->core->cancel( $order->get_id() );
		} catch ( \SwedbankPay\Core\Exception $e ) {
			throw new Exception( $e->getMessage() );
		}
	}
}


