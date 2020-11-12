<?php
use Magento\Framework\App\Cache\Manager;
use Magento\Framework\App\Utility\Files;
use Magento\Framework\Autoload\AutoloaderRegistry;
use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Component\DirSearch;
use Magento\Framework\Logger\Handler\Debug;
use Magento\Framework\Logger\Handler\System;
use Magento\Framework\Profiler\Driver\Standard;
use Magento\Framework\Shell;
use Magento\Framework\Shell\CommandRenderer;
use Magento\Framework\View\Design\Theme\ThemePackageList;
use Magento\TestFramework\Bootstrap\Environment;
use Magento\TestFramework\Bootstrap\MemoryFactory;
use Magento\TestFramework\Bootstrap\Profiler;
use Magento\TestFramework\Bootstrap\Settings;
use Magento\TestFramework\Helper\Bootstrap;
use Monolog\Logger;
use ReachDigital\TestFramework\Application;
use ReachDigital\TestFramework\Bootstrap\DocBlock;

/**
 * phpcs:disable PSR1.Files.SideEffects
 * phpcs:disable Squiz.Functions.GlobalFunction
 * phpcs:disable Magento2.Security.IncludeFile
 */
require_once __DIR__ . '/../../../../app/bootstrap.php';
require_once __DIR__ . '/autoload.php';

if (!defined('TESTS_ROOT_DIR')) {
    define('TESTS_ROOT_DIR', dirname(__DIR__));
}

$integrationTestDir = realpath(__DIR__ . '/../../integration');
$fixtureBaseDir = $integrationTestDir . '/testsuite';

if (!defined('TESTS_TEMP_DIR')) {
    define('TESTS_TEMP_DIR', $integrationTestDir . '/tmp');
}

if (!defined('INTEGRATION_TESTS_DIR')) {
    define('INTEGRATION_TESTS_DIR', $integrationTestDir);
}

