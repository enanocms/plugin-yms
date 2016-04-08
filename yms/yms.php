<?php

function page_Special_YMS()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  global $lang;
  global $output;
  global $yms_client_id;
  
  $yms_client_id = ($force_cid = getConfig('yms_force_client_id', 0)) > 0 ? intval($force_cid) : $session->user_id;
  
  // Require re-auth?
  if ( !$session->user_logged_in || ($session->auth_level < USER_LEVEL_CHPREF && getConfig('yms_require_reauth', 1) == 1) )
  {
    redirect(makeUrlNS('Special', "Login/$paths->fullpage", 'level=' . USER_LEVEL_CHPREF), '', '', 0);
  }
  
  // Check for Yubikey plugin
  if ( !function_exists('yubikey_validate_otp') )
  {
    die_friendly($lang->get('yms_err_yubikey_plugin_missing_title'), '<p>' . $lang->get('yms_err_yubikey_plugin_missing_body') . '</p>');
  }
  
  // Client switch allowed?
  if ( $session->user_level >= USER_LEVEL_ADMIN && getConfig('yms_claim_enable', 0) == 1 )
  {
    $on_home = empty($_POST) && !$paths->getParam(0);
    
    // yes.
    $configkey = "yms_zeroeditsess_{$session->user_id}";
    if ( getConfig($configkey, 0) == 1 && !isset($_GET['client_switch']) )
    {
      // set to zero
      $yms_client_id = 0;
    }
    else if ( !getConfig($configkey) && isset($_GET['client_switch']) )
    {
      // set to zero + update config
      $yms_client_id = 0;
      setConfig($configkey, 1);
    }
    else if ( getConfig($configkey) && isset($_GET['client_switch']) )
    {
      // turning off
      setConfig($configkey, false);
    }
    
    // display a notice
    if ( $yms_client_id == 0 && $on_home )
    {
      $output->add_after_header('<div class="info-box">' . $lang->get('yms_msg_editing_zero') . '</div>');
    }
  }
  
  // Does the client exist?
  $q = $db->sql_query('SELECT 1 FROM ' . table_prefix . "yms_clients WHERE id = {$yms_client_id};");
  if ( !$q )
    $db->_die();
  
  $client_exists = $db->numrows();
  $db->free_result();
  if ( !$client_exists && $yms_client_id > 0 )
  {
    redirect(makeUrlNS('Special', 'YMSCreateClient'), '', '', 0);
  }
  
  // Check for a subpage request
  if ( $subpage = $paths->getParam(0) )
  {
    if ( preg_match('/^[A-z0-9]+$/', $subpage) )
    {
      if ( function_exists("page_Special_YMS_{$subpage}") )
      {
        // call the subpage
        $return = call_user_func("page_Special_YMS_{$subpage}");
        if ( !$return )
          return false;
        
        // return true = continue exec
      }
    }
  }
  
  //
  // POST processing
  //
  
  if ( isset($_POST['add_aes']) && isset($_POST['add_otp']) )
  {
    $client_id = false;
    $enabled = $_POST['state'] == 'active';
    $any_client = isset($_POST['any_client']);
    $notes = $_POST['notes'];
    
    // Release key?
    if ( $session->user_level >= USER_LEVEL_ADMIN && getConfig('yms_claim_enable', 0) == 1 && isset($_POST['allow_claim']) )
    {
      $client_id = 0;
      // also allow anyone to validate OTPs from it and mark it as active
      $any_client = true;
      $enabled = true;
    }
    
    $result = yms_add_yubikey($_POST['add_aes'], $_POST['add_otp'], $client_id, $enabled, $any_client, $notes);
    yms_send_response('yms_msg_addkey_success', $result);
  }
  else if ( isset($_POST['claim_otp']) && getConfig('yms_claim_enable', 0) == 1 )
  {
    // do we need to validate a custom field?
    if ( ($url = getConfig('yms_claim_auth_url')) && getConfig('yms_claim_auth_field') && getConfig('yms_claim_auth_enable', 0) == 1 )
    {
      if ( ($result = yms_validate_custom_field($_POST['custom_field'], $_POST['claim_otp'], $url)) !== true )
        yms_send_response('n/a', $result);
    }
    
    // validate this OTP, make sure it's all good
    $result = strtolower(yms_validate_otp($_POST['claim_otp'], 0));
    if ( $result !== 'ok' )
      yms_send_response('n/a', "yubiauth_err_response_{$result}");
    
    // change owner
    $client_id = false;
    $enabled = $_POST['state'] == 'active';
    $any_client = isset($_POST['any_client']);
    $notes = $_POST['notes'];
    $result = yms_chown_yubikey($_POST['claim_otp'], $client_id, $enabled, $any_client, $notes);
    yms_send_response('yms_msg_addkey_success', $result);
  }
  else if ( $paths->getParam(0) == 'DeleteKey' && $paths->getParam(2) == 'Confirm' )
  {
    csrf_request_confirm();
    $id = intval($paths->getParam(1));
    $result = yms_delete_key($id);
    yms_send_response('yms_msg_delete_success', $result);
  }
  else if ( isset($_POST['update_counters']) )
  {
    $yk_id  = $_POST['update_counters'];
    $scount = $_POST['session_count'];
    $tcount = $_POST['token_count'];
    $any_client = isset($_POST['any_client']);
    $result = yms_update_counters($yk_id, $scount, $tcount, false, $any_client);
    yms_send_response('yms_msg_counter_update_success', $result);
  }
  
  if ( isset($_GET['toggle']) && isset($_GET['state']) )
  {
    $id = intval($_GET['toggle']);
    if ( $_GET['state'] === 'active' )
      $expr = 'flags | ' . YMS_ENABLED;
    else
      $expr = 'flags & ~' . YMS_ENABLED;
      
    $q = $db->sql_query('UPDATE ' . table_prefix . "yms_yubikeys SET flags = $expr WHERE id = $id AND client_id = {$yms_client_id};");
    if ( !$q )
      $db->die_json();
  }
  
  // Preload JS libraries we need for Yubikey
  $template->preload_js(array('jquery', 'jquery-ui', 'l10n', 'flyin', 'messagebox', 'fadefilter'));
  // Load CSS
  $template->add_header('<link rel="stylesheet" type="text/css" href="' . scriptPath . '/plugins/yms/styles.css" />');
  // Load JS
  $template->add_header('<script type="text/javascript" src="' . scriptPath . '/plugins/yms/cp.js"></script>');
  
  // Send header
  $output->header();
  
  // Message container
  if ( !isset($_GET['ajax'] ) )
    echo '<div id="yms-messages"></div><div id="yms-keylist">';
  
  // Buttons
  ?>
  <div class="yms-buttons">
    <a class="abutton abutton_green icon" style="background-image: url(<?php echo scriptPath; ?>/plugins/yms/icons/key_add.png);"
       href="<?php echo makeUrlNS('Special', 'YMS/AddKey'); ?>" onclick="yms_showpage('AddKey'); return false;">
      <?php echo $lang->get('yms_btn_add_key'); ?>
    </a>
    <?php if ( getConfig('yms_claim_enable', 0) == 1 && $yms_client_id > 0 ): ?>
    <a class="abutton abutton_blue icon" style="background-image: url(<?php echo scriptPath; ?>/plugins/yms/icons/key_add.png);"
       href="<?php echo makeUrlNS('Special', 'YMS/AddPreregisteredKey'); ?>" onclick="yms_showpage('AddPreregisteredKey'); return false;">
      <?php echo $lang->get('yms_btn_add_key_preregistered'); ?>
    </a>
    <?php endif; ?>
  </div>
  <?php
  
  // Pull all Yubikeys
  $q = $db->sql_query('SELECT id, public_id, session_count, create_time, access_time, flags, notes FROM ' . table_prefix . "yms_yubikeys WHERE client_id = {$yms_client_id} ORDER BY id ASC;");
  if ( !$q )
    $db->_die();
  
  if ( $db->numrows() < 1 )
  {
    echo '<h2 class="emptymessage">' . $lang->get('yms_msg_no_yubikeys') . '</h2>';
  }
  else
  {
    ?>
    <div class="tblholder">
    <table border="0" cellspacing="1" cellpadding="4">
    
    <!-- Table header -->
      <tr>
        <th><?php echo $lang->get('yms_th_id'); ?></th>
        <th><?php echo $lang->get('yms_th_publicid'); ?></th>
        <th><?php echo $lang->get('yms_th_createtime'); ?></th>
        <th><?php echo $lang->get('yms_th_accesstime'); ?></th>
        <th><?php echo $lang->get('yms_th_state'); ?></th>
        <th><?php echo $lang->get('yms_th_note'); ?></th>
        <th></th>
      </tr>
    
    <?php
      $cls = 'row2';
      while ( $row = $db->fetchrow($q) )
      {
        $cls = $cls == 'row2' ? 'row1' : 'row2';
        ?>
        <tr>
          <!-- Key ID -->
          <td style="text-align: center;" class="<?php echo $cls; ?>"><?php echo $row['id']; ?></td>
          
          <!-- Public UID -->
          <td style="text-align: left;" class="<?php echo $cls; ?>"><?php echo yms_modhex_encode($row['public_id']); ?></td>
          
          <!-- Create time -->
          <td style="text-align: left;" class="<?php echo $cls; ?>"><?php echo yms_date($row['create_time']); ?></td>
          
          <!-- Access time -->
          <td style="text-align: left;" class="<?php echo $cls; ?>"><?php echo $row['access_time'] <= $row['create_time'] ? $lang->get('yms_msg_access_never') : yms_date($row['access_time']); ?></td>
          
          <!-- State -->
          <td style="text-align: center;" class="<?php echo $cls; ?>"><?php echo yms_state_indicator($row['flags'], $row['id']); ?></td>
          
          <!-- Notes -->
          <td style="text-align: center;" class="<?php echo $cls; ?>"><?php echo yms_notes_cell($row['notes'], $row['id']); ?></td>
          
          <!-- Actions -->
          <td style="text-align: center;" class="<?php echo $cls; ?>"><?php echo yms_show_actions($row);  ?></td>
        </tr>
        <?php
      }
    ?>
    
    </table>
    </div>
    
    <br /><br />
    <?php
  }
  
  ?>
  <a href="<?php echo makeUrlNS('Special', 'YMS/Converter'); ?>" onclick="yms_showpage('Converter'); return false;" class="abutton abutton_red icon"
     style="background-image: url(<?php echo scriptPath; ?>/plugins/yms/icons/application_view_icons.png);">
    <?php echo $lang->get('yms_btn_show_converter'); ?>
  </a>
  
  <a href="<?php echo makeUrlNS('Special', 'YMS/ShowClientInfo'); ?>" onclick="yms_showpage('ShowClientInfo'); return false;" class="abutton abutton_blue icon"
     style="background-image: url(<?php echo scriptPath; ?>/plugins/yms/icons/show_client_info.png);">
    <?php echo $lang->get('yms_btn_show_client_info'); ?>
  </a>
  
  <?php if ( getConfig('yms_claim_enable', 0) == 1 ): ?>
  <a href="<?php echo makeUrlNS('Special', 'YMS', 'client_switch', true); ?>" class="abutton abutton_green">
    <?php echo $yms_client_id == 0 ? $lang->get('yms_btn_switch_from_zero') : $lang->get('yms_btn_switch_to_zero'); ?>
  </a>
  <?php endif; ?>
  <?php
  
  $db->free_result($q);
  
  // close off inner div (yms-keylist)
  if ( !isset($_GET['ajax'] ) )
    echo '</div>';
  
  // Send footer
  $output->footer();
}

