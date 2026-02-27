<?php

/**
 * smolQuery
 * https://github.com/joby-lol/smol-query
 * (c) 2026 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Query;

use Generator;
use InvalidArgumentException;
use Joby\Smol\Query\Traits\ColumnListTrait;
use Joby\Smol\Query\Traits\LimitOffsetTrait;
use Joby\Smol\Query\Traits\OrderListTrait;
use Joby\Smol\Query\Traits\WhereClauseTrait;
use PDO;
use PDOStatement;

/**
 * @template ReturnType of mixed
 */
class SelectQuery extends AbstractQuery
{

    use ColumnListTrait;
    use WhereClauseTrait;
    use OrderListTrait;
    use LimitOffsetTrait;

    /**
     * Optional hydration callback for construction objects (or anything else) from the raw database row.
     * 
     * @var (callable(array<string,string|int|float|null>):ReturnType)|null
     */
    protected mixed $hydrate_callback = null;

    /**
     * Optional class string of an object to ask PDO to hydrate to automatically.
     * 
     * @var class-string<ReturnType&object>|null
     */
    protected string|null $hydrate_class = null;

    protected PDOStatement|null $fetch_statement = null;

    /**
     * List of joins, each includes its type (i.e. JOIN or LEFT OUTER JOIN), the table name, the ON statement
     * @var array<array{type:string,table:string,on:string}> $joins
     */
    protected array $joins = [];

    public function __construct(
        DB $db,
        public readonly string $table,
    )
    {
        parent::__construct($db);
    }

    /**
     * @inheritDoc
     */
    public function parameters(): array
    {
        return $this->finalizeParameterList($this->where_parameters);
    }

    /**
     * @inheritDoc
     */
    public function sql(): string
    {
        $sql = ["SELECT"];
        $sql[] = $this->sql_columns();
        $sql[] = "FROM";
        $sql[] = $this->table;
        $sql[] = $this->sql_joins();
        $sql[] = $this->sql_where();
        $sql[] = $this->sql_order();
        $sql[] = $this->sql_limit_offset();
        $sql = array_filter($sql, fn($l) => $l !== '');
        return implode(PHP_EOL, $sql);
    }

    /**
     * Add an inner join to this query on the given table using the given ON statement.
     */
    public function join(string $table, string $on_statement): static
    {
        $this->joins[] = [
            'type'  => 'JOIN',
            'table' => $table,
            'on'    => $on_statement,
        ];
        return $this;
    }

    public function leftJoin(string $table, string $on_statement): static
    {
        $this->joins[] = [
            'type'  => 'LEFT OUTER JOIN',
            'table' => $table,
            'on'    => $on_statement,
        ];
        return $this;
    }

    /**
     * Helper function for constructing SQL from joins
     */
    protected function sql_joins(): string
    {
        return implode(PHP_EOL, array_map(
            /** @param array{type:string,table:string,on:string} $join */
            fn(array $join): string => sprintf(
                '%s %s ON %s',
                $join['type'],
                $join['table'],
                $join['on'],
            ),
            $this->joins,
        ));
    }

    /**
     * @return Generator<ReturnType>
     */
    public function fetchAll(): iterable
    {
        $statement = $this->newFetchStatement();
        if ($this->hydrate_callback) {
            while (($r = $statement->fetch()) !== false) {
                // @phpstan-ignore-next-line it does get an array here
                yield ($this->hydrate_callback)($r);
            }
        }
        else {
            while (($r = $statement->fetch()) !== false) {
                yield $r;
            }
        }
    }

    /**
     * @return ReturnType|null
     */
    public function fetch(): mixed
    {
        if (!$this->fetch_statement) {
            $this->fetch_statement = $this->newFetchStatement();
        }
        $result = $this->fetch_statement->fetch();
        if ($result === false)
            return null;
        if ($this->hydrate_callback)
            $result = ($this->hydrate_callback)($result); // @phpstan-ignore-line
        return $result;
    }

    /**
     * @return Generator<string|int|float|null>
     */
    public function fetchColumn(string $column_name): Generator
    {
        $copy = clone $this;
        $copy->column($column_name);
        $statement = $copy->newFetchStatement(true);
        while (($r = $statement->fetchColumn()) !== false) {
            assert(is_scalar($r));
            yield $r;
        }
    }

    /**
     * Count the results that would be returned from this query.
     */
    public function count(): int
    {
        $statement = $this->db->pdo->prepare(
            'SELECT COUNT(*) FROM (' . $this->sql() . ')'
        );
        $statement->execute($this->parameters());
        return (int) $statement->fetchColumn();
    }

    /**
     * Build a new fetch statement using this object's SQL and parameters. Optionally with PDO's built-in object hydration disabled, for cases where we're not using that, such as fetchColumn
     */
    protected function newFetchStatement(bool $no_hydration = false): PDOStatement
    {
        $statement = $this->db->pdo->prepare($this->sql());
        $statement->execute($this->parameters());
        if ($this->hydrate_class && !$no_hydration)
            $statement->setFetchMode(PDO::FETCH_CLASS | PDO::FETCH_PROPS_LATE, $this->hydrate_class);
        else
            $statement->setFetchMode(PDO::FETCH_ASSOC);
        return $statement;
    }

    /**
     * Set a hydrator for this query, either a class string or callback that can take in a raw row and return some other object or value.
     * 
     * @template NewReturnType of mixed
     * @param (callable(array<string,string|int|float|null>):NewReturnType)|class-string<NewReturnType> $callback_or_class
     * @return ($callback_or_class is null ? static<array<string,string|int|float|null>> : static<NewReturnType>)
     * @phpstan-self-out ($callback_or_class is null ? static<array<string,string|int|float|null>> : static<NewReturnType>)
     */
    public function hydrate(callable|string|null $callback_or_class): static
    {
        // if it's null we're unsetting hydration
        if ($callback_or_class === null) {
            $this->hydrate_callback = null;
            $this->hydrate_class = null;
        }
        // if it's a callable set the callback and unset the class
        elseif (is_callable($callback_or_class)) {
            // @phpstan-ignore-next-line it is actually right, but this is pushiing the limits of phpstan
            $this->hydrate_callback = $callback_or_class;
            $this->hydrate_class = null;
        }
        // otherwise ensure the class exists and set the class and unset the callback
        elseif (class_exists($callback_or_class)) {
            $this->hydrate_callback = null;
            // @phpstan-ignore-next-line it is actually right, but this is pushign the limits of phpstan
            $this->hydrate_class = $callback_or_class;
        }
        // otherwise throw an exception
        else {
            throw new InvalidArgumentException("Hydrator class '$callback_or_class' does not exist");
        }
        // @phpstan-ignore-next-line it is actually right, but this is pushing the limits of phpstan
        return $this;
    }

}
