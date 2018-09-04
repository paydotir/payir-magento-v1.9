<?php

/**
 * Magento
 *
 * @category   Payir
 * @package    Payir
 * @copyright  Copyright (c) 2017 Pay.ir (https://pay.ir/)
 */

class Payir_Block_Redirect extends Mage_Core_Block_Abstract
{
	protected function _toHtml()
	{
		$module = 'payir';
		$payment = $this->getOrder()->getPayment()->getMethodInstance();
		$res = $payment->getUrl();

		if ($res->status && isset($res->status) && $res->status == 1) {
			$html = '<html><body> <script type="text/javascript"> window.location = "https://pay.ir/payment/gateway/' . $res->transId . '" </script> </body></html>';
		}
		else {
			$html = '<html><body> <script type="text/javascript"> window.location = "' . Mage::getUrl('checkout/onepage/failure', array('_secure' => true)) . '" </script> </body></html>';
		}
		return $html;
	}
}
