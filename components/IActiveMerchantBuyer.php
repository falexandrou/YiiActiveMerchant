<?php
/**
 * Interface that applies to the buyer objects
 */
interface IActiveMerchantBuyer
{
    /**
     * @return array the contact for the buyer
     */
    public function getContact();

    /**
     * @return array the address to use in the payment gateway
     */
    public function getAddress();
}
