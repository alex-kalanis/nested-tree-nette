<?php

namespace Tests\NetteExtendedDbTests;

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'bootstrap.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'AbstractExtendedDbTests.php';

use kalanis\nested_tree\Support;
use Tester\Assert;
use Tests\MockNode;

class UpdateDbTest extends AbstractExtendedDbTests
{
    /**
     * Test check that selected parent is same level or under its children.
     *
     * This will be use to check before update the data.
     *
     * @return void
     */
    public function testIsNodeParentInItsChildren() : void
    {
        $this->dataRefill();
        $categoryOption = new Support\Options();
        $categoryCondition = new Support\Conditions();
        $categoryCondition->query = 't_type = ?';
        $categoryCondition->bindValues = ['category'];
        $categoryOption->additionalColumns = ['node.t_type'];
        $categoryOption->where = $categoryCondition;
        $this->nestedSet->rebuild($categoryOption);

        $categoryCondition->query = 'node.t_type = ?';
        Assert::false($this->nestedSet->isNewParentOutsideCurrentNodeTree(
            9, // 2.1.1 (9)
            12, // shouldn't under 2.1.1.1 (12)
            $categoryOption
        ));
        Assert::true($this->nestedSet->isNewParentOutsideCurrentNodeTree(
            9, // 2.1.1 (9)
            20, // is okay to be under 3.2.3 (20 - will be new parent)
            $categoryOption
        ));

        Assert::true($this->nestedSet->isNewParentOutsideCurrentNodeTree(
            19, // 3.2.2
            16, // is under 3.2
            $categoryOption
        ));

        $categoryCondition->bindValues = ['product-category'];
        // test search not found because incorrect `t_type` (must be return `true`).
        Assert::false($this->nestedSet->isNewParentOutsideCurrentNodeTree(
            19,
            16,
            $categoryOption
        ));

        Assert::false($this->nestedSet->isNewParentOutsideCurrentNodeTree(
            21, // camera (21)
            25, // shouldn't under nikon (25)
            $categoryOption
        ));
        // test multiple level children.
        Assert::true($this->nestedSet->isNewParentOutsideCurrentNodeTree(
            30, // dell
            22, // is under desktop (28) > and desktop is under computer (22)
            $categoryOption
        ));
    }

    public function testMoveNodeUp() : void
    {
        $this->dataRefill();
        $categoryOption = $this->getOptions();
        $this->nestedSet->rebuild($categoryOption);
        $this->nestedSet->move(13, 3, $categoryOption);
        $this->nestedSet->rebuild($categoryOption);

        $options = new Support\Options();
        $options->joinChild = true;
        $options->additionalColumns = ['ANY_VALUE(child.`t_name`) as name', 'child.`t_type`'];
        $where = new Support\Conditions();
        $where->query = 'child.`t_name` LIKE ? AND child.`t_type` = ?';
        $where->bindValues = ['2.1.1.%', 'category'];
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
        $categoryOption = $this->getOptions();
        $this->nestedSet->rebuild($categoryOption);
        $this->nestedSet->move(14, 2, $categoryOption);
        $this->nestedSet->rebuild($categoryOption);

        $options = new Support\Options();
        $options->joinChild = true;
        $options->additionalColumns = ['ANY_VALUE(child.`t_name`) as name', 'child.`t_type`'];
        $where = new Support\Conditions();
        $where->query = 'child.`t_name` LIKE ? AND child.`t_type` = ?';
        $where->bindValues = ['2.1.1.%', 'category'];
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
        $categoryOption = $this->getOptions();
        $categoryTypedOption = $this->getTypedOptions();
        $this->nestedSet->rebuild($categoryOption);
        Assert::true($this->nestedSet->changeParent(13, 16, $categoryTypedOption));
        $this->nestedSet->rebuild($categoryOption);
        Assert::false($this->nestedSet->changeParent(4, 12, $categoryTypedOption));

        $options = new Support\Options();
        $options->joinChild = true;
        $options->additionalColumns = ['ANY_VALUE(child.`t_name`) as name', 'child.`t_type`'];
        $where = new Support\Conditions();
        $where->query = 'child.`parent_id` = ? AND child.`t_type` = ?';
        $options->where = $where;

        // old group
        $where->bindValues = [9, 'category'];
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
        $where->bindValues = [16, 'category'];
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
        $categoryOption = $this->getOptions();
        $this->nestedSet->rebuild($categoryOption);

        $options = new Support\Options();
        $options->joinChild = true;
        $options->additionalColumns = ['ANY_VALUE(child.`t_name`) as name', 'child.`t_type`'];
        $where = new Support\Conditions();
        $where->query = 'child.t_name LIKE ? AND node.`t_type` = ?';
        $where->bindValues = ['14.1.%', 'category'];
        $options->where = $where;

        Assert::false($this->nestedSet->move(22, 2, $options));
    }

