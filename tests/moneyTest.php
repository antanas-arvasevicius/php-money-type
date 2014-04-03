<?php
require_once(dirname(__FILE__).DIRECTORY_SEPARATOR.'MockedMoneyConverter.php');

class MoneyTest extends PHPUnit_Framework_TestCase
{

    public function setUp()
    {
        Money::setDefaultMoneyConverter(new MockedMoneyConverter());
        Money::setDefaultCurrency(null);
    }

    public function provider_testCreate()
    {
        return array(
            array('0.10', 'LTL', 4, '0.1000', 'LTL'),
            array('0.15', 'LTL', 2, '0.1500', 'LTL'),
            array('999999.53', 'USD', 3, '999999.530', 'USD'),
            array('999999.536', 'USD', 2, '999999.53', 'USD'),
            array('-1120.4266', 'USD', 2, '-1120.42', 'USD'),
            array('-1120.4266', 'USD', 1, '-1120.4', 'USD'),
            array('-1120.4266', 'USD', 0, '-1120', 'USD'),
            array('872', 'USD', 0, '872', 'USD'),
            array('99999999.99', 'USD', 2, '99999999.99', 'USD'),
        );
    }

    /**
     * @dataProvider provider_testCreate
     */
    public function testCreate($amount, $currency, $precision, $resultAmount, $resultCurrency)
    {
        $this->assertMoney(Money::create($amount, $resultCurrency, $precision), $resultAmount, $resultCurrency);
    }

    public function provider_testCreateUnsafe()
    {
        return array(
            array(Money::createUnsafe('10.00', 'LTL'), Money::create('10.00', 'LTL')),
            array(Money::createUnsafe(doubleval('10.00'), 'LTL'), Money::create('10.00', 'LTL')),
            array(Money::createUnsafe(doubleval('10.6523'), 'LTL'), Money::create('10.6523', 'LTL')),
            array(Money::createUnsafe(123.50, 'LTL',2), Money::create('123.50', 'LTL', 2)),
            array(Money::createUnsafe(599, 'LTL',4), Money::create('599.0000', 'LTL', 4)),
            array(Money::createUnsafe('', 'LTL'), Money::create('', 'LTL', 4)),
            array(Money::createUnsafe(0, 'LTL'), Money::create('0', 'LTL')),
            array(Money::createUnsafe(null, 'LTL'), Money::undefined('LTL')),
        );
    }

    /**
     * @dataProvider provider_testCreateUnsafe
     */
    public function testCreateUnsafe(Money $money, Money $result)
    {
        $this->assertTrue($money->isEqualExact($result), "createUnsafe: incorrectly created:  created =".$money->asString(), ", should be: ".$result->asString());
    }

    public function provider_testIsDefined()
    {
        return array(

            array(Money::undefined(), false),
            array(Money::undefined('USD', 4), false),
            array(Money::undefined('LTL'), false),

            array(Money::create('', 'LTL'), false),
            array(Money::create(false), false),
            array(Money::create(null), false),


            array(Money::create('0.00', 'LTL'), true),
            array(Money::create('0.00'), true),
            array(Money::create('5.12'), true),
        );
    }

    /**
     * @dataProvider provider_testIsDefined
     */
    public function testIsDefined(Money $a, $expectedResult)
    {
        $result       = $a->isDefined();
        $resultNegate = $a->isUndefined();

        $this->assertFalse($result === $resultNegate, "Test A.isDefined() A.isUndefined() failed, must return opposite values.");
        $this->assertTrue($result == $expectedResult, 'Test A.isDefined() failed. got: ' . ($result ? 'true' : 'false') . ', expected: ' . ($expectedResult ? 'true' : 'false'));
    }


    /**
     * You cannot create money object from integer or float or double, only from string!
     * @expectedException RuntimeException
     */
    public function testCreateFromNotString()
    {
        Money::create(10.50, 'LTL');
    }

    public function provider_testAdd()
    {
        return array(


            array(Money::create('0.00', 'LTL'), Money::create('0.00', 'LTL'), Money::create('0.00', 'LTL')),

            // if ZERO then implicitly infering currency from second parameter. first money currency is unknown and must be ZERO, overwise exception is thrown
            array(Money::create('0.00', ''), Money::create('10.00', 'LTL'), Money::create('10.00', 'LTL')),

            array(Money::create('15.33', 'LTL'), Money::create('10.33', 'LTL'), Money::create('25.66', 'LTL')),
            array(Money::create('-10.50', 'USD'), Money::create('10.50', 'USD'), Money::create('0', 'USD')),
            array(Money::create('-10.50', 'USD'), Money::create('11.50', 'USD'), Money::create('1', 'USD')),
            array(Money::create('-10.50', 'USD'), Money::create('11.50', 'USD'), Money::create('1.0000', 'USD')),

            // if precisions wont match we use the bigger one
            array(Money::create('-10.50', 'USD', 2), Money::create('12.55', 'USD', 4), Money::create('2.0500', 'USD', 4)),
            array(Money::create('-10.50', 'USD', 2), Money::create('12.55987', 'USD', 4), Money::create('2.0598', 'USD', 4)),

        );
    }

