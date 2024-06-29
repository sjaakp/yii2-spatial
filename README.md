Yii2-spatial
============

[![Latest Stable Version](https://poser.pugx.org/sjaakp/yii2-spatial/v/stable)](https://packagist.org/packages/sjaakp/yii2-spatial)
[![Total Downloads](https://poser.pugx.org/sjaakp/yii2-spatial/downloads)](https://packagist.org/packages/sjaakp/yii2-spatial)
[![License](https://poser.pugx.org/sjaakp/yii2-spatial/license)](https://packagist.org/packages/sjaakp/yii2-spatial)

ActiveRecord with spatial attributes. Those attributes are transformed from the internal
 [MySQL format](https://dev.mysql.com/doc/refman/8.0/en/spatial-types.html) to 
 [GeoJSON format](https://geojson.org/) after finding, and vice versa before storing.

**Yii2-spatial** can also be used to find the model or models which are nearest to a given location.

**Notice that this extension is intended to be used with a MySQL or MariaDB database exclusively.**

Version 1.1.0 is compatible with MySQL 5.7 and MariaDB 10.3.

## Installation ##

Install **Yii2-spatial** with [Composer](https://getcomposer.org/). Either add the following to the require section of your `composer.json` file:

`"sjaakp/yii2-spatial": "*"` 

Or run:

`composer require sjaakp/yii2-spatial "*"` 

You can manually install **Yii2-spatial** by [downloading the source in ZIP-format](https://github.com/sjaakp/yii2-spatial/archive/master.zip).

## Usage ##

Simply use a `sjaakp\spatial\ActiveRecord` as base class for your models, like so:

	<?php

	use sjaakp\spatial\ActiveRecord;

	class MySpatialModel extends ActiveRecord
	{
	    // ...
	}


**Notice:** if you override `find()` in a `sjaakp\spatial\ActiveRecord`-derived class, be sure to return a `sjaakp\spatial\ActiveQuery` and not an 'ordinary' `yii\db\ActiveQuery`.

## ActiveRecord method ##

#### featureProperties() ####


    public function featureProperties($field, $geometry)

Override this function to add properties to the GeoJSON encoded attribute.

- `$field` is the attribute name.
- `$geometry` is an array with the GeoJSON-information, like decoded JSON.
- Return: `array` of `property => value`.

The default implementation adds the ActiveRecord's primary key as the property `'id'`.

## ActiveQuery method ##

#### nearest() ####

    public function nearest($from, $attribute, $radius)

Change the query so that it finds the model(s) nearest to the point given by `$from`.

- `$from` - `string|array`
     - `string`: GeoJSON representation of search `Point` or `Feature`.
     - `array`:  location in the form `[<lng>, <lat>]` (two `floats`).
- `$attribute` - `string` attribute name of `Point` in the model.
- `$radius` - `number` search radius in kilometers. Default `100`.
- Return: `$this`.

Example usages:

    $here = [4.9, 52.3];     // longitude and latitude of my place

	$here2 = '{"type":"Point","coordinates":[4.9,52.3]}';	// another representation
     

	$nearestModel = <model>::find()->nearest($here, <attributeName>, 200)->one();    // search radius is 200 km
    
	$fiveNearestModels =  <model>::find()->nearest($here, <attributeName>)->limit(5)->all();	// search radius is 100 km (default)
    
	$dataProvider = new ActiveDataProvider([ 'query' => <model>::find()->nearest($here, <attributeName>) ]);

## Thanks

 - **fpolito** for finding a very subtle bug.
