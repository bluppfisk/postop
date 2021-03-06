<?php
/*
Plugin Name:  Post-op Feedback Collector
Plugin URI:   https://github.com/bluppfisk/postop
Description:  Feedback Collector for Businesses
Version:      20180104
Author:       Sander Van de Moortel
Author URI:   https://worldofnonging.com
License:      MIT
Text Domain:  wporg
Domain Path:  /languages
*/


// Make sure we don't expose any info if called directly
if ( !function_exists( 'add_action' ) ) {
	echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
	exit;
}

register_activation_hook( __FILE__, 'postop_activate' );
register_deactivation_hook( __FILE__, 'postop_uninstall' );
register_uninstall_hook( __FILE__, 'postop_uninstall');

add_action( 'init', 'postop_instantiate' );


function postop_instantiate()
{
	if ( is_admin() ) {
		require_once( plugin_dir_path( __FILE__ ) . 'class.postop-admin.php' );
		$postop_admin = new Postop_Admin();
		$postop_admin->init();
	} else {
		require_once ( plugin_dir_path( __FILE__ ) . 'class.postop.php' );
		$postop = new Postop();
		$postop->init();
	}
}


function postop_activate()
{
	// set up our plugin
	global $wpdb;

	$solicit_review_page_id = get_option('postop_review_page_id', false);

	if ($solicit_review_page_id == false) {

		$post_arr = array (
			'post_title'   => 'Geef uw feedback',
			'post_content' => '[postop_review]',
			'post_status'  => 'publish',
			'post_type'	   => 'page',
		);

		$solicit_review_page_id = wp_insert_post($post_arr);

		add_option('postop_review_page_id', $solicit_review_page_id);

	}

	$table_name = $wpdb->prefix . 'postop_reviews';
	
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE IF NOT EXISTS $table_name (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		date datetime,
		given_name varchar(100) NOT NULL,
		family_name varchar(100) NOT NULL,
		email varchar(255) NOT NULL,
		rating int(1),
		body text,
		access_token varchar(50),
		may_be_published boolean,
		approved boolean,
		PRIMARY KEY (id)
	) $charset_collate;";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );

	$wpdb->insert(
		$table_name,
		array(
			'given_name' => 'Sander',
			'family_name' => 'Van de Moortel',
			'email' => 'sander.vandemoortel@gmail.com',
			'access_token' => 'blaapje'
		)
	);
}

function postop_deactivate()
{
	// deactivate our plugin
}

function postop_uninstall()
{
	// remove database
	global $wpdb;
	$table_name = $wpdb->prefix . 'postop_reviews';
	$wpdb->query("DROP TABLE IF EXISTS $table_name");

	// remove associated page
	$solicit_review_page_id = get_option('postop_review_page_id', false);
	wp_delete_post($solicit_review_page_id, true);

	// remove associated option
	delete_option('postop_review_page_id');
}