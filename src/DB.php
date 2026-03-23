<?php

/**
 * smolQuery
 * https://github.com/joby-lol/smol-query
 * (c) 2026 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Query;

use BackedEnum;
use Closure;
use PDO;
use Stringable;

class DB
{

    public readonly PDO $pdo;

    public readonly string $filename;

    public function __construct(string|Stringable $filename)
    {
        $this->pdo = new PDO('sqlite:' . $filename);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('PRAGMA case_sensitive_like = 1');
        $this->filename = (string) $filename;
    }

    /**
     * Create a new SELECT query on the given table.
     * 
     * @return SelectQuery<array<string,string|int|float|null>>
     */
    public function select(string $table): SelectQuery
    {
        // @phpstan-ignore-next-line an array is the default return type
        return new SelectQuery($this, $table);
    }

    /**
     * Create a new INSERT query on the given table.
     */
    public function insert(string $table): InsertQuery
    {
        return new InsertQuery($this, $table);
    }

    /**
     * Create a new "Upsert" query, which will be executed using SQLite's ON CONFLICT ... DO UPDATE mechanism. Defaults to detecting conflicts via the "id" column, but can be configured via `conflictColumns()`. You must also specify which columns will be updated via `updateColumns()`.
     * 
     * Allows upserting multiple rows in a single query similar to insert.
     */
    public function upsert(string $table): UpsertQuery
    {
        return new UpsertQuery($this, $table);
    }

    /**
     * Create a new UPDATE query on the given table. Optionally set the values right here in the constructor.
     * 
     * @param array<string,string|Stringable|BackedEnum|int|float|bool|(Closure(mixed...):(string|Stringable|BackedEnum|int|float|bool))> $values
     */
    public function update(string $table, array $values = []): UpdateQuery
    {
        return new UpdateQuery($this, $table, $values);
    }

    /**
     * Create a new DELETE query on the given table.
     */
    public function delete(string $table): DeleteQuery
    {
        return new DeleteQuery($this, $table);
    }

}
