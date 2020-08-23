<?php
declare(strict_types=1);
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

namespace ReachDigital\TestFramework;

use Magento\Framework\App\DeploymentConfig\Writer\PhpFormatter;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Component\ComponentRegistrar;
use Magento\TestFramework\Isolation\DeploymentConfig;

class Application extends \Magento\TestFramework\Application
{
    //@todo Why should this be handled on bootup, should happen on the source..
    protected $canInstallSequence = false;

    /**
     * @inheritDoc
     */
    public function install($cleanup)
    {
        $this->_ensureDirExists($this->installDir);
        $this->_ensureDirExists($this->_configDir);

        $file = $this->_globalConfigDir . '/config.php';
        if (file_exists(TESTS_ROOT_DIR . '/etc/config.php')) {
            $file = TESTS_ROOT_DIR . '/etc/config.php';
        }

        $targetFile = $this->installDir . '/etc/config.php';

        $this->_ensureDirExists(dirname($targetFile));
        if ($file !== $targetFile) {
            copy($file, $targetFile);
        }

        $configData = include $file;
        $configData['modules'] = array_merge($configData['modules'], $this->getTestModules());

        file_put_contents($targetFile, (new PhpFormatter())->format($configData));

        parent::install($cleanup);
    }

    private function getTestModules()
    {
        $modules = (new ComponentRegistrar())->getPaths(ComponentRegistrar::MODULE);

        $filteredModules = array_flip(
            array_filter(array_keys($modules), function ($item) {
                return strpos($item, 'TestModule') !== false;
            })
        );
        return array_map(function () {
            return 1;
        }, $filteredModules);
    }

    /**
     * @inheritDoc
     */
    public function isInstalled()
    {
        //We check for the env.php file instead of the config.php because those already exist
        return is_file($this->_configDir . '/env.php');
    }

    protected function getCustomDirs()
    {
        $customDirs = parent::getCustomDirs();
        unset($customDirs[DirectoryList::GENERATED_CODE]);
        return $customDirs;
    }
}
