<?php
/**
 * Created by PhpStorm.
 * User: Sjaak
 * Date: 19-10-2014
 * Time: 12:07
 */

namespace sjaakp\spatial;

/**
 * Class SpatialHelper
 * @package sjaakp\spatial
 * Converts between:
 * - geometry (PHP array)
 * - feature (PHP array)
 * - Well-Known Text (used by MySQL)
 * - GeoJSON (used by Leaflet)
 *
 * Geometry and feature are 'decoded GeoJson'. They are formatted like:
 * [
 *  'type' => 'Point',
 *  'coordinates' => [ lng, lat ]
 * ]
 * (Notice: Leaflet uses [ lat, lng ] internally)
 *
 * @link http://geojson.org/geojson-spec.html
 * @link http://dev.mysql.com/doc/refman/5.6/en/gis-data-formats.html#gis-wkt-format
 *
 */
use yii\helpers\Json;

class SpatialHelper {

    protected static function implodePoint($pntArray)  {
        return implode(' ', $pntArray);
    }

    protected static function implodePoints($pntsArray)    {
        return implode(',', array_map('static::implodePoint', $pntsArray));
    }

    protected static function implodeLines($lnsArray)    {
        return implode(',', array_map(function($v){ return '(' . static::implodePoints($v) . ')'; }, $lnsArray));
    }

    protected static function explodePoint($pnt)    {
        return array_map('floatval', explode(' ', $pnt));
    }

    protected static function explodePoints($pntsWkt)    {
        return array_map(function($v) {
            return static::explodePoint($v);
        }, $pntsWkt);
    }

    protected static function explodeLines($lnsWkt)    {
        return array_map(function($v) {
            $matches = [];
            return preg_match_all('/([-\d. ]+)/', $v, $matches) ? static::explodePoints($matches[1]) : [];
        }, $lnsWkt);
    }

    public static function geomToWkt($geom)   {
        $r = '';
        if (! empty($geom)) {
//            if ($array['type'] == 'Feature') $array = $array['geometry'];
            $r = strtoupper($geom['type']);
            if ($r == 'GEOMETRYCOLLECTION') {
                $d = $geom['geometries'];
                $r .= empty($d) ? ' EMPTY' : '(' . implode(',', array_map('static::geomToWkt', $d)) . ')';
            }
            else {
                $d = $geom['coordinates'];
                if (empty($d)) {
                    $r .= ' EMPTY';
                } else {
                    switch ($r) {
                        case 'POINT':
                            $r .= '(' . static::implodePoint($d) . ')';
                            break;

                        case 'LINESTRING':
                        case 'MULTIPOINT':
                            $r .= '(' . static::implodePoints($d) . ')';
                            break;

                        case 'POLYGON':
                        case 'MULTILINESTRING':
                            $r .= '(' . static::implodeLines($d) . ')';
                            break;

                        case 'MULTIPOLYGON':
                            $r .= '(' . implode(',', array_map(function ($v) {
                                            return '(' . static::implodeLines($v) . ')';
                                        }, $d)) . ')';
                            break;

                        default:
                            $r .= ' not implemented';
                            break;
                    }
                }
            }
        }
        return $r;
    }

