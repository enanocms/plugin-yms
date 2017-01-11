<?php

function yms_add_yubikey($key, $otp, $client_id = false, $enabled = true, $any_client = false, $notes = false)
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  
  if ( $client_id === false )
    $client_id = $GLOBALS['yms_client_id'];
  
  $key = yms_tobinary($key);
  $otp = yms_tobinary($otp);
  
  if ( strlen($key) != 16 )
  {
    return 'yms_err_addkey_invalid_key';
  }
  
  if ( strlen($otp) != 22 )
  {
    return 'yms_err_addkey_invalid_otp';
  }
  
  $otpdata = yms_decode_otp($otp, $key);
  if ( $otpdata === false )
  {
    return 'yms_err_addkey_invalid_otp';
  }
  if ( !$otpdata['crc_good'] )
  {
    return 'yms_err_addkey_crc_failed';
  }
  
  // make sure it's not already in there
  $q = $db->sql_query('SELECT 1 FROM ' . table_prefix . "yms_yubikeys WHERE public_id = '{$otpdata['publicid']}';");
  if ( !$q )
    $db->_die();
  
  if ( $db->numrows() > 0 )
  {
    $db->free_result();
    return 'yms_err_addkey_key_exists';
  }
  $db->free_result();
  
  $now = time();
  $key = yms_hex_encode($key);
  
  $flags = 0;
  if ( $enabled )
    $flags |= YMS_ENABLED;
  if ( $any_client )
    $flags |= YMS_ANY_CLIENT;
  
  $notes = $notes ? $db->escape(strval($notes)) : '';
  
  $q = $db->sql_query("INSERT INTO " . table_prefix . "yms_yubikeys(client_id, public_id, private_id, session_count, token_count, create_time, access_time, token_time, aes_secret, flags, notes) VALUES\n"
         . "  ($client_id, '{$otpdata['publicid']}', '{$otpdata['privateid']}', {$otpdata['session']}, {$otpdata['count']}, $now, $now, {$otpdata['timestamp']}, '$key', $flags, '$notes');");
  if ( !$q )
    $db->_die();
  
  return true;
}

function yms_chown_yubikey($otp, $client_id = false, $enabled = true, $any_client = false, $notes = false)
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  
  if ( $client_id === false )
    $client_id = $GLOBALS['yms_client_id'];
  
  $otp = yms_tobinary($otp);
  
  if ( strlen($otp) != 22 )
  {
    return 'yms_err_addkey_invalid_otp';
  }
  
  $public_id = yms_hex_encode(substr($otp, 0, 6));
  
  // make sure it's already in there
  $q = $db->sql_query('SELECT id FROM ' . table_prefix . "yms_yubikeys WHERE public_id = '{$public_id}' AND client_id = 0;");
  if ( !$q )
    $db->_die();
  
  if ( $db->numrows() < 1 )
  {
    // this should never happen, as the OTP is put through validation before this function is called
    $db->free_result();
    return 'yms_err_claimkey_owner_invalid';
  }
  
  list($key_id) = $db->fetchrow_num();
  $db->free_result();
  
  $now = time();
  
  $flags = 0;
  if ( $enabled )
    $flags |= YMS_ENABLED;
  if ( $any_client )
    $flags |= YMS_ANY_CLIENT;
  
  $notes = $notes ? $db->escape(strval($notes)) : '';
  
  $q = $db->sql_query("UPDATE " . table_prefix . "yms_yubikeys SET flags = $flags, notes = '$notes', client_id = $client_id WHERE id = $key_id;");
  if ( !$q )
    $db->_die();
  
  return true;
}

function yms_delete_key($id, $client_id = false)
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  
  if ( $client_id === false )
    $client_id = $GLOBALS['yms_client_id'];
  
  $q = $db->sql_query('SELECT 1 FROM ' . table_prefix . "yms_yubikeys WHERE id = $id AND client_id = $client_id;");
  if ( !$q )
    $db->_die();
  
  if ( $db->numrows() < 1 )
  {
    $db->free_result();
    return 'yms_err_delete_not_found';
  }
  $db->free_result();
  
  $q = $db->sql_query('DELETE FROM ' . table_prefix . "yms_yubikeys WHERE id = $id AND client_id = $client_id;");
  if ( !$q )
    $db->_die();
  
  return true;
}

