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

class DeleteQueryTest extends TestCase
{

    protected DB $db;

    protected function setUp(): void
    {
        $this->db = new DB(':memory:');
        $this->db->pdo->exec('CREATE TABLE foo (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT,
            value INTEGER
        )');
        $this->db->pdo->exec("INSERT INTO foo (name, value) VALUES ('alpha', 1)");
        $this->db->pdo->exec("INSERT INTO foo (name, value) VALUES ('beta', 2)");
        $this->db->pdo->exec("INSERT INTO foo (name, value) VALUES ('gamma', 3)");
    }

    protected function countRows(): int
    {
        return (int) $this->db->pdo->query('SELECT COUNT(*) FROM foo')->fetchColumn();
    }

    // --- safety guard ---

    public function test_execute_throws_without_where_clause(): void
    {
        $this->expectException(RuntimeException::class);
        $this->db->delete('foo')->execute();
    }

    public function test_execute_allows_no_where_with_flag(): void
    {
        $this->db->delete('foo')->execute(true);
        $this->assertEquals(0, $this->countRows());
    }

    // --- basic deletion ---

    public function test_execute_deletes_matching_row(): void
    {
        $this->db->delete('foo')
            ->where('name', 'alpha')
            ->execute();
        $this->assertEquals(2, $this->countRows());
    }

    public function test_execute_returns_affected_row_countRows(): void
    {
        $count = $this->db->delete('foo')
            ->where('value > ?', 1)
            ->execute();
        $this->assertEquals(2, $count);
    }

    public function test_execute_deletes_nothing_when_where_matches_nothing(): void
    {
        $count = $this->db->delete('foo')
            ->where('name', 'nonexistent')
            ->execute();
        $this->assertEquals(0, $count);
        $this->assertEquals(3, $this->countRows());
    }

    // --- WHERE variants ---

    public function test_where_shorthand_with_column_and_value(): void
    {
        $this->db->delete('foo')->where('name', 'beta')->execute();
        $remaining = $this->db->pdo->query('SELECT name FROM foo')->fetchAll(\PDO::FETCH_COLUMN);
        $this->assertNotContains('beta', $remaining);
        $this->assertContains('alpha', $remaining);
        $this->assertContains('gamma', $remaining);
    }

    public function test_multiple_where_clauses_are_anded(): void
    {
        $this->db->delete('foo')
            ->where('name', 'alpha')
            ->where('value', 1)
            ->execute();
        $this->assertEquals(2, $this->countRows());
    }

    public function test_multiple_where_clauses_both_must_match(): void
    {
        // name matches alpha but value doesn't — should delete nothing
        $count = $this->db->delete('foo')
            ->where('name', 'alpha')
            ->where('value', 99)
            ->execute();
        $this->assertEquals(0, $count);
        $this->assertEquals(3, $this->countRows());
    }

    public function test_where_with_raw_statement(): void
    {
        $this->db->delete('foo')
            ->where('value > ?', 1)
            ->execute();
        $this->assertEquals(1, $this->countRows());
    }

    public function test_where_with_multiple_parameters(): void
    {
        $this->db->delete('foo')
            ->where('value = ? OR value = ?', [1, 3])
            ->execute();
        $this->assertEquals(1, $this->countRows());
    }

    public function test_where_null(): void
    {
        $this->db->pdo->exec("INSERT INTO foo (name, value) VALUES (NULL, 99)");
        $this->db->delete('foo')->whereNull('name')->execute();
        $this->assertEquals(3, $this->countRows());
    }

    public function test_where_not_null(): void
    {
        $this->db->pdo->exec("INSERT INTO foo (name, value) VALUES (NULL, 99)");
        $count = $this->db->delete('foo')->whereNotNull('name')->execute();
        $this->assertEquals(3, $count);
        $this->assertEquals(1, $this->countRows());
    }

    public function test_where_with_callable_parameter(): void
    {
        $this->db->delete('foo')
            ->where('name', fn() => 'alpha')
            ->execute();
        $this->assertEquals(2, $this->countRows());
    }

    public function test_where_with_backed_enum_parameter(): void
    {
        $this->db->delete('foo')
            ->where('name', TestStringEnum::Bar)
            ->execute();
        // 'bar' doesn't exist so nothing deleted
        $this->assertEquals(3, $this->countRows());
    }

}
