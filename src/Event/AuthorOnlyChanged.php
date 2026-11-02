<?php

namespace HuseyinFiliz\AuthorModeration\Event;

use Flarum\Discussion\Discussion;
use Flarum\User\User;

class AuthorOnlyChanged
{
    public function __construct(
        public Discussion $discussion,
        public User $actor,
        public bool $enabled
    ) {
    }
}
