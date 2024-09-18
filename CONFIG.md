## Integration tests:

You can run integration tests from PhpStorm by running the
`dev/tests/quick-integration/phpunit.xml.dist` file. It will run all Reach
Digital integration tests. When first running integration tests, make sure to
perform the following setup steps:

### Creating a integration tests specific database:
Create the `magento_integration_tests` database. Be careful that you do this
   using the `root` user, since the `magento` user does not have permissions to
   do this.

```mysql
create schema magento_integration_tests;
GRANT ALL PRIVILEGES ON magento_integration_tests.* TO 'magento';
```

### Configuring database settings:
Copy the contents of `dev/tests/integration/etc/install-config-mysql.php.dist` to `dev/tests/integration/etc/install-config-mysql.php`
Configure the Magento instance with the docker-dev credentials and use the `magento_integration_tests` database name.

Any modules that are disabled in the `app/etc/config.php` will not automatically be disabled during integration tests.

To manually specify a list of module to disable:
```php
return [
...
'admin-lastname' => \Magento\TestFramework\Bootstrap::ADMIN_LASTNAME,
'disable-modules'  => join(',', [
        'Magento_Inventory',
        'Magento_InventoryApi',
        'Magento_InventoryCatalogApi',
        'Magento_InventoryConfigurationApi',
        'Magento_InventorySales',
        'Magento_InventoryMultiDimensionalIndexerApi',
        'Magento_InventoryReservationsApi',
        'Magento_InventoryIndexer',
        'Magento_InventorySalesApi',
        'Magento_InventorySourceDeductionApi',
        'Magento_InventorySourceSelectionApi'
    ])
]
```

Or copy the enables/disabled status from app/etc/config.php

```php
$disableModules = [];
foreach ($config['modules'] as $moduleName => $moduleStatus) {
    if ($moduleStatus === 1) {
        continue;
    }
    $disableModules[] = $moduleName;
}

return [
    ...
    'admin-lastname' => \Magento\TestFramework\Bootstrap::ADMIN_LASTNAME,
    'disable-modules' => implode(',', $disableModules),
];

```

Remove `amqp` settings (unless they are actually used)

### Configuring quick-integration tests:
Copy the contents of `dev/tests/quick-integration/phpunit.xml.dist` to `dev/tests/quick-integration/phpunit.xml` and make the following changes:
 - Enable setting `TESTS_PARALLEL_RUN`:
   ```xml
   <const name="TESTS_PARALLEL_RUN" value="1"/>
   ```
 - Set php's memory limit to a number that makes sense for your tests:
   ```xml
   <ini name="memory_limit" value="2048M"/>
   ```
 - Add the default integration as paths to include for the quick-integration tests 
   ```xml
   <includePath>../integration</includePath>
   <includePath>../integration/testsuite</includePath>
   ```

Install patches:

Disabling the ```$this->createCliCommands();``` This prevents a lot of issues when stating integration tests:

```php
    /**
     * Retrieve object manager.
     *
     * @return ObjectManagerInterface
     * @throws \Magento\Setup\Exception
     */
    public function get()
    {
        if (null === $this->objectManager) {
            $initParams = $this->serviceLocator->get(InitParamListener::BOOTSTRAP_PARAM);
            $factory = $this->getObjectManagerFactory($initParams);
            $this->objectManager = $factory->create($initParams);
//            Disabled early CLI command initialisation, to prevent DB access from bin/magento setup:install, which breaks
//            integration tests. This is because some classes being injected in CLI command constructors trigger DB access.
//            if (PHP_SAPI == 'cli') {
//                $this->createCliCommands();
//            }
        }
        return $this->objectManager;
    }
```

