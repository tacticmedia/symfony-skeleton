<?php

declare(strict_types=1);

use Rector\CodingStyle\Rector\ClassLike\NewlineBetweenClassLikeStmtsRector;
use Rector\Config\RectorConfig;
use Rector\Naming\Rector\Class_\RenamePropertyToMatchTypeRector;
use Rector\Naming\Rector\ClassMethod\RenameParamToMatchTypeRector;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;
use Rector\TypeDeclaration\Rector\ClassMethod\ParamTypeByMethodCallTypeRector;

// conf/bleedingEdge.neon includes phar://phpstan.phar/, an alias only the phpstan binary registers.
Phar::loadPhar(__DIR__.'/vendor/phpstan/phpstan/phpstan.phar', 'phpstan.phar');

return RectorConfig::configure()
    ->withParallel()
    ->withMemoryLimit('-1')
    ->withCache(
        cacheDirectory: __DIR__.'/var/cache/rector',
        cacheClass: Rector\Caching\ValueObject\Storage\FileCacheStorage::class,
        containerCacheDirectory: __DIR__.'/var/cache/dev',
    )
    ->withSymfonyContainerPhp(__DIR__.'/var/cache/dev/App_KernelDevDebugContainer.php')
    ->withPHPStanConfigs([
        __DIR__.'/phpstan.dist.neon',
    ])
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
        typeDeclarationDocblocks: true,
        privatization: true,
        naming: true,
    )
    ->withAttributesSets(all: true)
    ->withPhpSets(php85: true)
    ->withPaths([
        __DIR__.'/src',
        __DIR__.'/tests',
    ])
    ->withSkipPath(__DIR__.'/src/Kernel.php')
    ->withRootFiles()
    ->withComposerBased(
        twig: true,
        doctrine: true,
        phpunit: true,
        symfony: true,
    )
    ->withSkip([
        NewlineBetweenClassLikeStmtsRector::class, // php-cs-fixer strips the blank lines again; skipping keeps rector --dry-run clean
        ParamTypeByMethodCallTypeRector::class, // inserts ?mixed, which is B/S
        ClassPropertyAssignToConstructorPromotionRector::class, // I don't always want to use constructor promotion
        RenameParamToMatchTypeRector::class, // I don't like this
        RenamePropertyToMatchTypeRector::class, // I don't like this either
        Rector\Naming\Rector\Foreach_\RenameForeachValueVariableToMatchExprVariableRector::class, // nor this one
        Rector\Naming\Rector\Foreach_\RenameForeachValueVariableToMatchMethodCallReturnTypeRector::class, // renames $file to $finder
        Rector\Naming\Rector\Assign\RenameVariableToMatchMethodCallReturnTypeRector::class,
        Rector\Naming\Rector\ClassMethod\RenameVariableToMatchNewTypeRector::class,
        // The access inspector fixtures exist only to carry attributes on empty actions.
        Rector\DeadCode\Rector\ClassMethod\RemoveEmptyClassMethodRector::class => [__DIR__.'/tests/Functional/Security/Fixture'],
        // bin/console importmap:require rewrites the file from a template without strict_types.
        Rector\TypeDeclaration\Rector\StmtsAwareInterface\SafeDeclareStrictTypesRector::class => [__DIR__.'/importmap.php'],
    ])
;
