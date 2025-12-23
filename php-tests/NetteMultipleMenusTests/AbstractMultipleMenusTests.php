<?php

namespace Tests\NetteMultipleMenusTests;

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'AbstractDatabaseTestCase.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'NetteSupport' . DIRECTORY_SEPARATOR . 'DumpTrait.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'MultiMenuSoftDelete.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'MultiMenuTableSettings.php';

use kalanis\nested_tree\Support;
use kalanis\nested_tree_nette\Sources\Nette\MySql;
use Tests\AbstractDatabaseTestCase;
use Tests\MockNode;
use Tests\NestedSetExtends;
use Tests\NetteSupport\DumpTrait;

abstract class AbstractMultipleMenusTests extends AbstractDatabaseTestCase
{
    use DumpTrait;

    protected ?NestedSetExtends $nestedSet = null;
    protected ?MultiMenuTableSettings $settings = null;

    protected function setUp() : void
    {
        parent::setUp();
        $this->settings = new MultiMenuTableSettings();
        $this->settings->softDelete = new MultiMenuSoftDelete();
        $this->nestedSet = new NestedSetExtends(
            new MySql(
                $this->dbExplorer,
                new MockNode(),
                $this->settings,
            ),
        );
    }

    protected function dataRefill() : void
    {
        $this->emptyDatabase();
        $this->createDatabase();
    }

    protected function rebuild(int $menuId = 0) : void
    {
        $options = new Support\Options();
        $optionsWhere = new Support\Conditions();
        $optionsWhere->query = 'menu_id = ?';
        $optionsWhere->bindValues = [$menuId];
        $options->where = $optionsWhere;
        $this->nestedSet->rebuild($options);
    }

    protected function emptyDatabase() : void
    {
        $this->connection->query('DROP TABLE IF EXISTS `test_taxonomy_3`;');
    }

    protected function createDatabase() : void
    {
        $this->connection->query("CREATE TABLE IF NOT EXISTS `test_taxonomy_3` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `menu_id` int NOT NULL,
  `left` int NULL,
  `right` int NULL,
  `depth` tinyint NOT NULL DEFAULT '0',
  `parent_id` int NULL,
  `order` int NULL,
  `last` tinyint NULL,
  `deleted` tinyint NOT NULL DEFAULT '0',

  INDEX `t3_idx_menu_id` (`menu_id`),
  INDEX `t3_idx_deleted` (`deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='contain taxonomy with more menus in one table and deleted column flag.' AUTO_INCREMENT=1 ;
");
        $this->connection->query('INSERT IGNORE INTO `test_taxonomy_3` (`id`, `parent_id`, `menu_id`, `deleted`, `order`) VALUES
(1, null, 1, 0, 1),
(2, null, 1, 0, 2),
(3, null, 1, 0, 3),
(4, 2, 1, 0, 1),
(5, 2, 1, 0, 2),
(6, 2, 1, 1, 0),
(7, 2, 1, 1, 0),
(8, 2, 1, 0, 3),
(9, 4, 1, 0, 1),
(10, 4, 1, 0, 2),
(11, 4, 1, 0, 3),
(12, 9, 1, 0, 1),
(13, 9, 1, 0, 2),
(14, 9, 1, 1, 0),
(15, 3, 1, 0, 1),
(16, 3, 1, 0, 2),
(17, 3, 1, 0, 3),
(18, 16, 1, 0, 1),
(19, 16, 1, 0, 2),
(20, 16, 1, 1, 3),
(21, null, 2, 0, 1),
(22, null, 2, 0, 2),
(23, null, 2, 0, 3),
(24, 21, 2, 0, 1),
(25, 21, 2, 0, 2),
(26, 21, 2, 0, 3),
(27, 21, 2, 1, 4),
(28, 22, 2, 0, 1),
(29, 22, 2, 0, 2),
(30, 28, 2, 0, 1),
(31, 28, 2, 0, 2),
(32, 28, 2, 1, 3);
');
    }
}