    public function testMoveNoConditionsMet() : void
    {
        $this->dataRefill();
        $categoryOption = $this->getOptions();
        $this->nestedSet->rebuild($categoryOption);

        $options = new Support\Options();
        $options->joinChild = true;
        $options->additionalColumns = ['ANY_VALUE(child.`t_name`) as name', 'child.`t_type`'];
        $where = new Support\Conditions();
        $where->query = 'child.`t_type` = ?';
        $where->bindValues = ['category'];
        $options->where = $where;

        Assert::true($this->nestedSet->move(15, 12, $options));
    }

    public function testUpdateData() : void
    {
        $this->dataRefill();
        $categoryOption = $this->getOptions();
        $categoryTypedOption = $this->getTypedOptions();
        $this->nestedSet->rebuild($categoryOption);

        $node = MockNode::create(14, name: 'Mop Update');
        Assert::true($this->nestedSet->update($node, $categoryTypedOption));

        $row = $this->getRow($this->dbExplorer, $this->settings, 14);

        // updated
        Assert::equal(14, $row[$this->settings->idColumnName]);
        Assert::equal(9, $row[$this->settings->parentIdColumnName]);
        Assert::equal(3, $row[$this->settings->positionColumnName]);
        Assert::equal('Mop Update', $row['t_name']);
    }