    public static function wktToGeom($wkt) {
        $cases = [
            'POINT' => 'Point',
            'MULTIPOINT' => 'MultiPoint',
            'LINESTRING' => 'LineString',
            'MULTILINESTRING' => 'MultiLineString',
            'POLYGON' => 'Polygon',
            'MULTIPOLYGON' => 'MultiPolygon',
            'GEOMETRYCOLLECTION' => 'GeometryCollection'
        ];
        $r = [];
        $matches = [];
        if (preg_match('/([^( ]+)/', $wkt, $matches))    {
            $type = $matches[1];
            $r['type'] = $cases[$type];
            $data = [];
            $prop = 'coordinates';
            if (strpos($wkt, 'EMPTY') === FALSE) {
                switch ($type) {
                    case 'POINT':
                        if (preg_match('/([-\d. ]+)/', $wkt, $matches))
                            $data = static::explodePoint($matches[1]);
                        break;

                    case 'LINESTRING':
                    case 'MULTIPOINT':
                        if (preg_match_all('/([-\d. ]+)/', $wkt, $matches))
                            $data = static::explodePoints($matches[1]);
                        break;

                    case 'POLYGON':
                    case 'MULTILINESTRING':
                        if (preg_match_all('/\(([-\d., ]+)/', $wkt, $matches))
                            $data = static::explodeLines($matches[1]);
                        break;

                    case 'MULTIPOLYGON':
                        if (preg_match_all('/\(?(\({2}.*?\){2})/', $wkt, $matches)) {
                            $data = array_map(function($v) {
                                $pmatches = [];
                                return preg_match_all('/\(([-\d., ]+)/', $v, $pmatches) ? static::explodeLines($pmatches[1]) : [];
                            }, $matches[1]);
                        }
                        break;

                    case 'GEOMETRYCOLLECTION':
                        $prop = 'geometries';
                        if (preg_match_all('/([A-Z]+[^A-Z]*)/', substr($wkt, 19, -1), $matches)) {
                            $data = array_map('static::wktToGeom', $matches[1]);
                        }
                        break;

                    default:
                        break;
                }
            }
            $r[$prop] = $data;
        }

        return $r;
    }

    public static function featureToGeom($feat)    {
        if ($feat)  {
            switch ($feat['type'])  {
                case 'Feature':
                    $feat = $feat['geometry'];
                    break;

                case 'FeatureCollection':
                    $features = $feat['features'];
                    if (count($features) == 1) $feat = current($features)['geometry'];
                    else $feat = [
                        'type' => 'GeometryCollection',
                        'geometries' => array_map(function($v) { return $v['geometry']; }, $features)
                    ];
                    break;

                case 'Point':
                case 'MultiPoint':
                case 'LineString':
                case 'MultiLineString':
                case 'Polygon':
                case 'MultiPolygon':
                case 'GeometryCollection':
                    break;      // it is already a geometry; return unchanged

                default:
                    $feat = null;   // don't understand
                    break;
            }
        }
        return $feat;
    }

    public static function geomToFeature($geom, $properties = []) {
        if ($geom)  {
            switch ($geom['type'])  {
                case 'Point':
                case 'MultiPoint':
                case 'LineString':
                case 'MultiLineString':
                case 'Polygon':
                case 'MultiPolygon':
                    $geom = [
                        'type' => 'Feature',
                        'geometry' => $geom,
                        'properties' => $properties
                    ];
                    break;

                case 'GeometryCollection':
                    $geoms = $geom['geometries'];
                    if (count($geoms) == 1) {
                        $geom = [   // if collection has one item, return it as a Feature
                            'type' => 'Feature',
                            'geometry' => current($geoms),
                            'properties' => $properties
                        ];
                    }
                    else {
                        // compile a FeatureCollection and return it
                        $feats = [];
                        foreach ($geoms as $g) {
                            $feats[] = [
                                'type' => 'Feature',
                                'geometry' => $g,
                                'properties' => $properties
                            ];
                        }
                        $geom = [
                            'type' => 'FeatureCollection',
                            'features' => $feats
                        ];

/*                          // collection has more items (or zero)
                                // see if they are all of the same type
                        $type = false;
                        foreach ($geoms as $g) {
                            if (! $type) $type = $g['type'];
                            elseif ($type != $g['type']) {
                                $type = false;
                                break;
                            }
                        }

                        if ($type && (
                                $type == 'Point' || $type == 'LineString' || $type == 'Polygon'
                            ))  {   // all geometries are of the same singular type

                            $coords = [];   // combine all the coordinates
                            foreach ($geoms as $g) {
                                $coords[] = $g['coordinates'];
                            }

                            $geom = [   // return it as a Feature of a multiple type
                                'type' => 'Feature',
                                'geometry' => [
                                    'type' => 'Multi' . $type,
                                    'coordinates' => $coords
                                ],
                                'properties' => $properties
                            ];
                        }
                        else {
                            // if types are different, compile a FeatureCollection and return it
                            $feats = [];
                            foreach ($geoms as $g) {
                                $feats[] = [
                                    'type' => 'Feature',
                                    'geometry' => $g,
                                    'properties' => $properties
                                ];
                            }
                            $geom = [
                                'type' => 'FeatureCollection',
                                'features' => $feats
                            ];
                        }*/
                    }
                    break;

                case 'Feature':
                case 'FeatureCollection':
                    break;      // it is already a Feature; return unchanged

                default:
                    $geom = null;       // don't understand
                    break;
            }
        }
        return $geom;
    }

