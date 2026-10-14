<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

return new Configuration()
    ->addPathsToScan([
        __DIR__.'/bin/console',
        __DIR__.'/config',
        __DIR__.'/migrations',
        __DIR__.'/public/index.php',
    ], isDev: false)
    // Registered for dev and test only; production installs with --no-dev.
    ->ignoreErrorsOnPackagesAndPaths([
        'symfony/debug-bundle',
        'symfony/maker-bundle',
        'symfony/web-profiler-bundle',
        'tacticmedia/qa-bundle',
        'zenstruck/foundry',
    ], [__DIR__.'/config/bundles.php'], [ErrorType::DEV_DEPENDENCY_IN_PROD])
    // Used through configuration, templates, Composer plugins or the image build.
    ->ignoreErrorsOnPackages([
        'league/commonmark', // markdown_to_html
        'martin-georgiev/postgresql-for-doctrine', // DBAL types in doctrine_postgres.yaml
        'symfony/asset', // asset()
        'symfony/asset-mapper', // importmap()
        'symfony/doctrine-messenger', // doctrine:// transports in messenger.yaml
        'symfony/flex', // Composer plugin, dump-env in the image build
        'symfony/lock', // lock.yaml
        'symfony/messenger', // messenger.yaml
        'symfony/runtime', // vendor/autoload_runtime.php
        'symfony/yaml', // configuration files
        'twig/cssinliner-extra', // inline_css
        'twig/html-extra', // html_attr(), html_classes()
        'twig/inky-extra', // inky_to_html
        'twig/intl-extra', // format_datetime
        'twig/markdown-extra', // markdown_to_html
    ], [ErrorType::UNUSED_DEPENDENCY])
    // Production needs it through vendor code: Dotenv loads .env.local.php at boot.
    ->ignoreErrorsOnPackages(['symfony/dotenv'], [ErrorType::PROD_DEPENDENCY_ONLY_IN_DEV])
    // polyfill-ctype is replaced, so Twig needs the extension.
    ->ignoreErrorsOnExtensions(['ext-ctype'], [ErrorType::PROD_DEPENDENCY_ONLY_IN_DEV])
    // iconv replaces its polyfill; apcu backs cache.system; xsl backs inky_to_html.
    ->ignoreErrorsOnExtensions(['ext-apcu', 'ext-iconv', 'ext-xsl'], [ErrorType::UNUSED_DEPENDENCY])
;
