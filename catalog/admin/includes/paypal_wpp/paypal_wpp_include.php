<?php
/*
  $Id: paypal_wpp.php,v 1.0.9 Brian Burton brian [at] dynamoeffects [dot] com Exp $

  Copyright (c) 2008, 2009 Brian Burton - brian [at] dynamoeffects [dot] com

  Released under the GNU General Public License
*/
  define('FILENAME_PAYPAL_WPP_3DS', 'paypal_wpp_3ds.php');
  
  include(DIR_FS_CATALOG . DIR_WS_INCLUDES . 'modules/payment/paypal_wpp.php');
  include(DIR_WS_INCLUDES . 'paypal_wpp/languages/' . $language . '/paypal_wpp.php');
  
  /* Database Define */
  define('TABLE_ORDERS_STATUS_HISTORY_TRANSACTIONS', 'orders_status_history_transactions');
  
  if (!is_object($order)) {
    $oID = (int)$_GET['oID'];
    $order = new order($oID);
    $order_totals = array();
  }
  
  if (!is_object($currencies)) {
    require_once(DIR_WS_CLASSES . 'currencies.php');
    $currencies = new currencies();
  }
  
  class paypal_wpp_admin extends paypal_wpp {
    var $last_response, $last_request;
    //PHP4 compatibility
    function paypal_wpp_admin() {
      $this->paypal_wpp();
      $this->is_admin = true;
      return true;
    }
    
    function case_refund() {
      global $messageStack, $currencies;
      $input = array();
      $oID = tep_db_input($_GET['oID']);

      $errors = '';

      $input['transaction_id'] = preg_replace('/[^0-9A-Z]/', '', $_POST['refund_transaction_id']);
      $input['currency'] = $_POST['refund_currency'];
      $input['type'] = $_POST['refund_type'];
      $input['amount'] = $this->round_amount($_POST['refund_amount']);
      $input['order_status'] = $_POST['refund_order_status'];
      $input['comments'] = $_POST['refund_comments'];

      if ($input['transaction_id'] == '') {
        $errors .= '* ' . WPP_ERROR_NO_TRANS_ID . '<br />';
      }
      
      if ($input['currency'] != 'AUD' && $input['currency'] != 'CAD' && $input['currency'] != 'EUR' 
          && $input['currency'] != 'GBP' && $input['currency'] != 'JPY' && $input['currency'] != 'USD') {
        $errors .= '* ' . WPP_ERROR_BAD_CURRENCY . '<br />';
      }
      
      if ($input['type'] != 'Partial' && $input['type'] != 'Full') {
        $errors .= '* ' . WPP_ERROR_SELECT_REFUND_TYPE . '<br />';
      }
      
      $transaction = $this->get_transactions($input['transaction_id']);
      
      $order_total = $transaction[0]['transaction_amount'];
      
      if ($input['type'] == 'Partial' && $input['amount'] == $order_total) {
        $input['type'] = 'Full';
        $input['amount'] = '';
      } 
      
      if ($errors != '') {
        $messageStack->add_session('<b>' . WPP_ERROR_REFUND_FAILED_BECAUSE . '</b><br />' . $errors, 'warning');
      } else {
        
        
        
        $result = $this->do_refund($input);
        
        if (!$result || $result['ack'] != 'Success') {
          $messageStack->add_session('<b>' . WPP_ERROR_REFUND_FAILED_BECAUSE . '</b><br />' . implode('<br />', $result['msgs']), 'warning');
          if ($this->enableDebugging == '1') {
            $spacer =          "---------------------------------------------------------------------\r\n";
           
            $request_title =   "----------------------------PAYPAL-REQUEST---------------------------\r\n";

            $response_title =  "---------------------------PAYPAL-RESPONSE---------------------------\r\n";

            $request_dump = print_r($this->last_request, true);
            $request_dump = str_replace(MODULE_PAYMENT_PAYPAL_DP_API_USERNAME, str_repeat('X', strlen(MODULE_PAYMENT_PAYPAL_DP_API_USERNAME)), $request_dump);
            $request_dump = str_replace(MODULE_PAYMENT_PAYPAL_DP_API_PASSWORD, str_repeat('X', strlen(MODULE_PAYMENT_PAYPAL_DP_API_PASSWORD)), $request_dump);

            $response_dump = print_r($this->last_response, true);
            
            tep_mail(STORE_OWNER, 
                     STORE_OWNER_EMAIL_ADDRESS, 
                     'PayPal Error Dump', 
                     "In function: do_refund()\r\n" . 
                     $spacer . $request_title . $spacer . $request_dump . "\r\n\r\n" . 
                     $spacer . $response_title . $spacer . $response_dump,
                     STORE_OWNER, 
                     STORE_OWNER_EMAIL_ADDRESS);
          }
        } else {
          if ($input['type'] == 'Full') {
            $input['amount'] = $this->gross_refund_amount;
          }
        
        
          $comments = $input['type'] . " " . WPP_REFUND_ISSUED . " " . $currencies->format($input['amount']);
          
          if ($input['comments'] != '') {
            $comments = $input['comments'] . "\n" . $comments;
          }
          
          tep_db_query("INSERT INTO " . TABLE_ORDERS_STATUS_HISTORY . " 
                          (`orders_id`, 
                           `orders_status_id`, 
                           `date_added`, 
                           `comments`
                          ) VALUES (
                           '" . (int)$oID . "', 
                           '" . tep_db_input($input['order_status']) . "', 
                           now(), 
                           '" . tep_db_input($comments)  . "')");
                           
          $order_status_id = tep_db_insert_id();
          
          if ($order_status_id > 0) {
          
            $refund_details  = '<br />NetRefundAmount: ' . $this->net_refund_amount . '<br />';
            $refund_details .= 'FeeRefundAmount: ' . $this->fee_refund_amount . '<br />';
            $refund_details .= 'GrossRefundAmount: ' . $this->gross_refund_amount;
            /*
            tep_db_query("UPDATE " . TABLE_ORDERS_STATUS_HISTORY_TRANSACTIONS . " 
                          SET transaction_type = '" . ($input['type'] == 'Partial' ? 'PART' : '') . "REFUNDED' 
                          WHERE transaction_id = '" . $input['transaction_id'] . "' 
                          LIMIT 1");
            */           
          
            tep_db_query("INSERT INTO " . TABLE_ORDERS_STATUS_HISTORY_TRANSACTIONS . " 
                            (`orders_status_history_id`,
                             `transaction_id`,
                             `transaction_type`,
                             `payment_type`,
                             `payment_status`,
                             `transaction_amount`,
                             `module_code`,
                             `transaction_msgs`
                            ) VALUES (
                             " . (int)$order_status_id . ",
                             '" . $this->trans_id . "',
                             '" . $this->trans_type . "',
                             '" . $this->payment_type . "',
                             '" . $this->payment_status . "',
                             " . $input['amount'] . ",
                             '" . $this->code . "',
                             '" . $refund_details . "'
                            )");
                            
            /* Set Order Totals */
            $ot_query = tep_db_query("SELECT orders_total_id as id, value, sort_order 
                                      FROM " . TABLE_ORDERS_TOTAL . " 
                                      WHERE orders_id = " . (int)$oID . " 
                                        AND class = 'ot_total'
                                      LIMIT 1");
                                                
            $ot = tep_db_fetch_array($ot_query);
            
            $ot_refund_query = tep_db_query("SELECT orders_total_id as id, value, sort_order 
                                             FROM " . TABLE_ORDERS_TOTAL . " 
                                             WHERE orders_id = " . (int)$oID . " 
                                               AND class = 'ot_refund'
                                             LIMIT 1");
            $ot_refund = array();
            
            if (tep_db_num_rows($ot_refund_query) > 0) {
              $ot_refund = tep_db_fetch_array($ot_refund_query);
            }
            
            if (count($ot_refund) < 1) {
              tep_db_query("INSERT INTO " . TABLE_ORDERS_TOTAL . " 
                             (`orders_id`,
                              `title`,
                              `text`,
                              `value`,
                              `class`,
                              `sort_order`
                             ) VALUES (
                              " . (int)$oID . ",
                              'Refund:',
                              '" . $currencies->format($this->gross_refund_amount) . "',
                              " . $this->gross_refund_amount . ",
                              'ot_refund',
                              " . ($ot['sort_order'] - 1 < 0 ? 0 : $ot['sort_order'] - 1) . "
                             )");
            } else {
              $new_refund_total = $ot_refund['value'] + $this->gross_refund_amount;
              
              tep_db_query("UPDATE " . TABLE_ORDERS_TOTAL . " 
                            SET text = '" . $currencies->format($new_refund_total) . "',
                                value = '" . $new_refund_total . "'  
                            WHERE orders_total_id = " . (int)$ot_refund['id'] . " 
                            LIMIT 1");
            }
            
            $new_total = $ot['value'] - $this->gross_refund_amount;
            
            tep_db_query("UPDATE " . TABLE_ORDERS_TOTAL . " 
                          SET text = '<b>" . $currencies->format($new_total) . "</b>',
                              value = '" . $new_total . "' 
                          WHERE orders_total_id = " . (int)$ot['id'] . " 
                          LIMIT 1");
          }

          $messageStack->add_session('<b>' . WPP_SUCCESS_REFUND . '</b>', 'success');
        }
      }
      //tep_redirect(tep_href_link(FILENAME_ORDERS, tep_get_all_get_params(array('action')) . 'action=edit', 'SSL'));

      $this->refresh_parent();
    }
    
    function case_charge() {
      global $oID, $messageStack, $order, $order_totals, $currencies, $order;

      $errors = '';
      $order_total = $order->totals[count($order->totals) - 1]['text'];
      $order_total = preg_replace('/[^0-9\.]/', '', $order_total);

      $input = array('order_id' => $oID,
                     'amount' => $this->round_amount($_POST['paypalwpp_amount']),
                     'first_name' => $_POST['paypalwpp_cc_firstname'],
                     'last_name' => $_POST['paypalwpp_cc_lastname'],
                     'cc_type' => $_POST['paypalwpp_cc_type'],
                     'cc_number' => preg_replace('/[^0-9]/', '', $_POST['paypalwpp_cc_number']),
                     'cc_expiration_month' => preg_replace('/[^0-9]/', '', $_POST['paypalwpp_cc_expires_month']),
                     'cc_expiration_year' => preg_replace('/[^0-9]/', '', $_POST['paypalwpp_cc_expires_year']),
                     'cc_cvv2' => preg_replace('/[^0-9]/', '', $_POST['paypalwpp_cc_checkcode']),
                     'comments' => $_POST['paypalwpp_comments'],
                     'order_status' => $_POST['order_status']);

      if ($input['amount'] <= 0) {
        $errors .= '* ' . WPP_ERROR_INVALID_CHARGE_AMOUNT . '<br />';
      }
      
      if ($input['first_name'] == '' || $input['last_name'] == '') {
        $errors .= '* ' . WPP_ERROR_INCOMPLETE_CARDHOLDER_NAME . '<br />';
      }

      if ($errors != '') {
        $messageStack->add_session('<b>' . WPP_ERROR_CHARGE_FAILED_BECAUSE . '</b><br />' . $errors, 'warning');
      } else {
        //The whole name gets loaded into the firstname because they get combined again in the end
        $order->delivery['firstname'] = $order->delivery['name'];
        $order->delivery['lastname'] = '';
        
        $order->billing['firstname'] = $order->billing['name'];
        $order->billing['lastname'] = '';
        
        $order->customer['firstname'] = $order->customer['name'];
        $order->customer['lastname'] = '';
        
        $country_query = tep_db_query("SELECT countries_name, countries_iso_code_2 
                                       FROM " . TABLE_COUNTRIES . " 
                                       WHERE countries_name = '" . tep_db_input($order->delivery['country']) . "' 
                                         OR  countries_name = '" . tep_db_input($order->billing['country']) . "' 
                                         OR  countries_name = '" . tep_db_input($order->customer['country']) . "'");
                                         
        if (tep_db_num_rows($country_query) > 0) {
          while ($country = tep_db_fetch_array($country_query)) {
            if ($country['countries_name'] == $order->delivery['country']) {
              $order->delivery['country'] = array('iso_code_2' => $country['countries_iso_code_2']);
            }
            if ($country['countries_name'] == $order->billing['country']) {
              $order->billing['country'] = array('iso_code_2' => $country['countries_iso_code_2']);
            }
            if ($country['countries_name'] == $order->customer['country']) {
              $order->customer['country'] = array('iso_code_2' => $country['countries_iso_code_2']);
            }
          }
        } else {
          $messageStack->add_session('<b>' . WPP_ERROR_COUNTRY_NOT_FOUND . '</b>', 'warning');
          $this->refresh_parent();
        }

        $order->content_type = 'virtual';
        
        $order->products = array(array(
                    'qty' => 1,
                    'name' => WPP_CHARGE_NAME,
                    'model' => '',
                    'tax' => 0,
                    'price' => $input['amount'],
                    'final_price' => $input['amount']));
                    
        $order->totals = array(array('title' => 'Total:', 
                                     'text' => '<b>' . $currencies->format($input['amount']) . '</b>'));
                                     
        $order_totals[] = array('code' => 'ot_total',
                                'value' => $input['amount']);

        $result = $this->before_process($input);
        
        if (!$result || tep_session_is_registered('paypal_error')) {
          $messageStack->add_session('<b>' . WPP_ERROR_CHARGE_FAILED_BECAUSE . '</b><br />' . $_SESSION['paypal_error'], 'warning');
          tep_session_unregister('paypal_error');

        } else {

          $comments = WPP_CHARGE_ISSUED . " " . $currencies->format($input['amount']);
          
          if ($input['comments'] != '') {
            $comments = $input['comments'] . "\n" . $comments;
          }
          
          $orders_status_query = tep_db_query("SELECT orders_status as status 
                                               FROM " . TABLE_ORDERS . " 
                                               WHERE orders_id = " . (int)$oID . " 
                                               LIMIT 1");
                                               
          $orders_status = tep_db_fetch_array($orders_status_query);
          
          tep_db_query("INSERT INTO " . TABLE_ORDERS_STATUS_HISTORY . " 
                          (`orders_id`,
                           `orders_status_id`,
                           `date_added`,
                           `customer_notified`,
                           `comments`)
                         VALUES
                          (" . (int)$oID . ",
                           " . $orders_status['status'] . ",
                           NOW(),
                           0,
                           '" . tep_db_input($comments) . "')");
                           
          $order_status_id = tep_db_insert_id();
          
          if ($order_status_id > 0) {
            if (strtoupper($this->transaction_log['transaction_type']) == 'SALE') {
              $this->transaction_log['transaction_type'] = 'CHARGE';
            }
            tep_db_query("INSERT INTO " . TABLE_ORDERS_STATUS_HISTORY_TRANSACTIONS . " 
                            (`orders_status_history_id`,
                             `transaction_id`,
                             `transaction_type`,
                             `payment_type`,
                             `payment_status`,
                             `transaction_amount`,
                             `module_code`,
                             `transaction_avs`,
                             `transaction_cvv2`,
                             `transaction_msgs`
                            ) VALUES (
                             " . (int)$order_status_id . ",
                            '" . tep_db_input($this->transaction_log['transaction_id']) . "',
                            '" . strtoupper($this->transaction_log['transaction_type']) . "',
                            '" . tep_db_input(strtoupper($this->transaction_log['payment_type'])) . "',
                            '" . tep_db_input(strtoupper($this->transaction_log['payment_status'])) . "',
                            " . $this->total_amount . ",
                            '" . $this->code . "',
                            '" . tep_db_input($this->transaction_log['avs']) . "',
                            '" . tep_db_input($this->transaction_log['cvv2']) . "',
                            '" . tep_db_input($this->transaction_log['transaction_msgs']) . "'
                            )");
          }


          $messageStack->add_session('<b>' . WPP_SUCCESS_CHARGE . '</b>', 'success');
        }
      }
      
      //tep_redirect(tep_href_link(FILENAME_ORDERS, tep_get_all_get_params(array('action')) . 'action=edit', 'SSL'));
      $this->refresh_parent();
    }
    
    function case_capture() {
      global $messageStack, $currencies;
      
      $input = array();
      $oID = tep_db_input($_GET['oID']);
      $errors = '';


      $input['transaction_id'] = preg_replace('/[^0-9A-Z]/', '', $_POST['capture_transaction_id']);
      $input['currency'] = $_POST['capture_currency'];
      $input['order_status'] = $_POST['capture_order_status'];
      $input['comments'] = $_POST['capture_comments'];
      $input['type'] = 'Complete';
      
      $transactions = $this->get_transactions($input['transaction_id']);
      
      if ($input['transaction_id'] == '' or count($transactions) < 1) {
        $errors .= '* ' . WPP_ERROR_NO_TRANS_ID . '<br />';
      }
      
      if ($input['currency'] != 'AUD' && $input['currency'] != 'CAD' && $input['currency'] != 'EUR' 
          && $input['currency'] != 'GBP' && $input['currency'] != 'JPY' && $input['currency'] != 'USD') {
        $errors .= '* ' . WPP_ERROR_BAD_CURRENCY . '<br />';
      }
      
      if ($errors != '') {
        $messageStack->add_session('<b>' . WPP_ERROR_CHARGE_FAILED_BECAUSE . '</b><br />' . $errors, 'warning');
      } else {
        $input['amount'] = $transactions[0]['transaction_amount'];

        $result = $this->do_capture($input);
        
        if (!$result || $result['ack'] == 'Failure') {
          $messageStack->add_session('<b>' . WPP_ERROR_CHARGE_FAILED_BECAUSE . '</b><br />' . implode('<br />', $result['msgs']), 'warning');
          if ($this->enableDebugging == '1') {
            $spacer =          "---------------------------------------------------------------------\r\n";

            $request_title =   "----------------------------PAYPAL-REQUEST---------------------------\r\n";

            $response_title =  "---------------------------PAYPAL-RESPONSE---------------------------\r\n";

            $this->last_data = str_replace(MODULE_PAYMENT_PAYPAL_DP_API_USERNAME, str_repeat('X', strlen(MODULE_PAYMENT_PAYPAL_DP_API_USERNAME)), $this->last_data);
            $this->last_data = str_replace(MODULE_PAYMENT_PAYPAL_DP_API_PASSWORD, str_repeat('X', strlen(MODULE_PAYMENT_PAYPAL_DP_API_PASSWORD)), $this->last_data);

            $response_dump = print_r($result, true);
            
            tep_mail(STORE_OWNER, 
                     STORE_OWNER_EMAIL_ADDRESS, 
                     'PayPal Error Dump', 
                     "In function: case_capture()\r\n" . 
                     $spacer . $request_title . $spacer . $this->last_data . "\r\n\r\n" . 
                     $spacer . $response_title . $spacer . $response_dump,
                     STORE_OWNER, 
                     STORE_OWNER_EMAIL_ADDRESS);
          }
        } else {
          $comments = $input['type'] . " " . WPP_CAPTURE_ISSUED . " " . $currencies->format($input['amount']);
          
          if ($input['comments'] != '') {
            $comments = $input['comments'] . "\n" . $comments;
          }

          tep_db_query("UPDATE " . TABLE_ORDERS_STATUS_HISTORY_TRANSACTIONS . " 
                        SET transaction_type = '" . $this->trans_type . "',
                            payment_status = 'COMPLETED' 
                        WHERE transaction_id = '" . tep_db_input($input['transaction_id']) . "'");
                        
          tep_db_query("UPDATE " . TABLE_ORDERS_STATUS_HISTORY . " 
                        SET orders_status_id = " . (int)$input['order_status'] . " 
                        LIMIT 1");

          $messageStack->add_session('<b>' . WPP_SUCCESS_CAPTURE . '</b>', 'success');
        }
      }
      $this->refresh_parent();
    }
  
    /* $details should be an array formed like this:
      $details = array('currency' => 'USD',
                       'amount' => 0.00,
                       'type' => 'Partial',
                       'transaction_id' => '123456789012345');
    */
    function do_refund($details) {
      $data = array();
      
      if (is_array($details)) {
        $data['PAYPAL_REFUND_TYPE'] = $details['type'];
        $data['PAYPAL_TRANSACTION_ID'] = $details['transaction_id'];
        
        if ($details['type'] == 'Partial') {
          $data['PAYPAL_AMOUNT'] = '<Amount currencyID="' . $details['currency'] . '" xsi:type="cc:BasicAmountType">' . $details['amount'] . '</Amount>';
        } else {
          $data['PAYPAL_AMOUNT'] = '';
        }
        
        $response = $this->wpp_execute_transaction('refundTransaction', $data);
        $output = array('ack' => '', 'msgs' => array());
        $this->last_request = $data;
        $this->last_response = $response;
        //$this->DBG_error(print_r($this->last_request, true) . "\r\n\r\n\r\n" . print_r($this->last_response, true));
        if ($response) {
          //Convert the XML into an easy-to-use associative array
          if(!is_array($response) || @strlen($response['RefundTransactionResponse'][0]['RefundTransactionID']) < 1 || ($response['RefundTransactionResponse'][0]['Ack'] != 'Success' && $response['RefundTransactionResponse'][0]['Ack'] != 'SuccessWithWarning')) {            
            if ($response['RefundTransactionResponse'][0]['Errors'][0]['ErrorCode'] == '') {
              $output['ack'] = 'Failure';
              $output['msgs'][] = 'No response was received from payment processor.';
            } else {
              $output['ack'] = 'Failure';
              $output['msgs'][] = $this->return_transaction_errors($response['RefundTransactionResponse'][0]['Errors']);
            }
          } else {
            $details = $response['RefundTransactionResponse'][0];
            $this->trans_id = $details['RefundTransactionID'];
            $this->trans_type = 'REFUND';
            $this->net_refund_amount = $details['NetRefundAmount'];
            $this->fee_refund_amount = $details['FeeRefundAmount'];
            $this->gross_refund_amount = $details['GrossRefundAmount'];
            $output['ack'] = 'Success';
            $output['msgs'][] = 'The amount was successfully refunded to the customer.';
          }

        } else {
          $output['ack'] = 'Failure';
          $output['msgs'][] = 'Internal Failure or no response from payment processor';
        }
        
        return $output;
      }
      
      return false;
    }
    
    function do_capture($details) {
      $data = array();
      
      if (is_array($details)) {
        $data['PAYPAL_COMPLETE_TYPE'] = $details['type'];
        $data['PAYPAL_TRANSACTION_ID'] = $details['transaction_id'];
        $data['PAYPAL_CURRENCY'] = $details['currency'];
        $data['PAYPAL_AMOUNT'] = $details['amount'];
        
        $response = $this->wpp_execute_transaction('doCapture', $data);
        $output = array('ack' => '', 'msgs' => array());

        if ($response) {
          //Convert the XML into an easy-to-use associative array
          if(!is_array($response) || ($response['DoCaptureResponse'][0]['Ack'] != 'Success' && $response['DoCaptureResponse'][0]['Ack'] != 'SuccessWithWarning')) {            
            if ($response['DoCaptureResponse'][0]['Errors'][0]['ErrorCode'] == '') {
              $output['ack'] = 'Failure';
              $output['msgs'][] = 'No response was received from payment processor.';
            } else {
              $output['ack'] = 'Failure';
              $output['msgs'][] = $this->return_transaction_errors($response['DoCaptureResponse'][0]['Errors']);
            }
          } else {
            $details = $response['DoCaptureResponse'][0];
            $this->trans_id = $details['transaction_id'];
            $this->trans_type = 'CHARGE';
            $output['ack'] = 'Success';
            $output['msgs'][] = 'The amount was successfully captured.';
          }

        } else {
          $output['ack'] = 'Failure';
          $output['msgs'][] = 'Internal Failure or no response from payment processor';
        }
        
        return $output;
      }
      
      return false;
    }

    
    function get_currency_symbol() {
      global $order;
      
      $currency_symbol_query = tep_db_query("SELECT symbol_left FROM " . TABLE_CURRENCIES . " WHERE code = '" . tep_db_input($order->info['currency']) . "' LIMIT 1");
      if (tep_db_num_rows($currency_symbol_query) > 0) {
        $result = tep_db_fetch_array($currency_symbol_query);
        $currency_symbol = $result['symbol_left'];
      } else {
        $currency_symbol = '';
      }
      
      return $currency_symbol;
    }
    
    function get_transactions($transaction_id = '', $include_refunds = false) {
      global $oID;
      
      $transactions = array();
      
      $additional_query = '';
      
      if (trim($transaction_id) != '') {
        $additional_query = " AND ot.transaction_id = '" . $transaction_id . "'";
      }
      
      $oh_query  = "SELECT ot.orders_status_history_id as id,
                           ot.transaction_id,
                           ot.transaction_type,
                           ot.payment_type,
                           ot.payment_status,
                           ot.module_code,
                           ot.transaction_amount,
                           ot.transaction_avs as avs,
                           ot.transaction_cvv2 as cvv2,
                           ot.transaction_msgs as msgs,
                           o.orders_status_id as status_id,
                           o.date_added as date
                    FROM " . TABLE_ORDERS_STATUS_HISTORY . " o
                      LEFT JOIN " . TABLE_ORDERS_STATUS_HISTORY_TRANSACTIONS . " ot 
                        ON (o.orders_status_history_id = ot.orders_status_history_id) 
                    WHERE o.orders_id = '" . (int)$oID . "'" . $additional_query . " 
                    ORDER BY ot.orders_status_history_id ASC";
                                            
      $orders_history_query = tep_db_query($oh_query);
      $transactions = array();
      if (tep_db_num_rows($orders_history_query) > 0) {
        while ($history = tep_db_fetch_array($orders_history_query)) {
          if (($include_refunds || (!$include_refunds && $history['transaction_type'] != 'REFUND')) && $history['transaction_id'] != '') {
            $transactions[] = $history;
          }         
        }
      }
      
      return $transactions;
    }
    
    function add_javascript() {
      if (!$this->enabled) return false;
?>
    <script type="text/javascript">
      function paypal_wpp_popup(action) {
        var w = 400;
        var h = 300;
        
        if (action == 'charge') {
        <?php
          if (MODULE_PAYMENT_PAYPAL_DP_UK_ENABLED == 'Yes') {
            echo 'h = 500;';
          } else {
            echo 'h = 380;';
          }
          if (MODULE_PAYMENT_PAYPAL_DP_CC_ENABLE == 'Yes') {
            echo 'alert("This feature is unavailable with the Cardinal Commerce 3D Secure feature enabled.");';
            echo 'return false;';
          }
        ?>
        }      

        var ScreenW = self.screen.width;
        var ScreenH = self.screen.height;

        var leftPos = (ScreenW/2)-(w/2);
        var topPos = (ScreenH/2)-(h/2);

        var paypal_wpp_window = window.open('<?php echo DIR_WS_INCLUDES; ?>paypal_wpp/paypal_wpp_'+action+'.php?oID='+<?php echo (int)$_GET['oID']; ?>, 'paypal_wpp_window', 'toolbar=0,scrollbars=0,location=0,statusbar=0,menubar=0,resizable=0,width='+w+',height='+h+',left='+leftPos+',top='+topPos);
      }
      
      function paypal_wpp_reload() {
        window.location.reload();
      }
    </script>
<?php

    }
    
    function refresh_parent() {
      echo '<html><head><script type="text/javascript">window.opener.paypal_wpp_reload();self.close();</script></head></html>';
      flush();
      die();
    }
    
    function isHTTPS() {
      if($_SERVER['https'] == 1) {
         return true;
      } elseif ($_SERVER['https'] == 'on') {
         return true;
      } elseif ($_SERVER['SERVER_PORT'] == 443) {
         return true;
      } else {
        return false;
      } 
    }
    
    function display_buttons() {
      if (!$this->enabled) return false;
      
      if (!$this->isHTTPS()) {
        echo '<tr><td class="main" style="padding-bottom: 20px"><b>' . WPP_ERROR_NO_SSL . '</b></td></tr>';
        return false;
      }
      
      $transactions = $this->get_transactions();

      $allow_refund = false;
      $is_charge = false;
      $is_authorization = false;
      foreach ($transactions as $t) {
        if ($t['transaction_type'] == 'AUTHORIZATION' && $t['payment_status'] == 'PENDING') {
          $is_authorization = true;
        }

        if ($t['transaction_type'] == 'CHARGE') {
          $is_charge = true;
        }
      }
      
      if ($is_charge) {
        $ot_refund_query = tep_db_query("SELECT value 
                                         FROM " . TABLE_ORDERS_TOTAL . " 
                                         WHERE orders_id = " . (int)$_GET['oID'] . " 
                                           AND class = 'ot_refund'");
                                           
        if (tep_db_num_rows($ot_refund_query) > 0) {
          $ot_refund = tep_db_fetch_array($ot_refund_query);
          
          $ot_query = tep_db_query("SELECT value 
                                    FROM " . TABLE_ORDERS_TOTAL . " 
                                    WHERE orders_id = " . (int)$_GET['oID'] . " 
                                      AND class = 'ot_total'");
          $ot = tep_db_fetch_array($ot_query);
          
          if ($ot['value'] > 0) {
            $allow_refund = true;
          }
        } else {
          $allow_refund = true;
        }
      }
?>
    <tr>
      <td>
      <?php if ($is_authorization) { ?>
      <a href="javascript:void(0);" onclick="paypal_wpp_popup('capture')"><img src="<?php echo DIR_WS_INCLUDES; ?>paypal_wpp/images/button_capture_funds.gif" border="0"></a>&nbsp;
      <?php 
            } 
            if ($is_charge) {
      ?>
      <a href="javascript:void(0);" onclick="paypal_wpp_popup('refund')"><img src="<?php echo DIR_WS_INCLUDES; ?>paypal_wpp/images/button_issue_refund.gif" border="0"></a>&nbsp;
      <?php } ?>
      <a href="javascript:void(0);" onclick="paypal_wpp_popup('charge')"><img src="<?php echo DIR_WS_INCLUDES; ?>paypal_wpp/images/button_add_charge.gif" border="0"></a></td>
    </tr>
<?php
    }
    
    function get_transaction_info($status_id) {
      $status_id = preg_replace('/[^0-9]/', '', $status_id);

      if ($status_id < 1) return false;
      
      $transaction_query = tep_db_query(
        "SELECT transaction_id," .
        "       transaction_type," .
        "       payment_type," .
        "       payment_status," .
        "       module_code," .
        "       transaction_avs," .
        "       transaction_cvv2," .
        "       transaction_msgs " .
        "FROM " . TABLE_ORDERS_STATUS_HISTORY_TRANSACTIONS . " " .
        "WHERE orders_status_history_id = " . (int)$status_id . " " .
        "LIMIT 1"
      );
                                         
      if (tep_db_num_rows($transaction_query) < 1) return false;
      
      $transaction = tep_db_fetch_array($transaction_query);

      $transaction_info = '';

      if ($transaction['transaction_id']) {
        $transaction_info = "Transaction ID: " .
          "<a href=\"https://www.paypal.com/" . (MODULE_PAYMENT_PAYPAL_DP_UK_ENABLED == 'Yes' ? 'uk' : 'us') . 
          "/cgi-bin/webscr?cmd=_view-a-trans&id=" . $transaction['transaction_id'] . 
          "\" target=\"_BLANK\" style=\"font-weight: bold\">" . 
          $transaction['transaction_id'] . "</a><br />";
      }
      
      if ($transaction['transaction_type'])
        $transaction_info .= "Transaction Type: " . $transaction['transaction_type'] . "<br />";
        
      if ($transaction['payment_type'])
        $transaction_info .= "Payment Type: " . $transaction['payment_type'] . "<br />";
        
      if ($transaction['payment_status'])
        $transaction_info .= "Payment Status: <b>" . $transaction['payment_status'] . "</b><br />";
        
      if ($transaction['transaction_avs'])
        $transaction_info .= "AVS Code: " . $transaction['transaction_avs'] . "<br />";
        
      if ($transaction['transaction_cvv2'])
        $transaction_info .= "CVV2 Code: " . $transaction['transaction_cvv2'] . "<br />";

      if ($transaction['transaction_msgs'])
        $transaction_info .= "Messages: " . $transaction['transaction_msgs'] . "<br />";
        
      return $transaction_info;
    }
  }
  
  $paypal_wpp = new paypal_wpp_admin;
  
  //Catch actions
  switch ($_GET['action']) {
    case 'refund':
      $paypal_wpp->case_refund();
      break;
    case 'charge':
      $paypal_wpp->case_charge();
      break;
    case 'capture':
      $paypal_wpp->case_capture();
      break;
  }
  
?>