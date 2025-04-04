# Nested Tree - for Nette database

![Build Status](https://github.com/alex-kalanis/nested-tree-nette/actions/workflows/code_checks.yml/badge.svg)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/alex-kalanis/nested-tree-nette/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/alex-kalanis/nested-tree-nette/?branch=master)
[![Latest Stable Version](https://poser.pugx.org/alex-kalanis/nested-tree-nette/v/stable.svg?v=1)](https://packagist.org/packages/alex-kalanis/nested-tree-nette)
[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%208.1-8892BF.svg)](https://php.net/)
[![Downloads](https://img.shields.io/packagist/dt/alex-kalanis/nested-tree-nette.svg?v1)](https://packagist.org/packages/alex-kalanis/nested-tree-nette)
[![License](https://poser.pugx.org/alex-kalanis/nested-tree-nette/license.svg?v=1)](https://packagist.org/packages/alex-kalanis/nested-tree-nette)
[![Code Coverage](https://scrutinizer-ci.com/g/alex-kalanis/nested-tree-nette/badges/coverage.png?b=master&v=1)](https://scrutinizer-ci.com/g/alex-kalanis/nested-tree-nette/?branch=master)

Library to work with Nested tree set. Adapter for [Nette](https://nette.org/) and
its database connection. Extension of [Nested tree](https://github.com/alex-kalanis/nested-tree) package.

## About

This is connection between Nested tree package and Nette framework. It exists due
differences in accessing DB underneath, because Nette has own Database package and
layer and not raw PDO.

## Requirements

* PHP version 8.1 or higher
* Nette database 3.2

## Basic usage

Basic usage is about to same as ```Nested Tree``` package. The only difference is
in datasource.

```php
class MyNodes extends \kalanis\nested_tree\Support\Node
{
    public ?string $my_column = null;
}

class MyTable extends \kalanis\nested_tree\Support\TableSettings
{
    public string $tableName = 'my_menu';
}

$myNodes = new MyNodes();
$myTable = new MyTable();

// this is usually set via DI
$actions = new \kalanis\nested_tree\Actions(
    new \kalanis\nested_tree\NestedSet(
        new \kalanis\nested_tree_nette\Sources\Nette\MySql(
            $netteExplorer,
            $myNodes,
            $myTable,
        ),
        $myNodes,
        $myTable,
    ),
);

// now work:

// repair the whole structure
$actions->fixStructure();

// move node in row
$actions->movePosition(25, 3);

// change parent node for the one chosen
$actions->changeParent(13, 7);
```

## DB structure

Basic usage is about to same as ```Nested Tree``` package.

## Running tests

The package contains tests written in [Nette Tester](https://tester.nette.org/).

* `tester` - runs all tests

## Caveats

As said in ```Nested Tree``` package, you must choose if you go with MariaDB or MySQL,
because default implementation uses function [ANY_VALUE()](https://jira.mariadb.org/browse/MDEV-10426) to go around the
problem with non-standard ```GROUP_BY``` implementation. So you may either use MySQL 5.7+
or disable ```ONLY_FULL_GROUP_BY``` directive in MariaDB. Or write custom query source
which itself will go around this particular problem.
