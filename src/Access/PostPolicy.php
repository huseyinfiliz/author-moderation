<?php

namespace HuseyinFiliz\AuthorModeration\Access;

use Flarum\Post\Post;
use Flarum\User\Access\AbstractPolicy;
use Flarum\User\User;
use HuseyinFiliz\AuthorModeration\AuthorPermissions;

class PostPolicy extends AbstractPolicy
{
    public function __construct(protected AuthorPermissions $permissions)
    {
    }

    public function edit(User $actor, Post $post): ?string
    {
        return $this->permissions->allowsPost($actor, $post, 'EditPosts') ? $this->allow() : null;
    }

    public function hide(User $actor, Post $post): ?string
    {
        // The first post retains core's behavior; whole-topic moderation is separate.
        if ($post->number === 1) {
            return null;
        }

        $ability = $post->hidden_at ? 'RestorePosts' : 'HidePosts';

        if ($this->permissions->allowsPost($actor, $post, $ability)
            && (! $post->hidden_at || $this->permissions->canRestore($actor, $post->hidden_user_id))) {
            return $this->allow();
        }

        return null;
    }
}
