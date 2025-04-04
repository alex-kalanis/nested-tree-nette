<?php

namespace Tests\NetteSimpleDbTests;

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'bootstrap.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'AbstractSimpleDbTests.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'MockNode.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'NetteSupport' . DIRECTORY_SEPARATOR . 'NamesAsArrayTrait.php';

use Tester\Assert;
use Tests\MockNode;

class CreateDbTest extends AbstractSimpleDbTests
{
    /**
     * Test get new position, the `position` value will be use before `INSERT` the data to DB.
     */
    public function testGetNewPosition() : void
    {
        $this->dataRefill();
        Assert::equal(4, $this->nestedSet->getNewPosition(4));
        Assert::equal(6, $this->nestedSet->getNewPosition(2));
    }

    /**
     * Test get the data tree. This is usually for retrieve all the data with less condition.
     *
     * The `getTreeWithChildren()` method will be called automatically while run the `rebuild()` method.
     */
    public function testGetTreeWithChildren() : void
    {
        $this->dataRefill();
        $this->nestedSet->rebuild();
        $result = $this->nestedSet->getTreeWithChildren();
        Assert::count(21, $result);
    }

    /**
     * Test `rebuild()` method. This method must run after `INSERT`, `UPDATE`, or `DELETE` the data in database.<br>
     * It may have to run if the `level`, `left`, `right` data is incorrect.
     */
    public function testRebuild() : void
    {
        $this->dataRefill();
        $this->nestedSet->rebuild();

        // get the result of 3
        $sql = 'SELECT * FROM ' . $this->settings->tableName . ' WHERE ' . $this->settings->idColumnName . ' = ?';
        $row = $this->connection->fetch($sql, 3);
        // assert value must be matched.
        Assert::equal(40, $row->{$this->settings->rightColumnName});
        Assert::equal(1, $row->{$this->settings->levelColumnName});

        // get the result of 10
        $sql = 'SELECT * FROM ' . $this->settings->tableName . ' WHERE ' . $this->settings->idColumnName . ' = ?';
        $row = $this->connection->fetch($sql, 10);

        // assert value must be matched.
        Assert::equal(13, $row->{$this->settings->leftColumnName});
        Assert::equal(14, $row->{$this->settings->rightColumnName});
        Assert::equal(3, $row->{$this->settings->levelColumnName});
    }

    public function testAdd() : void
    {
        $this->dataRefill();
        $this->nestedSet->rebuild();
        $node = $this->nestedSet->add(MockNode::create(99, 0, 536, 412, 65, 58465, [], 'Added One'));
        Assert::true(!empty($node));
        $this->nestedSet->rebuild();
        Assert::equal(21, $node->id);

        $sql = 'SELECT * FROM ' . $this->settings->tableName . ' WHERE ' . $this->settings->idColumnName . ' = ?';
        $row = $this->connection->fetch($sql, $node->id);

        // recalculated
        Assert::equal(41, $row->{$this->settings->leftColumnName});
        Assert::equal(42, $row->{$this->settings->rightColumnName});
        Assert::equal(1, $row->{$this->settings->levelColumnName});
        Assert::equal(4, $row->{$this->settings->positionColumnName});
    }
}

(new CreateDbTest())->run();
