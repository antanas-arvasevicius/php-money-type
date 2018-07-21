<?php
/**
 * Class Money is a Value Object, that means that all operations will create new instance of Money
 * and all instances are immutable
 *
 * This class is used in all system to describe money value. It is {amount, currency} tuple.
 *
 * To persist this object in database please use  DECIMAL(12, 4) for amount, and CHAR(3) BINARY for currency!
 *
 *
 * Methods add(), subtract(), compare(), isEqual() will implicitly convert specified argument into appropriate currency.
 *
 * For currency conversion is used @see Currency::convert()
 *
 * Default decimal precision is 4 digits
 *
 * You can compare only two objects with exact the same precisions.
 * All operations will use highest precisions of available operands.
 *
 * use Money::create($amount = '', $currency = '', $precision = -1) static factory to create Money instances.
 * don`t use constructors directly!
 *
 * amount must be specified in string type  and decimal separator must be a dot (e.g.  "123.45")
 * You cannot specify  Money::create(123.45) or Money::create("123,45").
 * only Money::create("123.45") <- string, and dot separated number
 *
 * use Money::undefined($currency = '', $precision = -1) to create value with unknown money amount.
 * its syntactic sugar for Money::create(false), Money::create(null) or Money::create('')
 *
 * e.g. Money::create('') === Money::undefined();
 *      Money::create('0.00') !== Money::undefined();
 *
 * you can use methods isDefined() isUndefined() to check whether Money object has an defined amount.
 *
 * all undefined values in any money operations are allowed and will be casted to zero
 *
 *
 * Note: please remind that currency conversions will accumulate computation errors, so operations like:
 *
 * a = Money::create('10.00', 'LTL')
 * b = a->convertTo('USD')->convertTo('LVL')->convertTo('LTL');
 *
 * will produce value which was not equal to previous value due conversion errors. absolute error is +-0.0003
 *
 * you can compare these values with isEqualExact() @see Money::isEqualExact() with lower precision
 *
 */
class Money
{

    /**
     * @var string
     */
    private $amount;

    /**
     * @var string
     */
    private $currency;

    /**
     * @var bool
     */
    private $zero;

    /**
     * @var int [-1 | 0 | +1]
     */
    private $comparedToZero;

    /**
     * @var boolean
     */
    private $undefined;

    /**
     * Default precision of Money
     * @var int
     */
    private $precision = 4;

    /**
     * see defaultConverter
     * @var MoneyConverter
     */
    private static $moneyConverter = null;

    /**
     * Currency which will be applied for all new Money instances
     * @var string
     */
    private static $defaultCurrency = null;

    function __construct($amount, $currency, $precision = -1)
    {
        if ($amount && !is_string($amount))
            throw new RuntimeException("Money: `amount` argument must be string value. you passed: " . gettype($amount));

        if ($precision >= 0)
            $this->precision = $precision;

        $amountFixed = !$amount ? '0.' . str_repeat('0', $this->precision) : bcadd($amount, '0', $this->precision);

        $this->updateValue($amountFixed, $currency === '' && self::$defaultCurrency ? self::$defaultCurrency : $currency, $this->precision);

        $this->undefined = self::isUndefinedValue($amount);
    }

    protected function updateValue($amount, $currency, $precision)
    {
        if ($this->precision != $precision) {

            $this->amount = bcadd($amount, '0', $precision);
            $this->precision = $precision;

        } else {
            $this->amount   = $amount;
        }

        $this->currency = $currency;

        $this->comparedToZero = $amount ? bccomp($this->amount, '0', $this->precision) : 0;
        $this->zero           = $amount ? ($this->comparedToZero === 0) : true;
        $this->undefined      = self::isUndefinedValue($amount);
    }

    /**
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
     * @return Money
     */
    public static function create($amount = '', $currency = '', $precision = -1)
    {
        return self::isUndefinedValue($amount) ? self::undefined($currency, $precision) : new Money($amount, $currency, $precision);
    }

