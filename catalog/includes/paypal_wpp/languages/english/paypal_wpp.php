<?php
/*
  $Id: paypal_wpp.php,v 1.0.0 Brian Burton brian [at] dynamoeffects [dot] com Exp $

  Copyright (c) 2008 Brian Burton - brian [at] dynamoeffects [dot] com

  Released under the GNU General Public License
*/
  define('TEXT_PAYPALWPP_EC_HEADER', 'Fast, Secure Checkout with PayPal');
  define('TEXT_PAYPALWPP_EC_BUTTON_TEXT', 'Save time. Checkout securely. Pay without sharing your financial information.');
  
  define('MODULE_PAYMENT_PAYPAL_DP_TEXT_TITLE', 'PayPal Direct Payment');
  define('MODULE_PAYMENT_PAYPAL_EC_TEXT_TITLE', 'PayPal Express Checkout');
  define('EMAIL_EC_ACCOUNT_INFORMATION', 'Thank you for using PayPal Express Checkout!  To make your next visit with us even smoother, an account has been automatically created for you.  Your new login information has been included below:' . "\n\n");  

  define('TEXT_PAYPALWPP_EC_SWITCH_METHOD_1', 'You\'re currently checking out with PayPal Express Checkout!');
  define('TEXT_PAYPALWPP_EC_SWITCH_METHOD_2', 'Click Here to choose another payment method.');
  
  define('TEXT_PAYPALWPP_IPN_PENDING_COMMENT', 'The status of your payment is "Pending" for the following reason:');
  define('TEXT_PAYPALWPP_IPN_REVERSED_COMMENT', 'The status of your payment is "Reversed" or "Refunded" for the following reason:');
  define('TEXT_PAYPALWPP_IPN_COMPLETED_COMMENT', 'The status of your payment is "Completed."');
  
  define('TEXT_PAYPALWPP_ERROR_PAYMENT_CLASS', 'It appears that you are missing modifications within /includes/classes/payment.php.  Please reference the installation guide for assistance.');
  
  define('MODULE_PAYMENT_PAYPAL_DP_TEXT_ERROR_COUNTRY', 'Unfortunately the country of the address you selected is not currently one that we offer service to.  If you have any questions, please feel free to contact us.');
  
  define('TEXT_PAYPALWPP_3DS_SUBMITTING', 'You are now being sent to your bank\'s website to completle the checkout process.');
  define('TEXT_PAYPALWPP_3DS_AUTH_SUCCESS', 'Security Authentication Successful!');
  define('TEXT_PAYPALWPP_3DS_AUTH_RETURNING_TO_CHECKOUT', 'Your order is now being processed.');
?>