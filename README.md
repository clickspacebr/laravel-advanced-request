# Map request fields to Eloquent fields in Laravel

This package allows you to configure a different structure of fields for the requests and to map with a database field (with Eloquent).

## Installation

You can install the package via Composer:

```bash
composer require clickspacebr/laravel-request-fields
```

## Usage

You must let your model use the `Clickspace\RequestFields\MapRequestFields` trait.

```php
use Illuminate\Database\Eloquent\Model;
use Clickspace\RequestFields;

class TestModel extends Model
{
    use MapRequestFields;
    
    protected static $requestFields = [
        'address.street' => 'address_street',
        'address.street_number' => 'address_street_number',
        'address.complement' => 'address_complement',
        'address.neighborhood' => 'address_neighborhood',
        'address.city' => 'address_city',
        'address.state' => 'address_state',
        'address.zipcode' => 'address_zipcode'
    ];
}
```

In the method that you want to map the request fields to, follow the example below. 

```php
public function store(Request $request)
{
    $request->merge(Model::mapRequest($request));
```

## Testing

``` bash
composer test
```