<?php

namespace Tests\NetteSimpleDbTests;

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'bootstrap.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'AbstractSimpleDbTests.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'MockNode.php';

use kalanis\nested_tree\Support;
use Tester\Assert;
use Tests\MockNode;

class UpdateDbTest extends AbstractSimpleDbTests
{
    /**
     * Test check that selected parent is same level or under its children.
     *
     * This will be use to check before update the data.
     */
    public function testIsNodeParentInItsChildren() : void
    {
        $this->dataRefill();
        $this->nestedSet->rebuild();
        Assert::false($this->nestedSet->isNewParentOutsideCurrentNodeTree(9, 12));
        Assert::false($this->nestedSet->isNewParentOutsideCurrentNodeTree(9, 14));
        Assert::true($this->nestedSet->isNewParentOutsideCurrentNodeTree(9, 4));
        Assert::true($this->nestedSet->isNewParentOutsideCurrentNodeTree(9, 7));
        Assert::true($this->nestedSet->isNewParentOutsideCurrentNodeTree(9, 20));
        Assert::true($this->nestedSet->isNewParentOutsideCurrentNodeTree(9, 0)); // root always
    }

    public function testMoveNodeUp() : void
    {
        $this->dataRefill();
        $this->nestedSet->rebuild();
        $this->nestedSet->move(13, 3);
        $this->nestedSet->rebuild();

        $options = new Support\Options();
        $options->joinChild = true;
        $options->additionalColumns = ['ANY_VALUE(child.name) as name'];
        $where = new Support\Conditions();
        $where->query = 'child.name LIKE ?';
        $where->bindValues = ['2.1.1.%'];
        $options->where = $where;
        $nodes = $this->nestedSet->listNodesFlatten($options);

        $node = reset($nodes->items);
        Assert::equal('2.1.1.1', $node->name);
        Assert::equal(1, $node->position);
        Assert::equal(12, $node->id);
        $node = next($nodes->items);
        Assert::equal('2.1.1.3', $node->name);
        Assert::equal(2, $node->position);
        Assert::equal(14, $node->id);
        $node = next($nodes->items);
        Assert::equal('2.1.1.2', $node->name);
        Assert::equal(3, $node->position);
        Assert::equal(13, $node->id);

        Assert::true(empty(next($nodes->items)));
    }

    public function testMoveNodeDown() : void
    {
        $this->dataRefill();
        $this->nestedSet->rebuild();
        $this->nestedSet->move(14, 2);
        $this->nestedSet->rebuild();

        $options = new Support\Options();
        $options->joinChild = true;
        $options->additionalColumns = ['ANY_VALUE(child.name) as name'];
        $where = new Support\Conditions();
        $where->query = 'child.name LIKE ?';
        $where->bindValues = ['2.1.1.%'];
        $options->where = $where;
        $nodes = $this->nestedSet->listNodesFlatten($options);

        $node = reset($nodes->items);
        Assert::equal('2.1.1.1', $node->name);
        Assert::equal(1, $node->position);
        Assert::equal(12, $node->id);
        $node = next($nodes->items);
        Assert::equal('2.1.1.3', $node->name);
        Assert::equal(2, $node->position);
        Assert::equal(14, $node->id);
        $node = next($nodes->items);
        Assert::equal('2.1.1.2', $node->name);
        Assert::equal(3, $node->position);
        Assert::equal(13, $node->id);

        Assert::true(empty(next($nodes->items)));
    }

    public function testChangeNodeParent() : void
    {
        $this->dataRefill();
        $this->nestedSet->rebuild();
        Assert::true($this->nestedSet->changeParent(13, 16));
        $this->nestedSet->rebuild();
        Assert::false($this->nestedSet->changeParent(4, 12));

        $options = new Support\Options();
        $options->joinChild = true;
        $options->additionalColumns = ['ANY_VALUE(child.name) as name'];
        $where = new Support\Conditions();
        $where->query = 'child.parent_id = ?';
        $options->where = $where;

        // old group
        $where->bindValues = [9];
        $nodes = $this->nestedSet->listNodesFlatten($options);

        $node = reset($nodes->items);
        Assert::equal('2.1.1.1', $node->name);
        Assert::equal(12, $node->id);
        Assert::equal(1, $node->position);
        $node = next($nodes->items);
        Assert::equal('2.1.1.3', $node->name);
        Assert::equal(14, $node->id);
        Assert::equal(2, $node->position);

        Assert::true(empty(next($nodes->items)));

        // new group
        $where->bindValues = [16];
        $nodes = $this->nestedSet->listNodesFlatten($options);

        $node = reset($nodes->items);
        Assert::equal('3.2.1', $node->name);
        Assert::equal(18, $node->id);
        Assert::equal(1, $node->position);
        $node = next($nodes->items);
        Assert::equal('3.2.2', $node->name);
        Assert::equal(19, $node->id);
        Assert::equal(2, $node->position);
        $node = next($nodes->items);
        Assert::equal('3.2.3', $node->name);
        Assert::equal(20, $node->id);
        Assert::equal(3, $node->position);
        $node = next($nodes->items);
        Assert::equal('2.1.1.2', $node->name);
        Assert::equal(13, $node->id);
        Assert::equal(4, $node->position);

        Assert::true(empty(next($nodes->items)));
    }

    public function testMoveNoEntry() : void
    {
        $this->dataRefill();
        $this->nestedSet->rebuild();

        $extraOptions = new Support\Options();
        $extraOptions->joinChild = true;
        $extraOptions->additionalColumns = ['ANY_VALUE(child.name) as name'];
        $extraWhere = new Support\Conditions();
        $extraWhere->query = 'child.name LIKE ?';
        $extraWhere->bindValues = ['14.1.%'];
        $extraOptions->where = $extraWhere;

        Assert::false($this->nestedSet->move(22, 2, $extraOptions));
    }

    public function testMoveNoConditions() : void
    {
        $this->dataRefill();
        $this->nestedSet->rebuild();

        $options = new Support\Options();
        $options->joinChild = true;
        $options->additionalColumns = ['ANY_VALUE(child.name) as name'];
        $conditions = new Support\Conditions();
        $conditions->query = 'child.name LIKE ?';
        $conditions->bindValues = ['14.1.%'];
        $options->where = $conditions;

        Assert::false($this->nestedSet->move(15, 12, $options));
    }

    public function testUpdateData() : void
    {
        $this->dataRefill();
        $this->nestedSet->rebuild();

        $node = MockNode::create(14, name: 'Mop Update');
        Assert::true($this->nestedSet->update($node));

        $sql = 'SELECT * FROM ' . $this->settings->tableName . ' WHERE ' . $this->settings->idColumnName . ' = ?';
        $row = $this->connection->fetch($sql, 14);

        // updated
        Assert::equal(14, $row->{$this->settings->idColumnName});
        Assert::equal(9, $row->{$this->settings->parentIdColumnName});
        Assert::equal(3, $row->{$this->settings->positionColumnName});
        Assert::equal('Mop Update', $row['name']);
    }
}

(new UpdateDbTest())->run();
