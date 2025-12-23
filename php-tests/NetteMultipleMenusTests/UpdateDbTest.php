<?php

namespace Tests\NetteMultipleMenusTests;

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'bootstrap.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'AbstractMultipleMenusTests.php';

use kalanis\nested_tree\Support;
use Tester\Assert;
use Tests\MockNode;

class UpdateDbTest extends AbstractMultipleMenusTests
{
    /**
     * Test check that selected parent is same level or under its children.
     *
     * This will be used to check before update the data.
     *
     * @return void
     */
    public function testIsNodeParentInItsChildren() : void
    {
        $this->dataRefill();
        $this->rebuild(1);
        $this->rebuild(2);
        $categoryOption = new Support\Options();
        $categoryCondition = new Support\Conditions();
        $categoryCondition->query = 'menu_id = ?';
        $categoryCondition->bindValues = [1];
        $categoryOption->additionalColumns = ['node.menu_id'];
        $categoryOption->where = $categoryCondition;
        $this->nestedSet->rebuild($categoryOption);

        $categoryCondition->query = 'node.menu_id = ?';
        Assert::false($this->nestedSet->isNewParentOutsideCurrentNodeTree(
            9, // 2.1.1 (9)
            12, // shouldn't under 2.1.1.1 (12)
            $categoryOption
        ));
        Assert::true($this->nestedSet->isNewParentOutsideCurrentNodeTree(
            9, // 2.1.1 (9)
            19, // is okay to be under 3.2.2 (19 - can be new parent)
            $categoryOption
        ));
        Assert::false($this->nestedSet->isNewParentOutsideCurrentNodeTree(
            9, // 2.1.1 (9)
            20, // is not okay to be under 3.2.3 (20 - soft deleted)
            $categoryOption
        ));

        Assert::true($this->nestedSet->isNewParentOutsideCurrentNodeTree(
            19, // 3.2.2
            16, // is under 3.2
            $categoryOption
        ));

        $categoryCondition->bindValues = [2];
        $this->nestedSet->rebuild($categoryOption);
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
        $this->rebuild(1);
        $categoryOption = $this->getOptions(1);
        $this->nestedSet->rebuild($categoryOption);
        $this->nestedSet->move(17, 2, $categoryOption);
        $this->nestedSet->rebuild($categoryOption);

        $options = new Support\Options();
        $options->joinChild = true;
        $options->additionalColumns = ['child.menu_id'];
        $where = new Support\Conditions();
        $where->query = 'child.parent_id = ? AND child.menu_id = ?';
        $where->bindValues = [3, 1];
        $options->where = $where;
        $nodes = $this->nestedSet->listNodesFlatten($options);

        $node = reset($nodes->items);
        Assert::equal(1, $node->position);
        Assert::equal(15, $node->id);
        $node = next($nodes->items);
        Assert::equal(2, $node->position);
        Assert::equal(17, $node->id);
        $node = next($nodes->items);
        Assert::equal(3, $node->position);
        Assert::equal(16, $node->id);

        Assert::true(empty(next($nodes->items)));
    }

    public function testMoveNodeDown() : void
    {
        $this->dataRefill();
        $categoryOption = $this->getOptions(1);
        $this->nestedSet->rebuild($categoryOption);
        $this->nestedSet->move(16, 3, $categoryOption);
        $this->nestedSet->rebuild($categoryOption);

        $options = new Support\Options();
        $options->joinChild = true;
        $options->additionalColumns = ['child.menu_id'];
        $where = new Support\Conditions();
        $where->query = 'child.parent_id = ? AND child.menu_id = ?';
        $where->bindValues = [3, 1];
        $options->where = $where;
        $nodes = $this->nestedSet->listNodesFlatten($options);

        $node = reset($nodes->items);
        Assert::equal(1, $node->position);
        Assert::equal(15, $node->id);
        $node = next($nodes->items);
        Assert::equal(2, $node->position);
        Assert::equal(17, $node->id);
        $node = next($nodes->items);
        Assert::equal(3, $node->position);
        Assert::equal(16, $node->id);

        Assert::true(empty(next($nodes->items)));
    }

