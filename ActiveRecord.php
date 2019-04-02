<?php
/**
 * MIT licence
 * Version 1.1.0
 * Sjaak Priester, Amsterdam 21-06-2014 ... 02-04-2019.
 *
 * ActiveRecord with spatial attributes in Yii 2.0 framework
 *
 * @link https://github.com/sjaakp/yii2-spatial
 */

namespace sjaakp\spatial;

use Yii;
use yii\db\Expression;
use yii\helpers\Json;
use yii\db\ActiveRecord as YiiActiveRecord;
use yii\base\InvalidCallException;

/**
 * Class ActiveRecord
 * @package sjaakp\spatial
 */
class ActiveRecord extends YiiActiveRecord {
    /** @var  float - virtual attribute used by ActiveQuery::nearest() */
    public $_d;

    /**
     * @return object|\yii\db\ActiveQuery
     * @throws \yii\base\InvalidConfigException
     */
    public static function find()    {
        return Yii::createObject(ActiveQuery::class, [get_called_class()]);
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

    /**
     * @param bool $insert
     * @return bool
     * @throws \yii\base\InvalidConfigException
     */
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
                        $this->setAttribute($field, new Expression("ST_GeomFromText('$wkt')"));
                    }
                }
            }
        }
        return $r;
    }

    /**
     * @param bool $insert
     * @param array $changedAttributes
     */
    public function afterSave($insert, $changedAttributes)    {
        foreach ($this->_saved as $field => $attr)
            $this->setAttribute($field, $attr);
        parent::afterSave($insert, $changedAttributes);
    }

    /**
     * @throws \yii\base\InvalidConfigException
     */
    public function afterFind()    {
        parent::afterFind();

        $scheme = static::getTableSchema();
        foreach ($scheme->columns as $column)   {
            if (static::isSpatial($column))   {
                $field = $column->name;
                $attr = $this->getAttribute($field);    // get WKT
                if ($attr)  {
                    if (YII_DEBUG && preg_match( '/[\\x80-\\xff]+/' , $attr ))   {
                        /* If you get an exception here, it probably means you have overridden find()
                             and did not return sjaakp\spatial\ActiveQuery. */
                        throw new InvalidCallException('Spatial attribute not converted.');
                    }
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

    /**
     * Override this function to set more Feature properties
     * @param $field
     * @param $geometry
     * @return array
     */
    public function featureProperties($field, $geometry)  {
        return [ 'id' => $this->getPrimaryKey() ];
    }
}
