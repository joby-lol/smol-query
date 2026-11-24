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
            nullable TEXT NULL,
            json_data TEXT NULL,
            json_other TEXT NULL
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

    // --- jsonSet() and jsonRemove() ---

    public function test_json_set_adds_property(): void
    {
        $this->db->pdo->exec("UPDATE foo SET json_data = '{}'");

        $this->db->update('foo')
            ->jsonSet('json_data', '$.foo', 'bar')
            ->where('id', 1)
            ->execute();

        $this->assertSame(
            ['foo' => 'bar'],
            json_decode($this->fetchRow(1)['json_data'], true)
        );
        $this->assertSame('{}', $this->fetchRow(2)['json_data']);
    }

    public function test_json_set_overwrites_existing_property(): void
    {
        $this->db->pdo->exec("UPDATE foo SET json_data = '{\"foo\":1,\"bar\":2}'");

        $this->db->update('foo')
            ->jsonSet('json_data', '$.foo', 99)
            ->where('id', 1)
            ->execute();

        $this->assertSame(
            ['foo' => 99, 'bar' => 2],
            json_decode($this->fetchRow(1)['json_data'], true)
        );
    }

    public function test_json_set_initializes_null_column(): void
    {
        $this->db->update('foo')
            ->jsonSet('json_data', '$.foo', 'bar')
            ->where('id', 1)
            ->execute();

        $this->assertSame(
            ['foo' => 'bar'],
            json_decode($this->fetchRow(1)['json_data'], true)
        );
        $this->assertNull($this->fetchRow(2)['json_data']);
    }

    public function test_json_set_preserves_json_types(): void
    {
        $expected = [
            'string' => '123',
            'integer' => 123,
            'float' => 1.25,
            'true' => true,
            'false' => false,
            'null' => null,
            'array' => [1, 2, 3],
            'object' => ['foo' => 'bar'],
            'json_string' => '{"foo":"bar"}',
        ];

        $query = $this->db->update('foo')->where('id', 1);

        foreach ($expected as $key => $value)
            $query->jsonSet('json_data', '$.' . $key, $value);

        $query->execute();

        $this->assertSame(
            $expected,
            json_decode($this->fetchRow(1)['json_data'], true)
        );
    }

    public function test_json_set_preserves_empty_object(): void
    {
        $this->db->update('foo')
            ->jsonSet('json_data', '$.object', new \stdClass())
            ->jsonSet('json_data', '$.array', [])
            ->where('id', 1)
            ->execute();

        $data = json_decode($this->fetchRow(1)['json_data']);

        $this->assertInstanceOf(\stdClass::class, $data->object);
        $this->assertSame([], $data->array);
    }

    public function test_json_set_updates_nested_property(): void
    {
        $this->db->pdo->exec(
            "UPDATE foo SET json_data = '{\"nested\":{\"a\":1,\"b\":2}}'"
        );

        $this->db->update('foo')
            ->jsonSet('json_data', '$.nested.a', 99)
            ->where('id', 1)
            ->execute();

        $this->assertSame(
            ['nested' => ['a' => 99, 'b' => 2]],
            json_decode($this->fetchRow(1)['json_data'], true)
        );
    }

    public function test_json_set_overwrites_pending_set(): void
    {
        $this->db->update('foo')
            ->jsonSet('json_data', '$.foo', 'first')
            ->jsonSet('json_data', '$.foo', 'second')
            ->where('id', 1)
            ->execute();

        $this->assertSame(
            ['foo' => 'second'],
            json_decode($this->fetchRow(1)['json_data'], true)
        );
    }

    public function test_json_remove_removes_property(): void
    {
        $this->db->pdo->exec("UPDATE foo SET json_data = '{\"a\":1,\"b\":2}'");

        $this->db->update('foo')
            ->jsonRemove('json_data', '$.a')
            ->where('id', 1)
            ->execute();

        $this->assertSame(
            ['b' => 2],
            json_decode($this->fetchRow(1)['json_data'], true)
        );
        $this->assertSame(
            ['a' => 1, 'b' => 2],
            json_decode($this->fetchRow(2)['json_data'], true)
        );
    }

    public function test_json_remove_removes_multiple_properties(): void
    {
        $this->db->pdo->exec(
            "UPDATE foo SET json_data = '{\"a\":1,\"b\":2,\"c\":3}'"
        );

        $this->db->update('foo')
            ->jsonRemove('json_data', '$.a')
            ->jsonRemove('json_data', '$.c')
            ->where('id', 1)
            ->execute();

        $this->assertSame(
            ['b' => 2],
            json_decode($this->fetchRow(1)['json_data'], true)
        );
    }

    public function test_json_remove_ignores_missing_property(): void
    {
        $this->db->pdo->exec("UPDATE foo SET json_data = '{\"a\":1}'");

        $this->db->update('foo')
            ->jsonRemove('json_data', '$.nonexistent')
            ->where('id', 1)
            ->execute();

        $this->assertSame(
            ['a' => 1],
            json_decode($this->fetchRow(1)['json_data'], true)
        );
    }

    public function test_json_remove_preserves_null_column(): void
    {
        $this->db->update('foo')
            ->jsonRemove('json_data', '$.foo')
            ->where('id', 1)
            ->execute();

        $this->assertNull($this->fetchRow(1)['json_data']);
    }

    public function test_json_remove_deduplicates_pending_removal(): void
    {
        $this->db->pdo->exec("UPDATE foo SET json_data = '[1,2,3]'");

        $this->db->update('foo')
            ->jsonRemove('json_data', '$[0]')
            ->jsonRemove('json_data', '$[0]')
            ->where('id', 1)
            ->execute();

        $this->assertSame(
            [2, 3],
            json_decode($this->fetchRow(1)['json_data'], true)
        );
    }

    public function test_json_set_cancels_pending_remove(): void
    {
        $this->db->pdo->exec("UPDATE foo SET json_data = '{\"foo\":1}'");

        $this->db->update('foo')
            ->jsonRemove('json_data', '$.foo')
            ->jsonSet('json_data', '$.foo', 2)
            ->where('id', 1)
            ->execute();

        $this->assertSame(
            ['foo' => 2],
            json_decode($this->fetchRow(1)['json_data'], true)
        );
    }

    public function test_json_remove_cancels_pending_set(): void
    {
        $this->db->pdo->exec("UPDATE foo SET json_data = '{}'");

        $this->db->update('foo')
            ->jsonSet('json_data', '$.foo', 1)
            ->jsonRemove('json_data', '$.foo')
            ->where('id', 1)
            ->execute();

        $this->assertSame(
            '{}',
            $this->fetchRow(1)['json_data']
        );
    }

    public function test_json_updates_can_target_multiple_columns(): void
    {
        $this->db->pdo->exec(
            "UPDATE foo SET json_data = '{\"a\":1}', json_other = '{\"b\":2}'"
        );

        $this->db->update('foo')
            ->jsonSet('json_data', '$.c', 3)
            ->jsonSet('json_other', '$.d', 4)
            ->jsonRemove('json_data', '$.a')
            ->jsonRemove('json_other', '$.b')
            ->where('id', 1)
            ->execute();

        $row = $this->fetchRow(1);

        $this->assertSame(
            ['c' => 3],
            json_decode($row['json_data'], true)
        );
        $this->assertSame(
            ['d' => 4],
            json_decode($row['json_other'], true)
        );
    }

    public function test_json_updates_can_be_combined_with_regular_updates(): void
    {
        $this->db->pdo->exec(
            "UPDATE foo SET json_data = '{\"a\":1,\"b\":2}'"
        );

        $count = $this->db->update('foo')
            ->value('name', 'changed')
            ->jsonSet('json_data', '$.c', 3)
            ->where('id', 1)
            ->jsonRemove('json_data', '$.a')
            ->value('value', 99)
            ->jsonSet('json_other', '$.d', 4)
            ->where('flag', 0)
            ->execute();

        $this->assertSame(1, $count);

        $row = $this->fetchRow(1);

        $this->assertSame('changed', $row['name']);
        $this->assertEquals(99, $row['value']);
        $this->assertSame(
            ['b' => 2, 'c' => 3],
            json_decode($row['json_data'], true)
        );
        $this->assertSame(
            ['d' => 4],
            json_decode($row['json_other'], true)
        );

        $this->assertSame('beta', $this->fetchRow(2)['name']);
    }

    public function test_json_set_throws_without_where_clause(): void
    {
        $this->expectException(RuntimeException::class);

        $this->db->update('foo')
            ->jsonSet('json_data', '$.foo', 1)
            ->execute();
    }

    public function test_json_remove_throws_without_where_clause(): void
    {
        $this->expectException(RuntimeException::class);

        $this->db->update('foo')
            ->jsonRemove('json_data', '$.foo')
            ->execute();
    }

    public function test_json_update_allows_no_where_with_flag(): void
    {
        $count = $this->db->update('foo')
            ->jsonSet('json_data', '$.foo', 1)
            ->execute(true);

        $this->assertSame(3, $count);

        foreach ($this->fetchAll() as $row) {
            $this->assertSame(
                ['foo' => 1],
                json_decode($row['json_data'], true)
            );
        }
    }
}
