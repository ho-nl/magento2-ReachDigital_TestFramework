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
This prevents a lot of issues when stating integration tests:
 - [patches/fix-db-access-breaking-integration-test.patch](https://github.com/ho-nl/project-paracord.eu/commit/b12d74f1323a11f392a9f89d69806d262e29cae7)

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
Foutmelding: Call to undefined method ReflectionMethod::getAttributes()
```
This error is caused by Magento 2.4.5 integration tests not being compatible with php 7.4. The easiest way to solve this is to run with php 8.1
See: [Magento/TestFramework/Fixture/Parser/DbIsolation.php](https://github.com/magento/magento2/blob/2.4.5/dev/tests/integration/framework/Magento/TestFramework/Fixture/Parser/DbIsolation.php#L53)

### Tips: 
Data fixtures as php attributes are available since php8. Traditionally every Magento module makes data fixtures available 
via `/dev/test/integration/testsuite/Magento/<module>/_files/<data-fixture>.php`. These can be used when writing your own 
data-fixture, demonstrated here: [paid_banktransfer_order.php](https://github.com/ho-nl/project-vaessen-creative.com/blob/feature/integration-tests/app/code/ReachDigital/OrderProcessor/Test/Integration/_files/paid_banktransfer_order.php#L11)

However, a cleaner way of adding data fixtures with attributes is demonstrated here:
[PickerJobTest.php](https://github.com/ho-nl/project-paracord.eu/blob/0bf600d67a2375bcd1d703e9031e50ceba1286b3/app/code/ReachDigital/PickerTracker/Test/Integration/PickerJobTest.php#L131-L142)

