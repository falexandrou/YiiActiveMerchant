<?php
/**
 * Simple wrapper for
 *
 * @see CApplicationComponent
 */

use AktiveMerchant\Billing;
use AktiveMerchant\Billing\Gateways;


class YiiActiveMerchant extends CApplicationComponent
{
    // Enviroment related constants
    const MODE_TEST     = 'test';
    const MODE_LIVE     = 'live';

    // Payment Method related constants
    const GATEWAY_AUTHORIZENET       = 'AuthorizeNet';
    const GATEWAY_BOGUS              = 'Bogus';
    const GATEWAY_BRIDGEPAY          = 'BridgePay';
    const GATEWAY_CARDSTREAM         = 'Cardstream';
    const GATEWAY_CENTINEL           = 'Centinel';
    const GATEWAY_CENTINELPAYPAL     = 'CentinelPaypal';
    const GATEWAY_EWAY               = 'Eway';
    const GATEWAY_EXACT              = 'Exact';
    const GATEWAY_FATZEBRA           = 'FatZebra';
    const GATEWAY_HSBCGLOBALIRIS     = 'HsbcGlobalIris';
    const GATEWAY_HSBCSECUREEPAYMENTS = 'HsbcSecureEpayments';
    const GATEWAY_IRIDIUM            = 'Iridium';
    const GATEWAY_MERCURY            = 'Mercury';
    const GATEWAY_MONERIS            = 'Moneris';
    const GATEWAY_MONERISUS          = 'MonerisUs';
    const GATEWAY_PAYFLOW            = 'Payflow';
    const GATEWAY_PAYFLOWUK          = 'PayflowUk';
    const GATEWAY_PAYPAL             = 'Paypal';
    const GATEWAY_PAYPALEXPRESS      = 'PaypalExpress';
    const GATEWAY_PIRAEUSPAYCENTER   = 'PiraeusPaycenter';
    const GATEWAY_REALEX             = 'Realex';
    const GATEWAY_WORLDPAY           = 'Worldpay';

    // Currency related constants
    const CURRENCY_USD              = 'USD';
    const CURRENCY_EUR              = 'EUR';
    const CURRENCY_GBP              = 'GBP';

    /**
     * @var string the mode of the component
     *  Whether we're testing or it's on production
     */
    public $mode        = self::MODE_TEST;

    /**
     * @var array the gateways available in the system
     */
    public $gateways    = array();

    /**
     * @var array   the options for the adapter (cURL in our case)
     */
    public $adapterOptions = array();

    /**
     * @var array   the setup to configure the gateway with
     */
    protected $setup    = array();

    /**
     * @var string  the currency to use
     */
    protected $currency     = self::CURRENCY_EUR;

    /**
     * @var \AktiveMerchant\Billing\Gateway the gateway to use
     */
    private $_gateway;

    /**
     * @var AktiveMerchant\Billing\Response the response returned from the gateway
     */
    private $_lastResponse;

    /**
     * @see CApplicationComponent::init()
     * @throws CException   on server's invalid setup
     */
    public function init()
    {
        parent::init();

        // Register the component
        static::register();

        // No curl? Problem
        if (!function_exists('curl_init'))
            throw new CException('Curl is required');

        // No SimpleXML? problem
        if (!function_exists('simplexml_load_string'))
            throw new CException('SimpleXML is required');

        if (empty($this->gateways))
            throw new CException('You have to provide a list of gateways first');

        AktiveMerchant\Billing\Base::mode($this->mode);
    }

    /**
     * @param CEvent    the event to raise
     */
    protected function onBeforePurchase(CEvent $event)
    {
        $this->raiseEvent('onBeforePurchase', $event);
    }

    /**
     * @param CEvent    the event to raise after purchase
     */
    protected function onAfterPurchase(CEvent $event)
    {
        $this->raiseEvent('onAfterPurchase', $event);
    }

    /**
     * @return boolean whether purchase should process
     */
    protected function beforePurchase()
    {
        if ($this->hasEventHandler('onBeforePurchase')) {
            $event = new CEvent($this);
            $this->onBeforePurchase($event);
            return $event->isValid;
        }

        return true;
    }

    /**
     * @return boolean whether the part after the purchase was successful or not
     */
    public function afterPurchase()
    {
        if ($this->hasEventHandler('onAfterPurchase')) {
            $event = new CEvent($this, array(
                'buyer' => $this->getBuyerAttributes(),
            ));
            $this->onAfterPurchase($event);
            return $event->isValid;
        }

        return true;
    }

    /**
     * Registers the path to the AktiveMerchant main library
     */
    public static function register()
    {
        Yii::setPathOfAlias('AktiveMerchant', dirname(__FILE__) . '/Aktive-Merchant/lib/AktiveMerchant');
    }

    /**
     * @return \AktiveMerchant\Billing\Gateway  the gateway object
     */
    public function getGateway()
    {
        return $this->_gateway;
    }

    /**
     * @param string    the payment gateway to use
     */
    public function setGateway($gateway)
    {
        if (!$this->gatewayExists($gateway))
            throw new CException("Gateway '{$gateway}' not found");

        return $this->_gateway = $this->initGateway($gateway);
    }

