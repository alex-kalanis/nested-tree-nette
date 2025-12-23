<?php

namespace Tests\NetteMultipleMenusTests;

use kalanis\nested_tree\Support;

class MultiMenuSoftDelete extends Support\SoftDelete
{
    public string $columnName = 'deleted';
}
