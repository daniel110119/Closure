<?php

namespace Closure\Traits;


use Closure\Extensions\CollectionExtension;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use think\Model;

trait ClosureTable
{
    /**
     * Eloquent Listener
     */
    public static function init()
    {
        parent::init();

        static::event('after_update', function ($model) {
            $model->updateClosure();
        });

        static::event('after_insert', function ($model) {
            $model->insertClosure($model->getParentKey() ?: 0);
        });

        static::event('after_delete', function ($model) {
            $model->deleteObservers();
        });

        if (method_exists(new static, 'restored')) {
            static::restored(function (Model $model) {
                $model->insertClosure($model->getParentKey() ?: 0);
            });
        }
    }

    public function isDirty($model, $column): bool
    {

    }

    /**
     * Get this relation closure table
     *
     * @return string
     */
    protected function getClosureTable()
    {
        if (!isset($this->closureTable)) {
            return str_replace('\\', '', Str::snake(class_basename($this))) . '_closure';
        }
        return $this->closureTable;
    }

    protected function getPrefixedClosureTable()
    {
        return config('database.prefix') . $this->getClosureTable();
    }

    protected function getPrefixedTable()
    {
        return config('database.prefix') . $this->getTable();
    }

    /**
     * Get this closure table ancestor column
     *
     * @return string
     */
    protected function getAncestorColumn()
    {
        if (!isset($this->ancestorColumn)) {
            return 'ancestor';
        }
        return $this->ancestorColumn;
    }

    /**
     * Get this closure table descendant column
     *
     * @return string
     */
    protected function getDescendantColumn()
    {
        if (!isset($this->descendantColumn)) {
            return 'descendant';
        }
        return $this->descendantColumn;
    }

    /**
     * Get this closure table distance(hierarchy) column
     *
     * @return string
     */
    protected function getDistanceColumn()
    {
        if (!isset($this->distanceColumn)) {
            return 'distance';
        }
        return $this->distanceColumn;
    }

    /**
     * Get key Name
     *
     * @return string
     */
    protected function getKeyName()
    {
        if (!isset($this->keyName)) {
            return 'id';
        }
        return $this->keyName;
    }

    /**
     * Get parent column
     *
     * @return string
     */
    protected function getParentColumn()
    {
        if (!isset($this->parentColumn)) {
            return 'parent';
        }
        return $this->parentColumn;
    }

    /**
     * Get ancestor column with table name
     *
     * @return string
     */
    protected function getQualifiedAncestorColumn()
    {
        return $this->getClosureTable() . '.' . $this->getAncestorColumn();
    }

    /**
     * Get descendant column with table name
     *
     * @return string
     */
    protected function getQualifiedDescendantColumn()
    {
        return $this->getClosureTable() . '.' . $this->getDescendantColumn();
    }

    /**
     * Get Distance column with table name
     *
     * @return string
     */
    protected function getQualifiedDistanceColumn()
    {
        return $this->getClosureTable() . '.' . $this->getDistanceColumn();
    }

    protected function getQualifiedParentColumn()
    {
        return $this->getTable() . '.' . $this->getParentColumn();
    }

    /**
     * @return mixed
     */
    protected function getParentKey()
    {
        $parentColumn = $this->getParentColumn();
        return $this->{$parentColumn};
    }

    /**
     * @param $key
     */
    protected function setParentKey($key)
    {
        $this->setAttr($this->getParentColumn(), $key);
    }

    /**
     * Join closure table
     *
     * @param $column
     * @param bool $withSelf
     * @return mixed
     */
    protected function joinRelationBy($column, $withSelf = false)
    {

        if (!$this->isExists()) {
            throw new ModelNotFoundException();
        }
        $query = null;
        $keyName = $this->getQualifiedKeyName();

        $key = $this->getKey();
        $closureTable = $this->getClosureTable();
        $ancestor = $this->getQualifiedAncestorColumn();
        $descendant = $this->getQualifiedDescendantColumn();
        $distance = $this->getQualifiedDistanceColumn();

        switch ($column) {
            case 'ancestor':
                $query = $this->join($closureTable, $ancestor . '=' . $keyName)
                    ->where($descendant, '=', $key);
                break;

            case 'descendant':
                $query = $this->join($closureTable, $descendant . '=' . $keyName)
                    ->where($ancestor, '=', $key);
                break;
        }

        $operator = ($withSelf === true ? '>=' : '>');
        $query->where($distance, $operator, 0);

        return $query;
    }

