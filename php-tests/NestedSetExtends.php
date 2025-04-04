<?php

namespace Tests;

use kalanis\nested_tree\Support\Options;

class NestedSetExtends extends \kalanis\nested_tree\NestedSet
{
    /**
     * {@inheritDoc}
     */
    public function getTreeRebuildChildren(array $array) : array
    {
        return parent::getTreeRebuildChildren($array);
    }

    /**
     * {@inheritDoc}
     */
    public function getTreeWithChildren(Options $options = new Options()) : array
    {
        return parent::getTreeWithChildren($options);
    }

    /**
     * {@inheritDoc}
     */
    public function rebuildGenerateTreeData(array &$array, int $id, int $level, int &$n) : void
    {
        parent::rebuildGenerateTreeData($array, $id, $level, $n);
    }

    /**
     * {@inheritDoc}
     */
    public function rebuildGeneratePositionData(array &$array, int $id, int &$n) : void
    {
        parent::rebuildGeneratePositionData($array, $id, $n);
    }
}
