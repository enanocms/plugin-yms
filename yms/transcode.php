<?php

define('CHARSET_HEX', '0123456789abcdef');
define('CHARSET_MODHEX', 'cbdefghijklnrtuv');
define('CHARSET_BASE64', 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=');

function yms_modhex_encode($str)
{
  if ( !preg_match('/^[' . CHARSET_HEX . ']+$/', $str) )
    $str = yms_hex_encode($str);
  
  return strtr($str, '0123456789abcdef', 'cbdefghijklnrtuv');
}

function yms_modhex_decode($str)
{
  if ( !preg_match('/^[' . CHARSET_MODHEX . ']+$/', $str) )
    return false;
  
  return strtr($str, 'cbdefghijklnrtuv', '0123456789abcdef');
}

function yms_hex_decode($str)
{
  if ( !preg_match('/^[' . CHARSET_HEX . ']+$/', $str) )
    return false;
  
  if ( strlen($str) % 2 != 0 )
    return '';
  
  $return = '';
  for ( $i = 0; $i < strlen($str); $i+=2 )
  {
    $chr = substr($str, $i, 2);
    $return .= chr(intval(hexdec($chr)));
  }
  return $return;
}

function yms_hex_encode($str)
{
  $return = '';
  for ( $i = 0; $i < strlen($str); $i++ )
  {
    $chr = dechex(ord($str{$i}));
    if ( strlen($chr) < 2 )
      $chr = "0$chr";
    $return .= $chr;
  }
  return $return;
}

function yms_tobinary($str)
{
  if ( preg_match('/^[' . CHARSET_HEX . ']+$/', $str) )
  {
    return yms_hex_decode($str);
  }
  else if ( preg_match('/^[' . CHARSET_MODHEX . ']+$/', $str) )
  {
    return yms_hex_decode(yms_modhex_decode($str));
  }
  else if ( preg_match('#^[' . CHARSET_BASE64 . ']+$#', $str) )
  {
    return base64_decode($str);
  }
  return $str;
}

function yms_randbin($len)
{
  $ret = '';
  for ( $i = 0; $i < $len; $i++ )
  {
    $ret .= chr(mt_rand(0, 255));
  }
  return $ret;
}

