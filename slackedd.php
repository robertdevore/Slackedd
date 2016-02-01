<?php
/**
 * Plugin Name:	Slackedd
 * Plugin URI:	http://www.deviodigital.com/slackedd
 * Description:	Receive notification through Slack when you make a sale with Easy Digital Downloads
 * Version:		1.0
 * Author:		Devio Digital
 * Author URI:	http://www.deviodigital.com
 * Text Domain: slackedd
 *
 * @package		Slackedd
 */

/* Exit if accessed directly */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin update notification
 *
 * @since	   1.0.0
 */
include_once('updater.php');

if (is_admin()) { /* Double check that everything is happening in the admin */
	$config = array(
		'slug' => plugin_basename(__FILE__), /* This is the slug of your plugin */
		'proper_folder_name' => 'slackedd', /* This is the name of the folder your plugin lives in */
		'api_url' => 'https://api.github.com/repos/deviodigital/slackedd', /* The GitHub API url of your GitHub repo */
		'raw_url' => 'https://raw.github.com/deviodigital/slackedd/master', /* The GitHub raw url of your GitHub repo */
		'github_url' => 'https://github.com/deviodigital/slackedd', /* The GitHub url of your GitHub repo */
		'zip_url' => 'https://github.com/deviodigital/slackedd/zipball/master', /* The zip url of the GitHub repo */
		'sslverify' => true, /* Whether WP should check the validity of the SSL cert when getting an update */
		'requires' => '3.0', /* Which version of WordPress does your plugin require? */
		'tested' => '4.4.1', /* Which version of WordPress is your plugin tested up to? */
		'readme' => 'README.md', /* Which file to use as the readme for the version number */
		'access_token' => '', /* Access private repositories by authorizing under Appearance > GitHub Updates when this example plugin is installed */
	);
	new WP_GitHub_Updater($config);
}

/**
 * Check to see if Easy Digital Downloads exists
 *
 * @since	   1.0.0
 */
function slackedd_edd_activation() {
	if ( ! class_exists( 'Easy_Digital_Downloads' ) ) {
		/* display notice */
		add_action( 'admin_notices', 'slackedd_admin_notice' );
		return;
	}
}
add_action( 'admin_init', 'slackedd_edd_activation' );

/**
 * Notification display if Easy Digital Downloads isn't installed
 *
 * @since	   1.0.0
 */
function slackedd_admin_notice() {
	if ( ! is_plugin_active( 'easy-digital-downloads/easy-digital-downloads.php' ) ) {
		echo '<div class="error"><p><strong>Slackedd</strong> requires <a href="http://wordpress.org/plugins/easy-digital-downloads/" target="_blank"><strong>Easy Digital Downloads</strong></a> in order to be activated.</p></div>';
	}
}

/**
 * Add settings link to plugin information
 *
 * @since	   1.0.0
 */
function slackedd_settings_link( $links ) {
	$plugin_links = array(
		'<a href="' . admin_url( 'edit.php?post_type=download&page=edd-settings&tab=extensions' ) . '">Settings</a>',
	);
	return array_merge( $plugin_links, $links );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'slackedd_settings_link', 10, 2 );

/**
 * Slackedd Settings Page
 *
 * @since	   1.0.0
 */
function slackedd_settings( $settings ) {
	$slack_settings = array(
		array(
			'id' 		=> 'slackedd_header',
			'name' 		=> '<strong>Slackedd Settings</strong>',
			'desc' 		=> '',
			'type' 		=> 'header',
			'size' 		=> 'regular',
		),
		array(
			'id'		=> 'slackedd_enable_notification',
			'name'  	=> 'Enable Slack Notifications',
			'desc'  	=> 'Check this to turn on Slack notifications',
			'type'  	=> 'checkbox',
			'std'   	=> '1',
		),
		array(
			'id'		=> 'slackedd_bot_name',
			'name'	  => 'Bot Name',
			'desc'	  => 'Enter the name of your Bot.',
			'type'	  => 'text',
			'size'	  => 'all-options',
		),
		array(
			'id'		=> 'slackedd_icon_emoji',
			'name'	  => 'Bot Icon',
			'desc'	  => 'Enter the emoji icon for your Bot. (<a href="http://emoji-cheat-sheet.com" target="_blank">emoji cheat sheet</a>)',
			'type'	  => 'text',
			'size'	  => 'all-options',
		),
		array(
			'id'		=> 'slackedd_channel',
			'name'	  => 'Channel Name',
			'desc'	  => 'Enter the name of the Channel your notifications should be sent to (example: #general)',
			'type'	  => 'text',
			'size'	  => 'all-options',
		),
		array(
			'id'		=> 'slackedd_webhook_url',
			'name'	  => 'Webhook URL',
			'desc'	  => 'Enter the url of the webhook created for the channel above. This can be created <a href="https://my.slack.com/services/new/incoming-webhook/" target="_blank">here</a>',
			'type'	  => 'text',
			'size'	  => 'all-options',
		),
		array(
			'id'		=> 'slackedd_hide_order_number',
			'name'  	=> 'Hide order number?',
			'desc'  	=> 'Check this to show the payment order number in the notification',
			'type'  	=> 'checkbox',
			'std'   	=> '1',
		),
		array(
			'id'		=> 'slackedd_hide_order_items',
			'name'  	=> 'Hide order items?',
			'desc'  	=> 'Check this to show the items ordered in the notification',
			'type'  	=> 'checkbox',
			'std'   	=> '1',
		),
		array(
			'id'		=> 'slackedd_hide_payment_gateway',
			'name'  	=> 'Hide payment gateway?',
			'desc'  	=> 'Check this to show the payment gateway in the notification',
			'type'  	=> 'checkbox',
			'std'   	=> '1',
		),
		array(
			'id'		=> 'slackedd_hide_buyer_information',
			'name'  	=> 'Hide buyer information?',
			'desc'  	=> 'Check this to hide the buyer\'s name and email address in the notification',
			'type'  	=> 'checkbox',
			'std'   	=> '1',
		),
	);
	return array_merge( $settings, $slack_settings );
}
add_filter( 'edd_settings_extensions', 'slackedd_settings' );

