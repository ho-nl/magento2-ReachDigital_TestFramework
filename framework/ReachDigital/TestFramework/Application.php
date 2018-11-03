<?php
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

    protected function getCustomDirs()
    {
        $customDirs = parent::getCustomDirs();
        unset($customDirs[DirectoryList::GENERATED_CODE]);
        return $customDirs;
    }
}
