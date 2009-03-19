<?php 
/*
  $Id: wpp_diagnostics.php for v1.0.0+ Brian Burton brian [at] dynamoeffects [dot] com Exp $

  Copyright (c) 2008 Brian Burton - brian [at] dynamoeffects [dot] com

  Released under the GNU General Public License
*/
  $ssl_error = false;
  $osc_error = false;
  
  
  if (!file_exists('includes/application_top.php')) {
    $osc_error = true;
  } else {
    require('includes/application_top.php');
  }

  if ($_SERVER['HTTPS'] != "on") {  
    if (ENABLE_SSL == true && HTTPS_SERVER != '' && $_GET['redirected'] != '1' && !$osc_error) {
      header("Location: " . tep_href_link(basename($_SERVER['PHP_SELF']), 'redirected=1', 'SSL'));
    } else {
      $ssl_error = true;
    }
  }

  function wpp_parse_xml($text) {
    $reg_exp = '/<(\w+)[^>]*>(.*?)<\/\\1>/s';
    preg_match_all($reg_exp, $text, $match);
    foreach ($match[1] as $key=>$val) {
      if ( preg_match($reg_exp, $match[2][$key]) ) {
          $array[$val][] = wpp_parse_xml($match[2][$key]);
      } else {
          $array[$val] = $match[2][$key];
      }
    }
    return $array;
  } 

  if (!$ssl_error && !$osc_error) {
    $php_check = true;
    $curl_exists = true;
    $curl_works = true;
    $cert_exists = true;
    $cert_htaccess_exists = true;
    $user_exists = true;
    $pass_exists = true;
    $db_cols_exists = true;
    $xml_exists = array();
    $curl_errors = '';
    $payment_class = true;
    $checkout_process_bug = true;
    
    $all_good = true;
    
    $xml_documents = array('doDirectPayment.xml',
                           'doExpressCheckout.xml',
                           'getExpressCheckoutDetails.xml',
                           'setExpressCheckout.xml',
                           'transactionSearch.xml',
                           'doCapture.xml',
                           'getTransactionDetails.xml',
                           'refundTransaction.xml');

    if (function_exists('version_compare')) {
      if (version_compare(phpversion(), '4.3.0') < 0) {
        $php_check = false;
        $all_good = false;
      }
    } else {
      $php_check = false;
      $all_good = false;
    }
    
    if (!function_exists('curl_init')) {
      $curl_exists = false;
      $all_good = false;
    }

    if ($curl_exists) {
      $ch = curl_init();
      @curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      @curl_setopt($ch, CURLOPT_URL, "https://www.paypal.com");
      if (trim(MODULE_PAYMENT_PAYPAL_DP_PROXY) != '') {
        @curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
        @curl_setopt($ch, CURLOPT_PROXY, MODULE_PAYMENT_PAYPAL_DP_PROXY);
      }
      @curl_exec($ch);
      if (@curl_errno($ch) != 0) {
        $curl_works = false;
        $all_good = false;
        $curl_errors = curl_error($ch) . ' (Error No. ' . curl_errno($ch) . ')';
      }
      curl_close($ch);
    }

    if (!file_exists(MODULE_PAYMENT_PAYPAL_DP_CERT_PATH)) {
      $cert_exists = false;
      $all_good = false;
    }
    if (!file_exists(dirname(MODULE_PAYMENT_PAYPAL_DP_CERT_PATH) . '/.htaccess')) {
      $cert_htaccess_exists = false;
      $all_good = false;
    }
    if (trim(MODULE_PAYMENT_PAYPAL_DP_API_USERNAME) == '') {
      $user_exists = false;
      $all_good = false;
    }
    if (trim(MODULE_PAYMENT_PAYPAL_DP_API_PASSWORD) == '') {
      $pass_exists = false;
      $all_good = false;
    }
    
    require_once(DIR_WS_CLASSES . 'payment.php');
    $payment = new payment;
    if (!@method_exists($payment, 'ec_step1') || !@method_exists($payment, 'ec_step1')) {
		$payment_class = false;
		$all_good = false;
    }
    
    $checkout_process = file_get_contents('checkout_process.php');
    if (strpos($checkout_process, '$order_totals = $order_total_modules->process()') > strpos($checkout_process, '$payment_modules->before_process()')) {
    	$checkout_process_bug = false;
    	$all_good = false;
    }
    
    $col_query = tep_db_query("SHOW COLUMNS FROM customers");
    $found = array(false, false);
    while ($col = tep_db_fetch_array($col_query)) {
      if ($col['Field'] == 'customers_paypal_payerid') $found[0] = true;
      if ($col['Field'] == 'customers_paypal_ec') $found[1] = true;
    }
    if ($found[0] == false && $found[1] == false) {
      $db_cols_exists = false;
      $all_good = false;
    }
    
    $table_query = tep_db_query("SHOW TABLES LIKE 'orders_status_history_transactions'");
    
    if (@tep_db_num_rows($table_query) < 1) {
      $db_cols_exists = false;
      $all_good = false;      
    }
    
    for ($x = 0; $x < count($xml_documents); $x++) {
      
      if (!file_exists(DIR_WS_INCLUDES . 'paypal_wpp/xml/' . $xml_documents[$x])) {
        $xml_exists[$x] = false;
        $all_good = false;
      } else {
        $xml_exists[$x] = true;
      }
    }
  }
