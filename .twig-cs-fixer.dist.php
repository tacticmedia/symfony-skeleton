<?php

use TwigCsFixer\Config\Config;
use TwigCsFixer\Rules\Function\IncludeFunctionRule;
use TwigCsFixer\Rules\Literal\CompactHashRule;
use TwigCsFixer\Standard\Symfony;

$ruleset = new TwigCsFixer\Ruleset\Ruleset();

$ruleset
    ->addStandard(new Symfony())
    ->removeRule(CompactHashRule::class)
    ->removeRule(IncludeFunctionRule::class)
;

$finder = new TwigCsFixer\File\Finder()
    ->in(__DIR__)
    ->exclude('var')
;

$config = new Config();
$config->setRuleset($ruleset);
$config->setFinder($finder);

return $config;
