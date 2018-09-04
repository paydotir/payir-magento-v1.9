<?php

/**
 * Magento
 *
 * @category   Payir
 * @package    Payir
 * @copyright  Copyright (c) 2017 Pay.ir (https://pay.ir/)
 */

class Payir_Block_Success extends Mage_Core_Block_Template
{
	protected function _toHtml()
	{
		require_once Mage::getBaseDir() . DS . 'lib' . DS . 'Zend' . DS . 'Log.php';

		$oderId = Mage::helper('core')->decrypt(Mage::getSingleton('core/session')->getOrderId());
		Mage::getSingleton('core/session')->unsOrderId();

		$order = new Mage_Sales_Model_Order();
		$incrementId = Mage::getSingleton('checkout/session')->getLastRealOrderId();
		$order->loadByIncrementId($incrementId);
		$this->_paymentInst = $order->getPayment()->getMethodInstance();

		$success = false;
		$message = Mage::Helper('payir')->getMessage();

		if (isset($_POST['status']) && isset($_POST['transId']) && isset($_POST['factorNumber'])) {

			$status = $_POST['status'];
			$transId = $_POST['transId'];
			$factorNumber = $_POST['factorNumber'];
			$response = $_POST['message'];

			if (isset($status) && $status == 1) {

				$apiKey = Mage::helper('core')->decrypt($this->_paymentInst->getConfigData('terminal_Id'));

				$params = array(

					'api' => $apiKey,
					'transId' => $transId
				);

				$result = self::common('https://pay.ir/payment/verify', $params);

				if ($result && isset($result->status) && $result->status == 1) {

					$cardNumber = isset($_POST['cardNumber']) ? $_POST['cardNumber'] : null;

					$amount = intval($order->getGrandTotal());

					if ($amount == $result->amount) {

						$success = true;
					}
					else {

						$message = Mage::Helper('payir')->getMessage(105);
					}
				}
				else {

					$message = Mage::Helper('payir')->getMessage(104);
					$message = isset($result->errorMessage) ? $result->errorMessage : $message;
				}
			}
			else {

				if ($response) {

					$message = $response;
				}
				else {

					$message = Mage::Helper('payir')->getMessage(103);
				}
			}
		}
		else {

			$message = Mage::Helper('payir')->getMessage(102);
		}

		if ($success == true) {

			$invoice = $order->prepareInvoice();
			$invoice->register()->capture();

			Mage::getModel('core/resource_transaction')->addObject($invoice)->addObject($invoice->getOrder())->save();

			$message = sprintf($this->__("Yours order track number is %s"), $transId);
			$card = 'شماره کارت پرداخت کننده ' . $cardNumber;

			$order->addStatusToHistory($this->_paymentInst->getConfigData('second_order_status'), $message, true);
			$order->addStatusToHistory($this->_paymentInst->getConfigData('second_order_status'), $card, false);
			$order->save();

			$order->sendNewOrderEmail();

			Mage::getSingleton('core/session')->addSuccess($message);

			$html = '<html><body> <script type="text/javascript"> window.location = "' . Mage::getUrl('checkout/onepage/success', array('_secure' => true)) . '" </script> </body></html>';
			return $html;
		}
		else {

			$this->_order = Mage::getModel('sales/order')->loadByIncrementId($saleOrderId);

			$order->addStatusToHistory(Mage_Sales_Model_Order::STATE_CANCELED, $message, true);
			$order->save();

			$this->_order->sendOrderUpdateEmail(true, $message);

			Mage::getSingleton('checkout/session')->setErrorMessage($message);

			$html = '<html><body> <script type="text/javascript"> window.location = "' . Mage::getUrl('checkout/onepage/failure', array('_secure' => true)) . '" </script></body></html>';
			return $html;
		}
	}

	private function common($url, $params)
	{
		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));

		$response = curl_exec($ch);
		$error = curl_errno($ch);

		curl_close($ch);

		$output = $error ? false : json_decode($response);

		return $output;
	}
}
