<?php

namespace Tests\NetteNullDbTests;

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'bootstrap.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'AbstractNullDbTests.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'MockNode.php';

use kalanis\nested_tree\Support\Options;
use kalanis\nested_tree\Support\Search;
use Tester\Assert;

class ListingDbTest extends AbstractNullDbTests
{
    /**
     * Test listing the taxonomy data in hierarchy or flatten styles with many options.
     */
    public function testListTaxonomyFullCount() : void
    {
        $this->dataRefill();
        $this->nestedSet->rebuild();

        // full result test.
        $options = new Options();
        $options->unlimited = true;
        $options->joinChild = true;
        $options->additionalColumns = ['ANY_VALUE(parent.name)', 'ANY_VALUE(child.name) AS name'];

        $result = $this->nestedSet->listNodes($options);
        // assert
        Assert::equal(20, $result->count);
        Assert::count(3, iterator_to_array($result));
        // due to this is nested list (tree list),
        // it is not flatten list then it will be count only root items which there are just 3.
        // (Root 1, Root 2, Root 3.)
    }

    /**
     * Test listing the taxonomy data in hierarchy or flatten styles with many options.
     */
    public function testListTaxonomyOptionsCurrentId() : void
    {
        $this->dataRefill();
        $this->nestedSet->rebuild();

        $options = new Options();
        $options->unlimited = true;
        $options->currentId = 1;

        // tests with options.
        $result = $this->nestedSet->listNodes($options);
        // assert
        Assert::equal(1, $result->count); // with children.
        Assert::count(1, $result->items); // only root items because not flatten.
    }

    /**
     * Test listing the taxonomy data in hierarchy or flatten styles with many options.
     */
    public function testListTaxonomyOptionsParentId() : void
    {
        $this->dataRefill();
        $this->nestedSet->rebuild();

        $options = new Options();
        $options->unlimited = true;
        $options->parentId = 2;
        $options->additionalColumns = ['ANY_VALUE(parent.name)', 'ANY_VALUE(parent.left)', 'ANY_VALUE(child.name)'];

        $result = $this->nestedSet->listNodes($options);

        // assert
        Assert::equal(11, $result->count); // with children.
        Assert::count(5, $result->items); // only root items because not flatten.
    }

    /**
     * Test listing the taxonomy data in hierarchy or flatten styles with many options.
     */
    public function testListTaxonomyOptionsSearchSimple() : void
    {
        $this->dataRefill();
        $this->nestedSet->rebuild();

        $options = new Options();
        $options->unlimited = true;
        $options->additionalColumns = ['ANY_VALUE(parent.name)', 'ANY_VALUE(child.name)'];
        $optionSearch = new Search();
        $optionSearch->columns = ['name'];
        $optionSearch->value = '3.';
        $options->search = $optionSearch;
        $result = $this->nestedSet->listNodes($options);

        // assert
        Assert::equal(6, $result->count); // with children.
        Assert::count(3, $result->items); // only root items because not flatten.
    }

    /**
     * Test listing the taxonomy data in hierarchy or flatten styles with many options.
     */
    public function testListTaxonomyOptionsSearchInEitherColumn() : void
    {
        $this->dataRefill();
        $this->nestedSet->rebuild();

        $options = new Options();
        $options->unlimited = true;
        $options->additionalColumns = ['ANY_VALUE(parent.name)', 'ANY_VALUE(child.name)'];
        $optionSearch = new Search();
        $optionSearch->columns = ['name', 'position'];
        $optionSearch->value = '3.';
        $options->search = $optionSearch;
        $result = $this->nestedSet->listNodes($options);

        // assert
        Assert::equal(6, $result->count); // with children.
        Assert::count(3, $result->items); // only root items because not flatten.
    }

    /**
     * Test listing the taxonomy data in hierarchy or flatten styles with many options.
     */
    public function testListTaxonomyOptionsFilterByMore() : void
    {
        $this->dataRefill();
        $this->nestedSet->rebuild();

        $options = new Options();
        $options->unlimited = true;
        $options->filterIdBy = [1, 5, 6, 15, 99];
        $options->joinChild = true;
        $options->additionalColumns = ['ANY_VALUE(child.name) AS name'];
        $result = $this->nestedSet->listNodes($options);

        // assert
        Assert::equal(4, $result->count); // with children.
        Assert::count(4, $result->items); // only root items because not flatten.
    }

    /**
     * Test listing the taxonomy data in hierarchy or flatten styles with many options.
     */
    public function testListTaxonomyOptionsFilterByMoreString() : void
    {
        $this->dataRefill();
        $this->nestedSet->rebuild();

        $options = new Options();
        $options->unlimited = true;
        $options->filterIdBy = [1, 5, 6, '15', '99', new \stdClass(), 'not-a-number'];
        $options->joinChild = true;
        $options->additionalColumns = ['ANY_VALUE(child.name) AS name'];
        $result = $this->nestedSet->listNodes($options);

        // assert
        Assert::equal(4, $result->count); // with children.
        Assert::count(4, $result->items); // only root items because not flatten.
    }

    /**
     * Test listing the taxonomy data in hierarchy or flatten styles with many options.
     */
    public function testListTaxonomyOptionsNoSortOrder() : void
    {
        $this->dataRefill();
        $this->nestedSet->rebuild();

        $options = new Options();
        $options->unlimited = true;
        $options->noSortOrder = true;
        $options->joinChild = true;
        $options->additionalColumns = ['ANY_VALUE(child.name) AS name'];
        $result = $this->nestedSet->listNodes($options);

        // assert
        Assert::equal(20, $result->count); // with children.
        Assert::count(3, $result->items); // only root items because not flatten.
    }

    /**
     * Sub test of `testListTaxonomy()` but expect (assert) the children that will be generated from `NestedSet->listTaxonomyBuildTreeWithChildren()`.
     */
    public function testListTaxonomyExpectChildren() : void
    {
        $this->dataRefill();
        $this->nestedSet->rebuild();

        $options = new Options();
        $options->unlimited = true;
        $options->joinChild = true;
        $options->additionalColumns = ['ANY_VALUE(child.name) AS name'];
        $result = $this->nestedSet->listNodes($options);

        Assert::true(isset($result->items[1]) && is_object($result->items[1]));
        Assert::equal(2, $result->items[1]->id);
        Assert::true(isset($result->items[1]->childrenNodes[0]) && is_object($result->items[1]->childrenNodes[0]));
        Assert::equal(4, $result->items[1]->childrenNodes[0]->id);
    }

    /**
     * Test listing the taxonomy data in flatten style.
     */
    public function testListTaxonomyFlatten() : void
    {
        $this->dataRefill();
        $this->nestedSet->rebuild();

        $options = new Options();
        $options->unlimited = true;
        $options->joinChild = true;
        $options->additionalColumns = ['ANY_VALUE(child.name) AS name'];

        $list_txn = $this->nestedSet->listNodesFlatten($options);

        // assert
        Assert::equal(20, $list_txn->count);
        Assert::count(20, $list_txn->items); // due to this is flatten list, it will be count all items that were fetched which there are 20 items.
    }
}

(new ListingDbTest())->run();
