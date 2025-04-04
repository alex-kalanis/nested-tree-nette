<?php

namespace Tests\NetteExtendedDbTests;

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'bootstrap.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'AbstractExtendedDbTests.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'NetteSupport' . DIRECTORY_SEPARATOR . 'NamesAsArrayTrait.php';

use kalanis\nested_tree\Support\Conditions;
use kalanis\nested_tree\Support\Options;
use Tester\Assert;
use Tests\NetteSupport\NamesAsArrayTrait;

class ReadDbTest extends AbstractExtendedDbTests
{
    use NamesAsArrayTrait;

    /**
     * Test get selected item (or start from selected item but skip it) and look up until root.
     */
    public function testGetTaxonomyWithParentsId() : void
    {
        $this->dataRefill();
        $this->rebuild();

        // test filter taxonomy id option.
        $options = new Options();
        $options->currentId = 13;
        $options->additionalColumns = ['parent.t_name'];
        $result = $this->nestedSet->getNodesWithParents($options);

        $resultNames = $this->getNamesAsArray($result, 't_name');
        Assert::count(4, $result);
        Assert::equal(['Root 2', '2.1', '2.1.1', '2.1.1.2'], $resultNames);
        Assert::equal(count($result), count($resultNames));
    }

    /**
     * Test get selected item (or start from selected item but skip it) and look up until root.
     */
    public function testGetTaxonomyWithParentsWhere() : void
    {
        $this->dataRefill();
        $this->rebuild();

        // test where option.
        $options = new Options();
        $optionsWhere = new Conditions();
        $optionsWhere->query = 'node.t_status = ? AND node.t_type = ?';
        $optionsWhere->bindValues = [0, 'category'];
        $options->where = $optionsWhere;
        $options->joinChild = true;
        $options->additionalColumns = ['ANY_VALUE(node.t_name)', 'ANY_VALUE(parent.t_name) AS t_name'];
        $result = $this->nestedSet->getNodesWithParents($options);

        $resultNames = $this->getNamesAsArray($result, 't_name');
        Assert::count(9, $result);
        Assert::equal(['Root 2', '2.1', '2.1.1', '2.1.1.3', '2.3', '2.4', 'Root 3', '3.2', '3.2.3'], $resultNames);
        Assert::equal(count($result), count($resultNames));
    }

    /**
     * Test get selected item and retrieve its children.
     */
    public function testGetTaxonomyWithChildren() : void
    {
        $this->dataRefill();
        $this->rebuild();

        // test filter taxonomy id option.
        $options = new Options();
        $options->currentId = 4;
        $options->joinChild = true;
        $options->additionalColumns = ['ANY_VALUE(child.t_name) AS t_name', 'ANY_VALUE(parent.t_name)'];
        $optionsWhere = new Conditions();
        $optionsWhere->query = 't_type = ?';
        $optionsWhere->bindValues =  ['category'];
        $options->where = $optionsWhere;
        $optionsWhere->query = 'child.t_type = ?';
        $optionsWhere->bindValues =  ['category'];
        $result = $this->nestedSet->getNodesWithChildren($options);

        $resultNames = $this->getNamesAsArray($result, 't_name');
        Assert::count(7, $result);
        Assert::true(empty(array_diff(['2.1', '2.1.1', '2.1.1.1', '2.1.1.2', '2.1.1.3', '2.1.2', '2.1.3'], $resultNames)));
        Assert::equal(count($result), count($resultNames));
    }
}

(new ReadDbTest())->run();
