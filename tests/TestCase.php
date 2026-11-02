<?php

namespace HuseyinFiliz\AuthorModeration\Tests;

use Flarum\Foundation\Paths;
use Flarum\Testing\integration\TestCase as FrameworkTestCase;
use Illuminate\Filesystem\Filesystem;

abstract class TestCase extends FrameworkTestCase
{
    protected function app()
    {
        $app = parent::app();
        $container = $app->getContainer();

        // Core 2.0.0 clears this directory when test extensions are enabled,
        // but its catalogue writer assumes the directory still exists.
        $container->make(Filesystem::class)->ensureDirectoryExists(
            $container->make(Paths::class)->storage.'/locale'
        );

        return $app;
    }
}
