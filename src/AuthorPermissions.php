<?php

namespace HuseyinFiliz\AuthorModeration;

use Flarum\Discussion\Discussion;
use Flarum\Post\CommentPost;
use Flarum\Post\Post;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;

class AuthorPermissions
{
    public const REPLY_ABILITIES = ['EditPosts', 'HidePosts', 'RestorePosts'];

    public function __construct(
        protected Eligibility $eligibility,
        protected SettingsRepositoryInterface $settings,
        protected Authority $authority
    ) {
    }

    public function enabledFor(User $actor): bool
    {
        return (bool) $this->settings->get('huseyinfiliz-author-moderation.moderation_enabled', true)
            && $this->eligibility->allows($actor);
    }

    public function allows(User $actor, Discussion $discussion, string $ability): bool
    {
        return $actor->id && $discussion->user_id
            && (int) $actor->id === (int) $discussion->user_id
            && $actor->can('authorModeration'.$ability, $discussion)
            && $this->enabledFor($actor);
    }

    public function allowsPost(User $actor, Post $post, string $ability): bool
    {
        if (! $post instanceof CommentPost || $post->is_private
            || ! $this->allows($actor, $post->discussion, $ability)) {
            return false;
        }

        // Exemption limits only our extra grants, never an existing staff permission.
        return ! $post->user || (int) $post->user_id === (int) $actor->id
            || ! $this->authority->forUser($post->user)->hasPermission('huseyinfiliz-author-moderation.exempt');
    }

    public function canRestore(User $actor, ?int $hiddenBy): bool
    {
        return $hiddenBy === (int) $actor->id
            || (bool) $this->settings->get('huseyinfiliz-author-moderation.restore_others', false);
    }

    public function canViewHiddenReplies(User $actor, Discussion $discussion): bool
    {
        foreach (self::REPLY_ABILITIES as $ability) {
            if ($this->allows($actor, $discussion, $ability)) {
                return true;
            }
        }

        return false;
    }
}