    /**
     * You can pass amount as one of (int, float, double) and it will be converted to string.
     * Better use static method create() and pass only a string.
     *
     * @param string|integer|null $amount
     * @param string $currency
     * @param        $precision
     *
     * @throws RuntimeException
     * @return Money
     */
    public static function createUnsafe($amount = '', $currency = '', $precision = -1)
    {
        if (is_string($amount)) $convertedAmount = $amount;
        else if (is_numeric($amount)) $convertedAmount = number_format($amount, $precision >= 0 ? $precision : 4, '.', '');
        else if (is_null($amount)) $convertedAmount = null;
        else throw new RuntimeException("argument `amount` must be string,float,double or int. you passed: " . gettype($amount));

        return self::create($convertedAmount, $currency, $precision);
    }

    /**
     * Method sets default converter for Money conversions which are applied with method convertTo()
     * If you dont specify default money converter any conversions to money would throw exception
     * @param MoneyConverter $converter
     */
    public static function setDefaultMoneyConverter(MoneyConverter $converter = null)
    {
        self::$moneyConverter = $converter;
    }

    /**
     * Method sets default currency for all Money instances, this currency will be applied
     * to all constructors  undefined() create() createUnsafe()  if `currency` argument is not specified.
     * @param string|null $defaultCurrency
     */
    public static function setDefaultCurrency($defaultCurrency)
    {
        self::$defaultCurrency = $defaultCurrency;
    }

    /**
     * @param $value
     * @return bool
     */
    protected static function isUndefinedValue($value)
    {
        return ($value === '' || $value === null || $value === false);
    }


    /**
     * @var array|Money[]
     */
    private static $_undefinedValues = array();

    /**
     * @param string $currency
     * @param $precision
     * @return Money|null
     */
    public static function undefined($currency = '', $precision = -1)
    {
        return new Money(null, $currency, $precision);
    }


    /**
     * Method adds money amount to current money instance.
     * If currencies doesn`t match then we leave this instance currency and convert argument into that currency.
     * If this instances currency is unknown then we use default currency from object passed by argument
     * @param Money $money
     * @throws RuntimeException
     * @return Money
     */
    public function add(Money $money)
    {
        if (!$this->currency && !$this->isZero())
            throw new RuntimeException("Money: cannot add money to nonzero with unknown currency. (" . $this->asString() . ") + (" . $money->asString() . ")");

        $toCurrency  = $this->currency ? $this->currency : $money->currency;
        $toPrecision = $this->precision > $money->precision ? $this->precision : $money->precision;

        $value = bcadd($this->amount, $money->convertTo($toCurrency)->getAmount(), $toPrecision);

        return $this->createMoney($value, $toCurrency, $toPrecision);
    }

    /**
     * @param $number
     * @return Money
     */
    public function multiplyByInteger($number) {
        if (!is_int($number))
            throw new RuntimeException("Money: cannot multiply money by " . $number . ". Only integer value is acceptable.");

        $value = bcmul($this->amount, $number, $this->precision);

        return $this->createMoney($value, $this->currency, $this->precision);
    }

    /**
     * Method subtracts money amount from current money instance
     * If currencies doesn`t match then we leave this instance currency and convert argument into that currency.
     * If this instances currency is unknown then we use default currency from object passed by argument
     * @param Money $money
     * @return Money
     */
    public function subtract(Money $money)
    {
        $toCurrency  = $this->currency ? $this->currency : $money->currency;
        $toPrecision = $this->precision > $money->precision ? $this->precision : $money->precision;


        $value = bcsub($this->amount, $money->convertTo($toCurrency)->getAmount(), $toPrecision);

        return $this->createMoney($value, $toCurrency, $toPrecision);
    }

    /**
     * Method compares two Money objects and returns zero if equal, -1 if current is less than specified, or +1 if current is greater than specified.
     * If currencies don`t match we implicitly convert argument into first instance currency.
     * Precisions must be equal for both values
     *
     * @param Money $money
     * @return int   -1 | 0 | +1
     * @throws RuntimeException
     */
    public function compare(Money $money)
    {
        $isZero = $this->isZero();

        if (!$this->currency && !$isZero && !$money->isZero())
            throw new RuntimeException("Money: cannot compare money because we don`t know of convert  money currency (amount={$this->amount}, currency=null)");

        if ($this->precision !== $money->precision)
            throw new RuntimeException("Money: compare error, precisions don`t match. (this.precision={$this->precision}, argument.precision={$money->precision})");


        // * if comparing zero with argument, then compare argument with zero instead. then invert results.
        if ($isZero) {
            $r = $money->compareToZero();

            return $r == 0 ? 0 : ($r > 0 ? -1 : +1);
        }

        // * if currency is not specified and we comparing with zero then ignore currencies.
        if (!$this->currency && $money->isZero()) {
            return $this->compareToZero();
        }

        return bccomp($this->amount, $money->convertTo($this->currency)->getAmount(), $this->precision);
    }