    protected function getQualifiedKeyName()
    {
        return $this->getName() . '.' . $this->getPk();
    }

    /**
     * Get self relation
     *
     * @return mixed
     */
    protected function joinRelationSelf()
    {
        if (!$this->isExists()) {
            throw new ModelNotFoundException();
        }

        $keyName = $this->getQualifiedKeyName();
        $key = $this->getKey();
        $closureTable = $this->getClosureTable();
        $ancestor = $this->getQualifiedAncestorColumn();
        $descendant = $this->getQualifiedDescendantColumn();
        $distance = $this->getQualifiedDistanceColumn();

        $query = $this
            ->join($closureTable, "$keyName = $ancestor")
            ->where([
                $ancestor => $key,
                $descendant => $key,
                $distance => 0
            ]);

        return $query;
    }

    /**
     * Get without closure table
     *
     * @return mixed
     */
    protected function joinWithoutClosure()
    {
        $keyName = $this->getQualifiedKeyName();

        $closureTable = $this->getClosureTable();
        $ancestor = $this->getQualifiedAncestorColumn();
        $descendant = $this->getQualifiedDescendantColumn();
        $query = $this
            ->leftJoin($closureTable, $keyName, '=', $ancestor)
            ->whereNull($ancestor)
            ->whereNull($descendant);
        return $query;
    }

    /**
     * @param $query
     * @return mixed
     */
    public function scopeIsolated($query)
    {
        $keyName = $this->getQualifiedKeyName();
        $closureTable = $this->getClosureTable();
        $ancestor = $this->getQualifiedAncestorColumn();
        $descendant = $this->getQualifiedDescendantColumn();
        return $query
            ->leftJoin($closureTable, $keyName, '=', $ancestor)
            ->whereNull($ancestor)
            ->whereNull($descendant);
    }

    /**
     * @param $query
     * @return mixed
     */
    public function scopeOnlyRoot($query)
    {
        $parentColumn = $this->getParentColumn();
        return $query->where($parentColumn, 0)
            ->orWhere(function ($query) use ($parentColumn) {
                $query->whereNull($parentColumn);
            });
    }

    /**
     * Insert node relation to closure table
     *
     * @param int $ancestorId
     * @return void
     */
    protected function insertClosure($ancestorId = 0)
    {
        if (!$this->isExists()) {
            throw new ModelNotFoundException();
        }

        $descendantId = $this->getKey();
        $prefixedTable = $this->getPrefixedClosureTable();
        $ancestorColumn = $this->getAncestorColumn();
        $descendantColumn = $this->getDescendantColumn();
        $distanceColumn = $this->getDistanceColumn();

        $sql = "
            INSERT INTO {$prefixedTable} ({$ancestorColumn}, {$descendantColumn}, {$distanceColumn})
            SELECT tbl.{$ancestorColumn}, {$descendantId}, tbl.{$distanceColumn}+1
            FROM {$prefixedTable} AS tbl
            WHERE tbl.{$descendantColumn} = {$ancestorId}
            UNION
            SELECT {$descendantId}, {$descendantId}, 0
        ";

        \think\Db::connect($this->getConnection())->query($sql);
    }

    /**
     * @param null $with
     * @return bool
     */
    protected function detachSelfRelation($with = null)
    {
        $key = $this->getKey();
        $table = $this->getClosureTable();
        $ancestorColumn = $this->getAncestorColumn();
        $descendantColumn = $this->getDescendantColumn();

        switch ($with) {
            case 'ancestor':
                \think\Db::table($table)->where($descendantColumn, $key)->delete();
                break;
            case 'descendant':

                \think\Db::table($table)->where($ancestorColumn, $key)->delete();
                break;
            default:
                \think\Db::table($table)->where($descendantColumn, $key)->orWhere($ancestorColumn, $key)->delete();
        }
        return true;
    }

    protected function deleteObservers()
    {
        if (!$this->isExists()) {
            throw new ModelNotFoundException();
        }
        $children = $this->getChildren();
        foreach ($children as $child) {
            $child->setParentKey(0);
            $child->save();
        }
        $this->detachRelationships();
        $this->detachSelfRelation();
    }

