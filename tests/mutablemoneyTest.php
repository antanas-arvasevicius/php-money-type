<?php
require_once(dirname(__FILE__).DIRECTORY_SEPARATOR.'MockedMoneyConverter.php');

class MutableMoneyTest extends PHPUnit_Framework_TestCase
{

    public function setUp()
    {
        Money::setDefaultMoneyConverter(new MockedMoneyConverter());
        Money::setDefaultCurrency(null);
    }

    public function provider_testAdd()
    {
        return array(


            array(MutableMoney::create('0.00', 'LTL'), Money::create('0.00', 'LTL'), Money::create('0.00', 'LTL')),

            // if ZERO then implicitly infering currency from second parameter. first money currency is unknown and must be ZERO, overwise exception is thrown
            array(MutableMoney::create('0.00', ''), Money::create('10.00', 'LTL'), Money::create('10.00', 'LTL')),

            array(MutableMoney::create('15.33', 'LTL'), Money::create('10.33', 'LTL'), Money::create('25.66', 'LTL')),
            array(MutableMoney::create('-10.50', 'USD'), Money::create('10.50', 'USD'), Money::create('0', 'USD')),
            array(MutableMoney::create('-10.50', 'USD'), Money::create('11.50', 'USD'), Money::create('1', 'USD')),
            array(MutableMoney::create('-10.50', 'USD'), Money::create('11.50', 'USD'), Money::create('1.0000', 'USD')),

            // if precisions wont match we use the bigger one
            array(MutableMoney::create('-10.50', 'USD', 2), Money::create('12.55', 'USD', 4), Money::create('2.0500', 'USD', 4)),
            array(MutableMoney::create('-10.50', 'USD', 2), Money::create('12.55987', 'USD', 4), Money::create('2.0598', 'USD', 4)),

        );
    }

    /**
     * @dataProvider provider_testAdd
     */
    public function testAdd(MutableMoney $a, Money $b, Money $expectedResult)
    {
        $result = $a->add($b);
        $this->assertTrue($result->isEqual($expectedResult), 'Test A + B failed. ' . $a->asString() . ' + ' . $b->asString() . ' = ' . $result->asString() . ', expected: ' . $expectedResult->asString());
    }


    public function provider_testSubtract()
    {
        return array(

            array(MutableMoney::create('0.00', ''), Money::create('0.00', ''), Money::create('0.00', '')),
            array(MutableMoney::create('0.00', ''), Money::create('0.00', ''), Money::create('0.00', 'LTL')),

            // infering currency from second parameter. first money currency is unknown.
            array(MutableMoney::create('0.00', ''), Money::create('10.00', 'LTL'), Money::create('-10.00', 'LTL')),

            // regular tests
            array(MutableMoney::create('15.33', 'LTL'), Money::create('10.33', 'LTL'), Money::create('5.00', 'LTL')),
            array(MutableMoney::create('-10.50', 'USD'), Money::create('10.50', 'USD'), Money::create('-21.00', 'USD')),
            array(MutableMoney::create('-10.50', 'USD'), Money::create('11.50', 'USD'), Money::create('-22.00', 'USD')),

            // if precisions wont match we use the bigger one
            array(MutableMoney::create('-10.50', 'USD', 2), Money::create('12.55', 'USD', 4), Money::create('-23.0500', 'USD', 4)),
            array(MutableMoney::create('-10.50', 'USD', 2), Money::create('12.55987', 'USD', 4), Money::create('-23.0598', 'USD', 4)),
            array(MutableMoney::create('65.50', 'USD', 2), Money::create('60.5074', 'USD', 4), Money::create('4.9926', 'USD', 4)),
        );
    }

    /**
     * @dataProvider provider_testSubtract
     */
    public function testSubtract(Money $a, Money $b, Money $expectedResult)
    {
        $result = $a->subtract($b);
        $this->assertTrue($result->isEqual($expectedResult), 'Test A - B failed. ' . $a->asString() . ' - ' . $b->asString() . ' = ' . $result->asString() . ', expected: ' . $expectedResult->asString());
    }


    public function testConvertTo()
    {
        $a        = MutableMoney::create('2.00', 'EUR');
        $original = Money::create($a->getAmount(), $a->getCurrency());

        $a->convertTo('LTL');

        $b = Money::create($a->getAmount(), $a->getCurrency());

        $a->convertTo('EUR');

        $c = Money::create($a->getAmount(), $a->getCurrency());

        // must be:  a == c;

        $this->assertTrue($a->isEqualExact($original, 2), $a->asString() . ' -convert-> ' . $b->asString() . ' -convert-> ' . $c->asString() . ', must be a == c, but failed.');
    }