    public function testMoveWithoutChildren() : void
    {
        $this->dataRefill();
        $categoryOption = $this->getOptions();
        $categoryTypedOption = $this->getTypedOptions();
        $this->nestedSet->rebuild($categoryOption);

        Assert::true($this->nestedSet->move(12, 3, $categoryTypedOption));
        $this->nestedSet->rebuild($categoryOption);

        // check nodes
        $options = new Support\Options();
        $options->joinChild = true;
        $options->additionalColumns = ['ANY_VALUE(child.`t_name`) as name', 'child.`t_type`'];
        $where = new Support\Conditions();
        $where->query = 'child.`t_type` = ?';
        $where->bindValues = ['category'];
        $options->where = $where;
        $nodes = $this->nestedSet->listNodesFlatten($options);
        usort($nodes->items, fn (Support\Node $node1, Support\Node $node2) : int => $node1->id <=> $node2->id);

        $node = reset($nodes->items);
        Assert::equal('Root 1', $node->name);
        Assert::equal(1, $node->left);
        Assert::equal(2, $node->right);
        $node = next($nodes->items);
        Assert::equal('Root 2', $node->name);
        Assert::equal(3, $node->left);
        Assert::equal(26, $node->right);
        $node = next($nodes->items);
        Assert::equal('Root 3', $node->name);
        Assert::equal(27, $node->left);
        Assert::equal(40, $node->right);
        $node = next($nodes->items);
        Assert::equal('2.1', $node->name);
        Assert::equal(4, $node->left);
        Assert::equal(17, $node->right);
        $node = next($nodes->items);
        Assert::equal('2.2', $node->name);
        Assert::equal(18, $node->left);
        Assert::equal(19, $node->right);
        $node = next($nodes->items);
        Assert::equal('2.3', $node->name);
        Assert::equal(20, $node->left);
        Assert::equal(21, $node->right);
        $node = next($nodes->items);
        Assert::equal('2.4', $node->name);
        Assert::equal(22, $node->left);
        Assert::equal(23, $node->right);
        $node = next($nodes->items);
        Assert::equal('2.5', $node->name);
        Assert::equal(24, $node->left);
        Assert::equal(25, $node->right);
        // base
        $node = next($nodes->items);
        Assert::equal('2.1.1', $node->name);
        Assert::equal(5, $node->left);
        Assert::equal(12, $node->right);
        $node = next($nodes->items);
        Assert::equal('2.1.2', $node->name);
        Assert::equal(13, $node->left);
        Assert::equal(14, $node->right);
        $node = next($nodes->items);
        Assert::equal('2.1.3', $node->name);
        Assert::equal(15, $node->left);
        Assert::equal(16, $node->right);
        // moved items
        $node = next($nodes->items);
        Assert::equal('2.1.1.1', $node->name);
        Assert::equal(3, $node->position);
        Assert::equal(10, $node->left);
        Assert::equal(11, $node->right);
        $node = next($nodes->items);
        Assert::equal('2.1.1.2', $node->name);
        Assert::equal(1, $node->position);
        Assert::equal(6, $node->left);
        Assert::equal(7, $node->right);
        $node = next($nodes->items);
        Assert::equal('2.1.1.3', $node->name);
        Assert::equal(2, $node->position);
        Assert::equal(8, $node->left);
        Assert::equal(9, $node->right);
        // no change
        $node = next($nodes->items);
        Assert::equal('3.1', $node->name);
        Assert::equal(28, $node->left);
        Assert::equal(29, $node->right);
        $node = next($nodes->items);
        Assert::equal('3.2', $node->name);
        Assert::equal(30, $node->left);
        Assert::equal(37, $node->right);
        $node = next($nodes->items);
        Assert::equal('3.3', $node->name);
        Assert::equal(38, $node->left);
        Assert::equal(39, $node->right);
        $node = next($nodes->items);
        Assert::equal('3.2.1', $node->name);
        Assert::equal(31, $node->left);
        Assert::equal(32, $node->right);
        $node = next($nodes->items);
        Assert::equal('3.2.2', $node->name);
        Assert::equal(33, $node->left);
        Assert::equal(34, $node->right);
        $node = next($nodes->items);
        Assert::equal('3.2.3', $node->name);
        Assert::equal(35, $node->left);
        Assert::equal(36, $node->right);
        Assert::true(empty(next($nodes->items)));
    }

