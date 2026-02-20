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

class InsertQueryTest extends TestCase
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
    }

    // --- sql() ---

    public function test_sql_throws_on_empty_rows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->db->insert('foo')->sql();
    }

    public function test_sql_generates_correct_single_row(): void
    {
        $sql = $this->db->insert('foo')
            ->row(['name' => 'bar', 'value' => 1])
            ->sql();
        $this->assertStringContainsString('INSERT INTO', $sql);
        $this->assertStringContainsString('foo', $sql);
        $this->assertStringContainsString('(name, value)', $sql);
        $this->assertStringContainsString('VALUES', $sql);
        $this->assertStringContainsString('(?,?)', $sql);
    }

    public function test_sql_generates_correct_multi_row(): void
    {
        $sql = $this->db->insert('foo')
            ->row(['name' => 'bar', 'value' => 1])
            ->row(['name' => 'baz', 'value' => 2])
            ->sql();
        $this->assertEquals(2, substr_count($sql, '(?,?)'));
    }

    // --- row() ---

    public function test_row_throws_on_mismatched_keys(): void
    {
        $this->expectException(RuntimeException::class);
        $this->db->insert('foo')
            ->row(['name' => 'bar', 'value' => 1])
            ->row(['name' => 'baz', 'different_key' => 2]);
    }

    public function test_row_throws_on_mismatched_key_count(): void
    {
        $this->expectException(RuntimeException::class);
        $this->db->insert('foo')
            ->row(['name' => 'bar', 'value' => 1])
            ->row(['name' => 'baz']);
    }

    // --- execute() ---

    public function test_execute_inserts_single_row(): void
    {
        $count = $this->db->insert('foo')
            ->row(['name' => 'bar', 'value' => 1])
            ->execute();
        $this->assertEquals(1, $count);
        $result = $this->db->pdo->query('SELECT * FROM foo')->fetchAll();
        $this->assertCount(1, $result);
    }

    public function test_execute_inserts_multiple_rows(): void
    {
        $count = $this->db->insert('foo')
            ->row(['name' => 'bar', 'value' => 1])
            ->row(['name' => 'baz', 'value' => 2])
            ->row(['name' => 'qux', 'value' => 3])
            ->execute();
        $this->assertEquals(3, $count);
        $result = $this->db->pdo->query('SELECT * FROM foo')->fetchAll();
        $this->assertCount(3, $result);
    }

    public function test_execute_inserts_null_values(): void
    {
        $this->db->insert('foo')
            ->row(['name' => 'bar', 'value' => 1, 'nullable' => null])
            ->execute();
        $result = $this->db->pdo->query('SELECT nullable FROM foo')->fetch();
        $this->assertNull($result['nullable']);
    }

    public function test_execute_converts_bool_true_to_one(): void
    {
        $this->db->insert('foo')
            ->row(['name' => 'bar', 'value' => 1, 'flag' => true])
            ->execute();
        $result = $this->db->pdo->query('SELECT flag FROM foo')->fetch();
        $this->assertEquals(1, $result['flag']);
    }

    public function test_execute_converts_bool_false_to_zero(): void
    {
        $this->db->insert('foo')
            ->row(['name' => 'bar', 'value' => 1, 'flag' => false])
            ->execute();
        $result = $this->db->pdo->query('SELECT flag FROM foo')->fetch();
        $this->assertEquals(0, $result['flag']);
    }

    public function test_execute_resolves_callable_values(): void
    {
        $this->db->insert('foo')
            ->row(['name' => fn() => 'from_callable', 'value' => 1])
            ->execute();
        $result = $this->db->pdo->query('SELECT name FROM foo')->fetch();
        $this->assertEquals('from_callable', $result['name']);
    }

    public function test_execute_resolves_backed_enum_values(): void
    {
        $this->db->insert('foo')
            ->row(['name' => TestStringEnum::Bar, 'value' => 1])
            ->execute();
        $result = $this->db->pdo->query('SELECT name FROM foo')->fetch();
        $this->assertEquals('bar', $result['name']);
    }

    public function test_execute_resolves_int_backed_enum_values(): void
    {
        $this->db->insert('foo')
            ->row(['name' => 'test', 'value' => TestIntEnum::Two])
            ->execute();
        $result = $this->db->pdo->query('SELECT value FROM foo')->fetch();
        $this->assertEquals(2, $result['value']);
    }

}
