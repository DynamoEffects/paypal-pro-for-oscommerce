<?php
/*
  $Id: paypal_wpp.php,v 1.0.7 Brian Burton brian [at] dynamoeffects [dot] com Exp $

  Copyright (c) 2008, 2009 Brian Burton - brian [at] dynamoeffects [dot] com

  Released under the GNU General Public License
*/

  define('MODULE_PAYMENT_PAYPAL_DP_TEXT_TITLE', 'PayPal Direct Payment');
  define('MODULE_PAYMENT_PAYPAL_EC_TEXT_TITLE', 'PayPal Express Checkout');
  define('MODULE_PAYMENT_PAYPAL_DP_TEXT_DESCRIPTION', '<center><b><h2>PayPal Pro for osCommerce 2.2MS2+</h2>Direct Payment & Express Checkout<br><br><i>Developed and maintained by:</i><br><a href="http://forums.oscommerce.com/index.php?showuser=80233">Brian Burton (dynamoeffects)</a></b></center>');
  define('MODULE_PAYMENT_PAYPAL_DP_ERROR_HEADING', 'We\'re sorry, but we were unable to process your credit card.');
  define('MODULE_PAYMENT_PAYPAL_DP_TEXT_CARD_ERROR', 'The credit card information you entered contains an error.  Please check it and try again.');
  define('MODULE_PAYMENT_PAYPAL_DP_TEXT_CREDIT_CARD_FIRSTNAME', 'First Name on Credit Card:');
  define('MODULE_PAYMENT_PAYPAL_DP_TEXT_CREDIT_CARD_LASTNAME', 'Last Name on Credit Card:');
  define('MODULE_PAYMENT_PAYPAL_DP_TEXT_CREDIT_CARD_TYPE', 'Credit Card Type:');
  define('MODULE_PAYMENT_PAYPAL_DP_TEXT_CREDIT_CARD_NUMBER', 'Credit Card Number:');
  define('MODULE_PAYMENT_PAYPAL_DP_TEXT_CREDIT_CARD_EXPIRES', 'Credit Card Expiry Date:');
  define('MODULE_PAYMENT_PAYPAL_DP_TEXT_CREDIT_CARD_CHECKNUMBER', 'Credit Card Security Code:');
  define('MODULE_PAYMENT_PAYPAL_DP_TEXT_CREDIT_CARD_CHECKNUMBER_LOCATION', 'What\'s this?');
  define('MODULE_PAYMENT_PAYPAL_DP_TEXT_CREDIT_CARD_START_MONTH', 'Solo/Maestro Start Month:');
  define('MODULE_PAYMENT_PAYPAL_DP_TEXT_CREDIT_CARD_START_YEAR', 'Solo/Maestro Start Year:');
  define('MODULE_PAYMENT_PAYPAL_DP_TEXT_CREDIT_CARD_ISSUE_NUMBER', 'Solo/Maestro Issue Number:');
  define('MODULE_PAYMENT_PAYPAL_DP_TEXT_CREDIT_CARD_SWITCHSOLO_ONLY', '(required only for Maestro/Solo cards)');
  define('MODULE_PAYMENT_PAYPAL_DP_TEXT_DECLINED', 'Your credit card was declined. Please try another card or contact your bank for more info.<br><br>');
  define('MODULE_PAYMENT_PAYPAL_DP_INVALID_RESPONSE', 'PayPal returned invalid or incomplete data to complete your order.  Please try again or select an alternate payment method.<br><br>');
  define('MODULE_PAYMENT_PAYPAL_DP_TEXT_GEN_ERROR', 'An error occured when we tried to contact PayPal\'s servers.<br><br>');
  define('MODULE_PAYMENT_PAYPAL_DP_TEXT_ERROR', 'An error occured when we tried to process your credit card.<br><br>');
  define('MODULE_PAYMENT_PAYPAL_DP_TEXT_UNVERIFIED', 'To maintain a high level of security, customers using Express Checkout must be verified PayPal customers.  Please either verify your account at PayPal or choose another means of payment.');
  define('MODULE_PAYMENT_PAYPAL_DP_TEXT_BAD_CARD', 'We apologize for the inconvenience, but PayPal only accepts Visa, Master Card, Discover, and American Express.  Please use a different credit card.');
  define('MODULE_PAYMENT_PAYPAL_DP_TEXT_BAD_LOGIN', 'There was a problem validating your account.  Please try again.');
  define('MODULE_PAYMENT_PAYPAL_DP_TEXT_JS_CC_OWNER', '* The owner\'s name of the credit card must be at least ' . CC_OWNER_MIN_LENGTH . ' characters.\n');
  define('MODULE_PAYMENT_PAYPAL_DP_TEXT_JS_CC_NUMBER', '* The credit card number must be at least ' . CC_NUMBER_MIN_LENGTH . ' characters.\n');
  define('MODULE_PAYMENT_PAYPAL_DP_TEXT_JS_CC_CVV2', '* You must enter the CVV2 number found on your card.\n');
  define('MODULE_PAYMENT_PAYPAL_DP_TEXT_EC_HEADER', 'Fast, Secure Checkout with PayPal:');
  define('MODULE_PAYMENT_PAYPAL_DP_TEXT_BUTTON_TEXT', 'Save time. Checkout securely.<br>Pay without sharing your financial information.');
  define('MODULE_PAYMENT_PAYPAL_DP_TEXT_STATE_ERROR', 'The state assigned to your account is not valid.  Please go into your account settings and change it.');
  define('MODULE_PAYMENT_PAYPAL_DP_MISSING_XML', 'PayPal WPP installation incomplete!  There should be XML files located in ' . DIR_FS_CATALOG . DIR_WS_INCLUDES . 'paypal_wpp/xml/ !');
  define('MODULE_PAYMENT_PAYPAL_DP_CURL_NOT_INSTALLED', 'cURL, which is required by the PayPal Website Payments Pro module, is not present.  Please contact your webhost and request that it be installed.');
  define('MODULE_PAYMENT_PAYPAL_DP_CERT_NOT_INSTALLED', 'Your Website Payments Pro API certificate could not be found.  Please check the location in your administration section.');
  define('MODULE_PAYMENT_PAYPAL_DP_GEN_ERROR', 'Payment not processed!');
  define('MODULE_PAYMENT_PAYPAL_EC_ALTERNATIVE', '<hr /><p align="center">or you may use</p><hr />');
  define('MODULE_PAYMENT_PAYPAL_DP_BUG_1629', 'Your store has a bug in checkout_process.php that prevents this module from working.  Please read the Troubleshooting section of the READ_ME.htm that came with the module for more information.');
  define('MODULE_PAYMENT_PAYPAL_EC_TEXT_DECLINED', 'Your PayPal transaction was declined. Please select a different method.<br><br>');
?>