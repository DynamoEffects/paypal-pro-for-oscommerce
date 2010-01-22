<?php
/*
  $Id: paypal_wpp.php,v 1.0.0 Brian Burton brian [at] dynamoeffects [dot] com Exp $

  Copyright (c) 2008 Brian Burton - brian [at] dynamoeffects [dot] com

  Released under the GNU General Public License
*/
  /* Filenames */
  define('FILENAME_PAYPAL_WPP', 'paypal_wpp.php');
  define('FILENAME_PAYPAL_WPP_3DS', 'paypal_wpp_3ds.php');
  define('FILENAME_CVV2INFO', 'cvv2info.php');
  define('FILENAME_EXPRESS_CHECKOUT_IMG', 'https://www.paypal.com/en_US/i/btn/btn_xpressCheckout.gif');
  
  /* Database Table */
  define('TABLE_ORDERS_STATUS_HISTORY_TRANSACTIONS', 'orders_status_history_transactions');
  
  require(DIR_WS_INCLUDES . 'paypal_wpp/languages/' . $language . '/' . FILENAME_PAYPAL_WPP);
  
  if (MODULE_PAYMENT_PAYPAL_DP_STATUS == 'True' && MODULE_PAYMENT_PAYPAL_EC_ENABLED == 'Yes' && $cart->count_contents() > 0) {
    $ec_enabled = true;
  } else {
    $ec_enabled = false;
  }
  
  function tep_paypal_wpp_ep_button($return_to = '') {
    global $ec_enabled;
    
    if (!$ec_enabled) return false;
    
    if ($return_to != FILENAME_SHOPPING_CART && $return_to != FILENAME_CHECKOUT_SHIPPING) {
      $return_to = FILENAME_CHECKOUT_SHIPPING;
    }
?>
      <tr>
        <td><?php echo tep_draw_separator('pixel_trans.gif', '100%', '10'); ?></td>
      </tr>
      <tr>
        <td class="main" width="100%" colspan="2" valign="top"><b><?php echo TEXT_PAYPALWPP_EC_HEADER; ?></b></td>
      </tr>
      <tr>
        <td width="100%" colspan=2 valign="top"><table border="0" width="100%" height="100%" cellspacing="1" cellpadding="2" class="infoBox">
          <tr class="infoBoxContents">
            <td><table border="0" width="100%" height="100%" cellspacing="0" cellpadding="2">
              <tr>
                <td><table border="0" width="100%" cellspacing="0" cellpadding="2">
                  <tr>
                    <td width="10"><?php echo tep_draw_separator('pixel_trans.gif', '10', '1'); ?></td>
                    <td align="center"><a href="<?php echo tep_href_link(basename($_SERVER['SCRIPT_NAME']), 'action=express_checkout&return_to=' . $return_to, 'SSL'); ?>"><img src="<?php echo FILENAME_EXPRESS_CHECKOUT_IMG; ?>" border=0></a></td>
                    <td align="left" valign="middle"><span style="font-size:11px; font-family: Arial, Verdana;"><?php echo TEXT_PAYPALWPP_EC_BUTTON_TEXT; ?></span></td>
                    <td width="10"><?php echo tep_draw_separator('pixel_trans.gif', '10', '1'); ?></td>
                  </tr>
                </table></td>
              </tr>
            </table></td>
          </tr>
        </table></td>
      </tr>
<?php
  }
  
  function tep_paypal_wpp_checkout_shipping_error_display($ec_checkout) {
    global $messageStack, $ec_enabled;
    
    if (!$ec_enabled) return false;
    
    if (tep_session_is_registered('paypal_error')) {
      $messageStack->add('shipping', $_SESSION['paypal_error']);
      tep_session_unregister('paypal_error');
    }
    if ($messageStack->size('shipping') > 0) {
?>

      <tr>
        <td><?php echo $messageStack->output('shipping'); ?></td>
      </tr>
      <tr>
        <td><?php echo tep_draw_separator('pixel_trans.gif', '100%', '10'); ?></td>
      </tr>
<?php 
    }
    if (!$ec_checkout) {
      tep_paypal_wpp_ep_button(FILENAME_CHECKOUT_SHIPPING);
    } else {
      tep_paypal_wpp_switch_checkout_method(FILENAME_CHECKOUT_SHIPPING);
    }
  }
  
  function tep_paypal_wpp_switch_checkout_method($filename) {
?>
      <tr>
        <td><?php echo tep_draw_separator('pixel_trans.gif', '100%', '10'); ?></td>
      </tr>
      <tr>
        <td>
          <table border="0" width="100%" cellspacing="0" cellpadding="2">
            <tr>
              <td class="main"><b><?php echo TEXT_PAYPALWPP_EC_HEADER; ?></b></td>
            </tr>
          </table>
        </td>
      </tr>
      <tr>
    	  <td width="100%" colspan=2 valign="top">
          <table border="0" width="100%" height="100%" cellspacing="1" cellpadding="2" class="infoBox">
            <tr class="infoBoxContents">
          		<td>
                <table border="0" width="100%" height="100%" cellspacing="0" cellpadding="2">
                  <tr>
            		    <td>
                      <table border="0" width="100%" cellspacing="0" cellpadding="2">
                  			<tr>
                          <td width="10"><?php echo tep_draw_separator('pixel_trans.gif', '10', '1'); ?></td>
                          <td align="center" style="font-size:14px; font-family: Arial, Verdana;"><b><?php echo TEXT_PAYPALWPP_EC_SWITCH_METHOD_1; ?></b><br><a href="<?php echo tep_href_link($filename, 'ec_cancel=1', 'SSL'); ?>"><?php echo TEXT_PAYPALWPP_EC_SWITCH_METHOD_2; ?></a></td>
                          <td width="10"><?php echo tep_draw_separator('pixel_trans.gif', '10', '1'); ?></td>
                        </tr>
              		    </table>
                    </td>
             		  </tr>
                </table>
              </td>
            </tr>
          </table>
        </td>
      </tr>
<?php
  }
  
  function tep_paypal_wpp_checkout_shipping_redirect($show_payment_page, $ec_enabled) {
    if ($show_payment_page || !$ec_enabled) {
      tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL'));
    } else {
      tep_redirect(tep_href_link(FILENAME_CHECKOUT_CONFIRMATION, '', 'SSL'));
    }
  }
  
  function tep_paypal_wpp_checkout_completed($ec_enabled) {
    if ($ec_enabled) {
      if ($_SESSION['paypal_ec_temp']) {
        tep_session_unregister('customer_id');
        tep_session_unregister('customer_default_address_id');
        tep_session_unregister('customer_first_name');
        tep_session_unregister('customer_country_id');
        tep_session_unregister('customer_zone_id');
        tep_session_unregister('comments');
        tep_paypal_wpp_delete_customer($customer_id);
        tep_session_unregister('paypal_ec_temp');
      }
    
      tep_session_unregister('paypal_ec_temp');
      tep_session_unregister('paypal_ec_token');
      tep_session_unregister('paypal_ec_payer_id');
      tep_session_unregister('paypal_ec_payer_info');
    }
  }
  
  function tep_paypal_wpp_delete_customer($customer_id) {
    $customer_id = preg_replace('/[^0-9]/', '', $customer_id);
    
    if ($customer_id < 1) return false;
    
    tep_db_query("delete from " . TABLE_ADDRESS_BOOK . " where customers_id = '" . (int)$customer_id . "'");
    tep_db_query("delete from " . TABLE_CUSTOMERS . " where customers_id = '" . (int)$customer_id . "'");
    tep_db_query("delete from " . TABLE_CUSTOMERS_INFO . " where customers_info_id = '" . (int)$customer_id . "'");
    tep_db_query("delete from " . TABLE_CUSTOMERS_BASKET . " where customers_id = '" . (int)$customer_id . "'");
    tep_db_query("delete from " . TABLE_CUSTOMERS_BASKET_ATTRIBUTES . " where customers_id = '" . (int)$customer_id . "'");
    tep_db_query("delete from " . TABLE_WHOS_ONLINE . " where customer_id = '" . (int)$customer_id . "'");
  }
  
  function tep_paypal_wpp_create_account_check($email_address) {
    $check_email_query = tep_db_query("SELECT customers_id as id, 
                                              customers_paypal_ec as ec 
                                       FROM " . TABLE_CUSTOMERS . " 
                                       WHERE customers_email_address = '" . tep_db_input($email_address) . "'
                                       LIMIT 1");
                                       
    if (tep_db_num_rows($check_email_query) > 0) {
      $check_email = tep_db_fetch_array($check_email_query);
      if ($check_email['ec'] == '1') {
        //It's a temp account, so delete it and let the user create a new one
        tep_paypal_wpp_delete_customer($check_email['id']);
        
        return false;
      }
    }
    return true;
  }
  
  function tep_paypal_wpp_checkout_payment_error_display() {
    global $messageStack, $ec_enabled;
    
    if (tep_session_is_registered('paypal_error')) {
      $messageStack->add('payment', $_SESSION['paypal_error']);
      tep_session_unregister('paypal_error');
    }
    
    if ($messageStack->size('payment') > 0) {
?>
      <tr>
        <td><?php echo $messageStack->output('payment'); ?></td>
      </tr>
      <tr>
        <td><?php echo tep_draw_separator('pixel_trans.gif', '100%', '10'); ?></td>
      </tr>
<?php
    }
  }
  
  function tep_paypal_wpp_show_user_options() {
    global $ec_enabled;
    
    if (tep_session_is_registered('customer_id')) {
      $show_user_options = true;
      if ($ec_enabled && tep_session_is_registered('paypal_ec_temp')) {
        //If this is a temp account that'll be deleted, don't show account information
        if ($_SESSION['paypal_ec_temp']) {
          return false;
        }
      }
    } else {
      return false;
    }
    
    return true;
  }
  
  if (SEARCH_ENGINE_FRIENDLY_URLS == 'true') $_GET &= $HTTP_GET_VARS;
  
  switch($_GET['action']) {
    case 'express_checkout':
      require_once(DIR_WS_CLASSES . 'payment.php');

      if (tep_session_is_registered('paypal_error')) tep_session_unregister('paypal_error');

      if (isset($_GET['clearSess'])) {
        tep_session_unregister('paypal_ec_temp');
    		tep_session_unregister('paypal_ec_token');
    		tep_session_unregister('paypal_ec_payer_id');
    		tep_session_unregister('paypal_ec_payer_info');
      }
      
      if ($ec_enabled) {
      
        $payment_modules = new payment('paypal_wpp');
        
        if (!method_exists($payment_modules, 'ec_step1') || !method_exists($payment_modules, 'ec_step2')) {
          die(TEXT_PAYPALWPP_ERROR_PAYMENT_CLASS);
        }
        
        if(!tep_session_is_registered('paypal_ec_token')) {
          $payment_modules->ec_step1($_GET['return_to']);
        } else {
          $payment_modules->ec_step2();
        }
      }
      break;
    case 'paypal_wpp_ipn':
      //if (count($_POST) > 0) {
        include('paypal_wpp_ipn.php');
      //}
      break;
    case 'cardinal_centinel_auth':
      if (tep_session_is_registered('cardinal_centinel') && isset($_POST['PaRes'])) {
        require_once(DIR_WS_MODULES . 'payment/paypal_wpp.php');
        $payment_module = new paypal_wpp;
        
        $payment_module->cardinal_centinel_before_process();
      }
      break;
  }

  $current_page = pathinfo($_SERVER['SCRIPT_NAME']);
  $current_page = $current_page['basename'];
  
  switch ($current_page) {
    case FILENAME_LOGIN:
      
      if ($ec_enabled) {
        //If they're here, they're either about to go to paypal or were sent back by an error, so clear these session vars
        if (tep_session_is_registered('paypal_ec_temp')) tep_session_unregister('paypal_ec_temp');
        if (tep_session_is_registered('paypal_ec_token')) tep_session_unregister('paypal_ec_token');
        if (tep_session_is_registered('paypal_ec_payer_id')) tep_session_unregister('paypal_ec_payer_id');
        if (tep_session_is_registered('paypal_ec_payer_info')) tep_session_unregister('paypal_ec_payer_info');
        
        //Find out if the user is logging in to checkout so that we know to draw the EC box          
        $checkout_login = false;
        if (sizeof($navigation->snapshot) > 0 || isset($_GET['payment_error'])) {
          if (strpos($navigation->snapshot['page'], 'checkout_') !== false || isset($_GET['payment_error'])) {
            $checkout_login = true;
          }
        }
        
        if (tep_session_is_registered('paypal_error')) {
          $checkout_login = true;
          $messageStack->add('login', $paypal_error);
          tep_session_unregister('paypal_error');
        }
      }
      break;
    case FILENAME_SHOPPING_CART:
      if (($ec_enabled && $cart->count_contents() > 0) || (isset($_GET['ec_cancel']) || (tep_session_is_registered('paypal_ec_token') && !tep_session_is_registered('paypal_ec_payer_id') && !tep_session_is_registered('paypal_ec_payer_info')))) {
        $ec_enabled = true;
        //If they're here, they're either about to go to paypal or were sent back by an error, so clear these session vars
        if (tep_session_is_registered('paypal_ec_temp')) tep_session_unregister('paypal_ec_temp');
        if (tep_session_is_registered('paypal_ec_token')) tep_session_unregister('paypal_ec_token');
        if (tep_session_is_registered('paypal_ec_payer_id')) tep_session_unregister('paypal_ec_payer_id');
        if (tep_session_is_registered('paypal_ec_payer_info')) tep_session_unregister('paypal_ec_payer_info');
      } else {
        $ec_enabled = false;
      }
      break;
    case FILENAME_CHECKOUT_SHIPPING:
      if ($ec_enabled) {
        if (isset($_GET['ec_cancel']) || (tep_session_is_registered('paypal_ec_token') && !tep_session_is_registered('paypal_ec_payer_id') && !tep_session_is_registered('paypal_ec_payer_info'))) {
          if (tep_session_is_registered('paypal_ec_temp')) tep_session_unregister('paypal_ec_temp');
          if (tep_session_is_registered('paypal_ec_token')) tep_session_unregister('paypal_ec_token');
          if (tep_session_is_registered('paypal_ec_payer_id')) tep_session_unregister('paypal_ec_payer_id');
          if (tep_session_is_registered('paypal_ec_payer_info')) tep_session_unregister('paypal_ec_payer_info');
        }
      
        $show_payment_page = false;
        
        $config_query = tep_db_query("SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'MODULE_PAYMENT_PAYPAL_DP_DISPLAY_PAYMENT_PAGE' LIMIT 1");
        if (tep_db_num_rows($config_query) > 0) {
          $config_result = tep_db_fetch_array($config_query);
          if ($config_result['configuration_value'] == 'Yes') {
            $show_payment_page = true;
          }
        }
        
        $ec_checkout = true;
        if (!tep_session_is_registered('paypal_ec_token') && !tep_session_is_registered('paypal_ec_payer_id') && !tep_session_is_registered('paypal_ec_payer_info')) { 
          $ec_checkout = false;
          $show_payment_page = true;
        } else {
          if (!tep_session_is_registered('payment')) tep_session_register('payment');
          $payment = 'paypal_wpp';
        }
      }
      break;
    case FILENAME_CHECKOUT_PAYMENT:
      if ($ec_enabled) {
        if (tep_session_is_registered('paypal_error')) {
          $checkout_login = true;
          $messageStack->add('payment', $paypal_error);
          tep_session_unregister('paypal_error');
        }
      }
      if (tep_session_is_registered('cardinal_centinel')) tep_session_unregister('cardinal_centinel');
      break;
    case FILENAME_CHECKOUT_CONFIRMATION:
      if ($ec_enabled) {
        $show_payment_page = false;
        
        $config_query = tep_db_query("SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'MODULE_PAYMENT_PAYPAL_DP_DISPLAY_PAYMENT_PAGE' LIMIT 1");
        if (tep_db_num_rows($config_query) > 0) {
          $config_result = tep_db_fetch_array($config_query);
          if ($config_result['configuration_value'] == 'Yes') {
            $show_payment_page = true;
          }
        }
        
        $ec_checkout = true;
        if (!tep_session_is_registered('paypal_ec_token') && !tep_session_is_registered('paypal_ec_payer_id') && !tep_session_is_registered('paypal_ec_payer_info')) { 
          $ec_checkout = false;
          $show_payment_page = true;
        }
      }
      break;
  }
?>