// Add key, using AES secret
function page_Special_YMS_AddKey()
{
  global $output;
  global $lang;
  
  $output->add_after_header('<div class="breadcrumbs">
      <a href="' . makeUrlNS('Special', 'YMS') . '">' . $lang->get('yms_specialpage_yms') . '</a> &raquo;
      ' . $lang->get('yms_btn_add_key') . '
    </div>');
  
  $output->header();
  ?>
  <h3><?php echo $lang->get('yms_lbl_addkey_heading'); ?></h3>
  <p><?php echo $lang->get('yms_lbl_addkey_desc'); ?></p>
  <form action="<?php echo makeUrlNS('Special', 'YMS'); ?>" method="post">
  
    <div class="tblholder">
    <table border="0" cellspacing="1" cellspacing="4">
    
      <!-- AES secret -->
      <tr>
        <td class="row2">
          <?php echo $lang->get('yms_lbl_addkey_field_secret'); ?><br />
          <small><?php echo $lang->get('yms_lbl_addkey_field_secret_hint'); ?></small>
        </td>
        <td class="row1">
          <input type="text" name="add_aes" value="" size="40" />
        </td>
      </tr>
      
      <!-- OTP -->
      <tr>
        <td class="row2">
          <?php echo $lang->get('yms_lbl_addkey_field_otp'); ?>
        </td>
        <td class="row1">
          <?php echo generate_yubikey_field('add_otp'); ?>
        </td>
      </tr>
      
      <!-- State -->
      <tr>
        <td class="row2">
          <?php echo $lang->get('yms_lbl_addkey_field_state'); ?>
        </td>
        <td class="row1">
          <select name="state">
            <option value="active" selected="selected"><?php echo $lang->get('yms_state_active'); ?></option>
            <option value="inactive"><?php echo $lang->get('yms_state_inactive'); ?></option>
          </select>
        </td>
      </tr>
      
      <!-- Any client -->
      <tr>
        <td class="row2">
          <?php echo $lang->get('yms_lbl_addkey_field_any_client_name'); ?><br />
          <small><?php echo $lang->get('yms_lbl_addkey_field_any_client_hint'); ?></small>
        </td>
        <td class="row1">
          <label>
            <input type="checkbox" name="any_client" />
            <?php echo $lang->get('yms_lbl_addkey_field_any_client'); ?>
          </label>
        </td>
      </tr>
      
      <!-- Allow claim -->
      <?php if ( getConfig('yms_claim_enable', 0) == 1 ): ?>
      <tr>
        <td class="row2">
          <?php echo $lang->get('yms_lbl_addkey_field_allow_claim_name'); ?><br />
          <small><?php echo $lang->get('yms_lbl_addkey_field_allow_claim_hint'); ?></small>
        </td>
        <td class="row1">
          <label>
            <input type="checkbox" name="allow_claim" />
            <?php echo $lang->get('yms_lbl_addkey_field_allow_claim'); ?>
          </label>
        </td>
      </tr>
      <?php endif; ?>
      
      <!-- Notes -->
      <tr>
        <td class="row2">
          <?php echo $lang->get('yms_lbl_addkey_field_notes'); ?>
        </td>
        <td class="row1">
          <textarea style="font-family: sans-serif; font-size: 9pt;" name="notes" rows="5" cols="40"></textarea>
        </td>
      </tr>
      
      <!-- Submit -->
      <tr>
        <th class="subhead" colspan="2">
          <input type="submit" value="<?php echo $lang->get('yms_btn_addkey_submit'); ?>" />
        </th>
      </tr>
      
    </table>
    </div>
  
  </form>
  <?php
  $output->footer();
}

// Add key, using just an OTP
// Requires the key to be in the database as client ID 0
function page_Special_YMS_AddPreregisteredKey()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  global $lang, $output;
  
  if ( getConfig('yms_claim_enable', 0) != 1 )
    die();
  
  $output->add_after_header('<div class="breadcrumbs">
      <a href="' . makeUrlNS('Special', 'YMS') . '">' . $lang->get('yms_specialpage_yms') . '</a> &raquo;
      ' . $lang->get('yms_btn_add_key_preregistered') . '
    </div>');
  
  $output->header();
  ?>
  <h3><?php echo $lang->get('yms_lbl_claimkey_heading'); ?></h3>
  <p><?php echo $lang->get('yms_lbl_claimkey_desc'); ?></p>
  <form action="<?php echo makeUrlNS('Special', 'YMS'); ?>" method="post">
  
    <div class="tblholder">
    <table border="0" cellspacing="1" cellspacing="4">
    
      <!-- OTP -->
      <tr>
        <td class="row2">
          <?php echo $lang->get('yms_lbl_addkey_field_otp'); ?>
        </td>
        <td class="row1">
          <?php echo generate_yubikey_field('claim_otp'); ?>
        </td>
      </tr>
      
      <!-- State -->
      <tr>
        <td class="row2">
          <?php echo $lang->get('yms_lbl_addkey_field_state'); ?>
        </td>
        <td class="row1">
          <select name="state">
            <option value="active" selected="selected"><?php echo $lang->get('yms_state_active'); ?></option>
            <option value="inactive"><?php echo $lang->get('yms_state_inactive'); ?></option>
          </select>
        </td>
      </tr>
      
      <!-- Any client -->
      <tr>
        <td class="row2">
          <?php echo $lang->get('yms_lbl_addkey_field_any_client_name'); ?><br />
          <small><?php echo $lang->get('yms_lbl_addkey_field_any_client_hint'); ?></small>
        </td>
        <td class="row1">
          <label>
            <input type="checkbox" name="any_client" />
            <?php echo $lang->get('yms_lbl_addkey_field_any_client'); ?>
          </label>
        </td>
      </tr>
      
      <!-- Notes -->
      <tr>
        <td class="row2">
          <?php echo $lang->get('yms_lbl_addkey_field_notes'); ?>
        </td>
        <td class="row1">
          <textarea style="font-family: sans-serif; font-size: 9pt;" name="notes" rows="5" cols="40"></textarea>
        </td>
      </tr>
      
      <?php if ( ($field = getConfig('yms_claim_auth_field', '')) && getConfig('yms_claim_auth_url') ): ?>
      <!-- Custom field -->
      <tr>
        <td class="row2">
          <?php echo htmlspecialchars($field); ?>
        </td>
        <td class="row1">
          <input type="text" name="custom_field" value="" size="30" />
        </td>
      </tr>
      <?php endif; ?>
      
      <!-- Submit -->
      <tr>
        <th class="subhead" colspan="2">
          <input type="submit" value="<?php echo $lang->get('yms_btn_addkey_submit'); ?>" />
        </th>
      </tr>
      
    </table>
    </div>
  
  </form>
  <?php
  $output->footer();
}

// Show the AES secret for a key
function page_Special_YMS_ShowAESKey()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  global $lang, $output, $yms_client_id;
  
  $output->add_after_header('<div class="breadcrumbs">
      <a href="' . makeUrlNS('Special', 'YMS') . '">' . $lang->get('yms_specialpage_yms') . '</a> &raquo;
      ' . $lang->get('yms_btn_show_aes') . '
    </div>');
  
  $id = intval($paths->getParam(1));
  
  // verify ownership, retrieve key
  $q = $db->sql_query('SELECT client_id, public_id, aes_secret, session_count, token_count, flags FROM ' . table_prefix . "yms_yubikeys WHERE id = $id;");
  if ( !$q )
    $db->_die();
  
  if ( $db->numrows() < 1 )
  {
    die_friendly('no rows', '<p>key not found</p>');
  }
  
  list($client_id, $public_id, $secret, $scount, $tcount, $flags) = $db->fetchrow_num();
  $db->free_result();
  
  if ( $client_id !== $yms_client_id )
    die_friendly($lang->get('etc_access_denied_short'), '<p>' . $lang->get('etc_access_denied') . '</p>');
  
  $output->header();
  ?>
  
  <h3><?php echo $lang->get('yms_showaes_heading_main'); ?></h3>
  
  <form action="<?php echo makeUrlNS('Special', 'YMS'); ?>" method="post">
  <input type="hidden" name="update_counters" value="<?php echo $id; ?>" />
  
  <div class="tblholder">
  <table border="0" cellspacing="1" cellpadding="4">
    <tr>
      <th colspan="2">
      <?php echo $lang->get('yms_showaes_th', array('public_id' => yms_modhex_encode($public_id))); ?>
      </th>
    </tr>
    
    <!-- hex -->
    <tr>
      <td class="row2" style="width: 50%;">
        <?php echo $lang->get('yms_showaes_lbl_hex'); ?>
      </td>
      <td class="row1">
        <?php echo $secret; ?>
      </td>
    </tr>
    
    <!-- modhex -->
    <tr>
      <td class="row2">
        <?php echo $lang->get('yms_showaes_lbl_modhex'); ?>
      </td>
      <td class="row1">
        <?php echo yms_modhex_encode($secret); ?>
      </td>
    </tr>
    
    <!-- base64 -->
    <tr>
      <td class="row2">
        <?php echo $lang->get('yms_showaes_lbl_base64'); ?>
      </td>
      <td class="row1">
        <?php echo base64_encode(yms_tobinary($secret)); ?>
      </td>
    </tr>
    
    <!-- COUNTERS -->
    <tr>
      <th colspan="2">
      <?php echo $lang->get('yms_showaes_th_counter'); ?>
      </th>
    </tr>
    
    <tr>
      <td class="row2">
        <?php echo $lang->get('yms_showaes_field_session_count'); ?><br />
        <small><?php echo $lang->get('yms_showaes_field_session_count_hint'); ?></small>
      </td>
      <td class="row1">
        <input type="text" name="session_count" value="<?php echo $scount; ?>" size="5" />
      </td>
    </tr>
    
    <tr>
      <td class="row2">
        <?php echo $lang->get('yms_showaes_field_otp_count'); ?><br />
        <small><?php echo $lang->get('yms_showaes_field_otp_count_hint'); ?></small>
      </td>
      <td class="row1">
        <input type="text" name="token_count" value="<?php echo $tcount; ?>" size="5" />
      </td>
    </tr>
    
    <!-- Any client -->
    <tr>
      <td class="row2">
        <?php echo $lang->get('yms_lbl_addkey_field_any_client_name'); ?><br />
        <small><?php echo $lang->get('yms_lbl_addkey_field_any_client_hint'); ?></small>
      </td>
      <td class="row1">
        <label>
          <input type="checkbox" name="any_client" <?php if ( $flags & YMS_ANY_CLIENT ) echo 'checked="checked" '; ?>/>
          <?php echo $lang->get('yms_lbl_addkey_field_any_client'); ?>
        </label>
      </td>
    </tr>
    
    <tr>
      <th class="subhead" colspan="2">
        <input type="submit" value="<?php echo $lang->get('etc_save_changes'); ?>" />
      </td>
    </tr>
    
  </table>
  </div>
  
  </form>
  <?php
  $output->footer();
}

// show the user's API key and client ID
function page_Special_YMS_ShowClientInfo()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  global $lang, $output, $yms_client_id;
  
  $output->add_after_header('<div class="breadcrumbs">
      <a href="' . makeUrlNS('Special', 'YMS') . '">' . $lang->get('yms_specialpage_yms') . '</a> &raquo;
      ' . $lang->get('yms_btn_show_client_info') . '
    </div>');
  
  $q = $db->sql_query('SELECT apikey FROM ' . table_prefix . "yms_clients WHERE id = {$yms_client_id};");
  if ( !$q )
    $db->_die();
  
  list($api_key) = $db->fetchrow_num();
  $db->free_result();
  
  $api_key = yms_tobinary($api_key);
  
  $validate_url = makeUrlComplete('Special', 'YubikeyValidate');
  $validate_url = preg_replace('/[?&]auth=[0-9a-f]+/', '', $validate_url);
  
  $output->header();
  ?>
  <div class="tblholder">
  <table border="0" cellspacing="1" cellpadding="4">
  
    <tr>
      <th colspan="2"><?php echo $lang->get('yms_th_client_id'); ?></th>
    </tr>
    
    <tr>
      <td class="row2"><?php echo $lang->get('yms_lbl_client_id'); ?></td>
      <td class="row1"><?php echo strval($yms_client_id); ?></td>
    </tr>
    
    <tr>
      <td class="row2"><?php echo $lang->get('yms_lbl_validate_url'); ?></td>
      <td class="row1"><?php echo htmlspecialchars($validate_url); ?></td>
    </tr>
    
    <tr>
      <th colspan="2"><?php echo $lang->get('yms_th_api_key'); ?></th>
    </tr>
    
    <tr>
      <td class="row2"><?php echo $lang->get('yms_showaes_lbl_hex'); ?></td>
      <td class="row1"><?php echo yms_hex_encode($api_key); ?></td>
    </tr>
    
    <tr>
      <td class="row2"><?php echo $lang->get('yms_showaes_lbl_modhex'); ?></td>
      <td class="row1"><?php echo yms_modhex_encode($api_key); ?></td>
    </tr>
    
    <tr>
      <td class="row2"><?php echo $lang->get('yms_showaes_lbl_base64'); ?></td>
      <td class="row1"><?php echo base64_encode($api_key); ?></td>
    </tr>
  
  </table>
  </div>
  <?php
  $output->footer();
}

// Converter between different binary encodings
function page_Special_YMS_Converter()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  global $lang, $output;
  
  $output->add_after_header('<div class="breadcrumbs">
      <a href="' . makeUrlNS('Special', 'YMS') . '">' . $lang->get('yms_specialpage_yms') . '</a> &raquo;
      ' . $lang->get('yms_btn_show_converter') . '
    </div>');
  
  $output->header();
  
  if ( isset($_POST['value']) )
  {
    switch($_POST['format'])
    {
      case 'auto':
      default:
        $binary = yms_tobinary($_POST['value']);
        break;
      case 'hex':
        $_POST['value'] = str_replace(" ", '', $_POST['value']);
        $binary = yms_hex_decode($_POST['value']);
        break;
      case 'modhex':
        $binary = yms_hex_decode(yms_modhex_decode($_POST['value']));
        break;
      case 'base64':
        $binary = base64_decode($_POST['value']);
        break;
    }
    
    if ( empty($binary) )
    {
      echo '<div class="error-box">' . $lang->get('yms_conv_err_invalid_string') . '</div>';
    }
    else
    {
    ?>
    <div class="tblholder">
    <table border="0" cellspacing="1" cellpadding="4">
    
      <tr>
        <th colspan="2"><?php echo $lang->get('yms_th_converted_value'); ?></th>
      </tr>
      
      <tr>
        <td class="row2"><?php echo $lang->get('yms_showaes_lbl_hex'); ?></td>
        <td class="row1"><?php echo yms_hex_encode($binary); ?></td>
      </tr>
      
      <tr>
        <td class="row2"><?php echo $lang->get('yms_showaes_lbl_modhex'); ?></td>
        <td class="row1"><?php echo yms_modhex_encode($binary); ?></td>
      </tr>
      
      <tr>
        <td class="row2"><?php echo $lang->get('yms_showaes_lbl_base64'); ?></td>
        <td class="row1"><?php echo base64_encode($binary); ?></td>
      </tr>
    
    </table>
    </div>
    <?php
    }
  }
  
  ?>
  <form method="post" class="submit_to_self" action="<?php echo makeUrl($paths->fullpage); ?>">
  
  <div class="tblholder">
  <table border="0" cellspacing="1" cellpadding="4">
  
    <tr>
      <th colspan="2"><?php echo $lang->get('yms_th_converter'); ?></th>
    </tr>
    
    <tr>
      <td class="row2" style="width: 30%;"><?php echo $lang->get('yms_conv_lbl_value'); ?></td>
      <td class="row1"><input type="text" name="value" size="60" /></td>
    </tr>
    
    <tr>
      <td class="row2" style="width: 30%;"><?php echo $lang->get('yms_conv_lbl_format'); ?></td>
      <td class="row1">
        <?php
        foreach ( array('auto', 'hex', 'modhex', 'base64') as $i => $fmt )
        {
          echo '<label><input type="radio" name="format" value="' . $fmt . '" ';
          if ( ( isset($_POST['format']) && $_POST['format'] === $fmt ) || ( !isset($_POST['format']) && $i == 0 ) )
            echo 'checked="checked" ';
          
          echo '/> ';
          echo $lang->get("yms_conv_lbl_format_$fmt"); 
          echo "</label>\n        ";
        }
        ?>
      </td>
    </tr>
    
    <tr>
      <th class="subhead" colspan="2">
        <input type="submit" value="<?php echo $lang->get('yms_conv_btn_submit'); ?>" />
      </th>
    </tr>
  
  </table>
  </div>
  
  </form>
  <?php
  
  $output->footer();
}

function page_Special_YMS_DeleteKey()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  global $lang, $output;
  
  $output->add_after_header('<div class="breadcrumbs">
      <a href="' . makeUrlNS('Special', 'YMS') . '">' . $lang->get('yms_specialpage_yms') . '</a> &raquo;
      ' . $lang->get('yms_btn_delete_key') . '
    </div>');
  
  $id = intval($paths->getParam(1));
  if ( !$id )
    die();
  
  if ( $paths->getParam(2) == 'Confirm' )
  {
    // go back, Jack!
    return true;
  }
  
  $delete_url = makeUrlNS('Special', "YMS/DeleteKey/$id/Confirm", "cstok={$session->csrf_token}", true);
  
  $output->header();
  
  ?>
  <form action="<?php echo $delete_url; ?>" method="post">
  <div style="text-align: center;">
    <h3><?php echo $lang->get('yms_msg_delete_confirm'); ?></h3>
    <input type="hidden" name="placeholder" value="placeholder" />
    <p>
      <a href="<?php echo $delete_url; ?>" onclick="return yms_ajax_submit(this);" class="abutton abutton_red icon" style="background-image: url(<?php echo scriptPath; ?>/plugins/yms/icons/key_delete.png);">
        <?php echo $lang->get('yms_btn_delete_key'); ?>
      </a>
    </p>
  </div>
  </form>
  <?php
  
  $output->footer();
}

function page_Special_YMS_AjaxToggleState()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  global $yms_client_id;
  
  $id = intval($_POST['id']);
  if ( $_POST['state'] === 'active' )
    $expr = 'flags | ' . YMS_ENABLED;
  else
    $expr = 'flags & ~' . YMS_ENABLED;
    
  $q = $db->sql_query('UPDATE ' . table_prefix . "yms_yubikeys SET flags = $expr WHERE id = $id AND client_id = {$yms_client_id};");
  if ( !$q )
    $db->die_json();
  
  if ( $db->sql_affectedrows() < 1 )
    echo 'no affected rows; not ';
  
  echo 'ok';
}

