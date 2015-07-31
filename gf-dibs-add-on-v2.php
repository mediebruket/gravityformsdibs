<?php
/*
  Plugin Name: Gravity Forms DIBS v2
  Plugin URI:
  Description: Supports D2 and DX platform
  Version: 2.0
  Author: Mediebruket
  Author URI:
*/

//import necessary class
// require_once 'bb_validation.php';
// require_once 'form_settings.php';
require_once 'GFDibsDao.php';
require_once 'GFDibsAddOn.php';
require_once 'Hook.php';

function register_gf_dibs_addon() {
  $DAO = new GFDibsDao();
  $DAO->setupTables();

}
register_activation_hook( __FILE__, 'register_gf_dibs_addon' );


function include_frontend_scripts(){
  wp_enqueue_script('jquery');

  wp_register_script( 'frontend', plugin_dir_url(__FILE__).'js.js' );
  wp_enqueue_script('frontend');
}

add_action( 'wp_enqueue_scripts', 'include_frontend_scripts' );

add_filter("gform_pre_render", array("Hook", "preRenderForm"));
add_action("gform_confirmation", array("Hook", "dibsTransition"), 10, 4 );
add_filter("gform_form_tag",  array("Hook", "formTag"), 10, 2);
add_action("gform_entry_detail", array('Hook', 'addPaymentDetails'), 10, 2);
add_filter("gform_disable_notification", array('Hook', 'disableNotifications'), 10, 4);
add_filter("gform_pre_send_email", array('Hook', "parseNotification"), 10, 1);
add_filter("gform_entries_column_filter", array("Hook" , "changeColumnData") , 10, 5);
add_filter("gform_leads_before_export", array("Hook" , "modifyExportData") , 10, 3);
?>