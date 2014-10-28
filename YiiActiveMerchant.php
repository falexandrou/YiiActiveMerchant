<?php
/**
 * Simple wrapper for
 *
 * @see CApplicationComponent
 */
class YiiActiveMerchant extends CApplicationComponent
{
    // Enviroment related constants
    const MODE_TEST     = 'test';
    const MODE_LIVE     = 'live';

    /**
     * @var string the mode of the component
     *  Whether we're testing or it's on production
     */
    public $mode        = self::MODE_TEST;

    /**
     * @var array the list of available gateways
     */
    private $_gateways  = array();

    /**
     * @see CApplicationComponent::init()
     */
    public function init()
    {
        parent::init();
        static::register();

        if (!function_exists('curl_init'))
            throw new CException('Curl is required');

        if (!function_exists('simplexml_load_string'))
            throw new CException('SimpleXML is required');

        AktiveMerchant\Billing\Base::mode($this->mode);
    }

    /**
     * Registers the path to the AktiveMerchant main library
     */
    public static function register()
    {
        Yii::setPathOfAlias('AktiveMerchant', dirname(__FILE__) . '/lib/AktiveMerchant');
    }

    /**
     * Get supported gateway
     *
     * @param string the gateway's name
     * @return AktiveMerchant\Billing\Gateway
     */
    public function getGateway($name)
    {
        if (!isset($this->_gateways[$name]))
            $this->_gateways[$name] = $this->_initGateway($name);
        return $this->_gateways[$name];
    }

    public function setGateways($gateways=array())
    {

    }

    /**
     * Returns a credit card
     *
     * @param array $params
     * @return AktiveMerchant\Billing\CreditCard
     */
    public function getCreditCard($params)
    {
        return new AktiveMerchant\Billing\CreditCard($params);
    }

    /**
     * Initializes the gateway
     *
     * @param string the name of the gateway
     * @return AktiveMerchant\Billing\Base
     * @throws CException
     */
    protected function _initGateway($name)
    {
        if ($this->_existsGateway($name))
            return AktiveMerchant\Billing\Base::gateway($name, $this->gateways[$name]);

        throw new CException("Unsupported gateway: '{$name}'");
    }

    /**
     * Checks if a gateway exists
     *
     * @param string the name of the gateway
     * @return boolean
     */
    protected function _existsGateway($name)
    {
        return isset($this->gateways[$name]);
    }

    /**
     * @return array the gateways enabled in AktiveMerchant module
     */
    public static function optsGateways()
    {
        return array(
            'AuthorizeNet',
            'Bogus',
            'BridgePay',
            'Cardstream',
            'Centinel',
            'CentinelPaypal',
            'Eway',
            'Exact',
            'FatZebra',
            'HsbcGlobalIris',
            'HsbcSecureEpayments',
            'Iridium',
            'Mercury',
            'Moneris',
            'MonerisUs',
            'Payflow',
            'PayflowUk',
            'Paypal',
            'PaypalExpress',
            'PiraeusPaycenter',
            'Realex',
            'Worldpay',
        );
    }
}
