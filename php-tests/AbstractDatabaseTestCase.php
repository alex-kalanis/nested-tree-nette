<?php

namespace Tests;

require_once __DIR__ . DIRECTORY_SEPARATOR . 'TestCase.php';

use Nette\Database\Connection;
use Nette\Database\Explorer;
use Tester\Environment;

/**
 * DatabaseTestCase
 */
abstract class AbstractDatabaseTestCase extends TestCase
{
    protected Connection $connection;
    protected Explorer $dbExplorer;

    /**
     * Setup database
     */
    protected function setUp() : void
    {
        parent::setUp();
        Environment::lock('db', dirname(TEMP_DIR));

        $this->connection = $this->getConnection();
        $this->dbExplorer = $this->getDbExplorer();
    }

    /**
     * Get database connection
     * @return Connection
     */
    protected function getConnection() : Connection
    {
        return $this->container->getByType(Connection::class);
    }

    /**
     * Get database connection
     * @return Explorer
     */
    protected function getDbExplorer() : Explorer
    {
        return $this->container->getByType(Explorer::class);
    }

    /**
     * Empty database
     */
    abstract protected function emptyDatabase() : void;

    /**
     * Create database
     */
    abstract protected function createDatabase() : void;

    /**
     * Test tear down
     */
    protected function tearDown() : void
    {
        parent::tearDown();
        $this->emptyDatabase();
    }
}