/**
 * Slackedd Notification Codes
 *
 * @since	   1.0.0
 */
function slackedd_notification( $payment_id ) {

	$edd_options = edd_get_settings();

	/* Check that the user has all required information added for the plugin to work */
	$enable_slack   		= isset( $edd_options['slackedd_enable_notification'] ) ? $edd_options['slackedd_enable_notification'] : '';
	$hide_order_number   	= isset( $edd_options['slackedd_hide_order_number'] ) ? $edd_options['slackedd_hide_order_number'] : '';
	$hide_order_items		= isset( $edd_options['slackedd_hide_order_items'] ) ? $edd_options['slackedd_hide_order_items'] : '';
	$hide_payment_gateway	= isset( $edd_options['slackedd_hide_payment_gateway'] ) ? $edd_options['slackedd_hide_payment_gateway'] : '';
	$hide_buyer_information = isset( $edd_options['slackedd_hide_buyer_information'] ) ? $edd_options['slackedd_hide_buyer_information'] : '';
	$slack_channel  	 	= isset( $edd_options['slackedd_channel'] ) ? $edd_options['slackedd_channel'] : '';
	$webhook_url		 	= isset( $edd_options['slackedd_webhook_url'] )? $edd_options['slackedd_webhook_url'] : '';

	if ( ! ( $enable_slack && $slack_channel && $webhook_url ) ) {
		return;
	}

	$enable_slack		= isset( $edd_options['slackedd_enable_notification'] ) ? $edd_options['slackedd_enable_notification'] : '';
	$emoji				= ! empty( $edd_options['slackedd_icon_emoji'] ) ? $edd_options['slackedd_icon_emoji'] : ':moneybag:';
	$bot_name			= ! empty( $edd_options['slackedd_bot_name'] ) ? $edd_options['slackedd_bot_name'] : 'Slackedd';
	$order_amount		= esc_attr( edd_format_amount( edd_get_payment_amount( $payment_id ) ) );
	$currency_symbol	= edd_currency_symbol( $payment_meta['currency'] );
	$currency_symbol	= html_entity_decode( $currency_symbol, ENT_QUOTES, 'UTF-8' );
	$payment_meta   	= edd_get_payment_meta( $payment_id );
	$cart_items	 		= edd_get_payment_meta_cart_details( $payment_id );
	$items_sold	 		= '';
	$order_id 			= edd_get_payment_number( $payment_id );

	foreach ( $cart_items as $key => $cart_item ) {
		$name   		= $cart_item['name'];
		$price  		= $cart_item['price'];
		$items_sold	   .= "*Name:* ". $name ." | *Price:* ". $currency_symbol ."". $price ." \n";
	}

	$gateway			= edd_get_payment_gateway( $payment_id );
	$payment_method 	= edd_get_gateway_admin_label( $gateway );
	$user_data 			= $payment_meta['user_info'];

	/* Display the new sale introduction */
	$message  			= "A new sale has occurred at " . get_bloginfo( 'name' ) ." \n\n";

	/* Show or hide order number based on user preference in settings page */
	if ( ! ( $hide_order_number ) ) {
		$message .= "*Order* <". get_bloginfo( 'home' ) ."/wp-admin/edit.php?post_type=download&page=edd-payment-history&view=view-order-details&id=". $order_id ."|#". $order_id ."> \n";
	}

	/* Show the order total */
	$message .= "*Order Total:* ". $currency_symbol ."". $order_amount ." \n\n";

	/* Show or hide payment gateway based on user preference in settings page */
	if ( ! ( $hide_payment_gateway ) ) {
		$message .= "*Payment Method:* ". $payment_method ." \n\n";
	}

	/* Show or hide order items based on user preference in settings page */
	if ( ! ( $hide_order_items ) ) {
		$message .= "*". edd_get_cart_quantity() ." ITEM(S):* \n";
		$message .= $items_sold;
	}

	/* Show or hide order number based on user preference in settings page */
	if ( ! ( $hide_buyer_information ) ) {
		$message .= "\n\n *Customer:* ".$user_data['first_name']." ".$user_data['last_name']." ".$user_data['email']."\n";
	}

	$attachment = array();

	$attachment[] = array(
		'color'			=> 'good',
		'fallback'		=> 'New sale notification of '. $currency_symbol .''. $price .' at ' . get_bloginfo( 'name' ),
		'mrkdwn_in'		=> array( 'text' ),
		'text'			=> $message,
		'title'			=> 'New Sale Notification!',
	);

	$payload = array(
		'attachments'	=> $attachment,
		'channel'		=> $slack_channel,
		'icon_emoji'	=> $emoji,
		'username'		=> $bot_name,
	);

	$args = array(
		'body'			=> json_encode( $payload ),
		'timeout'		=> 30,
	);

	$response = wp_remote_post( $webhook_url, $args );
	return;
}
add_action( 'edd_complete_purchase', 'slackedd_notification' );