    /**
     * Unbind self to ancestor and descendants to ancestor relations
     *
     * @return bool
     */
    protected function detachRelationships()
    {
        if (!$this->isExists()) {
            throw new ModelNotFoundException();
        }

        $key = $this->getKey();
        $prefixedTable = $this->getPrefixedClosureTable();
        $ancestorColumn = $this->getAncestorColumn();
        $descendantColumn = $this->getDescendantColumn();

        $sql = "
            DELETE FROM {$prefixedTable}
            WHERE {$descendantColumn} IN (
              SELECT d FROM (
                SELECT {$descendantColumn} as d FROM {$prefixedTable}
                WHERE {$ancestorColumn} = {$key}
              ) as dct
            )
            AND {$ancestorColumn} IN (
              SELECT a FROM (
                SELECT {$ancestorColumn} AS a FROM {$prefixedTable}
                WHERE {$descendantColumn} = {$key}
                AND {$ancestorColumn} <> {$key}
              ) as ct
            )
        ";

        \think\Db::connect($this->getConnection())->query($sql);
        return true;
    }

    /**
     * Associate self to ancestor and descendants to ancestor relations
     *
     * @param int|null $parentKey
     * @return bool
     */
    protected function attachTreeTo($parentKey = 0)
    {
        if (!$this->isExists()) {
            throw new ModelNotFoundException();
        }

        if (is_null($parentKey)) {
            $parentKey = 0;
        }

        $key = $this->getKey();
        $prefixedTable = $this->getPrefixedClosureTable();
        $ancestorColumn = $this->getAncestorColumn();
        $descendantColumn = $this->getDescendantColumn();
        $distanceColumn = $this->getDistanceColumn();

        $sql = "
            INSERT INTO {$prefixedTable} ({$ancestorColumn}, {$descendantColumn}, {$distanceColumn})
            SELECT supertbl.{$ancestorColumn}, subtbl.{$descendantColumn}, supertbl.{$distanceColumn}+subtbl.{$distanceColumn}+1
            FROM (SELECT * FROM {$prefixedTable} WHERE {$descendantColumn} = {$parentKey}) as supertbl
            JOIN {$prefixedTable} as subtbl ON subtbl.{$ancestorColumn} = {$key}
        ";

        \think\Db::connect($this->getConnection())->query($sql);
        return true;
    }

    /**
     * Unbind self and descendants all relations
     *
     * @return bool
     */
    protected function deleteRelationships()
    {
        if (!$this->isExists()) {
            throw new ModelNotFoundException();
        }

        $key = $this->getKey();
        $prefixedTable = $this->getPrefixedClosureTable();
        $ancestorColumn = $this->getAncestorColumn();
        $descendantColumn = $this->getDescendantColumn();
        $sql = "
            DELETE FROM {$prefixedTable}
            WHERE {$descendantColumn} IN (
            SELECT d FROM (
              SELECT {$descendantColumn} as d FROM {$prefixedTable}
                WHERE {$ancestorColumn} = {$key}
              ) as dct
            )
        ";

        \think\Db::connect($this->getConnection())->query($sql);
        return true;
    }

    /**
     * Convert parameter
     *
     * @param $parameter
     * @return Model|null
     */
    protected function parameter2Model($parameter)
    {
        $model = null;
        if ($parameter instanceof Model) {
            $model = $parameter;
        } elseif (is_numeric($parameter)) {
            $model = $this->findOrFail($parameter);
        } else {
            throw (new ModelNotFoundException)->setModel(
                get_class($this->model), $parameter
            );
        }
        return $model;
    }

    /**
     * @throws ClosureTableException
     * @throws \Throwable
     */
    protected function updateClosure()
    {
        if ($this->getParentKey()) {
            $parent = $this->parameter2Model($this->getParentKey());
            $parentKey = $parent->getKey();
        } else {
            $parentKey = 0;
        }

        $ids = $this->getDescendantsAndSelf([$this->getKeyName()])->pluck($this->getKeyName());

        if (in_array($parentKey, $ids)) {
            throw new ClosureTableException('Can\'t move to descendant');
        }
        \think\Db::connect($this->getConnection())->transaction(function () use ($parentKey) {
            if (!$this->detachRelationships()) {
                throw new ClosureTableException('Unbind relationships failed');
            }
            if (!$this->attachTreeTo($parentKey)) {
                throw new ClosureTableException('Associate tree failed');
            }
        });
    }

