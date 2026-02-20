<?php

/**
 * smolQuery
 * https://github.com/joby-lol/smol-query
 * (c) 2026 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Query\Traits;

/**
 * Trait for adding the ability to set a list of order statement clauses to the query, such as ordering a SelectQuery
 */
trait OrderListTrait
{

    /**
     * List of ordering rules
     * @var string[] $order_rules
     */
    protected array $order_rules = [];

    /**
     * Add an order rule to this query.
     */
    public function order(string $order_rule): static
    {
        $this->order_rules[] = $order_rule;
        return $this;
    }

    /**
     * Reset all ordering rules.
     */
    public function resetOrder(): static
    {
        $this->order_rules = [];
        return $this;
    }

    /**
     * Get a properly-formatted ORDER BY statement, 
     */
    protected function sql_order(): string
    {
        if (empty($this->order_rules))
            return "";
        else
            return 'ORDER BY'
                . PHP_EOL
                . implode(',' . PHP_EOL, $this->order_rules);
    }

}
