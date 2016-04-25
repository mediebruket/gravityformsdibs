<?php
/*
  Plugin Name: Gravity Forms DIBS
  Plugin URI: http://nettbutikk.mediebruket.no
  Description: DIBS add-on for Gravity Forms. Supports D2 and DX platform.
  Version: 1.0.5
  Author: Mediebruket
  Author URI: http://mediebruket.no
*/

require_once 'GFDibsDao.php';
require_once 'GFDibsAddOn.php';
require_once 'GFDibsHook.php';
require_once 'GFDibsUpdater.php';

$plugin_file = __FILE__;

if ( !function_exists('_debug') ){
  function _debug($message = null){
    if( WP_DEBUG === true ){
      error_log( print_r( $message, true ) );
    }
  }
}

register_activation_hook( $plugin_file , array('GFDibsHook', 'setupDBTables') );
$GFDibsUpdater = new GFDibsUpdater( __FILE__ );
?>