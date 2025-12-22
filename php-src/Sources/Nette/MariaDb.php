<?php

namespace kalanis\nested_tree_nette\Sources\Nette;

use kalanis\nested_tree\Support;

/**
 * Implementation without ANY_VALUE which will cause problems on MariaDB servers
 * @codeCoverageIgnore cannot connect both MySQL and MariaDB and set the ONLY_FULL_GROUP_BY variable out on Maria.
 * Both Github and Scrutinizer have this problem.
 */
class MariaDb extends MySql
{
    public function selectCount(Support\Options $options) : int
    {
        $sql = 'SELECT ';
        $sql .= ' parent.' . $this->settings->idColumnName . ' AS p_cid';
        $sql .= ', parent.' . $this->settings->parentIdColumnName . ' AS p_pid';
        if ($this->settings->softDelete) {
            $sql .= ', parent.' . $this->settings->softDelete->columnName;
        }
        if (!is_null($options->currentId) || !is_null($options->parentId) || !empty($options->search) || $options->joinChild) {
            $sql .= ', child.' . $this->settings->idColumnName . ' AS `' . $this->settings->idColumnName . '`';
            $sql .= ', child.' . $this->settings->parentIdColumnName . ' AS `' . $this->settings->parentIdColumnName . '`';
            $sql .= ', child.' . $this->settings->leftColumnName . ' AS `' . $this->settings->leftColumnName . '`';
        }
        $sql .= $this->addAdditionalColumnsSql($options);
        $sql .= ' FROM ' . $this->settings->tableName . ' AS parent';

        if (!is_null($options->currentId) || !is_null($options->parentId) || !empty($options->search) || $options->joinChild) {
            // if there is filter or search, there must be inner join to select all of filtered children.
            $sql .= ' INNER JOIN ' . $this->settings->tableName . ' AS child';
            $sql .= ' ON child.' . $this->settings->leftColumnName . ' BETWEEN parent.' . $this->settings->leftColumnName . ' AND parent.' . $this->settings->rightColumnName;
        }

        $sql .= ' WHERE TRUE';
        $params = [];
        $sql .= $this->addFilterBySql($params, $options);
        $sql .= $this->addCurrentIdSql($params, $options, 'parent.');
        $sql .= $this->addParentIdSql($params, $options, 'parent.');
        $sql .= $this->addSearchSql($params, $options, 'parent.');
        $sql .= $this->addCustomQuerySql($params, $options->where);
        $sql .= $this->addSoftDeleteSql('parent.');
        $sql .= $this->addSortingSql($params, $options);

        // get 'total' count.
        $result = $this->database->fetchAll($sql, ...$params);

        // "a bit" hardcore - get all lines and then count them
        return $result ? count($result) : 0;
    }

    public function selectLimited(Support\Options $options) : array
    {
        $sql = 'SELECT';
        $sql .= ' parent.' . $this->settings->idColumnName . ' AS p_pid';
        $sql .= ', parent.' . $this->settings->parentIdColumnName . ' AS p_cid';
        $sql .= ', parent.' . $this->settings->leftColumnName . ' AS p_plt';
        if (!is_null($options->currentId) || !is_null($options->parentId) || !empty($options->search) || $options->joinChild) {
            $sql .= ', child.' . $this->settings->idColumnName . ' AS `' . $this->settings->idColumnName . '`';
            $sql .= ', child.' . $this->settings->parentIdColumnName . ' AS `' . $this->settings->parentIdColumnName . '`';
            $sql .= ', child.' . $this->settings->leftColumnName . ' AS `' . $this->settings->leftColumnName . '`';
            $sql .= ', child.' . $this->settings->rightColumnName . ' AS `' . $this->settings->rightColumnName . '`';
            $sql .= ', child.' . $this->settings->levelColumnName . ' AS `' . $this->settings->levelColumnName . '`';
            $sql .= ', child.' . $this->settings->positionColumnName . ' AS `' . $this->settings->positionColumnName . '`';
        }
        $sql .= $this->addAdditionalColumnsSql($options);
        $sql .= ' FROM ' . $this->settings->tableName . ' AS parent';

        if (!is_null($options->currentId) || !is_null($options->parentId) || !empty($options->search) || $options->joinChild) {
            // if there is filter or search, there must be inner join to select all of filtered children.
            $sql .= ' INNER JOIN ' . $this->settings->tableName . ' AS child';
            $sql .= ' ON child.' . $this->settings->leftColumnName . ' BETWEEN parent.' . $this->settings->leftColumnName . ' AND parent.' . $this->settings->rightColumnName;
        }

        $sql .= ' WHERE TRUE';
        $params = [];
        $sql .= $this->addFilterBySql($params, $options);
        $sql .= $this->addCurrentIdSql($params, $options, 'parent.');
        $sql .= $this->addParentIdSql($params, $options, 'parent.');
        $sql .= $this->addSearchSql($params, $options, 'parent.');
        $sql .= $this->addCustomQuerySql($params, $options->where);
        $sql .= $this->addSoftDeleteSql('parent.');
        $sql .= $this->addSortingSql($params, $options);

        // re-create query and prepare. second step is for set limit and fetch all items.
        if (!$options->unlimited) {
            if (empty($options->offset)) {
                $options->offset = 0;
            }
            if (empty($options->limit) || (10000 < $options->limit)) {
                $options->limit = 20;
            }

            $sql .= ' LIMIT ' . $options->offset . ', ' . $options->limit;
        }

        $result = $this->database->fetchAll($sql, ...$params);

        return $result ? $this->fromDbRows($result) : [];
    }

    /**
     * {@inheritdoc}
     */
    public function selectWithParents(Support\Options $options) : array
    {
        $params = [];
        $sql = 'SELECT';
        $sql .= ' parent.' . $this->settings->idColumnName . ' AS `' . $this->settings->idColumnName . '`';
        $sql .= ', parent.' . $this->settings->parentIdColumnName . ' AS `' . $this->settings->parentIdColumnName . '`';
        $sql .= ', parent.' . $this->settings->leftColumnName . ' AS `' . $this->settings->leftColumnName . '`';
        $sql .= ', parent.' . $this->settings->rightColumnName . ' AS `' . $this->settings->rightColumnName . '`';
        $sql .= ', parent.' . $this->settings->levelColumnName . ' AS `' . $this->settings->levelColumnName . '`';
        $sql .= ', parent.' . $this->settings->positionColumnName . ' AS `' . $this->settings->positionColumnName . '`';
        $sql .= $this->addAdditionalColumnsSql($options);
        $sql .= ' FROM ' . $this->settings->tableName . ' AS node,';
        $sql .= ' ' . $this->settings->tableName . ' AS parent';
        $sql .= ' WHERE';
        $sql .= ' (node.' . $this->settings->leftColumnName . ' BETWEEN parent.' . $this->settings->leftColumnName . ' AND parent.' . $this->settings->rightColumnName . ')';
        $sql .= $this->addCurrentIdSql($params, $options, 'node.');
        $sql .= $this->addSearchSql($params, $options, 'node.');
        $sql .= $this->addCustomQuerySql($params, $options->where);
        $sql .= $this->addSoftDeleteSql('node.');
        $sql .= ' GROUP BY parent.`' . $this->settings->idColumnName . '`';
        $sql .= ' ORDER BY parent.`' . $this->settings->leftColumnName . '`';

        $result = $this->database->fetchAll($sql, ...$params);

        if (empty($result)) {
            return [];
        }
        if ($options->skipCurrent) {
            unset($result[count($result)-1]);
        }

        return $this->fromDbRows($result);
    }
}