function page_Special_YMS_AjaxNotes()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  global $yms_client_id;
  
  if ( isset($_POST['get']) )
  {
    $id = intval($_POST['get']);
    $q = $db->sql_query('SELECT notes FROM ' . table_prefix . "yms_yubikeys WHERE id = $id AND client_id = {$yms_client_id};");
    if ( !$q )
      $db->_die();
    if ( $db->numrows() < 1 )
    {
      echo "key not found";
    }
    else
    {
      list($note) = $db->fetchrow_num();
      echo $note;
    }
    $db->free_result();
  }
  else if ( isset($_POST['save']) )
  {
    $id = intval($_POST['save']);
    $note = trim($_POST['note']);
    $note = $db->escape($note);
    $q = $db->sql_query('UPDATE ' . table_prefix . "yms_yubikeys SET notes = '$note' WHERE id = $id AND client_id = {$yms_client_id};");
    if ( !$q )
      $db->die_json();
    
    echo 'ok';
  }
}

// Client creation
function page_Special_YMSCreateClient()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  global $lang;
  global $output;
  global $yms_client_id;
  
  $yms_client_id = $session->user_id;
  
  // Require re-auth?
  if ( $session->auth_level < USER_LEVEL_CHPREF && getConfig('yms_require_reauth', 1) == 1 )
  {
    redirect(makeUrlNS('Special', "Login/$paths->fullpage", 'level=' . USER_LEVEL_CHPREF), '', '', 0);
  }
  
  // Check for Yubikey plugin
  if ( !function_exists('yubikey_validate_otp') )
  {
    die_friendly($lang->get('yms_err_yubikey_plugin_missing_title'), '<p>' . $lang->get('yms_err_yubikey_plugin_missing_body') . '</p>');
  }
  
  // Does the client exist?
  $q = $db->sql_query('SELECT 1 FROM ' . table_prefix . "yms_clients WHERE id = {$yms_client_id};");
  if ( !$q )
    $db->_die();
  
  $client_exists = $db->numrows();
  $db->free_result();
  
  if ( $client_exists )
  {
    die_friendly($lang->get('yms_err_client_exists_title'), '<p>' . $lang->get('yms_err_client_exists_body') . '</p>');
  }
  
  $template->add_header('<link rel="stylesheet" type="text/css" href="' . scriptPath . '/plugins/yms/styles.css" />');
  $output->header();
  
  if ( isset($_POST['register_client']) )
  {
    // register the client
    // SHA1 key length: 160 bits
    $api_key = base64_encode(AESCrypt::randkey(160 / 8));
    $client_id = $yms_client_id;
    
    $q = $db->sql_query('INSERT INTO ' . table_prefix . "yms_clients(id, apikey) VALUES ($client_id, '$api_key');");
    if ( !$q )
      $db->_die();
    
    $validate_url = makeUrlComplete('Special', 'YubikeyValidate');
    $validate_url = preg_replace('/[?&]auth=[0-9a-f]+/', '', $validate_url);
    
    ?>
    <h3><?php echo $lang->get('yms_register_msg_success_title'); ?></h3>
    <?php echo $lang->get('yms_register_msg_success_body', array(
        'yms_link' => makeUrlNS('Special', 'YMS'),
        'client_id' => $client_id,
        'api_key' => $api_key,
        'validate_url' => $validate_url
      ));
  }
  else
  {
    // confirmation page
    ?>
    <form action="<?php echo makeUrlNS('Special', 'YMSCreateClient'); ?>" method="post">
      <h3><?php echo $lang->get('yms_register_confirm_title'); ?></h3>
      <p><?php echo $lang->get('yms_register_confirm_body'); ?></p>
      <p>
        <input type="submit" style="font-weight: bold;" name="register_client" value="<?php echo $lang->get('yms_register_btn_submit'); ?>" />
        <input type="submit" name="cancel" value="<?php echo $lang->get('etc_cancel'); ?>" />
      </p>
    </form>
    <?php
  }
  
  $output->footer();
}

