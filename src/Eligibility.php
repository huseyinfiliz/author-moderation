<?php

namespace HuseyinFiliz\AuthorModeration;

use Flarum\Extension\ExtensionManager;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use FoF\ModeratorWarnings\Model\Warning;

class Eligibility
{
    protected ?array $warningPoints = null;

    public function __construct(
        protected SettingsRepositoryInterface $settings,
        protected ExtensionManager $extensions
    ) {
    }

    public function allows(User $user): bool
    {
        if (! $user->id || ! $user->is_email_confirmed) {
            return false;
        }

        if (! $this->settings->get('huseyinfiliz-author-moderation.warning_limit_enabled', false)
            || ! $this->extensions->isEnabled('fof-moderator-warnings')) {
            return true;
        }

        $threshold = max(1, (int) $this->settings->get('huseyinfiliz-author-moderation.warning_threshold', 3));

        if ($this->warningPoints !== null && array_key_exists($user->id, $this->warningPoints)) {
            return $this->warningPoints[$user->id] < $threshold;
        }

        $points = (int) Warning::where('user_id', $user->id)->whereNull('hidden_at')->sum('strikes');

        if ($this->warningPoints !== null) {
            $this->warningPoints[$user->id] = $points;
        }

        return $points < $threshold;
    }

    public function withinRequest(callable $next): mixed
    {
        $previous = $this->warningPoints;
        $this->warningPoints = [];

        try {
            return $next();
        } finally {
            // No stale eligibility across requests, CLI calls or long-lived workers.
            $this->warningPoints = $previous;
        }
    }
}
