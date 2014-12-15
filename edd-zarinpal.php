<?php
/**
<<<<<<< HEAD
 * Plugin Name: ZarinPal for EDD
 * Version: 1.0
 * Description: این افزونه درگاه پرداخت <a href="https://zarinpal.com">زرینپال</a> را به افزونه‌ی EDD اضافه می‌کند.
 * Plugin URI: http://eddpersian.ir/
 * Author: Ehsaan
 * Author: http://iehsan.ir/
 */

require_once( 'includes/auto-update.php' );

if ( ! class_exists( 'nusoap_client' ) )
	require_once( 'includes/nusoap.php' );

if ( !function_exists( 'edd_rial' ) ) {
	function edd_rial( $formatted, $currency, $price ) {
		return $price . ' ریال';
	}
	add_filter( 'edd_rial_currency_filter_after', 'edd_rial', 10, 3 );
=======
	Plugin Name: Zarinpal Web Gate for EDD
	Version: 2.0
	Description: این افزونه درگاه <a href="https://zarinpal.com/" target="_blank">زرین پال</a> را به افزونه Easy Digital Downloads اضافه می&zwnj;کند. این افزونه با نسخه  1.9.5 سازگار است.
	Plugin URI: https://zarinpal.com/Labs/Details/WP-EDD
	
	Author: M.Amini
	Author URI: http://haftir.ir
**/
@session_start();

function zpw_edd_rial ($formatted, $currency, $price) {
	return $price . 'ریال';
>>>>>>> origin/master
}

function add_zarinpal_gateway( $gateways ) {
	$gateways['zarinpal'] = array(
		'admin_label'		=>	'زرین‌پال',
		'checkout_label'	=>	'درگاه زرین‌پال'
	);

	return $gateways;
}
add_filter( 'edd_payment_gateways', 'add_zarinpal_gateway' );

function zp_cc_form() { return; }
add_action( 'edd_zarinpal_cc_form', 'zp_cc_form' );

function zp_process( $purchase_data ) {
	global $edd_options;

	$payment_data = array( 
		'price' => $purchase_data['price'], 
		'date' => $purchase_data['date'], 
		'user_email' => $purchase_data['post_data']['edd_email'],
		'purchase_key' => $purchase_data['purchase_key'],
		'currency' => $edd_options['currency'],
		'downloads' => $purchase_data['downloads'],
		'cart_details' => $purchase_data['cart_details'],
		'user_info' => $purchase_data['user_info'],
		'status' => 'pending'
	);
	$payment = edd_insert_payment($payment_data);

	if ( $payment ) {
		@ delete_transient( 'edd_zarinpal_record' );
		set_transient( 'edd_zarinpal_record', $payment );

		$callback = add_query_arg( 'verify', 'zarinpal', get_permalink( $edd_options['success_page'] ) );
		$amount = intval( $payment_data['price'] ) / 10;
		$description = 'پرداخت صورت حساب ' . $purchase_data['purchase_key'];
		$merchant = $edd_options['zp_merchant'];

		$endpoint = ( $edd_options['zp_deserver'] == '1' ) ? 'https://de.zarinpal.com/pg/services/WebGate/wsdl' : 'https://ir.zarinpal.com/pg/services/WebGate/wsdl';
		$accesspage = ( $edd_options['zp_webgate'] == '1' ) ? 'https://www.zarinpal.com/pg/StartPay/%s' : 'https://www.zarinpal.com/pg/StartPay/%s/ZarinGate';

		$client = new nusoap_client( $endpoint, 'wsdl' );
		$client->soap_defencoding = 'UTF-8';

		$data = array(
			'MerchantID'		=>	$merchant,
			'Amount'			=>	$amount,
			'Description'		=>	$description,
			'Email'				=>	$payment_data['user_email'],
			'CallbackURL'		=>	$callback
		);

		$result = $client->call( 'PaymentRequest', array( $data ) );
		edd_insert_payment_note( $payment, 'کد پاسخ زرین پال: ' . $result['Status'] . ' و کد پرداخت: ' . $result['Authority'] );
		if ( $result['Status'] == '100' ) {
			wp_redirect( sprintf( $accesspage, $result['Authority'] ) );
			exit;
		} else {
			wp_die( 'خطای ' . $result['Status'] . ': در اتصال به درگاه پرداخت مشکلی پیش آمد' );
			exit;
		}
	} else {
		edd_send_back_to_checkout('?payment-mode=' . $purchase_data['post_data']['edd-gateway']);
	}
}
add_action( 'edd_gateway_zarinpal', 'zp_process' );

function zp_verify() {
	global $edd_options;

	if ( isset( $_GET['verify'] ) &&  $_GET['verify'] == 'zarinpal' && isset( $_GET['Status'] ) && false != ( get_transient( 'edd_zarinpal_record' ) ) ) {
		$payment_id = get_transient( 'edd_zarinpal_record' );
		delete_transient( 'edd_zarinpal_record' );

		if ( $_GET['Status'] == 'OK' ) {
			$endpoint = ( $edd_options['zp_deserver'] == '1' ) ? 'https://de.zarinpal.com/pg/services/WebGate/wsdl' : 'https://ir.zarinpal.com/pg/services/WebGate/wsdl';
			$client = new nusoap_client( $endpoint, 'wsdl' );
			$client->soap_defencoding = 'UTF-8';
			$amount = intval( edd_get_payment_amount( $payment_id ) ) / 10;

			$result = $client->call( 'PaymentVerification', array( array(
				'MerchantID'		=>	$edd_options['zp_merchant'],
				'Authority'			=>	esc_attr( $_GET['Authority'] ),
				'Amount'			=>	$amount
			) ) );
			edd_empty_cart();

			edd_insert_payment_note( $payment_id, 'نتیجه بازگشت: وضعیت: ' . $result['Status'] . ' و کد پرداخت: ' . $result['RefId'] );
			if ( $result['Status'] == 100 ) {
				edd_update_payment_status( $payment_id, 'publish' );
				edd_send_to_success_page();
			} else {
				edd_update_payment_status( $payment_id, 'failed' );
				wp_redirect( get_permalink( $edd_options['failure_page'] ) );
			}
			exit;
		} else {
			edd_update_payment_status( $payment_id, 'revoked' );
			wp_redirect( get_permalink( $edd_options['failure_page'] ) );
			exit;
		}
	}
}
add_action( 'init', 'zp_verify' );

function zp_settings( $settings ) {
	$zarinpal_options = array(
		array(
			'id'		=>	'zarinpal_settings',
			'type'		=>	'header',
			'name'		=>	'پیکربندی زرین‌پال - <a href="http://eddpersian.ir/">EDD Persian</a> &ndash; <a href="mailto:info@eddpersian.ir">گزارش خرابی</a>'
		),
		array(
			'id'		=>	'zp_merchant',
			'type'		=>	'text',
			'name'		=>	'مرچنت‌کد',
			'desc'		=>	'کد درگاه که از سایت زرین‌پال دریافت کرده‌اید را وارد کنید'
		),
		array(
			'id'		=>	'zp_webgate',
			'type'		=>	'checkbox',
			'name'		=>	'استفاده از درگاه غیرمستقیم',
			'desc'		=>	'برای استفاده از درگاه وب‌گیت (غیرمستقیم) این گزینه را فعال کنید'
		),
		array(
			'id'		=>	'zp_deserver',
			'type'		=>	'checkbox',
			'name'		=>	'استفاده از نود آلمان',
			'desc'		=>	'برای استفاده از سرور لمان این گزینه را فعال کنید. اگر هاستینگ شما خارجی است، پیشنهاد می‌کنیم این گزینه را فعال کنید'
		)
	);

	return array_merge( $settings, $zarinpal_options );
}
add_filter( 'edd_settings_gateways', 'zp_settings' );
