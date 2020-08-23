<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\Framework\Component\ComponentRegistrar;
use Magento\TestFramework\Bootstrap\Settings;

/**
 * phpcs:disable PSR1.Files.SideEffects
 * phpcs:disable Squiz.Functions.GlobalFunction
 * @var string $testFrameworkDir - Must be defined in parent script.
 * @var Settings $settings - Must be defined in parent script.
 */

class DeployVendorTestModules
{
    /** @var string */
    private $vendorPath;

    /** @var ComponentRegistrar  */
    private $registrar;

    /** @var string */
    private $targetPath;

    public function __construct(string $testFrameworkDir)
    {
        // phpcs:ignore Magento2.Functions.DiscouragedFunction
        $this->vendorPath = realpath($testFrameworkDir . '/../../../../vendor');

        // phpcs:ignore Magento2.Functions.DiscouragedFunction
        $this->targetPath = realpath($testFrameworkDir . '/../../../../app/code');
        $this->registrar = new ComponentRegistrar();
    }

    private function getModulePaths()
    {
        return new GlobIterator(
            $this->vendorPath . '/*/*/TestModule/*/*/*',
            FilesystemIterator::KEY_AS_PATHNAME |
                FilesystemIterator::CURRENT_AS_FILEINFO |
                FilesystemIterator::FOLLOW_SYMLINKS
        );
    }

    public function install()
    {
        foreach ($this->getModulePaths() as $testModule) {
            $modulePath = $testModule->getPath() . '/';

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($modulePath, RecursiveDirectoryIterator::FOLLOW_SYMLINKS)
            );

            foreach ($iterator as $file) {
                if (!$file->isDir()) {
                    $source = $file->getPathname();

                    $parts = explode(DIRECTORY_SEPARATOR, substr($source, strlen($this->vendorPath)));
                    $relativePath = implode(DIRECTORY_SEPARATOR, array_slice($parts, 6));

                    $ns = $parts[4];
                    $module = $parts[5];

                    $destination =
                        $this->targetPath .
                        DIRECTORY_SEPARATOR .
                        $ns .
                        DIRECTORY_SEPARATOR .
                        $module .
                        DIRECTORY_SEPARATOR .
                        $relativePath;

                    // phpcs:ignore Magento2.Functions.DiscouragedFunction
                    $targetDir = dirname($destination);

                    // phpcs:ignore Magento2.Functions.DiscouragedFunction
                    if (!is_dir($targetDir)) {
                        // phpcs:ignore Magento2.Functions.DiscouragedFunction
                        mkdir($targetDir, 0755, true);
                    }
                    // phpcs:ignore Magento2.Functions.DiscouragedFunction
                    copy($source, $destination);
                }
            }
            include $modulePath . '/registration.php';
        }
    }

    public function uninstall()
    {
        $filesystem = new \Symfony\Component\Filesystem\Filesystem();

        foreach ($this->getModulePaths() as $testModule) {
            $modulePath = $testModule->getPath() . '/';

            $parts = explode(DIRECTORY_SEPARATOR, substr($modulePath, strlen($this->vendorPath)));
            $relativePath = implode(DIRECTORY_SEPARATOR, array_slice($parts, 6));

            $ns = $parts[4];
            $module = $parts[5];

            $destination =
                $this->targetPath .
                DIRECTORY_SEPARATOR .
                $ns .
                DIRECTORY_SEPARATOR .
                $module .
                DIRECTORY_SEPARATOR .
                $relativePath;

            $filesystem->remove($destination);
        }
    }
}

$deploy = new DeployVendorTestModules($testFrameworkDir);
$deploy->install();

// phpcs:ignore Magento2.Functions.DiscouragedFunction
register_shutdown_function(function (DeployVendorTestModules $deploy) {
    $deploy->uninstall();
}, $deploy);