    public function testChangeNodeParent() : void
    {
        $this->dataRefill();
        $categoryOption = $this->getOptions(1);
        $categoryTypedOption = $this->getTypedOptions(1);
        $this->nestedSet->rebuild($categoryOption);
        Assert::true($this->nestedSet->changeParent(13, 16, $categoryTypedOption));
        $this->nestedSet->rebuild($categoryOption);
        Assert::false($this->nestedSet->changeParent(4, 12, $categoryTypedOption));

        $options = new Support\Options();
        $options->joinChild = true;
        $options->additionalColumns = ['child.menu_id'];
        $where = new Support\Conditions();
        $where->query = 'child.parent_id = ? AND child.menu_id = ?';
        $options->where = $where;

        // old group
        $where->bindValues = [9, 1];
        $nodes = $this->nestedSet->listNodesFlatten($options);

        $node = reset($nodes->items);
        Assert::equal(12, $node->id);
        Assert::equal(1, $node->position);
        // 14 is disabled here
        Assert::true(empty(next($nodes->items)));

        // new group
        $where->bindValues = [16, 1];
        $nodes = $this->nestedSet->listNodesFlatten($options);

        $node = reset($nodes->items);
        Assert::equal(18, $node->id);
        Assert::equal(1, $node->position);
        $node = next($nodes->items);
        Assert::equal(19, $node->id);
        Assert::equal(2, $node->position);
        $node = next($nodes->items);
        Assert::equal(13, $node->id);
        Assert::equal(3, $node->position);
        // 20 is disabled

        Assert::true(empty(next($nodes->items)));
    }

    public function testMoveNoEntry() : void
    {
        $this->dataRefill();
        $categoryOption = $this->getOptions(1);
        $this->nestedSet->rebuild($categoryOption);

        $options = new Support\Options();
        $options->joinChild = true;
        $options->additionalColumns = ['child.menu_id'];
        $where = new Support\Conditions();
        $where->query = 'node.menu_id = ?';
        $where->bindValues = [1];
        $options->where = $where;

        Assert::false($this->nestedSet->move(22, 2, $options));
    }

    public function testMoveNoConditionsMet() : void
    {
        $this->dataRefill();
        $categoryOption = $this->getOptions(1);
        $this->nestedSet->rebuild($categoryOption);

        $options = new Support\Options();
        $options->joinChild = true;
        $options->additionalColumns = ['child.menu_id'];
        $where = new Support\Conditions();
        $where->query = 'child.menu_id = ?';
        $where->bindValues = [1];
        $options->where = $where;

        Assert::true($this->nestedSet->move(15, 12, $options));
    }

    public function testUpdateData() : void
    {
        $this->dataRefill();
        $categoryOption = $this->getOptions(1);
        $categoryTypedOption = $this->getTypedOptions(1);
        $this->nestedSet->rebuild($categoryOption);

        $node = MockNode::create(13, name: 'Mop Update');
        Assert::false($this->nestedSet->update($node, $categoryTypedOption)); // no name can be set here

        $row = $this->getRow($this->dbExplorer, $this->settings, 13);

        // updated
        Assert::equal(13, $row[$this->settings->idColumnName]);
        Assert::equal(9, $row[$this->settings->parentIdColumnName]);
        Assert::equal(2, $row[$this->settings->positionColumnName]);
    }