    /**
     * Delete nonexistent relations
     *
     * @return bool
     */
    public static function deleteRedundancies()
    {
        $instance = new static;

        $segment = '';
        if (method_exists($instance, 'bootSoftDeletes')) {
            $segment = 'OR t.' . $instance->getDeletedAtColumn() . ' IS NOT NULL';
        }
        $table = $instance->getPrefixedTable();
        $prefixedTable = $instance->getPrefixedClosureTable();
        $ancestorColumn = $instance->getAncestorColumn();
        $descendantColumn = $instance->getDescendantColumn();
        $keyName = $instance->getKeyName();
        $sql = "
            DELETE ct FROM {$prefixedTable} ct
            WHERE {$descendantColumn} IN (
              SELECT d FROM (
                SELECT {$descendantColumn} as d FROM {$prefixedTable}
                LEFT JOIN {$table} t 
                ON {$descendantColumn} = t.{$keyName}
                WHERE t.{$keyName} IS NULL {$segment}
              ) as dct
            )
            OR {$ancestorColumn} IN (
              SELECT d FROM (
                SELECT {$ancestorColumn} as d FROM {$prefixedTable}
                LEFT JOIN {$table} t 
                ON {$ancestorColumn} = t.{$keyName}
                WHERE t.{$keyName} IS NULL {$segment}
              ) as act
            )
        ";
        \think\Db::connect($instance->connection)->query($sql);
        return true;
    }

    /**
     * Create a child from Array
     *
     * @param array $attributes
     * @return mixed
     * @throws ClosureTableException
     */
    public function createChild(array $attributes)
    {
        if ($this->joinRelationSelf()->count() === 0) {
            throw new ClosureTableException('Model is not a node');
        }

        $model = null;
        $parentKey = $this->getKey();
        $attributes[$this->getParentColumn()] = $parentKey;
        $model = $this->forceCreate($attributes);
        return $model;
    }

    /**
     * Make this model to root
     *
     * @return bool
     */
    public function makeRoot()
    {
        if ($this->isRoot()) {
            return true;
        }
        $this->setParentKey(0);
        $this->save();
        return true;
    }

    /**
     * Associate a child or children to this model, accept model and string and array
     *
     * @param $children
     * @return bool
     * @throws ClosureTableException
     * @throws \Throwable
     */
    public function addChild($children)
    {

        if ($this->joinRelationSelf()->count() === 0) {
            throw new ClosureTableException('Model is not a node');
        }

        $keyName = $this->getKeyName();
        $key = $this->getKey();
        $ids = $this->getAncestorsAndSelf([$keyName])->pluck($keyName);
        if (!(is_array($children) || $children instanceof Collection)) {
            $children = array($children);
        }

        \think\Db::connect($this->getConnection())->transaction(function () use ($children, $key, $ids) {
            foreach ($children as $child) {
                $model = $this->parameter2Model($child);
                if (in_array($model->getKey(), $ids)) {
                    throw new ClosureTableException('Children can\'t be ancestor');
                }
                $model->setParentKey($key);
                $model->save();
            }
        });

        return true;
    }

    public function getConnection()
    {
        $config = !empty($this->connection) && is_array($this->connection) ?: array_merge(\think\Db::getConfig(), $this->connection);
        return $config;
    }

    /**
     * @param array $attributes
     * @return mixed
     * @throws ClosureTableException
     */
    public function createSibling(array $attributes)
    {
        if ($this->joinRelationSelf()->count() === 0) {
            throw new ClosureTableException('Model is not a node');
        }

        $model = null;
        $parentKey = $this->getParent()->getKey();
        $attributes[$this->getParentColumn()] = $parentKey;
        $model = $this->forceCreate($attributes);
        return $model;
    }

    /**
     * @param $siblings
     * @return bool
     */
    public function addSiblings($siblings)
    {
        $parent = $this->getParent();
        if (!$parent) {
            return false;
        }
        return $parent->addChild($siblings);
    }

    /**
     * @param $parent
     * @return bool
     */
    public function moveTo($parent)
    {
        $model = $this->parameter2Model($parent);
        $this->setParentKey($model->getKey());
        $this->save();

        return true;
    }

    /**
     * fix the model to ancestors relation. If you need to fix all, cycle all
     *
     * @return bool
     * @throws \Throwable
     */
    public function perfectNode()
    {
        $parentKey = $this->getParentKey() ?: 0;
        \think\Db::connect($this->getConnection())->transaction(function () use ($parentKey) {
            $this->detachSelfRelation('ancestor');
            $this->insertClosure($parentKey);
        });
        return true;
    }

    /**
     * Each and fix tree's every item, If your tree too large careful with it
     *
     * @return bool
     */
    public function perfectTree()
    {
        $result = true;

        $this->getDescendants()->each(function ($item) use (&$result) {
            if (!$item->perfectNode()) {
                $result = false;
                return false;
            }
        });
        return $result;
    }

