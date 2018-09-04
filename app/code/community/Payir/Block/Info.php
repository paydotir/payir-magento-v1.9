<?php

/**
 * Magento
 *
 * @category   Payir
 * @package    Payir
 * @copyright  Copyright (c) 2017 Pay.ir (https://pay.ir/)
 */

class Payir_Block_Info extends Mage_Payment_Block_Info
{
	protected function _construct()
	{
		parent::_construct();
		$this->setTemplate('payir/info.phtml');
	}
	public function getMethodCode()
	{
		return $this->getInfo()->getMethodInstance()->getCode();
	}
	public function toPdf()
	{
		$this->setTemplate('payir/pdf/info.phtml');
		return $this->toHtml();
	}
}
