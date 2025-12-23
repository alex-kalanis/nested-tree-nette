<?php

namespace kalanis\nested_tree_nette\Sources\Nette;

use kalanis\nested_tree\Sources\SourceInterface;
use kalanis\nested_tree\Support;
use Nette\Database\Explorer;
use Nette\Database\Row;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

class MySql implements SourceInterface
{
    use Support\ColumnsTrait;

    public function __construct(
        protected readonly Explorer $database,
        protected readonly Support\Node $nodeBase,
        protected readonly Support\TableSettings $settings,
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function selectLastPosition(?Support\Node $parentNode, ?Support\Conditions $where) : ?int
    {
        $sql = 'SELECT `' . $this->settings->idColumnName . '`, `' . $this->settings->parentIdColumnName . '`, `' . $this->settings->positionColumnName . '`'
            . ' FROM `' . $this->settings->tableName . '`'
            . ' WHERE TRUE';
        if (is_null($parentNode?->id)) {
            $sql .= ' AND `' . $this->settings->parentIdColumnName . '` IS NULL';
        } else {
            $sql .= ' AND `' . $this->settings->parentIdColumnName . '` = ?';
        }
        $params = [];
        if (!is_null($parentNode?->id)) {
            $params[] = $parentNode->id;
        }
        $sql .= $this->addCustomQuerySql($params, $where, '');
        $sql .= $this->addSoftDeleteSql();
        $sql .= ' ORDER BY `' . $this->settings->positionColumnName . '` DESC';

        $row = $this->database->fetch($sql, ...$params);

        if (!empty($row)) {
            return is_null($row[$this->settings->positionColumnName]) ? null : max(1, intval($row[$this->settings->positionColumnName]));
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function selectSimple(Support\Options $options) : array
    {
        $params = [];
        $sql = 'SELECT node.`' . $this->settings->idColumnName . '`'
            . ', node.`' . $this->settings->parentIdColumnName . '`'
            . ', node.`' . $this->settings->leftColumnName . '`'
            . ', node.`' . $this->settings->rightColumnName . '`'
            . ', node.`' . $this->settings->levelColumnName . '`'
            . ', node.`' . $this->settings->positionColumnName . '`'
        ;
        $sql .= $this->addAdditionalColumnsSql($options, 'node.');
        $sql .= ' FROM `' . $this->settings->tableName . '` node';
        $sql .= ' WHERE TRUE';
        $sql .= $this->addCurrentIdSql($params, $options, 'node.');
        $sql .= $this->addCustomQuerySql($params, $options->where, 'node.');
        $sql .= $this->addSoftDeleteSql('node.');
        $sql .= ' ORDER BY node.`' . $this->settings->positionColumnName . '` ASC';

        $result = $this->database->fetchAll($sql, ...$params);

        return $result ? $this->fromDbRows($result) : [];
    }

    /**
     * {@inheritdoc}
     */
    public function selectParent(Support\Node $node, Support\Options $options) : ?Support\Node
    {
        if (is_null($node->parentId)) {
            return null;
        }

        $selection = $this->database
            ->table($this->settings->tableName)
            ->where([$this->settings->idColumnName => $node->parentId]);
        $this->addCustomQuery($selection, $options->where, '');
        $this->addSoftDelete($selection);
        $row = $selection->fetch();
        $parentNode = !empty($row) ? $this->fillDataFromRow($row) : null;

        if (empty($parentNode)) {
            return $this->settings->rootIsNull ? null : new Support\Node();
        }

        return $parentNode;
    }

    public function selectCount(Support\Options $options) : int
    {
        $sql = 'SELECT ';
        $sql .= ' ANY_VALUE(parent.' . $this->settings->idColumnName . ') AS p_cid';
        $sql .= ', ANY_VALUE(parent.' . $this->settings->parentIdColumnName . ') AS p_pid';
        if ($this->settings->softDelete) {
            $sql .= ', ANY_VALUE(parent.' . $this->settings->softDelete->columnName . ')';
        }
        if (!is_null($options->currentId) || !is_null($options->parentId) || !empty($options->search) || $options->joinChild) {
            $sql .= ', ANY_VALUE(child.' . $this->settings->idColumnName . ') AS `' . $this->settings->idColumnName . '`';
            $sql .= ', ANY_VALUE(child.' . $this->settings->parentIdColumnName . ') AS `' . $this->settings->parentIdColumnName . '`';
            $sql .= ', ANY_VALUE(child.' . $this->settings->leftColumnName . ') AS `' . $this->settings->leftColumnName . '`';
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
        $sql .= ' ANY_VALUE(parent.' . $this->settings->idColumnName . ') AS p_pid';
        $sql .= ', ANY_VALUE(parent.' . $this->settings->parentIdColumnName . ') AS p_cid';
        $sql .= ', ANY_VALUE(parent.' . $this->settings->leftColumnName . ') AS p_plt';
        if (!is_null($options->currentId) || !is_null($options->parentId) || !empty($options->search) || $options->joinChild) {
            $sql .= ', ANY_VALUE(child.' . $this->settings->idColumnName . ') AS `' . $this->settings->idColumnName . '`';
            $sql .= ', ANY_VALUE(child.' . $this->settings->parentIdColumnName . ') AS `' . $this->settings->parentIdColumnName . '`';
            $sql .= ', ANY_VALUE(child.' . $this->settings->leftColumnName . ') AS `' . $this->settings->leftColumnName . '`';
            $sql .= ', ANY_VALUE(child.' . $this->settings->rightColumnName . ') AS `' . $this->settings->rightColumnName . '`';
            $sql .= ', ANY_VALUE(child.' . $this->settings->levelColumnName . ') AS `' . $this->settings->levelColumnName . '`';
            $sql .= ', ANY_VALUE(child.' . $this->settings->positionColumnName . ') AS `' . $this->settings->positionColumnName . '`';
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
        $sql .= ' ANY_VALUE(parent.' . $this->settings->idColumnName . ') AS `' . $this->settings->idColumnName . '`';
        $sql .= ', ANY_VALUE(parent.' . $this->settings->parentIdColumnName . ') AS `' . $this->settings->parentIdColumnName . '`';
        $sql .= ', ANY_VALUE(parent.' . $this->settings->leftColumnName . ') AS `' . $this->settings->leftColumnName . '`';
        $sql .= ', ANY_VALUE(parent.' . $this->settings->rightColumnName . ') AS `' . $this->settings->rightColumnName . '`';
        $sql .= ', ANY_VALUE(parent.' . $this->settings->levelColumnName . ') AS `' . $this->settings->levelColumnName . '`';
        $sql .= ', ANY_VALUE(parent.' . $this->settings->positionColumnName . ') AS `' . $this->settings->positionColumnName . '`';
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

    public function add(Support\Node $node, ?Support\Conditions $where = null) : Support\Node
    {
        $pairs = [];
        foreach ((array) $node as $column => $value) {
            if (
                !is_numeric($column)
                && !$this->isColumnNameFromBasic($column)
            ) {
                $translateColumn = $this->translateColumn($this->settings, $column);
                if (!is_null($translateColumn)) {
                    $pairs[$translateColumn] = $value;
                }
            }
        }

        $row = $this->database
            ->table($this->settings->tableName)
            ->insert($pairs);
        $node = !empty($row) && is_object($row) ? $this->fillDataFromRow($row) : null;

        if (is_null($node)) {
            // @codeCoverageIgnoreStart
            // when this happens it is problem with DB, not with library
            throw new \RuntimeException('Node not found in database');
        }
        // @codeCoverageIgnoreEnd

        return $node;
    }

    public function updateData(Support\Node $node, ?Support\Conditions $where = null) : bool
    {
        $pairs = [];
        foreach ((array) $node as $column => $value) {
            if (
                !is_numeric($column)
                && !$this->isColumnNameFromBasic($column)
                && !$this->isColumnNameFromTree($column)
            ) {
                $translateColumn = $this->translateColumn($this->settings, $column);
                if (!is_null($translateColumn)) {
                    $pairs[$translateColumn] = $value;
                }
            }
        }

        $count = $this->database
            ->table($this->settings->tableName)
            ->where([$this->settings->idColumnName => $node->id])
            ->update($pairs);

        return !empty($count);
    }

    /**
     * {@inheritdoc}
     */
    public function updateNodeParent(Support\Node $node, ?Support\Node $parent, int $position, ?Support\Conditions $where) : bool
    {
        $parent = $parent ?: ($this->settings->rootIsNull ? null : new Support\Node());

        $selection = $this->database
            ->table($this->settings->tableName)
            ->where([$this->settings->idColumnName => $node->id]);
        $this->addCustomQuery($selection, $where, '');
        $this->addSoftDelete($selection);

        $counter = $selection->update([
            $this->settings->parentIdColumnName => $parent?->id,
            $this->settings->positionColumnName => $position,
        ]);

        return !empty($counter);
    }

    /**
     * {@inheritdoc}
     */
    public function updateChildrenParent(Support\Node $node, ?Support\Node $parent, ?Support\Conditions $where) : bool
    {
        $parent = $parent ?: ($this->settings->rootIsNull ? null : new Support\Node());

        $selection = $this->database
            ->table($this->settings->tableName)
            ->where([$this->settings->parentIdColumnName => $node->id]);
        $this->addCustomQuery($selection, $where, '');
        $this->addSoftDelete($selection);

        $counter = $selection->update([
            $this->settings->parentIdColumnName => $parent?->id,
        ]);

        return !empty($counter);
    }

    public function updateLeftRightPos(Support\Node $row, ?Support\Conditions $where) : bool
    {
        $selection = $this->database
            ->table($this->settings->tableName)
            ->where([$this->settings->idColumnName => $row->id]);
        $this->addCustomQuery($selection, $where, '');
        $this->addSoftDelete($selection);

        $counter = $selection->update([
            $this->settings->levelColumnName => $row->level,
            $this->settings->leftColumnName => $row->left,
            $this->settings->rightColumnName => $row->right,
            $this->settings->positionColumnName => $row->position,
        ]);

        return !empty($counter);
    }

    /**
     * {@inheritdoc}
     */
    public function makeHole(?Support\Node $parent, int $position, bool $moveUp, ?Support\Conditions $where = null) : bool
    {
        $direction = $moveUp ? '-' : '+';
        $compare = $moveUp ? '<=' : '>=';
        $sql = 'UPDATE ' . $this->settings->tableName;
        $sql .= ' SET `' . $this->settings->positionColumnName . '` = `' . $this->settings->positionColumnName . '` ' . $direction . ' 1';
        if (is_null($parent)) {
            $sql .= ' WHERE ' . $this->settings->parentIdColumnName . ' IS NULL';
        } else {
            $sql .= ' WHERE ' . $this->settings->parentIdColumnName . ' = ?';
        }
        $sql .= ' AND `' . $this->settings->positionColumnName . '` ' . $compare . ' ?';
        $sql .= $this->addSoftDeleteSql();
        $params = [];
        if (!is_null($parent)) {
            $params[] = $parent->id;
        }
        $params[] = $position;

        $sql .= $this->addCustomQuerySql($params, $where, '');

        $this->database->fetch($sql, ...$params);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteSolo(Support\Node $node, ?Support\Conditions $where) : bool
    {
        $selection = $this->database
            ->table($this->settings->tableName)
            ->where([$this->settings->idColumnName => $node->id]);
        $this->addCustomQuery($selection, $where, '');
        $this->addSoftDelete($selection);
        $counter = $selection->delete();

        return !empty($counter);
    }

    /**
     * @param array<Row|ActiveRow> $rows
     * @return array<int<0, max>, Support\Node>
     */
    protected function fromDbRows(array $rows) : array
    {
        $result = [];
        foreach ($rows as &$row) {
            $data = $this->fillDataFromRow($row);
            $result[$data->id] = $data;
        }

        return $result;
    }

    protected function fillDataFromRow(Row|ActiveRow $row) : Support\Node
    {
        $data = clone $this->nodeBase;
        foreach ($row as $k => $v) {
            if ($this->settings->idColumnName === $k) {
                $data->id = max(0, intval($v));
            } elseif ($this->settings->parentIdColumnName === $k) {
                $data->parentId = is_null($v) && $this->settings->rootIsNull ? null : max(0, intval($v));
            } elseif ($this->settings->levelColumnName === $k) {
                $data->level = max(0, intval($v));
            } elseif ($this->settings->leftColumnName === $k) {
                $data->left = max(0, intval($v));
            } elseif ($this->settings->rightColumnName === $k) {
                $data->right = max(0, intval($v));
            } elseif ($this->settings->positionColumnName === $k) {
                $data->position = max(0, intval($v));
            } else {
                $data->{$k} = strval($v);
            }
        }

        return $data;
    }

    protected function bindToQuery(string $string) : string
    {
        return strval(preg_replace('#(:[^\s]+)#', '?', $string));
    }

    protected function replaceColumns(string $query, string $byWhat = '') : string
    {
        foreach (['`parent`.', '`child`.', '`node`.', 'parent.', 'child.', 'node.'] as $toReplace) {
            $query = str_replace($toReplace, $byWhat, $query);
        }

        return $query;
    }

    /**
     * @param Selection<ActiveRow> $selection
     * @param Support\Options $options
     * @param string|null $replaceName
     * @return void
     */
    protected function addAdditionalColumns(Selection $selection, Support\Options $options, ?string $replaceName = null) : void
    {
        $columns = [];
        if (!empty($options->additionalColumns)) {
            foreach ($options->additionalColumns as $column) {
                $columns[] = (!is_null($replaceName) ? $this->replaceColumns($column, $replaceName) : $column);
            }
        }
        if (!empty($columns)) {
            $selection->select(implode(', ', $columns));
        }
    }

    /**
     * @param Support\Options $options
     * @param string|null $replaceName
     * @return string
     */
    protected function addAdditionalColumnsSql(Support\Options $options, ?string $replaceName = null) : string
    {
        $sql = '';
        if (!empty($options->additionalColumns)) {
            foreach ($options->additionalColumns as $column) {
                $sql .= ', ' . (!is_null($replaceName) ? $this->replaceColumns($column, $replaceName) : $column);
            }
        }

        return $sql;
    }

    /**
     * @param array<mixed> $params
     * @param Support\Options $options
     * @return string
     */
    protected function addFilterBySql(array &$params, Support\Options $options) : string
    {
        $sql = '';
        if (!empty($options->filterIdBy)) {
            // Nette and other serious frameworks can bind directly, just kick out non-usable params
            $entries = $this->filteredEntries($options->filterIdBy);
            $positions = implode(',', array_fill(1, count($entries), '?'));
            $sql .= ' AND parent.' . $this->settings->idColumnName . ' IN (' . $positions . ')';
            $params = array_merge($params, array_values($entries));
        }

        return $sql;
    }

    /**
     * @param Selection<ActiveRow> $selection
     * @param Support\Options $options
     * @param string $dbPrefix
     * @return void
     */
    protected function addCurrentId(Selection $selection, Support\Options $options, string $dbPrefix = '') : void
    {
        if (!is_null($options->currentId)) {
            $selection->where([$dbPrefix . $this->settings->idColumnName => $options->currentId]);
        }
    }

    /**
     * @param array<mixed> $params
     * @param Support\Options $options
     * @param string $dbPrefix
     * @return string
     */
    protected function addCurrentIdSql(array &$params, Support\Options $options, string $dbPrefix = '') : string
    {
        $sql = '';
        if (!is_null($options->currentId)) {
            $sql .= ' AND ' . $dbPrefix . $this->settings->idColumnName . ' = ?';
            $params[] = $options->currentId;
        }

        return $sql;
    }

    /**
     * @param array<mixed> $params
     * @param Support\Options $options
     * @param string $dbPrefix
     * @return string
     */
    protected function addParentIdSql(array &$params, Support\Options $options, string $dbPrefix = '') : string
    {
        $sql = '';
        if (!is_null($options->parentId)) {
            $sql .= ' AND ' . $dbPrefix . $this->settings->parentIdColumnName . ' = ?';
            $params[] = $options->parentId;
        }

        return $sql;
    }

    /**
     * @param array<mixed> $params
     * @param Support\Options $options
     * @param string $dbPrefix
     * @return string
     */
    protected function addSearchSql(array &$params, Support\Options $options, string $dbPrefix = '') : string
    {
        $sql = '';
        if (
            !empty($options->search->columns)
            && !empty($options->search->value)
        ) {
            $sql .= ' AND (';
            $array_keys = array_keys($options->search->columns);
            $last_array_key = array_pop($array_keys);
            foreach ($options->search->columns as $key => $column) {
                $sql .= $dbPrefix . $column . ' LIKE ?';
                $params[] = '%' . $options->search->value . '%';
                if ($key !== $last_array_key) {
                    $sql .= ' OR ';
                }
            }
            $sql .= ')';
        }

        return $sql;
    }

    /**
     * @param Selection<ActiveRow> $selection
     * @param Support\Conditions|null $where
     * @param string|null $replaceName
     * @return void
     */
    protected function addCustomQuery(Selection $selection, ?Support\Conditions $where, ?string $replaceName = null) : void
    {
        if (!empty($where->query)) {
            $name = (!is_null($replaceName) ? $this->replaceColumns($where->query, $replaceName) : $where->query);
            $selection->where($this->bindToQuery($name), ...array_values($where->bindValues ?? []));
        }
    }

    /**
     * @param array<mixed> $params
     * @param Support\Conditions|null $where
     * @param string|null $replaceName
     * @return string
     */
    protected function addCustomQuerySql(array &$params, ?Support\Conditions $where, ?string $replaceName = null) : string
    {
        $sql = '';
        if (!empty($where->query)) {
            $sql .= ' AND ' . (!is_null($replaceName) ? $this->replaceColumns($where->query, $replaceName) : $where->query);
            $params = array_merge($params, array_values($where->bindValues ?? []));
        }

        return $sql;
    }

    /**
     * @param Selection<ActiveRow> $selection
     * @param string $dbPrefix
     * @return void
     */
    protected function addSoftDelete(Selection $selection, string $dbPrefix = '') : void
    {
        if (!is_null($this->settings->softDelete)) {
            $selection->where([
                ($dbPrefix ? $dbPrefix . '.' : '') . $this->settings->softDelete->columnName => $this->settings->softDelete->canUse,
            ]);
        }
    }

    /**
     * @param string $dbPrefix
     * @return string
     */
    protected function addSoftDeleteSql(string $dbPrefix = '') : string
    {
        $sql = '';
        if (!is_null($this->settings->softDelete)) {
            $sql .= ' AND ' . $dbPrefix . '`' . $this->settings->softDelete->columnName . '` = ' . $this->settings->softDelete->canUse;
        }

        return $sql;
    }

    /**
     * @param array<mixed> $params
     * @param Support\Options $options
     * @return string
     */
    protected function addSortingSql(array &$params, Support\Options $options) : string
    {
        $sql = '';
        if (!$options->noSortOrder) {
            if (!is_null($options->currentId) || !is_null($options->parentId) || !empty($options->search) || $options->joinChild) {
                $sql .= ' GROUP BY child.' . $this->settings->idColumnName;
                $order_by = 'child.' . $this->settings->leftColumnName . ' ASC';
            } elseif (!empty($options->filterIdBy)) {
                $entries = $this->filteredEntries($options->filterIdBy);
                $nodeIdIn = implode(',', array_fill(1, count($entries), '?'));
                $order_by = 'FIELD(' . $this->settings->idColumnName . ',' . $nodeIdIn . ')';
                $params = array_merge($params, array_values($entries));
            } else {
                $order_by = 'parent.' . $this->settings->leftColumnName . ' ASC';
            }
            $sql .= ' ORDER BY ' . $order_by;
        } elseif ($options->joinChild) {
            $sql .= ' GROUP BY ' . $this->settings->idColumnName;
        }

        return $sql;
    }

    /**
     * @param array<mixed> $entries
     * @return array<string|int|float|null>
     */
    protected function filteredEntries(array $entries) : array
    {
        return array_filter($entries, function ($entry) : bool {
            return is_null($entry) || is_numeric($entry) || is_string($entry);
        });
    }
}