    /**
     * Get ancestors query
     *
     * @return mixed
     */
    public function queryAncestors()
    {
        return $this->joinRelationBy('ancestor');
    }

    /**
     * @param array $columns
     * @return mixed
     */
    public function getAncestors(array $columns = ['*'])
    {
        return $this->queryAncestors()->select();
    }

    /**
     * @return mixed
     */
    public function queryAncestorsAndSelf()
    {
        return $this->joinRelationBy('ancestor', true);
    }

    /**
     * @param array $columns
     * @return mixed
     */
    public function getAncestorsAndSelf(array $columns = ['*'])
    {
        return $this->queryAncestorsAndSelf()->select();
    }

    /**
     * @return mixed
     */
    public function queryDescendants()
    {
        return $this->joinRelationBy('descendant');
    }

    /**
     * @param array $columns
     * @return mixed
     */
    public function getDescendants(array $columns = ['*'])
    {

        return $this->queryDescendants()->select();
    }

    /**
     * @return mixed
     */
    public function queryDescendantsAndSelf()
    {
        return $this->joinRelationBy('descendant', true);
    }

    /**
     * @param array $columns
     * @return mixed
     */
    public function getDescendantsAndSelf(array $columns = ['*'])
    {
        return $this->queryDescendantsAndSelf()->select();
    }

    /**
     * @return mixed
     */
    public function queryBesides()
    {
        $keyName = $this->getKeyName();
        $descendant = $this->getQualifiedDescendantColumn();

        $ids = $this->getDescendantsAndSelf([$keyName])->pluck($keyName);

        return $this->getRoot()
            ->joinRelationBy('descendant', true)
            ->whereNotIn($descendant, $ids);
    }

    /**
     * @param array $columns
     * @return mixed
     */
    public function getBesides(array $columns = ['*'])
    {
        return $this->queryBesides()->all($columns);
    }

    /**
     * @return mixed
     */
    public function queryChildren()
    {
        $key = $this->getKey();
        return $this->where($this->getParentColumn(), $key);
    }

    /**
     * @param array $columns
     * @return mixed
     */
    public function getChildren(array $columns = ['*'])
    {
        return $this->queryChildren()->all();
    }

    /**
     * @param array $columns
     * @return mixed
     */
    public function getParent(array $columns = ['*'])
    {
        $parentKey = $this->getParentKey();
        return $this->get($parentKey);
    }

    /**
     * @param array $columns
     * @return mixed
     */
    public function getRoot(array $columns = ['*'])
    {
        if ($this->isRoot()) {
            return $this;
        }
        $parentColumn = $this->getParentColumn();

        $res = $this->joinRelationBy('ancestor')
            ->whereOr([$parentColumn => 'IS NULL'])
            ->where($parentColumn, 0)
            ->find();
        return $res;
    }

    /**
     * @param $child
     * @return bool
     */
    public function isParentOf($child)
    {
        $model = $this->parameter2Model($child);
        return $this->getKey() === $model->getParentKey();
    }

    /**
     * @param $parent
     * @return bool
     */
    public function isChildOf($parent)
    {
        $model = $this->parameter2Model($parent);
        return $model->getKey() === $this->getParentKey();
    }

    /**
     * @param $descendant
     * @return bool
     */
    public function isAncestorOf($descendant)
    {
        $keyName = $this->getKeyName();
        $model = $this->parameter2Model($descendant);
        $ids = $this->getDescendants([$keyName])->pluck($keyName)->toArray();
        return in_array($model->getKey(), $ids);
    }

    /**
     * @param $beside
     * @return bool
     */
    public function isBesideOf($beside)
    {
        if ($this->isRoot()) {
            return false;
        }
        $keyName = $this->getKeyName();
        $model = $this->parameter2Model($beside);
        $ids = $this->getDescendantsAndSelf([$keyName])->pluck($keyName)->toArray();
        return !in_array($model->getKey(), $ids);
    }

    /**
     * @param $ancestor
     * @return bool
     */
    public function isDescendantOf($ancestor)
    {
        $keyName = $this->getKeyName();
        $model = $this->parameter2Model($ancestor);
        $ids = $this->getAncestors([$keyName])->pluck($keyName)->toArray();
        return in_array($model->getKey(), $ids);
    }