    /**
     * @param IActiveMerchantPurchasable    the item to purchase
     * @param integer                       the quantity for the item
     * @return boolean                      whether the item was added
     */
    public function addProduct($model, $quantity=1)
    {
        if (!$model instanceof IActiveMerchantPurchasable)
            throw new CException("Product should be an instance of IActiveMerchantPurchasable");

        $items = isset($this->setup['items']) ? $this->setup['items'] : array();

        $item = array(
			'name' 			=> $model->title,
			'description'	=> $model->description,
			'unit_price'	=> $model->price,
			'quantity'		=> $quantity,
			'id'			=> $model->id,
		);

        array_push($items, $item);

        $this->setup['items'] = $items;
        return true;
    }

    /**
     * @return array    the attributes of the buyer
     */
    public function getBuyerAttributes()
    {
        return array();
    }

    /**
     * @return double   the total price for all the items to purchase
     */
    public function getTotalPrice()
    {
        if (!isset($this->setup['items']))
            return 0.00;

        $total = 0.00;
        foreach($this->setup['items'] as $item) {
            $itemPrice = (int)$item['quantity'] * (double)$item['unit_price'];
            $total += $itemPrice;
        }

        return $total;
    }

    /**
     * Initializes a purchase
     *
     * @param string    the url to go after a successful purchase
     * @param string    the url to go after a failed purchase
     * @param array     any extra options provided to the gateway
     * @return boolean  whether the initialization was successful or not
     */
    public function initPurchase($successUrl=null, $errorUrl=null, $options=array())
    {
        // Verify that this is a CHttpRequest
        $hasRequest = Yii::app()->hasComponent('request') && Yii::app()->request instanceof CHttpRequest;
        if (!$hasRequest)
            throw new CException("Sorry, but this can only run when the `request` component is set");

        if (!$this->beforePurchase())
            return false;

        // Get the current url for when the success url or the error url are not specified
        $currentUrl = Yii::app()->hasComponent('request') ? Yii::app()->request->requestUri : '/';
        if ($successUrl === null)
            $successUrl = $currentUrl;
        if ($errorUrl === null)
            $errorUrl = $currentUrl;

        // Setup the options
        $this->setup = CMap::mergeArray(array(
            'return_url' 		=> $successUrl,
			'cancel_return_url'	=> $errorUrl,
            'items'             => array(),
        ), $this->setup);

        // Store the last response so that it can be used for url generation etc
        $this->_lastResponse = $this->gateway->setupPurchase($this->totalPrice, $this->setup, $options);

        // Finally return a boolean whether we were successful or not
        return $this->_lastResponse->success();
    }

    /**
     * @return string   the url to redirect after the
     */
    public function getPaymentUrl()
    {
        return $this->gateway->urlForToken($this->_lastResponse->token());
    }

    /**
     * Finalize the purchase
     *
     * @param double    the amount for the purchase
     * @param array     any extra options required
     * @return boolean  whether the purchase was successful
     */
    public function doPurchase($money, $options=array())
    {
        $this->_lastResponse = $this->gateway->purchase($money, $options);
        if ($this->_lastResponse->success())
            return $this->afterPurchase();
        return false;
    }

    /**
     * Initializes the gateway
     *
     * @param string the name of the gateway
     * @return AktiveMerchant\Billing\Base
     * @throws CException
     */
    protected function initGateway($name)
    {
        if ($this->gatewayExists($name)) {
            // Initialize the gateway
            $g = AktiveMerchant\Billing\Base::gateway($name, $this->gateways[$name]);

            // Apply the options in the config
            foreach($this->adapterOptions as $key => $value) {
                $g->getAdapter()->setOption($key, $value);
            }

            return $g;
        }

        // Invalid configuration
        throw new CException("Unsupported gateway: '{$name}'");
    }

    /**
     * Checks if a gateway exists
     *
     * @param string    the name of the gateway
     * @return boolean  whether the gateway exists
     */
    protected function gatewayExists($name)
    {
        return in_array($name, static::optsAllGateways()) && isset($this->gateways[$name]);
    }

    /**
     * @return array    the gateways enabled in AktiveMerchant module
     */
    public static function optsAllGateways()
    {
        return array(
            self::GATEWAY_AUTHORIZENET,
            self::GATEWAY_BOGUS,
            self::GATEWAY_BRIDGEPAY,
            self::GATEWAY_CARDSTREAM,
            self::GATEWAY_CENTINEL,
            self::GATEWAY_CENTINELPAYPAL,
            self::GATEWAY_EWAY,
            self::GATEWAY_EXACT,
            self::GATEWAY_FATZEBRA,
            self::GATEWAY_HSBCGLOBALIRIS,
            self::GATEWAY_HSBCSECUREEPAYMENTS,
            self::GATEWAY_IRIDIUM,
            self::GATEWAY_MERCURY,
            self::GATEWAY_MONERIS,
            self::GATEWAY_MONERISUS,
            self::GATEWAY_PAYFLOW,
            self::GATEWAY_PAYFLOWUK,
            self::GATEWAY_PAYPAL,
            self::GATEWAY_PAYPALEXPRESS,
            self::GATEWAY_PIRAEUSPAYCENTER,
            self::GATEWAY_REALEX,
            self::GATEWAY_WORLDPAY,
        );
    }
}
