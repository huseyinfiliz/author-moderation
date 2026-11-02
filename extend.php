<?php

/*
 * This file is part of huseyinfiliz/author-moderation.
 *
 * Copyright (c) 2026 Hüseyin Filiz.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace HuseyinFiliz\AuthorModeration;

use Flarum\Api\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource;
use Flarum\Api\Schema;
use Flarum\Discussion\Discussion;
use Flarum\Extend;
use Flarum\Group\Group;
use Flarum\Post\Post;
use Flarum\User\User;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less'),
    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js')
        ->css(__DIR__.'/less/admin.less'),
    new Extend\Locales(__DIR__.'/locale'),

    (new Extend\ServiceProvider())
        ->register(Provider::class),

    (new Extend\Middleware('api'))
        ->add(Middleware\CacheEligibility::class),

    (new Extend\Middleware('forum'))
        ->add(Middleware\CacheEligibility::class),

    (new Extend\Settings())
        ->default('huseyinfiliz-author-moderation.moderation_enabled', '1')
        ->default('huseyinfiliz-author-moderation.restore_others', '0')
        ->default('huseyinfiliz-author-moderation.author_only_enabled', '1')
        ->default('huseyinfiliz-author-moderation.warning_limit_enabled', '0')
        ->default('huseyinfiliz-author-moderation.warning_threshold', '3'),

    (new Extend\Model(Discussion::class))
        ->cast('is_author_only', 'boolean'),

    // Internal authorization data only: no API field exposes this relationship.
    (new Extend\Model(User::class))
        ->belongsToMany('authorModerationGroups', Group::class, 'group_user', 'user_id', 'group_id'),

    (new Extend\Policy())
        ->modelPolicy(Discussion::class, Access\DiscussionPolicy::class)
        ->modelPolicy(Post::class, Access\PostPolicy::class),

    (new Extend\ModelVisibility(Discussion::class))
        ->scope(Access\ScopeHiddenReplies::class, 'hidePosts'),

    (new Extend\ApiResource(Resource\DiscussionResource::class))
        ->endpoint([Endpoint\Index::class, Endpoint\Show::class, Endpoint\Update::class], Api\PreloadAuthority::class)
        ->field('posts', Api\DiscussionPosts::class)
        ->fields(fn () => [
            Schema\Boolean::make('isAuthorOnly')
                ->property('is_author_only')
                ->writable(fn (Discussion $discussion, Context $context) => $context->updating()
                    && resolve(AuthorOnly::class)->canManage($context->getActor(), $discussion))
                ->set(function (Discussion $discussion, bool $enabled, Context $context) {
                    if ($discussion->is_author_only === $enabled) {
                        return;
                    }

                    $discussion->is_author_only = $enabled;
                    $discussion->raise(new Event\AuthorOnlyChanged($discussion, $context->getActor(), $enabled));
                }),
            Schema\Boolean::make('authorOnlyActive')
                ->get(fn (Discussion $discussion) => resolve(AuthorOnly::class)->isActive($discussion)),
            Schema\Boolean::make('canManageAuthorOnly')
                ->get(fn (Discussion $discussion, Context $context) => resolve(AuthorOnly::class)->canManage($context->getActor(), $discussion)),
        ]),

    (new Extend\ApiResource(Resource\PostResource::class))
        ->endpoint([Endpoint\Index::class, Endpoint\Show::class, Endpoint\Update::class], Api\PreloadAuthority::class),

    (new Extend\Conditional())
        ->whenExtensionEnabled('flarum-lock', fn () => [
            (new Extend\Policy())
                ->modelPolicy(Discussion::class, Access\LockPolicy::class),
        ])
        ->whenExtensionEnabled('flarum-audit', fn () => [
            (new \Flarum\Audit\Extend\Audit())
                ->listen(Event\AuthorOnlyChanged::class, 'author_moderation.author_only_changed', fn (Event\AuthorOnlyChanged $event) => [
                    'discussion_id' => $event->discussion->id,
                    'enabled' => $event->enabled,
                ]),
        ]),
];
