<?php
/*
  $Id: paypal_wpp.php,v 1.0.0 Brian Burton brian [at] dynamoeffects [dot] com Exp $

  Copyright (c) 2008 Brian Burton - brian [at] dynamoeffects [dot] com

  Released under the GNU General Public License
*/
  chdir('../../');
  include('includes/application_top.php');
  include(DIR_FS_CATALOG . DIR_WS_INCLUDES . 'configure.php');
  
  include(DIR_WS_CLASSES . 'order.php');
  include(DIR_WS_INCLUDES . 'paypal_wpp/paypal_wpp_include.php');
  $paypal_wpp = new paypal_wpp_admin;
  
  $transactions = $paypal_wpp->get_transactions();
?>
<html>
  <head>
    <title><?php echo WPP_CHARGE_TITLE; ?></title>
    <style type="text/css">
      body {
        margin: 0;
      }
      table, input, select {
        font: 11px arial, verdana;
      }
      td.window_header {
        background-color: #084482;
        color: #fff;
        font-size: 18px;
        text-align:center;
        padding: 10px 0;
      }
    </style>
  </head>
  <body>
    <form name="charge" action="<?php echo tep_href_link(DIR_WS_INCLUDES . 'paypal_wpp/paypal_wpp_charge.php', 'oID=' . (int)$_GET['oID'] . '&action=charge', 'SSL'); ?>" target="_top" method="POST">
        <input type="hidden" name="order_status" value="<?php echo $orders_history['orders_status_id']; ?>">
        <table border="0" width="100%" cellspacing="0" cellpadding="5">
          <tr>
            <td colspan="2" class="window_header"><?php echo WPP_CHARGE_TITLE; ?></td>
          </tr>
          <tr>
            <td class="smallText" align="right"><?php echo WPP_AMOUNT_TO_CHARGE . ' ' . $paypal_wpp->get_currency_symbol(); ?></td>
            <td class="smallText"><input type="text" name="paypalwpp_amount" id="refund_amount" maxlength="7" style="width: 70px; text-align: right"></td>
          </tr>
<?php
  $selection = $paypal_wpp->selection();
  
  foreach ($selection['fields'] as $field) {
    echo '<tr>';
    echo '  <td align="right">' . $field['title'] . '</td>';
    echo '  <td>' . $field['field'] . '</td>';
    echo '</tr>';
  }
  
?>
          <tr>
            <td colspan="2"><?php echo WPP_COMMENTS; ?><br /><textarea name="paypalwpp_comments" style="width:100%; height: 50px"></textarea></td>
          </tr>
          <tr>
            <td align="center" colspan="2"><input type="submit" name="charge_submit" value="<?php echo WPP_CHARGE_SUBMIT; ?>">&nbsp;<input type="button" name="cancel" value="<?php echo WPP_CANCEL; ?>" onclick="window.close();"></td>
          </tr>
        </table>
      </form>
  </body>
</html>