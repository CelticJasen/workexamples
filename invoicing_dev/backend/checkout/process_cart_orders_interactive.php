<?php
/*
 File: process_cart_orders.php

 This script will only be called manually.

 It will lock the website_orders
 table to prevent concurrency problems.

 It will then retrieve all of the orders
 that have not been processed yet,
 adds them to the invoicing system, marks
 them as processed, and unlocks the table.

 Aaron Kennedy, kennedy@postpro.net, 2012-03-09
 */
try {
  chdir("/webroot/invoicing/backend/checkout");
  define("PVT_KEY_PASS", "How come nobody ever uses spaces in their passphrases?");
  require_once ("process_cart_order.inc.php");
  mysqli_report(MYSQLI_REPORT_STRICT | MYSQLI_REPORT_ERROR);
  $db = new mysqli("localhost", "office", "never eat shredded wheat", "invoicing");
  ob_start();

  Mailer::mail('steven@emovieposter.com', 'DEBUG - ' . __FILE__, 'Line:' . __LINE__ . "\n\n" . var_export($_GET, true) . "\n\n" . var_export($_POST, true));

  $r = $db->query("select get_lock('invoicing.website_orders', 10)");
  list($r) = $r->fetch_row();
  if ($r == 0)
    throw new Exception("Named lock 'invoicing.website_orders' timed out.", 10601);

  $r = $db->query("select `data`, `key`, `data2`, `key2`, id, email from website_orders where processed is null");

  while (list($data, $key, $data2, $key2, $id, $email) = $r->fetch_row()) {
    $private_key = openssl_pkey_get_private("file://private.key", PVT_KEY_PASS);

    if (false == openssl_open($data, $serialized, $key, $private_key))
      throw new Exception("Could not decrypt", 10010);

    $unserialized = unserialize($serialized);

    if (!empty($unserialized['test']))
      continue;

    if (!empty($data2)) {
      if (false == openssl_open($data2, $serialized, $key2, $private_key))
        throw new Exception("Could not decrypt", 10010);

      $data2 = unserialize($serialized);

      if ($data2->DoExpressCheckoutPaymentResponseDetails->PaymentInfo->PaymentStatus != "Completed" && empty($_POST["process$id"])) {
        continue;
      }
    } else
      $data2 = false;

    try {
      $db->query("start transaction");

      if (isset($unserialized[0]))
        $orders = $unserialized;
      else
        $orders = array($unserialized);

      if (empty($_GET['quotes_only']) && empty($_GET['orders_only'])) {
        foreach ($orders as $k=>$data) {
          if ($data['op'] == "quote_request") {
            echo process_cart_order($data, $data2, $db, true);
            Mailer::mail('steven@emovieposter.com', 'DEBUG - ' . __FILE__, 'Line:' . __LINE__ . "\n\n" . var_export($_GET, true) . "\n\n" . var_export($_POST, true));
          } else {
            echo process_cart_order($data, $data2, $db, false, !empty($_POST["process$id"])) . "\n";
            Mailer::mail('steven@emovieposter.com', 'DEBUG - ' . __FILE__, 'Line:' . __LINE__ . "\n\n" . var_export($_GET, true) . "\n\n" . var_export($_POST, true));
          }
        }
      } else {
        foreach ($orders as $k=>$data) {
          if ($data['op'] == "quote_request" && (!empty($_GET['quotes_only']) && empty($_GET['orders_only']))) {
            echo process_cart_order($data, $data2, $db, true);
            Mailer::mail('steven@emovieposter.com', 'DEBUG - ' . __FILE__, 'Line:' . __LINE__ . "\n\n" . var_export($_GET, true) . "\n\n" . var_export($_POST, true));
          } elseif ($data['op'] != "quote_request" && (empty($_GET['quotes_only']) && !empty($_GET['orders_only']))) {
            echo process_cart_order($data, $data2, $db, false, !empty($_POST["process$id"])) . "\n";
            Mailer::mail('steven@emovieposter.com', 'DEBUG - ' . __FILE__, 'Line:' . __LINE__ . "\n\n" . var_export($_GET, true) . "\n\n" . var_export($_POST, true));
          }
        }
      }

      $db->query("update website_orders set processed = now() where id = '$id'");

      $db->query("rollback");

      //$db->query("commit");
    } catch(Exception $e) {
      $db->query("rollback");
      echo "$email: " . $e->getMessage() . "\n";
    }

    usleep(max(round(695047 - (time() - 1501181576) / 3.5), 50000));
  }
  $content = ob_get_clean();
  die(json_encode(array("output"=>$content)));
} catch(Exception $e) {
  die(json_encode(array("error"=>$e->__toString())));
}
?>
