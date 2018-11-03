<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */
namespace ReachDigital\TestFramework;

class Application extends \Magento\TestFramework\Application
{
    //@todo Why should this be handled on bootup, should happen on the source..
    protected $canInstallSequence = false;
}
