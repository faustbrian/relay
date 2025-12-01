<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\PhpCsFixer\Preset\Standard;
use Cline\PhpCsFixer\ConfigurationFactory;

$config = ConfigurationFactory::createFromPreset(
    new Standard(),
);

/** @var PhpCsFixer\Finder $finder */
$finder = $config->getFinder();
$finder->in([__DIR__.'/src', __DIR__.'/tests']);

// Fixture is extensible for custom redaction logic
$config->setRules(array_merge($config->getRules(), [
    'final_class' => false,
]));

return $config;