    public static function featureToWkt($feat)  {
        return static::geomToWkt(static::featureToGeom($feat));
    }

    public static function wktToFeature($wkt, $properties = [])   {
        return static::geomToFeature(static::wktToGeom($wkt), $properties);
    }

/*    public static function featureCollToGeom($featureColl)  {
        if ($featureColl['type'] == 'FeatureCollection') {
            $points = [];
            $lineStrings = [];
            $polygons = [];

            foreach ($featureColl['features'] as $feature)   {
                $geom = $feature['geometry'];
                $type = $geom['type'];
                if ($type == 'GeometryCollection')  {

                }
                else {
                    $data = $geom['coordinates'];
                    switch ($geom['type']) {
                        case 'Point':
                            $points[] = $data;
                            break;
                        case 'MultiPoint':
                            array_push($points, $data);
                            break;
                        case 'LineString':
                            $lineStrings[] = $data;
                            break;
                        case 'MultiLineString':
                            array_push($lineStrings, $data);
                            break;
                        case 'Polygon':
                            $polygons[] = $data;
                            break;
                        case 'MultiPolygon':
                            array_push($polygons, $data);
                            break;
                    }
                }
            }
            $r = [];

            switch (count($points)) {
                case 0:
                    break;
                case 1:
                    $r[] = [ 'type' => 'Point', 'coordinates' => current($points) ];
                    break;
                default:
                    $r[] = [ 'type' => 'MultiPoint', 'coordinates' => $points ];
                    break;
            }

            switch (count($lineStrings)) {
                case 0:
                    break;
                case 1:
                    $r[] = [ 'type' => 'LineString', 'coordinates' => current($lineStrings) ];
                    break;
                default:
                    $r[] = [ 'type' => 'MultiLineString', 'coordinates' => $lineStrings ];
                    break;
            }

            switch (count($polygons)) {
                case 0:
                    break;
                case 1:
                    $r[] = [ 'type' => 'Polygon', 'coordinates' => current($polygons) ];
                    break;
                default:
                    $r[] = [ 'type' => 'MultiPolygon', 'coordinates' => $polygons ];
                    break;
            }

            switch (count($r))  {
                case 0:
                    $featureColl = null;
                    break;
                case 1:
                    $featureColl = $r;
                    break;
                default:
                    $featureColl = [
                        'type' => 'GeometryCollection',
                        'geometries' => $r
                    ];
                    break;
            }
        }

        return $featureColl;
    }*/

    protected static function revCoords($array)    {
        if (is_array($array) && ! empty($array))    {
            $item = $array[0];
            if (is_array($item)) return array_map(function($v) { return static::revCoords($v); }, $array);
            else return array_reverse($array);
        }
        return $array;
    }

    public static function reverseCoordinates($array)    {
        if (isset($array['geometries'])) $array['geometries'] = array_map(function($v) { return static::reverseCoordinates($v); }, $array['geometries']);
        else if (isset($array['coordinates'])) $array['coordinates'] = static::revCoords($array['coordinates']);
        return $array;
    }
} 