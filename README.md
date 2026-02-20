# smolQuery

A lightweight SQLite query builder and migration tool for PHP 8.1+.

## Installation

```bash
composer require joby-lol/smol-query
```

## About

smolQuery provides a fluent interface for building SQLite queries and managing schema migrations. It is deliberately SQLite-only, which keeps the implementation simple and the API focused.

## Basic Usage

```php
use Joby\Smol\Query\DB;

$db = new DB('/path/to/database.db');

// Select
$rows = $db->select('users')
    ->where('active', 1)
    ->order('name ASC')
    ->limit(10)
    ->fetchAll();

// Insert
$db->insert('users')
    ->row(['name' => 'Alice', 'email' => 'alice@example.com'])
    ->execute();

// Update
$db->update('users')
    ->value('name', 'Alicia')
    ->where('id', 123)
    ->execute();

// Delete
$db->delete('users')
    ->where('id', 123)
    ->execute();
```

## SELECT Queries

### Fetching

```php
$query = $db->select('users')->where('active', 1);

// Fetch one row (returns null when exhausted)
$row = $query->fetch();

// Fetch all rows as a generator
foreach ($query->fetchAll() as $row) {
    // ...
}

// Fetch a single column as a generator
foreach ($query->fetchColumn('email') as $email) {
    // ...
}

// Count results
$count = $query->count();
```

### WHERE Clauses

```php
// Shorthand column = value
$query->where('status', 'active');

// Raw statement
$query->where('created_at > ?', $timestamp);

// Multiple parameters
$query->where('role = ? OR role = ?', ['admin', 'moderator']);

// NULL checks
$query->whereNull('deleted_at');
$query->whereNotNull('email');

// LIKE (case-sensitive by default, SQLite default is case-insensitive for ASCII)
$query->whereLike('name', 'alice%');
$query->whereLike('name', 'alice%', case_insensitive: true);
```

Multiple `where()` calls are AND'd together. For OR logic, use a single raw statement.

### Projections, Order, Limit, Offset

```php
$db->select('users')
    ->column('id')
    ->column('name')
    ->order('name ASC')
    ->limit(20)
    ->offset(40)
    ->fetchAll();
```

### Hydration

By default, rows are returned as associative arrays. You can hydrate to objects using a callable or a class string.

```php
// Callable hydrator
$query->hydrate(fn($row) => new User($row));

// Class string (uses PDO's FETCH_CLASS — note: not compatible with readonly properties)
$query->hydrate(User::class);

// Reset to arrays
$query->hydrate(null);
```

Parameters and values accept scalars, `Stringable` objects, backed enums (automatically unwrapped), callables (lazily evaluated), and `null`.

## INSERT Queries

```php
// Single row
$db->insert('users')
    ->row(['name' => 'Alice', 'email' => 'alice@example.com'])
    ->execute();

// Multiple rows (must have identical keys)
$db->insert('users')
    ->row(['name' => 'Alice', 'email' => 'alice@example.com'])
    ->row(['name' => 'Bob', 'email' => 'bob@example.com'])
    ->execute();
```

`execute()` returns the number of rows inserted.

## UPDATE Queries

```php
// Single value
$db->update('users')
    ->value('name', 'Alicia')
    ->where('id', 123)
    ->execute();

// Multiple values
$db->update('users')
    ->values(['name' => 'Alicia', 'email' => 'alicia@example.com'])
    ->where('id', 123)
    ->execute();

// Initial values via constructor
$db->update('users', ['name' => 'Alicia'])->where('id', 123)->execute();
```

`execute()` returns the number of affected rows. Without a WHERE clause, an exception is thrown unless you pass `execute(without_where: true)`.

## DELETE Queries

```php
$db->delete('users')->where('id', 123)->execute();
```

Same `without_where` safety guard as UPDATE.

## Migrations

```php
use Joby\Smol\Query\Migrator;

$migrator = new Migrator('/path/to/database.db');

// Programmatic migrations
$migrator->addMigration('001_create_users', '
    CREATE TABLE users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT UNIQUE NOT NULL
    );
    CREATE INDEX idx_users_email ON users(email);
');

// Directory of .sql files (sorted by filename, natural order)
$migrator->addMigrationDirectory(__DIR__ . '/migrations');

$migrator->run();
```

Migration files are named with a sortable prefix: `001_create_users.sql`, `002_add_email_index.sql`, etc. Each migration is recorded by name in a `_migrations` table. Migrations run inside a transaction and roll back on failure. Already-run migrations are skipped; out-of-order migrations throw an exception.

Multi-statement SQL files are fully supported.

### Custom Log Table

```php
$migrator = new Migrator('/path/to/database.db', log_table: '_schema_versions');
```

## Requirements

Fully tested on PHP 8.3+, static analysis for PHP 8.1+. Requires the `pdo_sqlite` and `sqlite3` PHP extensions (both enabled by default in most PHP installations).

## License

MIT License - See [LICENSE](LICENSE) file for details.