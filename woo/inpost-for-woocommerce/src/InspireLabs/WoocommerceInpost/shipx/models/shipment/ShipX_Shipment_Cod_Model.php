<?php
namespace InspireLabs\WoocommerceInpost\shipx\models\shipment;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
} // Exit if accessed directly.

class ShipX_Shipment_Cod_Model
{
    /**
     * @var float
     */
    private $amount;

    /**
     * @var string
     */
    private $currency;

    /**
     * @return float
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * @param float $amount
     */
    public function setAmount($amount)
    {
        $this->amount = $amount;
    }

    /**
     * @return string
     */
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * @param string $currency
     */
    public function setCurrency($currency)
    {
        $this->currency = $currency;
    }

}