<?php

/**
 * smolQuery
 * https://github.com/joby-lol/smol-query
 * (c) 2026 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Query\Traits;

/**
 * Trait for adding the ability to set a list of columns in a query, such as the columns to be selected in a SelectQuery
 */
trait ColumnListTrait
{

    /**
     * List of column names
     * @var string[] $columns
     */
    protected array $columns = [];

    /**
     * Specify a column to get. If you specify a column this way, all columns will no longer be fetched, and you must manually specify all columns you wish to get.
     */
    public function column(string $name): static
    {
        $this->columns[] = $name;
        return $this;
    }

    /**
     * Reset all columns to the default, of getting all of them with *
     */
    public function resetColumns(): static
    {
        $this->columns = [];
        return $this;
    }

    /**
     * Get a properly-formatted list of all column names, ready to use in a SQL query.
     */
    protected function sql_columns(): string
    {
        if (empty($this->columns))
            return '*';
        else
            return implode(',' . PHP_EOL, $this->columns);
    }

}