    /**
     * Method checks is given money is equal to current. Return true if yes.
     * If currencies don`t match then we convert second value to first`s currency then comparing is done on that.
     * Precisions must be equal for both values
     *
     * @param Money $money
     * @return bool
     */
    public function isEqual(Money $money)
    {
        return $this->compare($money) === 0;
    }

    /**
     * Method checks is given money is exactly equal as this. must match amount, currency and precision.
     * If price is undefined it must match only undefined prices.
     * @param Money $money
     * @param $precision
     * @return bool
     */
    public function isEqualExact(Money $money, $precision = -1)
    {
        if ($this->undefined || $money->undefined)
            return $this->undefined === $money->undefined;

        return ($precision >= 0) ? $this->toPrecision($precision)->isEqualExact($money->toPrecision($precision)) :
            ($this->amount === $money->amount &&
                $this->currency === $money->currency &&
                $this->precision === $money->precision);
    }

    /**
     * Method compares this money object to zero.
     * @return int   -1 | 0 | +1
     */
    public function compareToZero()
    {
        return $this->comparedToZero;
    }

    /**
     * @param Money $money
     * @return bool
     */
    public function le(Money $money)
    {
        return $this->compare($money) <= 0;
    }

    /**
     * @param Money $money
     * @return bool
     */
    public function lt(Money $money)
    {
        return $this->compare($money) < 0;
    }

    /**
     * @param Money $money
     * @return bool
     */
    public function ge(Money $money)
    {
        return $this->compare($money) >= 0;
    }

    /**
     * @param Money $money
     * @return bool
     */
    public function gt(Money $money)
    {
        return $this->compare($money) > 0;
    }

    /**
     * Alias to isEqual()
     * @param Money $money
     * @return bool
     */
    public function eq(Money $money)
    {
        return $this->isEqual($money);
    }

    /**
     * Method convert money to specified currency
     * @param $currency
     * @return Money
     * @throws RuntimeException
     */
    public function convertTo($currency)
    {
        // * skip conversion if currency are equal
        if ($this->currency === $currency)
            return $this;

        if (self::$moneyConverter === null)
            throw new RuntimeException("Money: cannot convert money ".$this->asString().", because you haven't provide any money converter using setDefaultMoneyConverter(..) method.");


        if (!$this->isZero()) {
            if (!$this->currency)
                throw new RuntimeException("Money: cannot convert money to specified currency({$currency}) because we don`t know of convert money currency (amount={$this->amount}, currency=null)");

            $value = ($this->currency !== $currency) ? self::$moneyConverter->convert($this->amount, $this->currency, $currency) : $this->amount;
        } else $value = $this->isDefined() ? $this->amount : '';

        return $this->createMoney($value, $currency, $this->precision);
    }

    /**
     * Method returns new value with specified precision
     * @param $precision
     * @return Money
     */
    public function toPrecision($precision)
    {
        return $this->createMoney($this->amount, $this->currency, $precision);
    }

    protected function createMoney($amount, $currency, $precision)
    {
        return Money::create($amount, $currency, $precision);
    }

    /**
     * Method returns negated value of current value.  e.g.  (+50.00).negate() => -50.00
     * @return Money
     */
    public function negate()
    {
        $amount = bcmul($this->amount, '-1', $this->precision);

        return $this->createMoney($amount, $this->currency, $this->precision);
    }

    /**
     * @return bool
     */
    public function isZero()
    {
        return $this->zero;
    }

    /**
     * @return bool
     */
    public function isDefined()
    {
        return !$this->undefined;
    }

    /**
     * @return bool
     */
    public function isUndefined()
    {
        return $this->undefined;
    }

    /**
     * @return string
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * @return string
     */
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * @return int
     */
    public function getPrecision()
    {
        return $this->precision;
    }


    /**
     * Method returns format string of current value in format "{amount} {currency}" (e.g. 4.29 LTL)
     * @return string
     */
    public function asString()
    {
        return $this->undefined ? "undefined {$this->currency}" : "{$this->amount} {$this->currency}";
    }

}