    public function testMoveWithChildrenNoAnotherChildren() : void
    {
        $this->dataRefill();
        $categoryOption = $this->getOptions();
        $categoryTypedOption = $this->getTypedOptions();
        $this->nestedSet->rebuild($categoryOption);

        Assert::true($this->nestedSet->move(9, 3, $categoryTypedOption));
        $this->nestedSet->rebuild($categoryOption);

        // check nodes
        $options = new Support\Options();
        $options->joinChild = true;
        $options->additionalColumns = ['ANY_VALUE(child.`t_name`) as name', 'child.`t_type`'];
        $where = new Support\Conditions();
        $where->query = 'child.`t_type` = ?';
        $where->bindValues = ['category'];
        $options->where = $where;
        $nodes = $this->nestedSet->listNodesFlatten($options);
        usort($nodes->items, fn (Support\Node $node1, Support\Node $node2) : int => $node1->id <=> $node2->id);

        $node = reset($nodes->items);
        Assert::equal('Root 1', $node->name);
        Assert::equal(1, $node->left);
        Assert::equal(2, $node->right);
        $node = next($nodes->items);
        Assert::equal('Root 2', $node->name);
        Assert::equal(3, $node->left);
        Assert::equal(26, $node->right);
        $node = next($nodes->items);
        Assert::equal('Root 3', $node->name);
        Assert::equal(27, $node->left);
        Assert::equal(40, $node->right);
        $node = next($nodes->items);
        // base
        Assert::equal('2.1', $node->name);
        Assert::equal(4, $node->left);
        Assert::equal(17, $node->right);
        $node = next($nodes->items);
        Assert::equal('2.2', $node->name);
        Assert::equal(18, $node->left);
        Assert::equal(19, $node->right);
        $node = next($nodes->items);
        Assert::equal('2.3', $node->name);
        Assert::equal(20, $node->left);
        Assert::equal(21, $node->right);
        $node = next($nodes->items);
        Assert::equal('2.4', $node->name);
        Assert::equal(22, $node->left);
        Assert::equal(23, $node->right);
        $node = next($nodes->items);
        Assert::equal('2.5', $node->name);
        Assert::equal(24, $node->left);
        Assert::equal(25, $node->right);
        $node = next($nodes->items);
        // moved items
        Assert::equal('2.1.1', $node->name);
        Assert::equal(3, $node->position);
        Assert::equal(9, $node->left);
        Assert::equal(16, $node->right);
        $node = next($nodes->items);
        Assert::equal('2.1.2', $node->name);
        Assert::equal(1, $node->position);
        Assert::equal(5, $node->left);
        Assert::equal(6, $node->right);
        $node = next($nodes->items);
        Assert::equal('2.1.3', $node->name);
        Assert::equal(2, $node->position);
        Assert::equal(7, $node->left);
        Assert::equal(8, $node->right);
        $node = next($nodes->items);
        Assert::equal('2.1.1.1', $node->name);
        Assert::equal(10, $node->left);
        Assert::equal(11, $node->right);
        $node = next($nodes->items);
        Assert::equal('2.1.1.2', $node->name);
        Assert::equal(12, $node->left);
        Assert::equal(13, $node->right);
        $node = next($nodes->items);
        Assert::equal('2.1.1.3', $node->name);
        Assert::equal(14, $node->left);
        Assert::equal(15, $node->right);
        // no change in next
        $node = next($nodes->items);
        Assert::equal('3.1', $node->name);
        Assert::equal(28, $node->left);
        Assert::equal(29, $node->right);
        $node = next($nodes->items);
        Assert::equal('3.2', $node->name);
        Assert::equal(30, $node->left);
        Assert::equal(37, $node->right);
        $node = next($nodes->items);
        Assert::equal('3.3', $node->name);
        Assert::equal(38, $node->left);
        Assert::equal(39, $node->right);
        $node = next($nodes->items);
        Assert::equal('3.2.1', $node->name);
        Assert::equal(31, $node->left);
        Assert::equal(32, $node->right);
        $node = next($nodes->items);
        Assert::equal('3.2.2', $node->name);
        Assert::equal(33, $node->left);
        Assert::equal(34, $node->right);
        $node = next($nodes->items);
        Assert::equal('3.2.3', $node->name);
        Assert::equal(35, $node->left);
        Assert::equal(36, $node->right);
        Assert::true(empty(next($nodes->items)));
    }