// Generic response function
// Processing functions return either true or a string containing an error message. This
// takes that return, and sends a response through the appropriate channel, while allowing
// shared backend functions.

function yms_send_response($success_string, $result)
{
  global $lang, $output;
  
  if ( $result === true )
  {
    if ( isset($_GET['ajax']) )
    {
      yms_json_response(array(
        'mode' => 'success',
        'message' => $lang->get($success_string)
      ));
    }
    else
    {
      $output->add_after_header(
          '<div class="info-box">' . $lang->get($success_string) . '</div>'
        );
    }
  }
  else
  {
    if ( isset($_GET['ajax']) )
    {
      yms_json_response(array(
        'mode' => 'error',
        'error' => $lang->get($result)
      ));
    }
    else
    {
      $output->add_after_header(
          '<div class="error-box">' . $lang->get($result) . '</div>'
        );
    }
  }
}

function yms_json_response($response)
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  
  header('Content-type: application/json');
  echo enano_json_encode($response);
  
  $db->close();
  exit;
}

function yms_date($ts)
{
  return enano_date('Y-m-d H:m:i', $ts);
}

function yms_state_indicator($flags, $id)
{
  global $lang;
  return $flags & YMS_ENABLED ?
    '<a href="' . makeUrlNS('Special', 'YMS', "toggle=$id&state=inactive", true) . '" onclick="yms_toggle_state(this, ' . $id . '); return false;" class="yms-enabled">' . $lang->get('yms_state_active') . '</a>' :
    '<a href="' . makeUrlNS('Special', 'YMS', "toggle=$id&state=active",   true) . '" onclick="yms_toggle_state(this, ' . $id . '); return false;" class="yms-disabled">' . $lang->get('yms_state_inactive') . '</a>';
}