    public function testMoveWithoutChildren() : void
    {
        $this->dataRefill();
        $categoryOption = $this->getOptions(1);
        $categoryTypedOption = $this->getTypedOptions(1);
        $this->nestedSet->rebuild($categoryOption);

        Assert::true($this->nestedSet->move(12, 3, $categoryTypedOption));
        $this->nestedSet->rebuild($categoryOption);

        // check nodes
        $options = new Support\Options();
        $options->joinChild = true;
        $options->additionalColumns = ['child.menu_id'];
        $where = new Support\Conditions();
        $where->query = 'child.menu_id = ?';
        $where->bindValues = [1];
        $options->where = $where;
        $nodes = $this->nestedSet->listNodesFlatten($options);
        usort($nodes->items, fn (Support\Node $node1, Support\Node $node2) : int => $node1->id <=> $node2->id);

        $node = reset($nodes->items);
        Assert::equal(1, $node->id);
        Assert::equal(1, $node->left);
        Assert::equal(2, $node->right);
        $node = next($nodes->items);
        Assert::equal(2, $node->id);
        Assert::equal(3, $node->left);
        Assert::equal(20, $node->right);
        $node = next($nodes->items);
        Assert::equal(3, $node->id);
        Assert::equal(21, $node->left);
        Assert::equal(32, $node->right);
        $node = next($nodes->items);
        Assert::equal(4, $node->id);
        Assert::equal(4, $node->left);
        Assert::equal(15, $node->right);
        $node = next($nodes->items);
        Assert::equal(5, $node->id);
        Assert::equal(16, $node->left);
        Assert::equal(17, $node->right);
        $node = next($nodes->items);
        Assert::equal(8, $node->id);
        Assert::equal(18, $node->left);
        Assert::equal(19, $node->right);
        // base
        $node = next($nodes->items);
        Assert::equal(9, $node->id);
        Assert::equal(5, $node->left);
        Assert::equal(10, $node->right);
        $node = next($nodes->items);
        Assert::equal(10, $node->id);
        Assert::equal(11, $node->left);
        Assert::equal(12, $node->right);
        $node = next($nodes->items);
        Assert::equal(11, $node->id);
        Assert::equal(13, $node->left);
        Assert::equal(14, $node->right);
        // moved items
        $node = next($nodes->items);
        Assert::equal(12, $node->id);
        Assert::equal(2, $node->position);
        Assert::equal(8, $node->left);
        Assert::equal(9, $node->right);
        $node = next($nodes->items);
        Assert::equal(13, $node->id);
        Assert::equal(1, $node->position);
        Assert::equal(6, $node->left);
        Assert::equal(7, $node->right);
        // no change
        $node = next($nodes->items);
        Assert::equal(15, $node->id);
        Assert::equal(22, $node->left);
        Assert::equal(23, $node->right);
        $node = next($nodes->items);
        Assert::equal(16, $node->id);
        Assert::equal(24, $node->left);
        Assert::equal(29, $node->right);
        $node = next($nodes->items);
        Assert::equal(17, $node->id);
        Assert::equal(30, $node->left);
        Assert::equal(31, $node->right);
        $node = next($nodes->items);
        Assert::equal(18, $node->id);
        Assert::equal(25, $node->left);
        Assert::equal(26, $node->right);
        $node = next($nodes->items);
        Assert::equal(19, $node->id);
        Assert::equal(27, $node->left);
        Assert::equal(28, $node->right);
        Assert::true(empty(next($nodes->items)));
    }

    public function testMoveWithChildrenNoAnotherChildren() : void
    {
        $this->dataRefill();
        $categoryOption = $this->getOptions(1);
        $categoryTypedOption = $this->getTypedOptions(1);
        $this->nestedSet->rebuild($categoryOption);

        Assert::true($this->nestedSet->move(9, 3, $categoryTypedOption));
        $this->nestedSet->rebuild($categoryOption);

        // check nodes
        $options = new Support\Options();
        $options->joinChild = true;
        $options->additionalColumns = ['child.menu_id'];
        $where = new Support\Conditions();
        $where->query = 'child.menu_id = ?';
        $where->bindValues = [1];
        $options->where = $where;
        $nodes = $this->nestedSet->listNodesFlatten($options);
        usort($nodes->items, fn (Support\Node $node1, Support\Node $node2) : int => $node1->id <=> $node2->id);

        $node = reset($nodes->items);
        Assert::equal(1, $node->id);
        Assert::equal(1, $node->left);
        Assert::equal(2, $node->right);
        $node = next($nodes->items);
        Assert::equal(2, $node->id);
        Assert::equal(3, $node->left);
        Assert::equal(20, $node->right);
        $node = next($nodes->items);
        Assert::equal(3, $node->id);
        Assert::equal(21, $node->left);
        Assert::equal(32, $node->right);
        $node = next($nodes->items);
        Assert::equal(4, $node->id);
        Assert::equal(4, $node->left);
        Assert::equal(15, $node->right);
        $node = next($nodes->items);
        Assert::equal(5, $node->id);
        Assert::equal(16, $node->left);
        Assert::equal(17, $node->right);
        $node = next($nodes->items);
        Assert::equal(8, $node->id);
        Assert::equal(18, $node->left);
        Assert::equal(19, $node->right);
        // base
        $node = next($nodes->items);
        Assert::equal(9, $node->id);
        Assert::equal(9, $node->left);
        Assert::equal(14, $node->right);
        $node = next($nodes->items);
        Assert::equal(10, $node->id);
        Assert::equal(5, $node->left);
        Assert::equal(6, $node->right);
        $node = next($nodes->items);
        Assert::equal(11, $node->id);
        Assert::equal(7, $node->left);
        Assert::equal(8, $node->right);
        // moved items
        $node = next($nodes->items);
        Assert::equal(12, $node->id);
        Assert::equal(10, $node->left);
        Assert::equal(11, $node->right);
        $node = next($nodes->items);
        Assert::equal(13, $node->id);
        Assert::equal(12, $node->left);
        Assert::equal(13, $node->right);
        // no change
        $node = next($nodes->items);
        Assert::equal(15, $node->id);
        Assert::equal(22, $node->left);
        Assert::equal(23, $node->right);
        $node = next($nodes->items);
        Assert::equal(16, $node->id);
        Assert::equal(24, $node->left);
        Assert::equal(29, $node->right);
        $node = next($nodes->items);
        Assert::equal(17, $node->id);
        Assert::equal(30, $node->left);
        Assert::equal(31, $node->right);
        $node = next($nodes->items);
        Assert::equal(18, $node->id);
        Assert::equal(25, $node->left);
        Assert::equal(26, $node->right);
        $node = next($nodes->items);
        Assert::equal(19, $node->id);
        Assert::equal(27, $node->left);
        Assert::equal(28, $node->right);
        Assert::true(empty(next($nodes->items)));
    }

