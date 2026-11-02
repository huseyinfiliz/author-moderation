<?php

namespace HuseyinFiliz\AuthorModeration\Api;

use Flarum\Api\Context;
use Flarum\Api\Schema\Relationship\ToMany;
use Flarum\Discussion\Discussion;
use Flarum\Post\Post;
use HuseyinFiliz\AuthorModeration\AuthorPermissions;

class DiscussionPosts
{
    public function __construct(protected AuthorPermissions $permissions)
    {
    }

    public function __invoke(ToMany $field): ToMany
    {
        $original = clone $field;

        return $field->get(function (Discussion $discussion, Context $context) use ($original) {
            if (! $this->permissions->canViewHiddenReplies($context->getActor(), $discussion)) {
                return $original->getValue($context);
            }

            // Core's single-discussion fast path checks hidePosts. These authors
            // deliberately do not have that blanket grant, so use the generic
            // visibility scope, including other extensions' private/ban scopes.
            $prototype = new Post;
            $prototype->exists = true;

            return $discussion->posts()
                ->whereVisibleTo($context->getActor())
                ->orderBy('posts.number')
                ->toBase()
                ->pluck('posts.id')
                ->map(function ($id) use ($prototype) {
                    $post = clone $prototype;
                    $post->setRawAttributes(['id' => $id], true);

                    return $post;
                })->all();
        });
    }
}
