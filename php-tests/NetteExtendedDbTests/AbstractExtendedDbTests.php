<?php

namespace Tests\NetteExtendedDbTests;

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'AbstractDatabaseTestCase.php';

use kalanis\nested_tree\Support;
use kalanis\nested_tree_nette\Sources\Nette\MySql;
use Tests\AbstractDatabaseTestCase;
use Tests\MockNode;
use Tests\NestedSetExtends;

abstract class AbstractExtendedDbTests extends AbstractDatabaseTestCase
{
    protected ?NestedSetExtends $nestedSet = null;
    protected ?ExtendedTableSettings $settings = null;

    protected function setUp() : void
    {
        parent::setUp();
        $this->settings = new ExtendedTableSettings();
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

    protected function rebuild(string $type = 'category') : void
    {
        $options = new Support\Options();
        $optionsWhere = new Support\Conditions();
        $optionsWhere->query = '`t_type` = :t_type';
        $optionsWhere->bindValues = [':t_type' => $type];
        $options->where = $optionsWhere;
        $this->nestedSet->rebuild($options);
    }

    protected function emptyDatabase() : void
    {
        $this->connection->query('DROP TABLE IF EXISTS `test_taxonomy_2`;');
    }

    protected function createDatabase() : void
    {
        $this->connection->query("CREATE TABLE IF NOT EXISTS `test_taxonomy_2` (
  `tid` bigint(20) NOT NULL AUTO_INCREMENT,
  `parent_id` bigint(20) NOT NULL DEFAULT '0' COMMENT 'refer to this table column id. this column value must be integer. if it is root then this value must be 0, it can not be NULL.',
  `t_type` varchar(100) DEFAULT NULL COMMENT 'taxonomy type. example: category, or product_category',
  `t_name` varchar(255) DEFAULT NULL COMMENT 'taxonomy name',
  `t_status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '0=unpublished, 1=published',
  `t_position` int(9) NOT NULL DEFAULT '0' COMMENT 'position when sort/order tags item.',
  `t_level` int(10) NOT NULL DEFAULT '1' COMMENT 'deep level of taxonomy hierarchy. begins at 1 (no sub items).',
  `t_left` int(10) NOT NULL DEFAULT '0' COMMENT 'for nested set model calculation. this will be able to select filtered parent id and all of its children.',
  `t_right` int(10) NOT NULL DEFAULT '0' COMMENT 'for nested set model calculation. this will be able to select filtered parent id and all of its children.',
  PRIMARY KEY (`tid`),
  KEY `parent_id` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='contain taxonomy with more complex data/columns.' AUTO_INCREMENT=1 ;
");
        $this->connection->query("INSERT IGNORE INTO `test_taxonomy_2` (`tid`, `parent_id`, `t_type`, `t_name`, `t_status`, `t_position`, `t_level`, `t_left`, `t_right`) VALUES
(1, 0, 'category', 'Root 1', 1, 1, 0, 0, 0),
(2, 0, 'category', 'Root 2', 1, 2, 0, 0, 0),
(3, 0, 'category', 'Root 3', 1, 3, 0, 0, 0),
(4, 2, 'category', '2.1', 1, 1, 0, 0, 0),
(5, 2, 'category', '2.2', 1, 2, 0, 0, 0),
(6, 2, 'category', '2.3', 0, 3, 0, 0, 0),
(7, 2, 'category', '2.4', 0, 4, 0, 0, 0),
(8, 2, 'category', '2.5', 1, 5, 0, 0, 0),
(9, 4, 'category', '2.1.1', 1, 1, 0, 0, 0),
(10, 4, 'category', '2.1.2', 1, 2, 0, 0, 0),
(11, 4, 'category', '2.1.3', 1, 3, 0, 0, 0),
(12, 9, 'category', '2.1.1.1', 1, 1, 0, 0, 0),
(13, 9, 'category', '2.1.1.2', 1, 2, 0, 0, 0),
(14, 9, 'category', '2.1.1.3', 0, 3, 0, 0, 0),
(15, 3, 'category', '3.1', 1, 1, 0, 0, 0),
(16, 3, 'category', '3.2', 1, 2, 0, 0, 0),
(17, 3, 'category', '3.3', 1, 3, 0, 0, 0),
(18, 16, 'category', '3.2.1', 1, 1, 0, 0, 0),
(19, 16, 'category', '3.2.2', 1, 2, 0, 0, 0),
(20, 16, 'category', '3.2.3', 0, 3, 0, 0, 0),
(21, 0, 'product-category', 'Camera', 1, 1, 0, 0, 0),
(22, 0, 'product-category', 'Computer', 1, 2, 0, 0, 0),
(23, 0, 'product-category', 'Electronic', 1, 3, 0, 0, 0),
(24, 21, 'product-category', 'Canon', 1, 1, 0, 0, 0),
(25, 21, 'product-category', 'Nikon', 1, 2, 0, 0, 0),
(26, 21, 'product-category', 'Sony', 1, 3, 0, 0, 0),
(27, 21, 'product-category', 'Fuji', 0, 4, 0, 0, 0),
(28, 22, 'product-category', 'Desktop', 1, 1, 0, 0, 0),
(29, 22, 'product-category', 'Laptop', 1, 2, 0, 0, 0),
(30, 28, 'product-category', 'Dell', 1, 1, 0, 0, 0),
(31, 28, 'product-category', 'Lenovo', 1, 2, 0, 0, 0),
(32, 28, 'product-category', 'MSI', 0, 3, 0, 0, 0);
");
    }
}

class ExtendedTableSettings extends Support\TableSettings
{
    public string $tableName = 'test_taxonomy_2';
    public string $idColumnName = 'tid';
    public string $parentIdColumnName = 'parent_id';
    public string $leftColumnName = 't_left';
    public string $rightColumnName = 't_right';
    public string $levelColumnName = 't_level';
    public string $positionColumnName = 't_position';
}
