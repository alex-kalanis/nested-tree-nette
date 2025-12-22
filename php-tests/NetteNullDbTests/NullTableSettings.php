<?php

namespace Tests\NetteNullDbTests;

use kalanis\nested_tree\Support;

class NullTableSettings extends Support\TableSettings
{
    public string $tableName = 'test_taxonomy_3';

    public bool $rootIsNull = true;
}
