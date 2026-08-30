<?php

declare(strict_types=1);

use ComposerUnused\ComposerUnused\Configuration\Configuration;
use ComposerUnused\ComposerUnused\Configuration\NamedFilter;

return static function (Configuration $config): Configuration {
    return $config
        ->addNamedFilter(NamedFilter::fromString('spatie/laravel-activitylog'))
        ->addNamedFilter(NamedFilter::fromString('spatie/laravel-settings'))
        ->addNamedFilter(NamedFilter::fromString('filament/filament'))
        ->addNamedFilter(NamedFilter::fromString('laravel/tinker'));
};
