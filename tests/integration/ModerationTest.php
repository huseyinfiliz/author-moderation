<?php

namespace HuseyinFiliz\AuthorModeration\Tests\Integration;

use Carbon\Carbon;
use Flarum\Discussion\Discussion;
use Flarum\Post\Post;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\User\User;
use HuseyinFiliz\AuthorModeration\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ModerationTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extension('huseyinfiliz-author-moderation');
        $this->setting('allow_post_editing', '0');
        $this->setting('allow_hide_own_posts', '0');
        $this->setting('allow_renaming', '0');
        $this->prepareDatabase([
            User::class => [
                $this->normalUser(),
                array_replace($this->normalUser(), ['id' => 3, 'username' => 'reader', 'email' => 'reader@machine.local']),
                array_replace($this->normalUser(), ['id' => 4, 'username' => 'protected', 'email' => 'protected@machine.local']),
            ],
            'groups' => [
                ['id' => 5, 'name_singular' => 'Author', 'name_plural' => 'Authors'],
                ['id' => 6, 'name_singular' => 'Protected', 'name_plural' => 'Protected'],
            ],
            'group_user' => [['user_id' => 2, 'group_id' => 5], ['user_id' => 4, 'group_id' => 6]],
            'group_permission' => [['group_id' => 6, 'permission' => 'huseyinfiliz-author-moderation.exempt']],
            Discussion::class => [
                ['id' => 1, 'title' => 'Owner discussion', 'user_id' => 2, 'comment_count' => 4, 'participant_count' => 4,
                    'first_post_id' => 1, 'last_post_id' => 4, 'last_post_number' => 4, 'last_posted_user_id' => 4,
                    'created_at' => Carbon::now()->subDay(), 'last_posted_at' => Carbon::now()->subMinutes(5)],
                ['id' => 2, 'title' => 'Other discussion', 'user_id' => 3, 'comment_count' => 1,
                    'first_post_id' => 5, 'last_post_id' => 5, 'last_post_number' => 1, 'last_posted_user_id' => 3,
                    'created_at' => Carbon::now()->subDay(), 'last_posted_at' => Carbon::now()->subMinutes(5)],
            ],
            Post::class => [
                $this->post(1, 1, 2, 1),
                $this->post(2, 1, 3, 2),
                $this->post(3, 1, 1, 3),
                $this->post(4, 1, 4, 4),
                $this->post(5, 2, 3, 1),
            ],
        ]);
    }

    protected function post(int $id, int $discussion, int $author, int $number): array
    {
        return ['id' => $id, 'discussion_id' => $discussion, 'user_id' => $author, 'type' => 'comment',
            'content' => '<t>Original content</t>', 'created_at' => Carbon::now()->subDay(), 'number' => $number];
    }

    protected function grant(string ...$abilities): void
    {
        $this->prepareDatabase(['group_permission' => array_map(fn ($ability) => [
            'group_id' => 5, 'permission' => 'discussion.'.$ability,
        ], $abilities)]);
    }

    protected function update(string $type, int $id, array $attributes, int $actor = 2)
    {
        return $this->send($this->request('PATCH', '/api/'.$type.'/'.$id, [
            'authenticatedAs' => $actor,
            'json' => ['data' => ['type' => $type, 'id' => (string) $id, 'attributes' => $attributes]],
        ]));
    }

    protected function assertSuccess($response): void
    {
        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
    }

    protected function assertRejected($response): void
    {
        $this->assertContains($response->getStatusCode(), [403, 404, 422], (string) $response->getBody());
    }

    #[Test]
    public function editing_is_limited_to_own_discussion_and_unprotected_targets(): void
    {
        $this->grant('authorModerationEditPosts');
        $this->assertSuccess($this->update('posts', 2, ['content' => 'Edited by discussion author']));
        $this->assertSame(2, Post::findOrFail(2)->edited_user_id);
        foreach ([3, 4, 5] as $post) {
            $this->assertRejected($this->update('posts', $post, ['content' => 'Must not change']));
            $this->assertStringContainsString('Original', Post::findOrFail($post)->content);
        }
        $this->assertRejected($this->update('posts', 2, ['content' => 'Not the owner'], 4));
    }

    #[Test]
    public function hiding_does_not_implicitly_grant_restoration_or_permanent_deletion(): void
    {
        $this->grant('authorModerationHidePosts');
        $this->assertSuccess($this->update('posts', 2, ['isHidden' => true]));
        $this->assertSame(2, Post::findOrFail(2)->hidden_user_id);
        $this->assertRejected($this->update('posts', 2, ['isHidden' => false]));
        $this->assertNotNull(Post::findOrFail(2)->hidden_at);
        $this->assertRejected($this->send($this->request('DELETE', '/api/posts/2', ['authenticatedAs' => 2])));
        $this->assertRejected($this->update('posts', 1, ['isHidden' => true]));
    }

    #[Test]
    public function hidden_replies_remain_visible_in_linkage_and_can_be_restored(): void
    {
        $this->grant('authorModerationHidePosts', 'authorModerationRestorePosts');
        $this->assertSuccess($this->update('posts', 2, ['isHidden' => true]));
        $response = $this->send($this->request('GET', '/api/discussions/1', ['authenticatedAs' => 2]));
        $this->assertSuccess($response);
        $body = json_decode((string) $response->getBody(), true);
        $this->assertContains('2', array_column($body['data']['relationships']['posts']['data'], 'id'));

        $response = $this->send($this->request('GET', '/api/posts/2', ['authenticatedAs' => 2]));
        $this->assertSuccess($response);
        $this->assertTrue(json_decode((string) $response->getBody(), true)['data']['attributes']['canHide']);
        $this->assertSuccess($this->update('posts', 2, ['isHidden' => false]));
        $this->assertNull(Post::findOrFail(2)->hidden_at);
    }

    #[Test]
    public function restoring_another_users_moderation_requires_the_setting_and_respects_exemption(): void
    {
        $this->grant('authorModerationRestorePosts');
        $this->app();
        $this->database()->table('posts')->whereIn('id', [2, 4])->update(['hidden_at' => Carbon::now(), 'hidden_user_id' => 1]);
        $this->assertRejected($this->update('posts', 2, ['isHidden' => false]));
        $this->app()->getContainer()->make('flarum.settings')->set('huseyinfiliz-author-moderation.restore_others', '1');
        $this->assertSuccess($this->update('posts', 2, ['isHidden' => false]));
        $this->assertRejected($this->update('posts', 4, ['isHidden' => false]));
    }

    #[Test]
    public function author_can_manage_a_replied_to_discussion_without_getting_hard_delete(): void
    {
        $this->grant('authorModerationRename', 'authorModerationHide', 'authorModerationRestore');
        $this->assertSuccess($this->update('discussions', 1, ['title' => 'New title by owner']));
        $this->assertSuccess($this->update('discussions', 1, ['isHidden' => true]));
        $this->assertNotNull(Discussion::findOrFail(1)->hidden_at);
        $this->assertSuccess($this->update('discussions', 1, ['isHidden' => false]));
        $this->assertNull(Discussion::findOrFail(1)->hidden_at);
        $this->assertRejected($this->send($this->request('DELETE', '/api/discussions/1', ['authenticatedAs' => 2])));
        $this->assertRejected($this->update('discussions', 2, ['title' => 'Not my topic']));
        $this->assertFalse(User::findOrFail(2)->can('viewFlags', Discussion::findOrFail(1)));
    }

    #[Test]
    public function staff_hidden_discussion_is_not_restorable_by_default(): void
    {
        $this->grant('authorModerationRestore');
        $this->app();
        $this->database()->table('discussions')->where('id', 1)->update(['hidden_at' => Carbon::now(), 'hidden_user_id' => 1]);
        $this->assertRejected($this->update('discussions', 1, ['isHidden' => false]));
        $this->app()->getContainer()->make('flarum.settings')->set('huseyinfiliz-author-moderation.restore_others', '1');
        $this->assertSuccess($this->update('discussions', 1, ['isHidden' => false]));
    }

    #[Test]
    public function lock_permission_is_optional_scoped_to_owner_and_allows_unlocking_staff_locks(): void
    {
        $this->extension('flarum-lock');
        $this->grant('authorModerationLock');
        $this->assertSuccess($this->update('discussions', 1, ['isLocked' => true], 1));
        $this->assertSuccess($this->update('discussions', 1, ['isLocked' => false]));
        $this->assertSuccess($this->update('discussions', 1, ['isLocked' => true]));
        $this->assertRejected($this->update('discussions', 2, ['isLocked' => true]));
    }

    #[Test]
    public function disabling_extra_grants_does_not_remove_existing_staff_permissions(): void
    {
        $this->grant('authorModerationEditPosts', 'editPosts', 'hidePosts');
        $this->setting('huseyinfiliz-author-moderation.moderation_enabled', '0');
        $this->assertSuccess($this->update('posts', 4, ['content' => 'Independent staff permission']));
        $this->assertSuccess($this->update('posts', 3, ['isHidden' => true]));
        $this->assertSuccess($this->update('posts', 3, ['isHidden' => false]));
    }

    #[Test]
    public function master_switch_removes_only_additional_author_grants(): void
    {
        $this->grant('authorModerationEditPosts', 'authorModerationHidePosts');
        $this->setting('huseyinfiliz-author-moderation.moderation_enabled', '0');
        $this->assertRejected($this->update('posts', 2, ['content' => 'Not allowed']));
        $this->assertRejected($this->update('posts', 2, ['isHidden' => true]));
    }

    #[Test]
    public function warning_threshold_removes_author_grants_but_not_staff_grants(): void
    {
        $this->extension('fof-moderator-warnings');
        $this->grant('authorModerationEditPosts', 'authorModerationRename');
        $this->setting('huseyinfiliz-author-moderation.warning_limit_enabled', '1');
        $this->prepareDatabase(['warnings' => [
            ['id' => 1, 'user_id' => 2, 'strikes' => 3, 'created_at' => Carbon::now(), 'public_comment' => '<t>Warning</t>'],
        ]]);
        $this->assertRejected($this->update('posts', 2, ['content' => 'At threshold']));
        $this->assertRejected($this->update('discussions', 1, ['title' => 'At threshold']));
        $this->database()->table('warnings')->where('id', 1)->update(['hidden_at' => Carbon::now()]);
        $this->assertSuccess($this->update('posts', 2, ['content' => 'Restored eligibility']));
    }

    #[Test]
    public function protected_targets_cannot_be_hidden_and_private_posts_are_not_exposed(): void
    {
        $this->grant('authorModerationHidePosts', 'authorModerationRestorePosts', 'authorModerationEditPosts');
        $this->assertRejected($this->update('posts', 3, ['isHidden' => true]));
        $this->assertRejected($this->update('posts', 4, ['isHidden' => true]));
        $this->database()->table('posts')->where('id', 2)->update(['is_private' => true, 'hidden_at' => Carbon::now()]);
        $this->assertRejected($this->send($this->request('GET', '/api/posts/2', ['authenticatedAs' => 2])));
        $response = $this->send($this->request('GET', '/api/discussions/1', ['authenticatedAs' => 2]));
        $this->assertSuccess($response);
        $body = json_decode((string) $response->getBody(), true);
        $this->assertNotContains('2', array_column($body['data']['relationships']['posts']['data'], 'id'));
    }

    #[Test]
    public function hidden_replies_in_other_discussions_are_not_exposed(): void
    {
        $this->grant('authorModerationHidePosts');
        $this->app();
        $this->database()->table('posts')->where('id', 5)->update(['hidden_at' => Carbon::now(), 'hidden_user_id' => 1]);
        $this->assertRejected($this->send($this->request('GET', '/api/posts/5', ['authenticatedAs' => 2])));
    }

    #[Test]
    public function restricted_tag_requires_its_own_author_permission(): void
    {
        $this->extension('flarum-tags');
        $this->grant('authorModerationEditPosts');
        $this->prepareDatabase([
            'tags' => [['id' => 1, 'name' => 'Restricted', 'slug' => 'restricted', 'is_restricted' => true]],
            'discussion_tag' => [['discussion_id' => 1, 'tag_id' => 1]],
            'group_permission' => [
                ['group_id' => 5, 'permission' => 'tag1.viewForum'],
                ['group_id' => 5, 'permission' => 'tag1.discussion.reply'],
            ],
        ]);
        $this->assertRejected($this->update('posts', 2, ['content' => 'Restricted tag']));
        $this->database()->table('posts')->where('id', 2)->update(['hidden_at' => Carbon::now(), 'hidden_user_id' => 1]);
        $this->assertRejected($this->send($this->request('GET', '/api/posts/2', ['authenticatedAs' => 2])));
    }

    #[Test]
    public function tag_specific_author_permission_allows_editing_and_hidden_reply_linkage(): void
    {
        $this->extension('flarum-tags');
        $this->prepareDatabase([
            'tags' => [['id' => 1, 'name' => 'Restricted', 'slug' => 'restricted', 'is_restricted' => true]],
            'discussion_tag' => [['discussion_id' => 1, 'tag_id' => 1]],
            'group_permission' => [
                ['group_id' => 5, 'permission' => 'tag1.viewForum'],
                ['group_id' => 5, 'permission' => 'tag1.discussion.reply'],
                ['group_id' => 5, 'permission' => 'tag1.discussion.authorModerationEditPosts'],
                ['group_id' => 5, 'permission' => 'tag1.discussion.authorModerationHidePosts'],
                ['group_id' => 5, 'permission' => 'tag1.discussion.authorModerationRestorePosts'],
            ],
        ]);
        $this->assertSuccess($this->update('posts', 2, ['content' => 'Tag-specific permission']));
        $this->assertSuccess($this->update('posts', 2, ['isHidden' => true]));
        $response = $this->send($this->request('GET', '/api/discussions/1', ['authenticatedAs' => 2]));
        $this->assertSuccess($response);
        $body = json_decode((string) $response->getBody(), true);
        $this->assertContains('2', array_column($body['data']['relationships']['posts']['data'], 'id'));
        $this->assertSuccess($this->update('posts', 2, ['isHidden' => false]));
    }

    #[Test]
    public function standard_moderation_actions_are_audited_without_duplicate_custom_records(): void
    {
        $this->extension('flarum-audit');
        $this->grant('authorModerationEditPosts', 'authorModerationHidePosts', 'authorModerationRestorePosts');
        $this->assertSuccess($this->update('posts', 2, ['content' => 'Audited edit']));
        $this->assertSuccess($this->update('posts', 2, ['isHidden' => true]));
        $this->assertSuccess($this->update('posts', 2, ['isHidden' => false]));
        foreach (['post.revised', 'post.hidden', 'post.restored'] as $action) {
            $this->assertSame(1, $this->database()->table('audit_log')->where('action', $action)->count());
        }
    }

    #[Test]
    public function disabled_tags_do_not_turn_old_tag_permissions_into_hidden_reply_access(): void
    {
        $this->prepareDatabase(['group_permission' => [
            ['group_id' => 5, 'permission' => 'tag1.discussion.authorModerationRestorePosts'],
        ]]);
        $this->app();
        $this->database()->table('posts')->where('id', 2)->update(['hidden_at' => Carbon::now(), 'hidden_user_id' => 1]);
        $this->assertRejected($this->send($this->request('GET', '/api/posts/2', ['authenticatedAs' => 2])));
    }

    #[Test]
    public function warning_threshold_does_not_revoke_a_separate_staff_permission(): void
    {
        $this->extension('fof-moderator-warnings');
        $this->grant('authorModerationEditPosts', 'editPosts');
        $this->setting('huseyinfiliz-author-moderation.warning_limit_enabled', '1');
        $this->prepareDatabase(['warnings' => [
            ['id' => 1, 'user_id' => 2, 'strikes' => 5, 'created_at' => Carbon::now(), 'public_comment' => '<t>Warning</t>'],
        ]]);
        $this->assertSuccess($this->update('posts', 4, ['content' => 'Independent staff permission']));
    }

    #[Test]
    public function hidden_group_exemption_is_preserved_in_serialized_capabilities(): void
    {
        $this->grant('authorModerationEditPosts', 'authorModerationHidePosts');
        $this->app();
        $this->database()->table('groups')->where('id', 6)->update(['is_hidden' => true]);

        $response = $this->send($this->request('GET', '/api/posts/4', ['authenticatedAs' => 2]));
        $this->assertSuccess($response);
        $body = json_decode((string) $response->getBody(), true);
        $this->assertFalse($body['data']['attributes']['canEdit']);
        $this->assertFalse($body['data']['attributes']['canHide']);
        $this->assertArrayNotHasKey('content', $body['data']['attributes']);
        $this->assertRejected($this->update('posts', 4, ['content' => 'Hidden group must remain exempt']));
        $this->assertNotContains('6', array_column(array_filter($body['included'] ?? [], fn ($row) => $row['type'] === 'groups'), 'id'));
    }

    #[Test]
    public function mixed_patch_is_rejected_before_any_authorized_field_is_changed(): void
    {
        $this->grant('authorModerationEditPosts');
        foreach ([['content' => 'No partial edit', 'isHidden' => true], ['isHidden' => true, 'content' => 'No partial edit']] as $attributes) {
            $this->assertRejected($this->update('posts', 2, $attributes));
            $this->assertStringContainsString('Original', Post::findOrFail(2)->content);
            $this->assertNull(Post::findOrFail(2)->hidden_at);
        }
    }

    #[Test]
    public function unconfirmed_author_loses_additional_grants(): void
    {
        $this->grant('authorModerationEditPosts', 'authorModerationHidePosts');
        $this->app();
        $this->database()->table('users')->where('id', 2)->update(['is_email_confirmed' => false]);
        $this->assertRejected($this->update('posts', 2, ['content' => 'Unconfirmed']));
        $this->assertRejected($this->update('posts', 2, ['isHidden' => true]));
    }

    #[Test]
    public function private_parent_discussion_does_not_leak_hidden_posts_through_includes(): void
    {
        $this->grant('authorModerationEditPosts', 'authorModerationRestorePosts');
        $this->app();
        $this->database()->table('discussions')->where('id', 1)->update(['is_private' => true]);
        $this->database()->table('posts')->where('id', 2)->update(['hidden_at' => Carbon::now(), 'hidden_user_id' => 2]);
        $this->assertRejected($this->send($this->request('GET', '/api/posts/2', ['authenticatedAs' => 2])->withQueryParams(['include' => 'discussion'])));
        $response = $this->send($this->request('GET', '/api/posts', ['authenticatedAs' => 2])->withQueryParams(['filter' => ['discussion' => 1]]));
        $this->assertSuccess($response);
        $this->assertSame([], json_decode((string) $response->getBody(), true)['data']);
    }

    #[Test]
    public function internal_authority_groups_cannot_be_included_or_written_through_the_api(): void
    {
        $this->grant('authorModerationEditPosts');
        foreach ([['/api/users/4', 'authorModerationGroups'], ['/api/posts/4', 'user.authorModerationGroups']] as [$path, $include]) {
            $response = $this->send($this->request('GET', $path, ['authenticatedAs' => 2])->withQueryParams(['include' => $include]));
            $this->assertContains($response->getStatusCode(), [400, 403, 422], (string) $response->getBody());
        }

        $response = $this->send($this->request('PATCH', '/api/users/2', [
            'authenticatedAs' => 2,
            'json' => ['data' => ['type' => 'users', 'id' => '2', 'relationships' => [
                'authorModerationGroups' => ['data' => [['type' => 'groups', 'id' => '1']]],
            ]]],
        ]));
        $this->assertContains($response->getStatusCode(), [400, 403, 422], (string) $response->getBody());
        $this->assertFalse(User::findOrFail(2)->isAdmin());
    }
}
