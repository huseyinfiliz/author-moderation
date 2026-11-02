<?php

namespace HuseyinFiliz\AuthorModeration;

use Flarum\Discussion\Discussion;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;

class AuthorOnly
{
    public function __construct(
        protected Eligibility $eligibility,
        protected SettingsRepositoryInterface $settings,
        protected Authority $authority
    ) {
    }

    public function canUse(User $actor, Discussion $discussion): bool
    {
        return (bool) $this->settings->get('huseyinfiliz-author-moderation.author_only_enabled', true)
            && $actor->id && $discussion->user_id
            && (int) $actor->id === (int) $discussion->user_id
            && $actor->can('authorModerationJournal', $discussion)
            && $this->eligibility->allows($actor);
    }

    public function canManage(User $actor, Discussion $discussion): bool
    {
        return $this->canUse($actor, $discussion)
            || $actor->can('manageAuthorOnly', $discussion);
    }

    public function isActive(Discussion $discussion): bool
    {
        return $discussion->is_author_only && $discussion->user
            && $this->canUse($this->authority->forUser($discussion->user), $discussion);
    }
}