    /**
     * @dataProvider provider_testAdd
     */
    public function testAdd(Money $a, Money $b, Money $expectedResult)
    {
        $result = $a->add($b);
        $this->assertTrue($result->isEqual($expectedResult), 'Test A + B failed. ' . $a->asString() . ' + ' . $b->asString() . ' = ' . $result->asString() . ', expected: ' . $expectedResult->asString());
    }

    public function provider_testAddWithUnknownCurrency()
    {
        return array(
            array(Money::create('4.00', ''), Money::create('10.00', 'LTL')),
            array(Money::create('4.00', 'LTL'), Money::create('10.00', '')),
            array(Money::create('-4.00', ''), Money::create('10.00', '')),
        );
    }

    /**
     * Cannot add values with unknown currencies
     * @expectedException RuntimeException
     * @dataProvider provider_testAddWithUnknownCurrency
     */
    public function testAddWithUnknownCurrency(Money $a, Money $b)
    {
        $a->add($b);
    }

    public function provider_testSubtract()
    {
        return array(

            array(Money::create('0.00', ''), Money::create('0.00', ''), Money::create('0.00', '')),
            array(Money::create('0.00', ''), Money::create('0.00', ''), Money::create('0.00', 'LTL')),

            // infering currency from second parameter. first money currency is unknown.
            array(Money::create('0.00', ''), Money::create('10.00', 'LTL'), Money::create('-10.00', 'LTL')),

            // regular tests
            array(Money::create('15.33', 'LTL'), Money::create('10.33', 'LTL'), Money::create('5.00', 'LTL')),
            array(Money::create('-10.50', 'USD'), Money::create('10.50', 'USD'), Money::create('-21.00', 'USD')),
            array(Money::create('-10.50', 'USD'), Money::create('11.50', 'USD'), Money::create('-22.00', 'USD')),

            // if precisions wont match we use the bigger one
            array(Money::create('-10.50', 'USD', 2), Money::create('12.55', 'USD', 4), Money::create('-23.0500', 'USD', 4)),
            array(Money::create('-10.50', 'USD', 2), Money::create('12.55987', 'USD', 4), Money::create('-23.0598', 'USD', 4)),
            array(Money::create('65.50', 'USD', 2), Money::create('60.5074', 'USD', 4), Money::create('4.9926', 'USD', 4)),
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


    /**
     * If currencies mismatch in  a + b  then we implicitly convert  b into's a currency. returned value is in a`s currency
     */
    public function testAddCurrencyMismatch()
    {

        $a = Money::create('0.00', 'LTL');
        $b = Money::create('1.00', 'EUR');

        $result = $a->add($b);

        $expectedResult = Money::create('1.00', 'EUR')->convertTo('LTL');

        $this->assertTrue($result->isEqual($expectedResult), $a->asString() . ' + ' . $b->asString() . ' = ' . $result->asString() . ', expected: ' . $expectedResult->asString());
    }

    public function testConvertTo()
    {
        $a = Money::create('2.00', 'EUR');
        $b = $a->convertTo('LTL');
        $c = $b->convertTo('EUR');

        // must be:  a == c;

        $this->assertTrue($a->isEqualExact($c, 2), $a->asString() . ' -convert-> ' . $b->asString() . ' -convert-> ' . $c->asString() . ', must be a == c, but failed.');
    }

    public function testConvertTo2()
    {
        $a = Money::create('3.50', 'EUR');
        $b = $a->convertTo('LTL');
        $c = $b->convertTo('LVL');
        $d = $c->convertTo('EUR');

        // must be: a == d;

        $this->assertTrue($a->isEqualExact($d, 2), $a->asString() . ' -> ' . $b->asString() . ' -> ' . $c->asString() . ' -> ' . $d->asString() . ', must be a == d, but failed.');
    }

    public function testConvertTo3()
    {
        $a = Money::create('10.00', 'LTL', 4);
        $b = $a->convertTo('LVL');
        $c = $b->convertTo('LTL');

        //convert to precision 2, because we are getting conversion errors in +-0.0002 range
        $this->assertTrue($a->isEqualExact($c, 2), $a->asString() . ' -> ' . $b->asString() . ' -> ' . $c->asString() . ', must be a == c, but failed.');
    }

    public function provider_testComparision()
    {
        return array(

            // zero comparision do not include currencies
            array(Money::create('0.00', ''), Money::create('0.00', ''), 0),
            array(Money::create('0.00', ''), Money::create('0.00', 'LTL'), 0),
            array(Money::create('0.00', ''), Money::create('0.00', 'XXX'), 0),

            // can compare with zero value, then currency is ignored and not included in comparision

            array(Money::create('0.00', 'XXX'), Money::create('1.00', 'LTL'), -1),
            array(Money::create('0.00', 'YYY'), Money::create('1.00', 'LTL'), -1),
            array(Money::create('0.00', 'ZZZ'), Money::create('1.00', 'LTL'), -1),
            array(Money::create('0.00', 'EUR'), Money::create('1.00', 'LTL'), -1),
            array(Money::create('0.00', ''), Money::create('1.00', 'LTL'), -1),

            // swapped arguments also ignore currencies if comparing with zero
            array(Money::create('1.00', ''), Money::create('0.00', ''), 1),
            array(Money::create('2.00', 'LTL'), Money::create('0.00', ''), 1),
            array(Money::create('-2.00', 'LTL'), Money::create('0.00', ''), -1),
            array(Money::create('-10.00', 'LTL'), Money::create('0.00', ''), -1),
            array(Money::create('-10.00', 'LTL'), Money::create('0.00', 'XXX'), -1),
            array(Money::create('-10.00', 'LTL'), Money::create('0.00', 'YYY'), -1),

            // comparing same currencies
            array(Money::create('-10.00', 'LTL'), Money::create('-10.00', 'LTL'), 0),
            array(Money::create('-10.25', 'LTL'), Money::create('-10.25', 'LTL'), 0),
            array(Money::create('10.25', 'LTL'), Money::create('1.00', 'LTL'), 1),
            array(Money::create('10.25', 'LTL'), Money::create('10.24', 'LTL'), 1),

            // comparing different currencies, we always convert second argument into first`s currency
            array(Money::create('1.02', 'LTL'), Money::create('1.02', 'LVL'), -1), // convert LVL into LTL then comparing
            array(Money::create('1.02', 'LVL'), Money::create('1.02', 'LTL'), 1), // convert LTL into LVL then comparing

        );
    }

    /**
     * @dataProvider provider_testComparision
     */
    public function testComparison(Money $a, Money $b, $result)
    {
        $r = $a->compare($b);
        $this->assertTrue($r == $result, "comparing: " . $a->asString() . " <=> " . $b->asString() . ", result: " . $r . ", expected: " . $result);
    }

    public function provider_testComparisionEqualityWithConversion()
    {
        return array(
            // must be equal. we convert back LTL to LVL then comparing.
            array(Money::create('0.99', 'LVL')),
            array(Money::create('-0.99', 'LVL')),
        );
    }

    /**
     * @dataProvider provider_testComparisionEqualityWithConversion
     */
    public function testComparisionEqualityWithConversion(Money $a)
    {
        $b = $a->convertTo('LTL');
        $r = $a->compare($b);
        $this->assertTrue($r == 0, "comparing: " . $a->asString() . " <=> " . $b->asString() . ", result: " . $r . ", expected: 0");
    }

    /**
     * @dataProvider provider_testComparision
     */
    public function testComparisonUsingSyntacticSugarMethods(Money $a, Money $b, $result)
    {
        $method = ($result == 0) ? 'eq' : ($result < 0 ? 'lt' : 'gt');

        $r = $a->{$method}($b);
        $this->assertTrue($r == true, "comparing using method `{$method}`: " . $a->asString() . " {$method} " . $b->asString() . ", result: " . ($r ? 'true' : 'false') . ", expected: true");

        if ($result == 0) {

            $r      = $a->le($b);
            $method = 'le';
            $this->assertTrue($r == true, "comparing using method `{$method}`: " . $a->asString() . " {$method} " . $b->asString() . ", result: " . ($r ? 'true' : 'false') . ", expected: true");

            $r      = $a->ge($b);
            $method = 'ge';
            $this->assertTrue($r == true, "comparing using method `{$method}`: " . $a->asString() . " {$method} " . $b->asString() . ", result: " . ($r ? 'true' : 'false') . ", expected: true");
        }
    }


    public function provider_testComparisonOfIllegalUsage()
    {
        return array(
            // Cannot compare non zero values of unknown currencies. Exception is thrown
            array(Money::create('0.02', ''), Money::create('0.05', 'LTL')),

            // Cannot compare non zero values of unknown currencies. Exception is thrown
            array(Money::create('0.02', ''), Money::create('0.05', '')),

            // Cannot compare non zero values of unknown currencies. Exception is thrown
            array(Money::create('0.02', 'LTL'), Money::create('0.05', '')),

            // Cannot compare two values with different precisions. Exception is thrown
            array(Money::create('123.02', 'LTL', 4), Money::create('15345.05', 'LTL', 3)),
        );
    }

    /**
     * @expectedException RuntimeException
     * @dataProvider provider_testComparisonOfIllegalUsage
     */
    public function testComparisonOfIllegalUsage(Money $a, Money $b)
    {
        $r = $a->compare($b);
    }


    public function provider_testCompareToZero()
    {
        return array(
            // comparing with zero we ignore currencies in all cases

            array(Money::create('0.00', ''), 0),
            array(Money::create('0.00', 'XXX'), 0),
            array(Money::create('0.00', 'YYY'), 0),

            array(Money::create('10.00', ''), 1),
            array(Money::create('10.00', 'USD'), 1),
            array(Money::create('10.00', 'XXX'), 1),

            array(Money::create('-10.00', 'YYY'), -1),
            array(Money::create('-0.01', 'YYY'), -1),

            array(Money::create('-0.00', 'YYY'), 0),
            array(Money::create('+0.00', 'YYY'), 0),

            // different precisions
            array(Money::create('+0.0', 'YYY', 1), 0),
            array(Money::create('+1.00', '', 2), 1),
            array(Money::create('1.00', '', 2), 1),
            array(Money::create('0.0001', '', 4), 1),
            array(Money::create('-0.0001', '', 4), -1),
        );
    }

    /**
     * @dataProvider provider_testCompareToZero
     */
    public function testCompareToZero(Money $a, $result)
    {
        $r = $a->compareToZero();
        $this->assertTrue($r == $result, "comparing to zero: " . $a->asString() . ", result: " . $r . ", expected: " . $result);
    }

    public function provider_testIsZero()
    {
        // completely ignoring currencies in isZero() testing.
        return array(
            array(Money::create('', ''), true),
            array(Money::create('1', ''), false),
            array(Money::create('1', 'YYY'), false),
            array(Money::create('123.9823', 'YYY'), false),
            array(Money::create('-2349.34', 'YYY'), false),

            array(Money::create('-0.0001'), false),
            array(Money::create('+0.0001'), false),
            array(Money::create('+0.0000'), true),
            array(Money::create('-0.0000'), true),

            // different precisions
            array(Money::create('0', '', 0), true),
            array(Money::create('1', '', 0), false),
            array(Money::create('-1', '', 0), false),
            array(Money::create('0.000001', '', 6), false),
            array(Money::create('-0.000001', '', 6), false),
            array(Money::create('-0.000000', '', 6), true),

            array(Money::create('0.00', 'LTL'), true),
            array(Money::create('-123098.23', 'LTL'), false),
        );
    }

    /**
     * @dataProvider provider_testIsZero
     */
    public function testIsZero(Money $a, $result)
    {
        $r = $a->isZero();
        $this->assertTrue($r == $result, "comparing to zero: " . $a->asString() . ", result: " . $r . ", expected: " . $result);
    }

    public function provider_testIsEqual()
    {
        return array(

            // we implicitly convert second parameter to first`s currency. so this would work too.
            // DOESN'T WORK DUE SMALL CALCULATION ERRORS +-0.0003
            //array(Money::create('10.00', 'LTL', 4), Money::create('10.00', 'LTL', 4), true),


            // can comparing without currencies only if ZERO amount. overwise exception is thrown. @see Money::compare()
            array(Money::create('0.00', ''), Money::create('0.00', ''), true),
            array(Money::create('0.00', ''), Money::create('10.00', 'XXX'), false),
            array(Money::create('10.00', 'XXX'), Money::create('0.00', 'XXX'), false),


            array(Money::create('25.43', 'LTL', 2), Money::create('25.43', 'LTL', 2), true),
            array(Money::create('-25.43', 'LTL', 2), Money::create('-25.43', 'LTL', 2), true),

            array(Money::create('-25.43', 'LTL', 2), Money::create('-20.43', 'LTL', 2), false),
        );
    }

    /**
     * @param Money $a
     * @param Money $b
     * @param $result
     * @dataProvider provider_testIsEqual
     */
    public function testIsEqual(Money $a, Money $b, $result)
    {
        $r = $a->isEqual($b);
        $this->assertTrue($r == $result, "isEqual: " . $a->asString() . ' == ' . $b->asString() . ", result: " . ($r ? 'true' : 'false') . ", expected: " . ($result ? 'true' : 'false'));
    }

    public function provider_testIsEqualExact()
    {
        return array(

            //  isEqualExact will compare two values for exact match of amount,currenct,precision
            array(Money::create('123.1234', 'LTL', 4), Money::create('123.1234', 'LTL', 4), true),

            array(Money::create('123.1234', 'LTL', 4), Money::create('123.1234', 'LTL', 3), false),
            array(Money::create('123.1234', 'LTL', 4), Money::create('123.1234', 'LVL', 4), false),
            array(Money::create('123.1234', 'LTL', 4), Money::create('123.0000', 'LVL', 4), false),
            array(Money::create('123.1234', '', 4), Money::create('123.0000', 'LVL', 4), false),

            array(Money::create('', '', 2), Money::create('', '', 2), true),
            array(Money::create('123.21', '', 2), Money::create('123.22', '', 2), false),

            array(Money::undefined(), Money::create('123.22', '', 2), false),
            array(Money::create('123.22', '', 2), Money::undefined(), false),
            array(Money::create('0.00', '', 2), Money::undefined(), false),
            array(Money::create('0.00', ''), Money::undefined(), false),
            array(Money::undefined(), Money::create('0.00', ''), false),
            array(Money::undefined(), Money::undefined(), true),
        );
    }

    /**
     * @dataProvider provider_testIsEqualExact
     */
    public function testIsEqualExact(Money $a, Money $b, $result)
    {
        $r = $a->isEqualExact($b);
        $this->assertTrue($r == $result, "isEqualExact: " . $a->asString() . ' === ' . $b->asString() . ", result: " . $r . ", expected: " . $result);
    }

    public function testGetters()
    {
        $a = Money::create('5.43210', 'LTL', 5);
        $this->assertTrue($a->getAmount() === '5.43210', "getter test failed: incorrect getAmount();");
        $this->assertTrue($a->getCurrency() === 'LTL', "getter test failed: incorrect getCurrency();");
        $this->assertTrue($a->getPrecision() === 5, "getter test failed: incorrect getPrecision();");
        $this->assertTrue($a->asString() === '5.43210 LTL', "getter test failed: incorrect asString(); returned: " . $a->asString() . ", should: 5.43210 LTL");
    }


    public function provider_testNegate()
    {
        return array(

            array(Money::create('123.1234', 'LTL'), Money::create('-123.1234', 'LTL')),
            array(Money::create('20.00', 'LTL'), Money::create('-20.00', 'LTL')),
            array(Money::create('-455.00', 'LTL'), Money::create('455.00', 'LTL')),
        );
    }

    /**
     * @dataProvider provider_testNegate
     */
    public function testNegate(Money $a, Money $result)
    {
        $b = $a->negate();
        $this->assertTrue($b->isEqualExact($result), "negate() incorrectly, " . $a->asString() . ".negate() result: " . $b->asString() . ", should be: " . $result->asString());
    }

    public function testToPrecision()
    {
        $input = Money::create('123.456', 'LTL', 4);

        $a = $input->toPrecision(2);

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


    public function assertMoney(Money $money, $amount, $currency, $precision = 0)
    {
        $this->assertTrue($money->getAmount() == $amount, "amount mismatch. must be {$amount}, got: " . $money->getAmount());
        $this->assertTrue($money->getCurrency() == $currency, "currency mismatch. must be {$currency}, got: " . $money->getCurrency());

        if ($precision)
            $this->assertTrue($money->getPrecision() == $precision, "precision mismatch. must be {$precision}, got: " . $money->getPrecision());
    }

    public function provider_testMultiplyByInteger() {
        return array(
            array(Money::create('0.00', ''), 5, Money::create('0.00', '')),
            array(Money::create('15.63', 'LTL'), 5, Money::create('78.15', 'LTL')),
            array(Money::create('-15.63', 'USD'), 5, Money::create('-78.15', 'USD')),
            array(Money::create('15.63', 'USD'), -5, Money::create('-78.15', 'USD'))
        );
    }

    /**
     * @dataProvider provider_testMultiplyByInteger
     */
    public function testMultiplyByInteger(Money $money, $number, Money $expectedResult) {
        $result = $money->multiplyByInteger($number);
        $this->assertTrue($result->isEqual($expectedResult), 'Test money * number failed. (' . $money->asString() . ') * (' . $result->asString() . '), expected: ' . $expectedResult->asString());
    }
}