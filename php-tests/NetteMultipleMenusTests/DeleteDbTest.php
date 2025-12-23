<?php

namespace Tests\NetteMultipleMenusTests;

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'bootstrap.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'AbstractMultipleMenusTests.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'NetteSupport' . DIRECTORY_SEPARATOR . 'NamesAsArrayTrait.php';

use kalanis\nested_tree\Support;
use Tester\Assert;
use Tests\NetteSupport\NamesAsArrayTrait;

class DeleteDbTest extends AbstractMultipleMenusTests
{
    use NamesAsArrayTrait;

    /**
     * Test delete selected item with its children.
     */
    public function testDeleteWithChildren() : void
    {
        $this->reload();

        // test delete with where condition
        $filter_taxonomy_id = 16;
        $listOptions = new Support\Options();
        $listOptions->unlimited = true;
        $resultBeforeDelete = $this->nestedSet->listNodesFlatten($listOptions);

        $getWithChildrenOptions = new Support\Options();
        $getWithChildrenOptions->currentId = $filter_taxonomy_id;
        $getWithChildrenConditions = new Support\Conditions();
        $getWithChildrenConditions->query = 'parent.menu_id = ?';
        $getWithChildrenConditions->bindValues = [1];
        $getWithChildrenOptions->where = $getWithChildrenConditions;

        $deleteOptions = new Support\Options();
        $deleteConditions = new Support\Conditions();
        $deleteConditions->query = 'parent.menu_id = ?';
        $deleteConditions->bindValues = [1];
        $deleteOptions->where = $deleteConditions;
        $deleteResult = $this->nestedSet->deleteWithChildren($filter_taxonomy_id, $deleteOptions);
        $this->rebuild(1);

        $resultAfterDelete = $this->nestedSet->listNodesFlatten($listOptions);
        $resultTargetAfterDelete = $this->nestedSet->getNodesWithChildren($getWithChildrenOptions);

        Assert::count(26, $resultBeforeDelete); // full list without marked entries
        Assert::equal(3, $deleteResult); // node 20 is already considered as "deleted" - not found there; rest is marked now
        Assert::count(0, $resultTargetAfterDelete);
        Assert::count(23, $resultAfterDelete);
    }

    /**
     * Test delete selected item with its children.
     */
    public function testDeleteWithChildrenIncorrectOptions() : void
    {
        $this->reload();
        $this->rebuild(2);

        // test with incorrect where condition
        $filter_taxonomy_id = 28;
        $options = new Support\Options();
        $options->unlimited = true;
        $optionsConditions = new Support\Conditions();
        $optionsConditions->query = 'parent.menu_id = ?';
        $optionsConditions->bindValues = [2];
        $options->where = $optionsConditions;
        $resultBeforeDelete = $this->nestedSet->listNodesFlatten($options);

        $getWithChildrenOptions = new Support\Options();
        $getWithChildrenOptions->currentId = $filter_taxonomy_id;
        $getWithChildrenConditions = new Support\Conditions();
        $getWithChildrenConditions->query = 'parent.menu_id = ?';
        $getWithChildrenConditions->bindValues = [1]; // incorrect, the id 28 should be for menu 2.
        $getWithChildrenOptions->where = $getWithChildrenConditions;
        $resultTargetBeforeDelete = $this->nestedSet->getNodesWithChildren($getWithChildrenOptions);

        $deleteOptions = new Support\Options();
        $deleteConditions = new Support\Conditions();
        $deleteConditions->query = 'parent.menu_id = ?';
        $deleteConditions->bindValues = [1];
        $deleteOptions->where = $deleteConditions;
        $deleteResult = $this->nestedSet->deleteWithChildren($filter_taxonomy_id, $deleteOptions);

        $this->rebuild(1);
        $this->rebuild(2);
        $resultAfterDelete = $this->nestedSet->listNodesFlatten($options);
        $resultTargetAfterDelete = $this->nestedSet->getNodesWithChildren($getWithChildrenOptions);

        Assert::count(0, $resultTargetBeforeDelete);
        Assert::count(10, $resultBeforeDelete);
        Assert::null($deleteResult); // delete nothing due to incorrect where condition
        Assert::count(0, $resultTargetAfterDelete);
        Assert::count(10, $resultAfterDelete);
    }

    public function testDeletePullUpChildren() : void
    {
        $this->reload();

        // test the same as first table.
        $options = new Support\Options();
        $options->unlimited = true;
        $optionsConditions = new Support\Conditions();
        $optionsConditions->query = 'parent.menu_id = ?';
        $optionsConditions->bindValues = [1];
        $options->where = $optionsConditions;
        $resultBeforeDelete = $this->nestedSet->listNodesFlatten($options);

        $deleteResult = $this->nestedSet->deletePullUpChildren(9);
        $this->rebuild(1);
        $resultAfterDelete = $this->nestedSet->listNodesFlatten($options);

        Assert::count(16, $resultBeforeDelete);
        Assert::true($deleteResult);
        Assert::count(15, $resultAfterDelete);
    }

    public function testDeletePullUpChildrenProducts() : void
    {
        $this->reload(2);

        // test delete on product-category
        $options = new Support\Options();
        $options->unlimited = true;
        $optionsConditions = new Support\Conditions();
        $optionsConditions->query = 'parent.menu_id = ?';
        $optionsConditions->bindValues = [2];
        $options->where = $optionsConditions;
        $resultBeforeDelete = $this->nestedSet->listNodesFlatten($options);

        $deleteResult = $this->nestedSet->deletePullUpChildren(28); // delete desktop (28). parent of desktop is computer (22).
        $this->rebuild(2);
        $resultAfterDelete = $this->nestedSet->listNodesFlatten($options);

        Assert::count(10, $resultBeforeDelete);
        Assert::true($deleteResult);
        Assert::count(9, $resultAfterDelete);

        // test by get some child of deleted item.
        $row = $this->getRow($this->dbExplorer, $this->settings, 30);
        Assert::equal(22, $row[$this->settings->parentIdColumnName]); // test that dell's (30) parent is computer (22) now. before delete desktop (28), dell's (30) parent is desktop (28).
    }

    protected function reload(int $menuId = 1) : void
    {
        $this->dataRefill();
        $this->rebuild($menuId);
    }
}

(new DeleteDbTest())->run();