    public function testMoveWithAnotherChildren() : void
    {
        $this->dataRefill();
        $categoryOption = $this->getOptions(1);
        $categoryTypedOption = $this->getTypedOptions(1);
        $this->nestedSet->rebuild($categoryOption);

        Assert::true($this->nestedSet->move(8, 1, $categoryTypedOption));
        $this->nestedSet->rebuild($categoryOption);

        // check nodes
        $options = new Support\Options();
        $options->joinChild = true;
        $options->additionalColumns = ['child.menu_id'];
        $where = new Support\Conditions();
        $where->query = 'child.menu_id = ?';
        $where->bindValues = [1];
        $options->where = $where;
        $nodes = $this->nestedSet->listNodesFlatten($options);
        usort($nodes->items, fn (Support\Node $node1, Support\Node $node2) : int => $node1->id <=> $node2->id);

        $node = reset($nodes->items);
        Assert::equal(1, $node->id);
        Assert::equal(1, $node->left);
        Assert::equal(2, $node->right);
        $node = next($nodes->items);
        Assert::equal(2, $node->id);
        Assert::equal(3, $node->left);
        Assert::equal(20, $node->right);
        $node = next($nodes->items);
        Assert::equal(3, $node->id);
        Assert::equal(21, $node->left);
        Assert::equal(32, $node->right);
        $node = next($nodes->items);
        Assert::equal(4, $node->id);
        Assert::equal(6, $node->left);
        Assert::equal(17, $node->right);
        $node = next($nodes->items);
        Assert::equal(5, $node->id);
        Assert::equal(18, $node->left);
        Assert::equal(19, $node->right);
        $node = next($nodes->items);
        Assert::equal(8, $node->id);
        Assert::equal(4, $node->left);
        Assert::equal(5, $node->right);
        $node = next($nodes->items);
        Assert::equal(9, $node->id);
        Assert::equal(7, $node->left);
        Assert::equal(12, $node->right);
        $node = next($nodes->items);
        Assert::equal(10, $node->id);
        Assert::equal(13, $node->left);
        Assert::equal(14, $node->right);
        $node = next($nodes->items);
        Assert::equal(11, $node->id);
        Assert::equal(15, $node->left);
        Assert::equal(16, $node->right);
        $node = next($nodes->items);
        Assert::equal(12, $node->id);
        Assert::equal(8, $node->left);
        Assert::equal(9, $node->right);
        $node = next($nodes->items);
        Assert::equal(13, $node->id);
        Assert::equal(10, $node->left);
        Assert::equal(11, $node->right);
        $node = next($nodes->items);
        Assert::equal(15, $node->id);
        Assert::equal(22, $node->left);
        Assert::equal(23, $node->right);
        $node = next($nodes->items);
        Assert::equal(16, $node->id);
        Assert::equal(24, $node->left);
        Assert::equal(29, $node->right);
        $node = next($nodes->items);
        Assert::equal(17, $node->id);
        Assert::equal(30, $node->left);
        Assert::equal(31, $node->right);
        $node = next($nodes->items);
        Assert::equal(18, $node->id);
        Assert::equal(25, $node->left);
        Assert::equal(26, $node->right);
        $node = next($nodes->items);
        Assert::equal(19, $node->id);
        Assert::equal(27, $node->left);
        Assert::equal(28, $node->right);
        Assert::true(empty(next($nodes->items)));
    }

