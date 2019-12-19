<?php

namespace HappyTypes\Test\MoneyType;

use HappyTypes\MoneyConverter;
use RuntimeException;

class MockedMoneyConverter implements MoneyConverter
{
    private $currencies = array(
        'LTL' => 1.0000,
        'USD' => 0.40000,
        'EUR' => 0.28962,
        'LVL' => 0.2035461,
    );

    /**
     * @param string $amount
     * @param string $currency
     * @param string $convertToCurrency
     *
     * @return string
     */
    public function convert($amount, $currency, $convertToCurrency)
    {
        $converted_amount = ($amount / $this->getRatio($currency)) * $this->getRatio($convertToCurrency);
        return number_format($converted_amount, 4, '.', '');
    }

    /**
     * @param $currency
     *
     * @return float
     * @throws RuntimeException
     */
    private function getRatio($currency)
    {
        if (!isset($this->currencies[$currency]))
            throw new RuntimeException("cannot find currency ratio (currency: {$currency})");

        return $this->currencies[$currency];
    }
}
