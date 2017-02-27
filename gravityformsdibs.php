<?php
/*
  Plugin Name: Gravity Forms DIBS
  Plugin URI: http://nettbutikk.mediebruket.no
  Description: DIBS add-on for Gravity Forms. Supports D2 and DX platform.
  Version: 1.3.3
  Author: Mediebruket
  Author URI: http://mediebruket.no
*/

require_once 'conf/conf.php';
require_once 'utils/utils.php';
require_once 'GFDibsDao.php';
require_once 'GFDibsAddOn.php';
require_once 'GFDibsHook.php';
require_once 'GFDibsUpdater.php';

$plugin_file;

$plugin_file = __FILE__;

if ( !function_exists('_debug') ){
  function _debug($message = null){
    if( WP_DEBUG === true ){
      error_log( print_r( $message, true ) );
    }
  }
}

register_activation_hook( $plugin_file , array('GFDibsHook', 'setupDBTables') );
add_action( 'init', array('GFDibsHook', 'checkDBUpdate'), 1 );


$GFDibsUpdater = new GFDibsUpdater( __FILE__ );

if(!function_exists('_log')){
  function _log( $message ) {
    if( WP_DEBUG === true ){
      if( is_array( $message ) || is_object( $message ) ){
        error_log( print_r( $message, true ) );
      } else {
        error_log( $message );
      }
    }
  }
}

if(!function_exists('_debug')){
  function _debug($message) {
    if( WP_DEBUG === true ){
      if( is_array( $message ) || is_object( $message ) ){
        echo "<pre>" . print_r($message, true) . "</pre>";
      } else {
        echo "<pre>" . $message . "</pre>";
      }
    }
  }
}
