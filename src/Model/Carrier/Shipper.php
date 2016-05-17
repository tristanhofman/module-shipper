<?php
/**
 *
 * ShipperHQ Shipping Module
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * Shipper HQ Shipping
 *
 * @category ShipperHQ
 * @package ShipperHQ_Shipping_Carrier
 * @copyright Copyright (c) 2015 Zowta LLC (http://www.ShipperHQ.com)
 * @license http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @author ShipperHQ Team sales@shipperhq.com
 */
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace ShipperHQ\Shipper\Model\Carrier;

/**
 * Shipper shipping model
 *
 * @category ShipperHQ
 * @package ShipperHQ_Shipper
 */

use ShipperHQ\WS\Client;
use ShipperHQ\WS\Rate\Response;
use ShipperHQ\WS\Helper\RateHelper;

use ShipperHQ\Shipper\Helper\Config;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\Error;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Shipping\Model\Rate\Result;

class Shipper
    extends \Magento\Shipping\Model\Carrier\AbstractCarrier
    implements \Magento\Shipping\Model\Carrier\CarrierInterface
{

    /**
     * @var string
     */
    protected $_code = 'shipper';

    /*
     * Rate request object
     * @var \ShipperHQ\WS\Rate\Request\RateRequest
     */
    protected $shipperRequest = null;

    /**
     * Raw rate request data
     *
     * @var Varien_Object|null
     */
    protected $rawRequest = null;

    /**
     * Flag for check carriers for activity
     *
     * @var string
     */
    const ACTIVE_FLAG = 'active';

    /**
     * @var Config
     */
    protected $configHelper;
    /**
     * @var \ShipperHQ\Shipper\Helper\Data
     */
    protected $shipperDataHelper;

    /**
     * @var \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory
     */
    protected $rateMethodFactory;
    /**
     * @var \Magento\Shipping\Model\Rate\ResultFactory
     */
    protected $rateFactory;

    /**
     * @var \Magento\Framework\Registry
     */
    private $registry;
    /**
     * @var \ShipperHQ\Shipper\Helper\LogAssist
     */
    private $shipperLogger;
    /**
     * @var Client\WebServiceClientFactory
     */
    private $shipperWSClientFactory;
    /**
     * @var Processor\CarrierConfigHandler
     */
    private $carrierConfigHandler;
    /**
     * @var \ShipperHQ\Shipper\Helper\CarrierCache
     */
    private $carrierCache;
    /**
     * @var Processor\BackupCarrier
     */
    private $backupCarrier;
    /**
     * @var \ShipperHQ\WS\Helper\RateHelper
     */
    private $shipperRateHelper;
    /**
     * @var \Magento\Framework\Stdlib\DateTime\TimezoneInterface
     */
    protected $_localeDate;

    /**
     * Rate result data
     *
     * @var Mage_Shipping_Model_Rate_Result|null
     */
    protected $result = null;
    protected $carrierGroupFactory;

    /**
     * @param \ShipperHQ\Shipper\Helper\Data $shipperDataHelper
     * @param \ShipperHQ\Shipper\Helper\CarrierCache $carrierCache
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \ShipperHQ\Shipper\Helper\LogAssist $shipperLogger
     * @param \Psr\Log\LoggerInterface $logger
     * @param Config $configHelper
     * @param Processor\ShipperMapper $shipperMapper
     * @param Processor\CarrierConfigHandler $carrierConfigHandler
     * @param Processor\BackupCarrier $backupCarrier
     * @param \Magento\Framework\Registry $registry
     * @param Client\WebServiceClientFactory $shipperWSClientFactory
     * @param \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory
     * @param \Magento\Shipping\Model\Rate\ResultFactory $resultFactory
     * @param \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory
     * @param  \ShipperHQ\WS\Helper\RateHelper $shipperWSRateHelper
     * @param  \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate,
     * @param array $data
     */
    public function __construct(
        \ShipperHQ\Shipper\Helper\Data $shipperDataHelper,
        \ShipperHQ\Shipper\Helper\CarrierCache $carrierCache,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \ShipperHQ\Shipper\Helper\LogAssist $shipperLogger,
        \Psr\Log\LoggerInterface $logger,
        Config $configHelper,
        Processor\ShipperMapper $shipperMapper,
        Processor\CarrierConfigHandler $carrierConfigHandler,
        Processor\BackupCarrier $backupCarrier,
        \Magento\Framework\Registry $registry,
        \ShipperHQ\WS\Client\WebServiceClientFactory $shipperWSClientFactory,
        \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory,
        \Magento\Shipping\Model\Rate\ResultFactory $resultFactory,
        \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory,
        \ShipperHQ\Shipper\Model\CarrierGroupFactory $carrierGroupFactory,
        \ShipperHQ\WS\Helper\RateHelper $shipperWSRateHelper,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate,
        array $data = []
    )
    {
        $this->shipperDataHelper = $shipperDataHelper;
        $this->configHelper = $configHelper;
        $this->shipperMapper = $shipperMapper;
        $this->rateFactory = $resultFactory;
        $this->rateMethodFactory = $rateMethodFactory;
        $this->registry = $registry;
        $this->shipperLogger = $shipperLogger;
        $this->shipperWSClientFactory = $shipperWSClientFactory;
        $this->carrierConfigHandler = $carrierConfigHandler;
        $this->carrierCache = $carrierCache;
        $this->backupCarrier = $backupCarrier;
        $this->carrierGroupFactory = $carrierGroupFactory;
        $this->shipperRateHelper = $shipperWSRateHelper;
        $this->_localeDate = $localeDate;
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
    }

    /**
     * Collect and get rates
     *
     * @param RateRequest $request
     * @return bool|Result|Error
     */
    public function collectRates(RateRequest $request)
    {
        if (!$this->getConfigFlag(self::ACTIVE_FLAG)) {
            return false;
        }
        $initVal = microtime(true);

        $this->cacheEnabled = $this->getConfigFlag('use_cache');
        $this->setRequest($request);

        $this->result = $this->getQuotes();
        $elapsed = microtime(true) - $initVal;
        $this->shipperLogger->postDebug('Shipperhq_Shipper', 'Long lapse',$elapsed);

        return $this->getResult();

    }

    /**
     * Prepare and set request to this instance
     *
     * @param \Magento\Quote\Model\Quote\Address\RateRequest $request
     * @return $this
     */
    public function setRequest(\Magento\Quote\Model\Quote\Address\RateRequest $request)
    {
        if (is_array($request->getAllItems())) {
            $item = current($request->getAllItems());
            if ($item instanceof Mage_Sales_Model_Quote_Item_Abstract) {
                $request->setQuote($item->getQuote());
            }
        }

        $isCheckout = $this->shipperDataHelper->isCheckout();
        $cartType = (!is_null($isCheckout) && $isCheckout != 1) ? "CART" : "STD";
        if ($this->shipperDataHelper->isMultiAddressCheckout()) {
            $cartType = 'MAC';
        }
        $request->setCartType($cartType);

        $this->shipperRequest = $this->shipperMapper->getShipperTranslation($request);
        $this->rawRequest = $request;
        return $this;

    }

    /**
     * Get result of request
     *
     * @return mixed
     */
    public function getResult()
    {
        return $this->result;
    }

    /**
     * Refresh saved carrier methods
     *
     * @return mixed
     */
    public function refreshCarriers()
    {
        $allowedMethods =  $this->getAllowedMethods();
        if(count($allowedMethods) == 0 ) {
            $this->shipperLogger->postDebug('Shipperhq_Shipper', 'Refresh carriers',
                'Allowed methods web service did not contain any shipping methods for carriers');
            $result['result'] = false;
            $result['error'] = 'ShipperHQ Error: No shipping methods for carrier setup in your ShipperHQ account';
            return $result;
        }
        return $allowedMethods;

    }

    /**
     * Get allowed shipping methods
     *
     * @return array
     */
    public function getAllowedMethods()
    {
        $ourCarrierCode = $this->getId();
        $result = [];
        $allowedMethods = [];

        if ($this->_scopeConfig->getValue(
            'carriers/shipper/active',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE)
        ) {
            $allowedMethodUrl = $this->shipperDataHelper->getAllowedMethodGatewayUrl();
            $timeout = $this->shipperDataHelper->getWebserviceTimeout();
            $resultSet = $this->shipperWSClientFactory->create()->sendAndReceive(
                $this->shipperMapper->getCredentialsTranslation(), $allowedMethodUrl, $timeout);

            $allowedMethodResponse = $resultSet['result'];
            $debugData = $resultSet['debug'];
            $this->shipperLogger->postDebug('Shipperhq_Shipper', 'Allowed methods response', $debugData);

            if (!is_object($allowedMethodResponse)) {
                $this->shipperLogger->postInfo('Shipperhq_Shipper',
                    'Allowed Methods: No or invalid response received from Shipper HQ', $allowedMethodResponse);

                $shipperHQ = "<a href=https://shipperhq.com/ratesmgr/websites>ShipperHQ</a> ";
                $result['result'] = false;
                $result['error'] = 'ShipperHQ is not contactable, verify the details from the website configuration in '
                    . $shipperHQ;
                return $result;
            } else if (count($allowedMethodResponse->errors)) {

                $this->shipperLogger->postInfo('Shipperhq_Shipper',
                    'Allowed methods: response contained following errors',$allowedMethodResponse);
                $error = 'ShipperHQ Error: ';
                foreach ($allowedMethodResponse->errors as $anError) {
                    if (isset($anError->internalErrorMessage)) {
                        $error .= ' ' . $anError->internalErrorMessage;
                    } elseif (isset($anError->externalErrorMessage) && $anError->externalErrorMessage != '') {
                        $error .= ' ' . $anError->externalErrorMessage;
                    }
                }
                $result['result'] = false;
                $result['error'] = $error;
                return $result;
            } else if (!count($allowedMethodResponse->carrierMethods)) {
                $this->shipperLogger->postInfo('Shipperhq_Shipper',
                    'Allowed methods web service did not return any carriers or shipping methods',$allowedMethodResponse);
                $result['result'] = false;
                $result['warning'] = 'ShipperHQ Warning: No carriers setup, log in to ShipperHQ Dashboard and create carriers';
                return $result;
            }

            $returnedMethods = $allowedMethodResponse->carrierMethods;

            $carrierConfig = [];

            foreach ($returnedMethods as $carrierMethod) {

                $rateMethods = $carrierMethod->methods;

                foreach ($rateMethods as $method) {
                    if (!is_null($ourCarrierCode) && $carrierMethod->carrierCode != $ourCarrierCode) {
                        continue;
                    }

                    $allowedMethodCode = /*$carrierMethod->carrierCode . '_' .*/
                        $method->methodCode;
                    $allowedMethodCode = preg_replace('/&|;| /', "_", $allowedMethodCode);

                    if (!array_key_exists($allowedMethodCode, $allowedMethods)) {
                        $allowedMethods[$allowedMethodCode] = $carrierMethod->title . '(' . $method->name . ')';
                    }
                }

                $carrierConfig[$carrierMethod->carrierCode]['title'] = $carrierMethod->title;
                if (isset($carrierMethod->sortOrder)) {
                    $carrierConfig[$carrierMethod->carrierCode]['sortOrder'] = $carrierMethod->sortOrder;
                }
            }

            $this->shipperLogger->postDebug('Shipperhq_Shipper','Allowed methods parsed result ',
                    $allowedMethods);
            // go set carrier titles
            $this->carrierConfigHandler->setCarrierConfig($carrierConfig);
        }
        return $allowedMethods;
    }


    /**
     * @param $ratesToAdd
     * @return \Magento\Shipping\Model\Rate\Result
     */
    public function createMergedRate($ratesToAdd)
    {
        $result = $this->rateFactory->create();
        foreach ($ratesToAdd as $rateToAdd) {
            $method = $this->rateMethodFactory->create();
            $method->setPrice((float)$rateToAdd['price']);
            $method->setCost((float)$rateToAdd['price']);
            $method->setCarrier($this->_code);
            $method->setCarrierTitle($rateToAdd['mergedTitle']);
            $method->setMethod($rateToAdd['title']);
            $method->setMethodTitle($rateToAdd['title']);
            $method->setMethodDescription($rateToAdd['mergedDescription']);
            $method->setCarrierType(__('multiple_shipments'));
            $result->append($method);
        }
        return $result;
    }

    /**
     * @param $carrierRate
     * @param $carrierGroupId
     * @param $carrierGroupDetail
     * @return array
     */
    public function extractShipperHQRates($carrierRate, $carrierGroupId, $carrierGroupDetail)
    {

        $carrierResultWithRates = [
            'code' => $carrierRate->carrierCode,
            'title' => $carrierRate->carrierTitle];

        if (isset($carrierRate->error)) {
            $carrierResultWithRates['error'] = (array)$carrierRate->error;
            $carrierResultWithRates['carriergroup_detail']['carrierGroupId'] = $carrierGroupId;
        }

        if (isset($carrierRate->rates) && !array_key_exists('error', $carrierResultWithRates)) {
            $thisCarriersRates = $this->populateRates($carrierRate, $carrierGroupDetail, $carrierGroupId);
            $carrierResultWithRates['rates'] = $thisCarriersRates;
        }

        return $carrierResultWithRates;
    }

    protected function populateRates($carrierRate, &$carrierGroupDetail, $carrierGroupId)
    {
        $thisCarriersRates = [];
        $baseRate = 1;
        $baseCurrencyCode = $this->shipperDataHelper->getBaseCurrencyCode();
        $latestCurrencyCode = '';
        $this->populateCarrierLevelDetails($carrierRate, $carrierGroupDetail);

        $interim = isset($carrierRate->deliveryDateFormat) ?
            $carrierRate->deliveryDateFormat : $this->shipperRateHelper->getDateFormat();
        $dateFormat = $this->shipperDataHelper->getCldrDateFormat($this->getLocaleInGlobals(), $interim);
        $dateOption = $carrierRate->dateOption;
        $deliveryMessage = $this->shipperRateHelper->getDeliveryMessage($carrierRate, $dateOption);

        foreach ($carrierRate->rates as $oneRate) {
            $methodDescription = false;
            $title = $this->shipperDataHelper->isTransactionIdEnabled() ?
                __($oneRate->name) . ' (' . $carrierGroupDetail['transaction'] . ')'
                : __($oneRate->name);
            if (isset($oneRate->currency) && $oneRate->currency != $latestCurrencyCode) {
                if ($oneRate->currency != $baseCurrencyCode || $baseRate != 1) {
                    $baseRate = $this->shipperDataHelper->getBaseCurrencyRate($oneRate->currency);
                    $latestCurrencyCode = $oneRate->currency;
                    if (!$baseRate) {
                        $carrierResultWithRates['error'] = __('Can\'t convert rate from "%1".',
                            $oneRate->currency);
                        $carrierResultWithRates['carriergroup_detail']['carrierGroupId'] = $carrierGroupId;
                        $this->shipperLogger->postWarning('Shipperhq_Shipper','Currency Rate Missing',
                            'Currency code in shipping rate is ' .$oneRate->currency
                            .' but there is no currency conversion rate configured so we cannot display this shipping rate');
                        continue;
                    }

                }
            }
            $this->populateRateLevelDetails((array)$oneRate, $carrierGroupDetail, $baseRate);

            $this->populateRateDeliveryDetails($oneRate, $carrierGroupDetail, $methodDescription, $dateFormat,
                $dateOption, $deliveryMessage);

            if ($methodDescription) {
                $title .= ' ' . $methodDescription;
            }
            $carrierType = $oneRate->carrierType;
            if($carrierType == 'shqshared') {
                $carrierType.= '_' .$oneRate->carrierType;
            }
            //create rateToAdd array - freight_rate, custom_duties,
            $rateToAdd = [
                'methodcode' => $oneRate->code,
                'method_title' => $title,
                'cost' => (float)$oneRate->shippingPrice * $baseRate,
                'price' => (float)$oneRate->totalCharges * $baseRate,
                'carrier_type' => $carrierRate->carrierType,
                'carrier_id' => $carrierRate->carrierId,
            ];

            if ($methodDescription) {
                $rateToAdd['method_description'] = $methodDescription;
            }
            $rateToAdd['carriergroup_detail'] = $carrierGroupDetail;

            $thisCarriersRates[] = $rateToAdd;
        }
        return $thisCarriersRates;
    }

    protected function populateCarrierLevelDetails($carrierRate, &$carrierGroupDetail)
    {
        $carrierGroupDetail['carrierType'] = $carrierRate->carrierType;
        $carrierGroupDetail['carrierTitle'] = $carrierRate->carrierTitle;
        $carrierGroupDetail['carrier_code'] = $carrierRate->carrierCode;
        $carrierGroupDetail['carrierName'] = $carrierRate->carrierName;
        $notice = $customDescription = false;
        if(!$this->shipperDataHelper->getConfigFlag('carriers/shipper/hide_notify') && isset($carrierRate->notices)) {
            $notice = '';
            foreach($carrierRate->notices as $oneNotice) {
                $notice .= $oneNotice ;
            }

        }
        $carrierGroupDetail['notice'] = $notice;
        if(isset($carrierRate->customDescription)) {
            $customDescription =  __($carrierRate->customDescription) ;
        }
        $carrierGroupDetail['custom_description'] = $customDescription;
    }

    protected function populateRateLevelDetails($rate, &$carrierGroupDetail, $currencyConversionRate)
    {
        $carrierGroupDetail['methodTitle'] = $rate['name'];
        $carrierGroupDetail['price'] = (float)$rate['totalCharges']*$currencyConversionRate;
        $carrierGroupDetail['cost'] = (float)$rate['shippingPrice']*$currencyConversionRate;
        $carrierGroupDetail['code'] = $rate['code'];
    }

    protected function populateRateDeliveryDetails($rate, &$carrierGroupDetail, &$methodDescription, $dateFormat, $dateOption, $deliveryMessage)
    {
        if($rate->deliveryDate && is_numeric($rate->deliveryDate)) {
            $deliveryDate = $this->_localeDate->formatDate(\DateTime($rate->deliveryDate/1000), $dateFormat);
            if($dateOption == \ShipperHQ\WS\Helper\RateHelper::DELIVERY_DATE_OPTION && isset($rate->deliveryDate)) {
//                $methodDescription = __(' %1 %2',$deliveryMessage, $deliveryDate);
//                if($rate->latestDeliveryDate && is_numeric($rate->latestDeliveryDate)) {
//                    $latestDeliveryDate = $this->_localeDate->formatDate($rate->latestDeliveryDate/1000, $dateFormat);
//                    $methodDescription.= ' - ' .$latestDeliveryDate;
//                }
            }
            else if($dateOption == Shipperhq_Shipper_Helper_Data::TIME_IN_TRANSIT
                && isset($rate->dispatchDate)) {
//                $numDays = floor(abs($rate->deliveryDate/1000 - $rate->dispatchDate/1000)/60/60/24);
//                if($rate->latestDeliveryDate && is_numeric($rate->latestDeliveryDate)) {
//                    $maxNumDays = floor(abs($rate->latestDeliveryDate/1000 - $rate->dispatchDate/1000)/60/60/24);
//                    $methodDescription = __(' (%1 - %2 %3)',$numDays, $maxNumDays, $deliveryMessage);
//                }
//                else {
//                    $methodDescription = __(' (%1 %2)',$numDays, $deliveryMessage);
//                }
            }
            $carrierGroupDetail['delivery_date'] = $deliveryDate;
        }
        if($rate->dispatchDate && is_numeric($rate->dispatchDate)) {
//            $dispatchDate = $this->_localeDate->formatDate(\DateTime($rate->dispatchDate/1000), $dateFormat);
//            $carrierGroupDetail['dispatch_date'] = $dispatchDate;
        }

    }

    protected function getLocaleInGlobals()
    {
        $locale = $this->shipperDataHelper->getGlobalSetting('preferredLocale');
        return $locale ? $locale : 'en-US';
    }

    protected function getDateFormat()
    {
        $dateFormat = $this->shipperDataHelper->getGlobalSetting('preferredLocale');
    }

    /**
     * Do remote request for and handle errors
     *
     * @return Mage_Shipping_Model_Rate_Result
     */
    protected function getQuotes()
    {
        $requestString = serialize($this->shipperRequest);
        $resultSet = $this->carrierCache->getCachedQuotes($requestString, $this->getCarrierCode());
        $timeout = $this->shipperDataHelper->getWebserviceTimeout();
        if (!$resultSet) {
            $initVal =  microtime(true);
            $resultSet = $this->shipperWSClientFactory->create()->sendAndReceive($this->shipperRequest,
                $this->shipperDataHelper->getRateGatewayUrl(), $timeout);
            $elapsed = microtime(true) - $initVal;
            $this->shipperLogger->postDebug('Shipperhq_Shipper', 'Short lapse',$elapsed);

            if (!$resultSet['result']) {
                $backupRates = $this->backupCarrier->getBackupCarrierRates($this->rawRequest, $this->getConfigData("backup_carrier"));
                if ($backupRates) {
                    return $backupRates;
                }
            }
            $this->carrierCache->setCachedQuotes($requestString, $resultSet, $this->getCarrierCode());

        }

        return $this->parseShipperResponse($resultSet['result']);

    }


    /**
     * @param $shipperResponse
     * @return Mage_Shipping_Model_Rate_Result
     */
    protected function parseShipperResponse($shipperResponse)
    {
        $debugRequest = $this->shipperRequest;
        $debugRequest->credentials = null;
        $debugData = ['request' => $debugRequest, 'response' => $shipperResponse];

        //first check and save globals for display purposes
        if (is_object($shipperResponse) && isset($shipperResponse->globalSettings)) {
            $globals = (array)$shipperResponse->globalSettings;
            $this->shipperDataHelper->getQuote()->setShipperGlobal($globals);
        }

        $result = $this->rateFactory->create();

        // If no rates are found return error message
        if (!is_object($shipperResponse)) {
            $this->shipperLogger->postInfo('Shipperhq_Shipper', 'Shipper HQ did not return a response', $debugData);

            return $this->returnGeneralError('Shipper HQ did not return a response - could not contact ShipperHQ. Please review your settings');
        } elseif (!empty($shipperResponse->errors)) {
            $this->shipperLogger->postInfo('Shipperhq_Shipper','Shipper HQ returned an error', $debugData);
            if (isset($shipperResponse->errors)) {
                foreach ($shipperResponse->errors as $error) {
                    $this->appendError($result, $error, $this->_code, $this->getConfigData('title'));
                }
            }
            return $result;
        } elseif (!isset($shipperResponse->carrierGroups)) {
            // DO NOTHING
        }

        if (isset($shipperResponse->carrierGroups)) {
            $carrierRates = $this->processRatesResponse($shipperResponse);
        } else {
            $carrierRates = [];
        }
        if (count($carrierRates) == 0) {
            $this->shipperLogger->postInfo('Shipperhq_Shipper','Shipper HQ did not return any carrier rates',$debugData);
            return $result;
        }
        foreach ($carrierRates as $carrierRate) {
            if (isset($carrierRate['error'])) {
                $carriergroupId = null;
                $carrierGroupDetail = null;
                if (array_key_exists('carriergroup_detail', $carrierRate)
                    && !is_null($carrierRate['carriergroup_detail'])
                ) {
                    if (array_key_exists('carrierGroupId', $carrierRate['carriergroup_detail'])) {
                        $carriergroupId = $carrierRate['carriergroup_detail']['carrierGroupId'];
                    }
                    $carrierGroupDetail = $carrierRate['carriergroup_detail'];
                }
                $this->appendError($result, $carrierRate['error'], $carrierRate['code'], $carrierRate['title'],
                    $carriergroupId, $carrierGroupDetail);
                continue;
            }

            if (!array_key_exists('rates', $carrierRate)) {
                $this->shipperLogger->postInfo('Shipperhq_Shipper', 'Shipper HQ did not return any rates for ' . $carrierRate['code'] . ' ' . $carrierRate['title'], $debugData);
            } else {

                foreach ($carrierRate['rates'] as $rateDetails) {
                    $rate = $this->rateMethodFactory->create();
                    $rate->setCarrier($carrierRate['code']);

                    $rate->setCarrierTitle($carrierRate['title']);

                    $methodCombineCode = preg_replace('/&|;| /', "_", $rateDetails['methodcode']);

                    $rate->setMethod($methodCombineCode);

                    $rate->setMethodTitle(__($rateDetails['method_title']));

                    if (array_key_exists('method_description', $rateDetails)) {
                        $rate->setMethodDescription(__($rateDetails['method_description']));
                    }
                    $rate->setCost($rateDetails['cost']);

                    $rate->setPrice($rateDetails['price']);

                    if (array_key_exists('carrier_type', $rateDetails)) {
                        $rate->setCarrierType($rateDetails['carrier_type']);
                    }

                    if (array_key_exists('carrier_id', $rateDetails)) {
                        $rate->setCarrierId($rateDetails['carrier_id']);
                    }

                    if (array_key_exists('carriergroup_detail', $rateDetails)
                        && !is_null($rateDetails['carriergroup_detail'])
                    ) {
                        $rate->setCarriergroupShippingDetails(
                            $this->shipperDataHelper->encodeShippingDetails($rateDetails['carriergroup_detail']));
                        if (array_key_exists('carrierGroupId', $rateDetails['carriergroup_detail'])) {
                            $rate->setCarriergroupId($rateDetails['carriergroup_detail']['carrierGroupId']);
                        }
                        if (array_key_exists('checkoutDescription', $rateDetails['carriergroup_detail'])) {
                            $rate->setCarriergroup($rateDetails['carriergroup_detail']['checkoutDescription']);
                        }
                    }
                    $result->append($rate);
                }
            }
        }
        $this->shipperLogger->postInfo('Shipperhq_Shipper', 'Rate request and result', $debugData);

        return $result;

    }

    /*
     *
     * Build array of rates based on split or merged rates display
     */
    protected function processRatesResponse($shipperResponse)
    {
        $this->shipperDataHelper->setStandardShipperResponseType();
        $carrierGroups = $shipperResponse->carrierGroups;
        $ratesArray = [];
        $globals = (array)$shipperResponse->globalSettings;
        $responseSummary = (array)$shipperResponse->responseSummary;
        foreach ($carrierGroups as $carrierGroup) {
            $carrierGroupDetail = (array)$carrierGroup->carrierGroupDetail;
            $carrierGroupId = array_key_exists('carrierGroupId', $carrierGroupDetail) ? $carrierGroupDetail['carrierGroupId'] : 0;

            $this->registry->unregister('shipperhq_transaction');
            $this->registry->register('shipperhq_transaction', $responseSummary['transactionId']);
            $carrierGroupDetail['transaction'] = $responseSummary['transactionId'];

            $this->setCarriergroupOnItems($carrierGroupDetail, $carrierGroup->products);
            $globals = array_merge($globals, $carrierGroupDetail);
            //Pass off each carrier group to helper to decide best fit to process it.
            //Push result back into our array
            foreach ($carrierGroup->carrierRates as $carrierRate) {
                $this->carrierConfigHandler->saveCarrierResponseDetails($carrierRate, $carrierGroupDetail, false);
                $carrierResultWithRates = $this->extractShipperHQRates($carrierRate, $carrierGroupId, $carrierGroupDetail);
                $ratesArray[] = $carrierResultWithRates;
            }
        }
        $this->shipperDataHelper->getQuote()->setShipperGlobal($globals);

        $carriergroupDescriber = $shipperResponse->globalSettings->carrierGroupDescription;
        if ($carriergroupDescriber != '') {
            $this->carrierConfigHandler->saveConfig($this->shipperDataHelper->getCarrierGroupDescPath(),
                $carriergroupDescriber);
        }

        $this->carrierConfigHandler->refreshConfig();

        return $ratesArray;
    }

    protected function setCarriergroupOnItems($carriergroupDetails, $productInRateResponse)
    {
        $quoteItems = $this->getQuote()->getAllItems();
        $rateItems = [];
        foreach ($productInRateResponse as $item) {
            $item = (array)$item;
            $rateItems[$item['sku']] = $item['qty'];
        }

        foreach ($quoteItems as $item) {
            if (array_key_exists($item->getSku(), $rateItems)) {
                $item->setCarriergroupId($carriergroupDetails['carrierGroupId']);
                $item->setCarriergroup($carriergroupDetails['name']);
            }
        }
        foreach ($this->rawRequest->getAllItems() as $quoteItem) {
            if (array_key_exists($quoteItem->getSku(), $rateItems)) {
                $quoteItem->setCarriergroupId($carriergroupDetails['carrierGroupId']);
                $quoteItem->setCarriergroup($carriergroupDetails['name']);
            }
        }
    }

    /**
     * Retrieve checkout quote model object
     *
     * @return Mage_Sales_Model_Quote
     */
    public function getQuote()
    {
        return $this->shipperDataHelper->getQuote();
    }

    /**
     *
     * Build up an error message when no carrier rates returned
     * @return Mage_Shipping_Model_Rate_Result
     */
    protected function returnGeneralError($message = null)
    {
        $result = $this->rateFactory->create();
        $error = $this->_rateErrorFactory->create();
        $error->setCarrier($this->_code);
        $error->setCarrierTitle($this->getConfigData('title'));
        $error->setCarriergroupId('');
        if ($message && $this->shipperDataHelper->getConfigValue('carriers/shipper/debug')) {
            $error->setErrorMessage($message);
        } else {
            $error->setErrorMessage($this->getConfigData('specificerrmsg'));
        }
        $result->append($error);
        return $result;
    }

    /**
     *
     * Generate error message from ShipperHQ response.
     * Display of error messages per carrier is managed in SHQ configuration
     *
     * @param $result
     * @param $errorDetails
     * @return Mage_Shipping_Model_Rate_Result
     */
    protected function appendError($result, $errorDetails, $carrierCode, $carrierTitle,
                                   $carrierGroupId = null, $carrierGroupDetail = null)
    {
        if (is_object($errorDetails)) {
            $errorDetails = get_object_vars($errorDetails);
        }
        if ((array_key_exists('internalErrorMessage', $errorDetails) && $errorDetails['internalErrorMessage'] != '')
            || (array_key_exists('externalErrorMessage', $errorDetails) && $errorDetails['externalErrorMessage'] != '')
        ) {
            $errorMessage = false;

            if ($this->getConfigData("debug") && array_key_exists('internalErrorMessage', $errorDetails)
                && $errorDetails['internalErrorMessage'] != ''
            ) {
                $errorMessage = $errorDetails['internalErrorMessage'];
            } else if (array_key_exists('externalErrorMessage', $errorDetails)
                && $errorDetails['externalErrorMessage'] != ''
            ) {
                $errorMessage = $errorDetails['externalErrorMessage'];
            }
            if (array_key_exists('externalErrorMessage', $errorDetails)
                && $errorDetails['externalErrorMessage'] != ''
            ) {
                $errorMessage = $errorDetails['externalErrorMessage'];
            }


            if ($errorMessage) {
                $error = $this->_rateErrorFactory->create();
                $error->setCarrier($carrierCode);
                $error->setCarrierTitle($carrierTitle);
                $error->setErrorMessage($errorMessage);
                if (!is_null($carrierGroupId)) {
                    $error->setCarriergroupId($carrierGroupId);
                }
                if (is_array($carrierGroupDetail) && array_key_exists('checkoutDescription', $carrierGroupDetail)) {
                    $error->setCarriergroup($carrierGroupDetail['checkoutDescription']);
                }

                $result->append($error);

                $this->shipperLogger->postInfo('Shipperhq_Shipper','Shipper HQ returned error', $errorDetails);
            }

        }
        return $result;
    }
}
