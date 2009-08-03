<?php
/**!info**
{
  "Plugin Name"  : "Yubikey management service",
  "Plugin URI"   : "http://enanocms.org/plugin/yubikey-yms",
  "Description"  : "Adds the ability for Enano to act as a Yubikey authentication provider. The Yubikey authentication plugin is a prerequisite.",
  "Author"       : "Dan Fuhry",
  "Version"      : "0.1",
  "Author URI"   : "http://enanocms.org/"
}
**!*/

$plugins->attachHook('session_started', 'yms_add_special_pages();');

function yms_add_special_pages()
{
  global $lang;
  
  register_special_page('YMS', 'yms_specialpage_yms');
  register_special_page('YMSCreateClient', 'yms_specialpage_register');
  register_special_page('YubikeyValidate', 'yms_specialpage_validate');
}

define('YMS_DISABLED', 0);
define('YMS_ENABLED', 1);
define('YMS_ANY_CLIENT', 2);

define('YMS_INSTALLED', 1);

require(ENANO_ROOT . '/plugins/yms/yms.php');
require(ENANO_ROOT . '/plugins/yms/libotp.php');
require(ENANO_ROOT . '/plugins/yms/transcode.php');
require(ENANO_ROOT . '/plugins/yms/backend.php');
require(ENANO_ROOT . '/plugins/yms/validate.php');
require(ENANO_ROOT . '/plugins/yms/validate-functions.php');
require(ENANO_ROOT . '/plugins/yms/admincp.php');

