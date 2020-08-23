<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/**
 * phpcs:disable PSR1.Files.SideEffects
 * phpcs:disable Squiz.Functions.GlobalFunction
 * @var string $testFrameworkDir - Must be defined in parent script.
 * @var \Magento\TestFramework\Bootstrap\Settings $settings - Must be defined in parent script.
 */

/** Copy test modules to app/code/Magento to make them visible for Magento instance */
$vendorPath = realpath($testFrameworkDir . '/../../../../vendor');

// Register the modules under '_files/'
$pathPattern = $vendorPath . '/*/*/TestModule*/registration.php';
// phpcs:ignore Magento2.Functions.DiscouragedFunction
$files = glob($pathPattern, GLOB_NOSORT);
if ($files === false) {
    throw new \RuntimeException('glob() returned error while searching in \'' . $pathPattern . '\'');
}
foreach ($files as $file) {
    // phpcs:ignore Magento2.Security.IncludeFile
    include $file;
}
