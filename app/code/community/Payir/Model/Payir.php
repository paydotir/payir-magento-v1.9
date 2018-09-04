<?php

/**
 * Magento
 *
 * @category   Payir
 * @package    Payir
 * @copyright  Copyright (c) 2017 Pay.ir (https://pay.ir/)
 */

class Payir_Model_payir extends Mage_Payment_Model_Method_Abstract
{
	protected $_code = 'payir';
	protected $_formBlockType = 'payir/form';
	protected $_infoBlockType = 'payir/info';
	protected $_isGateway = false;
	protected $_canAuthorize = true;
	protected $_canCapture = true;
	protected $_canCapturePartial = false;
	protected $_canRefund = false;
	protected $_canVoid = false;
	protected $_canUseInternal = false;
	protected $_canUseCheckout = true;
	protected $_canUseForMultishipping = false;
	protected $_order;

	public function getOrder()
	{
		if (!$this->_order) {
			$paymentInfo = $this->getInfoInstance();
			$this->_order = Mage::getModel('sales/order')->loadByIncrementId($paymentInfo->getOrder()->getRealOrderId());
		}
		return $this->_order;
	}

	public function validate()
	{
		$quote = Mage::getSingleton('checkout/session')->getQuote();
		$quote->setCustomerNoteNotify(false);
		parent::validate();
	}

	public function getOrderPlaceRedirectUrl()
	{
		return Mage::getUrl('payir/redirect/redirect', array('_secure' => true));
	}

	public function capture(Varien_Object $payment, $amount)
	{
		$payment->setStatus(self::STATUS_APPROVED)->setLastTransId($this->getTransactionId());
		return $this;
	}

	public function getPaymentMethodType()
	{
		return $this->_paymentMethod;
	}

	public function getUrl()
	{
		require_once Mage::getBaseDir() . DS . 'lib' . DS . 'Zend' . DS . 'Log.php';

		$result = [];

		if (extension_loaded('curl')) {

			$orderId = $this->getOrder()->getRealOrderId();

			Mage::getSingleton('core/session')->setOrderId(Mage::helper('core')->encrypt($this->getOrder()->getRealOrderId()));

			$apiKey = Mage::helper('core')->decrypt($this->getConfigData('terminal_Id'));
			$amount = intval($this->getOrder()->getGrandTotal());
			$callback = ($this->getConfigData('ssl_enabled') ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/index.php' . '/payir/redirect/success/';
			$mobile = $this->getOrder()->getShippingAddress()->getTelephone();
			$description = 'پرداخت شماره سفارش ' . $orderId;

			$params = array(

				'api' => $apiKey,
				'amount' => $amount,
				'redirect' => urlencode($callback),
				'mobile' => $mobile,
				'factorNumber' => $orderId,
				'description' => $description
			);

			$result = self::common('https://pay.ir/payment/send', $params);

			if ($result && isset($result->status) && $result->status == 1) {

				$pgwpay_url ='https://pay.ir/payment/gateway/' . $result->transId;
			}
			else {

				$message = Mage::Helper('payir')->getMessage(101);
				$message = isset($result->errorMessage) ? $result->errorMessage : $message;

				$this->getOrder();
				$this->_order->addStatusToHistory(Mage_Sales_Model_Order::STATE_CANCELED, $message, true);
				$this->_order->save();
				Mage::getSingleton('checkout/session')->setErrorMessage($message);
			}
		}
		else {

			$message = Mage::Helper('payir')->getMessage(100);

			$this->getOrder();
			$this->_order->addStatusToHistory(Mage_Sales_Model_Order::STATE_CANCELED, $message, true);
			$this->_order->save();
			Mage::getSingleton('checkout/session')->setErrorMessage($message);
		}

		return $result;
	}

	public function getFormFields()
	{
		$orderId = $this->getOrder()->getRealOrderId();
		$params = array('x_invoice_num' => $orderId);
		return $params;
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