/**!language**

The following text up to the closing comment tag is JSON language data.
It is not PHP code but your editor or IDE may highlight it as such. This
data is imported when the plugin is loaded for the first time; it provides
the strings displayed by this plugin's interface.

You should copy and paste this block when you create your own plugins so
that these comments and the basic structure of the language data is
preserved. All language data is in the same format as the Enano core
language files in the /language/* directories. See the Enano Localization
Guide and Enano API Documentation for further information on the format of
language files.

The exception in plugin language file format is that multiple languages
may be specified in the language block. This should be done by way of making
the top-level elements each a JSON language object, with elements named
according to the ISO-639-1 language they are representing. The path should be:

  root => language ID => categories array, ( strings object => category \
  objects => strings )

All text leading up to first curly brace is stripped by the parser; using
a code tag makes jEdit and other editors do automatic indentation and
syntax highlighting on the language data. The use of the code tag is not
necessary; it is only included as a tool for development.

<code>
{
  // english
  eng: {
    categories: [ 'meta', 'yms' ],
    strings: {
      meta: {
        yms: 'Yubikey management system'
      },
      yms: {
        specialpage_yms: 'Yubikey manager',
        specialpage_register: 'Register YMS client',
        specialpage_validate: 'Yubikey validation API',
        err_yubikey_plugin_missing_title: 'Yubikey plugin not found',
        err_yubikey_plugin_missing_body: 'The Yubikey YMS cannot load because the Enano <a href="http://enanocms.org/plugin/yubikey">Yubikey authentication plugin</a> is not installed. Please ask your administrator to install it.',
        err_client_exists_title: 'Client already exists',
        err_client_exists_body: 'You cannot register another YMS client using this same user account.',
        register_confirm_title: 'Enable your account for Yubikey authentication',
        register_confirm_body: 'As a Yubikey authentication client, you gain the ability to manage multiple Yubikeys and tie them to your own organization. It also lets you retrieve secret AES keys for tokens, register new or reprogrammed keys, validate Yubikey OTPs using your own API key, and deactivate keys in case of a compromise. Do you want to enable your account for Yubikey management?',
        register_btn_submit: 'Create YMS client',
        
        register_msg_success_title: 'Congratulations! Your account is now enabled for YMS access.',
        register_msg_success_body: '<p>You can now go to the <a href="%yms_link|htmlsafe%">YMS admin panel</a> and add your Yubikeys. Your client ID and API key are below:</p>
                                      <p class="yms-copypara">Client ID: <span class="yms-copyfield">%client_id%</span><br />
                                         API key: <span class="yms-copyfield">%api_key%</span><br />
                                         Validation API URL: <span class="yms-copyfield">%validate_url%</span></p>
                                    <p><b>Remember to secure your user account!</b> Your Enano login is used to administer your YMS account. For maximum security, use the Yubikey Settings page of the User Control Panel to require both a password and a Yubikey OTP to log in.</p>',
        msg_no_yubikeys: 'No Yubikeys found',
        msg_editing_zero: '<b>Notice:</b> You are currently viewing the YMS profile for Client ID 0, the pool of claimable keys. By default, anybody can validate or claim these Yubikeys, but you can prevent validation of these keys by marking them inactive here. All key settings such as lifecycle state and notes are reset when a user claims a key here.',
        btn_add_key: 'Add Yubikey',
        btn_add_key_preregistered: 'Claim a New Key',
        btn_switch_to_zero: 'Edit claimable pool',
        btn_switch_from_zero: 'Switch back to my client',
        state_active: 'Active',
        state_inactive: 'Inactive',
        
        th_id: 'ID#',
        th_publicid: 'OTP prefix',
        th_createtime: 'Created',
        th_accesstime: 'Last accessed',
        th_state: 'Lifecycle state',
        th_note: 'Note',
        
        msg_access_never: 'Never',
        
        // Add key interface
        lbl_addkey_heading: 'Register Yubikey',
        lbl_addkey_desc: 'Register a Yubikey that you programmed yourself in YMS to enable validation of OTPs from that key against this server.',
        lbl_addkey_field_secret: 'AES secret key:',
        lbl_addkey_field_secret_hint: 'Input in ModHex, hex, or base-64. The format will be detected automatically.',
        lbl_addkey_field_otp: 'Enter an OTP from this Yubikey:',
        lbl_addkey_field_notes: 'Notes about this key:',
        lbl_addkey_field_state: 'Lifecycle state:',
        lbl_addkey_field_any_client_name: 'Allow validation by any client:',
        lbl_addkey_field_any_client_hint: 'If unchecked, OTPs from this Yubikey can only be verified by someone using your client ID. Check this if you plan to use this Yubikey on websites you don\'t control.',
        lbl_addkey_field_any_client: 'Other clients can validate OTPs from this key',
        lbl_addkey_field_allow_claim_name: 'Place key in claimable pool:',
        lbl_addkey_field_allow_claim_hint: 'After this key is added, YMS will release your ownership of this key so that other users may claim it.',
        lbl_addkey_field_allow_claim: 'Release this key and allow others to claim it',
        btn_addkey_submit: 'Register key',
        msg_addkey_success: 'This key has been successfully registered.',
        
        err_addkey_crc_failed: 'The CRC check on the OTP failed. This usually means that your AES key is wrong or could not be properly interpreted.',
        err_addkey_invalid_key: 'There was an error decoding your AES secret key. Please enter a 128-bit hex, ModHex, or base-64 value.',
        err_addkey_invalid_otp: 'The OTP from the Yubikey is invalid.',
        err_addkey_key_exists: 'This Yubikey is already registered on this server.',
        
        // Claim key interface
        lbl_claimkey_heading: 'Claim Yubikey',
        lbl_claimkey_desc: 'Attach a key you have not reprogrammed to your YMS account, so that you can see its AES secret key and keep track of it.',
        lbl_claimkey_field_otp: 'Enter an OTP from this Yubikey:',
        lbl_custom_hint: 'For your security, this is used to validate your ownership of this Yubikey.',
        
        // AES key view interface
        showaes_heading_main: 'View AES key and counters',
        showaes_th: 'AES secret key for key %public_id%',
        showaes_lbl_hex: 'Hex:',
        showaes_lbl_modhex: 'ModHex:',
        showaes_lbl_base64: 'Base64:',
        showaes_th_counter: 'Counters',
        showaes_field_session_count: 'Session count:',
        showaes_field_session_count_hint: 'Incremented by 1 each time you insert this Yubikey into a USB port.',
        showaes_field_otp_count: 'OTP count:',
        showaes_field_otp_count_hint: 'Incremented by 1 each time you press the button on the Yubikey; reset when the Yubikey is plugged in.',
        
        err_expected_int: 'Expected an integer',
        msg_counter_update_success: 'The counters for this Yubikey have been updated.',
        
        // API key view interface
        th_client_id: 'Client ID',
        lbl_client_id: 'Client ID:',
        th_api_key: 'API key',
        
        // Deletion interface
        msg_delete_confirm: 'Are you sure you want to delete this Yubikey?',
        err_delete_not_found: 'That Yubikey was not found, or it is not yours to delete.',
        msg_delete_success: 'The selected Yubikey has been deleted successfully.',
        
        // Binary format converter
        th_converted_value: 'Converted value',
        conv_err_invalid_string: 'The string was invalid or you entered did not match the format you selected.',
        th_converter: 'Convert binary formats',
        conv_lbl_value: 'Value to convert:',
        conv_lbl_format: 'Current encoding:',
        conv_lbl_format_auto: 'Auto-detect',
        conv_lbl_format_hex: 'Hexadecimal',
        conv_lbl_format_modhex: 'ModHex',
        conv_lbl_format_base64: 'Base-64',
        conv_btn_submit: 'Convert',
        
        // Key list
        btn_note_view: 'View or edit note',
        btn_note_create: 'No note; click to create',
        btn_delete_key: 'Delete key',
        btn_show_aes: 'AES secret and counter information',
        btn_show_converter: 'Binary encoding converter',
        btn_show_client_info: 'View client info',
        
        // ADMIN
        acp_title: 'Yubikey Management Server',
        acp_heading_main: 'YMS configuration',
        acp_th_main: 'Yubikey Management Server options',
        acp_field_require_reauth_title: 'Require re-authentication to access YMS interface:',
        acp_field_require_reauth_hint: 'This can be redundant and unnecessary if the sole purpose of your Enano installation is for YMS purposes.',
        acp_field_require_reauth: 'YMS pages require re-authentication',
        acp_field_claim_enable_title: 'Allow users to claim Yubikeys:',
        acp_field_claim_enable_hint: 'If you plan to program your own Yubikeys and give them to others, enable this to allow them to create YMS accounts and "claim" the keys so they can see AES secrets and control settings on their keys.<br />
                                      If you enable this, all Administrators will see an option when adding a new key to put it into the pool of unclaimed keys.<br />
                                      To claim a Yubikey, YMS requires users to enter a valid OTP, and optionally, an additional field you may configure below.',
        acp_field_claim_enable: 'Enable the claim system',
        acp_field_claimauth_enable_title: 'Use external authentication when claiming Yubikeys:',
        acp_field_claimauth_enable_hint: 'This allows you to require an additional value - for example, the receipt number from the user\'s Yubikey order - when Yubikeys are claimed.',
        acp_field_claimauth_enable: 'Require additional field to claim a Yubikey',
        acp_field_claimauth_url_title: 'URL to claim authentication server:',
        acp_field_claimauth_url_hint: 'The following variables will be applied:
                                       <ul>
                                         <li>%c = The value the user entered in your custom field</li>
                                         <li>%o = The Yubikey OTP from the form</li>
                                         <li>%h = The HMAC signature of the request</li>
                                       </ul>
                                       This authentication uses the same protocol as other Yubikey authentication servers. See the <a href="http://enanocms.org/plugin/yms" onclick="window.opeh(this.href); return false;">YMS plugin page on enanocms.org</a> for information on how to write an authentication server.<br />
                                       <b>Example URL:</b> <tt>http://10.4.27.3/wsapi/validateclaim?id=1&amp;tid=%c&amp;otp=%o&amp;h=%h</tt>',
        acp_field_claimauth_key_title: 'API key for authentication server:',
        acp_field_claimauth_key_hint: 'If provided, YMS will sign the requests it makes to your authentication server. Leave blank to disable signature support.',
        
        acp_msg_saved: 'Your changes to the YMS configuration have been saved.',
      }
    }
  }
}
</code>
**!*/

/**!install dbms="mysql"; **

CREATE TABLE {{TABLE_PREFIX}}yms_clients(
  id int(12) NOT NULL DEFAULT 0,
  apikey varchar(40) NOT NULL,
  PRIMARY KEY ( id )
);

CREATE TABLE {{TABLE_PREFIX}}yms_yubikeys(
  id int(12) NOT NULL auto_increment,
  client_id int(12) NOT NULL DEFAULT 0,
  public_id varchar(12) NOT NULL DEFAULT '000000000000',
  private_id varchar(12) NOT NULL DEFAULT '000000000000',
  session_count int(8) NOT NULL DEFAULT 0,
  token_count int(8) NOT NULL DEFAULT 0,
  create_time int(12) NOT NULL DEFAULT 0,
  access_time int(12) NOT NULL DEFAULT 0,
  token_time int(12) NOT NULL DEFAULT 0,
  aes_secret varchar(40) NOT NULL DEFAULT '00000000000000000000000000000000',
  flags int(8) NOT NULL DEFAULT 1,
  notes text,
  PRIMARY KEY (id)
);

**!*/

