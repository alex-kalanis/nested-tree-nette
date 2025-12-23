<?php

namespace Tests\NetteMultipleMenusTests;

use kalanis\nested_tree\Support;

class MultiMenuTableSettings extends Support\TableSettings
{
    public string $tableName = 'test_taxonomy_3';
    public string $idColumnName = 'id';
    public string $parentIdColumnName = 'parent_id';
    public string $leftColumnName = 'left';
    public string $rightColumnName = 'right';
    public string $levelColumnName = 'depth';
    public string $positionColumnName = 'order';

    public ?string $name = null;
}
