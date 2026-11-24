<?php

/**
 * smolQuery
 * https://github.com/joby-lol/smol-query
 * (c) 2026 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Query\Traits;

/**
 * Trait for tracking sets and deletes on a single JSON column.
 */
class JsonColumnStatement
{
    /** @var array<string> */
    protected array $remove = [];

    /** @var array<string,mixed> */
    protected array $set = [];

    public function __construct(
        protected string $column
    ) {}

    public function statement(): string
    {
        $statement = $this->column;
        $statement = $this->wrapSetStatement($statement);
        $statement = $this->wrapRemoveStatement($statement);
        $statement = sprintf('%s = %s', $this->column, $statement);
        return $statement;
    }

    /**
     * Set the given data at the given path, overwriting anything that is already there.
     */
    public function set(string $path, mixed $data): static
    {
        $this->set[$path] = $data;
        $this->remove = array_diff($this->remove, [$path]);
        return $this;
    }

    /**
     * Indicate that a path should be removed from the given column if it exists.
     */
    public function remove(string $path): static
    {
        if (!in_array($path, $this->remove))
            $this->remove[] = $path;
        unset($this->set[$path]);
        return $this;
    }

    /**
     * Get all parameters necessary to use this call's statement
     * @return array<int,string>
     */
    public function parameters(): array
    {
        $output = [];
        foreach ($this->set as $path => $data) {
            $output[] = $path;
            $output[] = json_encode($data, JSON_THROW_ON_ERROR);
        }
        foreach ($this->remove as $path)
            $output[] = $path;
        return $output;
    }

    /**
     * Wrap the statement under construction in a json_set call if necessary.
     */
    protected function wrapSetStatement(string $statement): string
    {
        if (!$this->set)
            return $statement;
        $updated = "json_set(" . PHP_EOL . "coalesce($statement, '{}')";
        $updated .= str_repeat(',' . PHP_EOL . '?,json(?)', count($this->set));
        $updated .= PHP_EOL . ')';
        return $updated;
    }

    /**
     * Wrap the statement under construction in a json_remove call if necessary.
     */
    protected function wrapRemoveStatement(string $statement): string
    {
        if (!$this->remove)
            return $statement;
        $updated = 'json_remove(' . PHP_EOL . $statement;
        $updated .= str_repeat(',' . PHP_EOL . '?', count($this->remove));
        $updated .= PHP_EOL . ')';
        return $updated;
    }
}
