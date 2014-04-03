<?php
/**
 * Class MutableMoney
 *
 * This class is the same as Money class except that this class is not Value Object so it doesn't create
 * new instances but operations are applied to the same instances.
 * so add() and subtract() methods will affect instance values.
 *
 * Uses this class for intensive computations of lot of values
 */
class MutableMoney extends Money
{

    /**
     * Static factory method will always create an new instance for MutableMoney
     * @param string $currency
     * @param $precision
     * @return MutableMoney|null
     */
    public static function undefined($currency = '', $precision = -1)
    {
        return new MutableMoney(null, $currency, $precision);
    }

    /**
     * Static factory method will always create a new instance for MutableMoney
     *
     * Money amount must be string type only. Number must be dot separated. e.g. "123.45" not "123,45" (bc limitation)
     * You cannot use float or integer types its defensive restriction to ensure that you wont loose precision accidentaly.
     * In case if you want to specify integers or floats or string you can use  createUnsafe() method. but its not recommended.
     *
     * If you want create undefined value use create(false) or create(null) or create('')
     * but better use Money::undefined() method instead.
     *
     * @param string $amount
     * @param string $currency
     * @param int $precision
     * @return MutableMoney|null
     */
    public static function create($amount = '', $currency = '', $precision = -1)
    {
        return self::isUndefinedValue($amount) ? self::undefined($currency, $precision) : new MutableMoney($amount, $currency, $precision);
    }

    /**
     * Method creates mutable money object from immutable money.
     * @param Money $money
     * @return MutableMoney|null
     */
    public static function from(Money $money)
    {
        return $money->isDefined() ?
            self::create($money->getAmount(), $money->getCurrency(), $money->getPrecision()) :
            self::undefined($money->getCurrency(), $money->getPrecision());
    }

    /**
     * In mutable money we always update current instance values and return reference to self
     * @param $amount
     * @param $currency
     * @param $precision
     * @return $this|Money
     */
    protected function createMoney($amount, $currency, $precision)
    {
        $this->updateValue($amount, $currency, $precision);

        return $this;
    }

    /**
     * @return Money
     */
    public function immutable()
    {
        return Money::create($this->getAmount(), $this->getCurrency(), $this->getPrecision());
    }

    /**
     * @param Money $money
     * @param $precision
     * @return bool
     */
    public function isEqualExact(Money $money, $precision = -1)
    {
        if ($this->isUndefined() || $money->isUndefined())
            return $this->isUndefined() === $money->isUndefined();

        $amountIsEqual = bccomp($this->getAmount(), $money->getAmount(), $precision < 0 ? $this->getPrecision() : $precision) == 0;

        return ($amountIsEqual &&
            $this->getCurrency() === $money->getCurrency() &&
            ($precision < 0 ? $this->getPrecision() === $money->getPrecision() : true));
    }

}