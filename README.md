Money library
====

This library adds a new type **Money** which abstracts a real money object. **Money** has an **Value** and **Currency**.
Without **Money** object you must store these values in separate variables like $amount, $currency.
Its really hard to produce nice API methods without money object.

 Example:
 ```php
 function getPrice()
 ```

 Should it return value or object ?

 ```php
 function getPrice()
 {
    return array($price, $value);
 }
 ```

 or better?

 ```php
 function getPrice()
 {
    return Money::create($price, $value);
 }
 ```

now you can apply some mathematics to money object:

```php
getPrice()->add(Money::create('10.00', 'LTL'))
```

or add and convert to some currency in same time

```php
$priceInUsd = getPrice()->add(Money::create('10.00', 'LTL'))->convertTo('USD')
```

or with mutiple currencies ? wha?

```php
$sum = Money::create('10.00', 'LTL')->add(Money::create('5.00', 'USD'))

echo $sum->asString(); // 24.0000 LTL
```

comparing moneys with different currencies ?

```php
Money::create('10.00', 'LTL')->compare(Money::create('100.00', 'RUB'));
```

To enable currency conversion you must implement ```MoneyConverter``` interface and register it to ```Money``` using: ```Money::setDefaultMoneyConverter()```

Implementation example of ```MoneyConverter``` can be found at ```/tests/MockedMoneyConverter.php```


Money
-----

Class Money is a Value Object which means that all operations will create new instance of Money
and all instances are immutable

This class is used in all system to describe money value. It is {amount, currency} tuple.

Methods ```add()```, ```subtract()```, ```compare()```, ```isEqual()``` will implicitly convert specified argument into appropriate currency.

For currency conversion you can implement ```MoneyConverter``` interface and register your money converter
using ```Money::setDefaultMoneyConverter()```

Default decimal precision is 4 digits

You can compare only two objects with exact the same precisions.
All operations will use highest precisions of available operands.

use ```Money::create($amount = '', $currency = '', $precision = -1)``` static factory to create Money instances.
**don`t use constructors directly!**

amount must be specified in string type  and decimal separator must be a dot (e.g.  "123.45")
You cannot specify  ```Money::create(123.45)``` or ```Money::create("123,45")```. only ```Money::create("123.45")``` <- string, and dot separated number

Money amount **must be** string type only. Number **must be** dot separated. e.g. "123.45" not "123,45" (bc limitation)
You **cannot** use float or integer types. Its defensive restriction to ensure that you wont loose precision accidentaly.
In case if you want to specify integers or floats or string you can use  ```Money::createUnsafe()`` method. but its **not recommended**.

use ```Money::undefined($currency = '', $precision = -1)``` to create value with unknown money amount.
its syntactic sugar for ```Money::create(false)```, ```Money::create(null)``` or ```Money::create('')```

````
e.g. Money::create('') === Money::undefined();
   Money::create('0.00') !== Money::undefined();
````

you can use methods ```isDefined()``` ```isUndefined()``` to check whether Money object has an defined amount.

all undefined values in any money operations are allowed and will be casted to zero


Note: please remind that currency conversions will accumulate computation errors, so operations like:

```
a = Money::create('10.00', 'LTL')
b = a->convertTo('USD')->convertTo('LVL')->convertTo('LTL');
```

will produce value which was not equal to previous value due conversion errors. absolute error is +-0.0003

you can compare these values with ```isEqualExact()``` with lower precision


Mutable Money
--------------


Class MutableMoney

This class is the same as Money class except that this class is not Value Object so it doesn't create
new instances but operations are applied to the same instances.
so ```add()``` and ```subtract()``` methods will affect instance values.

Uses this class for intensive computations of lot of values

Default System Currency
------------------------

As of v1.1 you can specify default currency which will be passed to all new **Money** object as a default currency
if not specified. See ```Money::setDefaultCurrency($defaultCurrency)```

Database
---------

To persist this object in database please use  **DECIMAL(12, 4)** for amount, and **CHAR(3) BINARY** for currency!


Installation and usage
---------------------

add these lines to your ```composer.json```

`````
    "require":
    {
        "happy-types/money-type" : "~1.1"
    }
}
`````

run: ```composer update```

insert composer auto loader in your project:
``` require_once(dirname(__FILE__).'/vendor/autoload.php'); ```

example is for index.php which is in project root folder.