function yms_notes_cell($notes, $id)
{
  global $lang;
  $notes = trim($notes);
  if ( empty($notes) )
  {
    $img = 'note_delete.png';
    $str = $lang->get('yms_btn_note_create');
  }
  else
  {
    $img = 'note.png';
    $str = $lang->get('yms_btn_note_view');
  }
  echo '<a href="#" onclick="yms_show_notes(this, '.$id.'); return false;" title="' . $str . '"><img alt="' . $str . '" src="' . scriptPath . '/plugins/yms/icons/' . $img . '" /></a>';
  
  if ( !empty($notes) )
  {
    echo ' ';
    if ( strlen($notes) > 15 )
      echo htmlspecialchars(substr($notes, 0, 12)) . '...';
    else
      echo htmlspecialchars($notes);
  }
}

function yms_show_actions($row)
{
  global $lang;
  
  // Show AES secret
  ?>
    <a href="<?php echo makeUrlNS('Special', "YMS/ShowAESKey/{$row['id']}"); ?>" title="<?php echo $lang->get('yms_btn_show_aes'); ?>" onclick="yms_showpage('ShowAESKey/<?php echo $row['id']; ?>'); return false;">
      <img alt="<?php echo $lang->get('yms_btn_show_aes'); ?>" src="<?php echo scriptPath; ?>/plugins/yms/icons/key_go.png" />
    </a>
    <a href="<?php echo makeUrlNS('Special', "YMS/DeleteKey/{$row['id']}"); ?>" title="<?php echo $lang->get('yms_btn_delete_key'); ?>" onclick="yms_showpage('DeleteKey/<?php echo $row['id']; ?>'); return false;">
      <img alt="<?php echo $lang->get('yms_btn_delete_key'); ?>" src="<?php echo scriptPath; ?>/plugins/yms/icons/key_delete.png" />
    </a>
  <?php
}
