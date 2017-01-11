<?php

/**
 * Returns OTP data. Numeric except for public and private IDs, which are hex.
 * @return array Associative
 */

function yms_decode_otp($otp, $key)
{
  static $aes = false;
  if ( !is_object($aes) )
    $aes = AESCrypt::singleton(128, 128);
  
  $return = array();
  
  $otp = yms_tobinary($otp);
  if ( strlen($otp) != 22 )
  {
    return false;
  }
  $key = yms_tobinary($key);
  if ( strlen($key) != 16 )
  {
    return false;
  }
  
  $cryptpart = yms_hex_encode(substr($otp, 6, 16));
  $publicid = substr($otp, 0, 6);
  
  $return['publicid'] = yms_hex_encode($publicid);
  $otp_decrypted = $aes->decrypt($cryptpart, $key, ENC_HEX);
  $crc_is_good = yms_validate_crc($otp_decrypted);
  $return['privateid'] = yms_hex_encode(substr($otp_decrypted, 0, 6));
  $return['session'] = yms_unpack_int(strrev(substr($otp_decrypted, 6, 2)));
  $return['timestamp'] = yms_unpack_int(strrev(substr($otp_decrypted, 8, 3)));
  $return['count'] = yms_unpack_int(substr($otp_decrypted, 11, 1));
  $return['random'] = yms_unpack_int(substr($otp_decrypted, 12, 2));
  $return['crc'] = yms_unpack_int(substr($otp_decrypted, 14, 2));
  $return['crc_good'] = $crc_is_good;
  
  return $return;
}

function yms_unpack_int($str)
{
  $return = 0;
  for ( $i = 0; $i < strlen($str); $i++ )
  {
    $return = $return << 8;
    $return = $return | ord($str{$i});
  }
  return $return;
}

function yms_crc16($buffer)
{
  $buffer = yms_tobinary($buffer);
  
  $m_crc=0x5af0;
  for($bpos=0; $bpos<strlen($buffer); $bpos++)
  {
    $m_crc ^= ord($buffer[$bpos]);
    for ($i=0; $i<8; $i++)
    {
      $j=$m_crc & 1;
      $m_crc >>= 1;
      if ($j) $m_crc ^= 0x8408;
    }
  }
  return $m_crc;
}

function yms_validate_crc($token)
{
  $crc = yms_crc16($token);
  return $crc == 0;
}

function yms_within($test, $control, $fuzz)
{
  return abs($control - $test) <= $fuzz;
}
