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

/**
 * Trait for adding the ability to set JSON values using an ergonomic API.
 */
trait JsonUpdateTrait
{
    /**
     * Array of JSON change-tracking and statement-building objects.
     * @var array<string,JsonColumnStatement>
     */
    protected array $json_changes = [];

    public function jsonSet(string $column, string $path, mixed $value): static
    {
        if (!isset($this->json_changes[$column]))
            $this->json_changes[$column] = new JsonColumnStatement($column);
        $this->json_changes[$column]->set($path, $value);
        return $this;
    }

    public function jsonRemove(string $column, string $path): static
    {
        if (!isset($this->json_changes[$column]))
            $this->json_changes[$column] = new JsonColumnStatement($column);
        $this->json_changes[$column]->remove($path);
        return $this;
    }

    /**
     * @return array<string>
     */
    protected function jsonStatements(): array
    {
        return array_map(
            fn(JsonColumnStatement $s): string => $s->statement(),
            $this->json_changes
        );
    }

    /**
     * @return array<int,string|Stringable|BackedEnum|int|float|bool|null|(Closure(mixed...):(string|Stringable|BackedEnum|int|float|bool|null))>
     */
    protected function jsonParameters(): array
    {
        $output = [];
        foreach ($this->json_changes as $s)
            foreach ($s->parameters() as $p)
                $output[] = $p;
        return $output;
    }
}
