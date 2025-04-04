<?php

namespace Tests\NetteSimpleDbTests;

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'AbstractDatabaseTestCase.php';

use kalanis\nested_tree\Support;
use kalanis\nested_tree_nette\Sources\Nette\MySql;
use Tests\AbstractDatabaseTestCase;
use Tests\MockNode;
use Tests\NestedSetExtends;

abstract class AbstractSimpleDbTests extends AbstractDatabaseTestCase
{
    protected ?NestedSetExtends $nestedSet = null;
    protected ?SimpleTableSettings $settings = null;

    protected function setUp() : void
    {
        parent::setUp();
        $this->settings = new SimpleTableSettings();
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

    protected function emptyDatabase() : void
    {
        $this->connection->query('DROP TABLE IF EXISTS `test_taxonomy`;');
    }

    protected function createDatabase() : void
    {
        $this->connection->query("CREATE TABLE IF NOT EXISTS `test_taxonomy` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `parent_id` bigint(20) NOT NULL DEFAULT '0' COMMENT 'refer to this table column id. this column value must be integer. if it is root then this value must be 0, it can not be NULL.',
  `name` varchar(255) DEFAULT NULL COMMENT 'taxonomy name',
  `position` int(9) NOT NULL DEFAULT '0' COMMENT 'position when sort/order tags item.',
  `level` int(10) NOT NULL DEFAULT '1' COMMENT 'deep level of taxonomy hierarchy. begins at 1 (no sub items).',
  `left` int(10) NOT NULL DEFAULT '0' COMMENT 'for nested set model calculation. this will be able to select filtered parent id and all of its children.',
  `right` int(10) NOT NULL DEFAULT '0' COMMENT 'for nested set model calculation. this will be able to select filtered parent id and all of its children.',
  PRIMARY KEY (`id`),
  KEY `parent_id` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='contain taxonomy data such as category.' AUTO_INCREMENT=1 ;
");
        $this->connection->query("INSERT IGNORE INTO `test_taxonomy` (`id`, `parent_id`, `name`, `position`, `level`, `left`, `right`) VALUES
(1, 0, 'Root 1', 1, 0, 0, 0),
(2, 0, 'Root 2', 2, 0, 0, 0),
(3, 0, 'Root 3', 3, 0, 0, 0),
(4, 2, '2.1', 1, 0, 0, 0),
(5, 2, '2.2', 2, 0, 0, 0),
(6, 2, '2.3', 3, 0, 0, 0),
(7, 2, '2.4', 4, 0, 0, 0),
(8, 2, '2.5', 5, 0, 0, 0),
(9, 4, '2.1.1', 1, 0, 0, 0),
(10, 4, '2.1.2', 2, 0, 0, 0),
(11, 4, '2.1.3', 3, 0, 0, 0),
(12, 9, '2.1.1.1', 1, 0, 0, 0),
(13, 9, '2.1.1.2', 2, 0, 0, 0),
(14, 9, '2.1.1.3', 3, 0, 0, 0),
(15, 3, '3.1', 1, 0, 0, 0),
(16, 3, '3.2', 2, 0, 0, 0),
(17, 3, '3.3', 3, 0, 0, 0),
(18, 16, '3.2.1', 1, 0, 0, 0),
(19, 16, '3.2.2', 2, 0, 0, 0),
(20, 16, '3.2.3', 3, 0, 0, 0);
");
    }
}

class SimpleTableSettings extends Support\TableSettings
{
    public string $tableName = 'test_taxonomy';
}