?>
<html>
  <head>
    <title>PayPal Website Payments Pro Diagnostics Program by Brian Burton (dynamoeffects)</title>
    <style>
      body { margin: 0px }
      table { font: 12px verdana }
      td.topHeader {font-family:verdana, Arial, Helvetica, Sans-serif; padding: 20px 11px 4px 0px;}
      td.topHeader H2 {font-size:26px; line-height:0px; color:#4B7EAD; margin:0px;}
      td.topHeader H3, td.topHeader H1 {font-size:18px; line-height:15px; color:#365A7C; font-weight:bold; margin:4px 0px 0px 0px;}
      td.good { color: #00FF00; font: 16px "arial black", arial, verdana }
      td.error { color: #FF0000; font: 16px "arial black", arial, verdana }
      td.test_text { font-weight: bold; padding: 4px 0px 4px 0px }
      td.divider { font-size: 1px; height: 1px; padding: 0px; background-color: #EFEFEF }
    </style>
  </head>
  <body>
    <center>
      <table border=0 style="width:768px;text-align: left" cellspacing=0 cellpadding=0>
        <tr>
          <td class="topHeader" valign="middle" colspan="2"><h2>Paypal Website Payments Pro Diagnostics</h2><br><h1>for Version 1.0.0+</h1><br><b>by Brian Burton (<a href="http://forums.oscommerce.com/index.php?showuser=80233" target="_BLANK">dynamoeffects</a>)</b></td>
        </tr>
<?php 
  if ($ssl_error || $osc_error) {
    if ($osc_error) {
?>
        <tr>
          <td style="height: 20px"></td>
        </tr>

        <tr>
          <td colspan="2" class="topHeader" valign="middle" style="border: 2px solid #FF0000; padding: 20px"><center><h2><span style="color: #FF0000">osCommerce Installation Not Found!</span></h2></center><br><h1><span style="line-height: 20px">This script must be placed in the root directory of your osCommerce installation.  If your store is accessed through https://www.yourstore.com/catalog, this script should be uploaded so that it can be accessed by going to https://www.yourstore.com/catalog/wpp_diagnostics.php</span></h1></td>
        </tr>
<?php
    }
    if ($ssl_error) {
?>
        <tr>
          <td style="height: 20px"></td>
        </tr>
        <tr>
          <td colspan="2" class="topHeader" valign="middle" style="border: 2px solid #FF0000; padding: 20px"><center><h2><span style="color: #FF0000">SSL Not Detected!</span></h2></center><br><h1><span style="line-height: 20px">This script must be be accessed through your store's HTTPS URL, but your store is not setup to utilize SSL.<br><br>If you have not yet purchased an SSL certificate, you will need to do so before using this service.<br><br> If you have purchased a certificate and sure that it was installed, update your store's configuration file before continuing.</span></h1></td>
        </tr>
<?php
    }
  } else { 
?>
        <tr>
          <td class="topHeader" valign="middle" colspan="2"><h1>Basic Tests</h1></td>
        </tr>
        <tr><td class="divider" colspan="2"></td></tr>
        <tr>
          <td class="test_text">Using at least PHP 4.3.0?</td>
          <td align="center" class="<?php echo ($php_check ? 'good' : 'error'); ?>"><?php echo ($php_check ? 'Yes' : 'No'); ?></td>
        </tr>
        <tr><td class="divider" colspan="2"></td></tr>
        <tr>
          <td class="test_text">Does your store have an SSL certificate installed and working?</td>
          <td align="center" class="good">Yes</td>
        </tr>
        <tr><td class="divider" colspan="2"></td></tr>
        <tr>
          <td class="test_text">Is cURL installed?</td>
          <td align="center" class="<?php echo ($curl_exists ? 'good' : 'error'); ?>"><?php echo ($curl_exists ? 'Yes' : 'No'); ?></td>
        </tr>
        <tr><td class="divider" colspan="2"></td></tr>
        <?php if ($curl_exists) { ?>
        <tr>
          <td class="test_text">Does cURL work? (Simple HTTPS test)</td>
          <td align="center" class="<?php echo ($curl_works ? 'good' : 'error'); ?>"><?php echo ($curl_works ? 'Yes' : 'No'); ?></td>
        </tr>
        <?php
          if ($curl_errors != '') {
        ?>
        <tr>
          <td class="test_text" colspan="2" style="color: #FF0000; padding-left: 20px"><?php echo $curl_errors; ?></td>
        </tr>
        <?php
          }
        ?>
        <tr><td class="divider" colspan="2"></td></tr>
        <?php } ?>
        <tr>
          <td class="test_text">API Certificate installed?</td>
          <td align="center" class="<?php echo ($cert_exists ? 'good' : 'error'); ?>"><?php echo ($cert_exists ? 'Yes' : 'No'); ?></td>
        </tr>
        <tr><td class="divider" colspan="2"></td></tr>
        <tr>
          <td class="test_text">API Certificate directory protected?</td>
          <td align="center" class="<?php echo ($cert_htaccess_exists ? 'good' : 'error'); ?>"><?php echo ($cert_htaccess_exists ? 'Yes' : 'No'); ?></td>
        </tr>
        <tr><td class="divider" colspan="2"></td></tr>
        <tr>
          <td class="test_text">API Username in place?</td>
          <td align="center" class="<?php echo ($user_exists ? 'good' : 'error'); ?>"><?php echo ($user_exists ? 'Yes' : 'No'); ?></td>
        </tr>
        <tr><td class="divider" colspan="2"></td></tr>
        <tr>
          <td class="test_text">API Password in place?</td>
          <td align="center" class="<?php echo ($pass_exists ? 'good' : 'error'); ?>"><?php echo ($pass_exists ? 'Yes' : 'No'); ?></td>
        </tr>
        <tr><td class="divider" colspan="2"></td></tr>
        <tr>
          <td class="test_text">Database update in place?</td>
          <td align="center" class="<?php echo ($db_cols_exists ? 'good' : 'error'); ?>"><?php echo ($db_cols_exists ? 'Yes' : 'No'); ?></td>
        </tr>
        <tr><td class="divider" colspan="2"></td></tr>
        <tr>
          <td class="test_text">Payment class modifications in place?</td>
          <td align="center" class="<?php echo ($payment_class ? 'good' : 'error'); ?>"><?php echo ($payment_class ? 'Yes' : 'No'); ?></td>
        </tr>
        <tr><td class="divider" colspan="2"></td></tr>
        <tr>
          <td class="test_text">Bug in checkout_process.php fixed? <a href="http://forums.oscommerce.com/index.php?showtopic=80361" target="_BLANK">[Read More]</a></td>
          <td align="center" class="<?php echo ($checkout_process_bug ? 'good' : 'error'); ?>"><?php echo ($checkout_process_bug ? 'Yes' : 'No'); ?></td>
        </tr>
        <tr><td class="divider" colspan="2"></td></tr>
        <?php 
          for ($x = 0; $x < count($xml_documents); $x++) { 
        ?>
        <tr>
          <td class="test_text">XML Document "<?php echo $xml_documents[$x]; ?>" exists?</td>
          <td align="center" class="<?php echo ($xml_exists[$x] == true ? 'good' : 'error'); ?>"><?php echo ($xml_exists[$x] ? 'Yes' : 'No'); ?></td>
        </tr>
        <tr><td class="divider" colspan="2"></td></tr>
        <?php
          }
          flush();

          if ($all_good) {
            $curl_success = false;
            $data = array();
            $data['PAYPAL_USERNAME'] = MODULE_PAYMENT_PAYPAL_DP_API_USERNAME;
            $data['PAYPAL_PASSWORD'] = MODULE_PAYMENT_PAYPAL_DP_API_PASSWORD;
            $data['PAYPAL_VERSION'] = '2.0';
            $data['PAYPAL_PAYMENT_ACTION'] = MODULE_PAYMENT_PAYPAL_DP_PAYMENT_ACTION;
            $data['PAYPAL_MERCHANT_SESSION_ID'] = tep_session_id();
            $data['PAYPAL_IP_ADDRESS'] = $_SERVER['REMOTE_ADDR'];
            $data['PAYPAL_START_DATE'] = date('Y-m-d', mktime(0, 0, 0, date("m"), date("d")-1,  date("Y"))) . 'T00:00:00-0700';
            $data['PAYPAL_PAYER'] = STORE_OWNER_EMAIL_ADDRESS;
            $data['PAYPAL_AMOUNT'] = '1.00';
            $data['PAYPAL_CURRENCY'] = 'USD';
            
            $xml_file = DIR_WS_INCLUDES . 'paypal_wpp/xml/transactionSearch.xml';
            $fp = fopen($xml_file, "r");
            $xml_contents = fread($fp, filesize($xml_file));
            fclose($fp);

            foreach ($data as $k => $v) {
              $xml_contents = str_replace($k, $v, $xml_contents);
            }

            if (MODULE_PAYMENT_PAYPAL_DP_SERVER == 'sandbox') {
              $paypal_url = "https://api.sandbox.paypal.com/2.0/"; 
            } else { 
              $paypal_url = "https://api.paypal.com/2.0/"; 
            } 
            
            $ch = curl_init(); 

            if (trim(MODULE_PAYMENT_PAYPAL_DP_PROXY) != '') {
              curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
              curl_setopt($ch, CURLOPT_PROXY, MODULE_PAYMENT_PAYPAL_DP_PROXY);
            }

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_TIMEOUT, 180);
            curl_setopt($ch, CURLOPT_SSLCERTTYPE, "PEM"); 
            curl_setopt($ch, CURLOPT_SSLCERT, MODULE_PAYMENT_PAYPAL_DP_CERT_PATH);
            curl_setopt($ch, CURLOPT_URL, $paypal_url); 
            curl_setopt($ch, CURLOPT_POST, 1); 
            curl_setopt($ch, CURLOPT_POSTFIELDS, $xml_contents); 
            
            $response = curl_exec($ch);
            $curl_connected = true;
            $curl_valid_response = true;
            $curl_error_message = '';
            
            if (@curl_errno($ch) != 0) {
              $curl_connected = false;
              $curl_error_message = 'Error received: ' . curl_errno($ch) . ': ' . curl_error($ch);
            } else {
              if (strpos($response, 'SOAP-ENV') === false) {
                $curl_valid_response = false;
              }
            }
            curl_close($ch);
            
            $response = wpp_parse_xml($response);
        ?>
        <tr>
          <td class="topHeader" valign="middle" colspan="2"><h1>Advanced Diagnostics</h1></td>
        </tr>
        <tr><td class="divider" colspan="2"></td></tr>
        <tr>
          <td class="test_text">Able to connect to PayPal through cURL?</td>
          <td align="center" class="<?php echo ($curl_connected ? 'good' : 'error'); ?>"><?php echo ($curl_connected ? 'Yes' : 'No'); ?></td>
        </tr>
        <?php
          if ($curl_error_message != '') {
            $failed_installation = true;
        ?>
        <tr>
          <td class="test_text" colspan="2" style="color: #FF0000; padding-left: 20px"><?php echo $curl_error_message; ?></td>
        </tr>
        <?php
          } else {
        ?>
        <tr><td class="divider" colspan="2"></td></tr>
        <tr>
          <td class="test_text">Received a valid response?</td>
          <td align="center" class="<?php echo ($curl_valid_response ? 'good' : 'error'); ?>"><?php echo ($curl_valid_response ? 'Yes' : 'No'); ?></td>
        </tr>
        <tr><td class="divider" colspan="2"></td></tr>
        <?php
            $failed_installation = false;
            if ($curl_valid_response) {
              $errors_received = false;
              if (is_array($response['TransactionSearchResponse'][0]['Errors'])) {
                $errors_received = true;
                $errors = $response['TransactionSearchResponse'][0]['Errors'];
                $error_return = '';
                
                for ($x = 0; $x < count($errors); $x++) {
                  if ($error_return) $error_return .= '<br><br>';
                  if (count($errors) > 1) {
                    $error_return .= 'Error #' . ($x + 1) . ': ';
                  }
                  $error_return .= $errors[$x]['ShortMessage'] . ' (' . $errors[$x]['ErrorCode'] . ')<br>' . $errors[$x]['LongMessage'];
                }
              }
              if ($errors_received) $failed_installation = true;
        ?>
        <tr>
          <td class="test_text">Did PayPal respond without errors? (If not, errors are below)</td>
          <td align="center" class="<?php echo (!$errors_received ? 'good' : 'error'); ?>"><?php echo (!$errors_received ? 'Yes' : 'No'); ?></td>
        </tr>
        <?php    if ($errors_received) { ?>
        <tr>
          <td class="test_text" colspan="2" style="color: #FF0000; padding-left: 20px"><?php echo $error_return; ?></td>
        </tr>
        <?php    } ?>
        <tr><td class="divider" colspan="2"></td></tr>
        <tr>
          <td style="height: 20px"></td>
        </tr>
        <?php
              }
            }
            if ($failed_installation) {
        ?>
        <tr>
          <td colspan="2" class="topHeader" valign="middle" style="border: 2px solid #FF0000; padding: 20px"><center><h2><span style="color: #FF0000">Installation Incomplete!</span></h2></center><br><h1><span style="line-height: 20px">Review and resolve the errors above and run this script again.</span></h1></td>
        </tr>
        <?php
            } else {
        ?>
        <tr>
          <td colspan="2" class="topHeader" valign="middle" style="border: 2px solid #00FF00; padding: 20px"><center><h2><span style="color: #00FF00">Success!</span></h2></center><br><h1><span style="line-height: 20px">Congratulations!  This contribution appears to be correctly installed on your store!<br><br>Please note that if you're still having problems with your installation, it is most likely because you didn't completely integrate all of the code.</span></h1></td>
        </tr>
        <?php
            }
          } else {
        ?>
        <tr>
          <td style="height: 20px"></td>
        </tr>
        <tr>
          <td colspan="2" class="topHeader" valign="middle" style="border: 2px solid #FF0000; padding: 20px"><center><h2><span style="color: #FF0000">Test Stopped!</span></h2></center><br><h1><span style="line-height: 20px">Testing cannot be completed until the above errors are fixed.  After fixing the above errors, run this script again to complete the test.</span></h1></td>
        </tr>
        <?php
          }
        ?>
<?php } ?>
        <tr>
          <td style="height: 20px"></td>
        </tr>
      </table>
    </center>
  </body>
</html>