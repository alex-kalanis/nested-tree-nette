<?php

namespace Tests\NetteMultipleMenusTests;

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'bootstrap.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'AbstractMultipleMenusTests.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'NetteSupport' . DIRECTORY_SEPARATOR . 'NamesAsArrayTrait.php';

use kalanis\nested_tree\Support;
use Tester\Assert;
use Tests\MockNode;

class CreateDbTest extends AbstractMultipleMenusTests
{
    /**
     * Test get the data tree. This is usually for retrieve all the data with less condition.
     *
     * The `getTreeWithChildren()` method will be called automatically while run the `rebuild()` method.
     */
    public function testGetTreeWithChildren() : void
    {
        $this->dataRefill();
        $this->rebuild(1);
        $this->rebuild(2);
        $result = $this->nestedSet->getTreeWithChildren();
        Assert::count(27, $result);

        $option1 = new Support\Options();
        $condition1 = new Support\Conditions();
        $condition1->query = 'menu_id = ?';
        $condition1->bindValues = [1];
        $option1->where = $condition1;
        $result = $this->nestedSet->getTreeWithChildren($option1);
        Assert::count(17, $result);

        $option2 = new Support\Options();
        $condition2 = new Support\Conditions();
        $condition2->query = '(menu_id = ? AND deleted = ?)';
        $condition2->bindValues = [1, 0];
        $option2->where = $condition2;
        $result = $this->nestedSet->getTreeWithChildren($option2);
        Assert::count(17, $result);
    }

    /**
     * Test `rebuild()` method. This method must run after `INSERT`, `UPDATE`, or `DELETE` the data in database.<br>
     * It may have to run if the `level`, `left`, `right` data is incorrect.
     */
    public function testRebuild() : void
    {
        $this->dataRefill();
        $this->rebuild(1);

        // get the result of 3
        $row = $this->getRow($this->dbExplorer, $this->settings, 3);
        // assert value must be matched.
        Assert::equal(21, $row[$this->settings->leftColumnName]);
        Assert::equal(32, $row[$this->settings->rightColumnName]);
        Assert::equal(1, $row[$this->settings->levelColumnName]);

        // get the result of 10
        $row = $this->getRow($this->dbExplorer, $this->settings, 10);
        // assert value must be matched.
        Assert::equal(11, $row[$this->settings->leftColumnName]);
        Assert::equal(12, $row[$this->settings->rightColumnName]);
        Assert::equal(3, $row[$this->settings->levelColumnName]);

        // get the result of 29 (t_type = product_category and it did not yet rebuilt).
        $row = $this->getRow($this->dbExplorer, $this->settings, 29);
        // assert value must be matched.
        Assert::equal(null, $row[$this->settings->leftColumnName]);
        Assert::equal(null, $row[$this->settings->rightColumnName]);
        Assert::equal(0, $row[$this->settings->levelColumnName]);
    }

    /**
     * Test get new position, the `position` value will be use before `INSERT` the data to DB.
     */
    public function testGetNewPosition() : void
    {
        $this->dataRefill();
        $this->rebuild(1);
        $this->rebuild(2);

        $condition = new Support\Conditions();
        $condition->query = 'menu_id = ?';
        $condition->bindValues = [1];
        Assert::equal(4, $this->nestedSet->getNewPosition(4, $condition));
        Assert::equal(3, $this->nestedSet->getNewPosition(16, $condition)); // there is one deleted - will be skipped
        Assert::equal(4, $this->nestedSet->getNewPosition(777, $condition)); // not known - fails to root
        Assert::equal(4, $this->nestedSet->getNewPosition(null, $condition)); // root with unknown - fails to root

        $condition->bindValues = [2];
        $newPosition = $this->nestedSet->getNewPosition(21, $condition);
        Assert::equal(4, $newPosition);
    }

    public function testAdd() : void
    {
        $this->dataRefill();
        $this->rebuild(1);
        $this->rebuild(2);

        $options = new Support\Options();
        $optionsWhere = new Support\Conditions();
        $optionsWhere->query = 'menu_id = ?';
        $optionsWhere->bindValues = [1];
        $options->where = $optionsWhere;
        $addNode = MockNode::create(99, null, 536, 412, 65, 58465);
        $addNode->menu_id = 1;
        $node = $this->nestedSet->add($addNode, $options);

        Assert::false(empty($node));
        $this->rebuild(1);
        Assert::equal(33, $node->id);

        $row = $this->getRow($this->dbExplorer, $this->settings, $node->id);

        // recalculated
        Assert::equal(33, $row[$this->settings->leftColumnName]);
        Assert::equal(34, $row[$this->settings->rightColumnName]);
        Assert::equal(1, $row[$this->settings->levelColumnName]);
        Assert::equal(4, $row[$this->settings->positionColumnName]);
    }
}

(new CreateDbTest())->run();
