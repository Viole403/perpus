# CodeIgniter 4 Slug Library

Have you ever wanted an easy way to set up pretty URLs with CodeIgniter? This library is made for you!

`SteeveDroz\CiSlug\Slugify` allows you to calculate what URL-compatible name fits with a given title or name.

## Install

To install this library, type `composer require steevedroz/ci4-slug` from your CodeIgniter project.

## Usage

### Basic usage

`Slugify` is intended to be used from a Model as it requires one. Here is an example of the default usage:

```php
<?php

namespace App\Models;

use CodeIgniter\Model;
use SteeveDroz\CiSlug\Slugify;

class PageModel extends Model
{
    protected $table = 'pages';
    protected $allowedFields = ['title', 'content'];

    protected $beforeInsert = ['setSlug'];

    public function setSlug($data)
    {
        $slugify = new Slugify($this);
        $data = $slugify->addSlug($data, 'title');
        return $data;
    }
}
```

This code will look for the `title` value of the incomming data and calculate the corresponding `slug`. If a database entry already has that given `slug`, it will increment the slug with a `-N` suffix. For instance: `hello-world` becomes `hello-world-2`, which becomes `hello-world-3`, and so on until a free `slug` is found.

### Configuration

If you use a slug field that is not called `slug`, you can call `$slugify->setField('your_slug_field_name')` to change the default behaviour.

## Class reference

### `SteeveDroz\CiSlug\Slugify`

#### `__construct`

**Parameters**

- `$model` (*CodeIgniter\Model*) The model that will consult the database for existing slugs.

Creates an instance of the slug generator.

#### `setField`

**Parameters**

- `$field` (*string*) The name of the database field that contains the slugs. By default, that name is `slug` but can be overridden.

**Returns** `void`

#### `addSlug`

**Parameters**

- `$data` (*array*) The data passed by a Model event (usually *BeforeInsert* and *BeforeUpdate*).
- `$nameField` (*string*) The field to use as a source to generate the slug.

**Returns** `array`: the same `$data` as passed in parameter, with an added `slug` field.
