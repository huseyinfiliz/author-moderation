<?php

use Flarum\Database\Migration;

return Migration::addColumns('discussions', [
    'is_author_only' => ['boolean', 'default' => false],
]);
