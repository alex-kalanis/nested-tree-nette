<?php

namespace Tests\NetteExtendedDbTests;

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'bootstrap.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'AbstractExtendedDbTests.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'NetteSupport' . DIRECTORY_SEPARATOR . 'NamesAsArrayTrait.php';

use kalanis\nested_tree\Support;
use Tester\Assert;
use Tests\NetteSupport\NamesAsArrayTrait;

class DeleteDbTest extends AbstractExtendedDbTests
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
        $listOptions->additionalColumns = ['ANY_VALUE(parent.t_name)'];
        $resultBeforeDelete = $this->nestedSet->listNodesFlatten($listOptions);

        $getWithChildrenOptions = new Support\Options();
        $getWithChildrenOptions->currentId = $filter_taxonomy_id;
        $getWithChildrenConditions = new Support\Conditions();
        $getWithChildrenConditions->query = 'parent.t_type = ?';
        $getWithChildrenConditions->bindValues = ['category'];
        $getWithChildrenOptions->where = $getWithChildrenConditions;
        $getWithChildrenOptions->additionalColumns = ['ANY_VALUE(child.t_name) AS t_name'];
        $nodesBeforeDelete = $this->nestedSet->getNodesWithChildren($getWithChildrenOptions);
        $resultTargetBeforeDelete = $this->getNamesAsArray(
            $nodesBeforeDelete,
            't_name'
        );

        $deleteOptions = new Support\Options();
        $deleteConditions = new Support\Conditions();
        $deleteConditions->query = 'parent.t_type = ?';
        $deleteConditions->bindValues = ['category'];
        $deleteOptions->where = $deleteConditions;
        $deleteResult = $this->nestedSet->deleteWithChildren($filter_taxonomy_id, $deleteOptions);
        $this->rebuild();

        $resultAfterDelete = $this->nestedSet->listNodesFlatten($listOptions);
        $resultTargetAfterDelete = $this->nestedSet->getNodesWithChildren($getWithChildrenOptions);

        Assert::true(empty(array_diff(['3.2', '3.2.1', '3.2.2', '3.2.3'], $resultTargetBeforeDelete)));
        Assert::count(32, $resultBeforeDelete);
        Assert::equal(4, $deleteResult);
        Assert::count(0, $resultTargetAfterDelete);
        Assert::count(28, $resultAfterDelete);
    }

    /**
     * Test delete selected item with its children.
     */
    public function testDeleteWithChildrenIncorrectOptions() : void
    {
        $this->reload();

        // test with incorrect where condition
        $filter_taxonomy_id = 28;
        $options = new Support\Options();
        $options->unlimited = true;
        $optionsConditions = new Support\Conditions();
        $optionsConditions->query = 'parent.t_type = ?';
        $optionsConditions->bindValues = ['product-category'];
        $options->where = $optionsConditions;
        $resultBeforeDelete = $this->nestedSet->listNodesFlatten($options);

        $getWithChildrenOptions = new Support\Options();
        $getWithChildrenOptions->currentId = $filter_taxonomy_id;
        $getWithChildrenConditions = new Support\Conditions();
        $getWithChildrenConditions->query = 'parent.t_type = ?';
        $getWithChildrenConditions->bindValues = ['category']; // incorrect, the id 28 should be product-category type.
        $getWithChildrenOptions->where = $getWithChildrenConditions;
        $resultTargetBeforeDelete = $this->nestedSet->getNodesWithChildren($getWithChildrenOptions);

        $deleteOptions = new Support\Options();
        $deleteConditions = new Support\Conditions();
        $deleteConditions->query = 'parent.t_type = ?';
        $deleteConditions->bindValues = ['category'];
        $deleteOptions->where = $deleteConditions;
        $deleteResult = $this->nestedSet->deleteWithChildren($filter_taxonomy_id, $deleteOptions);

        $this->rebuild();
        $resultAfterDelete = $this->nestedSet->listNodesFlatten($options);
        $resultTargetAfterDelete = $this->nestedSet->getNodesWithChildren($getWithChildrenOptions);

        Assert::count(0, $resultTargetBeforeDelete);
        Assert::count(12, $resultBeforeDelete);
        Assert::null($deleteResult); // delete nothing due to incorrect where condition
        Assert::count(0, $resultTargetAfterDelete);
        Assert::count(12, $resultAfterDelete);
    }

    public function testDeletePullUpChildren() : void
    {
        $this->reload();

        // test the same as first table.
        $options = new Support\Options();
        $options->unlimited = true;
        $optionsConditions = new Support\Conditions();
        $optionsConditions->query = 'parent.t_type = ?';
        $optionsConditions->bindValues = ['category'];
        $options->where = $optionsConditions;
        $resultBeforeDelete = $this->nestedSet->listNodesFlatten($options);

        $deleteResult = $this->nestedSet->deletePullUpChildren(9);
        $this->rebuild();
        $resultAfterDelete = $this->nestedSet->listNodesFlatten($options);

        Assert::count(20, $resultBeforeDelete);
        Assert::true($deleteResult);
        Assert::count(19, $resultAfterDelete);
    }

    public function testDeletePullUpChildrenProducts() : void
    {
        $this->reload('product-category');

        // test delete on product-category
        $options = new Support\Options();
        $options->unlimited = true;
        $optionsConditions = new Support\Conditions();
        $optionsConditions->query = 'parent.t_type = ?';
        $optionsConditions->bindValues = ['product-category'];
        $options->where = $optionsConditions;
        $resultBeforeDelete = $this->nestedSet->listNodesFlatten($options);

        $deleteResult = $this->nestedSet->deletePullUpChildren(28); // delete desktop (28). parent of desktop is computer (22).
        $this->rebuild('product-category');
        $resultAfterDelete = $this->nestedSet->listNodesFlatten($options);

        Assert::count(12, $resultBeforeDelete);
        Assert::true($deleteResult);
        Assert::count(11, $resultAfterDelete);

        // test by get some child of deleted item.
        $row = $this->getRow($this->dbExplorer, $this->settings, 30); // get dell (30)
        Assert::equal(22, $row->{$this->settings->parentIdColumnName}); // test that dell's (30) parent is computer (22) now. before delete desktop (28), dell's (30) parent is desktop (28).
    }

    protected function reload(string $type = 'category') : void
    {
        $this->dataRefill();
        $this->rebuild($type);
    }
}

(new DeleteDbTest())->run();