    /**
     * @param $sibling
     * @return bool
     */
    public function isSiblingOf($sibling)
    {
        $keyName = $this->getKeyName();
        $model = $this->parameter2Model($sibling);
        $ids = $this->getSiblings([$keyName])->pluck($keyName)->toArray();
        return in_array($model->getKey(), $ids);
    }

    /**
     * @param array $sort
     * @param string $childrenColumn
     * @param array $columns
     * @return mixed
     */
    public function getTree(array $sort = [], $childrenColumn = 'children', array $columns = ['*'])
    {

        $keyName = $this->getKeyName();
        $parentColumn = $this->getParentColumn();

        if (in_array('*', $columns)) {
            $columns = ['*'];
        } elseif (!in_array($parentColumn, $columns)) {
            array_push($columns, $parentColumn);
        }

        if (!empty($sort)) {
            $sortKey = isset($sort[0]) ? $sort[0] : 'sort';
            $sortMode = isset($sort[1]) ? $sort[1] : 'asc';
            $this->joinRelationBy('descendant', true);

            return $this->joinRelationBy('descendant', true)
                ->order($sortKey, $sortMode)
                ->select()
                ->toTree($keyName, $parentColumn, $childrenColumn);
        }
        return $this
            ->joinRelationBy('descendant', true)
            ->select()
            ->toTree($keyName, $parentColumn, $childrenColumn);
    }

    /**
     * @param array $sort
     * @param string $childrenColumn
     * @param array $columns
     * @return array
     */
    public function getBesideTree(array $sort = [], $childrenColumn = 'children', array $columns = ['*'])
    {
        if ($this->isRoot()) {
            return [];
        }
        $keyName = $this->getKeyName();
        $parentColumn = $this->getParentColumn();

        if (in_array('*', $columns)) {
            $columns = ['*'];
        } elseif (!in_array($parentColumn, $columns)) {
            array_push($columns, $parentColumn);
        }

        if (!empty($sort)) {
            $sortKey = isset($sort[0]) ? $sort[0] : 'sort';
            $sortMode = isset($sort[1]) ? $sort[1] : 'asc';
            return $this
                ->queryBesides()
                ->order($sortKey, $sortMode)
                ->select()
                ->toTree($keyName, $parentColumn, $childrenColumn);
        }
        return $this
            ->queryBesides()
            ->select()
            ->toTree($keyName, $parentColumn, $childrenColumn);
    }

    /**
     * @return mixed
     */
    public function querySiblings()
    {
        if ($this->getParentKey()) {
            $parent = $this->getParent();
            $key = $this->getKey();
            $keyName = $this->getKeyName();
            return $parent->queryChildren()->whereNotIn($keyName, [$key]);
        } else {
            return self::onlyRoot();
        }

    }

    /**
     * @param array $columns
     * @return mixed
     */
    public function getSiblings(array $columns = ['*'])
    {
        return $this->querySiblings()->select();
    }

    /**
     * @return mixed
     */
    public function querySiblingsAndSelf()
    {
        $parent = $this->getParent();
        return $parent->queryChildren();
    }

    /**
     * @param array $columns
     * @return mixed
     */
    public function getSiblingsAndSelf(array $columns = ['*'])
    {
        return $this->querySiblingsAndSelf()->select();
    }

    /**
     * This model is root
     *
     * @return bool
     */
    public function isRoot()
    {
        return !$this->getParentKey();
    }

    /**
     * This model is leaf
     *
     * @return bool
     */
    public function isLeaf()
    {
        return $this->queryChildren()->count() === 0;
    }

    /**
     * @return bool
     */
    public function isIsolated()
    {
        $key = $this->getKey();
        $keyName = $this->getKeyName();
        $ids = $this->joinWithoutClosure()->get([$keyName])->pluck($keyName);
        return in_array($key, $ids);
    }

    /**
     * Get isolated item
     *
     * @param array $columns
     * @return mixed
     */
    public static function getIsolated(array $columns = ['*'])
    {
        return self::isolated()->select();
    }

    /**
     * @param array $columns
     * @return mixed
     */
    public static function getRoots(array $columns = ['*'])
    {
        return self::onlyRoot()->select();
    }


    public function toCollection($collection, $resultSetType = null)
    {
        $resultSetType = $resultSetType ?: $this->resultSetType;

        if ($resultSetType && false !== strpos($resultSetType, '\\')) {
            $collection = new $resultSetType($collection);
        } else {
            $collection = new CollectionExtension($collection);
        }

        return $collection;
    }
}
