<?php

namespace HappyTypes;

/**
 * Interface MoneyConverter
 * Implement this interface if you want your Money object would support money conversion with method convertTo(..)
 * for more details see Money::setDefaultMoneyConverter()
 *
 * @see Money::setDefaultMoneyConverter()
 */
interface MoneyConverter
{
    /**
     * @param string $amount
     * @param string $currency
     * @param string $convertToCurrency
     *
     * @return string
     */
    function convert($amount, $currency, $convertToCurrency);
}
