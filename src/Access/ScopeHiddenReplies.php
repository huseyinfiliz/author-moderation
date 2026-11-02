<?php

namespace HuseyinFiliz\AuthorModeration\Access;

use Flarum\Discussion\Discussion;
use Flarum\Extension\ExtensionManager;
use Flarum\User\User;
use HuseyinFiliz\AuthorModeration\AuthorPermissions;
use Illuminate\Database\Eloquent\Builder;

class ScopeHiddenReplies
{
    public function __construct(
        protected AuthorPermissions $permissions,
        protected ExtensionManager $extensions
    ) {
    }

    public function __invoke(User $actor, Builder $query): void
    {
        if (! $this->permissions->enabledFor($actor)) {
            return;
        }

        foreach (AuthorPermissions::REPLY_ABILITIES as $ability) {
            $permission = 'discussion.authorModeration'.$ability;

            if (! $actor->hasPermissionLike($permission)
                || (! $actor->hasPermission($permission) && ! $this->extensions->isEnabled('flarum-tags'))) {
                continue;
            }

            // Extend core's hidden-post visibility entry point, not hidePosts itself:
            // granting that ability would let core modify exempt users' posts.
            $query->orWhereIn('discussions.id', function ($sub) use ($actor, $permission) {
                $sub->select('discussions.id')->from('discussions');
                Discussion::query()->setQuery($sub)
                    ->where('discussions.user_id', $actor->id)
                    ->whereVisibleTo($actor)
                    ->whereVisibleTo($actor, $permission);
            });
        }
    }
}
