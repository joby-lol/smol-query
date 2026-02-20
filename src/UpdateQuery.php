<?php

/**
 * smolQuery
 * https://github.com/joby-lol/smol-query
 * (c) 2026 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Query;

use BackedEnum;
use Joby\Smol\Query\Traits\ValueListTrait;
use Joby\Smol\Query\Traits\WhereClauseTrait;
use RuntimeException;
use Stringable;

class UpdateQuery extends AbstractQuery
{

    use ValueListTrait;
    use WhereClauseTrait;

    /**
     * @param array<string,string|Stringable|BackedEnum|int|float|bool|(callable(mixed...):(string|Stringable|BackedEnum|int|float|bool))> $values
     */
    public function __construct(
        DB $db,
        public readonly string $table,
        array $values = [],
    )
    {
        parent::__construct($db);
        $this->values($values);
    }

    /**
     * @inheritDoc
     */
    public function parameters(): array
    {
        return $this->finalizeParameterList([
            ...$this->valueParameters(),
            ...$this->where_parameters,
        ]);
    }

    public function execute(bool $without_where = false): int
    {
        if (!$this->where_clauses && !$without_where)
            throw new RuntimeException('Must manually select $without_where to allow updates without a where clause');
        $statement = $this->db->pdo->prepare($this->sql());
        $statement->execute($this->parameters());
        return $statement->rowCount();
    }

    /**
     * @inheritDoc
     */
    public function sql(): string
    {
        if (empty($this->value_list))
            throw new RuntimeException('Cannot do an update with no values to set');
        $sql = ['UPDATE'];
        $sql[] = $this->table;
        $sql[] = 'SET';
        $sql[] = implode(',' . PHP_EOL, array_map(
            fn(string $column): string => "$column = ?",
            array_keys($this->value_list),
        ));
        $sql[] = $this->sql_where();
        $sql = array_filter($sql, fn($l) => $l !== '');
        return implode(PHP_EOL, $sql);
    }

}
