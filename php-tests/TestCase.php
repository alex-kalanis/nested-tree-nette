<?php

namespace Tests;

use kalanis\nested_tree\Support;
// use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Nette\Bootstrap\Configurator;
use Nette\Database\Explorer;
use Nette\Database\Row;
use Nette\DI\Container;
use Tester;

/**
 * TestCase
 */
abstract class TestCase extends Tester\TestCase
{
    //    use MockeryPHPUnitIntegration;

    protected Container $container;

    public function __construct()
    {
        $configurator = new Configurator();

        $config = $configurator->setTempDirectory(TEMP_DIR);
        $config->addStaticParameters([
            'NESTED_TREE_MYSQL_DB_HOST' => strval(getenv('NESTED_TREE_MYSQL_DB_HOST')) ?: '127.0.0.1',
            'NESTED_TREE_MYSQL_DB_NAME' => strval(getenv('NESTED_TREE_MYSQL_DB_NAME')) ?: 'testing',
            'NESTED_TREE_MYSQL_DB_USER' => strval(getenv('NESTED_TREE_MYSQL_DB_USER')) ?: 'testing',
            'NESTED_TREE_MYSQL_DB_PASS' => strval(getenv('NESTED_TREE_MYSQL_DB_PASS')) ?: null,
        ]);
        $config->addConfig(__DIR__ . DIRECTORY_SEPARATOR . 'config.neon');

        $this->container = $config->createContainer();
    }

    protected function compareNodes(
        Support\Node $storedNode,
        Support\Node $mockNode,
        bool $alsoChildren = false,
        bool $checkLeftRight = false,
        bool $checkPosition = false,
    ) : bool {
        return (
            $storedNode->id === $mockNode->id
            && $storedNode->parentId === $mockNode->parentId
            && $storedNode->name === $mockNode->name
            && ($alsoChildren ? ($this->sortIds($storedNode->childrenIds) === $this->sortIds($mockNode->childrenIds)) : true)
            && ($checkLeftRight ? ($storedNode->left === $mockNode->left) : true)
            && ($checkLeftRight ? ($storedNode->right === $mockNode->right) : true)
            && ($checkPosition ? ($storedNode->position === $mockNode->position) : true)
        );
    }

    protected function sortIds(array $data) : array
    {
        sort($data);

        return array_values($data);
    }

    protected function getRow(Explorer $database, Support\TableSettings $settings, int $rowId) : ?Row
    {
        $sql = 'SELECT * FROM ' . $settings->tableName . ' WHERE ' . $settings->idColumnName . ' = ?';
        return $database->query($sql, $rowId)->fetch();
    }
}
