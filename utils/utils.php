<?php
if ( !function_exists('_is') ){
  function _is($object, $attribute, $fallback = null ){
    $value = $fallback;
    if ( is_object($object) ){
      if ( isset($object->$attribute) ){
        $value = $object->$attribute;
      }
    }
    else if ( is_array($object) ){

      if ( isset($object[$attribute]) && is_array($object[$attribute]) && count($object[$attribute]) == 1 && isset($object[$attribute][0]) ){
        $value = $object[$attribute][0];
      }
      elseif ( isset($object[$attribute]) ){
        $value = $object[$attribute];
      }
    }


    if ( is_string($value) ){
      return trim($value);
    }
    else{
      // _log($value);
      return $value;
    }

  }
}


if( !function_exists('_get_language') ){
  function _get_language(){
    $wplang = strtolower(get_option( 'WPLANG', $default = 'nb_NO' ));
    return preg_replace('/.*_/', null, $wplang );
  }
}