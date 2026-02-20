<?php

/**
 * smolQuery
 * https://github.com/joby-lol/smol-query
 * (c) 2026 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Query;

use BackedEnum;
use RuntimeException;
use Stringable;

class InsertQuery extends AbstractQuery
{

    /**
     * List of rows to insert, with keys as column names and values as values
     * 
     * @var array<int,array<string,string|Stringable|BackedEnum|int|float|bool|null|(callable():(string|Stringable|BackedEnum|int|float|bool|null))>>
     */
    protected array $rows = [];

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
        $parameters = [];
        foreach ($this->rows as $row) {
            foreach ($row as $value) {
                $parameters[] = $value;
            }
        }
        return $this->finalizeParameterList($parameters);
    }

    /**
     * Queue a row of data to be added to the database. The keys of the first row added define the structure that all following rows must follow.
     * 
     * @param array<string,string|Stringable|BackedEnum|int|float|bool|null|(callable():(string|Stringable|BackedEnum|int|float|bool|null))> $row
     */
    public function row(array $row): static
    {
        if (!empty($this->rows))
            $this->assertRowMatchesExisting($row);
        $this->rows[] = $row;
        return $this;
    }

    /**
     * Throw an exception if a given row does not match the structure of the first-added row in this query.
     * 
     * @param array<mixed> $row
     */
    protected function assertRowMatchesExisting(array $row): void
    {
        if (array_keys($row) !== array_keys($this->rows[0]))
            throw new RuntimeException(sprintf(
                "Expected row with column names %s but got %s",
                implode(';', array_keys($this->rows[0])),
                implode(';', array_keys($row)),
            ));
    }

    /**
     * @inheritDoc
     */
    public function sql(): string
    {
        if (empty($this->rows))
            throw new RuntimeException('Can\'t generate SQL for a query that inserts no rows');
        $sql = ["INSERT INTO"];
        $sql[] = $this->table;
        $sql[] = '(' . implode(', ', array_keys($this->rows[0])) . ')';
        $sql[] = 'VALUES';
        $sql[] = $this->sql_row_placeholders();
        return implode(PHP_EOL, $sql);
    }

    /**
     * Generate a number of row placeholders like (?,?),(?,?) necessary to insert all values.
     */
    protected function sql_row_placeholders(): string
    {
        $row_placeholder = array_fill(0, count($this->rows[0]), '?');
        $row_placeholder = implode(',', $row_placeholder);
        $row_placeholder = "($row_placeholder)";
        return implode(
            ',' . PHP_EOL,
            array_fill(0, count($this->rows), $row_placeholder),
        );
    }

    /**
     * Execute the statement and return the number of rows inserted.
     */
    public function execute(): int
    {
        $statement = $this->db->pdo
            ->prepare($this->sql());
        if ($statement === false)
            throw new RuntimeException('Error preparing statement for InsertQuery');
        $result = $statement
            ->execute($this->parameters());
        if ($result === false)
            throw new RuntimeException('Error executing statement for InsertQuery');
        return $statement->rowCount();
    }

}
