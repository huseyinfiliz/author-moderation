<?php

namespace HuseyinFiliz\AuthorModeration\Tests\Integration;

use Carbon\Carbon;
use Flarum\Discussion\Discussion;
use Flarum\Post\Post;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\User\Guest;
use Flarum\User\User;
use HuseyinFiliz\AuthorModeration\AuthorOnly;
use HuseyinFiliz\AuthorModeration\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class AuthorOnlyTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extension('huseyinfiliz-author-moderation');
        $this->prepareDatabase([
            User::class => [
                $this->normalUser(),
                array_replace($this->normalUser(), ['id' => 3, 'username' => 'reader', 'email' => 'reader@machine.local']),
            ],
            Discussion::class => [
                ['id' => 1, 'title' => 'Author-only test', 'user_id' => 2, 'comment_count' => 1,
                    'first_post_id' => 1, 'last_post_id' => 1, 'last_post_number' => 1, 'last_posted_user_id' => 2,
                    'created_at' => Carbon::now(), 'last_posted_at' => Carbon::now(), 'is_author_only' => true],
            ],
            Post::class => [
                ['id' => 1, 'discussion_id' => 1, 'user_id' => 2, 'type' => 'comment',
                    'content' => '<t>First post</t>', 'created_at' => Carbon::now()->subMinutes(5), 'number' => 1],
            ],
            'group_permission' => [
                ['permission' => 'discussion.authorModerationJournal', 'group_id' => 3],
            ],
        ]);
    }

    protected function changeMode(int $actor, bool $enabled)
    {
        return $this->send($this->request('PATCH', '/api/discussions/1', [
            'authenticatedAs' => $actor,
            'json' => ['data' => ['type' => 'discussions', 'id' => '1', 'attributes' => ['isAuthorOnly' => $enabled]]],
        ]));
    }

    #[Test]
    public function author_can_toggle_but_another_authorized_member_cannot(): void
    {
        $response = $this->changeMode(2, false);
        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        $this->assertFalse(Discussion::findOrFail(1)->is_author_only);

        $response = $this->changeMode(3, true);
        $this->assertContains($response->getStatusCode(), [403, 422], (string) $response->getBody());
        $this->assertFalse(Discussion::findOrFail(1)->is_author_only);
    }

    #[Test]
    public function replies_are_enforced_at_the_api_and_owner_can_still_reply(): void
    {
        foreach ([3 => 403, 2 => 201] as $actor => $status) {
            $response = $this->send($this->request('POST', '/api/posts', [
                'authenticatedAs' => $actor,
                'json' => ['data' => [
                    'type' => 'posts',
                    'attributes' => ['content' => 'A reply'],
                    'relationships' => ['discussion' => ['data' => ['type' => 'discussions', 'id' => '1']]],
                ]],
            ]));
            $this->assertSame($status, $response->getStatusCode(), (string) $response->getBody());
        }
    }

    #[Test]
    public function bypass_does_not_override_a_lock(): void
    {
        $this->extension('flarum-lock');
        $this->prepareDatabase(['group_permission' => [
            ['permission' => 'huseyinfiliz-author-moderation.replyInAuthorOnly', 'group_id' => 3],
        ]]);
        $this->app();

        $reader = User::findOrFail(3);
        $discussion = Discussion::findOrFail(1);
        $this->assertTrue($reader->can('reply', $discussion));
        $discussion->is_locked = true;
        $discussion->save();
        $this->assertFalse($reader->can('reply', $discussion));
        $this->assertFalse(User::findOrFail(2)->can('reply', $discussion));
    }

    #[Test]
    public function guests_are_restricted_and_read_access_is_unchanged(): void
    {
        $this->app();
        $this->assertFalse((new Guest)->can('reply', Discussion::findOrFail(1)));
        $response = $this->send($this->request('GET', '/api/discussions/1'));
        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
    }

    #[Test]
    public function disabling_the_feature_preserves_the_mode_but_removes_the_restriction(): void
    {
        $this->setting('huseyinfiliz-author-moderation.author_only_enabled', '0');
        $this->app();
        $discussion = Discussion::findOrFail(1);
        $this->assertTrue($discussion->is_author_only);
        $this->assertTrue(User::findOrFail(3)->can('reply', $discussion));
    }

    #[Test]
    public function missing_warnings_extension_does_not_apply_the_condition(): void
    {
        $this->setting('huseyinfiliz-author-moderation.warning_limit_enabled', '1');
        $this->app();
        $this->assertTrue($this->app()->getContainer()->make(AuthorOnly::class)->isActive(Discussion::findOrFail(1)));
    }

    #[Test]
    public function active_warning_points_remove_capabilities_and_retraction_restores_them(): void
    {
        $this->extension('fof-moderator-warnings');
        $this->setting('huseyinfiliz-author-moderation.warning_limit_enabled', '1');
        $this->setting('huseyinfiliz-author-moderation.warning_threshold', '3');
        $this->prepareDatabase(['warnings' => [
            ['id' => 1, 'user_id' => 2, 'strikes' => 2, 'created_at' => Carbon::now(), 'public_comment' => '<t>Warning</t>'],
            ['id' => 2, 'user_id' => 2, 'strikes' => 1, 'created_at' => Carbon::now(), 'public_comment' => '<t>Warning</t>'],
            ['id' => 3, 'user_id' => 2, 'strikes' => 5, 'created_at' => Carbon::now(), 'public_comment' => '<t>Retracted</t>', 'hidden_at' => Carbon::now()],
        ]]);
        $this->app();
        $mode = $this->app()->getContainer()->make(AuthorOnly::class);
        $discussion = Discussion::findOrFail(1);
        $this->assertFalse($mode->canUse(User::findOrFail(2), $discussion));
        $this->assertTrue(User::findOrFail(3)->can('reply', $discussion));
        $this->assertTrue($discussion->is_author_only);

        $this->database()->table('warnings')->where('id', 2)->update(['hidden_at' => Carbon::now()]);
        $this->assertTrue($mode->canUse(User::findOrFail(2), $discussion));
        $this->assertFalse(User::findOrFail(3)->can('reply', $discussion));
    }

    #[Test]
    public function audit_records_a_successful_change_once_and_not_a_noop(): void
    {
        $this->extension('flarum-audit');
        $this->assertSame(200, $this->changeMode(2, false)->getStatusCode());
        $this->assertSame(200, $this->changeMode(2, false)->getStatusCode());
        $this->assertSame(1, $this->database()->table('audit_log')->where('action', 'author_moderation.author_only_changed')->count());
    }

    #[Test]
    public function hidden_group_author_permission_remains_effective_in_discussion_responses(): void
    {
        $this->prepareDatabase([
            'groups' => [['id' => 5, 'name_singular' => 'Hidden author', 'name_plural' => 'Hidden authors', 'is_hidden' => true]],
            'group_user' => [['group_id' => 5, 'user_id' => 2]],
            'group_permission' => [['group_id' => 5, 'permission' => 'discussion.authorModerationJournal']],
        ]);
        $this->app();
        $this->database()->table('group_permission')->where('group_id', 3)->where('permission', 'discussion.authorModerationJournal')->delete();

        $response = $this->send($this->request('GET', '/api/discussions/1', ['authenticatedAs' => 3]));
        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertTrue($body['data']['attributes']['authorOnlyActive']);
        $this->assertFalse($body['data']['attributes']['canReply']);
        $this->assertNotContains('5', array_column(array_filter($body['included'] ?? [], fn ($row) => $row['type'] === 'groups'), 'id'));
    }

    #[Test]
    public function missing_author_suspends_the_stored_reply_restriction(): void
    {
        $this->app();
        $this->database()->table('discussions')->where('id', 1)->update(['user_id' => null]);
        $discussion = Discussion::findOrFail(1);
        $this->assertTrue($discussion->is_author_only);
        $this->assertFalse($this->app()->getContainer()->make(AuthorOnly::class)->isActive($discussion));
        $this->assertTrue(User::findOrFail(3)->can('reply', $discussion));
    }
}
