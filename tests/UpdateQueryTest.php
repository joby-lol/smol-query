<?php

/**
 * smolQuery
 * https://github.com/joby-lol/smol-query
 * (c) 2026 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Query;

use PHPUnit\Framework\TestCase;
use RuntimeException;

class UpdateQueryTest extends TestCase
{

    protected DB $db;

    protected function setUp(): void
    {
        $this->db = new DB(':memory:');
        $this->db->pdo->exec('CREATE TABLE foo (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT,
            value INTEGER,
            flag INTEGER,
            nullable TEXT NULL
        )');
        $this->db->pdo->exec("INSERT INTO foo (name, value, flag) VALUES ('alpha', 1, 0)");
        $this->db->pdo->exec("INSERT INTO foo (name, value, flag) VALUES ('beta', 2, 0)");
        $this->db->pdo->exec("INSERT INTO foo (name, value, flag) VALUES ('gamma', 3, 0)");
    }

    protected function fetchAll(): array
    {
        return $this->db->pdo->query('SELECT * FROM foo ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC);
    }

    protected function fetchRow(int $id): array|false
    {
        return $this->db->pdo
            ->query("SELECT * FROM foo WHERE id = $id")
            ->fetch(\PDO::FETCH_ASSOC);
    }

    // --- safety guards ---

    public function test_execute_throws_without_where_clause(): void
    {
        $this->expectException(RuntimeException::class);
        $this->db->update('foo')->value('name', 'changed')->execute();
    }

    public function test_execute_allows_no_where_with_flag(): void
    {
        $this->db->update('foo')->value('name', 'changed')->execute(true);
        foreach ($this->fetchAll() as $row) {
            $this->assertEquals('changed', $row['name']);
        }
    }

    public function test_sql_throws_with_no_values(): void
    {
        $this->expectException(RuntimeException::class);
        $this->db->update('foo')->where('id', 1)->sql();
    }

    // --- basic updates ---

    public function test_execute_updates_matching_row(): void
    {
        $this->db->update('foo')
            ->value('name', 'changed')
            ->where('id', 1)
            ->execute();
        $this->assertEquals('changed', $this->fetchRow(1)['name']);
        $this->assertEquals('beta', $this->fetchRow(2)['name']);
    }

    public function test_execute_returns_affected_row_count(): void
    {
        $count = $this->db->update('foo')
            ->value('name', 'changed')
            ->where('value > ?', 1)
            ->execute();
        $this->assertEquals(2, $count);
    }

    public function test_execute_returns_zero_when_nothing_matched(): void
    {
        $count = $this->db->update('foo')
            ->value('name', 'changed')
            ->where('name', 'nonexistent')
            ->execute();
        $this->assertEquals(0, $count);
    }

    // --- value() and values() ---

    public function test_value_sets_single_column(): void
    {
        $this->db->update('foo')
            ->value('name', 'changed')
            ->where('id', 1)
            ->execute();
        $this->assertEquals('changed', $this->fetchRow(1)['name']);
    }

    public function test_value_can_be_chained(): void
    {
        $this->db->update('foo')
            ->value('name', 'changed')
            ->value('value', 99)
            ->where('id', 1)
            ->execute();
        $row = $this->fetchRow(1);
        $this->assertEquals('changed', $row['name']);
        $this->assertEquals(99, $row['value']);
    }

    public function test_values_sets_multiple_columns(): void
    {
        $this->db->update('foo')
            ->values(['name' => 'changed', 'value' => 99])
            ->where('id', 1)
            ->execute();
        $row = $this->fetchRow(1);
        $this->assertEquals('changed', $row['name']);
        $this->assertEquals(99, $row['value']);
    }

    public function test_values_overwrites_previous_values(): void
    {
        $this->db->update('foo')
            ->value('name', 'first')
            ->values(['name' => 'overwritten', 'value' => 99])
            ->where('id', 1)
            ->execute();
        $this->assertEquals('overwritten', $this->fetchRow(1)['name']);
    }

    public function test_constructor_values_parameter(): void
    {
        $this->db->update('foo', ['name' => 'from_constructor'])
            ->where('id', 1)
            ->execute();
        $this->assertEquals('from_constructor', $this->fetchRow(1)['name']);
    }

    // --- type handling ---

    public function test_value_converts_bool_true_to_one(): void
    {
        $this->db->update('foo')
            ->value('flag', true)
            ->where('id', 1)
            ->execute();
        $this->assertEquals(1, $this->fetchRow(1)['flag']);
    }

    public function test_value_converts_bool_false_to_zero(): void
    {
        $this->db->update('foo')
            ->value('flag', false)
            ->where('id', 1)
            ->execute();
        $this->assertEquals(0, $this->fetchRow(1)['flag']);
    }

    public function test_value_resolves_callable(): void
    {
        $this->db->update('foo')
            ->value('name', fn() => 'from_callable')
            ->where('id', 1)
            ->execute();
        $this->assertEquals('from_callable', $this->fetchRow(1)['name']);
    }

    public function test_value_resolves_backed_enum(): void
    {
        $this->db->update('foo')
            ->value('name', TestStringEnum::Bar)
            ->where('id', 1)
            ->execute();
        $this->assertEquals('bar', $this->fetchRow(1)['name']);
    }

    public function test_value_resolves_int_backed_enum(): void
    {
        $this->db->update('foo')
            ->value('value', TestIntEnum::Two)
            ->where('id', 1)
            ->execute();
        $this->assertEquals(2, $this->fetchRow(1)['value']);
    }

    public function test_value_accepts_null(): void
    {
        $this->db->update('foo')
            ->value('nullable', null)
            ->where('id', 1)
            ->execute();
        $this->assertNull($this->fetchRow(1)['nullable']);
    }

    // --- WHERE variants ---

    public function test_multiple_where_clauses_are_anded(): void
    {
        $this->db->update('foo')
            ->value('name', 'changed')
            ->where('name', 'alpha')
            ->where('value', 1)
            ->execute();
        $this->assertEquals('changed', $this->fetchRow(1)['name']);
        $this->assertEquals('beta', $this->fetchRow(2)['name']);
    }

    public function test_where_with_raw_statement(): void
    {
        $this->db->update('foo')
            ->value('name', 'changed')
            ->where('value > ?', 1)
            ->execute();
        $this->assertEquals('alpha', $this->fetchRow(1)['name']);
        $this->assertEquals('changed', $this->fetchRow(2)['name']);
        $this->assertEquals('changed', $this->fetchRow(3)['name']);
    }

    public function test_where_null(): void
    {
        $this->db->pdo->exec("INSERT INTO foo (name, value) VALUES (NULL, 99)");
        $this->db->update('foo')
            ->value('name', 'was_null')
            ->whereNull('name')
            ->execute();
        $rows = $this->db->pdo->query("SELECT name FROM foo WHERE name = 'was_null'")->fetchAll();
        $this->assertCount(1, $rows);
    }

    public function test_where_not_null(): void
    {
        $this->db->pdo->exec("INSERT INTO foo (name, value) VALUES (NULL, 99)");
        $count = $this->db->update('foo')
            ->value('flag', 1)
            ->whereNotNull('name')
            ->execute();
        $this->assertEquals(3, $count);
    }

    public function test_where_with_callable_parameter(): void
    {
        $this->db->update('foo')
            ->value('name', 'changed')
            ->where('name', fn() => 'alpha')
            ->execute();
        $this->assertEquals('changed', $this->fetchRow(1)['name']);
        $this->assertEquals('beta', $this->fetchRow(2)['name']);
    }

    public function test_value_accepts_stringable(): void
    {
        $this->db->update('foo')
            ->value('name', new TestStringable('from_stringable'))
            ->where('id', 1)
            ->execute();
        $this->assertEquals('from_stringable', $this->fetchRow(1)['name']);
    }

}
