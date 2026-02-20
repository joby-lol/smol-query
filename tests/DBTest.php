<?php

/**
 * smolQuery
 * https://github.com/joby-lol/smol-query
 * (c) 2026 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Query;

use PDO;
use PHPUnit\Framework\TestCase;
use SQLite3;

class DBTest extends TestCase
{

    protected DB $db;

    public function setUp(): void
    {
        $this->db = new DB(':memory:');
    }

    public function test_select_returns_select_query(): void
    {
        $this->assertInstanceOf(SelectQuery::class, $this->db->select('foo'));
    }

    public function test_insert_returns_insert_query(): void
    {
        $this->assertInstanceOf(InsertQuery::class, $this->db->insert('foo'));
    }

    public function test_update_returns_update_query(): void
    {
        $this->assertInstanceOf(UpdateQuery::class, $this->db->update('foo'));
    }

    public function test_delete_returns_delete_query(): void
    {
        $this->assertInstanceOf(DeleteQuery::class, $this->db->delete('foo'));
    }

    public function test_like_is_case_sensitive(): void
    {
        $this->db->pdo->exec('CREATE TABLE test (val TEXT)');
        $this->db->pdo->exec("INSERT INTO test VALUES ('Hello')");
        $result = $this->db->pdo->query("SELECT * FROM test WHERE val LIKE 'hello'")->fetch();
        $this->assertFalse($result);
    }

}
