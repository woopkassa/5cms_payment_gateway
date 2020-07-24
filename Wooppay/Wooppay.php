<?php

require_once('api/Fivecms.php');
require_once('WooppaySoapClient.php');

class Wooppay extends Fivecms
{
	public function checkout_form($order_id)
	{
		try {
			$order = $this->orders->get_order((int)$order_id);
			$payment_method = $this->payment->get_payment_method($order->payment_method_id);
			$payment_settings = $this->payment->get_payment_settings($payment_method->id);

			$price = $this->money->convert($order->total_price, $payment_method->currency_id, false);

			$url = $payment_settings['url'];
			$username = $payment_settings['username'];
			$password = $payment_settings['password'];
			$prefix = $payment_settings['prefix'];
			$service = $payment_settings['service'];

			$inv_id = $order->id;
			$inv_reference = $prefix . '_' . $inv_id;
			$inv_desc = 'Оплата заказа №' . $inv_id;
			$backUrl = $this->config->root_url . '/order/' . $order->url;
			$callbackUrl = $this->config->root_url . '/payment/Wooppay/callback.php';
			$requestUrl = $callbackUrl . '?order_id=' . $inv_id . '&order_key=' . md5($inv_id);
			$client = new WooppaySoapClient($url);
			if ($client->login($username, $password)) {
				$invoice = $client->createInvoice($inv_reference, $backUrl, $requestUrl, $price, $service, $inv_desc);
				$operationUrl = $invoice->response->operationUrl;
			} else {
				return '<h2>Произошла ошибка при создание заказа, попробуйте позже</h2>';
			}
		} catch (Exception $exception) {
			return '<h2>Платёжная система была неправильно сконфигурирована</h2>';
		}
		$button = "<form action='$operationUrl' method=POST>" .
			"<input type=submit class=checkout_button value='Перейти к оплате'>" .
			"</form>";
		return $button;
	}
}