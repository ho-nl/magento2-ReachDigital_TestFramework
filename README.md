# ReachDigital Magento 2 Performance tuned integration tests

TLDR; Boots up the integration testframework in less than 300ms when caches are primed.

Video: http://cloud.h-o.nl/adf01f498e00

## Installation

```bash
composer require --dev reach-digital/magento2-test-framework
```

## Usage

After the installation of the package there will be a folder `dev/tests/quick-integration` with the new integration
test framework. Copy `phpunit.xml.dist` to `phpunit.xml` and make your changes to include your own namespaces.

## Goals
- Have the startup time of the integration testframework below 300ms.
- Have no feature regressions for small batches of test.
- Show helpfull messages to speed up your tests

## Non-Goals
- Be a complete replacement for the complete integration test suite, only support the small suite that you test locally
will be fine.

## Motivation

Magento 2's integration tests are notoriously slow in booting up, which makes practicing TDD a pain in the ass. Nobody
wants to wait more than 10 seconds for tests to start..

Speed matters, but Magento developer have grown accustomed that things are just slow.

> - 0 to 100ms:	Respond to user actions within this time window and users feel like the result is immediate. Any longer, and the connection between action and reaction is broken.
> - 100 to 300ms: Users experience a slight perceptible delay.
> - 300 to 1000ms: Within this window, things feel part of a natural and continuous progression of tasks. For most users on the web, loading pages or changing views represents a task.
> - 1000ms or more: Beyond 1000 milliseconds (1 second), users lose focus on the task they are performing.
> - 10000ms or more: Beyond 10000 milliseconds (10 seconds), users are frustrated and are likely to abandon tasks. They may or may not come back later.
>
> https://developers.google.com/web/fundamentals/performance/rail

Currently it is no exception for the integration tests to run more than 10000ms: "Developers get
frustrated, are likely to abandon the test. They may or may not try TDD again later."

To put it in perspective: It is faster to load an Admin Page, click a button there than it is to click Play on a test..
it shoudn't be this way.

Because: **If Magento is able to render a complete html-page under 200ms, shouldn't a test be able to start at least as quickly as well?**

## Performance improvements

So the idea is that Magento is probably cleaning a lof of cache while booting up, running additional tests, etc. If we
can prevent the cleaning of cache, state, etc. we can achieve much higher performance and maybe even surpass the
frontend.

_Although this is probably a good idea to have 'clean slate', it isn't even a great idea per sé. Code should be
resiliant and should be able to run with warmed cache and cold cache.._

### 1. Disable memory cleanup scripts

Speed improvement; ~10-20s

By disabling the following classes we get the biggest speed improvement.

```php
<?php
\Magento\TestFramework\Workaround\Cleanup\TestCaseProperties::class;
\Magento\TestFramework\Workaround\Cleanup\StaticProperties::class;
```

### 2. Fix overzealous app reinitialisation

Speed improvement; ~50ms

```php
<?php
//Rewrites Magento's AppIsolation class
\ReachDigital\TestFramework\Annotation\AppIsolation::class;
```

### 3. Disable config-global.php by default

Speed improvement; ~280ms

The config-global.php.dist will always set some config values, but this requires reinitialisation of the config. By
not using this functionality we shave another 300ms off the request.

### 4. Disabled sequence table generation

Speed improvement; ~400ms

By default Magento creates all sequence tables

## Usability improvements

### 1. Moved the generation folder back to the root

Usually an IDE doesn't like it when duplicate classes exist, because of this reason the
`dev/test/integration/tmp/sandbox-*` directory should be ignored. By moving the generated folder to the root of the
project we get the benefit that the IDE can inspect those classes.