Not critical for integration tests, but helps find issues easier:
 - [magento-framework-object-manager-exception-handling.patch](https://github.com/ho-nl/project-paracord.eu/blob/rc/composer-patches/magento-framework-object-manager-exception-handling.patch)

### Common errors:

1. Next Magento\Framework\Exception\LocalizedException: Command returned non-zero exit code:
   `mysqldump --defaults-file='/dev/tests/integration/tmp/sandbox-0-d858badd2e2481d51291256f710b10a49fec2d820211028beb380ce0a7c72f57/defaults_extra.cnf' --host='127.0.0.1' --port='3306' --no-tablespaces  'magento_integration_tests' > '/dev/tests/integration/tmp/sandbox-0-d858badd2e2481d51291256f710b10a49fec2d820211028beb380ce0a7c72f57/setup_dump_magento_integration_tests.sql' 2>&1` in /vendor/magento/framework/Shell.php:66

This issue can be sidestepped with downgrading the mysql-client: [temporary solution](https://github.com/Homebrew/homebrew-core/issues/180498#issuecomment-2283141319)
A better solution would be to have this work with the most recent mysql-client, suggestions are welcome.

2. When running tests the following errors could occur:
```bash
call to undefined method getAnnotations()
```
This is a compatibility issue with PHPUnit version 9.5, update reach-digital/magento2-test-framework to version 1.5.0 or later.

```bash 
Invalid argument to foreach in vendor/magento/module-eav/Model/Config.php
```
A string value 'null' is saved in the cache. Empty the cache and run again.

```bash
Fatal error: Uncaught Magento\Framework\Exception\FileSystemException: The "app/code/Magento/TestModuleFakePaymentMethod/etc/config.xml" file doesn't exist. in vendor/magento/framework/Filesystem/File/Read.php:76
```
Enable TESTS_PARALLEL_RUN in the phpunit.xml file.

```bash
Error: Call to undefined method ReflectionMethod::getAttributes()
```
This error is caused by Magento 2.4.5 integration tests not being compatible with php 7.4. The easiest way to solve this is to run with php 8.1
See: [Magento/TestFramework/Fixture/Parser/DbIsolation.php](https://github.com/magento/magento2/blob/2.4.5/dev/tests/integration/framework/Magento/TestFramework/Fixture/Parser/DbIsolation.php#L53)

### Tips: 
Data fixtures as php attributes are available since php8. Traditionally every Magento module makes data fixtures available 
via `/dev/test/integration/testsuite/Magento/<module>/_files/<data-fixture>.php`. These can be used when writing your own 
data-fixture, for instance:

```php
<?php
declare(strict_types=1);

/** @var \Magento\Quote\Model\Quote $quote */
/** @var \Magento\Quote\Model\QuoteIdMask $quoteIdMask */

require 'Magento/Checkout/_files/quote_with_check_payment.php';

$quote->getPayment()->setMethod(\Magento\OfflinePayments\Model\Banktransfer::PAYMENT_METHOD_BANKTRANSFER_CODE);
$quote->save();

$cartManagement = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->get(
    \Magento\Quote\Api\GuestCartManagementInterface::class
);
$orderId = $cartManagement->placeOrder($quoteIdMask->getMaskedId());

$invoiceOrder = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->get(
    \Magento\Sales\Api\InvoiceOrderInterface::class
);
$invoiceOrder->execute($orderId);
```

However, a cleaner way of adding data fixtures with attributes, for example:

```php
<?php
declare(strict_types=1);

use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Checkout\Test\Fixture\PlaceOrder as PlaceOrderFixture;
use Magento\Checkout\Test\Fixture\SetBillingAddress as SetBillingAddressFixture;
use Magento\Checkout\Test\Fixture\SetDeliveryMethod as SetDeliveryMethodFixture;
use Magento\Checkout\Test\Fixture\SetGuestEmail as SetGuestEmailFixture;
use Magento\Checkout\Test\Fixture\SetPaymentMethod as SetPaymentMethodFixture;
use Magento\Checkout\Test\Fixture\SetShippingAddress as SetShippingAddressFixture;
use Magento\Quote\Test\Fixture\AddProductToCart as AddProductToCartFixture;
use Magento\Quote\Test\Fixture\GuestCart as GuestCartFixture;
use Magento\TestFramework\Fixture\DataFixture;

class MyTest extends \PHPUnit\Framework\TestCase
{
    #[
        DataFixture(ProductFixture::class, as: 'product'),
        DataFixture(GuestCartFixture::class, as: 'cart'),
        DataFixture(
            AddProductToCartFixture::class,
            ['cart_id' => '$cart.id$', 'product_id' => '$product.id$', 'qty' => 2]
        ),
        DataFixture(SetBillingAddressFixture::class, ['cart_id' => '$cart.id$']),
        DataFixture(SetShippingAddressFixture::class, ['cart_id' => '$cart.id$']),
        DataFixture(SetGuestEmailFixture::class, ['cart_id' => '$cart.id$']),
        DataFixture(SetDeliveryMethodFixture::class, ['cart_id' => '$cart.id$']),
        DataFixture(SetPaymentMethodFixture::class, ['cart_id' => '$cart.id$']),
        DataFixture(PlaceOrderFixture::class, ['cart_id' => '$cart.id$'], 'order'),
    ]
    public function testMyTest()
    {
        // write a test using the prepared cart via PHP attributes  
    }
}
```
