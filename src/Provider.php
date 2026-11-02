<?php

namespace HuseyinFiliz\AuthorModeration;

use Flarum\Foundation\AbstractServiceProvider;

class Provider extends AbstractServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(Eligibility::class);
        $this->container->singleton(Authority::class);
    }
}
