<?php

function _getConfig($index=null){
  $conf = array(
    'version' => '1.3.2'
    );

  if ( $index){
    return $conf[$index];
  }
  else{
    return $conf;
  }

}

