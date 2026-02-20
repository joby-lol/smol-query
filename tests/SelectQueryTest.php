<?php

/**
 * smolQuery
 * https://github.com/joby-lol/smol-query
 * (c) 2026 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Query;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class SelectQueryTest extends TestCase
{

    protected DB $db;

    protected function setUp(): void
    {
        $this->db = new DB(':memory:');
        $this->db->pdo->exec('CREATE TABLE foo (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT,
            value INTEGER,
            nullable TEXT NULL
        )');
        $this->db->pdo->exec("INSERT INTO foo (name, value) VALUES ('alpha', 1)");
        $this->db->pdo->exec("INSERT INTO foo (name, value) VALUES ('beta', 2)");
        $this->db->pdo->exec("INSERT INTO foo (name, value) VALUES ('gamma', 3)");
        $this->db->pdo->exec("INSERT INTO foo (name, value) VALUES ('Alpha', 4)");
        // additional table for JOIN tests
        $this->db->pdo->exec('CREATE TABLE bar (
         id INTEGER PRIMARY KEY AUTOINCREMENT,
         foo_id INTEGER REFERENCES foo(id),
         label TEXT
     )');
        $this->db->pdo->exec("INSERT INTO bar (foo_id, label) VALUES (1, 'bar_alpha')");
        $this->db->pdo->exec("INSERT INTO bar (foo_id, label) VALUES (2, 'bar_beta')");
        // intentionally no row for foo id=3 or id=4, to test LEFT JOIN behavior
    }

    // --- fetch() ---

    public function test_fetch_returns_associative_array_by_default(): void
    {
        $row = $this->db->select('foo')->where('id', 1)->fetch();
        $this->assertIsArray($row);
        $this->assertArrayHasKey('name', $row);
        $this->assertEquals('alpha', $row['name']);
    }

    public function test_fetch_returns_false_when_no_rows(): void
    {
        $result = $this->db->select('foo')->where('name', 'nonexistent')->fetch();
        $this->assertNull($result);
    }

    public function test_fetch_iterates_through_rows(): void
    {
        $query = $this->db->select('foo')->where('value < ?', 3);
        $first = $query->fetch();
        $second = $query->fetch();
        $third = $query->fetch();
        $this->assertIsArray($first);
        $this->assertIsArray($second);
        $this->assertNull($third);
    }

    public function test_fetch_resets_on_new_fetch_call_after_exhaustion(): void
    {
        $query = $this->db->select('foo')->where('id', 1);
        $first = $query->fetch();
        $exhausted = $query->fetch();
        $this->assertNull($exhausted);
        // fetchAll resets the statement
        $results = iterator_to_array($query->fetchAll());
        $this->assertCount(1, $results);
    }

    // --- fetchAll() ---

    public function test_fetch_all_returns_all_rows(): void
    {
        $results = iterator_to_array($this->db->select('foo')->fetchAll());
        $this->assertCount(4, $results);
    }

    public function test_fetch_all_returns_empty_on_no_match(): void
    {
        $results = iterator_to_array(
            $this->db->select('foo')->where('name', 'nonexistent')->fetchAll(),
        );
        $this->assertEmpty($results);
    }

    public function test_fetch_all_returns_associative_arrays_by_default(): void
    {
        $results = iterator_to_array($this->db->select('foo')->fetchAll());
        foreach ($results as $row) {
            $this->assertIsArray($row);
            $this->assertArrayHasKey('name', $row);
        }
    }

    // --- fetchColumn() ---

    public function test_fetch_column_returns_single_column_values(): void
    {
        $names = iterator_to_array($this->db->select('foo')->fetchColumn('name'));
        $this->assertContains('alpha', $names);
        $this->assertContains('beta', $names);
        $this->assertContains('gamma', $names);
    }

    public function test_fetch_column_does_not_return_other_columns(): void
    {
        $results = iterator_to_array($this->db->select('foo')->fetchColumn('name'));
        foreach ($results as $value) {
            $this->assertIsNotArray($value);
        }
    }

    public function test_fetch_column_returns_empty_on_no_match(): void
    {
        $results = iterator_to_array(
            $this->db->select('foo')->where('name', 'nonexistent')->fetchColumn('name'),
        );
        $this->assertEmpty($results);
    }

    // --- count() ---

    public function test_count_returns_total_row_count(): void
    {
        $this->assertEquals(4, $this->db->select('foo')->count());
    }

    public function test_count_respects_where_clause(): void
    {
        $this->assertEquals(2, $this->db->select('foo')->where('value < ?', 3)->count());
    }

    public function test_count_returns_zero_on_no_match(): void
    {
        $this->assertEquals(0, $this->db->select('foo')->where('name', 'nonexistent')->count());
    }

    // --- hydration ---

    public function test_hydrate_callable_transforms_rows(): void
    {
        $results = iterator_to_array(
            $this->db->select('foo')
                ->hydrate(fn($row) => strtoupper($row['name']))
                ->fetchAll(),
        );
        $this->assertContains('ALPHA', $results);
        $this->assertContains('BETA', $results);
    }

    public function test_hydrate_callable_used_in_fetch(): void
    {
        $result = $this->db->select('foo')
            ->hydrate(fn($row) => $row['name'] . '_hydrated')
            ->where('id', 1)
            ->fetch();
        $this->assertEquals('alpha_hydrated', $result);
    }

    public function test_hydrate_null_resets_to_array(): void
    {
        $query = $this->db->select('foo')
            ->hydrate(fn($row) => 'transformed')
            ->hydrate(null)
            ->where('id', 1);
        $result = $query->fetch();
        $this->assertIsArray($result);
    }

    public function test_hydrate_class_returns_objects(): void
    {
        $results = iterator_to_array(
            $this->db->select('foo')
                ->hydrate(TestHydrationClass::class)
                ->fetchAll(),
        );
        foreach ($results as $row) {
            $this->assertInstanceOf(TestHydrationClass::class, $row);
        }
    }

    public function test_hydrate_class_populates_properties(): void
    {
        $result = $this->db->select('foo')
            ->hydrate(TestHydrationClass::class)
            ->where('id', 1)
            ->fetch();
        $this->assertInstanceOf(TestHydrationClass::class, $result);
        $this->assertEquals('alpha', $result->name);
    }

    // --- columns/projections ---

    public function test_default_select_returns_all_columns(): void
    {
        $row = $this->db->select('foo')->where('id', 1)->fetch();
        $this->assertArrayHasKey('id', $row);
        $this->assertArrayHasKey('name', $row);
        $this->assertArrayHasKey('value', $row);
        $this->assertArrayHasKey('nullable', $row);
    }

    public function test_column_restricts_output(): void
    {
        $row = $this->db->select('foo')->column('name')->where('id', 1)->fetch();
        $this->assertArrayHasKey('name', $row);
        $this->assertArrayNotHasKey('id', $row);
        $this->assertArrayNotHasKey('value', $row);
    }

    public function test_columns_with_multiple_columns(): void
    {
        $row = $this->db->select('foo')->column('name')->column('value')->where('id', 1)->fetch();
        $this->assertArrayHasKey('name', $row);
        $this->assertArrayHasKey('value', $row);
        $this->assertArrayNotHasKey('id', $row);
    }

    // --- ORDER ---

    public function test_order_sorts_ascending(): void
    {
        $results = iterator_to_array(
            $this->db->select('foo')->order('value ASC')->fetchAll(),
        );
        $this->assertEquals(1, $results[0]['value']);
        $this->assertEquals(4, $results[3]['value']);
    }

    public function test_order_sorts_descending(): void
    {
        $results = iterator_to_array(
            $this->db->select('foo')->order('value DESC')->fetchAll(),
        );
        $this->assertEquals(4, $results[0]['value']);
        $this->assertEquals(1, $results[3]['value']);
    }

    // --- LIMIT ---

    public function test_limit_restricts_row_count(): void
    {
        $results = iterator_to_array(
            $this->db->select('foo')->limit(2)->fetchAll(),
        );
        $this->assertCount(2, $results);
    }

    public function test_limit_of_zero_returns_no_rows(): void
    {
        $query = $this->db->select('foo')->limit(0);
        $this->assertCount(0, (array) $query->fetchAll());
        $this->assertEquals(0, $query->count());
    }

    // --- OFFSET ---

    public function test_offset_skips_rows(): void
    {
        $all = iterator_to_array(
            $this->db->select('foo')->order('id ASC')->fetchAll(),
        );
        $offset = iterator_to_array(
            $this->db->select('foo')->order('id ASC')->offset(2)->fetchAll(),
        );
        $this->assertCount(2, $offset);
        $this->assertEquals($all[2]['id'], $offset[0]['id']);
    }

    public function test_limit_and_offset_together(): void
    {
        $results = iterator_to_array(
            $this->db->select('foo')->order('id ASC')->limit(2)->offset(1)->fetchAll(),
        );
        $this->assertCount(2, $results);
        $this->assertEquals(2, $results[0]['id']);
        $this->assertEquals(3, $results[1]['id']);
    }

    // --- WHERE variants ---

    public function test_where_shorthand(): void
    {
        $results = iterator_to_array(
            $this->db->select('foo')->where('name', 'alpha')->fetchAll(),
        );
        $this->assertCount(1, $results);
        $this->assertEquals('alpha', $results[0]['name']);
    }

    public function test_where_raw_statement(): void
    {
        $results = iterator_to_array(
            $this->db->select('foo')->where('value > ?', 2)->fetchAll(),
        );
        $this->assertCount(2, $results);
    }

    public function test_multiple_where_clauses_are_anded(): void
    {
        $results = iterator_to_array(
            $this->db->select('foo')
                ->where('value > ?', 1)
                ->where('value < ?', 4)
                ->fetchAll(),
        );
        $this->assertCount(2, $results);
    }

    public function test_where_null(): void
    {
        $this->db->pdo->exec("INSERT INTO foo (name, value, nullable) VALUES ('delta', 5, NULL)");
        $results = iterator_to_array(
            $this->db->select('foo')->whereNull('nullable')->fetchAll(),
        );
        // all existing rows + delta have null nullable
        $this->assertCount(5, $results);
    }

    public function test_where_not_null(): void
    {
        $this->db->pdo->exec("INSERT INTO foo (name, value, nullable) VALUES ('delta', 5, 'set')");
        $results = iterator_to_array(
            $this->db->select('foo')->whereNotNull('nullable')->fetchAll(),
        );
        $this->assertCount(1, $results);
        $this->assertEquals('delta', $results[0]['name']);
    }

    public function test_where_like_case_sensitive(): void
    {
        $results = iterator_to_array(
            $this->db->select('foo')->whereLike('name', 'alpha')->fetchAll(),
        );
        $this->assertCount(1, $results);
        $this->assertEquals('alpha', $results[0]['name']);
    }

    public function test_where_like_case_insensitive(): void
    {
        $results = iterator_to_array(
            $this->db->select('foo')->whereLike('name', 'alpha', true)->fetchAll(),
        );
        $this->assertCount(2, $results);
    }

    public function test_where_like_with_wildcard(): void
    {
        $results = iterator_to_array(
            $this->db->select('foo')->whereLike('name', '%a%')->fetchAll(),
        );
        // 'alpha', 'gamma', 'Alpha' contain 'a' (case sensitive, so not 'Alpha' for 'a')
        foreach ($results as $row) {
            $this->assertStringContainsString('a', $row['name']);
        }
    }

    public function test_where_with_callable_parameter(): void
    {
        $results = iterator_to_array(
            $this->db->select('foo')->where('name', fn() => 'alpha')->fetchAll(),
        );
        $this->assertCount(1, $results);
        $this->assertEquals('alpha', $results[0]['name']);
    }

    public function test_where_with_backed_enum_parameter(): void
    {
        $results = iterator_to_array(
            $this->db->select('foo')->where('name', TestStringEnum::Bar)->fetchAll(),
        );
        $this->assertEmpty($results);
    }

    public function test_where_with_stringable_parameter(): void
    {
        $results = iterator_to_array(
            $this->db->select('foo')
                ->where('name', new TestStringable('alpha'))
                ->fetchAll(),
        );
        $this->assertCount(1, $results);
        $this->assertEquals('alpha', $results[0]['name']);
    }

    // --- resetColumns() ---

    public function test_reset_columns_restores_wildcard(): void
    {
        $query = $this->db->select('foo')
            ->column('name')
            ->resetColumns()
            ->where('id', 1);
        $row = $query->fetch();
        $this->assertArrayHasKey('id', $row);
        $this->assertArrayHasKey('value', $row);
    }

    // --- resetOrder() ---

    public function test_reset_order_removes_ordering(): void
    {
        // just verify it doesn't throw and returns results
        $results = iterator_to_array(
            $this->db->select('foo')->order('value DESC')->resetOrder()->fetchAll(),
        );
        $this->assertCount(4, $results);
    }

    // --- limit/offset exceptions ---

    public function test_limit_throws_on_less_than_negative_one(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->db->select('foo')->limit(-2);
    }

    public function test_offset_throws_on_negative(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->db->select('foo')->offset(-1);
    }

    // --- JOIN tests ---

    public function test_join_returns_rows_from_both_tables(): void
    {
        $results = iterator_to_array(
            $this->db->select('foo')
                ->join('bar', 'bar.foo_id = foo.id')
                ->fetchAll(),
        );
        $this->assertCount(2, $results);
    }

    public function test_join_auto_qualifies_columns_from_all_tables(): void
    {
        $row = $this->db->select('foo')
            ->join('bar', 'bar.foo_id = foo.id')
            ->where('foo.id', 1)
            ->fetch();
        $this->assertArrayHasKey('id', $row);
        $this->assertArrayHasKey('label', $row);
    }

    public function test_join_excludes_rows_with_no_match(): void
    {
        // foo ids 3 and 4 have no matching bar rows
        $results = iterator_to_array(
            $this->db->select('foo')
                ->join('bar', 'bar.foo_id = foo.id')
                ->fetchAll(),
        );
        $ids = array_column($results, 'foo.id');
        $this->assertNotContains(3, $ids);
        $this->assertNotContains(4, $ids);
    }

    public function test_left_join_includes_rows_with_no_match(): void
    {
        $results = iterator_to_array(
            $this->db->select('foo')
                ->leftJoin('bar', 'bar.foo_id = foo.id')
                ->fetchAll(),
        );
        $this->assertCount(4, $results);
    }

    public function test_left_join_nulls_missing_columns(): void
    {
        $results = iterator_to_array(
            $this->db->select('foo')
                ->leftJoin('bar', 'bar.foo_id = foo.id')
                ->where('foo.id', 3)
                ->fetchAll(),
        );
        $this->assertCount(1, $results);
        $this->assertNull($results[0]['bar.label']);
    }

    public function test_join_with_explicit_columns_overrides_auto_qualify(): void
    {
        $row = $this->db->select('foo')
            ->join('bar', 'bar.foo_id = foo.id')
            ->column('foo.name')
            ->where('foo.id', 1)
            ->fetch();
        $this->assertArrayHasKey('name', $row);
        $this->assertArrayNotHasKey('bar.label', $row);
    }

    public function test_join_where_clause_can_reference_joined_table(): void
    {
        $results = iterator_to_array(
            $this->db->select('foo')
                ->join('bar', 'bar.foo_id = foo.id')
                ->where('bar.label', 'bar_alpha')
                ->fetchAll(),
        );
        $this->assertCount(1, $results);
        $this->assertEquals('alpha', $results[0]['name']);
    }

    public function test_multiple_joins(): void
    {
        // This test requires a third table — skip if your setUp doesn't have one,
        // or add: CREATE TABLE baz (bar_id INTEGER, note TEXT)
        // and INSERT INTO baz VALUES (1, 'baz_note')
        $this->db->pdo->exec('CREATE TABLE baz (bar_id INTEGER, note TEXT)');
        $this->db->pdo->exec("INSERT INTO baz (bar_id, note) VALUES (1, 'baz_note')");

        $results = iterator_to_array(
            $this->db->select('foo')
                ->join('bar', 'bar.foo_id = foo.id')
                ->join('baz', 'baz.bar_id = bar.id')
                ->fetchAll(),
        );
        $this->assertCount(1, $results);
        $this->assertArrayHasKey('note', $results[0]);
    }

}
