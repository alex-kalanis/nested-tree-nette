<?php

namespace Tests\NetteMultipleMenusTests;

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'bootstrap.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'AbstractMultipleMenusTests.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'MockNode.php';

use kalanis\nested_tree\Support\Conditions;
use kalanis\nested_tree\Support\Options;
use Tester\Assert;

class ListingDbTest extends AbstractMultipleMenusTests
{
    /**
     * Test listing the taxonomy data in hierarchy or flatten styles with many options.
     */
    public function testListTaxonomyFull() : void
    {
        $this->dataRefill();
        $this->rebuild(1);
        $this->rebuild(2);

        // full result test.
        $options = new Options();
        $options->unlimited = true;
        $options->joinChild = true;

        // full result test.
        $result = $this->nestedSet->listNodes($options);

        // assert
        Assert::equal(26, $result->count); // with children, no deleted
        Assert::count(6, $result->items); // only root items because not flatten.
    }

    /**
     * Test listing the taxonomy data in hierarchy or flatten styles with many options.
     */
    public function testListTaxonomyOptions1() : void
    {
        $this->dataRefill();
        $this->rebuild(1);

        // tests with options.
        $options = new Options();
        $options->unlimited = true;
        $options->joinChild = true;
        $optionsWhere = new Conditions();
        $optionsWhere->query = 'ANY_VALUE(child.menu_id) = ?';
        $optionsWhere->bindValues = [1];
        $options->where = $optionsWhere;

        $result = $this->nestedSet->listNodes($options);
        unset($options);
        // assert
        Assert::equal(16, $result->count); // with children, no disabled
        Assert::count(3, $result->items); // only root items because not flatten.
    }

    /**
     * Test listing the taxonomy data in hierarchy or flatten styles with many options.
     */
    public function testListTaxonomyOptions2() : void
    {
        $this->dataRefill();
        $this->rebuild(2);

        // tests with options.
        $options = new Options();
        $options->unlimited = true;
        $options->joinChild = true;
        $optionsWhere = new Conditions();
        $optionsWhere->query = 'ANY_VALUE(child.menu_id) = ?';
        $optionsWhere->bindValues = [2];
        $options->where = $optionsWhere;

        $result = $this->nestedSet->listNodes($options);
        unset($options);
        // assert
        Assert::equal(10, $result->count); // with children.
        Assert::count(3, $result->items); // only root items because not flatten.
    }

    /**
     * Test listing the taxonomy data in flatten style.
     */
    public function testListTaxonomyFlattenSimple() : void
    {
        $this->dataRefill();
        $this->rebuild(1);
        $this->rebuild(2);

        // full result test.
        $options = new Options();
        $options->unlimited = true;
        $options->joinChild = true;
        $result = $this->nestedSet->listNodesFlatten($options);
        unset($options);
        // assert
        Assert::equal(26, $result->count); // with children.
        Assert::count(26, $result->items); // was flatten.
    }

    /**
     * Test listing the taxonomy data in flatten style.
     */
    public function testListTaxonomyFlattenOptions() : void
    {
        $this->dataRefill();
        $this->rebuild(2);

        // full result test.
        $options = new Options();
        $options->unlimited = true;
        $options->joinChild = true;
        $optionsConditions = new Conditions();
        $optionsConditions->query = 'ANY_VALUE(child.menu_id) = ?';
        $optionsConditions->bindValues = [2];
        $options->where = $optionsConditions;

        $result = $this->nestedSet->listNodesFlatten($options);
        unset($options);
        // assert
        Assert::equal(10, $result->count); // with children.
        Assert::count(10, $result->items); // was flatten.
    }
}

(new ListingDbTest())->run();