try {
    setCustomErrorHandler();

    /* Bootstrap the application */
    $settings = new Settings($integrationTestDir, get_defined_constants());

    $testFrameworkDir = $integrationTestDir . '/framework';
    require_once $integrationTestDir . '/framework/deployTestModules.php';
    require_once 'deployVendorTestModules.php';

    if ($settings->get('TESTS_EXTRA_VERBOSE_LOG')) {
        $filesystem = new \Magento\Framework\Filesystem\Driver\File();
        $exceptionHandler = new \Magento\Framework\Logger\Handler\Exception($filesystem);
        $loggerHandlers = [
            'system' => new System($filesystem, $exceptionHandler),
            'debug' => new Debug($filesystem),
        ];
        $shell = new Shell(new CommandRenderer(), new Logger('main', $loggerHandlers));
    } else {
        $shell = new Shell(new CommandRenderer());
    }

    $installConfigFile = $settings->getAsConfigFile('TESTS_INSTALL_CONFIG_FILE');

    // phpcs:ignore Magento2.Functions.DiscouragedFunction
    if (!file_exists($installConfigFile)) {
        $installConfigFile .= '.dist';
    }
    $globalConfigFile = $settings->getAsConfigFile('TESTS_GLOBAL_CONFIG_FILE');
    // phpcs:ignore Magento2.Functions.DiscouragedFunction
    if (!file_exists($globalConfigFile)) {
        $globalConfigFile .= '.dist';
    }
    $sandboxUniqueId = md5(sha1_file($installConfigFile));
    $installDir = TESTS_TEMP_DIR . "/sandbox-{$settings->get('TESTS_PARALLEL_THREAD', 0)}-{$sandboxUniqueId}";

    // phpcs:ignore Magento2.Functions.DiscouragedFunction
    $coldBoot = !is_dir($installDir . '/cache');

    $application = new Application(
        $shell,
        $installDir,
        $installConfigFile,
        $globalConfigFile,
        $settings->get('TESTS_GLOBAL_CONFIG_DIR'),
        $settings->get('TESTS_MAGENTO_MODE'),
        AutoloaderRegistry::getAutoloader(),
        true
    );

    $bootstrap = new \Magento\TestFramework\Bootstrap(
        $settings,
        new Environment(),
        new DocBlock("{$integrationTestDir}/testsuite"),
        new Profiler(new Standard()),
        $shell,
        $application,
        new MemoryFactory($shell)
    );

    $bootstrap->runBootstrap();
    if ($settings->getAsBoolean('TESTS_CLEANUP')) {
        $application->cleanup();
    }
    if (!$application->isInstalled()) {
        $application->install($settings->getAsBoolean('TESTS_CLEANUP'));
    }
    $stop = rdTimerStart('$application->initialize', true);
    $application->initialize([]);
    $initTime = $stop();

    Bootstrap::setInstance(new Bootstrap($bootstrap));

    if ($initTime > 2000 && !$coldBoot) {
        $shell->execute('rm -r %s', ["{$installDir}/cache"]);
        die("Invalid cache detected (booting took {$initTime}ms), flushed all caches, please restart, exiting now..");
    }

    if (!$coldBoot) {
        // Make sure all caches are enabled
        /** @var Manager $cacheManager */
        $cacheManager = Bootstrap::getObjectManager()->get(Manager::class);
        $cacheManager->setEnabled($cacheManager->getAvailableTypes(), true);
    }

    $dirSearch = Bootstrap::getObjectManager()->create(DirSearch::class);
    $themePackageList = Bootstrap::getObjectManager()->create(ThemePackageList::class);
    Files::setInstance(
        new Magento\Framework\App\Utility\Files(new ComponentRegistrar(), $dirSearch, $themePackageList)
    );

    if (class_exists(\Magento\TestFramework\Workaround\Override\Config::class)) {
        /** @var \Magento\TestFramework\Workaround\Override\Config $overrideConfig */
        $overrideConfig = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->create(
            Magento\TestFramework\Workaround\Override\Config::class
        );
        $overrideConfig->init();
        Magento\TestFramework\Workaround\Override\Config::setInstance($overrideConfig);
        Magento\TestFramework\Workaround\Override\Fixture\Resolver::setInstance(
            new  \Magento\TestFramework\Workaround\Override\Fixture\Resolver($overrideConfig)
        );
    } else {
        $overrideConfig = null;
    }

    /* Unset declared global variables to release the PHPUnit from maintaining their values between tests */
    unset($testsBaseDir, $logWriter, $settings, $shell, $application, $bootstrap, $overrideConfig);
} catch (Exception $e) {
    // phpcs:ignore Magento2.Security.LanguageConstruct.DirectOutput
    echo $e . PHP_EOL;
    // phpcs:ignore Magento2.Security.LanguageConstruct.ExitUsage
    exit(1);
}

function rdTimerStart($name, $asFloat = false): callable
{
    $startTime = microtime(true);
    return function () use ($startTime, $name, $asFloat) {
        $duration = round((microtime(true) - $startTime) * 1000);
        if ($asFloat) {
            return $duration;
        }
        if ($duration) {
            echo "{$name}: {$duration}ms\n";
        }
        return $duration;
    };
}

/**
 * Set custom error handler
 */
function setCustomErrorHandler()
{
    set_error_handler(function ($errNo, $errStr, $errFile, $errLine) {
        if (error_reporting()) {
            $errorNames = [
                E_ERROR => 'Error',
                E_WARNING => 'Warning',
                E_PARSE => 'Parse',
                E_NOTICE => 'Notice',
                E_CORE_ERROR => 'Core Error',
                E_CORE_WARNING => 'Core Warning',
                E_COMPILE_ERROR => 'Compile Error',
                E_COMPILE_WARNING => 'Compile Warning',
                E_USER_ERROR => 'User Error',
                E_USER_WARNING => 'User Warning',
                E_USER_NOTICE => 'User Notice',
                E_STRICT => 'Strict',
                E_RECOVERABLE_ERROR => 'Recoverable Error',
                E_DEPRECATED => 'Deprecated',
                E_USER_DEPRECATED => 'User Deprecated',
            ];

            $errName = isset($errorNames[$errNo]) ? $errorNames[$errNo] : '';

            throw new \PHPUnit\Framework\Exception(
                sprintf('%s: %s in %s:%s.', $errName, $errStr, $errFile, $errLine),
                $errNo
            );
        }
    });
}
