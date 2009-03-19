<?php
/*
  $Id: paypal_wpp.php,v 1.0.0 Brian Burton brian [at] dynamoeffects [dot] com Exp $

  Copyright (c) 2008 Brian Burton - brian [at] dynamoeffects [dot] com

  Released under the GNU General Public License
*/
  include(DIR_FS_CATALOG . DIR_WS_LANGUAGES . $language . '/modules/payment/paypal_wpp.php');
  define('TABLE_HEADING_TRANSACTION_INFO', 'Transaction Details');
  define('WPP_ERROR_NO_TRANS_ID', 'A valid transaction ID was not found.');
  define('WPP_ERROR_BAD_CURRENCY', 'A valid currency was not entered.');
  define('WPP_ERROR_SELECT_REFUND_TYPE', 'Select a refund type: Full or Partial.');
  define('WPP_ERROR_FULL_MISSING_AMOUNT', 'To do a full refund, enter the entire order total.');
  define('WPP_ERROR_INVALID_REFUND_AMOUNT', 'Enter a valid amount to refund.');
  define('WPP_ERROR_REFUND_FAILED_BECAUSE', 'Refund Failed for the following reason(s):');
  define('WPP_SUCCESS_REFUND', 'Customer successfully refunded!');
  define('WPP_ERROR_INVALID_CHARGE_AMOUNT', 'Enter a valid amount to charge.');
  define('WPP_ERROR_INCOMPLETE_CARDHOLDER_NAME', 'Enter the full name of the cardholder as it appears on the card.');
  define('WPP_ERROR_CHARGE_FAILED_BECAUSE', 'The charge could not be completed for the following reason(s):');
  define('WPP_SUCCESS_CHARGE', 'Transaction completed successfully!');
  define('WPP_SUCCESS_CAPTURE', 'Transaction successfully captured!');
  define('WPP_CHARGE_NAME', 'Customer Service Charge');
  define('TEXT_CCVAL_ERROR_INVALID_DATE', 'The expiry date entered for the credit card is invalid. Please check the date and try again.');
  define('TEXT_CCVAL_ERROR_INVALID_NUMBER', 'The credit card number entered is invalid. Please check the number and try again.');
  define('TEXT_CCVAL_ERROR_UNKNOWN_CARD', 'The first four digits of the number entered are: %s. If that number is correct, we do not accept that type of credit card. If it is wrong, please try again.');
  define('WPP_TRANSACTION', 'Transaction:');
  define('WPP_REFUND_TYPE', 'Refund Type:');
  define('WPP_AMOUNT_OPTIONAL', '(Required only for partial amounts)');
  define('WPP_REFUND_ISSUED', 'refund issued in the amount of');
  define('WPP_FULL_REFUND_ISSUED', 'refund issued');
  define('WPP_CHARGE_ISSUED', 'Charge issued in the amount of');
  define('WPP_CAPTURE_ISSUED', 'Capture issued in the amount of');
  define('WPP_AMOUNT_TO_CHARGE', 'Amount to Charge:');
  define('WPP_FIRST_NAME', 'First Name on Card:');
  define('WPP_LAST_NAME', 'Last Name on Card:');
  define('WPP_CC_TYPE', 'Card Type:');
  define('WPP_CC_NUMBER', 'Card Number:');
  define('WPP_CC_EXPIRATION_DATE', 'Expiration Date:');
  define('WPP_CC_CVV2', 'CVV2 Number:');
  define('WPP_COMMENTS', 'Comments: (Optional)');
  define('WPP_CHARGE_SUBMIT', 'Charge Card');
  define('WPP_REFUND_PARTIAL', 'Partial');
  define('WPP_REFUND_FULL', 'Full');
  define('WPP_REFUND_AMOUNT', 'Refund Amount:');
  define('WPP_CAPTURE_AMOUNT', 'Capture Amount:');
  define('WPP_CHARGE_AMOUNT', 'Charge Amount:');
  define('WPP_SUBMIT_REFUND', 'Refund');
  define('WPP_SUBMIT_CAPTURE', 'Capture');
  define('WPP_REFUND_TITLE', 'PayPal Pro - Issue Refund');
  define('WPP_CHARGE_TITLE', 'PayPal Pro - Add Charge');
  define('WPP_CANCEL', 'Cancel');
  define('WPP_ORDER_STATUS', 'Order Status:');
  define('WPP_CAPTURE_TITLE', 'PayPal Pro - Capture Funds');
  define('WPP_ERROR_NO_SSL', 'You must access your administration section through HTTPS before you can use the advanced PayPal Pro features.');
  define('WPP_ERROR_COUNTRY_NOT_FOUND', 'The user\'s country could not be determined.  The operation has failed.');
?>