<?php
  if (@isset($_GET['blank']) && $_GET['blank'] == '1') die('<html></html>');
   
  chdir('../..');
  require('includes/application_top.php');
  
  /* This page should only be accessed if the customer is enrolled 
     in Verified by Visa or MasterCard and information about the
     bank's verification website is stored in the $_SESSION['cardinal_centinel']
     session variable.  */    
  if (!isset($_SESSION['cardinal_centinel']) || !is_array($_SESSION['cardinal_centinel'])) {
    tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL'));
  }

?>
<!doctype html public "-//W3C//DTD HTML 4.01 Transitional//EN">
<html <?php echo HTML_PARAMS; ?>>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=<?php echo CHARSET; ?>">
<title><?php echo TITLE; ?></title>
<base href="<?php echo (($request_type == 'SSL') ? HTTPS_SERVER : HTTP_SERVER) . DIR_WS_CATALOG; ?>">
<link rel="stylesheet" type="text/css" href="stylesheet.css">
<style>
  h1, h2 {font-family: Arial, Verdana, sans-serif}
  #paypal-wpp-3ds {
    width: 100%;
    height: 400px;
    border: 0;
    background-color: #fff;
  }
</style>
</head>
<body marginwidth="0" marginheight="0" topmargin="0" bottommargin="0" leftmargin="0" rightmargin="0">
<?php  if ($_GET['action'] == 'cardinal_centinel_auth') { ?>
  <h1><?php echo TEXT_PAYPALWPP_3DS_AUTH_SUCCESS; ?></h1>
  <h2><?php echo TEXT_PAYPALWPP_3DS_AUTH_RETURNING_TO_CHECKOUT; ?></h2>
  <form name="paypal_wpp_form_3ds" method="post" action="<?php echo tep_href_link(FILENAME_CHECKOUT_PROCESS, '', 'SSL'); ?>" target="_parent">
<?php
    foreach ($_SESSION['cardinal_centinel']['post'] as $key => $val) {
      echo '<input type="hidden" name="' . str_replace('"', '\"', $key) . '" value = "' . str_replace('"', '\"', $val) . '" />' . "\n";
    }
?>
    <noscript>
      <center><input type="submit" name="submit" value="Complete Checkout"></center>
    </noscript>
  </form>
<?php } else {?>
  <!-- header //-->
  <?php require(DIR_WS_INCLUDES . 'header.php'); ?>
  <!-- header_eof //-->

  <!-- body //-->
  <table border="0" width="100%" cellspacing="3" cellpadding="3">
    <tr>
  <!-- body_text //-->
      <td width="100%" height="100%" valign="top">
        <iframe src="<?php echo DIR_WS_INCLUDES . 'paypal_wpp/' . FILENAME_PAYPAL_WPP_3DS; ?>?blank=1" id="paypal-wpp-3ds" name="paypal-wpp-3ds"></iframe>
        <form name="paypal_wpp_form_3ds" method="post" action="<?php echo $_SESSION['cardinal_centinel']['acs_url']; ?>" target="paypal-wpp-3ds">
          <input type="hidden" name="PaReq" value="<?php echo $_SESSION['cardinal_centinel']['payload']; ?>">
          <input type="hidden" name="TermUrl" value="<?php echo tep_href_link(DIR_WS_INCLUDES . 'paypal_wpp/' . FILENAME_PAYPAL_WPP_3DS, 'action=cardinal_centinel_auth', 'SSL'); ?>">
          <input type="hidden" name="MD" value="<?php echo tep_session_id() ?>">
          <noscript>
            <center><input type="submit" name="submit" value="Begin Authentication"></center>
          </noscript>
        </form>
      </td>
  <!-- body_text_eof //-->
    </tr>
  </table>
  <!-- body_eof //-->

  <!-- footer //-->
  <?php require(DIR_WS_INCLUDES . 'footer.php'); ?>
  <!-- footer_eof //-->
  <br>
<?php } ?>
  <script type="text/javascript">
    document.paypal_wpp_form_3ds.submit();
  </script>
</body>
</html>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>