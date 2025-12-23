<?php

namespace Tests\NetteExtendedDbTests;

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'bootstrap.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'AbstractExtendedDbTests.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'NetteSupport' . DIRECTORY_SEPARATOR . 'NamesAsArrayTrait.php';

use kalanis\nested_tree\Support;
use Tester\Assert;

class CreateDbTest extends AbstractExtendedDbTests
{
    /**
     * Test get the data tree. This is usually for retrieve all the data with less condition.
     *
     * The `getTreeWithChildren()` method will be called automatically while run the `rebuild()` method.
     */
    public function testGetTreeWithChildren() : void
    {
        $this->dataRefill();
        $result = $this->nestedSet->getTreeWithChildren();
        Assert::count(33, $result);

        $option1 = new Support\Options();
        $condition1 = new Support\Conditions();
        $condition1->query = 't_type = ?';
        $condition1->bindValues = ['category'];
        $option1->where = $condition1;
        $result = $this->nestedSet->getTreeWithChildren($option1);
        Assert::count(21, $result);

        $option2 = new Support\Options();
        $condition2 = new Support\Conditions();
        $condition2->query = '(t_type = ? AND t_status = ?)';
        $condition2->bindValues = ['category', 1];
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
        // rebuild where t_type = category.
        $option1 = new Support\Options();
        $condition1 = new Support\Conditions();
        $condition1->query = 't_type = ?';
        $condition1->bindValues = ['category'];
        $option1->where = $condition1;
        $this->nestedSet->rebuild($option1);

        // get the result of 3
        $row = $this->getRow($this->dbExplorer, $this->settings, 3);
        // assert value must be matched.
        Assert::equal(40, $row->{$this->settings->rightColumnName});
        Assert::equal(1, $row->{$this->settings->levelColumnName});

        // get the result of 10
        $row = $this->getRow($this->dbExplorer, $this->settings, 10);
        // assert value must be matched.
        Assert::equal(13, $row->{$this->settings->leftColumnName});
        Assert::equal(14, $row->{$this->settings->rightColumnName});
        Assert::equal(3, $row->{$this->settings->levelColumnName});

        // get the result of 29 (t_type = product_category and it did not yet rebuilt).
        $row = $this->getRow($this->dbExplorer, $this->settings, 29);
        // assert value must be matched.
        Assert::equal(0, $row->{$this->settings->leftColumnName});
        Assert::equal(0, $row->{$this->settings->rightColumnName});
        Assert::equal(0, $row->{$this->settings->levelColumnName});
    }

    /**
     * Test get new position, the `position` value will be use before `INSERT` the data to DB.
     */
    public function testGetNewPosition() : void
    {
        $this->dataRefill();

        $condition = new Support\Conditions();
        $condition->query = 't_type = ?';
        $condition->bindValues = ['category'];
        Assert::equal(4, $this->nestedSet->getNewPosition(4, $condition));
        Assert::equal(4, $this->nestedSet->getNewPosition(16, $condition));
        Assert::equal(1, $this->nestedSet->getNewPosition(777, $condition)); // not known
        Assert::equal(1, $this->nestedSet->getNewPosition(null, $condition)); // root with unknown

        $condition->bindValues = ['product-category'];
        $newPosition = $this->nestedSet->getNewPosition(21, $condition);
        Assert::equal(5, $newPosition);
    }
}

(new CreateDbTest())->run();
