<?php

namespace App\Database\Eloquent;

use Hyperf\Database\Model\Model;
use Hyperf\Database\Model\Scope;
use Hyperf\Database\Model\Builder;

class SoftDeletingScope implements Scope {

    /**
     * All of the extensions to be added to the builder.
     *
     * @var array
     */
    protected $extensions = ['Restore', 'WithTrashed', 'WithoutTrashed', 'OnlyTrashed'];

    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param  \Hyperf\Database\Model\Builder  $builder
     * @param  \Hyperf\Database\Model\Model  $model
     * @return void
     */
    public function apply(Builder $builder, Model $model) {
        $builder->where($model->getQualifiedStatusColumn($builder), '=', 1);
    }

    /**
     * Extend the query builder with the needed functions.
     *
     * @param  \Hyperf\Database\Model\Builder  $builder
     * @return void
     */
    public function extend(Builder $builder) {
        foreach ($this->extensions as $extension) {
            $this->{"add{$extension}"}($builder);
        }

        $builder->onDelete(function (Builder $builder) {
            $column = $this->getDeletedAtColumn($builder);
            $data = [
                $column => 0,
            ];

            if ($builder->getModel()->getDeletedTimeColumn()) {
                $data[$builder->getModel()->getDeletedTimeColumn()] = $builder->getModel()->freshTimestampString();
            }

            return $builder->update($data);
        });
    }

    /**
     * Get the "deleted at" column for the builder.
     *
     * @param  \Hyperf\Database\Model\Builder  $builder
     * @return string
     */
    protected function getDeletedAtColumn(Builder $builder) {
        if (count((array) $builder->getQuery()->joins) > 0) {
            return $builder->getModel()->getQualifiedStatusColumn($builder);
        }

        return $builder->getModel()->getDeletedAtColumn();
    }

    /**
     * Add the restore extension to the builder.
     *
     * @param  \Hyperf\Database\Model\Builder  $builder
     * @return void
     */
    protected function addRestore(Builder $builder) {
        $builder->macro('restore', function (Builder $builder) {
            $builder->withTrashed();
            $data = [$builder->getModel()->getDeletedAtColumn() => 1];
            if ($builder->getModel()->getDeletedTimeColumn()) {
                $data[$builder->getModel()->getDeletedTimeColumn()] = $builder->getModel()->freshTimestampString();
            }

            return $builder->update($data);
        });
    }

    /**
     * Add the with-trashed extension to the builder.
     *
     * @param  \Hyperf\Database\Model\Builder  $builder
     * @return void
     */
    protected function addWithTrashed(Builder $builder) {
        $builder->macro('withTrashed', function (Builder $builder, $withTrashed = true) {
            if (!$withTrashed) {
                return $builder->withoutTrashed();
            }

            return $builder->withoutGlobalScope($this);
        });
    }

    /**
     * Add the without-trashed extension to the builder.
     *
     * @param  \Hyperf\Database\Model\Builder  $builder
     * @return void
     */
    protected function addWithoutTrashed(Builder $builder) {
        $builder->macro('withoutTrashed', function (Builder $builder) {
            $model = $builder->getModel();
            $builder->withoutGlobalScope($this)->where($model->getQualifiedStatusColumn($builder), '=', 1);

            return $builder;
        });
    }

    /**
     * Add the only-trashed extension to the builder.
     *
     * @param  \Hyperf\Database\Model\Builder  $builder
     * @return void
     */
    protected function addOnlyTrashed(Builder $builder) {
        $builder->macro('onlyTrashed', function (Builder $builder) {
            $model = $builder->getModel();
            $builder->withoutGlobalScope($this)->where($model->getQualifiedStatusColumn($builder), '=', 0);

            return $builder;
        });
    }

}
