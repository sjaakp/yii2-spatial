yii2-spatial
============

ActiveRecord with spatial attributes. Those attributes are transformed from the internal [MySQL format](https://dev.mysql.com/doc/refman/5.5/en/spatial-datatypes.html) to [GeoJSON format](http://geojson.org/geojson-spec.html) after finding, and vice versa before storing.

**Notice that this extension is intended to be used with a MySQL database exclusively.**

## Installation ##

The preferred way to install **yii2-spatial** is through [Composer](https://getcomposer.org/). Either add the following to the require section of your `composer.json` file:

`"sjaakp/yii2-spatial": "*"` 

Or run:

`$ php composer.phar require sjaakp/yii2-spatial "*"` 

You can manually install **yii2-spatial** by [downloading the source in ZIP-format](https://github.com/sjaakp/yii2-spatial/archive/master.zip).

## Usage ##

Simply use a `sjaakp\spatial\ActiveRecord` as base class for your models, like so:

	<?php

	use sjaakp\spatial\ActiveRecord;

	class MySpatialModel extends ActiveRecord
	{
	    // ...
	}


**Notice:** if you override `find()` in a `sjaakp\spatial\ActiveRecord`-derived class, be sure to return a `sjaakp\spatial\ActiveQuery` and not an 'ordinary' `yii\db\ActiveQuery`.

### ActiveRecord ###

#### featureProperties() ####


    public function featureProperties($field, $geometry)

Override this function to add properties to the GeoJSON encoded attribute.

- `$field` is the attribute name.
- `$geometry` is an array with the GeoJSON-information, like decoded JSON.

The default implementation adds the ActiveRecord's primary key as the property `'id'`.