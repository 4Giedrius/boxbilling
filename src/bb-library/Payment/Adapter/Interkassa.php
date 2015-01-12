<?php
/**
 * BoxBilling
 *
 * @copyright BoxBilling, Inc (http://www.boxbilling.com)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

class Payment_Adapter_Interkassa extends Payment_AdapterAbstract
{
    public function init()
    {
        if(!$this->getParam('ik_co_id')) {
            throw new Payment_Exception('Shop ID is missing in gateway configuration');
        }
        if(!$this->getParam('ik_secret_key')) {
            throw new Payment_Exception('Secret key is missing in gateway configuration');
        }
    }

	/**
	 * Return gateway type
	 *
	 * @return string
	*/
    public function getType()
    {
        return Payment_AdapterAbstract::TYPE_FORM;
    }

    public static function getConfig()
    {
        return array(
            'supports_one_time_payments'   =>  true,
            'supports_subscriptions'     =>  false,
            'description'     =>  'это удобный в использовании сервис, подключение к которому позволит Интернет-магазинам, веб-сайтам и прочим торговым площадкам принимать все возможные формы оплаты в максимально короткие сроки. http://www.interkassa.com/',
            'form'  => array(
                'ik_co_id' => array('text', array(
                            'label' => 'Shop ID which is registered in "INTERKASSA" system. Can be found under area "Настройки магазина". Example: 64C18529-4B94-0B5D-7405-F2752F2B716C',
                            'value' => '',
                    ),
                 ),
                'ik_secret_key' => array('password', array(
                            'label' => 'Secret Key. This is a line with characters, which is added to payment requisites, which is sent to seller with a notification. It is used to check and form data signature.',
                            'value' => '',
                    ),
                 ),
            ),
        );
    }

	/**
	 * Return service call url
	 *
	 * @return string
	*/
    public function getServiceUrl()
    {
        if($this->getParam('test_mode')) {
            return 'https://sci.interkassa.com/demo/';
        }
        return 'https://sci.interkassa.com/';
    }

	/**
	 * Init single payment call to webservice
	 * Invoice id is passed via notify_url
     *
	 * @return mixed
	*/
    public function singlePayment(Payment_Invoice $invoice)
    {
        return array(
            'ik_co_id'              => $this->getParam('ik_co_id'),
            'ik_pm_no'              => $invoice->Id,
            'ik_am'                 => $invoice->getTotal(),
            'ik_desc'               => $invoice->getTitle(),
            'ik_cur'                => $invoice->getCurrency(),

            'ik_ia_u'               => $this->getParam('notify_url'),
            'ik_ia_m'               => 'post',
            'ik_suc_u'              => $this->getParam('return_url'),
            'ik_suc_m'              => 'get',
            'ik_pnd_u'              => $this->getParam('return_url'),
            'ik_pnd_m'              => 'get',
            'ik_fal_u'              => $this->getParam('cancel_url'),
            'ik_fal_m'              => 'get',

            'ik_x_iid'              => $invoice->Id,
        );
    }

	/**
	 * Init recurrent payment call to webservice
	 *
	 * @return mixed
	*/
    public function recurrentPayment(Payment_Invoice $invoice)
    {
        throw new Payment_Exception('Interkassa payment gateway do not support recurrent payments');
    }

    /**
     * Handle IPN and return response object
     * @return Payment_Transaction
     */
    public function getTransaction($data, Payment_Invoice $invoice)
    {
        $ipn = $data['post'];

        $tx = new Payment_Transaction();
        $tx->setId($ipn['ik_trn_id']);
        $tx->setAmount($ipn['ik_am']);
        $tx->setCurrency($ipn['ik_cur']);
        $tx->setType(Payment_Transaction::TXTYPE_PAYMENT);

        if($ipn['ik_inv_st'] == 'success') {
            $tx->setStatus(Payment_Transaction::STATUS_COMPLETE);
        }
        
        return $tx;
    }

    public function isIpnValid($data, Payment_Invoice $invoice)
    {
        $status_data = $data['post'];
        $shop_id = $this->getParam('ik_co_id');
        $secret_key = $this->getParam('ik_secret_key');
        
        if($shop_id != $status_data['ik_co_id']) {
            error_log('Shop ids does not match');
            return false;
        }

        $dataSet = $status_data;
        unset($dataSet["ik_sign"]);
        ksort($dataSet, SORT_STRING); // sort by the keys in alphabetical order array elements
        array_push($dataSet, $secret_key); // add in the end of array “secret key”
        $signString = implode(':', $dataSet); // concatenate value through the ":" symbol
        $sign = base64_encode(md5($signString, true)); // take MD5 hash in a binary form by the

        return ($status_data["ik_sign"] == $sign);
    }
}