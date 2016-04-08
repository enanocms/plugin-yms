<?php

function page_Special_YubikeyValidate()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  global $do_gzip;
  $do_gzip = false;
  
  // Check parameters
  if ( !isset($_GET['id']) )
  {
    yms_send_reply('MISSING_PARAMETER', '', array('info' => 'id'));
  }
  
  if ( !isset($_GET['otp']) )
  {
    yms_send_reply('MISSING_PARAMETER', '', array('info' => 'otp'));
  }

  $nonce = null;
  if ( isset($_GET['nonce']) )
  {
    $nonce = $_GET['nonce'];
  }
  
  // first, get API key so we can properly sign responses
  $id = intval($_GET['id']);
  $q = $db->sql_query("SELECT apikey FROM " . table_prefix . "yms_clients WHERE id = $id;");
  if ( !$q )
    $db->_die();
  
  if ( $db->numrows($q) < 1 )
    yms_send_reply("NO_SUCH_CLIENT");
  
  list($g_api_key) = $db->fetchrow_num($q);
  $db->free_result($q);
  
  // check API key
  if ( isset($_GET['h']) )
  {
    $hex_api_key = yms_hex_encode(base64_decode($g_api_key));
    $right_sig = yubikey_sign($_GET, $hex_api_key);
    if ( $right_sig !== $_GET['h'] )
    {
      yms_send_reply('BAD_SIGNATURE');
    }
  }
  
  $GLOBALS['g_api_key'] =& $g_api_key;
  
  yms_send_reply(yms_validate_otp($_GET['otp'], $id), '', array('nonce' => $nonce, 'otp' => $_GET['otp']));
}

