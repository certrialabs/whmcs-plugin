<?php

/**
 * BitPay Checkout Callback File 3.0.0.6
 *
 * This file demonstrates how a payment gateway callback should be
 * handled within WHMCS.
 *
 * It demonstrates verifying that the payment gateway module is active,
 * validating an Invoice ID, checking for the existence of a Transaction ID,
 * Logging the Transaction for debugging and Adding Payment to an Invoice.
 *
 * For more information, please refer to the online documentation.
 *
 * @see https://developers.whmcs.com/payment-gateways/callbacks/
 *
 * @copyright Copyright (c) WHMCS Limited 2017
 * @license http://www.whmcs.com/license/ WHMCS Eula
 */
// Require libraries needed for gateway module functions.
require_once  '../../../init.php';
require_once  '../../../includes/gatewayfunctions.php';
require_once  '../../../includes/invoicefunctions.php';



$all_data = json_decode(file_get_contents("php://input"), true);
#
$data = $all_data['data'];
$event = $all_data['event'];


$orderid = $data['orderId'];

$order_status = $data['status'];
$order_invoice = $data['id'];

$price = $data['price'];

$data['id'];
#first see if the ipn matches
#get the user id first
$table = "_bitpay_checkout_transactions";
$fields = "order_id,transaction_id";
$where = array("order_id" => $orderid,"transaction_id" => $order_invoice);
$result = select_query($table, $fields, $where);
$rowdata = mysql_fetch_array($result);
$btn_id = $rowdata['transaction_id'];


if($btn_id):
switch ($event['name']) {
     #complete, update invoice table to Paid
     case 'invoice_confirmed':
     
        $table = "tblinvoices";
        $update = array("status" => 'Paid');
        $where = array("id" => $orderid, "paymentmethod" => "bitpaycheckout");
        update_query($table, $update, $where);

        #update the bitpay_invoice table
        $table = "_bitpay_checkout_transactions";
        $update = array("transaction_status" => $event['name']);
        $where = array("order_id" => $orderid, "transaction_id" => $order_invoice);
        update_query($table, $update, $where);


     break;
     
     #processing - put in Payment Pending
     case 'invoice_paidInFull':
        $table = "tblinvoices";
        $update = array("status" => 'Payment Pending');
        $where = array("id" => $orderid, "paymentmethod" => "bitpaycheckout");
        update_query($table, $update, $where);

        #update the bitpay_invoice table
        $table = "_bitpay_checkout_transactions";
        $update = array("transaction_status" => $event['name']);
        $where = array("order_id" => $orderid, "transaction_id" => $order_invoice);
        update_query($table, $update, $where);


     break;
     
     #confirmation error - put in Payment Pending
     case 'invoice_failedToConfirm':
        $table = "tblinvoices";
        $update = array("status" => 'Payment Pending');
        $where = array("id" => $orderid, "paymentmethod" => "bitpaycheckout");
        update_query($table, $update, $where);

        #update the bitpay_invoice table
        $table = "_bitpay_checkout_transactions";
        $update = array("transaction_status" => $event['name']);
        $where = array("order_id" => $orderid, "transaction_id" => $order_invoice);
        update_query($table, $update, $where);


     break;
     
     #expired, remove from transaction table, wont be in invoice table
     case 'invoice_expired':
        #delete any orphans
        $table = "_bitpay_checkout_transactions";
        $delete = 'DELETE FROM _bitpay_checkout_transactions WHERE transaction_id = "' . $order_invoice.'"';
        full_query($delete);
     break;
     
     #update both table to refunded
     case 'invoice_refundComplete':

        #get the user id first
        $table = "tblaccounts";
        $fields = "id,userid";
        $where = array("transid" => $order_invoice);
        $result = select_query($table, $fields, $where);
        $rowdata = mysql_fetch_array($result);
        $id = $rowdata['id'];
        $userid = $rowdata['userid'];


        #do an insert on tblaccounts
        $values = array("userid" => $userid, "description" => "BitPay Refund of Transaction ID: ".$oder_invoice, "amountin" => "0","currency"=>"0","amountout" => $price,"invoiceid" =>$orderid,"date"=>date("Y-m-d H:i:s"));
        $newid = insert_query($table, $values);

        #update the tblinvoices to show Refunded
        $table = "tblinvoices";
        $update = array("status" => 'Refunded');
        $where = array("id" => $orderid, "paymentmethod" => "bitpaycheckout");
        update_query($table, $update, $where);

        #update the bitpay_invoice table
        $table = "_bitpay_checkout_transactions";
        $update = array("transaction_status" => $event['name']);
        $where = array("order_id" => $orderid, "transaction_id" => $order_invoice);
        update_query($table, $update, $where);

     break;
}
endif;#end of the table lookup
