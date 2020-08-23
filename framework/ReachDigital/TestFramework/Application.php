<?php
declare(strict_types=1);
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

namespace ReachDigital\TestFramework;

use Magento\Framework\App\Filesystem\DirectoryList;

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

        parent::install($cleanup);
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