    public function testMoveWithAnotherChildren() : void
    {
        $this->dataRefill();
        $categoryOption = $this->getOptions();
        $categoryTypedOption = $this->getTypedOptions();
        $this->nestedSet->rebuild($categoryOption);

        Assert::true($this->nestedSet->move(8, 1, $categoryTypedOption));
        $this->nestedSet->rebuild($categoryOption);

        // check nodes
        $options = new Support\Options();
        $options->joinChild = true;
        $options->additionalColumns = ['ANY_VALUE(child.`t_name`) as name', 'child.`t_type`'];
        $where = new Support\Conditions();
        $where->query = 'child.`t_type` = ?';
        $where->bindValues = ['category'];
        $options->where = $where;
        $nodes = $this->nestedSet->listNodesFlatten($options);
        usort($nodes->items, fn (Support\Node $node1, Support\Node $node2) : int => $node1->id <=> $node2->id);

        $node = reset($nodes->items);
        Assert::equal('Root 1', $node->name);
        Assert::equal(1, $node->left);
        Assert::equal(2, $node->right);
        $node = next($nodes->items);
        Assert::equal('Root 2', $node->name);
        Assert::equal(3, $node->left);
        Assert::equal(26, $node->right);
        $node = next($nodes->items);
        Assert::equal('Root 3', $node->name);
        Assert::equal(27, $node->left);
        Assert::equal(40, $node->right);
        $node = next($nodes->items);
        Assert::equal('2.1', $node->name);
        Assert::equal(6, $node->left);
        Assert::equal(19, $node->right);
        $node = next($nodes->items);
        Assert::equal('2.2', $node->name);
        Assert::equal(20, $node->left);
        Assert::equal(21, $node->right);
        $node = next($nodes->items);
        Assert::equal('2.3', $node->name);
        Assert::equal(22, $node->left);
        Assert::equal(23, $node->right);
        $node = next($nodes->items);
        Assert::equal('2.4', $node->name);
        Assert::equal(24, $node->left);
        Assert::equal(25, $node->right);
        $node = next($nodes->items);
        Assert::equal('2.5', $node->name);
        Assert::equal(4, $node->left);
        Assert::equal(5, $node->right);
        $node = next($nodes->items);
        Assert::equal('2.1.1', $node->name);
        Assert::equal(7, $node->left);
        Assert::equal(14, $node->right);
        $node = next($nodes->items);
        Assert::equal('2.1.2', $node->name);
        Assert::equal(15, $node->left);
        Assert::equal(16, $node->right);
        $node = next($nodes->items);
        Assert::equal('2.1.3', $node->name);
        Assert::equal(17, $node->left);
        Assert::equal(18, $node->right);
        $node = next($nodes->items);
        Assert::equal('2.1.1.1', $node->name);
        Assert::equal(8, $node->left);
        Assert::equal(9, $node->right);
        $node = next($nodes->items);
        Assert::equal('2.1.1.2', $node->name);
        Assert::equal(10, $node->left);
        Assert::equal(11, $node->right);
        $node = next($nodes->items);
        Assert::equal('2.1.1.3', $node->name);
        Assert::equal(12, $node->left);
        Assert::equal(13, $node->right);
        $node = next($nodes->items);
        Assert::equal('3.1', $node->name);
        Assert::equal(28, $node->left);
        Assert::equal(29, $node->right);
        $node = next($nodes->items);
        Assert::equal('3.2', $node->name);
        Assert::equal(30, $node->left);
        Assert::equal(37, $node->right);
        $node = next($nodes->items);
        Assert::equal('3.3', $node->name);
        Assert::equal(38, $node->left);
        Assert::equal(39, $node->right);
        $node = next($nodes->items);
        Assert::equal('3.2.1', $node->name);
        Assert::equal(31, $node->left);
        Assert::equal(32, $node->right);
        $node = next($nodes->items);
        Assert::equal('3.2.2', $node->name);
        Assert::equal(33, $node->left);
        Assert::equal(34, $node->right);
        $node = next($nodes->items);
        Assert::equal('3.2.3', $node->name);
        Assert::equal(35, $node->left);
        Assert::equal(36, $node->right);
        Assert::true(empty(next($nodes->items)));
    }