    public function provider_testNegate()
    {
        return array(

            array(MutableMoney::create('123.1234', 'LTL'), Money::create('-123.1234', 'LTL')),
            array(MutableMoney::create('20.00', 'LTL'), Money::create('-20.00', 'LTL')),
            array(MutableMoney::create('-455.00', 'LTL'), Money::create('455.00', 'LTL')),
        );
    }

    /**
     * @dataProvider provider_testNegate
     */
    public function testNegate(Money $a, Money $result)
    {
        $original = Money::create($a->getAmount(), $a->getCurrency());
        $b        = $a->negate();
        $this->assertTrue($b->isEqualExact($result), "negate() incorrectly, " . $original->asString() . ".negate() result: " . $b->asString() . ", should be: " . $result->asString());
    }


    public function testImmutable()
    {
        $a = MutableMoney::create('20.00', 'LTL');

        $immutable = $a->immutable();

        $this->assertTrue($a->isEqualExact($immutable), "immutable(): not exact equal results: " . $a->asString() . " => " . $immutable->asString());

        $adding = Money::create('1.00', 'LTL');

        $value = $immutable->add($adding);

        $this->assertTrue($immutable->isEqualExact($a), "immutable(): adding to immutable value must not change its state. (immutable)" . $immutable->asString() . " + " . $adding->asString() . " => " . $immutable->asString());
    }

    public function provider_testIsEqualExact()
    {
        return array(

            //  isEqualExact will compare two values for exact match of amount,currenct,precision
            array(MutableMoney::create('123.1234', 'LTL', 4), MutableMoney::create('123.1234', 'LTL', 4), true),

            array(MutableMoney::create('123.1234', 'LTL', 4), MutableMoney::create('123.1234', 'LTL', 3), false),
            array(MutableMoney::create('123.1234', 'LTL', 4), MutableMoney::create('123.1234', 'LVL', 4), false),
            array(MutableMoney::create('123.1234', 'LTL', 4), MutableMoney::create('123.0000', 'LVL', 4), false),
            array(MutableMoney::create('123.1234', '', 4), MutableMoney::create('123.0000', 'LVL', 4), false),

            array(MutableMoney::create('', '', 2), MutableMoney::create('', '', 2), true),
            array(MutableMoney::create('123.21', '', 2), MutableMoney::create('123.22', '', 2), false),

            array(MutableMoney::undefined(), MutableMoney::create('123.22', '', 2), false),
            array(MutableMoney::create('123.22', '', 2), MutableMoney::undefined(), false),
            array(MutableMoney::create('0.00', '', 2), MutableMoney::undefined(), false),
            array(MutableMoney::create('0.00', ''), MutableMoney::undefined(), false),
            array(MutableMoney::undefined(), MutableMoney::create('0.00', ''), false),
            array(MutableMoney::undefined(), MutableMoney::undefined(), true),
        );
    }

    /**
     * @dataProvider provider_testIsEqualExact
     */
    public function testIsEqualExact(MutableMoney $a, MutableMoney $b, $result)
    {
        $original = MutableMoney::from($a);
        $r        = $a->isEqualExact($b);
        $this->assertTrue($r == $result, "isEqualExact: " . $a->asString() . ' === ' . $b->asString() . ", result: " . $r . ", expected: " . $result);
        $this->assertTrue($original->isEqualExact($a), "isEqualExact: failed. mutable money object value was changed due function call! isEqualExact cannot change values itself.");
    }

    public function testToPrecision()
    {
        $a = MutableMoney::create('123.456', 'LTL', 4);

        $input = $a->immutable();

        $a->toPrecision(2);

        $expected = Money::create('123.45', 'LTL', 2);
        $this->assertTrue($expected->isEqualExact($a), $input->asString() . " applied toPrecision(2) and got result ".$a->asString().", expected: ".$expected->asString());
    }


    public function provider_testUndefinedBugWhenCreatingMultipleValuesWithDifferentCurrencies()
    {
        return array(
            array('LTL', -1),
            array('USD', -1),
            array('LTL', -1),
            array('USD', 4),
            array('USD', 3),
            array('EUR', 2),
            array('EUR', -1),
        );
    }

    /**
     * @dataProvider provider_testUndefinedBugWhenCreatingMultipleValuesWithDifferentCurrencies
     */
    public function testUndefined($currency, $precision)
    {
        $money = Money::undefined($currency, $precision);

        $this->assertEquals($currency, $money->getCurrency(), "currency not match");
        $this->assertEquals($precision >= 0 ? $precision : 4, $money->getPrecision(), "precision not match");
    }


    public function testSetDefaultCurrency()
    {
        $defautCurrency = 'EUR';

        Money::setDefaultCurrency($defautCurrency);

        $undefined = Money::undefined();
        $money = Money::create('10.0000');

        $this->assertEquals($defautCurrency, $undefined->getCurrency(), "undefined() created not correct currency value");
        $this->assertEquals($defautCurrency, $money->getCurrency(), "money() created not correct currency value = ".$money->asString());
    }


}