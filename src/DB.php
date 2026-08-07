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
     * @template QueryClass of SelectQuery
     * @param class-string<QueryClass>|null $class Specify an alternate SelectQuery subclass to be used
     * @return ($class is null ? SelectQuery<array<string,string|int|float|null>> : QueryClass)
     */
    public function select(string $table, string|null $class = null): SelectQuery
    {
        if ($class === null)
            // @phpstan-ignore-next-line an array is the default return type
            return new SelectQuery($this, $table);
        else
            return new $class($this, $table);
    }

    /**
     * Create a new INSERT query on the given table.
     * 
     * @template QueryClass of InsertQuery
     * @param class-string<QueryClass> $class Specify an alternate InsertQuery subclass to be used
     * @return QueryClass
     */
    public function insert(string $table, string $class = InsertQuery::class): InsertQuery
    {
        return new $class($this, $table);
    }

    /**
     * Create a new "Upsert" query, which will be executed using SQLite's ON CONFLICT ... DO UPDATE mechanism. Defaults to detecting conflicts via the "id" column, but can be configured via `conflictColumns()`. You must also specify which columns will be updated via `updateColumns()`.
     * 
     * Allows upserting multiple rows in a single query similar to insert.
     * 
     * @template QueryClass of UpsertQuery
     * @param class-string<QueryClass> $class Specify an alternate UpsertQuery subclass to be used
     * @return QueryClass
     */
    public function upsert(string $table, string $class = UpsertQuery::class): UpsertQuery
    {
        return new $class($this, $table);
    }

    /**
     * Create a new UPDATE query on the given table. Optionally set the values right here in the constructor.
     * 
     * @param array<string,string|Stringable|BackedEnum|int|float|bool|(Closure(mixed...):(string|Stringable|BackedEnum|int|float|bool))> $values
     * @template QueryClass of UpdateQuery
     * @param class-string<QueryClass> $class Specify an alternate UpdateQuery subclass to be used
     * @return QueryClass
     */
    public function update(string $table, array $values = [], string $class = UpdateQuery::class): UpdateQuery
    {
        return new $class($this, $table, $values);
    }

    /**
     * Create a new DELETE query on the given table.
     * 
     * @template QueryClass of DeleteQuery
     * @param class-string<QueryClass> $class Specify an alternate DeleteQuery subclass to be used
     * @return QueryClass
     */
    public function delete(string $table, string $class = DeleteQuery::class): DeleteQuery
    {
        return new $class($this, $table);
    }

}