    public function testMoveWithBothChildren() : void
    {
        $this->dataRefill();
        $categoryOption = $this->getOptions(1);
        $categoryTypedOption = $this->getTypedOptions(1);
        $this->nestedSet->rebuild($categoryOption);

        Assert::true($this->nestedSet->move(3, 2, $categoryTypedOption));
        $this->nestedSet->rebuild($categoryOption);

        // check nodes
        $options = new Support\Options();
        $options->joinChild = true;
        $options->additionalColumns = ['child.menu_id'];
        $where = new Support\Conditions();
        $where->query = 'child.menu_id = ?';
        $where->bindValues = [1];
        $options->where = $where;
        $nodes = $this->nestedSet->listNodesFlatten($options);
        usort($nodes->items, fn (Support\Node $node1, Support\Node $node2) : int => $node1->id <=> $node2->id);

        $node = reset($nodes->items);
        Assert::equal(1, $node->id);
        Assert::equal(1, $node->left);
        Assert::equal(2, $node->right);
        $node = next($nodes->items);
        Assert::equal(2, $node->id);
        Assert::equal(3, $node->left);
        Assert::equal(20, $node->right);
        $node = next($nodes->items);
        Assert::equal(3, $node->id);
        Assert::equal(21, $node->left);
        Assert::equal(32, $node->right);
        $node = next($nodes->items);
        Assert::equal(4, $node->id);
        Assert::equal(4, $node->left);
        Assert::equal(15, $node->right);
        $node = next($nodes->items);
        Assert::equal(5, $node->id);
        Assert::equal(16, $node->left);
        Assert::equal(17, $node->right);
        $node = next($nodes->items);
        Assert::equal(8, $node->id);
        Assert::equal(18, $node->left);
        Assert::equal(19, $node->right);
        $node = next($nodes->items);
        Assert::equal(9, $node->id);
        Assert::equal(5, $node->left);
        Assert::equal(10, $node->right);
        $node = next($nodes->items);
        Assert::equal(10, $node->id);
        Assert::equal(11, $node->left);
        Assert::equal(12, $node->right);
        $node = next($nodes->items);
        Assert::equal(11, $node->id);
        Assert::equal(13, $node->left);
        Assert::equal(14, $node->right);
        $node = next($nodes->items);
        Assert::equal(12, $node->id);
        Assert::equal(6, $node->left);
        Assert::equal(7, $node->right);
        $node = next($nodes->items);
        Assert::equal(13, $node->id);
        Assert::equal(8, $node->left);
        Assert::equal(9, $node->right);
        $node = next($nodes->items);
        Assert::equal(15, $node->id);
        Assert::equal(22, $node->left);
        Assert::equal(23, $node->right);
        $node = next($nodes->items);
        Assert::equal(16, $node->id);
        Assert::equal(24, $node->left);
        Assert::equal(29, $node->right);
        $node = next($nodes->items);
        Assert::equal(17, $node->id);
        Assert::equal(30, $node->left);
        Assert::equal(31, $node->right);
        $node = next($nodes->items);
        Assert::equal(18, $node->id);
        Assert::equal(25, $node->left);
        Assert::equal(26, $node->right);
        $node = next($nodes->items);
        Assert::equal(19, $node->id);
        Assert::equal(27, $node->left);
        Assert::equal(28, $node->right);
        Assert::true(empty(next($nodes->items)));
    }

    protected function getOptions(int $menuId) : Support\Options
    {
        $categoryOption = new Support\Options();
        $categoryCondition = new Support\Conditions();
        $categoryCondition->query = 'menu_id = ?';
        $categoryCondition->bindValues = [$menuId];
        $categoryOption->additionalColumns = ['menu_id'];
        $categoryOption->where = $categoryCondition;

        return $categoryOption;
    }

    protected function getTypedOptions(int $menuId) : Support\Options
    {
        $categoryOption = new Support\Options();
        $categoryCondition = new Support\Conditions();
        $categoryCondition->query = '`node`.menu_id = ?';
        $categoryCondition->bindValues = [$menuId];
        $categoryOption->additionalColumns = ['`node`.menu_id'];
        $categoryOption->where = $categoryCondition;

        return $categoryOption;
    }
}

(new UpdateDbTest())->run();
