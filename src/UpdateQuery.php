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
use Joby\Smol\Query\Traits\JsonUpdateTrait;
use Joby\Smol\Query\Traits\ValueListTrait;
use Joby\Smol\Query\Traits\WhereClauseTrait;
use RuntimeException;
use Stringable;

/**
 * Query for updating records.
 * 
 * @phpstan-consistent-constructor
 */
class UpdateQuery extends AbstractQuery
{

    use ValueListTrait;
    use WhereClauseTrait;
    use JsonUpdateTrait;

    /**
     * @param array<string,string|Stringable|BackedEnum|int|float|bool|(Closure(mixed...):(string|Stringable|BackedEnum|int|float|bool))> $values
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
            ...$this->jsonParameters(),
            ...$this->where_parameters,
        ]);
    }

    public function execute(bool $without_where = false): int
    {
        if (!$this->where_clauses && !$without_where)
            throw new RuntimeException('Must manually select $without_where to allow updates without a where clause');
        $statement = $this->db->pdo->prepare($this->sql());
        foreach ($this->parameters() as $key => $value)
            $statement->bindValue($key + 1, $value, $this->paramType($value));
        $statement->execute();
        return $statement->rowCount();
    }

    /**
     * @inheritDoc
     */
    public function sql(): string
    {
        if (empty($this->value_list) && empty($this->json_changes))
            throw new RuntimeException('Cannot do an update with no values to set');
        $sql = ['UPDATE'];
        $sql[] = $this->table;
        $sql[] = 'SET';
        $set_statements = array_merge(
            $this->valueStatements(),
            $this->jsonStatements(),
        );
        $sql[] = implode(',' . PHP_EOL, $set_statements);
        $sql[] = $this->sql_where();
        $sql = array_filter($sql, fn($l) => $l !== '');
        return implode(PHP_EOL, $sql);
    }

    /**
     * @return array<string>
     */
    protected function valueStatements(): array
    {
        return array_map(
            fn(string $column): string => "$column = ?",
            array_keys($this->value_list),
        );
    }

}
