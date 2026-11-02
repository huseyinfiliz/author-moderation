<?php

namespace HuseyinFiliz\AuthorModeration\Access;

use Flarum\Discussion\Discussion;
use Flarum\User\Access\AbstractPolicy;
use Flarum\User\User;
use HuseyinFiliz\AuthorModeration\AuthorPermissions;

class LockPolicy extends AbstractPolicy
{
    public function __construct(protected AuthorPermissions $permissions)
    {
    }

    public function lock(User $actor, Discussion $discussion): ?string
    {
        return $this->permissions->allows($actor, $discussion, 'Lock') ? $this->allow() : null;
    }
}
