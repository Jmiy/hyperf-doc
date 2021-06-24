<?php

namespace App\Database\Eloquent\Relations;

//use BadMethodCallException;
use Hyperf\Database\Model\Model;
//use Hyperf\Database\Model\Builder;
//use Hyperf\Database\Model\Collection;
use Hyperf\Database\Model\Relations\MorphTo as FrameworkMorphTo;

class MorphTo extends FrameworkMorphTo {

    /**
     * Create a new model instance by type.
     *
     * @param  string  $type
     * @return \Hyperf\Database\Model\Model
     */
    public function createModelByType($type) {
        $class = Model::getActualClassNameForMorph($type);
        $model = new $class;

        $morphToConnection = $this->getParent()->getMorphToConnection();
        $connection = data_get($morphToConnection, $type, 'default_connection');
        switch ($connection) {
            case 'default_connection':
                break;

            case 'parent':
                $model->setConnection($this->getParent()->getConnectionName());

                break;

            default:
                $model->setConnection($connection);
                break;
        }


        return $model;
    }

}
