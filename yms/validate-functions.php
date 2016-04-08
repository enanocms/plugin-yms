<?php

function yms_send_reply($result, $api_key = '', $extra = array())
{
  header('Content-type: text/plain');
  
  global $g_api_key;
  
  if ( empty($api_key) )
    $api_key = $g_api_key;
  
  if ( empty($api_key) )
    $api_key = base64_encode("\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00");
  
  $now = gmdate("Y-m-d\TH:i:s");
  echo yms_generate_signed_response(array_merge($extra, array(
      't' => $now,
      'status' => $result
    )), $api_key);
  
  exit;
}

function yms_generate_signed_response($response, $api_key)
{
  $hash = yms_val_sign($response, $api_key);
  $result = "h={$hash}\n";
  foreach ( $response as $key => $value )
  {
    if ( $value === null )
    {
      continue;
    }
    $result .= "{$key}={$value}\n";
  }
  return trim($result);
}

function yms_val_sign($response, $api_key)
{
  foreach ( array('h', 'title', 'auth') as $key )
    if ( isset($response[$key]) )
      unset($response[$key]);
    
  ksort($response);
  
  $signstr = array();
  foreach ( $response as $key => $value )
  {
    $signstr[] = "$key=$value";
  }
  
  $signstr = implode('&', $signstr);
  
  $api_key = yms_hex_encode(base64_decode($api_key));
  $hash = hmac_sha1($signstr, $api_key);
  $hash = yms_hex_decode($hash);
  $hash = base64_encode($hash);
  
  return $hash;
}