function yms_validate_custom_field($value, $otp, $url)
{
  require_once(ENANO_ROOT . '/includes/http.php');
  $url = strtr($url, array(
      '%c' => rawurlencode($value),
      '%o' => rawurlencode($otp)
    ));
  // do we need to sign this?
  if ( strstr($url, '%h') && ($key = getConfig('yms_claim_auth_key', false)) )
  {
    list(, $signpart) = explode('?', $url);
    $signpart = preg_replace('/(&h=%h|^h=%h&)/', '', $signpart);
    $signpart = yms_ksort_url($signpart);
    
    $key = yms_tobinary($key);
    $key = yms_hex_encode($key);
    $hash = hmac_sha1($signpart, $key);
    $hash = yms_hex_decode($hash);
    $hash = base64_encode($hash);
    
    $url = str_replace('%h', rawurlencode($hash), $url);
  }
  
  // run authentication
  $result = yms_get_url($url);
  $result = yms_parse_auth_result($result, $key);
  
  if ( !$result['sig_valid'] )
    return 'yubiauth_err_response_bad_signature';
  
  if ( $result['status'] !== 'OK' )
  {
    if ( preg_match('/^[A-Z_]+$/', $result['status']) )
      return 'yubiauth_err_response_' . strtolower($result['status']);
    else
      return $result['status'];
  }
  
  // authentication is ok
  return true;
}

function yms_update_counters($id, $scount, $tcount, $client_id = false, $any_client = null)
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  
  if ( !$client_id )
    $client_id = intval($GLOBALS['yms_client_id']);
  
  foreach ( array($id, $scount, $tcount, $client_id) as $var )
    if ( (!is_int($var) && !is_string($var)) || (is_string($var) && !ctype_digit($var)) )
      return "yms_err_expected_int";
    
  $any_client_sql = '';
  if ( is_bool($any_client) )
  {
    $operand = $any_client ? "|" : "& ~";
    $any_client_sql = ", flags = flags " . $operand . YMS_ANY_CLIENT;
  }
    
  $q = $db->sql_query('UPDATE ' . table_prefix . "yms_yubikeys SET session_count = {$scount}, token_count = {$tcount}{$any_client_sql} WHERE id = $id AND client_id = $client_id");
  if ( !$q )
    $db->_die();
  
  return true;
}

function yms_get_url($url)
{
  require_once(ENANO_ROOT . '/includes/http.php');
  
  $url = preg_replace('#^https?://#i', '', $url);
  if ( !preg_match('#^(\[?[a-z0-9-:]+(?:\.[a-z0-9-:]+\]?)*)(?::([0-9]+))?(/.*)$#U', $url, $match) )
  {
    return 'invalid_auth_url';
  }
  $server =& $match[1];
  $port = ( !empty($match[2]) ) ? intval($match[2]) : 80;
  $uri =& $match[3];
  try
  {
    $req = new Request_HTTP($server, $uri, 'GET', $port);
    $response = $req->get_response_body();
  }
  catch ( Exception $e )
  {
    return 'http_failed:' . $e->getMessage();
  }
  
  if ( $req->response_code !== HTTP_OK )
    return 'http_failed_status:' . $req->response_code;
  
  return $response;
}

function yms_parse_auth_result($result, $api_key = false)
{
  $result = explode("\n", trim($result));
  $arr = array();
  foreach ( $result as $line )
  {
    list($name) = explode('=', $line);
    $value = substr($line, strlen($name) + 1);
    $arr[$name] = $value;
  }
  // signature check
  if ( $api_key )
  {
    $signarr = $arr;
    ksort($signarr);
    unset($signarr['h']);
    $signpart = array();
    foreach ( $signarr as $name => $value )
      $signpart[] = "{$name}={$value}";
    
    $signpart = implode('&', $signpart);
    $api_key = yms_hex_encode(yms_tobinary($api_key));
    $right_sig = base64_encode(yms_hex_decode(
                   hmac_sha1($signpart, $api_key)
                 ));
    $arr['sig_valid'] = ( $arr['h'] === $right_sig );
  }
  else
  {
    $arr['sig_valid'] = true;
  }
  return $arr;
}

