<?php

namespace Tests\NetteNullDbTests;

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'bootstrap.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'AbstractNullDbTests.php';

use Tester\Assert;

class UpdateDbTest extends AbstractNullDbTests
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
        Assert::true($this->nestedSet->isNewParentOutsideCurrentNodeTree(9, null)); // root always
    }
}

(new UpdateDbTest())->run();
