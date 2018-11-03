<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */

namespace ReachDigital\TestFramework\Bootstrap;

class DocBlock extends \Magento\TestFramework\Bootstrap\DocBlock
{

    protected function _getSubscribers(\Magento\TestFramework\Application $application)
    {
        return [
            new \Magento\TestFramework\Workaround\Segfault(),
//            new \Magento\TestFramework\Workaround\Cleanup\TestCaseProperties(),
//            new \Magento\TestFramework\Workaround\Cleanup\StaticProperties(),
            new \Magento\TestFramework\Isolation\WorkingDirectory(),
            new \Magento\TestFramework\Isolation\DeploymentConfig(),
            new \ReachDigital\TestFramework\Annotation\AppIsolation($application),
            new \Magento\TestFramework\Annotation\IndexerDimensionMode($application),
            new \Magento\TestFramework\Isolation\AppConfig(),
            new \Magento\TestFramework\Annotation\ConfigFixture(),
            new \Magento\TestFramework\Annotation\DataFixtureBeforeTransaction($this->_fixturesBaseDir),
            new \Magento\TestFramework\Event\Transaction(
                new \Magento\TestFramework\EventManager(
                    [
                        new \Magento\TestFramework\Annotation\DbIsolation(),
                        new \Magento\TestFramework\Annotation\DataFixture($this->_fixturesBaseDir),
                    ]
                )
            ),
            new \Magento\TestFramework\Annotation\ComponentRegistrarFixture($this->_fixturesBaseDir),
            new \Magento\TestFramework\Annotation\AppArea($application),
            new \Magento\TestFramework\Annotation\Cache($application),
            new \Magento\TestFramework\Annotation\AdminConfigFixture(),
            new \Magento\TestFramework\Annotation\ConfigFixture(),
        ];
    }
}
