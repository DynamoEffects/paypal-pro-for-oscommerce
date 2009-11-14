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
    <title><?php echo WPP_REFUND_TITLE; ?></title>
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
      input.disabled {
        background-color: #DEDEDE;
        color: #000;
      }
    </style>
    <script type="text/javascript">
      function hideAmount(b) {
        var o = document.getElementById('refund_amount');

        if (b == 'Full') {
          o.value = "";
          o.disabled = true;
          o.className = "disabled";
        } else {
          o.disabled = false;
          o.className = "";
        }
      }
    </script>
  </head>
  <body>
    <form name="refund" action="<?php echo tep_href_link(DIR_WS_INCLUDES . 'paypal_wpp/paypal_wpp_refund.php', 'oID=' . (int)$_GET['oID'] . '&action=refund', 'SSL'); ?>" target="_top" method="POST">
      <input type="hidden" name="refund_currency" value="<?php echo $order->info['currency']; ?>">
      
      <table border="0" width="100%" cellspacing="0" cellpadding="5">
        <tr>
          <td colspan="2" class="window_header"><?php echo WPP_REFUND_TITLE; ?></td>
        </tr>
        <tr>
          <td align="right" width="120"><?php echo WPP_TRANSACTION; ?></td>
          <td><select name="refund_transaction_id" id="refund_transaction_id">
    <?php
        $order_status = 0;
        foreach ($transactions as $t) {
          if ($t['transaction_id'] != '' && $t['transaction_type'] == 'CHARGE') {
            echo '<option value="' . $t['transaction_id'] . '">' . $t['transaction_id'] . ' (' . tep_datetime_short($t['date']) . ')</option>';
          }
          $order_status = $t['status_id'];
        }
    ?>
            </select>
          </td>
        </tr>
        <tr>
          <td align="right"><?php echo WPP_REFUND_TYPE; ?></td>
          <td><select name="refund_type" id="refund_type" onchange="hideAmount(this.value);"><option value=""></option><option value="Partial"><?php echo WPP_REFUND_PARTIAL; ?></option><option value="Full"><?php echo WPP_REFUND_FULL; ?></option></select></td>
        </tr>
        <tr>
          <td align="right"><?php echo WPP_REFUND_AMOUNT . ' ' . $paypal_wpp->get_currency_symbol(); ?></td>
          <td><input type="text" name="refund_amount" id="refund_amount" maxlength="7" style="width: 70px; text-align: right"> <?php echo WPP_AMOUNT_OPTIONAL; ?></td>
        </tr>
        <tr>
          <td align="right"><?php echo WPP_ORDER_STATUS; ?></td>
          <td>
            <select name="refund_order_status" id="refund_order_status">
              <?php
                $orders_status = tep_db_query("SELECT orders_status_id, orders_status_name 
                                               FROM " . TABLE_ORDERS_STATUS . " 
                                               WHERE language_id = " . (int)$languages_id . " 
                                               ORDER BY orders_status_name");
                                               
                while ($os = tep_db_fetch_array($orders_status)) {
                  echo '<option value="' . $os['orders_status_id'] . '"' . ($os['orders_status_id'] == $order_status ? ' selected="SELECTED"' : '') . '>' . $os['orders_status_name'] . '</option>';
                }
              ?>
            </select>
          </td>
        </tr>
        <tr>
          <td colspan="2"><?php echo WPP_COMMENTS; ?><br /><textarea name="refund_comments" style="width:390px; height: 70px"></textarea></td>
        </tr>
        <tr>
          <td colspan="2" align="center"><input type="submit" name="refund_submit" value="<?php echo WPP_SUBMIT_REFUND; ?>">&nbsp;<input type="button" name="cancel" value="<?php echo WPP_CANCEL; ?>" onclick="window.close();"></td>
        </tr>
      </table>
    </form>
  </body>
</html>