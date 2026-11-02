<?php

namespace HuseyinFiliz\AuthorModeration;

use Flarum\User\User;
use WeakMap;

class Authority
{
    /** @var WeakMap<User, User>|null */
    protected ?WeakMap $users = null;

    public function forUser(User $user): User
    {
        if ($this->users !== null && isset($this->users[$user])) {
            return $this->users[$user];
        }

        // Flarum 2.0.0 may preload only visible groups for API output. Never
        // derive authority from that presentation-filtered relationship.
        $principal = $user->newInstance([], $user->exists);
        $principal->setRawAttributes($user->getAttributes(), true);
        $principal->setRelation('groups', $user->authorModerationGroups);

        if ($this->users !== null) {
            $this->users[$user] = $principal;
        }

        return $principal;
    }

    public function withinRequest(callable $next): mixed
    {
        $previous = $this->users;
        $this->users = new WeakMap;

        try {
            return $next();
        } finally {
            $this->users = $previous;
        }
    }
}