function yms_ksort_url($signpart)
{
  $arr = array();
  $values = explode('&', $signpart);
  foreach ( $values as $var )
  {
    list($name) = explode('=', $var);
    $value = substr($var, strlen($name) + 1);
    $arr[$name] = $value;
  }
  ksort($arr);
  $result = array();
  foreach ( $arr as $name => $value )
  {
    $result[] = "{$name}={$value}";
  }
  return implode('&', $result);
}

function yms_validate_otp($otp, $id)
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  
  $public_id = yms_modhex_decode(substr($otp, 0, 12));
  if ( !$public_id )
  {
    return 'BAD_OTP';
  }
  // Just in case
  $public_id = $db->escape($public_id);
  
  $q = $db->sql_query("SELECT id, private_id, session_count, token_count, access_time, token_time, aes_secret, flags, client_id FROM " . table_prefix . "yms_yubikeys WHERE ( client_id = 0 or client_id = $id OR flags & " . YMS_ANY_CLIENT . " ) AND public_id = '$public_id';");
  if ( !$q )
    $db->_die();
  
  if ( $db->numrows($q) < 1 )
  {
    return 'NO_SUCH_KEY';
  }
  
  list($yubikey_id, $private_id, $session_count, $token_count, $access_time, $token_time, $aes_secret, $flags, $client_id) = $db->fetchrow_num($q);
  $session_count = intval($session_count);
  $token_count = intval($token_count);
  $access_time = intval($access_time);
  $token_time = intval($token_time);
  
  // check flags
  if ( $client_id > 0 )
  {
    if ( !($flags & YMS_ANY_CLIENT) )
    {
      return 'NO_SUCH_KEY';
    }
  }
  if ( !($flags & YMS_ENABLED) )
  {
    return 'NO_SUCH_KEY';
  }
  
  // decode the OTP
  $otp = yms_decode_otp($otp, $aes_secret);
  
  // check CRC
  if ( !$otp['crc_good'] )
  {
    return 'BAD_OTP';
  }
  
  // check private UID (avoids combining a whitelisted known public UID with the increment part of a malicious token)
  if ( $private_id !== $otp['privateid'] )
  {
    return 'BAD_OTP';
  }
  
  // check counters
  if ( $otp['session'] < $session_count )
  {
    return 'REPLAYED_OTP';
  }
  if ( $otp['session'] == $session_count && $otp['count'] <= $token_count )
  {
    return 'REPLAYED_OTP';
  }
  
  // check timestamp
  if ( $otp['session'] == $session_count )
  {
    $expect_delta = time() - $access_time;
    // Tolerate up to a 0.5Hz deviance from 8Hz. I've observed Yubikey
    // clocks running at 8.32Hz
    $actual_delta = $otp['timestamp'] - $token_time;
    $fuzz = 150 + round(($actual_delta / 7.5) - ($actual_delta / 8.5));
    // Now that we've calculated fuzz, convert the actual delta to quasi-seconds
    $actual_delta /= 8;
    if ( !yms_within($expect_delta, $actual_delta, $fuzz) )
    {
      // if we have a likely wraparound, just pass it
      if ( !($token_time > 0xe80000 && $otp['timestamp'] < 0x080000) )
      {
        return 'BAD_OTP';
      }
    }
    // $debug_array = array('ts_debug_delta_expected' => $expect_delta, 'ts_debug_delta_received' => $actual_delta);
  }
  
  // update DB
  $q = $db->sql_query("UPDATE " . table_prefix . "yms_yubikeys SET session_count = {$otp['session']}, token_count = {$otp['count']}, access_time = " . time() . ", token_time = {$otp['timestamp']} WHERE id = $yubikey_id;");
  if ( !$q )
    $db->_die();
  
  // looks like we're good
  return 'OK';
}
