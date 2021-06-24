<?php

namespace App\Database\Eloquent;

use Hyperf\Database\Model\Builder;

trait SoftDeletes {

    /**
     * Indicates if the model is currently force deleting.
     *
     * @var bool
     */
    protected $forceDeleting = false;

    /**
     * Boot the soft deleting trait for a model.
     *
     * @return void
     */
    public static function bootSoftDeletes() {
        static::addGlobalScope(make(SoftDeletingScope::class));//new SoftDeletingScope
    }

    /**
     * Force a hard delete on a soft deleted model.
     *
     * @return bool|null
     */
    public function forceDelete() {
        //禁用物理删除
        return false;

//        $this->forceDeleting = true;
//
//        return tap($this->delete(), function ($deleted) {
//            $this->forceDeleting = false;
//
//            if ($deleted) {
//                $this->fireModelEvent('forceDeleted', false);
//            }
//        });
    }

    /**
     * Perform the actual delete query on this model instance.
     *
     * @return mixed
     */
    protected function performDeleteOnModel() {
        if ($this->forceDeleting) {
            $this->exists = false;

            return $this->newModelQuery()->where($this->getKeyName(), $this->getKey())->forceDelete();
        }

        return $this->runSoftDelete();
    }

    /**
     * Perform the actual delete query on this model instance.
     *
     * @return void
     */
    protected function runSoftDelete() {
        $query = $this->newModelQuery()->where($this->getKeyName(), $this->getKey());

        $time = $this->freshTimestamp();

        $columns = [];
        if ($this->getDeletedAtColumn()) {
            $columns[$this->getDeletedAtColumn()] = 0;
            $this->{$this->getDeletedAtColumn()} = 0;
        }

        //更新删除时间
        if ($this->getDeletedTimeColumn()) {
            $columns[$this->getDeletedTimeColumn()] = $this->fromDateTime($time);
            $this->{$this->getDeletedTimeColumn()} = $time;
        }

        if ($this->timestamps && !is_null($this->getUpdatedAtColumn())) {
            $this->{$this->getUpdatedAtColumn()} = $time;
            $columns[$this->getUpdatedAtColumn()] = $this->fromDateTime($time);
        }

        $query->update($columns);
    }

    /**
     * Restore a soft-deleted model instance.
     *
     * @return bool|null
     */
    public function restore() {

        // If the restoring event does not return false, we will proceed with this
        // restore operation. Otherwise, we bail out so the developer will stop
        // the restore totally. We will clear the deleted timestamp and save.
        if ($this->fireModelEvent('restoring') === false) {
            return false;
        }

        //更新还原时间
        $time = $this->freshTimestamp();
        if ($this->getDeletedTimeColumn()) {
            $this->{$this->getDeletedTimeColumn()} = $this->fromDateTime($time);
        }

        if ($this->getDeletedAtColumn()) {
            $this->{$this->getDeletedAtColumn()} = 1;
        }

        // Once we have saved the model, we will fire the "restored" event so this
        // developer will do anything they need to after a restore operation is
        // totally finished. Then we will return the result of the save call.
        $this->exists = true;

        $result = $this->save();

        $this->fireModelEvent('restored', false);

        return $result;
    }

    /**
     * Determine if the model instance has been soft-deleted.
     *
     * @return bool
     */
    public function trashed() {
        return 0 == $this->{$this->getDeletedAtColumn()};
    }

    /**
     * Register a restoring model event with the dispatcher.
     *
     * @param  \Closure|string  $callback
     * @return void
     */
    public static function restoring($callback) {
        static::registerModelEvent('restoring', $callback);
    }

    /**
     * Register a restored model event with the dispatcher.
     *
     * @param  \Closure|string  $callback
     * @return void
     */
    public static function restored($callback) {
        static::registerModelEvent('restored', $callback);
    }

    /**
     * Determine if the model is currently force deleting.
     *
     * @return bool
     */
    public function isForceDeleting() {
        return $this->forceDeleting;
    }

    /**
     * Get the name of the "deleted at" column.
     *
     * @return string
     */
    public function getDeletedAtColumn() {
        return defined('static::STATUS_AT') ? static::STATUS_AT : 'status';
    }

    /**
     * Get the name of the "deleted at" column.
     *
     * @return string
     */
    public function getDeletedTimeColumn() {
        return defined('static::DELETED_AT') ? static::DELETED_AT : '';
    }

    /**
     * Get the fully qualified "deleted at" column.
     *
     * @return string
     */
    public function getQualifiedDeletedAtColumn() {
        return $this->qualifyColumn($this->getDeletedAtColumn());
    }

    /**
     * Get the fully qualified "deleted at" column.
     *
     * @return string
     */
    public function getQualifiedStatusColumn(Builder $builder) {

        $from = $builder->getQuery()->from;
        $column = $this->getDeletedAtColumn();
        if (stripos($from, ' as ') !== false) {
            $segments = preg_split('/\s+as\s+/i', $from);

            $column = end($segments) ? (end($segments) . '.' . $column) : $column;
        }

        return $this->qualifyColumn($column);
    }

    public function history() {
        return $this->withTrashed();
    }

}
