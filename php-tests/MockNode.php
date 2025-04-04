<?php

namespace Tests;

use kalanis\nested_tree\Support;

class MockNode extends Support\Node
{
    public string $name = '';

    public static function create(
        int $id,
        ?int $parentId = 0,
        int $left = 0,
        int $right = 0,
        int $level = 0,
        int $position = 0,
        array $children = [],
        string $name = '',
    ) : static {
        $t = new static();
        $t->id = $id;
        $t->parentId = $parentId;
        $t->left = $left;
        $t->right = $right;
        $t->level = $level;
        $t->position = $position;
        $t->childrenIds = $children;
        $t->name = $name;

        return $t;
    }
}
