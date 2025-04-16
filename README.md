# Laravel ETL Manifesto

[![Latest Version on Packagist](https://img.shields.io/packagist/v/laravelplus/etl-manifesto.svg?style=flat-square)](https://packagist.org/packages/laravelplus/etl-manifesto)
[![Total Downloads](https://img.shields.io/packagist/dt/laravelplus/etl-manifesto.svg?style=flat-square)](https://packagist.org/packages/laravelplus/etl-manifesto)
![GitHub Actions](https://github.com/laravelplus/etl-manifesto/actions/workflows/main.yml/badge.svg)

## Features

- 🚀 YAML-based manifest configuration
- 🔄 Flexible data transformation pipeline
- 📊 Support for complex SQL aggregations
- 🔌 Multiple export formats (CSV, JSON)
- 🔗 Automatic relationship handling
- 🎯 Group by and custom functions
- 🛡️ Error handling and validation
- 📝 Detailed logging

## Installation

You can install the package via composer:

```bash
composer require laravelplus/etl-manifesto
```

## Basic Usage

1. Create a manifest file (e.g., `manifests/etl.yml`):

```yaml
etl:
  - id: monthly_user_summary
    name: Monthly User Purchase Summary
    description: Generate monthly user purchase statistics

    source:
      entities:
        - users
        - orders
        - payments

      relationships:
        - users hasMany orders
        - orders hasOne payments

      conditions:
        - orders.created_at: last_month

      mapping:
        - id: users.id
        - name: users.name
        - email: users.email
        - total_orders:
            function: count
            column: orders.id
        - total_spent:
            function: sum
            column: payments.amount

      group_by:
        - users.id
        - users.name
        - users.email

    output:
      format: csv
      path: exports/monthly_user_summary.csv
```

2. Process the ETL manifest in your code:

```php
use Laravelplus\EtlManifesto\EtlManifesto;

$etl = new EtlManifesto();
$results = $etl->loadManifest('manifests/etl.yml')->process();
```

## Manifest Configuration

### Source Configuration

Define your data sources and their relationships:

```yaml
source:
  entities:
    - table_name
    - another_table
  
  relationships:
    - table_name hasMany related_table
    - table_name belongsTo parent_table
```

### Mapping Functions

Available mapping functions:

- `count`: Count records
- `sum`: Sum values
- `avg`: Calculate average
- `min`: Find minimum value
- `max`: Find maximum value
- `concat`: Concatenate strings
- `custom`: Define custom transformations

### Transformations

Apply transformations to your data:

```yaml
transform:
  - field_name: lower
  - another_field: upper
  - date_field: format_date
```

### Export Options

Supported export formats:

- CSV with custom delimiters and encoding
- JSON with formatting options
- Custom export handlers

## Advanced Usage

### Custom Transformers

Create custom transformers by extending the `DataTransformer` class:

```php
use Laravelplus\EtlManifesto\Services\DataTransformer;

class CustomTransformer extends DataTransformer
{
    public function transform($value, $options)
    {
        // Your custom transformation logic
    }
}
```

### Error Handling

The package provides detailed error handling:

```php
try {
    $results = $etl->loadManifest('manifests/etl.yml')->process();
} catch (\Exception $e) {
    // Handle errors
}
```

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Credits

- [Your Name]
- [All Contributors]

## Security

If you discover any security related issues, please email your@email.com instead of using the issue tracker.

## Laravel Package Boilerplate

This package was generated using the [Laravel Package Boilerplate](https://laravelpackageboilerplate.com).
