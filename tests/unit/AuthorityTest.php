<?php

namespace HuseyinFiliz\AuthorModeration\Tests\Unit;

use Flarum\Group\Group;
use Flarum\User\User;
use HuseyinFiliz\AuthorModeration\Authority;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class AuthorityTest extends TestCase
{
    private function user(int $id, int $groupId): User
    {
        $group = new Group;
        $group->setRawAttributes(['id' => $groupId, 'is_hidden' => true], true);
        $user = new User;
        $user->exists = true;
        $user->setConnection('flarum');
        $user->setRawAttributes(['id' => $id, 'is_email_confirmed' => true], true);
        $user->setRelation('groups', new Collection);
        $user->setRelation('authorModerationGroups', new Collection([$group]));

        return $user;
    }

    #[Test]
    public function hidden_authority_groups_do_not_replace_public_groups(): void
    {
        $source = $this->user(10, 20);
        $principal = (new Authority)->forUser($source);

        $this->assertNotSame($source, $principal);
        $this->assertSame(10, $principal->id);
        $this->assertSame('flarum', $principal->getConnectionName());
        $this->assertTrue($principal->exists);
        $this->assertSame([20], $principal->groups->modelKeys());
        $this->assertSame([], $source->groups->modelKeys());
        $this->assertFalse($principal->relationLoaded('authorModerationGroups'));
    }

    #[Test]
    public function duplicate_user_instances_are_not_mistaken_for_the_same_snapshot(): void
    {
        $authority = new Authority;
        $first = $this->user(10, 20);
        $second = $this->user(10, 30);

        $authority->withinRequest(function () use ($authority, $first, $second) {
            $a = $authority->forUser($first);
            $b = $authority->forUser($second);
            $this->assertSame($a, $authority->forUser($first));
            $this->assertNotSame($a, $b);
            $this->assertSame([20], $a->groups->modelKeys());
            $this->assertSame([30], $b->groups->modelKeys());
        });
    }

    #[Test]
    public function nested_request_exception_restores_the_outer_snapshot(): void
    {
        $authority = new Authority;
        $source = $this->user(10, 20);

        $authority->withinRequest(function () use ($authority, $source) {
            $outer = $authority->forUser($source);

            try {
                $authority->withinRequest(function () use ($authority, $source, $outer) {
                    $this->assertNotSame($outer, $authority->forUser($source));
                    throw new RuntimeException('Dummy request failure');
                });
            } catch (RuntimeException $error) {
                $this->assertSame('Dummy request failure', $error->getMessage());
            }

            $this->assertSame($outer, $authority->forUser($source));
        });
    }

    #[Test]
    public function request_and_cli_evaluations_do_not_reuse_previous_authority(): void
    {
        $authority = new Authority;
        $source = $this->user(10, 20);
        $first = $authority->withinRequest(fn () => $authority->forUser($source));
        $source->setRelation('authorModerationGroups', new Collection);
        $source->setRawAttributes(['id' => 10, 'is_email_confirmed' => false], true);
        $second = $authority->withinRequest(fn () => $authority->forUser($source));

        $this->assertNotSame($first, $second);
        $this->assertSame([], $second->groups->modelKeys());
        $this->assertFalse($second->is_email_confirmed);
        $this->assertNotSame($authority->forUser($source), $authority->forUser($source));
    }
}
