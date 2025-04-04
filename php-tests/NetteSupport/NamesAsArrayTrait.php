<?php

namespace Tests\NetteSupport;

use kalanis\nested_tree\Support\Result;

trait NamesAsArrayTrait
{
    /**
     * Rebuild result array by get the names as 2D array.
     */
    protected function getNamesAsArray(Result $result, string $nameColumn = 'name') : array
    {
        $resultNames = [];
        foreach ($result->items as $row) {
            $resultNames[] = is_object($row) ? $row->{$nameColumn} : $row[$nameColumn];
        }

        return $resultNames;
    }
}
