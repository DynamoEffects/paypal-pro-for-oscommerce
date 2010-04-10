<?php
/*
  $Id: paypal_wpp.php Brian Burton support [at] dynamoeffects [dot] com Exp $

  Copyright (c) 2008, 2009 Brian Burton - support [at] dynamoeffects [dot] com

  Released under the GNU General Public License
*/
  class paypal_wpp {
    var $code, $title, $description, $enabled, $resources, $is_admin, $max_retries, $cc_test_number, $total_amount;
    var $transaction_log = array();
    var $debug_email, $dynamo_checkout = false;
    
    /*
     * Constructor function -- initialize class properties
     */
    function paypal_wpp() {
      global $order;
      $this->code = 'paypal_wpp';
      $this->codeTitle = 'PayPal Website Payments Pro Plus';
      $this->codeVersion = '1.1.2';
      $this->debug_email = STORE_OWNER_EMAIL_ADDRESS;
      
      /* This variable stores the transaction request */
      $this->last_data = '';
      
      /* This variable sets the maximum number of times
         to retry the transaction if PayPal returns 10207.
         You can change it, but 10 should be enough
      */
      $this->max_retries = 10;
      
      $this->enableDebugging = ((MODULE_PAYMENT_PAYPAL_DP_DEBUGGING == 'True') ? '1' : '0');
      
      //Display correct module name depending on checkout type
      //Fix by Glen Hoag
    	if (tep_session_is_registered('paypal_ec_token') && tep_session_is_registered('paypal_ec_payer_id') && tep_session_is_registered('paypal_ec_payer_info')) {
    		$this->title = MODULE_PAYMENT_PAYPAL_EC_TEXT_TITLE;
    	} else {
    		$this->title = MODULE_PAYMENT_PAYPAL_DP_TEXT_TITLE;
    	}
      
      $this->description = MODULE_PAYMENT_PAYPAL_DP_TEXT_DESCRIPTION;
      $this->sort_order = MODULE_PAYMENT_PAYPAL_DP_SORT_ORDER;
      $this->enabled = ((MODULE_PAYMENT_PAYPAL_DP_STATUS == 'True') ? true : false);
      $this->is_admin = false;
      $this->cc_test_number = '4072497208897267';  //This is the test credit card number that works in the Sandbox only
      
      $this->resources = DIR_FS_CATALOG . DIR_WS_INCLUDES . 'paypal_wpp/';

      if (is_object($order)) $this->update_status();
    }  
    
    /*
     * Update status based on user's physical location
     */
    function update_status() {
      global $order;

      if ( ($this->enabled == true) && ((int)MODULE_PAYMENT_PAYPAL_DP_ZONE > 0) ) {
        $check_flag = false;
        $check_query = tep_db_query("select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . MODULE_PAYMENT_PAYPAL_DP_ZONE . "' and zone_country_id = '" . $order->billing['country']['id'] . "' order by zone_id");
        while ($check = tep_db_fetch_array($check_query)) {
          if ($check['zone_id'] < 1) {
            $check_flag = true;
            break;
          } elseif ($check['zone_id'] == $order->billing['zone_id']) {
            $check_flag = true;
            break;
          }
        }

        if ($check_flag == false) {
          $this->enabled = false;
        }
      }
    }

    /*
     * Javascript validation routines
     */ 
    function javascript_validation() {
      global $_SESSION;
      
      if (tep_session_is_registered('paypal_ec_token') && tep_session_is_registered('paypal_ec_payer_id') && tep_session_is_registered('paypal_ec_payer_info')) {
        return false;
      } else {
        $js  = '  if (payment_value == "' . $this->code . '") {' . "\n";
        $js .= '    var cc_firstname = document.checkout_payment.paypalwpp_cc_firstname.value;' . "\n";
        $js .= '    var cc_lastname = document.checkout_payment.paypalwpp_cc_lastname.value;' . "\n";
        $js .= '    var cc_number = document.checkout_payment.paypalwpp_cc_number.value;' . "\n";

        $js .= '    if (cc_firstname == "" || cc_lastname == "" || eval(cc_firstname.length) + eval(cc_lastname.length) < ' . CC_OWNER_MIN_LENGTH . ') {' . "\n";
        $js .= '      error_message = error_message + "' . MODULE_PAYMENT_PAYPAL_DP_TEXT_JS_CC_OWNER . '";' . "\n";
        $js .= '      error = 1;' . "\n";
        $js .= '    }' . "\n";
        $js .= '    if (cc_number == "" || cc_number.length < ' . CC_NUMBER_MIN_LENGTH . ') {' . "\n";
        $js .= '      error_message = error_message + "' . MODULE_PAYMENT_PAYPAL_DP_TEXT_JS_CC_NUMBER . '";' . "\n";
        $js .= '      error = 1;' . "\n";
        $js .= '    }' . "\n";
        if (MODULE_PAYMENT_PAYPAL_DP_CHECK_CVV2 == 'Yes') {
          $js .= '    var cc_checkcode = document.checkout_payment.paypalwpp_cc_checkcode.value;' . "\n";
          $js .= '    if (cc_checkcode == "") {' . "\n";
          $js .= '      error_message = error_message + "' . MODULE_PAYMENT_PAYPAL_DP_TEXT_JS_CC_CVV2 . '";' . "\n";
          $js .= '      error = 1;' . "\n";
          $js .= '    }' . "\n";
        }
        $js .= '  }' . "\n";
  
        return $js;
      }
    }

    /*
     * The entry fields found on checkout_payment.php
     */
    function selection() {
      global $order;

      $start_month = array(array('id' => '', 'text' => ''));
      
      for ($i=1; $i < 13; $i++) {
        $dropdown_item = array('id' => sprintf('%02d', $i), 'text' => $i . ' - ' . strftime('%B',mktime(0,0,0,$i,1,2000)));
        $expires_month[] = $dropdown_item;
        $start_month[] = $dropdown_item;
      }

      
      $start_year = array(array('id' => '', 'text' => ''));
      $today = getdate(); 
      for ($i=$today['year']; $i > $today['year'] - 30; $i--) {
        $dropdown_item = array('id' => strftime('%Y',mktime(0,0,0,1,1,$i)), 'text' => strftime('%Y',mktime(0,0,0,1,1,$i)));
        $start_year[] = $dropdown_item;
      }
      
      $today = getdate(); 
      for ($i=$today['year']; $i <= $today['year']+10; $i++) {
        $dropdown_item = array('id' => strftime('%y',mktime(0,0,0,1,1,$i)), 'text' => strftime('%Y',mktime(0,0,0,1,1,$i)));
        $expires_year[] = $dropdown_item;
      }
      
      
      $accepted_card_types = array(array('id' => 'Visa', 'text' => 'Visa'),
                                   array('id' => 'MasterCard', 'text' => 'MasterCard'),
                                   array('id' => 'Discover', 'text' => 'Discover'));
                                   
      //Amex is not supported for UK merchants
      if (MODULE_PAYMENT_PAYPAL_DP_UK_ENABLED != 'Yes') {
        $accepted_card_types[] = array('id' => 'Amex', 'text' => 'American Express');
      } else {
        $accepted_card_types[] = array('id' => 'Solo', 'text' => 'Solo');
        $accepted_card_types[] = array('id' => 'Maestro', 'text' => 'Maestro');
      }

      $selection = array('id' => $this->code,
                         'module' => MODULE_PAYMENT_PAYPAL_DP_TEXT_TITLE . '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . tep_image(DIR_WS_INCLUDES . 'paypal_wpp/images/credit_cards.gif', $this->title),
                         'fields' => array(array('title' => MODULE_PAYMENT_PAYPAL_DP_TEXT_CREDIT_CARD_FIRSTNAME,
                                                 'field' => tep_draw_input_field('paypalwpp_cc_firstname', $order->billing['firstname'])),
                                           array('title' => MODULE_PAYMENT_PAYPAL_DP_TEXT_CREDIT_CARD_LASTNAME,
                                                 'field' => tep_draw_input_field('paypalwpp_cc_lastname', $order->billing['lastname'])),
                                           array('title' => MODULE_PAYMENT_PAYPAL_DP_TEXT_CREDIT_CARD_TYPE,
                                                 'field' => tep_draw_pull_down_menu('paypalwpp_cc_type', $accepted_card_types)),                                                                                                              
                                           array('title' => MODULE_PAYMENT_PAYPAL_DP_TEXT_CREDIT_CARD_NUMBER,
                                                 'field' => tep_draw_input_field('paypalwpp_cc_number', (MODULE_PAYMENT_PAYPAL_DP_SERVER == 'sandbox' ? $this->cc_test_number : ''))),
                                           array('title' => MODULE_PAYMENT_PAYPAL_DP_TEXT_CREDIT_CARD_EXPIRES,
                                                 'field' => tep_draw_pull_down_menu('paypalwpp_cc_expires_month', $expires_month) . '&nbsp;' . tep_draw_pull_down_menu('paypalwpp_cc_expires_year', $expires_year)),
                                           array('title' => MODULE_PAYMENT_PAYPAL_DP_TEXT_CREDIT_CARD_CHECKNUMBER,
                                                 'field' => tep_draw_input_field('paypalwpp_cc_checkcode', '', 'size="4" maxlength="4"') . (!$this->is_admin ? '&nbsp;<a href="javascript:void(0);" onclick="javascript:window.open(\'' . tep_href_link(DIR_WS_INCLUDES . 'paypal_wpp/' . FILENAME_CVV2INFO, '', 'SSL') . '\',\'cardsecuritycode\',\'toolbar=no, location=no, directories=no, status=no, menubar=no, scrollbars=yes, resizable=no, width=500, height=350\');">' . MODULE_PAYMENT_PAYPAL_DP_TEXT_CREDIT_CARD_CHECKNUMBER_LOCATION . '</a>' : '' ))));
                                                 
      if (MODULE_PAYMENT_PAYPAL_DP_UK_ENABLED == 'Yes') {
        $selection['fields'][] = array('title' => MODULE_PAYMENT_PAYPAL_DP_TEXT_CREDIT_CARD_START_MONTH,
                                       'field' => tep_draw_pull_down_menu('paypalwpp_cc_start_month', $start_month, '', 'id="wpp_uk_start_month"') . '&nbsp;<small>' . MODULE_PAYMENT_PAYPAL_DP_TEXT_CREDIT_CARD_SWITCHSOLO_ONLY . '</small>');
        $selection['fields'][] = array('title' => MODULE_PAYMENT_PAYPAL_DP_TEXT_CREDIT_CARD_START_YEAR,
                                       'field' => tep_draw_pull_down_menu('paypalwpp_cc_start_year', $start_year, '', 'id="wpp_uk_start_year"') . '&nbsp;<small>' . MODULE_PAYMENT_PAYPAL_DP_TEXT_CREDIT_CARD_SWITCHSOLO_ONLY . '</small>');
        $selection['fields'][] = array('title' => MODULE_PAYMENT_PAYPAL_DP_TEXT_CREDIT_CARD_ISSUE_NUMBER,
                                       'field' => tep_draw_input_field('paypalwpp_cc_issue_number', '', 'size="2" maxlength="2" id="wpp_uk_issue_number"') . '&nbsp;<small>' . MODULE_PAYMENT_PAYPAL_DP_TEXT_CREDIT_CARD_SWITCHSOLO_ONLY . '</small>');
      }
      
      if (MODULE_PAYMENT_PAYPAL_DP_BUTTON_PAYMENT_PAGE == 'Yes' && !$this->is_admin) {
        $selection['fields'][] = array('title' => '<b>' . MODULE_PAYMENT_PAYPAL_DP_TEXT_EC_HEADER . '</b>',
                                       'field' => MODULE_PAYMENT_PAYPAL_EC_ALTERNATIVE . '<a href="' . tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'action=express_checkout&return_to=' . FILENAME_CHECKOUT_PAYMENT, 'SSL') . '"><img src="' . FILENAME_EXPRESS_CHECKOUT_IMG . '" border=0 style="padding-right:10px;padding-bottom:10px"></a><br><span style="font-size:11px; font-family: Arial, Verdana;">' . MODULE_PAYMENT_PAYPAL_DP_TEXT_BUTTON_TEXT . '</span></td>');
      }

      return $selection;
    }
    
    /*
     * This is the credit card check done between checkout_payment.php and 
     * checkout_confirmation.php (called from checkout_confirmation.php)
     */
    function pre_confirmation_check() {
      global $_POST, $_SESSION;
      //If this is an EC checkout, do nuttin'
      if (tep_session_is_registered('paypal_ec_token') && tep_session_is_registered('paypal_ec_payer_id') && tep_session_is_registered('paypal_ec_payer_info')) {
        return false;
      } else {
        require_once(DIR_FS_CATALOG . DIR_WS_CLASSES . 'cc_validation.php');
  
        $cc_validation = new cc_validation();
        $result = $cc_validation->validate($_POST['paypalwpp_cc_number'], $_POST['paypalwpp_cc_expires_month'], $_POST['paypalwpp_cc_expires_year']);
  
        $error = '';
        if ($result === -1) {
          $error = sprintf(TEXT_CCVAL_ERROR_UNKNOWN_CARD, substr($cc_validation->cc_number, 0, 4));
        } elseif ($result > -5 && $result < -1) {
          $error = TEXT_CCVAL_ERROR_INVALID_DATE;
        } elseif ($result < 1) {
          $error = TEXT_CCVAL_ERROR_INVALID_NUMBER;
        }
        
        $_POST['paypalwpp_cc_checkcode'] = preg_replace('/[^0-9]/i', '', $_POST['paypalwpp_cc_checkcode']);
                
        if ( ($result == false) || ($result < 1) ) {          
          $this->away_with_you(MODULE_PAYMENT_PAYPAL_DP_TEXT_CARD_ERROR . '<br><br>' . $error, false, FILENAME_CHECKOUT_PAYMENT);
        }
  
        if ($cc_validation->cc_type == 'Maestro/Solo') {
          if ($_POST['paypalwpp_cc_type'] == 'Solo' || $_POST['paypalwpp_cc_type'] == 'Maestro') {
            $this->cc_card_type = $_POST['paypalwpp_cc_type'];
          } else {
            $this->cc_card_type = 'Maestro';
          }
        } else {
          $this->cc_card_type = $cc_validation->cc_type;
        }

        $this->cc_card_number = $cc_validation->cc_number;
        $this->cc_expiry_month = $cc_validation->cc_expiry_month;
        $this->cc_expiry_year = $cc_validation->cc_expiry_year;
        $this->cc_checkcode = $_POST['paypalwpp_cc_checkcode'];
      }
    }

    /*
     * User's payment information seen on checkout_confirmation.php
     */
    function confirmation() {
      global $_POST, $_SESSION;

      if (tep_session_is_registered('paypal_ec_token') && tep_session_is_registered('paypal_ec_payer_id') && tep_session_is_registered('paypal_ec_payer_info')) {
        $confirmation = array('title' => MODULE_PAYMENT_PAYPAL_EC_TEXT_TITLE, 'fields' => array());
      } else {
        $confirmation = array('title' => MODULE_PAYMENT_PAYPAL_DP_TEXT_TITLE,
                              'fields' => array(array('title' => MODULE_PAYMENT_PAYPAL_DP_TEXT_CREDIT_CARD_FIRSTNAME,
                                                      'field' => $_POST['paypalwpp_cc_firstname']),
                                                array('title' => MODULE_PAYMENT_PAYPAL_DP_TEXT_CREDIT_CARD_LASTNAME,
                                                      'field' => $_POST['paypalwpp_cc_lastname']),
                                                array('title' => MODULE_PAYMENT_PAYPAL_DP_TEXT_CREDIT_CARD_TYPE,
                                                      'field' => $_POST['paypalwpp_cc_type']),
                                                array('title' => MODULE_PAYMENT_PAYPAL_DP_TEXT_CREDIT_CARD_NUMBER,
                                                      'field' => str_repeat('X', (strlen($_POST['paypalwpp_cc_number']) - 4)) . substr($_POST['paypalwpp_cc_number'], -4)),
                                                array('title' => MODULE_PAYMENT_PAYPAL_DP_TEXT_CREDIT_CARD_EXPIRES,
                                                      'field' => strftime('%B, %Y', mktime(0,0,0,$_POST['paypalwpp_cc_expires_month'], 1, '20' . $_POST['paypalwpp_cc_expires_year'])))));
  
        if (tep_not_null($_POST['paypalwpp_cc_checkcode'])) {
          $confirmation['fields'][] = array('title' => MODULE_PAYMENT_PAYPAL_DP_TEXT_CREDIT_CARD_CHECKNUMBER,
                                            'field' => $_POST['paypalwpp_cc_checkcode']);
        }
      }
      return $confirmation;
    }
    
    /*
     * Send the user away
     */
    function away_with_you($error_msg = '', $kill_sess_vars = false, $goto_page = '') {
      global $customer_first_name, $customer_id, $navigation, $paypal_error;
      
      //Dynamo Checkout specific code
      if ($this->dynamo_checkout && $error_msg != '') {
        $kill_sess_vars = true;
      }
      if ($kill_sess_vars) {
        if ($_SESSION['paypal_ec_temp']) { 
          $this->ec_delete_user($customer_id);
        }
        //Unregister the paypal session variables making the user start over
        if (tep_session_is_registered('paypal_ec_temp')) tep_session_unregister('paypal_ec_temp');
        if (tep_session_is_registered('paypal_ec_token')) tep_session_unregister('paypal_ec_token');
        if (tep_session_is_registered('paypal_ec_payer_id')) tep_session_unregister('paypal_ec_payer_id');
        if (tep_session_is_registered('paypal_ec_payer_info')) tep_session_unregister('paypal_ec_payer_info');
      }
      
      //Decide where to redirect them
      if ($goto_page == '' && !$this->is_admin) {
        if (tep_session_is_registered('customer_first_name') && tep_session_is_registered('customer_id')) {
          if ($goto_page == FILENAME_CHECKOUT_PAYMENT || MODULE_PAYMENT_PAYPAL_DP_DISPLAY_PAYMENT_PAGE == 'Yes') {
            $redirect_path = FILENAME_CHECKOUT_PAYMENT;
          } else {
            $redirect_path = FILENAME_CHECKOUT_SHIPPING;
          }
        } else {
          $navigation->set_snapshot(FILENAME_CHECKOUT_SHIPPING);
          $redirect_path = FILENAME_LOGIN;
        }
      } else {
        $redirect_path = $goto_page;
      }
      
      if ($error_msg) {
        if (!tep_session_is_registered('paypal_error')) tep_session_register('paypal_error');
        $_SESSION['paypal_error'] = $error_msg;
        
        if ($this->dynamo_checkout) {
          tep_redirect(tep_href_link(FILENAME_CHECKOUT, '', 'SSL', true, false));
          exit;
        }
      } else {
        if (tep_session_is_registered('paypal_error')) tep_session_unregister('paypal_error');
      }
      
      if (!$this->is_admin && !$this->dynamo_checkout)
        tep_redirect(tep_href_link($redirect_path, '', 'SSL', true, false));
      else
        return false;
    }
    
    /*
     * Paypal will sometimes send back more than one error message, 
     * so we must loop through them if necessary
     */
    function return_transaction_errors($errors) {
      $error_return = '';
      
      if (is_array($errors)) {
        for ($x = 0; $x < count($errors); $x++) {
          if ($error_return) $error_return .= '<br><br>';
          if (count($errors) > 1) {
            $error_return .= 'Error #' . ($x + 1) . ': ';
          }
          $error_return .= $errors[$x]['ShortMessage'] . ' (' . $errors[$x]['ErrorCode'] . ')<br>' . $errors[$x]['LongMessage'];
        }
      }
      
      return $error_return;
    }
    
    
    /*
     * Hidden user payment information found on checkout_confirmation.php
     */
    function process_button() {
      global $_POST, $order, $currencies, $currency;

      if (tep_session_is_registered('paypal_ec_token') && tep_session_is_registered('paypal_ec_payer_id') && tep_session_is_registered('paypal_ec_payer_info')) {
        return '';
      } else {       
        $process_button_string = tep_draw_hidden_field('paypalwpp_cc_type', $_POST['paypalwpp_cc_type']) .
                                 tep_draw_hidden_field('paypalwpp_cc_expires_month', $_POST['paypalwpp_cc_expires_month']) .
                                 tep_draw_hidden_field('paypalwpp_cc_expires_year', $_POST['paypalwpp_cc_expires_year']) .
                                 tep_draw_hidden_field('paypalwpp_cc_number', $_POST['paypalwpp_cc_number']) .
                                 tep_draw_hidden_field('paypalwpp_cc_checkcode', $_POST['paypalwpp_cc_checkcode']) .
                                 tep_draw_hidden_field('paypalwpp_cc_firstname', $_POST['paypalwpp_cc_firstname']) .
                                 tep_draw_hidden_field('paypalwpp_cc_lastname', $_POST['paypalwpp_cc_lastname']) .
                                 tep_draw_hidden_field('paypalwpp_redirect_url', tep_href_link(FILENAME_CHECKOUT_PROCESS, '', 'SSL', true));
        
        //Include UK-specific fields
        if (MODULE_PAYMENT_PAYPAL_DP_UK_ENABLED == 'Yes') {
          $process_button_string .= tep_draw_hidden_field('paypalwpp_cc_start_month', $_POST['paypalwpp_cc_start_month']) .
                                    tep_draw_hidden_field('paypalwpp_cc_start_year', $_POST['paypalwpp_cc_start_year']) .
                                    tep_draw_hidden_field('paypalwpp_cc_issue_number', $_POST['paypalwpp_cc_issue_number']);
        }
        return $process_button_string;
      }
    }
    
    function wpp_fix_state_for_paypal($country_code, $state) {
      //Thanks goes to SteveDallas for improved international support
      //Set the billing state field depending on what PayPal wants to see for that country
      switch ($country_code) {
        case 'US':
        case 'CA':
        //Paypal only accepts two character state/province codes for some countries
          if (strlen($state) > 2) {
            $state_query = tep_db_query("SELECT z.zone_code FROM " . TABLE_ZONES . " as z, " . TABLE_COUNTRIES . " as c WHERE c.countries_iso_code_2 = '" . $country_code . "' and c.countries_id = z.zone_country_id and z.zone_name = '" . tep_db_input($state) . "'");
            if (tep_db_num_rows($state_query) > 0) {
              $state_array = tep_db_fetch_array($state_query);
              $the_state = $state_array['zone_code'];
            } else {
              $this->away_with_you(MODULE_PAYMENT_PAYPAL_DP_TEXT_STATE_ERROR);
            }
          } else {
            $the_state = $state;
          }
          
          break;
        case 'AT':
        case 'BE':
        case 'FR':
        case 'DE':
        case 'CH':
          $the_state = '';
          break;
        default:
          $the_state = $this->wpp_xml_safe($state);
          break;
      }
      return $the_state;
    }

    function wpp_get_currency() {
      switch (MODULE_PAYMENT_PAYPAL_DP_CURRENCY) {
        case 'AUD':
        case 'CAD':
        case 'EUR':
        case 'GBP':
        case 'JPY':
        case 'USD':
          $currency = MODULE_PAYMENT_PAYPAL_DP_CURRENCY;
          break;
        default:
          $currency = 'USD';
          break;
      }
      return $currency;
    }
    
    
    /*
     * This one function replaces all of those damned pear modules.  Go me.
     */
    function wpp_execute_transaction($type, $data) {
      global $order;
      
      $service = 'paypal';
      
      if (in_array($type, array('cmpi_lookup', 'cmpi_authenticate'))) {
        $service = 'cardinal';
      }
      
      //Make sure cURL exists
      if (!function_exists('curl_init')) {
        $this->away_with_you(MODULE_PAYMENT_PAYPAL_DP_CURL_NOT_INSTALLED, true);
      }
      
      //Make sure the certificate exists
      if (!file_exists(MODULE_PAYMENT_PAYPAL_DP_CERT_PATH)) {
        $this->away_with_you(MODULE_PAYMENT_PAYPAL_DP_CERT_NOT_INSTALLED, true);
      }
      
      //Add some common variables to the $data array
      $data['PAYPAL_USERNAME'] = MODULE_PAYMENT_PAYPAL_DP_API_USERNAME;
      $data['PAYPAL_PASSWORD'] = MODULE_PAYMENT_PAYPAL_DP_API_PASSWORD;
      $data['PAYPAL_VERSION'] = '2.0';
      
      //Solo and Maestro cards can only be authorized and the currency must be GBP
      if ($order->info['cc_type'] == 'Maestro' || $order->info['cc_type'] == 'Solo') {
        $data['PAYPAL_PAYMENT_ACTION'] = 'Authorization';
        $data['PAYPAL_CURRENCY'] = 'GBP';
        $this->transaction_log['transaction_type'] = 'AUTHORIZATION';
      } else {
        //If the admin is charging the card, it should be a sale no matter what
        if ($this->is_admin) {
          $data['PAYPAL_PAYMENT_ACTION'] = 'Sale';
        } else {
          $data['PAYPAL_PAYMENT_ACTION'] = MODULE_PAYMENT_PAYPAL_DP_PAYMENT_ACTION;
        }
        
        $this->transaction_log['transaction_type'] = $data['PAYPAL_PAYMENT_ACTION'];
        
        $data['PAYPAL_CURRENCY'] = $this->wpp_get_currency();
      }
      
      $data['PAYPAL_MERCHANT_SESSION_ID'] = tep_session_id();
      $data['PAYPAL_IP_ADDRESS'] = $_SERVER['REMOTE_ADDR'];

      if ($service == 'paypal') {
        if (MODULE_PAYMENT_PAYPAL_DP_SERVER == 'sandbox') {
          $service_url = "https://api.sandbox.paypal.com/2.0/"; 
        } else {
          $service_url = "https://api.paypal.com/2.0/"; 
        }
      } else {
        $service_url = MODULE_PAYMENT_PAYPAL_DP_CC_TXURL;
      }
      
      //Make sure the XML file exists
      if (file_exists($this->resources . 'xml/' . $type . '.xml')) {
        //Suck in XML framework
        $xml_file = $this->resources . 'xml/' . $type . '.xml';
        $fp = fopen($xml_file, "r");
        $xml_contents = fread($fp, filesize($xml_file));
        fclose($fp);
        
        //Now, replace all of the placeholders with real data
        foreach ($data as $k => $v) {
          $xml_contents = str_replace($k, $v, $xml_contents);
        }

        //Set the last_data variable with this transaction request (for error logging)
        $this->last_data = $xml_contents;
        //This is used only during development
        //$this->DBG_error("\n\n\n\n\n\nREQUEST " . date("U") . "\n" . $xml_contents);
        
        //Initialize curl
        $ch = curl_init(); 

        //For the poor souls on GoDaddy and the like, set the connection to go through their proxy
        if (trim(MODULE_PAYMENT_PAYPAL_DP_PROXY) != '') {
          curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
          curl_setopt($ch, CURLOPT_PROXY, MODULE_PAYMENT_PAYPAL_DP_PROXY);
        }
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 180);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, $service_url); 
        
        if ($service == 'paypal') {
          curl_setopt($ch, CURLOPT_SSLCERTTYPE, "PEM"); 
          curl_setopt($ch, CURLOPT_SSLCERT, MODULE_PAYMENT_PAYPAL_DP_CERT_PATH);
        } elseif ($service == 'cardinal') {
          $xml_contents = 'cmpi_msg=' . urlencode($xml_contents);
        }
        
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml_contents); 
        
        $response = curl_exec($ch);
        
        //This is used only during development
        //$this->DBG_error("\n\nRESPONSE " . date("U") . "\n" . print_r($this->wpp_parse_xml($response), true));

        if ($response != '') {
          curl_close($ch);
          //Simple check to make sure that this is a valid response
          if ($service == 'paypal') {
            if (strpos($response, 'SOAP-ENV') === false) {
              $response = false;
            }
          } elseif ($service == 'cardinal') {
            if (strpos($response, 'CardinalMPI') === false) {
              $response = false;
            }
          }
          if ($response) {
            //Convert the XML into an easy-to-use associative array
            $response = $this->wpp_parse_xml($response);       
          }
          return $response;
        } else {
          $curl_errors = curl_error($ch) . ' (Error No. ' . curl_errno($ch) . ')';

          curl_close($ch);
          $this->away_with_you($curl_errors, true);
        }
      } else {
        //Oh noes, someone has missing XML files!
        $this->away_with_you(MODULE_PAYMENT_PAYPAL_DP_MISSING_XML . '<br>(' . $type . '.xml)', true);
      }
    }
    
    /*
     * This is used only during development.  It dumps whatever message it's
     * fed into a text file in the store's root directory
     */
    function DBG_error($msg) {
      $handle = fopen(DIR_FS_CATALOG . 'error_log.txt', 'a');
      fwrite($handle, "\n" . $msg);
      fclose($handle);
      return true;
    }
    
    /*
     * Breaks the XML response into an associative array
     */
    function wpp_parse_xml($text) {
      $reg_exp = '/<(\w+)[^>]*>(.*?)<\/\\1>/s';
      preg_match_all($reg_exp, $text, $match);
      foreach ($match[1] as $key=>$val) {
        if ( preg_match($reg_exp, $match[2][$key]) ) {
            $array[$val][] = $this->wpp_parse_xml($match[2][$key]);
        } else {
            $array[$val] = $match[2][$key];
        }
      }
      return $array;
    } 

    /*
     * Step 1 in the Express Checkout process.  This sends the user to PayPal.
     */
    function ec_step1($return_to = FILENAME_CHECKOUT_SHIPPING) {
      global $order, $customer_first_name, $customer_id, $languages_id, $currencies;
      $order_info = array();
      
      require_once(DIR_WS_CLASSES . 'order.php');
      $order = new order;
      
      //Find out the user's language so that if PayPal supports it, it'll be the language used on PayPal's site.
      $lang_query = tep_db_query("SELECT CODE FROM " . TABLE_LANGUAGES . " WHERE languages_id = '".$languages_id."' LIMIT 1");

      if(tep_db_num_rows($lang_query)) {
        $lang_id = tep_db_fetch_array($lang_query);

        //Only these 5 country codes are valid, so default to the good ol' US of A if they're from Krazakalakastan
        switch ($lang_id['code']) {
          case 'de':
            $lang_code = 'DE';
            break;
          case 'fr':
            $lang_code = 'FR';
            break;
          case 'it':
            $lang_code = 'IT';
            break;
          case 'ja':
            $lang_code = 'JP';
            break;
          default:
            $lang_code = 'US';
            break;
        }
      } else {
        $lang_code = 'US';
      }      
      
      //If the merchant has a different currency selected for this module
      //than they do as a default for the store, all prices will be converted
      $currency_value = $currencies->get_value($this->wpp_get_currency());
      
      $order_info['PAYPAL_ORDER_TOTAL'] = number_format($order->info['total'] * $currency_value, 2, '.', '');
      
      //Page Style
      if (trim(MODULE_PAYMENT_PAYPAL_EC_PAGE_STYLE) != '') {
        $order_info['PAYPAL_PAGE_STYLE'] = MODULE_PAYMENT_PAYPAL_EC_PAGE_STYLE;
      } else {
        $order_info['PAYPAL_PAGE_STYLE'] = '';
      }

      //These four attributes don't seem to have any effect.  Use page styles instead, it does the same thing.
      $order_info['PAYPAL_CPP_HEADER_IMAGE'] = '';
      $order_info['PAYPAL_CPP_HEADER_BORDER_COLOR'] = '';
      $order_info['PAYPAL_CPP_HEADER_BACK_COLOR'] = '';
      $order_info['PAYPAL_CPP_PAYFLOW_COLOR'] = '';
      
      if ($this->dynamo_checkout) {
        $redirect_path = FILENAME_CHECKOUT;
      } elseif ($return_to == FILENAME_SHOPPING_CART) {
        $redirect_path = $return_to;
      } elseif ($return_to == FILENAME_CHECKOUT_SHIPPING && tep_session_is_registered('customer_first_name') && tep_session_is_registered('customer_id')) {
        $redirect_path = $return_to;
      } elseif(!tep_session_is_registered('customer_first_name') && !tep_session_is_registered('customer_id')) {
        $redirect_path = FILENAME_LOGIN;
      }
      $redirect_attr = 'ec_cancel=1';
      
      //These strings need to have ampersands escaped
      $order_info['PAYPAL_RETURN_URL'] = htmlspecialchars(html_entity_decode(tep_href_link(basename($_SERVER['SCRIPT_NAME']),'action=express_checkout', 'SSL')));
      $order_info['PAYPAL_CANCEL_URL'] = htmlspecialchars(html_entity_decode(tep_href_link($redirect_path, $redirect_attr, 'SSL')));
      
      if(MODULE_PAYMENT_PAYPAL_DP_CONFIRMED == 'Yes') {
        $order_info['PAYPAL_REQUIRE_CONFIRM_SHIPPING'] = '1';
      } else {
        $order_info['PAYPAL_REQUIRE_CONFIRM_SHIPPING'] = '0';
      }

      $order_info['PAYPAL_LOCALE_CODE'] = $lang_code;
      
      /* 
        Check that the word "virtual" is in the content_type property instead 
        of checking that it *is* set to "virtual" because there's another
        popular contribution that modifies the actual field.
       */

      if (strpos($order->content_type,'virtual') !== false) {
        $order_info['PAYPAL_NO_SHIPPING'] = '1';
      } else {
        $order_info['PAYPAL_NO_SHIPPING'] = '0';
      }
        
      /* If the address in the database should be used, only set 
         PAYPAL_ADDRESS_OVERRIDE to '1' if the user is logged in
         and this is a physical purchase.  If we find out later
         that they have an account, the address will be switched
         once they return.
       */
      if (MODULE_PAYMENT_PAYPAL_EC_ADDRESS_OVERRIDE == 'Store' && 
          $order_info['PAYPAL_NO_SHIPPING'] == '0' && 
          tep_session_is_registered('customer_first_name') && 
          tep_session_is_registered('customer_id') && 
          $order->delivery['street_address'] != '' && 
          $order->delivery['state'] != '') 
      {
        $order_info['PAYPAL_ADDRESS_OVERRIDE'] = '1';

        $order_info['PAYPAL_NAME'] = trim($order->delivery['firstname'] . ' ' . $order->delivery['lastname']);
        $order_info['PAYPAL_ADDRESS1'] = $order->delivery['street_address'];
        $order_info['PAYPAL_ADDRESS2'] = $order->delivery['suburb'];
        $order_info['PAYPAL_CITY'] = $order->delivery['city'];
        $order_info['PAYPAL_STATE'] = $this->wpp_fix_state_for_paypal($order->delivery['country']['iso_code_2'], $order->delivery['state']);
        $order_info['PAYPAL_ZIP'] = $order->delivery['postcode'];
        $order_info['PAYPAL_COUNTRY'] = $order->delivery['country']['iso_code_2'];
      } else {
        $order_info['PAYPAL_ADDRESS_OVERRIDE'] = '0';
      }
      
      /* Don't override if the state is missing (Avoid 10729 errors) */
      if ($order_info['PAYPAL_ADDRESS_OVERRIDE'] == '1' && $order_info['PAYPAL_STATE'] == '') {
        $order_info['PAYPAL_ADDRESS_OVERRIDE'] = '0';
        $order_info['PAYPAL_NAME'] = '';
        $order_info['PAYPAL_ADDRESS1'] = '';
        $order_info['PAYPAL_ADDRESS2'] = '';
        $order_info['PAYPAL_CITY'] = '';
        $order_info['PAYPAL_STATE'] = '';
        $order_info['PAYPAL_ZIP'] = '';
        $order_info['PAYPAL_COUNTRY'] = '';
      }
      
      //This loop is necessary because I have found that many times Paypal simply does not respond.
      //Don't ask me why.
      for ($tries = $this->max_retries; $tries > 0; $tries--) {
        $response = $this->wpp_execute_transaction('setExpressCheckout', $order_info);
        
        if (count($response) > 0) break;
      }

      if(!is_array($response) || ($response['SetExpressCheckoutResponse'][0]['Ack'] != 'Success' && $response['SetExpressCheckoutResponse'][0]['Ack'] != 'SuccessWithWarning')) {
        if ($this->enableDebugging == '1') {
          $spacer =           "---------------------------------------------------------------------\r\n";
          
          $request_title   = "-------------------------------SEC_DUMP------------------------------\r\n";
          $request_title  .= "------------This is the information that was sent to PayPal----------\r\n";
          $response_title  = "-------------------------------FINAL_REQ-----------------------------\r\n";
          $response_title .= "-------------------This is the response from PayPal------------------\r\n";

          tep_mail(STORE_OWNER, 
                   $this->debug_email, 
                   'PayPal Error Dump', 
                   "In function: ec_step1()\r\n" . 
                   "Tries: " . $tries . "\r\n" . 
                   $spacer . $request_title . $spacer . str_replace('<', '&lt;', str_replace('>', '&gt;', $this->last_data)) . $spacer . "\r\n\r\n" . 
                   $response_title . $spacer . print_r($response, true), 
                   STORE_OWNER, 
                   $this->debug_email);
        }
        
        $this->away_with_you(MODULE_PAYMENT_PAYPAL_DP_TEXT_GEN_ERROR . $this->return_transaction_errors($response['SetExpressCheckoutResponse'][0]['Errors']), true);

      } else {
        tep_session_register('paypal_ec_token');
        $_SESSION['paypal_ec_token'] = $response['SetExpressCheckoutResponse'][0]['Token'];
        
        if(MODULE_PAYMENT_PAYPAL_DP_SERVER == 'live') {
          $paypal_url = 'https://www.paypal.com/cgi-bin/webscr';
        } else {
          $paypal_url = 'https://www.sandbox.paypal.com/cgi-bin/webscr';
        }
        tep_redirect($paypal_url."?cmd=_express-checkout&token=".$_SESSION['paypal_ec_token']);
      }
    }
    
    /*
     * Step 2 in the Express Checkout process.  Visitor just came back from PayPal 
     * and so we collect all the info returned, create an account if necessary, 
     * then log them in, and then send them to checkout_shipping.php.  
     * What a long, strange trip it's been.
     */
    function ec_step2() {
      global $customer_id, $customer_first_name, $language, $customer_default_address_id, $sendto, $billto;

      if ($_SESSION['paypal_ec_token'] == '') {
        if (isset($_GET['token'])) {
          $_SESSION['paypal_ec_token'] = $_GET['token'];
        } else {
          $this->away_with_you(MODULE_PAYMENT_PAYPAL_DP_INVALID_RESPONSE, true);
        }
      }
      
      //Make sure the token is in the correct format
      if (!ereg("([C-E]{2})-([A-Z0-9]{17})", $_SESSION['paypal_ec_token'])) {
        $this->away_with_you(MODULE_PAYMENT_PAYPAL_DP_INVALID_RESPONSE, true);
      }
      
      $token_info = array('PAYPAL_TOKEN' => $_SESSION['paypal_ec_token']);

      //This loop is necessary because I have found that many times Paypal simply does not respond.
      for ($tries = $this->max_retries; $tries > 0; $tries--) {
        $response = $this->wpp_execute_transaction('getExpressCheckoutDetails', $token_info);
        
        if (count($response) > 0) break;
      }
      
      if(!is_array($response)  || ($response['GetExpressCheckoutDetailsResponse'][0]['Ack'] != 'Success' && $response['GetExpressCheckoutDetailsResponse'][0]['Ack'] != 'SuccessWithWarning')) {
        if ($this->enableDebugging == '1') {
          $spacer =          "---------------------------------------------------------------------\r\n";
          
          $request_title   = "-------------------------------SEC_DUMP------------------------------\r\n";
          $request_title  .= "------------This is the information that was sent to PayPal----------\r\n";
          $response_title  = "-------------------------------FINAL_REQ-----------------------------\r\n";
          $response_title .= "-------------------This is the response from PayPal------------------\r\n";

          tep_mail(STORE_OWNER, 
                   $this->debug_email, 
                   'PayPal Error Dump', 
                   "In function: ec_step2()\r\n" . 
                   "Tries: " . $tries . "\r\n\r\n" . 
                   $spacer . $request_title . $spacer . str_replace('<', '&lt;', str_replace('>', '&gt;', $this->last_data)) . $spacer . "\r\n\r\n" . 
                   $response_title . $spacer . print_r($response, true), 
                   STORE_OWNER, 
                   $this->debug_email);
        }
        $this->away_with_you(MODULE_PAYMENT_PAYPAL_DP_GEN_ERROR . $this->return_transaction_errors($response['GetExpressCheckoutDetailsResponse'][0]['Errors']), true);
      } else {
        $root_node = $response['GetExpressCheckoutDetailsResponse'][0]['GetExpressCheckoutDetailsResponseDetails'][0];
        $payer_info = $root_node['PayerInfo'][0];
      
        if(MODULE_PAYMENT_PAYPAL_DP_REQ_VERIFIED == 'Yes' && strtolower($payer_info['PayerStatus']) != 'verified') {
          $this->away_with_you(MODULE_PAYMENT_PAYPAL_DP_TEXT_UNVERIFIED, true);
        }
        
        tep_session_register('paypal_ec_payer_id');
        $_SESSION['paypal_ec_payer_id'] = $payer_info['PayerID'];

        tep_session_register('paypal_ec_payer_info');
        $_SESSION['paypal_ec_payer_info'] = array(
            'payer_id' => $payer_info['PayerID'], 
            'payer_email' => utf8_decode($payer_info['Payer']), 
            'payer_firstname' => utf8_decode($payer_info['PayerName'][0]['FirstName']),
            'payer_lastname' => utf8_decode($payer_info['PayerName'][0]['LastName']),
            'payer_business' => utf8_decode($payer_info['PayerBusiness']),
            'payer_status' => utf8_decode($payer_info['PayerStatus']),
            'ship_owner' => utf8_decode($payer_info['Address'][0]['AddressOwner']),
            'ship_name' => utf8_decode($payer_info['Address'][0]['Name']),
            'ship_street_1' => utf8_decode($payer_info['Address'][0]['Street1']),
            'ship_street_2' => utf8_decode($payer_info['Address'][0]['Street2']),
            'ship_city' => utf8_decode($payer_info['Address'][0]['CityName']),
            'ship_state' => utf8_decode($payer_info['Address'][0]['StateOrProvince']),
            'ship_postal_code' => utf8_decode($payer_info['Address'][0]['PostalCode']),
            'ship_country' => utf8_decode($payer_info['Address'][0]['Country']),
            'ship_phone' => utf8_decode($root_node['ContactPhone']),
            'ship_address_status' => utf8_decode($payer_info['Address'][0]['AddressStatus']));
            

//moved this block below creation of paypal_ec_payer_info array, because it depends on these values.  
         $country_query = tep_db_query("SELECT countries_id, countries_name, address_format_id 
                                       FROM " . TABLE_COUNTRIES . " 
                                       WHERE countries_iso_code_2 = '" . tep_db_input($_SESSION['paypal_ec_payer_info']['ship_country']) . "' 
                                       LIMIT 1");
                                       
        if (tep_db_num_rows($country_query) > 0) {
          $country = tep_db_fetch_array($country_query);
          $country_id = $country['countries_id'];
          $_SESSION['paypal_ec_payer_info']['ship_country_id'] = $country_id;
          $_SESSION['paypal_ec_payer_info']['ship_country_name'] = $country['countries_name'];
          $address_format_id = $country['address_format_id'];
        } else {
          $this->away_with_you(MODULE_PAYMENT_PAYPAL_DP_TEXT_ERROR_COUNTRY, true);
        }

        $states_query = tep_db_query("SELECT zone_id 
                                      FROM " . TABLE_ZONES . " 
                                      WHERE (zone_code = '" . tep_db_input($_SESSION['paypal_ec_payer_info']['ship_state']) . "' 
                                         OR zone_name = '" . tep_db_input($_SESSION['paypal_ec_payer_info']['ship_state']) . "')
                                        AND zone_country_id = '" . (int)$country_id . "' 
                                      LIMIT 1");
                                      
        if (tep_db_num_rows($states_query) > 0) {
          $states = tep_db_fetch_array($states_query);
          $state_id = $states['zone_id'];
        } else {
          $state_id = 0;
        }

        $_SESSION['paypal_ec_payer_info']['ship_zone_id'] = $state_id;
    
        if (!tep_session_is_registered('paypal_ec_temp')) tep_session_register('paypal_ec_temp');
        
        //If the customer is logged in
        if (tep_session_is_registered('customer_first_name') && tep_session_is_registered('customer_id')) {
          //They're logged in, so forward them straight to checkout_shipping.php
          $order->customer['id'] = $customer_id;
          
          $this->set_ec_order_address();
          
          $_SESSION['paypal_ec_temp'] = false;

          $this->away_with_you(); 

        } else {
          //They're not logged in.  Create an account if necessary, and then log them in.
          //First, see if they're an existing customer

          //If Paypal didn't send an email address, something went wrong
          if (trim($_SESSION['paypal_ec_payer_info']['payer_email']) == '') $this->away_with_you(MODULE_PAYMENT_PAYPAL_DP_INVALID_RESPONSE, true);
          $check_customer_query = tep_db_query("select customers_id, customers_firstname, customers_lastname, customers_paypal_payerid, customers_paypal_ec from " . TABLE_CUSTOMERS . " where customers_email_address = '" . tep_db_input($_SESSION['paypal_ec_payer_info']['payer_email']) . "'");
          $check_customer = tep_db_fetch_array($check_customer_query);
          if (tep_db_num_rows($check_customer_query) > 0) {
            $acct_exists = true;
            if ($check_customer['customers_paypal_ec'] == '1') {
              //Delete the existing temporary account
              $this->ec_delete_user($check_customer['customers_id']);
              $acct_exists = false;
            }
          }

          //Create an account
          if (!$acct_exists) {
            //Generate a random-looking 8-char password
            $salt = "46z3haZzegmn676PA3rUw2vrkhcLEn2p1c6gf7vp2ny4u3qqfqBh5j6kDhuLmyv9xf";
            srand((double)microtime()*1000000); 
            $password = '';
            for ($x = 0; $x < 7; $x++) {
              $num = rand() % 33;
              $tmp = substr($salt, $num, 1);
              $password = $password . $tmp;
            }

            $sql_data_array = array('customers_firstname' => $_SESSION['paypal_ec_payer_info']['payer_firstname'],
                                    'customers_lastname' => $_SESSION['paypal_ec_payer_info']['payer_lastname'],
                                    'customers_email_address' => $_SESSION['paypal_ec_payer_info']['payer_email'],
                                    'customers_telephone' => $_SESSION['paypal_ec_payer_info']['ship_phone'],
                                    'customers_fax' => '',
                                    'customers_newsletter' => '0',
                                    'customers_password' => tep_encrypt_password($password),
                                    'customers_paypal_payerid' => $_SESSION['paypal_ec_payer_id']);

            tep_db_perform(TABLE_CUSTOMERS, $sql_data_array);
      
            $customer_id = tep_db_insert_id();
      
            $sql_data_array = array('customers_id' => $customer_id,
                                    'entry_company' => tep_db_input($_SESSION['paypal_ec_payer_info']['payer_business']),
                                    'entry_firstname' => tep_db_input($_SESSION['paypal_ec_payer_info']['payer_firstname']),
                                    'entry_lastname' => tep_db_input($_SESSION['paypal_ec_payer_info']['payer_lastname']),
                                    'entry_street_address' => tep_db_input($_SESSION['paypal_ec_payer_info']['ship_street_1']),
                                    'entry_suburb' => tep_db_input($_SESSION['paypal_ec_payer_info']['ship_street_2']),
                                    'entry_city' => tep_db_input($_SESSION['paypal_ec_payer_info']['ship_city']),
                                    'entry_state' => ($state_id ? '' : tep_db_input($_SESSION['paypal_ec_payer_info']['ship_state'])),
                                    'entry_zone_id' => $state_id,
                                    'entry_postcode' => tep_db_input($_SESSION['paypal_ec_payer_info']['ship_postal_code']),
                                    'entry_country_id' => $country_id);
      
            tep_db_perform(TABLE_ADDRESS_BOOK, $sql_data_array);
      
            $address_id = tep_db_insert_id();
            
            if (!tep_session_is_registered('billto')) tep_session_register('billto');
            $billto = $address_id;
            
            if (!tep_session_is_registered('sendto')) tep_session_register('sendto');

			if ($_SESSION['paypal_ec_payer_info']['payer_firstname'] . ' ' . $_SESSION['paypal_ec_payer_info']['payer_lastname'] == $_SESSION['paypal_ec_payer_info']['ship_name']) {
              $sendto = $address_id;
			} else {
			  //$sql_data_array already contains the bulk of the data; just set the 'ship to' name and re-use the rest.
              $sql_data_array['entry_firstname'] = tep_db_input($_SESSION['paypal_ec_payer_info']['ship_name']);
			  $sql_data_array['entry_lastname'] = '';
      
              tep_db_perform(TABLE_ADDRESS_BOOK, $sql_data_array);
      
              $address_id = tep_db_insert_id();			
              $sendto = $address_id;
			}
      
            tep_db_query("update " . TABLE_CUSTOMERS . " set customers_default_address_id = '" . (int)$address_id . "' where customers_id = '" . (int)$customer_id . "'");
      
            tep_db_query("insert into " . TABLE_CUSTOMERS_INFO . " (customers_info_id, customers_info_number_of_logons, customers_info_date_account_created) values ('" . (int)$customer_id . "', '0', now())");

            if (MODULE_PAYMENT_PAYPAL_DP_NEW_ACCT_NOTIFY == 'Yes') {
              require(DIR_WS_LANGUAGES . $language . '/' . FILENAME_CREATE_ACCOUNT);
              
              $email_text = sprintf(EMAIL_GREET_NONE, $_SESSION['paypal_ec_payer_info']['payer_firstname']) . EMAIL_WELCOME . EMAIL_TEXT;
              $email_text .= EMAIL_EC_ACCOUNT_INFORMATION . "Username: " . $_SESSION['paypal_ec_payer_info']['payer_email'] . "\nPassword: " . $password . "\n\n";
              $email_text .= EMAIL_CONTACT;
              
              tep_mail($_SESSION['paypal_ec_payer_info']['payer_firstname'] . " " . $_SESSION['paypal_ec_payer_info']['payer_lastname'], $_SESSION['paypal_ec_payer_info']['payer_email'], EMAIL_SUBJECT, $email_text, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);
              
              $_SESSION['paypal_ec_temp'] = false;
            } else {
              //Make it a temporary account that'll be deleted once they've checked out
              tep_db_query("UPDATE " . TABLE_CUSTOMERS . " SET customers_paypal_ec = '1' WHERE customers_id = '" . (int)$customer_id . "'");
              
              $_SESSION['paypal_ec_temp'] = True;
            }
          } else {
            $_SESSION['paypal_ec_temp'] = false;
          }
                   
          $this->user_login($_SESSION['paypal_ec_payer_info']['payer_email']);
        }
      }
    }
       
    /*
     * Set the order object's address properties to those returned by PayPal
     */
    function set_ec_order_address() {
      global $order, $sendto, $billto, $customer_id;
    /* 
      if (address_override = 'PAYPAL') {
        1. Search to see if address already exists.  If not, create it
        2. Set the sendto variable to this ID
      } elseif (address_override = 'STORE') {
        Grab the default address from the store and use it.
      }
    */
      if (!tep_session_is_registered('sendto')) tep_session_register('sendto'); 
      if (!tep_session_is_registered('billto')) tep_session_register('billto'); 
      
      if (MODULE_PAYMENT_PAYPAL_EC_ADDRESS_OVERRIDE == 'PayPal') {
        $address_query = tep_db_query("SELECT address_book_id as id 
                                       FROM " . TABLE_ADDRESS_BOOK . " 
                                       WHERE entry_street_address = '" . tep_db_input($_SESSION['paypal_ec_payer_info']['ship_street_1']) . "' 
                                         AND customers_id = '" . $customer_id . "'
										 AND entry_firstname = '" . tep_db_input($_SESSION['paypal_ec_payer_info']['payer_firstname']) . "' 
										 AND entry_lastname = '" . tep_db_input($_SESSION['paypal_ec_payer_info']['payer_lastname']) . "' 
                                         AND entry_city = '" . tep_db_input($_SESSION['paypal_ec_payer_info']['ship_city']) . "' 
                                         AND entry_postcode = '" . tep_db_input($_SESSION['paypal_ec_payer_info']['ship_postal_code']) . "' 
                                       LIMIT 1");
                                       
        if (tep_db_num_rows($address_query) > 0) {
          $address = tep_db_fetch_array($address_query);
          $billto = $address['id'];
        } else {
          //Create the address book entry
          $sql_data_array = array('customers_id' => $customer_id,
                                  'entry_company' => tep_db_input($_SESSION['paypal_ec_payer_info']['payer_business']),
                                  'entry_firstname' => tep_db_input($_SESSION['paypal_ec_payer_info']['payer_firstname']),
                                  'entry_lastname' => tep_db_input($_SESSION['paypal_ec_payer_info']['payer_lastname']),
                                  'entry_street_address' => tep_db_input($_SESSION['paypal_ec_payer_info']['ship_street_1']),
                                  'entry_suburb' => tep_db_input($_SESSION['paypal_ec_payer_info']['ship_street_2']),
                                  'entry_city' => tep_db_input($_SESSION['paypal_ec_payer_info']['ship_city']),
                                  'entry_state' => ((int)$_SESSION['paypal_ec_payer_info']['ship_zone_id'] ? '' : tep_db_input($_SESSION['paypal_ec_payer_info']['ship_state'])),
                                  'entry_zone_id' => (int)$_SESSION['paypal_ec_payer_info']['ship_zone_id'],
                                  'entry_postcode' => tep_db_input($_SESSION['paypal_ec_payer_info']['ship_postal_code']),
                                  'entry_country_id' => (int)$_SESSION['paypal_ec_payer_info']['ship_country_id']);
      
          tep_db_perform(TABLE_ADDRESS_BOOK, $sql_data_array);
      
          $insert_id = tep_db_insert_id();
          
          $billto = $insert_id;
        }
		
		//create a separate ship to address, if necessary
		if ($_SESSION['paypal_ec_payer_info']['payer_firstname'] . ' ' . $_SESSION['paypal_ec_payer_info']['payer_lastname'] == $_SESSION['paypal_ec_payer_info']['ship_name']) {
          $sendto = $billto;
		} else {
          //search the address book for the shipping address.  
          $address_query = tep_db_query("SELECT address_book_id as id 
                                         FROM " . TABLE_ADDRESS_BOOK . " 
                                         WHERE entry_street_address = '" . tep_db_input($_SESSION['paypal_ec_payer_info']['ship_street_1']) . "' 
                                           AND customers_id = '" . $customer_id . "'
										   AND '" . tep_db_input($_SESSION['paypal_ec_payer_info']['ship_name']) . "' = trim(concat(entry_firstname, ' ', entry_lastname)) 
                                           AND entry_city = '" . tep_db_input($_SESSION['paypal_ec_payer_info']['ship_city']) . "' 
                                           AND entry_postcode = '" . tep_db_input($_SESSION['paypal_ec_payer_info']['ship_postal_code']) . "' 
                                         LIMIT 1");
                                       
          if (tep_db_num_rows($address_query) > 0) {
            $address = tep_db_fetch_array($address_query);
            $sendto = $address['id'];
          } else {
            //Didn't find the address.  Create one.
            $sql_data_array = array('customers_id' => $customer_id,
                                    'entry_company' => tep_db_input($_SESSION['paypal_ec_payer_info']['payer_business']),
                                    'entry_firstname' => tep_db_input($_SESSION['paypal_ec_payer_info']['ship_name']),
                                    'entry_lastname' => '',
                                    'entry_street_address' => tep_db_input($_SESSION['paypal_ec_payer_info']['ship_street_1']),
                                    'entry_suburb' => tep_db_input($_SESSION['paypal_ec_payer_info']['ship_street_2']),
                                    'entry_city' => tep_db_input($_SESSION['paypal_ec_payer_info']['ship_city']),
                                    'entry_state' => ((int)$_SESSION['paypal_ec_payer_info']['ship_zone_id'] ? '' : tep_db_input($_SESSION['paypal_ec_payer_info']['ship_state'])),
                                    'entry_zone_id' => (int)$_SESSION['paypal_ec_payer_info']['ship_zone_id'],
                                    'entry_postcode' => tep_db_input($_SESSION['paypal_ec_payer_info']['ship_postal_code']),
                                    'entry_country_id' => (int)$_SESSION['paypal_ec_payer_info']['ship_country_id']);
      
            tep_db_perform(TABLE_ADDRESS_BOOK, $sql_data_array);
      
            $address_id = tep_db_insert_id();			
            $sendto = $address_id;
          }
		}
		
      /*
       * Use the default address found in the store
       */
      } else {
        if ((int)$sendto < 1 || (int)$billto < 1) {
          $address_query = tep_db_query("SELECT customers_default_address_id as id 
                                         FROM " . TABLE_CUSTOMERS . " 
                                         WHERE customers_id = " . (int)$customer_id . " 
                                         LIMIT 1");
                                         
          $address = tep_db_fetch_array($address_query);
          
          if ((int)$sendto < 1) {
            $sendto = $address['id'];
          }
          
          if ((int)$billto < 1) {
            $billto = $insert_id['id'];
          }
        }
      }
    }

    
    /*
      This allows the user to login with only a valid email (the email address sent back by PayPal)
      Their PayPal payerID is stored in the database, but I still don't know if that number changes.  If it doesn't, it could be used to
      help identify an existing customer who hasn't logged in.  Until I know for sure, the email address is enough
      
      *NOTE: If you have installed other contributions that create session variables on login, you'll need to add those same session
      variables below!
      
      There is a small security risk to this, but the only way it could be exploited is if the attacker had access to the customer's
      PayPal account, in which case, they'd be able to buy anything they want whether this module is installed or not.
    */
    function user_login($email_address) {

      global $order, $customer_id, $customer_default_address_id, $customer_first_name, $customer_country_id, $customer_zone_id;
      global $session_started, $language, $cart;
      if ($session_started == false) {
        tep_redirect(tep_href_link(FILENAME_COOKIE_USAGE));
      }
    
      require(DIR_WS_LANGUAGES . $language . '/' . FILENAME_LOGIN);
    
      $check_customer_query = tep_db_query("select customers_id, customers_firstname, customers_password, customers_email_address, customers_default_address_id, customers_paypal_payerid from " . TABLE_CUSTOMERS . " where customers_email_address = '" . tep_db_input($email_address) . "'");
      $check_customer = tep_db_fetch_array($check_customer_query);

      if (!tep_db_num_rows($check_customer_query)) {
        $this->away_with_you(MODULE_PAYMENT_PAYPAL_DP_TEXT_BAD_LOGIN, true);
      } else {
        if (SESSION_RECREATE == 'True') {
          tep_session_recreate();
        }

        $check_country_query = tep_db_query("select entry_country_id, entry_zone_id from " . TABLE_ADDRESS_BOOK . " where customers_id = '" . (int)$check_customer['customers_id'] . "' and address_book_id = '" . (int)$check_customer['customers_default_address_id'] . "'");
        $check_country = tep_db_fetch_array($check_country_query);

        $customer_id = $check_customer['customers_id'];
        $customer_default_address_id = $check_customer['customers_default_address_id'];
        $customer_first_name = $check_customer['customers_firstname'];
        $customer_country_id = $check_country['entry_country_id'];
        $customer_zone_id = $check_country['entry_zone_id'];
        tep_session_register('customer_id');
        tep_session_register('customer_default_address_id');
        tep_session_register('customer_first_name');
        tep_session_register('customer_country_id');
        tep_session_register('customer_zone_id');

        $order->customer['id'] = $customer_id;

        tep_db_query("update " . TABLE_CUSTOMERS_INFO . " set customers_info_date_of_last_logon = now(), customers_info_number_of_logons = customers_info_number_of_logons+1 where customers_info_id = '" . (int)$customer_id . "'");

        $cart->restore_contents();
        $this->set_ec_order_address();
        $this->away_with_you();
      }
    }
    
    /*
     * If automatic account creation is turned off, the user will be deleted as soon as checkout is complete
     */
    function ec_delete_user($cid) {
      global $customer_id, $customers_default_address_id, $customer_first_name, $customer_country_id, $customer_zone_id, $comments;
      tep_session_unregister('customer_id');
      tep_session_unregister('customer_default_address_id');
      tep_session_unregister('customer_first_name');
      tep_session_unregister('customer_country_id');
      tep_session_unregister('customer_zone_id');
      tep_session_unregister('comments');

      tep_db_query("delete from " . TABLE_ADDRESS_BOOK . " where customers_id = '" . (int)$cid . "'");
      tep_db_query("delete from " . TABLE_CUSTOMERS . " where customers_id = '" . (int)$cid . "'");
      tep_db_query("delete from " . TABLE_CUSTOMERS_INFO . " where customers_info_id = '" . (int)$cid . "'");
      tep_db_query("delete from " . TABLE_CUSTOMERS_BASKET . " where customers_id = '" . (int)$cid . "'");
      tep_db_query("delete from " . TABLE_CUSTOMERS_BASKET_ATTRIBUTES . " where customers_id = '" . (int)$cid . "'");
      tep_db_query("delete from " . TABLE_WHOS_ONLINE . " where customer_id = '" . (int)$cid . "'");
    }
    
    function wpp_add_PDI($details) {
      $output = '';
      
      $output  = '<PaymentDetailsItem>';
      $output .= '<Name>' . $details['name'] . '</Name>';
      $output .= '<Amount currencyID="' . $details['currency'] . '">' . number_format($details['amount'], 2, '.', '') . '</Amount>';
      $output .= '<Number>' . ($details['model'] == '' ? '-' : $details['model']) . '</Number>';
      $output .= '<Quantity>' . $details['qty'] . '</Quantity>';
      $output .= '</PaymentDetailsItem>';
      
      return $output;
    }
    
    /*
     * This function generates the PaymentDetailsItem type for doDirectPayment and doExpressCheckout
     */
    function wpp_generate_PDI($item_total) {
      global $order, $cart, $cc_id, $shipping, $currencies, $order_totals;
      $order_total = array();
      $total = 0;
      $currency = $this->wpp_get_currency();
      
      //If the merchant has a different currency selected for this module
      //than they do as a default for the store, all prices will be converted
      $currency_value = $currencies->get_value($this->wpp_get_currency());
      
      if (count($order_totals) < 1) {
        $this->away_with_you(MODULE_PAYMENT_PAYPAL_DP_BUG_1629);
      }

      foreach ($order_totals as $ot) {
        $order_total[$ot['code']] = $ot['value'];
      }
      
      $output = '';

      if (is_array($order->products)) {
        foreach ($order->products as $o) {
          $qty = $o['qty'];
          $price = tep_add_tax($o['final_price'] * $currency_value, $o['tax']);
          $name = $o['name'];
          
          // PayPal doesn't like fractional quantities, so force non-integer quantities to 1 and the price to be the extended price          
          if ($qty != (int)$qty) {
            $name .= ' (' . $qty . ' @ ' . $price . ')';
            $price = $price * $qty;
            $qty = '1';
          }
          
          $output .= $this->wpp_add_PDI(array('name' => $this->wpp_xml_safe($name),
                                              'currency' => $currency,
                                              'amount' => $price,
                                              'model' => $this->wpp_xml_safe($o['model']),
                                              'qty' => (int)$qty));
          $total += number_format(round($price, 2) * $qty, 2, '.', '');
        }
      }
      //CCGV integration - grrrrrrrrr
      //Coupons
      if ($order_total['ot_coupon'] > 0) {
        $coupon_query = tep_db_query("select coupon_type, coupon_amount, coupon_code, coupon_type from " . TABLE_COUPONS . " where coupon_id = '" . (int)$_SESSION['cc_id'] . "'");
        if (tep_db_num_rows($coupon_query) > 0) {
          $coupon = tep_db_fetch_array($coupon_query);
          
          //Coupon is percentage based.
          if ($coupon['coupon_type'] == 'P') {
            $coupon_details = '(' . round($coupon['coupon_amount'], 2) . '% off)';
            
    	  } elseif ($coupon['coupon_type'] == 'S' && $coupon['coupon_map'] == 0) {
            $coupon_details = '($-' . number_format(round($order->info['shipping_cost'] * $currency_value, 2), 2, '.', '') . ')';
          } elseif ($coupon['coupon_type'] == 'S' && $coupon['coupon_map'] == 1) {
            if (!tep_session_is_registered('map_only')) tep_session_register('map_only');
            $_SESSION['map_only'] = true;

            $total_weight = $cart->show_weight(true);

            $shipping_method = explode('_', $shipping['id']);
            
            $ship = new $shipping_method[0]();
            $totals = $ship->quote($shipping_method[1]);
            
            if (is_array($totals['methods'])) {
              $coupon_details = '($-' . number_format(round($totals['methods'][0]['cost'] * $currency_value, 2), 2, '.', '') . ')';
            }
            
            tep_session_unregister('map_only');
            
            $total_weight = $cart->show_weight(false);
          } else {
            $coupon_details = '($-' . number_format(round($coupon['coupon_amount'] * $currency_value, 2), 2, '.', '') . ')';
          }

          $coupon_total = number_format(round($order_total['ot_coupon'] * $currency_value, 2), 2, '.', '');
          $output .= $this->wpp_add_PDI(array('name' => 'Discount Coupon ' . $this->wpp_xml_safe($coupon_details),
                                              'currency' => $currency,
                                              'amount' => '-' . $coupon_total,
                                              'model' => $coupon['coupon_code'],
                                              'qty' => '1'));
          $total -= $coupon_total;
        }
      } elseif ($order_total['ot_coupon'] < 0) {
        //Support for Ingo's Discount Coupon contribution
        $output .= $this->wpp_add_PDI(array('name' => 'Discount Coupon ' . round($order_total['ot_coupon'] * $currency_value, 2),
                                            'currency' => $currency,
                                            'amount' => $order_total['ot_coupon'] * $currency_value,
                                            'model' => 'Discount Coupon',
                                            'qty' => '1'));
                                     
        $total -= round(abs($order_total['ot_coupon']) * $currency_value, 2);
      }
      
      //Gift Vouchers
      if ($order_total['ot_gv'] > 0) {
        $output .= $this->wpp_add_PDI(array('name' => 'Gift Voucher of $' . round($order_total['ot_gv'] * $currency_value, 2),
                                            'currency' => $currency,
                                            'amount' => $order_total['ot_gv'] * $currency_value,
                                            'model' => 'Gift Voucher',
                                            'qty' => '1'));
                                     
        $total -= round($order_total['ot_gv'] * $currency_value, 2);
      }
      //End grrrrrrrrrrr
      
      //kgt Discount Coupon Support
      if (is_object($order->coupon)) {
        $discount_total = 0;
        
        foreach ($order->coupon->applied_discount as $ad) {
          $discount_total += $ad;
        }
      
        $coupon_total = number_format(round($discount_total * $currency_value, 2), 2, '.', '');
        $output .= $this->wpp_add_PDI(array('name' => 'Discount Coupon (' . $this->wpp_xml_safe($order->coupon->coupon['coupons_id']) . ')',
                                            'currency' => $currency,
                                            'amount' => '-' . $coupon_total,
                                            'model' => $order->coupon->coupon['coupons_id'],
                                            'qty' => '1'));
        $total -= $coupon_total;
      }
      
      //OSC Programming Lesson #224: tep_round() is not reliable
      $item_total = round($item_total, 2);
      
      //If there is a discrepancy in the order total, fix it here
      //$item_total doesn't need to be converted because it's sent converted
      if ($total != $item_total) {
        $new_total = round($item_total - $total, 2);
        $output .= $this->wpp_add_PDI(array('name' => 'Order Total Discrepancy',
                                            'currency' => $currency,
                                            'amount' => $new_total,
                                            'model' => '',
                                            'qty' => '1'));
        $total += $new_total;
      }
      
      return array($total, $output);
    }
    
    /*
     * Escape evil characters for an XML output
     */
    function wpp_xml_safe($str) {
      //The 5 evil characters in XML
      $str = str_replace('<', '&lt;', $str);
      $str = str_replace('>', '&gt;', $str);
      $str = str_replace('&', '&amp;', $str);
      $str = str_replace("'", '&apos;', $str);
      $str = str_replace('"', '&quot;', $str);

      return $str;
    }

    /*
     * Charge cards or complete the Express Checkout process
     */
    function before_process() {
      global $order, $order_totals, $currencies;
      require_once(DIR_FS_CATALOG . DIR_WS_CLASSES . 'cc_validation.php');

////////////////////////////////////////////////////////////////////////////
///////          Process Common Order Information                  /////////
////////////////////////////////////////////////////////////////////////////
      $this->trans_type = 'CHARGE';
      
      if (count($order_totals) < 1) {
        $this->away_with_you(MODULE_PAYMENT_PAYPAL_DP_BUG_1629);
      }
      
      //Get order_total values
      $order_total = array();
      foreach ($order_totals as $ot) {
        $order_total[$ot['code']] += $ot['value'];
      }

      $order_info = array();
      
      //If the merchant has a different currency selected for this module
      //than they do as a default for the store, all prices will be converted
      $currency_value = $currencies->get_value($this->wpp_get_currency());

      $order_info['PAYPAL_ORDER_TOTAL'] = number_format($order_total['ot_total'] * $currency_value, 2, '.', '');
      $this->total_amount = $order_info['PAYPAL_ORDER_TOTAL'];
      
      $order_info['PAYPAL_ORDER_DESCRIPTION'] = 'Order placed on ' . date("F j, Y, g:i a") . ' by ' . $order->customer['firstname'] . ' ' . $order->customer['lastname'] . ' (ID: ' . $_SESSION['customer_id'] . ')';
      $order_info['PAYPAL_CUSTOM'] = 'Phone: ' . $order->customer['telephone'] . ' -- Email: ' . $order->customer['email_address'];
           
      //The shipping total must be under $10,000.  I've removed the check that would
      //set the shipping total at $10,000 if it was over, but that didn't make any sense
      //as the totals would be off, causing other errors.  Just don't ship anything that'll cost more than $10k
      $order_info['PAYPAL_SHIPPING_TOTAL'] = round($order_total['ot_shipping'] * $currency_value, 2);
      $order_info['PAYPAL_HANDLING_TOTAL'] = '';
      
      if (DISPLAY_PRICE_WITH_TAX == 'true') {
        $order_info['PAYPAL_TAX_TOTAL'] = '';
      } else {
        $order_info['PAYPAL_TAX_TOTAL'] = round($order_total['ot_tax'] * $currency_value, 2);
      }
      
      $order_total_check = $order_info['PAYPAL_ORDER_TOTAL'] - $order_info['PAYPAL_SHIPPING_TOTAL'] - $order_info['PAYPAL_HANDLING_TOTAL'];
      
      if (!(DISPLAY_PRICE_WITH_TAX == 'true')) {
        $order_total_check -= $order_info['PAYPAL_TAX_TOTAL'];
      }
      
      $pdi = $this->wpp_generate_PDI($order_total_check);
      $order_info['PAYPAL_ITEM_TOTAL'] = $pdi[0];
      $order_info['PAYMENT_DETAILS_ITEM'] = $pdi[1];
      
      /* 
       * Kludge to avoid error if person is purchasing a product with no price
       * but still getting charged shipping.
       */
      if ($order_info['PAYPAL_ITEM_TOTAL'] <= 0 && $order_total['ot_shipping'] > 0) {
        $order_info['PAYMENT_DETAILS_ITEM'] .= $this->wpp_add_PDI(array('name' => 'Shipping',
                                                                        'currency' => $this->wpp_get_currency(),
                                                                        'amount' => $order_info['PAYPAL_SHIPPING_TOTAL'],
                                                                        'model' => '',
                                                                        'qty' => '1'));
                                                                        
        $order_info['PAYPAL_ITEM_TOTAL'] += $order_info['PAYPAL_SHIPPING_TOTAL'];                                                                
        $order_info['PAYPAL_SHIPPING_TOTAL'] = 0;
      }
      
      if (strpos($order->content_type,'virtual') === false) {
        $order_info['PAYPAL_SHIPPING_NAME'] = trim($order->delivery['firstname'] . ' ' . $order->delivery['lastname']);
        $order_info['PAYPAL_SHIPPING_ADDRESS1'] = $order->delivery['street_address'];
        $order_info['PAYPAL_SHIPPING_ADDRESS2'] = $order->delivery['suburb'];
        $order_info['PAYPAL_SHIPPING_CITY'] = $order->delivery['city'];
        $order_info['PAYPAL_SHIPPING_STATE'] = $this->wpp_fix_state_for_paypal($order->delivery['country']['iso_code_2'] ,$order->delivery['state']);
        $order_info['PAYPAL_SHIPPING_ZIP'] = $order->delivery['postcode'];
        $order_info['PAYPAL_SHIPPING_COUNTRY'] = $order->delivery['country']['iso_code_2'];
      } else {
        $order_info['PAYPAL_SHIPPING_NAME'] = trim($order->billing['firstname'] . ' ' . $order->billing['lastname']);
        $order_info['PAYPAL_SHIPPING_ADDRESS1'] = $order->billing['street_address'];
        $order_info['PAYPAL_SHIPPING_ADDRESS2'] = $order->billing['suburb'];
        $order_info['PAYPAL_SHIPPING_CITY'] = $order->billing['city'];
        $order_info['PAYPAL_SHIPPING_STATE'] = $this->wpp_fix_state_for_paypal($order->billing['country']['iso_code_2'] ,$order->billing['state']);
        $order_info['PAYPAL_SHIPPING_ZIP'] = $order->billing['postcode'];
        $order_info['PAYPAL_SHIPPING_COUNTRY'] = $order->billing['country']['iso_code_2'];
      }
      
      $order_info['PAYPAL_NOTIFY_URL'] = ''; //MODULE_PAYMENT_PAYPAL_EC_IPN_URL;
      $order_info['PAYPAL_INVOICE_ID'] = '';

////////////////////////////////////////////////////////////////////////////
///////          Express Checkout Processing Portion               /////////
////////////////////////////////////////////////////////////////////////////
      if (tep_session_is_registered('paypal_ec_token') && tep_session_is_registered('paypal_ec_payer_id') && tep_session_is_registered('paypal_ec_payer_info')) {
        
        /*
         * The reason for this kludge is because of a conflict where the store owner
         * wants to use the address from the store, but a customer is checking out without logging in.
         * We don't know ahead of time if the user is an existing user or new user, so the address_override
         * variable doesn't get set because if they're a new customer, we need that address.
         * The only effect of this is that the address in the paypal receipt email is different than the order
         * email.
         */
        if (MODULE_PAYMENT_PAYPAL_EC_ADDRESS_OVERRIDE == 'Store' 
            && $order->shipping['street_address'] != $_SESSION['paypal_ec_payer_info']['ship_street_1']) {
          $order_info['PAYPAL_SHIPPING_ADDRESS1'] = $_SESSION['paypal_ec_payer_info']['ship_street_1'];
          $order_info['PAYPAL_SHIPPING_ADDRESS2'] = $_SESSION['paypal_ec_payer_info']['ship_street_2'];
          $order_info['PAYPAL_SHIPPING_CITY'] = $_SESSION['paypal_ec_payer_info']['ship_city'];
          $order_info['PAYPAL_SHIPPING_STATE'] = $_SESSION['paypal_ec_payer_info']['ship_state'];
          $order_info['PAYPAL_SHIPPING_ZIP'] = $_SESSION['paypal_ec_payer_info']['ship_postal_code'];
          $order_info['PAYPAL_SHIPPING_COUNTRY'] = $_SESSION['paypal_ec_payer_info']['ship_country'];
        }
        
        $order_info['PAYPAL_TOKEN'] = $_SESSION['paypal_ec_token'];
        $order_info['PAYPAL_PAYER_ID'] = $_SESSION['paypal_ec_payer_id'];

        $response = $this->wpp_execute_transaction('doExpressCheckout', $order_info);

        //Response processing
        if(!is_array($response) || ($response['DoExpressCheckoutPaymentResponse'][0]['Ack'] != 'Success' && $response['DoExpressCheckoutPaymentResponse'][0]['Ack'] != 'SuccessWithWarning')) {
          if ($this->enableDebugging == '1') {
            //Send the store owner a complete dump of the transaction
            $spacer =           "---------------------------------------------------------------------\r\n";
            
            $dp_dump_title =    "-------------------------------EC_DUMP-------------------------------\r\n";
            $dp_dump_title .=   "------------This is the information that was sent to PayPal----------\r\n";
            $final_req_title =  "-------------------------------FINAL_REQ-----------------------------\r\n";
            $final_req_title .= "-------------------This is the response from PayPal------------------\r\n";
            
            $final_req_dump = print_r($response, true);
            
            tep_mail(STORE_OWNER, 
                     $this->debug_email, 
                     'PayPal Error Dump', 
                     "In function: before_process() - Express Checkout\r\n" . 
                     "Did first contact attempt return error? " . ($error_occurred ? "Yes" : "Nope") . " \r\n" . 
                     $spacer . $dp_dump_title . $spacer . $this->last_data . "\r\n\r\n",
                     $spacer . $final_req_title . $spacer . $final_req_dump . "\r\n\r\n",
                     STORE_OWNER, 
                     $this->debug_email);
          }
          
          if ($response['DoExpressCheckoutPaymentResponse'][0]['Errors'][0]['ErrorCode'] == '') {
            $this->away_with_you(MODULE_PAYMENT_PAYPAL_EC_TEXT_DECLINED . 'No response from PayPal<br>No response was received from PayPal.  Please contact the store owner for assistance.', true);
          } else {
            $this->away_with_you(MODULE_PAYMENT_PAYPAL_DP_TEXT_ERROR . $this->return_transaction_errors($response['DoExpressCheckoutPaymentResponse'][0]['Errors']), true);
          }
        } else {
          $details = $response['DoExpressCheckoutPaymentResponse'][0]['DoExpressCheckoutPaymentResponseDetails'][0]['PaymentInfo'][0];
          $this->transaction_log['payment_type'] = $details['PaymentType'];
          $this->transaction_log['transaction_id'] = $details['TransactionID'];
          $this->transaction_log['payment_status'] = $details['PaymentStatus'];
          $this->transaction_log['avs'] = '';
          $this->transaction_log['cvv2'] = '';

          if ($details['PaymentStatus'] == 'Pending') {
            $this->transaction_log['transaction_msgs'] = $details['PendingReason'];
            $order->info['order_status'] = 1;
          }
          
          if (strtoupper($this->transaction_log['payment_status']) == 'PENDING') {
            if (MODULE_PAYMENT_PAYPAL_DP_PENDING_ORDER_STATUS_ID > 0) {
              $order->info['order_status'] = MODULE_PAYMENT_PAYPAL_DP_PENDING_ORDER_STATUS_ID;
            }
          } elseif (strtoupper($this->transaction_log['payment_status']) == 'COMPLETED') {
            if (MODULE_PAYMENT_PAYPAL_DP_COMPLETED_ORDER_STATUS_ID > 0) {
              $order->info['order_status'] = MODULE_PAYMENT_PAYPAL_DP_COMPLETED_ORDER_STATUS_ID;
            }
          }
        }
////////////////////////////////////////////////////////////////////////////
///////            Direct Payment Processing Portion               /////////
////////////////////////////////////////////////////////////////////////////
      } else {
        $cc_type = $_POST['paypalwpp_cc_type'];
        $cc_number = preg_replace('/[^0-9]/i', '', $_POST['paypalwpp_cc_number']);
        $cc_checkcode = preg_replace('/[^0-9]/i', '', $_POST['paypalwpp_cc_checkcode']);
        $cc_first_name = $_POST['paypalwpp_cc_firstname'];
        $cc_last_name = $_POST['paypalwpp_cc_lastname'];
        $cc_owner_ip = $_SERVER['REMOTE_ADDR'];
        $cc_expdate_month = preg_replace('/[^0-9]/i', '', $_POST['paypalwpp_cc_expires_month']);
        $cc_expdate_year = preg_replace('/[^0-9]/i', '', $_POST['paypalwpp_cc_expires_year']);

        $cc_validation = new cc_validation();
        $result = $cc_validation->validate($cc_number, $cc_expdate_month, $cc_expdate_year);

        $error = '';
        if ($result === -1) {
          $error = sprintf(TEXT_CCVAL_ERROR_UNKNOWN_CARD, substr($cc_validation->cc_number, 0, 4));
        } elseif ($result > -5 && $result < -1) {
          $error = TEXT_CCVAL_ERROR_INVALID_DATE;
        } elseif ($result < 1) {
          $error = TEXT_CCVAL_ERROR_INVALID_NUMBER;
        }
        
        if ($error != '') {
          $this->away_with_you($error, false, FILENAME_CHECKOUT_PAYMENT);
          return false;
        }
        
        if (strlen($cc_expdate_year) < 4) $cc_expdate_year = '20'.$cc_expdate_year;

        /*
         * If the cc type sent in the post var isn't any one of the 
         * accepted cards, send them back to the payment page
         * This error should never come up unless the visitor is  
         * playing with the post vars or they didn't get passed to 
         * checkout_confirmation.php
        */
        if (!in_array($cc_type, array('Visa', 'MasterCard', 'Discover', 'Amex', 'Maestro', 'Solo'))) {
          $this->away_with_you(MODULE_PAYMENT_PAYPAL_DP_TEXT_BAD_CARD, false, FILENAME_CHECKOUT_PAYMENT);
          return false;
        }
        
        //If they're still here, and awake, set some of the order object's variables
        //Storage of expiry date commented out for PCI DSS compliance
        $order->info['cc_type'] = $cc_type;
        $order->info['cc_number'] = str_repeat('X', (strlen($cc_number) - 4)) . substr($cc_number, -4);
        $order->info['cc_owner'] = $cc_first_name . ' ' . $cc_last_name;
//        $order->info['cc_expires'] = $cc_expdate_month . substr($cc_expdate_year, -2);

        //These have to be set to empty values so that the placeholders in the XML will get replaced
        $order_info['PAYPAL_CC_UK_DATA'] = '';
        
        //Maestro/Solo specific fields
        if (MODULE_PAYMENT_PAYPAL_DP_UK_ENABLED == 'Yes') {
          $order_info['PAYPAL_CC_UK_DATA']  = '<StartMonth>' . substr(preg_replace('/[^0-9]/i', '', $_POST['paypalwpp_cc_start_month']), 0, 2) . '</StartMonth>';
          $order_info['PAYPAL_CC_UK_DATA'] .= '<StartYear>' . substr(preg_replace('/[^0-9]/i', '', $_POST['paypalwpp_cc_start_year']), 0, 4) . '</StartYear>';
          if ($_POST['paypalwpp_cc_issue_number'] != '') {
            $order_info['PAYPAL_CC_UK_DATA'] .= '<IssueNumber>' . substr(preg_replace('/[^0-9]/i', '', $_POST['paypalwpp_cc_issue_number']), 0, 2) . '</IssueNumber>';
          }
        }
        
        /* Begin optional, unused data fields */
        $order_info['PAYPAL_BUTTON_SOURCE'] = '';
        /* End optional, unused data fields */
      
        //Billing information
        $order_info['PAYPAL_FIRST_NAME'] = $cc_first_name;
        $order_info['PAYPAL_LAST_NAME'] = $cc_last_name;
        $order_info['PAYPAL_ADDRESS1'] = $order->billing['street_address'];
        $order_info['PAYPAL_ADDRESS2'] = $order->billing['suburb'];
        $order_info['PAYPAL_CITY'] = $order->billing['city'];
        $order_info['PAYPAL_STATE'] = $this->wpp_fix_state_for_paypal($order->billing['country']['iso_code_2'], $order->billing['state']);
        $order_info['PAYPAL_ZIP'] = $order->billing['postcode'];
        $order_info['PAYPAL_COUNTRY'] = $order->billing['country']['iso_code_2'];
        $order_info['PAYPAL_BUYER_EMAIL'] = $order->customer['email_address'];

        //Credit card details
        if ($cc_type == 'Maestro') {
          $order_info['PAYPAL_CC_TYPE'] = 'Switch';
        } else {
          $order_info['PAYPAL_CC_TYPE'] = $cc_type;
        }
        
        $order_info['PAYPAL_CC_NUMBER'] = $cc_number;
        $order_info['PAYPAL_CC_EXP_MONTH'] = $cc_expdate_month;
        $order_info['PAYPAL_CC_EXP_YEAR'] = $cc_expdate_year;
        $order_info['PAYPAL_CC_CVV2'] = $cc_checkcode;

        $this->cardinal_centinel_before_process(&$order_info);
        //Make the call and (hopefully) return an array of information
        $final_req = $this->wpp_execute_transaction('doDirectPayment', $order_info);

        //If the transaction wasn't a success, start the error checking
        if (strpos($final_req['DoDirectPaymentResponse'][0]['Ack'], 'Success') === false) {
          $error_occurred = false;
          $ts_result = false;
          //If an error or failure occurred, don't do a transaction check
          //The transaction search is only for if we didn't receive a understandable response
          //and don't want to charge the customer multiple times
          if (strpos($final_req['DoDirectPaymentResponse'][0]['Ack'], 'Error') !== false || strpos($final_req['DoDirectPaymentResponse'][0]['Ack'], 'Failure') !== false) {
            //If PayPal said to retry (code 10207), try again
            if ($final_req['DoDirectPaymentResponse'][0]['Errors'][0]['ErrorCode'] == '10207' && $this->max_retries > 0) {
              $this->max_retries--;
              $this->before_process();
              return false;
            } else {
              $error_occurred = true;
              $error_log = $this->return_transaction_errors($final_req['DoDirectPaymentResponse'][0]['Errors']);
            }
          } elseif ($final_req['faultcode'] != '') {
            //There was an error in our request syntax
            //This should never occur in production
            $error_occurred = true;
            $error_log = $this->return_transaction_errors($final_req['faultstring']);
          } else {
            //Do a transaction search to make sure the connection didn't just timeout
            //It searches by email of payer and amount.  That should be accurate enough
           
            $transaction_info = array();
           
            //Set to one day ago to avoid any time zone issues.  This does introduce a possible bug, but 
            //the chance of the same person having the exact same total and paypal non responding within one day is pretty unlikely
            $transaction_info['PAYPAL_START_DATE'] = date('Y-m-d', mktime(0, 0, 0, date("m"), date("d")-1,  date("Y"))) . 'T00:00:00-0700';
            $transaction_info['PAYPAL_PAYER'] = $order->customer['email_address'];
            $transaction_info['PAYPAL_AMOUNT'] = number_format($order->info['total'], 2, '.', '');
            $ts_req = $this->wpp_execute_transaction('transactionSearch', $transaction_info);

            //If a matching transaction was found, tell us
            if(is_array($ts_req['TransactionSearchResponse'][0]['PaymentTransactions'])) {
              $ts_result = true;
            } else {
              $error_log = $this->return_transaction_errors($ts_req['TransactionSearchResponse'][0]['Errors']);
              $ts_result = false;
            }
          }

          if (!$error_occurred && $ts_result) {
            $return_codes = array($ts_req['TransactionSearchResponse'][0]['TransactionID'], 'No AVS Code Returned', 'No CVV2 Code Returned');
          } else {

            if ($this->enableDebugging == '1') {
              //Send the store owner a complete dump of the transaction
              
              $spacer =           "---------------------------------------------------------------------\r\n";
              
              $dp_dump_title =    "-------------------------------DP_DUMP-------------------------------\r\n";
              $dp_dump_title .=   "------------This is the information that was sent to PayPal----------\r\n";
              $final_req_title =  "-------------------------------FINAL_REQ-----------------------------\r\n";
              $final_req_title .= "-------------------This is the response from PayPal------------------\r\n";
              $final_req_dump = print_r($final_req, true);
              
              //Remove sensitive information
              $this->last_data = str_replace(MODULE_PAYMENT_PAYPAL_DP_API_USERNAME, str_repeat('X', strlen(MODULE_PAYMENT_PAYPAL_DP_API_USERNAME)), $this->last_data);
              $this->last_data = str_replace(MODULE_PAYMENT_PAYPAL_DP_API_PASSWORD, str_repeat('X', strlen(MODULE_PAYMENT_PAYPAL_DP_API_PASSWORD)), $this->last_data);
              $this->last_data = str_replace($order_info['PAYPAL_CC_NUMBER'], str_repeat('X', strlen($order_info['PAYPAL_CC_NUMBER'])), $this->last_data);
              $this->last_data = str_replace($order_info['PAYPAL_CC_CVV2'], str_repeat('X', strlen($order_info['PAYPAL_CC_CVV2'])), $this->last_data);
              
              $final_req_dump = str_replace(MODULE_PAYMENT_PAYPAL_DP_API_USERNAME, str_repeat('X', strlen(MODULE_PAYMENT_PAYPAL_DP_API_USERNAME)), $final_req_dump);
              $final_req_dump = str_replace(MODULE_PAYMENT_PAYPAL_DP_API_PASSWORD, str_repeat('X', strlen(MODULE_PAYMENT_PAYPAL_DP_API_PASSWORD)), $final_req_dump);
              $final_req_dump = str_replace($order_info['PAYPAL_CC_NUMBER'], str_repeat('X', strlen($order_info['PAYPAL_CC_NUMBER'])), $final_req_dump);
              $final_req_dump = str_replace($order_info['PAYPAL_CC_CVV2'], str_repeat('X', strlen($order_info['PAYPAL_CC_CVV2'])), $final_req_dump);
              
              $ts_req_title =     "---------------------------------TS_REQ------------------------------\r\n";
              $ts_req_title .=    "--------Results of the transaction search if it was executed---------\r\n";
              $ts_req_dump = print_r($ts_req, true);
              
              //Remove sensitive information
              $ts_req_dump = str_replace(MODULE_PAYMENT_PAYPAL_DP_API_USERNAME, str_repeat('X', strlen(MODULE_PAYMENT_PAYPAL_DP_API_USERNAME)), $ts_req_dump);
              $ts_req_dump = str_replace(MODULE_PAYMENT_PAYPAL_DP_API_PASSWORD, str_repeat('X', strlen(MODULE_PAYMENT_PAYPAL_DP_API_PASSWORD)), $ts_req_dump);
              
              $this->last_data = strtr($this->last_data, '<>', '[]');
              
              tep_mail(STORE_OWNER, 
                       STORE_OWNER_EMAIL_ADDRESS, 
                       'PayPal Error Dump', 
                       "In function: before_process() - Direct Payment\r\n" . 
                       "Did first contact attempt return error? " . ($error_occurred ? "Yes" : "Nope") . "\r\n" .
                       $spacer . $dp_dump_title . $spacer . $this->last_data . $spacer . "\r\n\r\n" . 
                       $final_req_title . $spacer . $final_req_dump . "\r\n\r\n" . 
                       $spacer . $ts_req_title . $spacer . $ts_req_dump, 
                       STORE_OWNER, 
                       STORE_OWNER_EMAIL_ADDRESS);
            }
            
            //If the return is empty
            if (!tep_not_null($error_log)) {
              $this->away_with_you(MODULE_PAYMENT_PAYPAL_DP_TEXT_DECLINED . 'No response from the payment processor<br>No response was received from the payment processor.  Please contact the store owner for assistance.', false, FILENAME_CHECKOUT_PAYMENT);
            } else {
              $this->away_with_you(MODULE_PAYMENT_PAYPAL_DP_TEXT_DECLINED . $error_log, false, FILENAME_CHECKOUT_PAYMENT);
            }
          }
        } else {
          $return_codes = array($final_req['DoDirectPaymentResponse'][0]['TransactionID'], $final_req['DoDirectPaymentResponse'][0]['AVSCode'], $final_req['DoDirectPaymentResponse'][0]['CVV2Code']);
        }
        
        
        $this->transaction_log['transaction_id'] = $return_codes[0];
        $this->transaction_log['payment_status'] = $details['PaymentStatus'];

        $ret_avs = $return_codes[1];
        $ret_cvv2 = $return_codes[2];

        /*
         * Get transaction status details from PayPal. Unlike Express Checkout, 
         * this requires another transaction to get the details.  This is used 
         * for the Authorization/Capture mode of operation
         * Addition by Glen Hoag (Steve Dallas)
         */
    	  $transaction_info['PAYPAL_TRANSACTION_ID'] = $this->transaction_log['transaction_id'];

    	  $response = $this->wpp_execute_transaction('getTransactionDetails', $transaction_info);

    	  if (is_array($response)) {
          $transaction_node = $response['GetTransactionDetailsResponse'][0]['PaymentTransactionDetails'][0]['PaymentInfo'][0];
          $this->transaction_log['payment_status'] = $transaction_node['PaymentStatus'];
          $this->transaction_log['payment_type'] = $transaction_node['PaymentType'];
          if ($this->transaction_log['payment_status'] == 'Pending') {
            $this->transaction_log['transaction_msgs'] = $transaction_node['PendingReason'];
            $order->info['order_status'] = 1;
          }
    	  } else {
    	    $this->transaction_log['payment_status'] == 'UNKNOWN';
          $this->transaction_log['payment_type'] = 'UNKNOWN';
    	  }
        
        if (strtoupper($this->transaction_log['payment_status']) == 'PENDING' || strtoupper($this->transaction_log['payment_status']) == 'UNKNOWN') {
          if (MODULE_PAYMENT_PAYPAL_DP_PENDING_ORDER_STATUS_ID > 0) {
            $order->info['order_status'] = MODULE_PAYMENT_PAYPAL_DP_PENDING_ORDER_STATUS_ID;
          }
        } elseif (strtoupper($this->transaction_log['payment_status']) == 'COMPLETED') {
          if (MODULE_PAYMENT_PAYPAL_DP_COMPLETED_ORDER_STATUS_ID > 0) {
            $order->info['order_status'] = MODULE_PAYMENT_PAYPAL_DP_COMPLETED_ORDER_STATUS_ID;
          }
        }

        switch ($ret_avs) {
        case 'A':
          $ret_avs_msg = 'Address Address only (no ZIP)';
          break;
        case 'B':
          $ret_avs_msg = 'International “A” Address only (no ZIP)';
          break;
        case 'C':
          $ret_avs_msg = 'International “N” None';
          break;
        case 'D':
          $ret_avs_msg = 'International “X” Address and Postal Code';
          break;
        case 'E':
          $ret_avs_msg = 'Not allowed for MOTO (Internet/Phone)';
          break;
        case 'F':
          $ret_avs_msg = 'UK-specific “X” Address and Postal Code';
          break;
        case 'G':
          $ret_avs_msg = 'Global Unavailable Not applicable';
          break;
        case 'I':
          $ret_avs_msg = 'International Unavailable Not applicable';
          break;
        case 'N':
          $ret_avs_msg = 'No None';
          break;
        case 'P':
          $ret_avs_msg = 'Postal (International “Z”) Postal Code only (no Address)';
          break;
        case 'R':
          $ret_avs_msg = 'Retry Not applicable';
          break;
        case 'S':
          $ret_avs_msg = 'Service not Supported Not applicable';
          break;
        case 'U':
          $ret_avs_msg = 'Unavailable Not applicable';
          break;
        case 'W':
          $ret_avs_msg = 'Whole ZIP Nine-digit ZIP code (no Address)';
          break;
        case 'X':
          $ret_avs_msg = 'Exact match Address and nine-digit ZIP code';
          break;
        case 'Y':
          $ret_avs_msg = 'Yes Address and five-digit ZIP';
          break;
        case 'Z':
          $ret_avs_msg = 'ZIP Five-digit ZIP code (no Address)';
          break;
        default:
          $ret_avs_msg = 'Error';
        }

        switch ($ret_cvv2) {
        case 'M':
          $ret_cvv2_msg = 'Match CVV2';
          break;
        case 'N':
          $ret_cvv2_msg = 'No match None';
          break;
        case 'P':
          $ret_cvv2_msg = 'Not Processed Not applicable';
          break;
        case 'S':
          $ret_cvv2_msg = 'Service not Supported Not applicable';
          break;
        case 'U':
          $ret_cvv2_msg = 'Unavailable Not applicable';
          break;
        case 'X':
          $ret_cvv2_msg = 'No response Not applicable';
          break;
        default:
          $ret_cvv2_msg = 'Error';
          break;
        }
        $this->transaction_log['avs'] = $ret_avs_msg;
        $this->transaction_log['cvv2'] = $ret_cvv2_msg;

        return true;
      }
    }

    /*
     * Called at the end of the order process to store the payment details 
     */
    function after_process() {
      global $insert_id;
      
      $history_query = tep_db_query("SELECT orders_status_history_id as id 
                                     FROM " . TABLE_ORDERS_STATUS_HISTORY . " 
                                     WHERE orders_id = " . (int)$insert_id . " 
                                     LIMIT 1");
                                     
      if (!tep_db_num_rows($history_query)) return false;
      
      $history = tep_db_fetch_array($history_query);
      
      //CHARGE is a less generic keyword
      if (strtoupper($this->transaction_log['transaction_type']) == 'SALE') {
        $this->transaction_log['transaction_type'] = 'CHARGE';
      }
      
      tep_db_query(
        "INSERT INTO " . TABLE_ORDERS_STATUS_HISTORY_TRANSACTIONS . " (" .
        "  `orders_status_history_id`," .
        "  `transaction_id`," .
        "  `transaction_type`," .
        "  `payment_type`," .
        "  `payment_status`," .
        "  `transaction_amount`," .
        "  `module_code`," .
        "  `transaction_avs`," .
        "  `transaction_cvv2`," .
        "  `transaction_msgs`" .
        ") VALUES (" .
          (int)$history['id'] . "," .
        "  '" . tep_db_input($this->transaction_log['transaction_id']) . "'," .
        "  '" . strtoupper($this->transaction_log['transaction_type']) . "'," .
        "  '" . tep_db_input(strtoupper($this->transaction_log['payment_type'])) . "'," .
        "  '" . tep_db_input(strtoupper($this->transaction_log['payment_status'])) . "'," .
          $this->total_amount . "," .
        "  '" . $this->code . "'," .
        "  '" . tep_db_input($this->transaction_log['avs']) . "'," .
        "  '" . tep_db_input($this->transaction_log['cvv2']) . "'," .
        "  '" . tep_db_input($this->transaction_log['transaction_msgs']) . "'" .
        ")"
      );
      
      if (tep_session_is_registered('cardinal_centinel')) tep_session_unregister('cardinal_centinel');
    }

    /*
     * Display error messages
     */
    function get_error() {
      global $language;
      require(DIR_WS_LANGUAGES . $language . '/modules/payment/' . FILENAME_PAYPAL_WPP);

      $error = array('title' => MODULE_PAYMENT_PAYPAL_DP_ERROR_HEADING,
                     'error' => ((isset($_GET['error'])) ? stripslashes(urldecode($_GET['error'])) : MODULE_PAYMENT_PAYPAL_DP_TEXT_CARD_ERROR));

      return $error;
    }
    
    /*
     * This will safely knock off fractional cents in monetary amounts.  For instance,
     * 1.999 becomes 1.99 and 2.011 becomes 2.01.  It's to prevent user-input error.
     */
    function round_amount($amount) {
      if (!is_numeric($amount)) return false;
      
      return number_format(round($amount - 0.005, 2),2,'.','');
    }

    /*
     * Check if this module is installed
     */
    function check() {
      if (!isset($this->_check)) {
        $check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_PAYPAL_DP_STATUS'");
        $this->_check = tep_db_num_rows($check_query);
      }
      return $this->_check;
    }

    /*
     * Install this module
     */
    function install() {
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable this Payment Module', 'MODULE_PAYMENT_PAYPAL_DP_STATUS', 'True', 'Do you want to enable this payment module?', '6', '0', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Debug Mode', 'MODULE_PAYMENT_PAYPAL_DP_DEBUGGING', 'False', 'Would you like to enable debug mode?  A complete dump of failed transactions will be emailed to the store owner.', '6', '1', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Payment Action', 'MODULE_PAYMENT_PAYPAL_DP_PAYMENT_ACTION', 'Sale', 'Sale or Authorization (Capture later)?', '6', '2', 'tep_cfg_select_option(array(\'Sale\', \'Authorization\'), ', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Live or Sandbox API', 'MODULE_PAYMENT_PAYPAL_DP_SERVER', 'live', 'Live: Live transactions<br>Sandbox: For developers and testing', '6', '3', 'tep_cfg_select_option(array(\'live\', \'sandbox\'), ', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('API Certificate', 'MODULE_PAYMENT_PAYPAL_DP_CERT_PATH', '" . DIR_FS_CATALOG . DIR_WS_INCLUDES . "paypal_wpp/cert/cert_key_pem.txt', 'Type in the filename of your API certificate<br>(this must be an ABSOLUTE path)', '6', '4', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('API Username', 'MODULE_PAYMENT_PAYPAL_DP_API_USERNAME', '', 'Your Paypal WPP API Username', '6', '5', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('API Password', 'MODULE_PAYMENT_PAYPAL_DP_API_PASSWORD', '', 'Your Paypal WPP API Password', '6', '6', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Proxy Address', 'MODULE_PAYMENT_PAYPAL_DP_PROXY', '', 'If curl transactions need to go through a proxy, type the address here.  Otherwise, leave it blank.', '6', '7', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('UK Functionality', 'MODULE_PAYMENT_PAYPAL_DP_UK_ENABLED', 'No', 'Enable UK Solo/Switch support?  (PayPal Pro UK account required.  Will not work with US accounts.)', '6', '8', 'tep_cfg_select_option(array(\'Yes\', \'No\'), ', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Require a CVV2 code?', 'MODULE_PAYMENT_PAYPAL_DP_CHECK_CVV2', 'No', 'Require a CVV2 code entered for Direct Payment transactions?  While a good security measure, you should note that not all credit cards have CVV2 numbers and some people might not be able to checkout.', '6', '9', 'tep_cfg_select_option(array(\'Yes\', \'No\'), ', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Express Checkout', 'MODULE_PAYMENT_PAYPAL_EC_ENABLED', 'Yes', 'Would you like to enable the Express Checkout feature?', '6', '10', 'tep_cfg_select_option(array(\'Yes\', \'No\'), ', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Express Checkout: IPN Notification URL', 'MODULE_PAYMENT_PAYPAL_EC_IPN_URL', '" . tep_catalog_href_link(FILENAME_DEFAULT, 'action=express_checkout_ipn', 'SSL') . "', 'This is the address where payment notifications are to be sent.  The address needs to be an https:// address, but the default value is most likely correct.', '6', '11', now())");   
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Express Checkout: Automatic Account Creation', 'MODULE_PAYMENT_PAYPAL_DP_NEW_ACCT_NOTIFY', 'Yes', 'If a visitor is not an existing customer, an account is created for them.  Would you like make it a permanent account and send them an email containing their login information?', '6', '12', 'tep_cfg_select_option(array(\'Yes\', \'No\'), ', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Express Checkout: Address Override', 'MODULE_PAYMENT_PAYPAL_EC_ADDRESS_OVERRIDE', 'PayPal', 'When existing customers in your store use Express Checkout, do you want to use the address returned from PayPal or one in your store?', '6', '13', 'tep_cfg_select_option(array(\'PayPal\', \'Store\'), ', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Express Checkout: Button Placement', 'MODULE_PAYMENT_PAYPAL_DP_BUTTON_PAYMENT_PAGE', 'No', 'Do you want to display the Express Checkout button on the payment page?', '6', '14',  'tep_cfg_select_option(array(\'Yes\', \'No\'), ', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Express Checkout: Verified Accounts Only', 'MODULE_PAYMENT_PAYPAL_DP_REQ_VERIFIED', 'Yes', 'Do you want to limit Express Checkout payments to only verified PayPal account owners? (HIGHLY RECOMMENDED: Yes)', '6', '15',  'tep_cfg_select_option(array(\'Yes\', \'No\'), ', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Express Checkout: Confirmed Address', 'MODULE_PAYMENT_PAYPAL_DP_CONFIRMED', 'Yes', 'Do you want to require that your customers\' shipping address with PayPal is confirmed? (HIGHLY RECOMMENDED: Yes)', '6', '16',  'tep_cfg_select_option(array(\'Yes\', \'No\'), ', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Express Checkout: Display Payment Page', 'MODULE_PAYMENT_PAYPAL_DP_DISPLAY_PAYMENT_PAGE', 'No', 'If someone\'s checking out with Express Checkout, do you want to display the checkout_payment.php page?  The payment options will be hidden.  (Yes, if you have CCGV installed)', '6', '17',  'tep_cfg_select_option(array(\'Yes\', \'No\'), ', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Express Checkout: Page Style', 'MODULE_PAYMENT_PAYPAL_EC_PAGE_STYLE', '', 'If you have a page style you\'d like your EC customers to see, enter it here.', '6', '18', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Transaction Currency', 'MODULE_PAYMENT_PAYPAL_DP_CURRENCY', 'USD', 'The currency to use for credit card transactions.  If your customer checks out with a different currency, it will be converted to the currency you have selected here.<br><b><u>The currency you select MUST exist in Localization -> Currencies!</u></b>', '6', '19', 'tep_cfg_select_option(array(\'AUD\', \'CAD\', \'EUR\', \'GBP\', \'JPY\', \'USD\'), ', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sort order of display.', 'MODULE_PAYMENT_PAYPAL_DP_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '20', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Payment Zone', 'MODULE_PAYMENT_PAYPAL_DP_ZONE', '0', 'If a zone is selected, only enable this payment method for that zone.', '6', '21', 'tep_get_zone_class_title', 'tep_cfg_pull_down_zone_classes(', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Pending Order Status', 'MODULE_PAYMENT_PAYPAL_DP_PENDING_ORDER_STATUS_ID', '0', 'When the payment status is reported as \"Pending,\" what should the order status be?', '6', '22', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Completed Order Status', 'MODULE_PAYMENT_PAYPAL_DP_COMPLETED_ORDER_STATUS_ID', '0', 'When the payment status is reported as \"Completed,\" what should the order status be?', '6', '23', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Refunded/Reversed Order Status', 'MODULE_PAYMENT_PAYPAL_DP_REVERSED_ORDER_STATUS_ID', '0', 'When the payment status is reported as \"Refunded,\" \"Reversed,\" \"Canceled,\" or similar, what should the order status be?', '6', '24', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())");

      /* Centinal Commerce Configurations */
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Cardinal Centinel: Enable/Disable', 'MODULE_PAYMENT_PAYPAL_DP_CC_ENABLE', 'No', 'Enable 3D Secure Buyer Authentication via the Cardinal Centinel.', '6', '26',  'tep_cfg_select_option(array(\'Yes\', \'No\'), ', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Cardinal Centinel: Transaction URL', 'MODULE_PAYMENT_PAYPAL_DP_CC_TXURL', 'https://centineltest.cardinalcommerce.com/maps/txns.asp', 'Transaction URL provided by Cardinal Commerce.', '6', '27', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Cardinal Centinel: Processor ID', 'MODULE_PAYMENT_PAYPAL_DP_CC_PROCESSOR_ID', '', 'Enter the Processor ID provided by Centinal Commerce', '6', '28', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Cardinal Centinel: Merchant ID', 'MODULE_PAYMENT_PAYPAL_DP_CC_MERCHANT_ID', '', 'Enter the Merchant ID provided by Centinal Commerce', '6', '29', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Cardinal Centinel: Transaction Password', 'MODULE_PAYMENT_PAYPAL_DP_CC_TXPWD', '', 'Enter the Transaction Password provided by Centinal Commerce', '6', '30', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Cardinal Centinel: Only Accept Chargeback Protected Orders', 'MODULE_PAYMENT_PAYPAL_DP_CC_ACCEPT_ONLY_CHARGEBACK_PROTECTED', 'No', 'Do you only want to accept chargeback protected orders?', '6', '31', 'tep_cfg_select_option(array(\'Yes\', \'No\'), ', now())");
      //Install the DB columns if necessary
      $col_query = tep_db_query("SHOW COLUMNS FROM " . TABLE_CUSTOMERS);
      $found = array(false, false);
      while ($col = tep_db_fetch_array($col_query)) {
        if ($col['Field'] == 'customers_paypal_payerid') $found[0] = true;
        if ($col['Field'] == 'customers_paypal_ec') $found[1] = true;
      }
      
      if (!$found[0]) {
        tep_db_query("ALTER TABLE `" . TABLE_CUSTOMERS . "` ADD `customers_paypal_payerid` VARCHAR( 20 )");
      }
      
      if (!$found[1]) {
        tep_db_query("ALTER TABLE `" . TABLE_CUSTOMERS . "` ADD `customers_paypal_ec` TINYINT (1) UNSIGNED DEFAULT '0' NOT NULL");
      }
      
      tep_db_query("CREATE TABLE IF NOT EXISTS `orders_status_history_transactions` (
                    `orders_status_history_id` INT NOT NULL ,
                    `transaction_id` VARCHAR( 64 ) NOT NULL ,
                    `transaction_type` VARCHAR( 32 ) NOT NULL ,
                    `payment_type` VARCHAR( 32 ) NOT NULL ,
                    `payment_status` VARCHAR( 32 ) NOT NULL ,
                    `transaction_amount` DECIMAL( 7, 2 ) NOT NULL ,
                    `module_code` VARCHAR( 32 ) NOT NULL ,
                    `transaction_avs` VARCHAR( 64 ) NOT NULL ,
                    `transaction_cvv2` VARCHAR( 64 ) NOT NULL ,
                    `transaction_msgs` VARCHAR( 255 ) NOT NULL ,
                    PRIMARY KEY ( `orders_status_history_id` ) ,
                    INDEX ( `transaction_id` )
                    )");
    }

    /*
     * Uninstall this module
     */
    function remove() {
      tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
    }

    /*
     * Configuration keys
     */
    function keys() {
      return array(
        'MODULE_PAYMENT_PAYPAL_DP_STATUS', 
        'MODULE_PAYMENT_PAYPAL_DP_DEBUGGING', 
        'MODULE_PAYMENT_PAYPAL_DP_PAYMENT_ACTION', 
        'MODULE_PAYMENT_PAYPAL_DP_SERVER', 
        'MODULE_PAYMENT_PAYPAL_DP_CERT_PATH', 
        'MODULE_PAYMENT_PAYPAL_DP_API_USERNAME', 
        'MODULE_PAYMENT_PAYPAL_DP_API_PASSWORD', 
        'MODULE_PAYMENT_PAYPAL_DP_PROXY', 
        'MODULE_PAYMENT_PAYPAL_DP_UK_ENABLED', 
        'MODULE_PAYMENT_PAYPAL_DP_CHECK_CVV2', 
        'MODULE_PAYMENT_PAYPAL_EC_ENABLED', 
        'MODULE_PAYMENT_PAYPAL_EC_IPN_URL', 
        'MODULE_PAYMENT_PAYPAL_EC_ADDRESS_OVERRIDE', 
        'MODULE_PAYMENT_PAYPAL_DP_BUTTON_PAYMENT_PAGE', 
        'MODULE_PAYMENT_PAYPAL_DP_REQ_VERIFIED', 
        'MODULE_PAYMENT_PAYPAL_DP_CONFIRMED', 
        'MODULE_PAYMENT_PAYPAL_DP_DISPLAY_PAYMENT_PAGE', 
        'MODULE_PAYMENT_PAYPAL_DP_NEW_ACCT_NOTIFY', 
        'MODULE_PAYMENT_PAYPAL_EC_PAGE_STYLE', 
        'MODULE_PAYMENT_PAYPAL_DP_CURRENCY', 
        'MODULE_PAYMENT_PAYPAL_DP_SORT_ORDER', 
        'MODULE_PAYMENT_PAYPAL_DP_ZONE', 
        'MODULE_PAYMENT_PAYPAL_DP_PENDING_ORDER_STATUS_ID', 
        'MODULE_PAYMENT_PAYPAL_DP_COMPLETED_ORDER_STATUS_ID',
        'MODULE_PAYMENT_PAYPAL_DP_REVERSED_ORDER_STATUS_ID',
        'MODULE_PAYMENT_PAYPAL_DP_CC_ENABLE',
        'MODULE_PAYMENT_PAYPAL_DP_CC_TXURL',
        'MODULE_PAYMENT_PAYPAL_DP_CC_PROCESSOR_ID',
        'MODULE_PAYMENT_PAYPAL_DP_CC_MERCHANT_ID',
        'MODULE_PAYMENT_PAYPAL_DP_CC_TXPWD',
        'MODULE_PAYMENT_PAYPAL_DP_CC_ACCEPT_ONLY_CHARGEBACK_PROTECTED'
      );
    }   
    
    /***************************************************************
     ****************** CARDINAL CENTINEL CODE *********************
     ***************************************************************/
    
    /*
     * Enable the Cardinal Centinel features?
     */
    function cardinal_centinel_enabled($cc_type = '') {
      if (MODULE_PAYMENT_PAYPAL_DP_CC_ENABLE != 'Yes') return false;
      if (trim(MODULE_PAYMENT_PAYPAL_DP_CC_TXURL) == '') return false;
      if (trim(MODULE_PAYMENT_PAYPAL_DP_CC_PROCESSOR_ID) == '') return false;
      if (trim(MODULE_PAYMENT_PAYPAL_DP_CC_MERCHANT_ID) == '') return false;
      if (trim(MODULE_PAYMENT_PAYPAL_DP_CC_TXPWD) == '') return false;
      
      if($cc_type != '' && !in_array($cc_type, array('Visa', 'MasterCard', 'Maestro'))) return false;
      
      return true;
    }
    
    /*
     * Parse and return errors from Cardinal Commerce
     */
    function cardinal_centinel_parse_errors($error_codes, $error_desc) {
      $error_codes = explode(',', $error_codes);
      $error_desc = explode(',', $error_desc);
      
      $errors = array();
      
      $error_count = count($error_codes);
      
      for ($x = 0; $x < $error_count; $x++) {
        $errors[] = array(
          'id' => trim($error_codes[$x]),
          'text' => trim($error_desc[$x])
        );
      }
      
      return $errors;
    }
     
    
    /* 
     * Get ISO4217 code number for currency
     */
    function cardinal_centinel_currency_code($currency = '') {
      if ($currency == '') return false;
      
      $currency_codes = array(
        'ADP' => '020','AED' => '784','AFA' => '004','ALL' => '008','AMD' => '051','ANG' => '532',
        'AON' => '024','ARS' => '032','ATS' => '040','AUD' => '036','AWG' => '533','AZM' => '031',
        'BAM' => '977','BBD' => '052','BDT' => '050','BEF' => '056','BGL' => '100','BHD' => '048',
        'BIF' => '108','BMD' => '060','BND' => '096','BOB' => '068','BRL' => '986','BSD' => '044',
        'BTN' => '064','BWP' => '072','BYR' => '974','BZD' => '084','CAD' => '124','CDF' => '976',
        'CHF' => '756','CLP' => '152','CNY' => '156','COP' => '170','CRC' => '188','CUP' => '192',
        'CVE' => '132','CYP' => '196','CZK' => '203','DEM' => '276','DJF' => '262','DKK' => '208',
        'DOP' => '214','DZD' => '012','EEK' => '233','EGP' => '818','ERN' => '232','ETB' => '230',
        'EUR' => '978','FIM' => '246','FJD' => '242','FKP' => '238','FRF' => '250','GBP' => '826',
        'GEL' => '981','GHC' => '288','GIP' => '292','GMD' => '270','GNF' => '324','GTQ' => '320',
        'GWP' => '624','GYD' => '328','HKD' => '344','HNL' => '340','HRK' => '191','HTG' => '332',
        'HUF' => '348','IDR' => '360','IEP' => '372','ILS' => '376','INR' => '356','IQD' => '368',
        'IRR' => '364','ISK' => '352','ITL' => '380','JMD' => '388','JOD' => '400','JPY' => '392',
        'KES' => '404','KGS' => '417','KHR' => '116','KMF' => '174','KPW' => '408','KRW' => '410',
        'KWD' => '414','KYD' => '136','KZT' => '398','LAK' => '418','LBP' => '422','LKR' => '144',
        'LRD' => '430','LSL' => '426','LTL' => '440','LUF' => '442','LVL' => '428','LYD' => '434',
        'MAD' => '504','MDL' => '498','MGF' => '450','MKD' => '807','MMK' => '104','MNT' => '496',
        'MOP' => '446','MRO' => '478','MTL' => '470','MUR' => '480','MVR' => '462','MWK' => '454',
        'MXN' => '484','MYR' => '458','MZM' => '508','NAD' => '516','NGN' => '566','NIO' => '558',
        'NLG' => '528','NOK' => '578','NPR' => '524','NZD' => '554','OMR' => '512','PAB' => '590',
        'PEN' => '604','PGK' => '598','PHP' => '608','PKR' => '586','PLN' => '985','PTE' => '620',
        'PYG' => '600','QAR' => '634','ROL' => '642','RUB' => '643','RUR' => '810','RWF' => '646',
        'SAR' => '682','SBD' => '090','SCR' => '690','SDD' => '736','SEK' => '752','SGD' => '702',
        'SHP' => '654','SIT' => '705','SKK' => '703','SLL' => '694','SOS' => '706','SRG' => '740',
        'STD' => '678','SVC' => '222','SYP' => '760','SZL' => '748','THB' => '764','TJS' => '972',
        'TMM' => '795','TND' => '788','TOP' => '776','TPE' => '626','TRL' => '792','TTD' => '780',
        'TWD' => '901','TZS' => '834','UAH' => '980','UGX' => '800','USD' => '840','UYU' => '858',
        'UZS' => '860','VEB' => '862','VND' => '704','VUV' => '548','WST' => '882','XAF' => '950',
        'XCD' => '951','XOF' => '952','XPF' => '953','YER' => '886','YUM' => '891','ZAR' => '710',
        'ZMK' => '894','ZWD' => '716'
      );
      
      /* If currency code is an integer, format it correctly */
      if (ctype_digit($currency) || is_int($currency)) {
        
        if(strlen($currency) == 1) {
          $currency = '00' . $currency;
        } elseif (strlen($currency) == 2) {
          $currency = '0' . $currency;
        } elseif (strlen($currency) > 3) {
          $currency = substr(preg_replace('/[^0-9]/', '', $currency), 0, 3);
        }
        
        /* If the currency code is valid, return it properly formatted */
        if (in_array($currency, $currency_codes)) {
          return $currency;
        }
      /* Otherwise get the code by string */
      } else {
        $currency = strtoupper($currency);
        
        if (array_key_exists($currency, $currency_codes)) {
          return $currency_codes[$currency];
        }
      }

      return false;
    }
    
    /*
     * Transaction amounts must be in cents, so format amounts correctly
     */
    function cardinal_centinel_format_currency($amount) {
      $amount = (float)preg_replace('/[^0-9\.]/', '', $amount);
      
      return round(($amount * 100), 0);
    }
    
    function cardinal_centinel_before_process($order_info = '') {
      global $order;
      
      $order_info['CARDINAL_CENTINEL_3DS'] = '';
      
      if ($this->cardinal_centinel_enabled($order->info['cc_type'])) {
        $cardinal_centinel_process = 'lookup';
        
        if (isset($_SESSION['cardinal_centinel']) && is_array($_SESSION['cardinal_centinel'])) {
          if ($_SESSION['cardinal_centinel']['auth_status'] === true) {
            $xml  = '<ThreeDSecureRequest>';
            $xml .= '<AuthStatus3ds>Y</AuthStatus3ds>';
            $xml .= '<MpiVendor3ds>Y</MpiVendor3ds>';
            $xml .= '<Cavv>' . $_SESSION['cardinal_centinel']['auth_cavv'] . '</Cavv>';
            $xml .= '<Eci3ds>' . $_SESSION['cardinal_centinel']['auth_eci'] . '</Eci3ds>';
            $xml .= '<Xid>' . $_SESSION['cardinal_centinel']['auth_xid'] . '</Xid>';
            $xml .= '</ThreeDSecureRequest>';
            
            $order_info['CARDINAL_CENTINEL_3DS'] = $xml;
            
            return false;
          } elseif (isset($_POST['PaRes']) && $_SESSION['cardinal_centinel']['enrolled'] === true) {
            $cardinal_centinel_process = 'authenticate';
          } else {
            if (tep_session_is_registered('cardinal_centinel')) tep_session_unregister('cardinal_centinel');
          }
        }
        
        $auth_info = array(
          'CARDINAL_CENTINEL_PROCESSOR_ID' => MODULE_PAYMENT_PAYPAL_DP_CC_PROCESSOR_ID,
          'CARDINAL_CENTINEL_MERCHANT_ID' => MODULE_PAYMENT_PAYPAL_DP_CC_MERCHANT_ID,
          'CARDINAL_CENTINEL_TXPWD' => MODULE_PAYMENT_PAYPAL_DP_CC_TXPWD
        );
        
        if ($cardinal_centinel_process == 'lookup') {
          $this->cardinal_centinel_lookup($auth_info, &$order_info);
        } else {
          $this->cardinal_centinel_authenticate($auth_info, &$order_info);
        }
      }
    }
    
    function cardinal_centinel_lookup($auth_info, $order_info) {
      $auth_info = array_merge($auth_info, array(
        'CARDINAL_CENTINEL_ORDER_ID' => tep_session_id(),
        'CARDINAL_CENTINEL_ORDER_DESC' => $order_info['PAYPAL_ORDER_DESCRIPTION'],
        'CARDINAL_CENTINEL_ORDER_TOTAL' => $this->cardinal_centinel_format_currency($order_info['PAYPAL_ORDER_TOTAL']),
        'CARDINAL_CENTINEL_CURRENCY' => $order_info['PAYPAL_CURRENCY'],
        'CARDINAL_CENTINEL_CARD_NUMBER' => $order_info['PAYPAL_CC_NUMBER'],
        'CARDINAL_CENTINEL_EXP_MONTH' => (strlen($order_info['PAYPAL_CC_EXP_MONTH']) < 2 ? '0' : '') . $order_info['PAYPAL_CC_EXP_MONTH'],
        'CARDINAL_CENTINEL_EXP_YEAR' => (strlen($order_info['PAYPAL_CC_EXP_YEAR']) < 4 ? '20' : '') . $order_info['PAYPAL_CC_EXP_YEAR'],
        'CARDINAL_CENTINEL_USER_AGENT' => $_SERVER['HTTP_USER_AGENT'],
        'CARDINAL_CENTINEL_BROWSER_HEADER' => $_SERVER['HTTP_ACCEPT'],
        'CARDINAL_CENTINEL_CURRENCY' => $this->cardinal_centinel_currency_code($this->wpp_get_currency())
      ));
    
      $cmpi_lookup = $this->wpp_execute_transaction('cmpi_lookup', $auth_info);
      
      $lookup_errors = false;
      
      if (is_array($cmpi_lookup['CardinalMPI'])) {
        $lookup_response = $cmpi_lookup['CardinalMPI'][0];
        
        if ($lookup_response['ErrorNo'] != '0') {
          $lookup_errors = $this->cardinal_centinel_parse_errors($lookup_response['ErrorNo'], $lookup_response['ErrorDesc']);
        }
      } else {
        $lookup_errors = array(array(
          'id' => '99999',
          'ErrorDesc' => 'Invalid response received from Cardinal Commerce'
        ));
        //TODO: Add error handling if there is no response
      }
      
      if (is_array($lookup_errors)) {
        $error_text = '<ul>';
        
        foreach ($lookup_errors as $err) {
          $error_text .= '<li>' . $err['text'] . ' (Error ' . $err['id'] . ')</li>';
        }
        
        $error_text .= '</ul>';
        
        if (tep_session_is_registered('cardinal_centinel')) tep_session_unregister('cardinal_centinel');
        $this->away_with_you(MODULE_PAYMENT_PAYPAL_DP_TEXT_DECLINED . $error_text, false, FILENAME_CHECKOUT_PAYMENT);
      }
      
      $result = array(
        'enrolled' => ($lookup_response['Enrolled'] == 'Y' ? true : false),
        'transaction_id' => $lookup_response['TransactionId'],
        'acs_url' => $lookup_response['ACSUrl'],
        'spa_hidden_fields' => $lookup_response['SPAHiddenFields'],
        'payload' => $lookup_response['Payload']
      );
      
      /* If only orders with chargeback protection are allowed, only allow Visa and JCB cards */
      if (MODULE_PAYMENT_PAYPAL_DP_CC_ACCEPT_ONLY_CHARGEBACK_PROTECTED == 'Yes') {
        if (!$result['enrolled'] && !in_array($order_info['PAYPAL_CC_TYPE'], array('Visa', 'JCB'))) {
          if (tep_session_is_registered('cardinal_centinel')) tep_session_unregister('cardinal_centinel');
          $this->away_with_you(MODULE_PAYMENT_PAYPAL_DP_TEXT_DECLINED, false, FILENAME_CHECKOUT_PAYMENT);
        }
      }
      
      if ($result['enrolled'] && trim($result['acs_url']) != '') {
        if (!tep_session_is_registered('cardinal_centinel')) tep_session_register('cardinal_centinel');
        $_SESSION['cardinal_centinel'] = $result;
        
        $_SESSION['cardinal_centinel']['post'] = $_POST;
        $cardinal_centinel = $_SESSION['cardinal_centinel'];
        
        tep_redirect(tep_href_link(DIR_WS_INCLUDES . 'paypal_wpp/' . FILENAME_PAYPAL_WPP_3DS, 'action=cardinal_centinel_lookup&osCsid=' . tep_session_id(), 'SSL'));
        exit;
      }
      
      return false;
    }
    
    function cardinal_centinel_authenticate($auth_info) {
      global $language;
      
      include(DIR_WS_LANGUAGES . $language . '/modules/payment/paypal_wpp.php');
      
      $auth_info = array_merge($auth_info, array(
        'CARDINAL_CENTINEL_TXID' => $_SESSION['cardinal_centinel']['transaction_id'],
        'CARDINAL_CENTINEL_PAYLOAD' => $_POST['PaRes']
      ));
      
      $cmpi_authenticate = $this->wpp_execute_transaction('cmpi_authenticate', $auth_info);
      
      $auth_errors = false;
      
      if (is_array($cmpi_authenticate['CardinalMPI'])) {
        $auth_response = $cmpi_authenticate['CardinalMPI'][0];
        
        if ($auth_response['ErrorNo'] != '0') {
          $auth_errors = $this->cardinal_centinel_parse_errors($auth_response['ErrorNo'], $auth_response['ErrorDesc']);
        }
      } else {
        $auth_errors = array(array(
          'id' => '99999',
          'ErrorDesc' => 'Invalid response received from Cardinal Commerce'
        ));
      }
      
      if (is_array($auth_errors)) {
        $error_text = '<ul>';
        
        foreach ($auth_errors as $err) {
          $error_text .= '<li>' . $err['text'] . ' (Error ' . $err['id'] . ')</li>';
        }
        
        $error_text .= '</ul>';
        
        if (tep_session_is_registered('cardinal_centinel')) tep_session_unregister('cardinal_centinel');
        $this->away_with_you(MODULE_PAYMENT_PAYPAL_DP_TEXT_DECLINED . $error_text, false, FILENAME_CHECKOUT_PAYMENT);
      }
      
      /* Check Issuer's Authentication */
      if (strtoupper($auth_response['PAResStatus']) == 'N') {
        if (tep_session_is_registered('cardinal_centinel')) tep_session_unregister('cardinal_centinel');
        $this->away_with_you(MODULE_PAYMENT_PAYPAL_DP_TEXT_DECLINED, false, FILENAME_CHECKOUT_PAYMENT);
      }
      
      if (strtoupper($auth_response['PAResStatus']) == 'U' && MODULE_PAYMENT_PAYPAL_DP_CC_ACCEPT_ONLY_CHARGEBACK_PROTECTED == 'Yes') {
        if (tep_session_is_registered('cardinal_centinel')) tep_session_unregister('cardinal_centinel');
        $this->away_with_you(MODULE_PAYMENT_PAYPAL_DP_TEXT_DECLINED, false, FILENAME_CHECKOUT_PAYMENT);
      }
      
      /* Signature Verification */
      if (strtoupper($auth_response['SignatureVerification']) == 'N') {
        if (tep_session_is_registered('cardinal_centinel')) tep_session_unregister('cardinal_centinel');
        $this->away_with_you(MODULE_PAYMENT_PAYPAL_DP_TEXT_DECLINED, false, FILENAME_CHECKOUT_PAYMENT);
      }

      $_SESSION['cardinal_centinel']['auth_status'] = true;
      $_SESSION['cardinal_centinel']['auth_xid'] = $auth_response['Xid'];
      $_SESSION['cardinal_centinel']['auth_cavv'] = $auth_response['Cavv'];
      $_SESSION['cardinal_centinel']['auth_eci'] = $auth_response['EciFlag'];
    }
  }
?>