<?php

/**
 * smolQuery
 * https://github.com/joby-lol/smol-query
 * (c) 2026 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Query;

use Joby\Smol\Query\Traits\WhereClauseTrait;
use RuntimeException;

class DeleteQuery extends AbstractQuery
{

    use WhereClauseTrait;

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

    public function execute(bool $without_where = false): int
    {
        if (!$this->where_clauses && !$without_where)
            throw new RuntimeException('Must manually select $without_where to allow deletes without a where clause');
        $statement = $this->db->pdo->prepare($this->sql());
        $statement->execute($this->parameters());
        return $statement->rowCount();
    }

    /**
     * @inheritDoc
     */
    public function sql(): string
    {
        $sql = ['DELETE FROM'];
        $sql[] = $this->table;
        $sql[] = $this->sql_where();
        $sql = array_filter($sql, fn($l) => $l !== '');
        return implode(PHP_EOL, $sql);
    }

}
