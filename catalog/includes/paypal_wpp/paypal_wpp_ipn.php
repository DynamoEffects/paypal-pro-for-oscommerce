<?php
/*
  $Id: paypal_wpp.php,v 1.0.0 Brian Burton brian [at] dynamoeffects [dot] com Exp $

  Copyright (c) 2008 Brian Burton - brian [at] dynamoeffects [dot] com

  Released under the GNU General Public License
*/

  if (MODULE_PAYMENT_PAYPAL_DP_SERVER == 'sandbox') {
    define("PAYPAL_URL", "https://api.sandbox.paypal.com/2.0/"); 
  } else {
    define("PAYPAL_URL", "https://api.paypal.com/2.0/"); 
  }
  
  define('PAYPAL_WPP_IPN_DEBUG', false);

  class paypal_wpp_ipn {
    var $started;
    var $data;
    var $response;
    var $paypal_response;

    function paypal_wpp_ipn() {}
    
    /*
     * Displays debug messages to screen if PAYPAL_WPP_IPN_DEBUG is set to true
     */
    function debug($msg) {
      if (PAYPAL_WPP_IPN_DEBUG) {
        echo (int)(date("U") - $this->started) . ' second(s): ' . $msg . '<br />';
        flush();
      }
    }
    
    function execute() {
      $this->started = date("U");
      /*
       * Store the post data in a local variable
       */
      $this->data = $_POST;

      $this->debug('Serializing POST Begin');
      /*
       * Generate the post string that will be sent back to PayPal
       */
      $this->serialize_post();
      
      $this->debug('Serializing POST Completed');
      
      $this->debug('Confirm Transaction');
      
      /*
       * Confirm the transaction with PayPal
       */
      if ($this->confirm_transaction()) {
        $this->debug('Transaction Confirmed');
        
        $this->debug('Begin Handling Response');
        /*
         * Handle the resposne from PayPal
         */
        $this->handle_response();
      }
      
      $this->debug('That\'s all folks!');
      /*
       * Die since page output is not necessary
       */
      die();
    }

    /*
     * Serialize the data that PayPal sent so that it can be sent right back
     */
    function serialize_post() {
    
      $this->response = 'cmd=_notify-validate';
      
      foreach ($this->data as $key => $val) {
        $this->response .= '&' . $key . '=' . urlencode(stripslashes($val));
      }
      
    }
    
    function confirm_transaction() {
      /*
       * cURL should be installed since it's necessary for the module to work
       */
      if (!function_exists('curl_init')) {
        return false;
      }
      
      $ch = @curl_init();

      curl_setopt($ch, CURLOPT_URL, 'https://' . PAYPAL_URL . '/cgi-bin/webscr');
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $this->response);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_HEADER, false);
      curl_setopt($ch, CURLOPT_TIMEOUT, 180);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

      $this->paypal_response = @curl_exec($ch);
      $this->paypal_response = strtoupper($this->paypal_response);
      
      $errors = curl_error($ch);
      
      @curl_close($ch);
      
      if ($errors != '') {
        $this->debug('Curl Errors!: ' . $errors);
        return false;
      }
      
      $this->debug('PayPal Returned: ' . $this->paypal_response);
      
      return true;
    }
    
    function handle_response() {
      /*
       * The transaction has been verified, so now
       * update the appropriate order if necessary
       */
      if ($this->paypal_response == 'VERIFIED' && $this->data['txn_id'] != '' && $this->data['payment_status'] != '') {
      
        $this->debug('PayPal response has been verified');
        
        /*
         * First, find the correct order and only if the payment status is different
         * since we don't need to update orders we already know about.
         */
        $order_query = tep_db_query("SELECT o.orders_id,
                                            o.orders_status,
                                            oh.comments,
                                            oht.orders_status_history_id,
                                            oht.payment_status,
                                            oht.transaction_msgs 
                                     FROM " . TABLE_ORDERS_STATUS_HISTORY . " oh 
                                       LEFT JOIN " . TABLE_ORDERS . " o  
                                         ON (oh.orders_id = o.orders_id) 
                                       LEFT JOIN " . TABLE_ORDERS_STATUS_HISTORY_TRANSACTIONS . " oht 
                                         ON (oh.orders_status_history_id = oht.orders_status_history_id) 
                                     WHERE oht.transaction_id = '" . tep_db_input($this->data['txn_id']) . "' 
                                       AND oht.payment_status != '" . strtoupper(tep_db_input($this->data['payment_status'])) . "' 
                                     LIMIT 1");
         
        /*
         * A matching order has been found!  Wooo!
         */
        if (tep_db_num_rows($order_query) > 0) {
        
          $this->debug('Matching order found!');
          
          $order_info = tep_db_fetch_array($order_query);
          
          $order_details = array('orders_status_history_id' => $order_info['orders_status_history_id'],
                                 'orders_status_id' => $order_info['orders_status'],
                                 'comments' => $order_info['comments'],
                                 'transaction_id' => $this->data['txn_id'],
                                 'payment_status' => $order_info['payment_status'],
                                 'transaction_msgs' => $order_info['transaction_msgs']);

          $order_details['payment_type'] = $this->data['payment_type'];
          $order_details['payment_status'] = strtoupper($this->data['payment_status']);
                                 
          /*
           * If the transaction is pending, refunded, or reversed, find out why
           */
          switch (strtoupper($this->data['payment_status'])) {
            case 'NONE':
            case 'PENDING':
            
              $order_details['orders_status_id'] = MODULE_PAYMENT_PAYPAL_DP_PENDING_ORDER_STATUS_ID;
              $order_details['payment_status'] = strtoupper($this->data['payment_status']);
              
              if ($order_details['transaction_msgs'] != '') $order_details['transaction_msgs'] .= '<br />';
              $order_details['transaction_msgs'] = $this->data['pending_reason'];
              
              if ($order_details['comments'] != '') $order_details['comments'] .= '<br />';
              $order_details['comments'] .= TEXT_PAYPALWPP_IPN_PENDING_COMMENT . ' ' . $this->data['pending_reason'];

              break;
              
            case 'CANCELED-REVERSAL':
            case 'DENIED':
            case 'EXPIRED':
            case 'FAILED':
            case 'REFUNDED':
            case 'REVERSED':
            case 'VOIDED':
              
              $order_details['orders_status_id'] = MODULE_PAYMENT_PAYPAL_DP_REVERSED_ORDER_STATUS_ID;
              $order_details['payment_status'] = strtoupper($this->data['payment_status']);
              
              if ($order_details['transaction_msgs'] != '') $order_details['transaction_msgs'] .= '<br />';
              $order_details['transaction_msgs'] = $this->data['reason_code'];
              
              if ($order_details['comments'] != '') $order_details['comments'] .= '<br />';
              $order_details['comments'] .= TEXT_PAYPALWPP_IPN_REVERSED_COMMENT . ' ' . $this->data['reason_code'];
              
              break;
            case 'PROCESSED':
            case 'COMPLETED':
            
              $order_details['payment_status'] = strtoupper($this->data['payment_status']);
              $order_details['orders_status_id'] = MODULE_PAYMENT_PAYPAL_DP_COMPLETED_ORDER_STATUS_ID;
              
              if ($order_details['comments'] != '') $order_details['comments'] .= '<br />';
              $order_details['comments'] .= TEXT_PAYPALWPP_IPN_COMPLETED_COMMENT;
              
              break;
          }

          $this->debug('Saving Transaction');
          
          $this->save_transaction($order_details);
        } else {
          $this->debug('No matching order found.');
        }
      }
    }
    
    /*
     * Save the transaction in the orders_status_history and orders_status_history_transactions tables
     */
    function save_transaction($order_details) {
      
      /*
       * Update orders_status_history_transactions
       */

      tep_db_query("UPDATE " . TABLE_ORDERS_STATUS_HISTORY_TRANSACTIONS . " 
                    SET `payment_status` = '" . tep_db_input(strtoupper($order_details['payment_status'])) . "',
                        `transaction_msgs` = '" . tep_db_input($order_details['transaction_msgs']) . "' 
                    WHERE orders_status_history_id = " . (int)$order_details['orders_status_history_id'] . " 
                    LIMIT 1");
      
      if (@mysql_error() == '') {
        $this->debug('orders_status_history_transactions Saved Successfully');
      } else {
        $this->debug('orders_status_history_transactions Save Failed!');
      }
      
      tep_db_query("UPDATE " . TABLE_ORDERS_STATUS_HISTORY . " 
                    SET `orders_status_id` = '" . (int)$order_details['orders_status_id'] . "',
                        `date_added` = NOW(),
                        `comments` = '" . tep_db_input($order_details['comments']) . "' 
                    WHERE orders_status_history_id = " . (int)$order_details['orders_status_history_id'] . " 
                    LIMIT 1");
                    
      if (@mysql_error() == '') {
        $this->debug('orders_status_history Saved Successfully');
      } else {
        $this->debug('orders_status_history Save Failed!');
      }        
                    
      tep_db_query("UPDATE " . TABLE_ORDERS . " 
                    SET `orders_status` = " . (int)$order_details['orders_status_id'] . " 
                    WHERE orders_id = " . (int)$order_details['orders_id'] . " 
                    LIMIT 1");
                    
      if (@mysql_error() == '') {
        $this->debug('orders Saved Successfully');
      } else {
        $this->debug('orders Save Failed!');
      }
                    
      return true;
    }
  }

  if (count($_POST) > 0) {
    $paypal_wpp_ipn = new paypal_wpp_ipn;
    $paypal_wpp_ipn->execute();
  }
?>