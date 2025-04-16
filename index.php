<?php

require __DIR__.'/vendor/autoload.php';

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Laravelplus\EtlManifesto\EtlManifesto;
use Laravelplus\EtlManifesto\Services\DataExporter;
use Laravelplus\EtlManifesto\Services\DataTransformer;
use Laravelplus\EtlManifesto\Services\ManifestParser;
use Laravelplus\EtlManifesto\Services\QueryBuilder;

// Create a new Laravel application instance
$app = new Container;
$app->singleton('app', function () use ($app) {
    return $app;
});

// Set the facade root
Facade::setFacadeApplication($app);

// Initialize configuration
$config = new Repository(require __DIR__.'/config/database.php');
$app->instance('config', $config);

// Initialize filesystem
$filesystem = new Filesystem;
$app->instance('files', $filesystem);
$app->singleton('filesystem', function () use ($app) {
    return new FilesystemManager($app);
});

// Initialize database
$capsule = new Capsule;
$capsule->addConnection($config->get('connections.'.$config->get('default')));
$capsule->setAsGlobal();
$capsule->bootEloquent();

// Register the database manager with the container
$app->singleton('db', function () use ($capsule) {
    return $capsule->getDatabaseManager();
});

// Drop existing tables if they exist
Capsule::schema()->dropIfExists('payments');
Capsule::schema()->dropIfExists('orders');
Capsule::schema()->dropIfExists('users');

// Create tables with correct structure
Capsule::schema()->create('users', function ($table) {
    $table->id();
    $table->string('name');
    $table->string('email');
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});

Capsule::schema()->create('orders', function ($table) {
    $table->id();
    $table->unsignedBigInteger('user_id');
    $table->string('product_id');
    $table->integer('quantity');
    $table->decimal('amount', 10, 2);
    $table->timestamps();

    $table->foreign('user_id')->references('id')->on('users');
});

Capsule::schema()->create('payments', function ($table) {
    $table->id();
    $table->unsignedBigInteger('order_id');
    $table->decimal('amount', 10, 2);
    $table->string('status');
    $table->timestamps();

    $table->foreign('order_id')->references('id')->on('orders');
});

// Insert sample data
DB::table('users')->insert([
    ['name' => 'John Doe', 'email' => 'john@example.com', 'is_active' => true],
    ['name' => 'Jane Smith', 'email' => 'jane@example.com', 'is_active' => true],
    ['name' => 'Bob Johnson', 'email' => 'bob@example.com', 'is_active' => false],
]);

$users = DB::table('users')->pluck('id');

DB::table('orders')->insert([
    [
        'user_id' => $users[0],
        'product_id' => 'P001',
        'quantity' => 2,
        'amount' => 100.00,
        'created_at' => now()->subMonth()->startOfMonth()->addDays(5),
    ],
    [
        'user_id' => $users[0],
        'product_id' => 'P002',
        'quantity' => 1,
        'amount' => 50.00,
        'created_at' => now()->subMonth()->startOfMonth()->addDays(10),
    ],
    [
        'user_id' => $users[1],
        'product_id' => 'P001',
        'quantity' => 3,
        'amount' => 150.00,
        'created_at' => now()->subMonth()->startOfMonth()->addDays(15),
    ],
    [
        'user_id' => $users[1],
        'product_id' => 'P003',
        'quantity' => 1,
        'amount' => 75.00,
        'created_at' => now()->subMonth()->startOfMonth()->addDays(20),
    ],
]);

$orders = DB::table('orders')->pluck('id');

DB::table('payments')->insert([
    ['order_id' => $orders[0], 'amount' => 100.00, 'status' => 'completed'],
    ['order_id' => $orders[1], 'amount' => 50.00, 'status' => 'completed'],
    ['order_id' => $orders[2], 'amount' => 150.00, 'status' => 'completed'],
    ['order_id' => $orders[3], 'amount' => 75.00, 'status' => 'completed'],
]);

// Initialize ETL services
$parser = new ManifestParser;
$queryBuilder = new QueryBuilder;
$transformer = new DataTransformer;
$exporter = new DataExporter;

// Process ETL manifest
$manifest = new EtlManifesto($parser, $queryBuilder, $transformer, $exporter);
$manifest->loadManifest(__DIR__.'/manifests/etl.yml');
$results = $manifest->process();

// Start HTML output with styling
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ETL Manifesto - Process Results</title>
    <style>
        :root {
            --primary-color: #4a90e2;
            --secondary-color: #f5f5f5;
            --text-color: #333;
            --border-color: #ddd;
            --success-color: #4caf50;
            --error-color: #f44336;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: var(--text-color);
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f9f9f9;
        }

        .container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
        }

        h1 {
            color: var(--primary-color);
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        h2 {
            color: var(--primary-color);
            margin-top: 20px;
        }

        .file-list {
            list-style: none;
            padding: 0;
        }

        .file-item {
            background-color: var(--secondary-color);
            border: 1px solid var(--border-color);
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 10px;
        }

        .file-content {
            background-color: #f8f9fa;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            padding: 15px;
            margin-top: 10px;
            overflow-x: auto;
        }

        .error-list {
            list-style: none;
            padding: 0;
        }

        .error-item {
            background-color: #ffebee;
            border: 1px solid var(--error-color);
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 10px;
            color: var(--error-color);
        }

        .success-message {
            background-color: #e8f5e9;
            border: 1px solid var(--success-color);
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 20px;
            color: var(--success-color);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        th {
            background-color: var(--secondary-color);
            font-weight: bold;
        }

        tr:hover {
            background-color: var(--secondary-color);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>ETL Process Results</h1>

        <?php if (empty($results['errors'])) { ?>
            <div class="success-message">
                ETL Process Completed Successfully
            </div>
        <?php } ?>

        <?php if (! empty($results['files'])) { ?>
            <h2>Generated Files</h2>
            <ul class="file-list">
                <?php foreach ($results['files'] as $file) { ?>
                    <li class="file-item">
                        <strong><?php echo basename($file); ?></strong>
                        <?php if (str_ends_with($file, '.csv')) { ?>
                            <div class="file-content">
                                <table>
                                    <?php
                                    $handle = fopen($file, 'r');
                            if ($handle) {
                                // Header row
                                $headers = fgetcsv($handle, 0, ',', '"', '\\');
                                echo '<thead><tr>';
                                foreach ($headers as $header) {
                                    echo '<th>'.htmlspecialchars($header).'</th>';
                                }
                                echo '</tr></thead>';

                                // Data rows
                                echo '<tbody>';
                                while (($data = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
                                    echo '<tr>';
                                    foreach ($data as $cell) {
                                        echo '<td>'.htmlspecialchars($cell).'</td>';
                                    }
                                    echo '</tr>';
                                }
                                echo '</tbody>';
                                fclose($handle);
                            }
                            ?>
                                </table>
                            </div>
                        <?php } ?>
                    </li>
                <?php } ?>
            </ul>
        <?php } ?>

        <?php if (! empty($results['errors'])) { ?>
            <h2>Errors</h2>
            <ul class="error-list">
                <?php foreach ($results['errors'] as $error) { ?>
                    <li class="error-item"><?php echo htmlspecialchars($error); ?></li>
                <?php } ?>
            </ul>
        <?php } ?>
    </div>
</body>
</html> 