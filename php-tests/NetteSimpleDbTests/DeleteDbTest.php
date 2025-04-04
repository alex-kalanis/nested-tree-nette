<?php

namespace Tests\NetteSimpleDbTests;

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'bootstrap.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'AbstractSimpleDbTests.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'NetteSupport' . DIRECTORY_SEPARATOR . 'NamesAsArrayTrait.php';

use kalanis\nested_tree\Support;
use Tester\Assert;
use Tests\NetteSupport\NamesAsArrayTrait;

class DeleteDbTest extends AbstractSimpleDbTests
{
    use NamesAsArrayTrait;

    /**
     * Test delete selected item with its children.
     */
    public function testDeleteWithChildren() : void
    {
        $this->dataRefill();
        $this->nestedSet->rebuild();

        // test to make sure that the data has been built correctly.
        $options = new Support\Options();
        $options->currentId = 16;
        $options->unlimited = true;
        $options->additionalColumns = ['ANY_VALUE(parent.name)', 'ANY_VALUE(child.name) as name'];
        $result = $this->nestedSet->getNodesWithChildren($options);

        $resultNames = $this->getNamesAsArray($result);
        Assert::count(4, $result);
        Assert::true(empty(array_diff(['3.2', '3.2.1', '3.2.2', '3.2.3'], $resultNames)));
        Assert::equal(count($result), count($resultNames));

        $options = new Support\Options();
        $options->unlimited = true;
        $resultBeforeDelete = $this->nestedSet->listNodesFlatten($options);
        $deleteResult = $this->nestedSet->deleteWithChildren(16);
        $this->nestedSet->rebuild();
        $resultAfterDelete = $this->nestedSet->listNodesFlatten($options);

        Assert::count(20, $resultBeforeDelete);
        Assert::equal(4, $deleteResult);
        Assert::count(16, $resultAfterDelete);
    }

    public function testDeletePullUpChildren() : void
    {
        $this->dataRefill();
        $this->nestedSet->rebuild();

        $options = new Support\Options();
        $options->unlimited = true;
        $options->additionalColumns = ['parent.name'];
        $resultBeforeDelete = $this->nestedSet->listNodesFlatten($options);
        $deleteResult = $this->nestedSet->deletePullUpChildren(9);
        $this->nestedSet->rebuild();
        $resultAfterDelete = $this->nestedSet->listNodesFlatten($options);

        Assert::count(20, $resultBeforeDelete);
        Assert::true($deleteResult);
        Assert::count(19, $resultAfterDelete);
    }

    public function testDeleteConditions() : void
    {
        $this->dataRefill();
        $this->nestedSet->rebuild();

        $options = new Support\Options();
        $options->unlimited = true;
        $options->additionalColumns = ['parent.name'];
        $conditions = new Support\Conditions();
        $conditions->query = 'parent.name LIKE ?';
        $conditions->bindValues = ['14.1.%'];
        $options->where = $conditions;
        $resultBeforeDelete = $this->nestedSet->listNodesFlatten($options);
        $deleteResult = $this->nestedSet->deletePullUpChildren(9, $options);
        $this->nestedSet->rebuild();
        $resultAfterDelete = $this->nestedSet->listNodesFlatten($options);

        Assert::count(0, $resultBeforeDelete);
        Assert::false($deleteResult);
        Assert::count(0, $resultAfterDelete);
    }
}

(new DeleteDbTest())->run();
