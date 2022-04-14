<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */
namespace ReachDigital\TestFramework\Annotation;

use PHPUnit\Framework\TestCase;
use PHPUnit\Util\Test as TestUtil;

/**
 * Rewrite of \Magento\TestFramework\Annotation\AppIsolation because that class will always reinitialize on each test
 * while it should only reinitialize when the test asks for it by providing '@magentoAppIsolation enabled'
 *
 * @see \Magento\TestFramework\Annotation\AppIsolation
 */
class AppIsolation
{
    /** @var \Magento\TestFramework\Application */
    private $application;

    /**@var array */
    private $serverGlobalBackup;

    public function __construct(\Magento\TestFramework\Application $application)
    {
        $this->application = $application;
    }

    /**
     * Isolate global application objects
     */
    protected function _isolateApp()
    {
        $this->application->reinitialize();
        $_SESSION = [];
        $_COOKIE = [];
        session_write_close();
    }

    /**
     * Isolate application before running test case
     */
    public function startTestSuite()
    {
        $this->serverGlobalBackup = $_SERVER;
    }

    /**
     * Isolate application after running test case
     */
    public function endTestSuite()
    {
        $_SERVER = $this->serverGlobalBackup;
    }

    /**
     * So if the test that has just ran has '@magentoAppIsolation enabled'
     *
     * @param TestCase $testCase
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function startTest(TestCase $testCase)
    {
        if ($this->isTestMagentoAppIsolationEnabled($testCase)) {
            $this->_isolateApp();
        }
    }

    /**
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function isTestMagentoAppIsolationEnabled(TestCase $testCase)
    {
        /* Determine an isolation from doc comment */
        $annotations = TestUtil::parseTestMethodAnnotations(get_class($testCase), $testCase->getName(false));
        $annotations = array_replace((array) $annotations['class'], (array) $annotations['method']);

        if (isset($annotations['magentoAppIsolation'])) {
            $isolation = $annotations['magentoAppIsolation'];
            if ($isolation !== ['enabled'] && $isolation !== ['disabled']) {
                throw new \Magento\Framework\Exception\LocalizedException(
                    __('Invalid "@magentoAppIsolation" annotation, can be "enabled" or "disabled" only.')
                );
            }
            return $isolation === ['enabled'];
        }

        /* Controller tests should be isolated by default */
        return $testCase instanceof \Magento\TestFramework\TestCase\AbstractController;
    }
}
