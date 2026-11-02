<?php

namespace HuseyinFiliz\AuthorModeration\Api;

use Flarum\Api\Context;
use Flarum\Api\Endpoint\Endpoint;
use Flarum\Api\Resource\AbstractResource;
use Flarum\Api\Resource\PostResource;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use SplObjectStorage;

class PreloadAuthority
{
    public function __invoke(Endpoint $endpoint, AbstractResource $resource): Endpoint
    {
        if ($resource instanceof PostResource) {
            $endpoint->eagerLoad('discussion.user');
        }

        return $endpoint->beforeSerialization(function (Context $context, mixed $data) {
            $pending = $data instanceof Model ? [$data] : ($data['models'] ?? []);
            $pending = $pending instanceof Collection ? $pending->all() : $pending;
            $seen = new SplObjectStorage;
            $users = [];

            while ($model = array_pop($pending)) {
                if (! $model instanceof Model || $seen->contains($model)) {
                    continue;
                }

                $seen->attach($model);

                if ($model instanceof User) {
                    $users[] = $model;

                    continue;
                }

                // Traverse only relations core already authorized and loaded.
                // Do not fetch every post just to prepare an ID-only linkage.
                foreach ($model->getRelations() as $relation) {
                    if ($relation instanceof Model) {
                        $pending[] = $relation;
                    } elseif ($relation instanceof Collection) {
                        array_push($pending, ...$relation->all());
                    }
                }
            }

            // One batched internal relation load across duplicate user instances.
            (new Collection($users))->loadMissing('authorModerationGroups');
        });
    }
}
