<?php
/**
 * Created by PhpStorm.
 * User: Sjaak
 * Date: 21-9-2014
 * Time: 11:11
 */

namespace sjaakp\spatial;

use yii\db\ActiveQuery as YiiActiveQuery;

class ActiveQuery extends YiiActiveQuery {


    public function prepare($builder)    {
        /** @var ActiveRecord $modelClass */
        $modelClass = $this->modelClass;
        $schema = $modelClass::getTableSchema();

        if (empty($this->select))   {
            $this->select('*');
            foreach ($schema->columns as $column)   {
                if (ActiveRecord::isSpatial($column)) {
                    $field = $column->name;
                    $this->addSelect(["AsText($field) AS $field"]);
                }
            }
        }
        else    {
            foreach ($this->select as $field)   {
                $column = $schema->getColumn($field);
                if (ActiveRecord::isSpatial($column)) {
                    $this->addSelect(["AsText($field) AS $field"]);
                }
            }
        }

        return parent::prepare($builder);
    }
}