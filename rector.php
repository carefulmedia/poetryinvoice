<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\ValueObject\PhpVersion;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/web/modules/custom',
    ])
    ->withImportNames()
    ->withPhpVersion(PhpVersion::PHP_83)
    ->withSets([
        __DIR__ . '/vendor/palantirnet/drupal-rector/config/drupal-10/drupal-10-all-deprecations.php',
    ]);
