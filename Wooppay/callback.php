<?php
/**
 *
 *
 * @copyright    5CMS
 * @link        http://5cms.ru
 *
 *
 */
chdir ('../../');
require_once('api/Fivecms.php');
require_once('WooppaySoapClient.php');

if (isset($_GET['order_id']) && isset($_GET['order_key'])) {
	$order_id = intval($_GET['order_id']);
	$order_key = $_GET['order_key'];
	if (md5($order_id) === $order_key) {
		$fivecms = new Fivecms();
		$order = $fivecms->orders->get_order(intval($order_id));

		if (empty($order)) {
			echo json_encode(['data' => 1]);
			die();
		}

		if ($order->paid) {
			echo json_encode(['data' => 1]);
			die();
		}

		$method = $fivecms->payment->get_payment_method(intval($order->payment_method_id));
		if (empty($method)) {
			echo json_encode(['data' => 1]);
			die();
		}

		$settings = unserialize($method->settings);

		$url = $settings['url'];
		$username = $settings['username'];
		$password = $settings['password'];
		$referenceId = $settings['prefix'] . '_' . $order_id;
		$service = $settings['service'];

		try {
			$client = new WooppaySoapClient($url);
			if ($client->login($username, $password)) {
				$invoice_request = new CashCreateInvoiceByServiceRequest();
				$invoice_request->referenceId = $referenceId;
				$invoice_request->serviceName = $service;
				$invoice = $client->createInvoiceByRequest($invoice_request);
				$operation = $client->getOperationData((int)$invoice->response->operationId);
				if ($operation->response->records[0]->status == WooppayOperationStatus::OPERATION_STATUS_DONE || $operation->response->records[0]->status == WooppayOperationStatus::OPERATION_STATUS_WAITING) {
					$purchases = $fivecms->orders->get_purchases(array('order_id' => intval($order->id)));
					foreach ($purchases as $purchase) {
						$variant = $fivecms->variants->get_variant(intval($purchase->variant_id));
						if (empty($variant) || (!$variant->infinity && $variant->stock < $purchase->amount)) {
							echo json_encode(['data' => 1]);
							die();
						}
					}
					$fivecms->orders->set_pay(intval($order->id));
					$fivecms->orders->close(intval($order->id));
					$fivecms->notify->email_order_user(intval($order->id));
					$fivecms->notify->email_order_admin(intval($order->id));
				}
			}
		} catch (Exception $exception) {
			echo json_encode(['data' => 1]);
			die();
		}
		echo json_encode(['data' => 1]);
		die();
	}
}
