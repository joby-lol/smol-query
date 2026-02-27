<?php

/**
 * smolQuery
 * https://github.com/joby-lol/smol-query
 * (c) 2026 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Query\Traits;

use BackedEnum;
use Closure;
use Stringable;

trait WhereClauseTrait
{

    /**
     * List of parameter values to be used in WHERE clauses. May by scalar, Stringable, BackedEnum, or a Closure that returns the same.
     * 
     * @var array<int,string|Stringable|BackedEnum|int|float|bool|(Closure(mixed...):(string|Stringable|BackedEnum|int|float|bool))> $where_parameters
     */
    protected array $where_parameters = [];

    /**
     * List of string WHERE clauses to be assembled in the final SQL output.
     * @var array<int, string>
     */
    protected array $where_clauses = [];

    /**
     * Add a WHERE clause to this query. Allows both full statements or column names with a single parameter. Parameters may be a single scalar, Stringable, BackedEnum, or a Closure that returns the same, or an array of such values.
     * 
     * When using the shorthand of ->where('column','value') an optional third parameter may be used to specify an operator (defaults to =).
     * 
     * @param string|Stringable|BackedEnum|int|float|bool|(Closure(mixed...):(string|Stringable|BackedEnum|int|float|bool))|array<string|Stringable|BackedEnum|int|float|bool|(Closure(mixed...):(string|Stringable|BackedEnum|int|float|bool))> $parameter_or_parameters
     */
    public function where(
        string $column_or_statement,
        string|Stringable|BackedEnum|int|float|bool|Closure|array $parameter_or_parameters = [],
        string|null $operator = null,
    ): static
    {
        // first normalize values to an array
        $parameters = $this->normalizeWhereParameters($parameter_or_parameters);
        // next determine whether the column/statement is just a column name
        if ($this->isColumnName($column_or_statement)) {
            $operator = $operator ?? '=';
            $statement = "($column_or_statement $operator ?)";
        }
        else {
            $statement = "($column_or_statement)";
        }
        // finally put everything together into the object
        foreach ($parameters as $p) {
            $this->where_parameters[] = $p;
        }
        $this->where_clauses[] = $statement;
        return $this;
    }

    /**
     * Attempt to guess whether a given column or statment is a lone column name.
     */
    protected function isColumnName(string $column_or_statement): bool
    {
        return !!preg_match(
            '/^[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z_][a-zA-Z0-9_]*)?$/',
            $column_or_statement,
        );
    }

    /**
     * Require a given column name or statement to be null.
     */
    public function whereNull(
        string $column_or_statement,
    ): static
    {
        $this->where_clauses[] = "(($column_or_statement) IS NULL)";
        return $this;
    }

    /**
     * Require a given column name or statement to be non-null.
     */
    public function whereNotNull(
        string $column_or_statement,
    ): static
    {
        $this->where_clauses[] = "(($column_or_statement) IS NOT NULL)";
        return $this;
    }

    /**
     * Require a given column name or statement to be LIKE a given SQL pattern. Optionally force case-insensitivity (although case-insensitivity does impose a potentially large performance penalty).
     */
    public function whereLike(
        string $column_or_statement,
        string $pattern,
        bool $case_insensitive = false,
    ): static
    {
        if ($case_insensitive) {
            $pattern = strtolower($pattern);
            $column_or_statement = "LOWER($column_or_statement)";
        }
        $this->where_parameters[] = $pattern;
        $this->where_clauses[] = "(($column_or_statement) LIKE ?)";
        return $this;
    }

    /**
     * Normalize input parameters to become an array. Accepts a single valid parameter value, or an array of them, and turns it into an int-indexed array of values.
     * 
     * @param string|Stringable|BackedEnum|int|float|bool|(Closure(mixed...):(string|Stringable|BackedEnum|int|float|bool))|array<string|Stringable|BackedEnum|int|float|bool|(Closure(mixed...):(string|Stringable|BackedEnum|int|float|bool))> $parameter_or_parameters
     * @return array<string|Stringable|BackedEnum|int|float|bool|(Closure(mixed...):(string|Stringable|BackedEnum|int|float|bool))>
     */
    protected function normalizeWhereParameters(
        string|Stringable|BackedEnum|int|float|bool|Closure|array $parameter_or_parameters,
    ): array
    {
        if (is_array($parameter_or_parameters))
            // @phpstan-ignore-next-line we can't really type check this
            return array_values($parameter_or_parameters);
        else
            return [$parameter_or_parameters];
    }

    /**
     * Generate the necessary WHERE statement to meet the given requirements. Returns an empty string if there are not any WHERE clauses added.
     */
    protected function sql_where(): string
    {
        if (!$this->where_clauses)
            return '';
        else
            return 'WHERE'
                . PHP_EOL
                . implode(
                    PHP_EOL . 'AND ',
                    $this->where_clauses,
                );
    }

}