    public function testMoveWithBothChildren() : void
    {
        $this->dataRefill();
        $categoryOption = $this->getOptions();
        $categoryTypedOption = $this->getTypedOptions();
        $this->nestedSet->rebuild($categoryOption);

        Assert::true($this->nestedSet->move(3, 2, $categoryTypedOption));
        $this->nestedSet->rebuild($categoryOption);

        // check nodes
        $options = new Support\Options();
        $options->joinChild = true;
        $options->additionalColumns = ['ANY_VALUE(child.`t_name`) as name', 'child.`t_type`'];
        $where = new Support\Conditions();
        $where->query = 'child.`t_type` = ?';
        $where->bindValues = ['category'];
        $options->where = $where;
        $nodes = $this->nestedSet->listNodesFlatten($options);
        usort($nodes->items, fn (Support\Node $node1, Support\Node $node2) : int => $node1->id <=> $node2->id);

        $node = reset($nodes->items);
        Assert::equal('Root 1', $node->name);
        Assert::equal(1, $node->left);
        Assert::equal(2, $node->right);
        Assert::equal(1, $node->position);
        $node = next($nodes->items);
        Assert::equal('Root 2', $node->name);
        Assert::equal(17, $node->left);
        Assert::equal(40, $node->right);
        Assert::equal(3, $node->position);
        $node = next($nodes->items);
        Assert::equal('Root 3', $node->name);
        Assert::equal(3, $node->left);
        Assert::equal(16, $node->right);
        Assert::equal(2, $node->position);
        $node = next($nodes->items);
        Assert::equal('2.1', $node->name);
        Assert::equal(18, $node->left);
        Assert::equal(31, $node->right);
        $node = next($nodes->items);
        Assert::equal('2.2', $node->name);
        Assert::equal(32, $node->left);
        Assert::equal(33, $node->right);
        $node = next($nodes->items);
        Assert::equal('2.3', $node->name);
        Assert::equal(34, $node->left);
        Assert::equal(35, $node->right);
        $node = next($nodes->items);
        Assert::equal('2.4', $node->name);
        Assert::equal(36, $node->left);
        Assert::equal(37, $node->right);
        $node = next($nodes->items);
        Assert::equal('2.5', $node->name);
        Assert::equal(38, $node->left);
        Assert::equal(39, $node->right);
        $node = next($nodes->items);
        Assert::equal('2.1.1', $node->name);
        Assert::equal(19, $node->left);
        Assert::equal(26, $node->right);
        $node = next($nodes->items);
        Assert::equal('2.1.2', $node->name);
        Assert::equal(27, $node->left);
        Assert::equal(28, $node->right);
        $node = next($nodes->items);
        Assert::equal('2.1.3', $node->name);
        Assert::equal(29, $node->left);
        Assert::equal(30, $node->right);
        $node = next($nodes->items);
        Assert::equal('2.1.1.1', $node->name);
        Assert::equal(20, $node->left);
        Assert::equal(21, $node->right);
        $node = next($nodes->items);
        Assert::equal('2.1.1.2', $node->name);
        Assert::equal(22, $node->left);
        Assert::equal(23, $node->right);
        $node = next($nodes->items);
        Assert::equal('2.1.1.3', $node->name);
        Assert::equal(24, $node->left);
        Assert::equal(25, $node->right);
        $node = next($nodes->items);
        Assert::equal('3.1', $node->name);
        Assert::equal(4, $node->left);
        Assert::equal(5, $node->right);
        $node = next($nodes->items);
        Assert::equal('3.2', $node->name);
        Assert::equal(6, $node->left);
        Assert::equal(13, $node->right);
        $node = next($nodes->items);
        Assert::equal('3.3', $node->name);
        Assert::equal(14, $node->left);
        Assert::equal(15, $node->right);
        $node = next($nodes->items);
        Assert::equal('3.2.1', $node->name);
        Assert::equal(7, $node->left);
        Assert::equal(8, $node->right);
        $node = next($nodes->items);
        Assert::equal('3.2.2', $node->name);
        Assert::equal(9, $node->left);
        Assert::equal(10, $node->right);
        $node = next($nodes->items);
        Assert::equal('3.2.3', $node->name);
        Assert::equal(11, $node->left);
        Assert::equal(12, $node->right);
        Assert::true(empty(next($nodes->items)));
    }

    protected function getOptions() : Support\Options
    {
        $categoryOption = new Support\Options();
        $categoryCondition = new Support\Conditions();
        $categoryCondition->query = '`t_type` = ?';
        $categoryCondition->bindValues = ['category'];
        $categoryOption->additionalColumns = ['`t_type`'];
        $categoryOption->where = $categoryCondition;

        return $categoryOption;
    }

    protected function getTypedOptions() : Support\Options
    {
        $categoryOption = new Support\Options();
        $categoryCondition = new Support\Conditions();
        $categoryCondition->query = 'node.`t_type` = ?';
        $categoryCondition->bindValues = ['category'];
        $categoryOption->additionalColumns = ['node.`t_type`'];
        $categoryOption->where = $categoryCondition;

        return $categoryOption;
    }
}

(new UpdateDbTest())->run();
