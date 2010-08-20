<?php

$plugins->attachHook('session_started', "yms_add_admincp();");
 
function yms_add_admincp()
{
  global $paths;
 
  $paths->addAdminNode('adm_cat_appearance', 'yms_acp_title', 'YMS', scriptPath . '/plugins/yms/icons/admincp.png');
}
 
function page_Admin_YMS()
{
  // Security check
  global $session;
  if ( $session->auth_level < USER_LEVEL_ADMIN )
    return false;
  
  global $lang;
  
  if ( isset($_POST['submit']) )
  {
    setConfig('yms_require_reauth', isset($_POST['require_reauth']) ? '1' : '0');
    setConfig('yms_claim_enable', isset($_POST['claim_enable']) ? '1' : '0');
    setConfig('yms_claim_auth_enable', isset($_POST['claimauth_enable']) ? '1' : '0');
    setConfig('yms_claim_auth_field', $_POST['claimauth_field']);
    setConfig('yms_claim_auth_url', $_POST['claimauth_url']);
    setConfig('yms_claim_auth_key', $_POST['claimauth_key']);
    
    echo '<div class="info-box">' . $lang->get('yms_acp_msg_saved') . '</div>';
  }
 
  acp_start_form();
  ?>
  <h3><?php echo $lang->get('yms_acp_heading_main'); ?></h3>
  
  <div class="tblholder">
  <table border="0" cellspacing="1" cellpadding="4">
  
    <tr>
      <th colspan="2"><?php echo $lang->get('yms_acp_th_main'); ?></th>
    </tr>
    
    <tr>
      <td class="row2" style="width: 50%;">
        <?php echo $lang->get('yms_acp_field_require_reauth_title'); ?><br />
        <small><?php echo $lang->get('yms_acp_field_require_reauth_hint'); ?></small>
      </td>
      <td class="row1" style="width: 50%;">
        <label>
          <input type="checkbox" name="require_reauth" <?php if ( getConfig('yms_require_reauth', 1) == 1 ) echo 'checked="checked" '; ?>/>
          <?php echo $lang->get('yms_acp_field_require_reauth'); ?>
        </label>
      </td>
    </tr>
    
    <tr>
      <td class="row2" style="width: 50%;">
        <?php echo $lang->get('yms_acp_field_claim_enable_title'); ?><br />
        <small><?php echo $lang->get('yms_acp_field_claim_enable_hint'); ?></small>
      </td>
      <td class="row1" style="width: 50%;">
        <label>
          <input type="checkbox" name="claim_enable" <?php if ( getConfig('yms_claim_enable', 0) == 1 ) echo 'checked="checked" '; ?>/>
          <?php echo $lang->get('yms_acp_field_claim_enable'); ?>
        </label>
      </td>
    </tr>
    
    <tr>
      <td class="row2" style="width: 50%;">
        <?php echo $lang->get('yms_acp_field_claimauth_enable_title'); ?><br />
        <small><?php echo $lang->get('yms_acp_field_claimauth_enable_hint'); ?></small>
      </td>
      <td class="row1" style="width: 50%;">
        <label>
          <input type="checkbox" name="claimauth_enable" <?php if ( getConfig('yms_claim_auth_enable', 1) == 1 ) echo 'checked="checked" '; ?>/>
          <?php echo $lang->get('yms_acp_field_claimauth_enable'); ?>
        </label>
      </td>
    </tr>
    
    <tr>
      <td class="row2" style="width: 50%;">
        <?php echo $lang->get('yms_acp_field_claimauth_title'); ?><br />
        <small><?php echo $lang->get('yms_acp_field_claimauth_title_hint'); ?></small>
      </td>
      <td class="row1" style="width: 50%;">
        <input type="text" name="claimauth_field" value="<?php echo htmlspecialchars(getConfig('yms_claim_auth_field', '')); ?>" size="40" />
      </td>
    </tr>
    
    <tr>
      <td class="row2" style="width: 50%;">
        <?php echo $lang->get('yms_acp_field_claimauth_url_title'); ?><br />
        <small><?php echo $lang->get('yms_acp_field_claimauth_url_hint'); ?></small>
      </td>
      <td class="row1" style="width: 50%;">
        <input type="text" name="claimauth_url" value="<?php echo htmlspecialchars(getConfig('yms_claim_auth_url', '')); ?>" size="40" />
      </td>
    </tr>
    
    <tr>
      <td class="row2" style="width: 50%;">
        <?php echo $lang->get('yms_acp_field_claimauth_key_title'); ?><br />
        <small><?php echo $lang->get('yms_acp_field_claimauth_key_hint'); ?></small>
      </td>
      <td class="row1" style="width: 50%;">
        <input type="text" name="claimauth_key" value="<?php echo htmlspecialchars(getConfig('yms_claim_auth_key', '')); ?>" size="40" />
      </td>
    </tr>
    
    <tr>
      <th colspan="2" class="subhead">
        <input name="submit" type="submit" value="<?php echo $lang->get('etc_save_changes'); ?>" />
      </th>
    </tr>
  
  </table>
  </div>
  
  </form>
  <?php
}

