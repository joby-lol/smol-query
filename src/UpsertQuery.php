<?php

/**
 * smolQuery
 * https://github.com/joby-lol/smol-query
 * (c) 2026 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Query;

use Joby\Smol\Query\InsertQuery;
use RuntimeException;

class UpsertQuery extends InsertQuery
{

    /**
     * Columns to update when a given row already exists. Column data other than these will be discarded for already-existing rows. Defaults to null, which indicates that the columns should be autodetected to update any columns specified in $conflict_columns.
     * @var array<string>|null
     */
    protected array|null $update_columns = null;

    /**
     * Columns to use for checking for conflicts.
     * @var array<string>
     */
    protected array $conflict_columns = ['id'];

    /**
     * Set which columns to update when a given row already exists. Column data other than these will be discarded for already-existing rows. Setting to null will autodetect by updating any columns that are not specified in `conflictColumns()`.
     * @param string|array<string>|null $columns
     */
    public function updateColumns(string|array|null $columns): static
    {
        $this->update_columns = (array) $columns;
        return $this;
    }

    /**
     * Set which columns are used to check for conflicts and determine which inserts should instead be updates of the columns given to updateColumns.
     * @param string|array<string> $columns
     */
    public function conflictColumns(string|array $columns): static
    {
        $this->conflict_columns = (array) $columns;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function sql(): string
    {
        // start with insert sql
        $sql = [parent::sql()];
        // add ON CONFLICT
        if (empty($this->conflict_columns))
            throw new RuntimeException('One or more conflict-detection columns must be specified');
        $sql[] = 'ON CONFLICT(' . implode(',', $this->conflict_columns) . ')';
        // add DO UPDATE with column names optionally auto-detected
        $update_columns = $this->update_columns;
        if (is_null($update_columns))
            $update_columns = array_diff(array_keys($this->rows[0]), $this->conflict_columns);
        if (empty($update_columns))
            throw new RuntimeException('One or more update columns must be specified');
        $sql[] = 'DO UPDATE SET ' . implode(', ', array_map(
            fn($col) => "$col = excluded.$col",
            $update_columns,
        ));
        // return imploded full sql
        return implode(PHP_EOL, $sql);
    }

}
