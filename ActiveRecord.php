<?php
/**
 * Created by PhpStorm.
 * User: Sjaak
 * Date: 21-9-2014
 * Time: 10:46
 */

namespace sjaakp\spatial;

use Yii;
use yii\db\Expression;
use yii\helpers\Json;
use yii\db\ActiveRecord as YiiActiveRecord;

class ActiveRecord extends YiiActiveRecord {

    public static function find()    {
        return Yii::createObject(ActiveQuery::className(), [get_called_class()]);
    }

    public static function isSpatial($column)   {
        $spatialFields = [
            'point',
            'linestring',
            'polygon',
            'multipoint',
            'multilinestring',
            'multipolygon',
            'geometry',
            'geometrycollection'
        ];

        return $column ? in_array($column->dbType, $spatialFields) : false;
    }

    protected $_saved = [];

    public function beforeSave($insert)    {
        $r = parent::beforeSave($insert);
        if ($r) {
            $scheme = static::getTableSchema();
            foreach ($scheme->columns as $column)   {
                if (static::isSpatial($column))   {
                    $field = $column->name;
                    $attr = $this->getAttribute($field);

                    if ($attr)  {
                        $this->_saved[$field] = $attr;
                        $feature = Json::decode($attr);
                        $wkt = SpatialHelper::featureToWkt($feature);
                        $this->setAttribute($field, new Expression("GeomFromText('$wkt')"));
                    }
                }
            }
        }
        return $r;
    }

    public function afterSave($insert, $changedAttributes)    {
        foreach ($this->_saved as $field => $attr)
            $this->setAttribute($field, $attr);
        parent::afterSave($insert, $changedAttributes);
    }

    public function afterFind()    {
        parent::afterFind();

        $scheme = static::getTableSchema();
        foreach ($scheme->columns as $column)   {
            if (static::isSpatial($column))   {
                $field = $column->name;
                $attr = $this->getAttribute($field);    // get WKT
                if ($attr)  {
                    $geom = SpatialHelper::wktToGeom($attr);

                    // Transform geometry FeatureCollection...
                    if ($geom['type'] == 'GeometryCollection')  {
                        $feats = [];
                        foreach ($geom['geometries'] as $g) {
                            $feats[] = [
                                'type' => 'Feature',
                                'geometry' => $g,
                                'properties' => $this->featureProperties($field, $g)
                            ];
                        }
                        $feature = [
                            'type' => 'FeatureCollection',
                            'features' => $feats
                        ];
                    }
                    else {  // ... or to Feature
                        $feature = SpatialHelper::geomToFeature($geom, $this->featureProperties($field, $geom));
                    }

                    $this->setAttribute($field, Json::encode($feature));
                }
            }
        }
    }

    // Override this function to set more Feature properties
    public function featureProperties($field, $geometry)  {
        return [ 'id' => $this->getPrimaryKey() ];
    }
}
