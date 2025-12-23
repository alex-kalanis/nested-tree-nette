<?php

namespace Tests\NetteSupport;

use kalanis\nested_tree\Support\TableSettings;
use Nette\Database\Explorer;

/**
 * @property Explorer $dbExplorer
 * @property TableSettings $settings
 */
trait DumpTrait
{
    /**
     * Dump trait to get data from DB
     */
    protected function getDbDump(string $conditions = '1=1', array $values = []) : array
    {
        $sql = 'SELECT * FROM `' . $this->settings->tableName . '` WHERE ' . $conditions;
        $rows = $this->dbExplorer->fetchAll($sql, ...$values);
        $entries = [];
        foreach ($rows as $row) {
            $entries[] = (array) $row;
        }

        return $entries;
    }
}
