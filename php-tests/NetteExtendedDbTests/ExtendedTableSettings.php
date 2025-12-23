<?php

namespace Tests\NetteExtendedDbTests;

use kalanis\nested_tree\Support;

class ExtendedTableSettings extends Support\TableSettings
{
    public string $tableName = 'test_taxonomy_2';
    public string $idColumnName = 'tid';
    public string $parentIdColumnName = 'parent_id';
    public string $leftColumnName = 't_left';
    public string $rightColumnName = 't_right';
    public string $levelColumnName = 't_level';
    public string $positionColumnName = 't_position';

    /* this one will be processed as extra */
    public string $name = 't_name';
}
