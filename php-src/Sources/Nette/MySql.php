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
    public function selectLastPosition(?int $parentNodeId, ?Support\Conditions $where) : ?int
    {
        $selection = $this->database
            ->table($this->settings->tableName)
            ->where([$this->settings->parentIdColumnName => $parentNodeId]);
        $this->addCustomQuery($selection, $where);
        $result = $selection
            ->order($this->settings->positionColumnName . ' DESC')
            ->fetch();

        return !empty($result) ? max(1, intval($result->{$this->settings->positionColumnName})) : null;
    }

    /**
     * {@inheritdoc}
     */
    public function selectSimple(Support\Options $options) : array
    {
        $selection = $this->database
            ->table($this->settings->tableName);
        $this->addCurrentId($selection, $options);
        $this->addCustomQuery($selection, $options->where);
        $result = $selection
            ->order($this->settings->positionColumnName . ' ASC')
            ->fetchAll();

        return $result ? $this->fromDbRows($result) : [];
    }

    /**
     * {@inheritdoc}
     */
    public function selectParent(int $nodeId, Support\Options $options) : ?int
    {
        $selection = $this->database
            ->table($this->settings->tableName)
            ->where([$this->settings->idColumnName => $nodeId]);
        $this->addCustomQuery($selection, $options->where, '');
        $row = $selection->fetch();
        $parent_id = !empty($row) ? $row->{$this->settings->parentIdColumnName} : null;

        return (empty($parent_id)) ? ($this->settings->rootIsNull ? null : 0) : max(0, intval($parent_id));
    }

    public function selectCount(Support\Options $options) : int
    {
        $sql = 'SELECT ';
        $sql .= ' ANY_VALUE(parent.' . $this->settings->idColumnName . ') AS p_cid';
        $sql .= ', ANY_VALUE(parent.' . $this->settings->parentIdColumnName . ') AS p_pid';
        if (!is_null($options->currentId) || !is_null($options->parentId) || !empty($options->search) || $options->joinChild) {
            $sql .= ', ANY_VALUE(child.' . $this->settings->idColumnName . ') AS `' . $this->settings->idColumnName . '`';
            $sql .= ', ANY_VALUE(child.' . $this->settings->parentIdColumnName . ') AS `' . $this->settings->parentIdColumnName . '`';
            $sql .= ', ANY_VALUE(child.' . $this->settings->leftColumnName . ') AS `' . $this->settings->leftColumnName . '`';
        }
        $sql .= $this->addAdditionalColumns($options);
        $sql .= ' FROM ' . $this->settings->tableName . ' AS parent';

        if (!is_null($options->currentId) || !is_null($options->parentId) || !empty($options->search) || $options->joinChild) {
            // if there is filter or search, there must be inner join to select all of filtered children.
            $sql .= ' INNER JOIN ' . $this->settings->tableName . ' AS child';
            $sql .= ' ON child.' . $this->settings->leftColumnName . ' BETWEEN parent.' . $this->settings->leftColumnName . ' AND parent.' . $this->settings->rightColumnName;
        }

        $sql .= ' WHERE 1';
        $params = [];
        $sql .= $this->addFilterBySql($params, $options);
        $sql .= $this->addCurrentIdSql($params, $options, 'parent.');
        $sql .= $this->addParentIdSql($params, $options, 'parent.');
        $sql .= $this->addSearchSql($params, $options, 'parent.');
        $sql .= $this->addCustomQuerySql($params, $options->where);
        $sql .= $this->addSortingSql($params, $options);
        /** @var literal-string $sql */

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
        $sql .= $this->addAdditionalColumns($options);
        $sql .= ' FROM ' . $this->settings->tableName . ' AS parent';

        if (!is_null($options->currentId) || !is_null($options->parentId) || !empty($options->search) || $options->joinChild) {
            // if there is filter or search, there must be inner join to select all of filtered children.
            $sql .= ' INNER JOIN ' . $this->settings->tableName . ' AS child';
            $sql .= ' ON child.' . $this->settings->leftColumnName . ' BETWEEN parent.' . $this->settings->leftColumnName . ' AND parent.' . $this->settings->rightColumnName;
        }

        $sql .= ' WHERE 1';
        $params = [];
        $sql .= $this->addFilterBySql($params, $options);
        $sql .= $this->addCurrentIdSql($params, $options, 'parent.');
        $sql .= $this->addParentIdSql($params, $options, 'parent.');
        $sql .= $this->addSearchSql($params, $options, 'parent.');
        $sql .= $this->addCustomQuerySql($params, $options->where);
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

        /** @var literal-string $sql */
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
        $sql .= $this->addAdditionalColumns($options);
        $sql .= ' FROM ' . $this->settings->tableName . ' AS node,';
        $sql .= ' ' . $this->settings->tableName . ' AS parent';
        $sql .= ' WHERE';
        $sql .= ' (node.' . $this->settings->leftColumnName . ' BETWEEN parent.' . $this->settings->leftColumnName . ' AND parent.' . $this->settings->rightColumnName . ')';
        $sql .= $this->addCurrentIdSql($params, $options, 'node.');
        $sql .= $this->addSearchSql($params, $options, 'node.');
        $sql .= $this->addCustomQuerySql($params, $options->where);
        $sql .= ' GROUP BY parent.`' . $this->settings->idColumnName . '`';
        $sql .= ' ORDER BY parent.`' . $this->settings->leftColumnName . '`';

        /** @var literal-string $sql */
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
            if (!is_numeric($column) && !$this->isColumnNameFromBasic($column)) {
                $translateColumn = $this->translateColumn($this->settings, $column);
                $pairs[$translateColumn] = $value;
            }
        }

        $row = $this->database
            ->table($this->settings->tableName)
            ->insert($pairs);
        $node = !empty($row) && is_object($row) && (is_a($row, ActiveRow::class) || is_a($row, Row::class)) ? $this->fillDataFromRow($row) : null;

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
                && !is_null($value)
            ) {
                $translateColumn = $this->translateColumn($this->settings, $column);
                $pairs[$translateColumn] = $value;
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
    public function updateNodeParent(int $nodeId, ?int $parentId, int $position, ?Support\Conditions $where) : bool
    {
        $selection = $this->database
            ->table($this->settings->tableName)
            ->where([$this->settings->idColumnName => $nodeId]);
        $this->addCustomQuery($selection, $where, '');

        $counter = $selection->update([
            $this->settings->parentIdColumnName => $parentId,
            $this->settings->positionColumnName => $position,
        ]);

        return !empty($counter);
    }

    /**
     * {@inheritdoc}
     */
    public function updateChildrenParent(int $nodeId, ?int $parentId, ?Support\Conditions $where) : bool
    {
        $selection = $this->database
            ->table($this->settings->tableName)
            ->where([$this->settings->parentIdColumnName => $nodeId]);
        $this->addCustomQuery($selection, $where, '');

        $counter = $selection->update([
            $this->settings->parentIdColumnName => $parentId,
        ]);

        return !empty($counter);
    }

    public function updateLeftRightPos(Support\Node $row, ?Support\Conditions $where) : bool
    {
        $selection = $this->database
            ->table($this->settings->tableName)
            ->where([$this->settings->idColumnName => $row->id]);
        $this->addCustomQuery($selection, $where, '');

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
    public function makeHole(?int $parentId, int $position, bool $moveUp, ?Support\Conditions $where = null) : bool
    {
        $direction = $moveUp ? '-' : '+';
        $compare = $moveUp ? '<=' : '>=';
        $sql = 'UPDATE ' . $this->settings->tableName;
        $sql .= ' SET ' . $this->settings->positionColumnName . ' = ' . $this->settings->positionColumnName . ' ' . $direction . ' 1';
        $sql .= ' WHERE ' . $this->settings->parentIdColumnName . ' = ?';
        $sql .= ' AND ' . $this->settings->positionColumnName . ' ' . $compare . ' ?';
        $params = [
            $parentId,
            $position,
        ];

        $sql .= $this->addCustomQuerySql($params, $where, '');

        /* @var literal-string $sql */
        $this->database->fetch($sql, ...$params);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteSolo(int $nodeId, ?Support\Conditions $where) : bool
    {
        $selection = $this->database
            ->table($this->settings->tableName)
            ->where([$this->settings->idColumnName => $nodeId]);
        $this->addCustomQuery($selection, $where, '');
        $counter = $selection->delete();

        return !empty($counter);
    }

    public function deleteWithChildren(Support\Node $row, ?Support\Conditions $where) : bool
    {
        $selection = $this->database
            ->table($this->settings->tableName)
            ->where([$this->settings->idColumnName => $row->id]);
        $this->addCustomQuery($selection, $where, '');
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
        foreach (['`parent`.', '`child`.', 'parent.', 'child.'] as $toReplace) {
            $query = str_replace($toReplace, $byWhat, $query);
        }

        return $query;
    }

    protected function addAdditionalColumns(Support\Options $options, ?string $replaceName = null) : string
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
