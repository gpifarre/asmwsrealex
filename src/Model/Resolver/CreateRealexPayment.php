<?php
/**
 * Copyright © ASMWS, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace asm\globalpayments\Model\Resolver;

use GlobalPayments\Api\PaymentMethods\CreditCardData;
use GlobalPayments\Api\ServicesConfig;
use GlobalPayments\Api\ServicesContainer;
use GlobalPayments\Api\Entities\Address;
use GlobalPayments\Api\Entities\Exceptions\ApiException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Store\Model\StoreManagerInterface;
use GlobalPayments\Api\Entities\EncryptionData;
use RealexPayments\HPP\Helper;

/**
 * Resolver for generating Globalpayments client token
 */
class createRealexPayment implements ResolverInterface
{
    /**
     * @var Data
     */
    private $_helper;

    private $_order;

    private $_paymentManagement;

    /**
     * @var GlobalpaymentsAdapterFactory
     * /
    * private $adapterFactory;
    */
    /**
     * @param ServicesConfig $config
     * @param GlobalpaymentsAdapterFactory $adapterFactory
     */
    public function __construct(
        \RealexPayments\HPP\Helper\Data $helper,
        \RealexPayments\HPP\API\RealexPaymentManagementInterface $paymentManagement
    ) {
        $this->_helper = $helper;
        //$this->adapterFactory = $adapterFactory;
        $this->_paymentManagement = $paymentManagement;
    }

    /**
     * @inheritdoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {

        //CARGAR LOS DATOS DE CONFIGURACION

        $config = new ServicesConfig();
        $config->merchantId = "asmwstest"; // Identificador
        $config->accountId = "internet"; // Subcuenta
        $config->sharedSecret = "secret"; // Shared Secret (contraseña para realizar transacciones)
        $config->rebatePassword = 'rebate'; // Rebate Password (contraseña para realizar devoluciones)
        $config->serviceUrl = "https://remote.sandbox.addonpayments.com/remote"; // URL de Addon Payments donde se envían las peticiones
        ServicesContainer::configure($config);

        if (!$this->config->isActive($storeId)) {
            throw new GraphQlInputException(__('The Globalpayments payment method is not active.'));
        }

        $params = [];
        $merchantAccountId = $this->config->getMerchantAccountId($storeId);
        if (!empty($merchantAccountId)) {
            $params[PaymentDataBuilder::MERCHANT_ACCOUNT_ID] = $merchantAccountId;
        }

        //return $this->adapterFactory->create($storeId)->generate($params);

        if (empty($args['credicard'])) {
            throw new GraphQlInputException(__('Specify the "credicard" value.'));
        }

        if (empty($args['expMonth'])) {
            throw new GraphQlInputException(__('Specify the "month" value.'));
        }

        if (empty($args['expYear'])) {
            throw new GraphQlInputException(__('Specify the "year" value.'));
        }

        if (empty($args['cvn'])) {
            throw new GraphQlInputException(__('Specify the "cvn" value.'));
        }

        //PAGO
        $card = new CreditCardData();
        $card->number = $args['credicard'];
        $card->expMonth = $args['expMonth'];
        $card->expYear = $args['expYear'];
        $card->cvn = $args['cvn'];

        $order = $args['orderId'];
        $this->_order = $order;

        try {
            $response = $card->charge($args['charge'])
                ->withCurrency($args['currency'])
                ->execute();

/*             $result = $response->responseCode; // 00 == Success
            $message = $response->responseMessage; // [ test system ] AUTHORISED

            $orderId = $response->orderId; // N6qsk4kYRZihmPrTXWYS6g
            $authCode = $response->authorizationCode; // 12345
            $paymentsReference = $response->transactionId; // pasref: 14610544313177922
            $schemeReferenceData = $response->schemeId; // MMC0F00YE4000000715 */

            return $this->_paymentManagement->processResponse($order, $response);
            //RealexPayments\HPP\Model\Config\Source\SettleMode;
            //processResponse($order, $response)
            //$this->_handleResponse($response);

        } catch (ApiException $e) {
            // handle errors
            return $e;
        }

    }


    /**
     * @param array $response
     *
     * @return bool
     */
    private function _handleResponse($response)
{
    if (empty($response)) {

        return false;
    }

    $this->_helper->logDebug(__('Gateway response:').print_r($this->_helper->stripTrimFields($response), true));

    // validate response
    $authStatus = $this->_validateResponse($response);
    if (!$authStatus) {

        return false;
    }
    //get the actual order id
    list($incrementId, $orderTimestamp) = explode('_', $response['ORDER_ID']);

    if ($incrementId) {
        $order = $this->_getOrder($incrementId);
        if ($order->getId()) {
            // process the response
            return $this->_paymentManagement->processResponse($order, $response);
        } else {

            return false;
        }
    } else {

        return false;
    }
}

    /**
     * Validate response using sha1 signature.
     *
     * @param array $response
     *
     * @return bool
     */
    private function _validateResponse($response)
    {
        $timestamp = $response['TIMESTAMP'];
        $result = $response['RESULT'];
        $orderid = $response['ORDER_ID'];
        $message = $response['MESSAGE'];
        $authcode = $response['AUTHCODE'];
        $pasref = $response['PASREF'];
        $realexsha1 = $response['SHA1HASH'];

        $merchantid = $this->_helper->getConfigData('merchant_id');

        $sha1hash = $this->_helper->signFields("$timestamp.$merchantid.$orderid.$result.$message.$pasref.$authcode");

        //Check to see if hashes match or not
        if ($sha1hash !== $realexsha1){
            return false;
        }

        return true;
    }


    /**
     * Get order based on increment id.
     *
     * @param $incrementId
     *
     * @return \Magento\Sales\Model\Order
     */
    private function _getOrder($incrementId)
    {
        if (!$this->_order) {
            $this->_order = $this->_orderFactory->create()->loadByIncrementId($incrementId);
        }

        return $this->_order;
    }
}
