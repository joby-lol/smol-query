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

class UpsertQueryTest extends TestCase
{

    protected DB $db;

    protected function setUp(): void
    {
        $this->db = new DB(':memory:');
        $this->db->pdo->exec('CREATE TABLE foo (
            id INTEGER PRIMARY KEY,
            name TEXT,
            value INTEGER,
            flag INTEGER,
            nullable TEXT NULL
        )');
    }

    // --- sql() ---

    public function test_sql_throws_on_empty_rows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->db->upsert('foo')
            ->updateColumns('name')
            ->sql();
    }

    public function test_sql_contains_insert_into(): void
    {
        $sql = $this->db->upsert('foo')
            ->updateColumns(['name', 'value'])
            ->row(['id' => 1, 'name' => 'bar', 'value' => 1])
            ->sql();
        $this->assertStringContainsString('INSERT INTO', $sql);
        $this->assertStringContainsString('foo', $sql);
        $this->assertStringContainsString('(id, name, value)', $sql);
        $this->assertStringContainsString('VALUES', $sql);
        $this->assertStringContainsString('(?,?,?)', $sql);
    }

    public function test_sql_contains_on_conflict_clause(): void
    {
        $sql = $this->db->upsert('foo')
            ->updateColumns(['name', 'value'])
            ->row(['id' => 1, 'name' => 'bar', 'value' => 1])
            ->sql();
        $this->assertStringContainsString('ON CONFLICT(id)', $sql);
        $this->assertStringContainsString('DO UPDATE SET', $sql);
    }

    public function test_sql_only_updates_specified_columns(): void
    {
        $sql = $this->db->upsert('foo')
            ->updateColumns('value')
            ->row(['id' => 1, 'name' => 'bar', 'value' => 1])
            ->sql();
        $setClause = substr($sql, strpos($sql, 'DO UPDATE SET'));
        $this->assertStringContainsString('value = excluded.value', $setClause);
        $this->assertStringNotContainsString('name = excluded.name', $setClause);
    }

    public function test_sql_respects_custom_conflict_column(): void
    {
        $this->db->pdo->exec('CREATE UNIQUE INDEX foo_name ON foo (name)');
        $sql = $this->db->upsert('foo')
            ->conflictColumns('name')
            ->updateColumns('value')
            ->row(['id' => 1, 'name' => 'bar', 'value' => 1])
            ->sql();
        $this->assertStringContainsString('ON CONFLICT(name)', $sql);
        $setClause = substr($sql, strpos($sql, 'DO UPDATE SET'));
        $this->assertStringContainsString('value = excluded.value', $setClause);
    }

    public function test_sql_respects_composite_conflict_columns(): void
    {
        $this->db->pdo->exec('CREATE TABLE bar (
            user_id INTEGER,
            role TEXT,
            value INTEGER,
            PRIMARY KEY (user_id, role)
        )');
        $sql = $this->db->upsert('bar')
            ->conflictColumns(['user_id', 'role'])
            ->updateColumns('value')
            ->row(['user_id' => 1, 'role' => 'admin', 'value' => 42])
            ->sql();
        $this->assertStringContainsString('ON CONFLICT(user_id,role)', $sql);
        $setClause = substr($sql, strpos($sql, 'DO UPDATE SET'));
        $this->assertStringContainsString('value = excluded.value', $setClause);
    }

    public function test_sql_generates_correct_multi_row(): void
    {
        $sql = $this->db->upsert('foo')
            ->updateColumns(['name', 'value'])
            ->row(['id' => 1, 'name' => 'bar', 'value' => 1])
            ->row(['id' => 2, 'name' => 'baz', 'value' => 2])
            ->sql();
        $this->assertEquals(2, substr_count($sql, '(?,?,?)'));
        $this->assertEquals(1, substr_count($sql, 'ON CONFLICT'));
    }

    // --- row() ---

    public function test_row_throws_on_mismatched_keys(): void
    {
        $this->expectException(RuntimeException::class);
        $this->db->upsert('foo')
            ->updateColumns('value')
            ->row(['id' => 1, 'name' => 'bar', 'value' => 1])
            ->row(['id' => 2, 'different_key' => 'baz', 'value' => 2]);
    }

    public function test_row_throws_on_mismatched_key_count(): void
    {
        $this->expectException(RuntimeException::class);
        $this->db->upsert('foo')
            ->updateColumns('value')
            ->row(['id' => 1, 'name' => 'bar', 'value' => 1])
            ->row(['id' => 2, 'name' => 'baz']);
    }

    // --- execute() ---

    public function test_execute_inserts_new_row(): void
    {
        $count = $this->db->upsert('foo')
            ->updateColumns(['name', 'value'])
            ->row(['id' => 1, 'name' => 'bar', 'value' => 42])
            ->execute();
        $this->assertEquals(1, $count);
        $result = $this->db->pdo->query('SELECT * FROM foo WHERE id = 1')->fetch();
        $this->assertEquals('bar', $result['name']);
        $this->assertEquals(42, $result['value']);
    }

    public function test_execute_updates_existing_row_on_conflict(): void
    {
        $this->db->pdo->exec("INSERT INTO foo (id, name, value) VALUES (1, 'bar', 42)");
        $this->db->upsert('foo')
            ->updateColumns(['name', 'value'])
            ->row(['id' => 1, 'name' => 'updated', 'value' => 99])
            ->execute();
        $result = $this->db->pdo->query('SELECT * FROM foo WHERE id = 1')->fetch();
        $this->assertEquals('updated', $result['name']);
        $this->assertEquals(99, $result['value']);
        $this->assertCount(1, $this->db->pdo->query('SELECT * FROM foo')->fetchAll());
    }

    public function test_execute_preserves_non_updated_columns(): void
    {
        $this->db->pdo->exec("INSERT INTO foo (id, name, value) VALUES (1, 'bar', 42)");
        $this->db->upsert('foo')
            ->updateColumns('value')
            ->row(['id' => 1, 'name' => 'should_be_ignored', 'value' => 99])
            ->execute();
        $result = $this->db->pdo->query('SELECT * FROM foo WHERE id = 1')->fetch();
        $this->assertEquals('bar', $result['name']);
        $this->assertEquals(99, $result['value']);
    }

    public function test_execute_handles_mixed_insert_and_update(): void
    {
        $this->db->pdo->exec("INSERT INTO foo (id, name, value) VALUES (1, 'existing', 1)");
        $this->db->upsert('foo')
            ->updateColumns(['name', 'value'])
            ->row(['id' => 1, 'name' => 'updated', 'value' => 10])
            ->row(['id' => 2, 'name' => 'new', 'value' => 20])
            ->execute();
        $rows = $this->db->pdo->query('SELECT * FROM foo ORDER BY id')->fetchAll();
        $this->assertCount(2, $rows);
        $this->assertEquals('updated', $rows[0]['name']);
        $this->assertEquals('new', $rows[1]['name']);
    }

    public function test_execute_inserts_null_values(): void
    {
        $this->db->upsert('foo')
            ->updateColumns(['name', 'value', 'nullable'])
            ->row(['id' => 1, 'name' => 'bar', 'value' => 1, 'nullable' => null])
            ->execute();
        $result = $this->db->pdo->query('SELECT nullable FROM foo WHERE id = 1')->fetch();
        $this->assertNull($result['nullable']);
    }

    public function test_execute_updates_null_values(): void
    {
        $this->db->pdo->exec("INSERT INTO foo (id, name, value, nullable) VALUES (1, 'bar', 1, 'was_set')");
        $this->db->upsert('foo')
            ->updateColumns('nullable')
            ->row(['id' => 1, 'name' => 'bar', 'value' => 1, 'nullable' => null])
            ->execute();
        $result = $this->db->pdo->query('SELECT nullable FROM foo WHERE id = 1')->fetch();
        $this->assertNull($result['nullable']);
    }

    public function test_execute_converts_bool_true_to_one(): void
    {
        $this->db->upsert('foo')
            ->updateColumns(['name', 'value', 'flag'])
            ->row(['id' => 1, 'name' => 'bar', 'value' => 1, 'flag' => true])
            ->execute();
        $result = $this->db->pdo->query('SELECT flag FROM foo WHERE id = 1')->fetch();
        $this->assertEquals(1, $result['flag']);
    }

    public function test_execute_converts_bool_false_to_zero(): void
    {
        $this->db->upsert('foo')
            ->updateColumns(['name', 'value', 'flag'])
            ->row(['id' => 1, 'name' => 'bar', 'value' => 1, 'flag' => false])
            ->execute();
        $result = $this->db->pdo->query('SELECT flag FROM foo WHERE id = 1')->fetch();
        $this->assertEquals(0, $result['flag']);
    }

    public function test_execute_resolves_callable_values(): void
    {
        $this->db->upsert('foo')
            ->updateColumns('name')
            ->row(['id' => 1, 'name' => fn() => 'from_callable', 'value' => 1])
            ->execute();
        $result = $this->db->pdo->query('SELECT name FROM foo WHERE id = 1')->fetch();
        $this->assertEquals('from_callable', $result['name']);
    }

    public function test_execute_resolves_backed_enum_values(): void
    {
        $this->db->upsert('foo')
            ->updateColumns('name')
            ->row(['id' => 1, 'name' => TestStringEnum::Bar, 'value' => 1])
            ->execute();
        $result = $this->db->pdo->query('SELECT name FROM foo WHERE id = 1')->fetch();
        $this->assertEquals('bar', $result['name']);
    }

    public function test_execute_resolves_int_backed_enum_values(): void
    {
        $this->db->upsert('foo')
            ->updateColumns('value')
            ->row(['id' => 1, 'name' => 'test', 'value' => TestIntEnum::Two])
            ->execute();
        $result = $this->db->pdo->query('SELECT value FROM foo WHERE id = 1')->fetch();
        $this->assertEquals(2, $result['value']);
    }

    // autodetection of update columns

    public function test_sql_autodetects_update_columns(): void
    {
        $sql = $this->db->upsert('foo')
            ->row(['id' => 1, 'name' => 'bar', 'value' => 1])
            ->sql();
        $setClause = substr($sql, strpos($sql, 'DO UPDATE SET'));
        $this->assertStringContainsString('name = excluded.name', $setClause);
        $this->assertStringContainsString('value = excluded.value', $setClause);
        $this->assertStringNotContainsString('id = excluded.id', $setClause);
    }

    public function test_sql_throws_on_empty_update_columns_array(): void
    {
        $this->expectException(RuntimeException::class);
        $this->db->upsert('foo')
            ->updateColumns([])
            ->row(['id' => 1, 'name' => 'bar', 'value' => 1])
            ->sql();
    }

    public function test_execute_autodetects_update_columns(): void
    {
        $this->db->pdo->exec("INSERT INTO foo (id, name, value) VALUES (1, 'bar', 42)");
        $this->db->upsert('foo')
            ->row(['id' => 1, 'name' => 'updated', 'value' => 99])
            ->execute();
        $result = $this->db->pdo->query('SELECT * FROM foo WHERE id = 1')->fetch();
        $this->assertEquals('updated', $result['name']);
        $this->assertEquals(99, $result['value']);
        $this->assertCount(1, $this->db->pdo->query('SELECT * FROM foo')->fetchAll());
    }

    public function test_sql_throws_on_empty_conflict_columns_array(): void
    {
        $this->expectException(RuntimeException::class);
        $this->db->upsert('foo')
            ->conflictColumns([])
            ->row(['id' => 1, 'name' => 'bar', 'value' => 1])
            ->sql();
    }

    public function test_sql_throws_when_autodetected_update_columns_is_empty(): void
    {
        // All row columns are conflict columns, leaving nothing to update
        $this->expectException(RuntimeException::class);
        $this->db->upsert('foo')
            ->conflictColumns(['id', 'name', 'value'])
            ->row(['id' => 1, 'name' => 'bar', 'value' => 1])
            ->sql();
    }

}
