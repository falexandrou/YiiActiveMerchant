<?php
/**
 * Interface for items that can be purchased via the ActiveMerchant module
 */
interface IActiveMerchantPurchasable
{
    /**
     * @return integer the id of the item to be purchased
     */
    public function getId();

    /**
     * @return double the price for the item to be purchased
     */
    public function getPrice();

    /**
     * @return string the title of the item to be purchased
     */
    public function getTitle();

    /**
     * @return string the description of the item to be purchased
     */
    public function getDescription();
}
