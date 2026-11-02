<?php

namespace HuseyinFiliz\AuthorModeration\Access;

use Flarum\Discussion\Discussion;
use Flarum\User\Access\AbstractPolicy;
use Flarum\User\User;
use HuseyinFiliz\AuthorModeration\AuthorOnly;
use HuseyinFiliz\AuthorModeration\AuthorPermissions;

class DiscussionPolicy extends AbstractPolicy
{
    public function __construct(
        protected AuthorOnly $authorOnly,
        protected AuthorPermissions $permissions
    ) {
    }

    public function rename(User $actor, Discussion $discussion): ?string
    {
        return $this->permissions->allows($actor, $discussion, 'Rename') ? $this->allow() : null;
    }

    public function hide(User $actor, Discussion $discussion): ?string
    {
        $ability = $discussion->hidden_at ? 'Restore' : 'Hide';

        if ($this->permissions->allows($actor, $discussion, $ability)
            && (! $discussion->hidden_at || $this->permissions->canRestore($actor, $discussion->hidden_user_id))) {
            return $this->allow();
        }

        return null;
    }

    public function reply(User $actor, Discussion $discussion): ?string
    {
        if ($this->authorOnly->isActive($discussion)
            && (int) $actor->id !== (int) $discussion->user_id
            && ! $actor->hasPermission('huseyinfiliz-author-moderation.replyInAuthorOnly')) {
            return $this->deny();
        }

        // Never grant reply here: locks, tag permissions and bans still apply.
        return null;
    }
}
