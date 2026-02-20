<?php

/**
 * smolQuery
 * https://github.com/joby-lol/smol-query
 * (c) 2026 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Query;

use PHPUnit\Framework\TestCase;
use SQLite3;

class MigratorTest extends TestCase
{

    protected string $db_path;

    protected function setUp(): void
    {
        $this->db_path = sys_get_temp_dir() . '/smol_query_test_' . uniqid() . '.sqlite';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->db_path)) {
            unlink($this->db_path);
        }
    }

    protected function migrator(): Migrator
    {
        return new Migrator($this->db_path);
    }

    public function test_migrate_creates_log_table(): void
    {
        $this->migrator()->migrate();
        $db = new SQLite3($this->db_path);
        $result = $db->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='_migrations'");
        $this->assertEquals('_migrations', $result);
        $db->close();
    }

    public function test_migrate_runs_a_migration(): void
    {
        $this->migrator()
            ->addMigration('001_create_foo', 'CREATE TABLE foo (id INTEGER PRIMARY KEY, name TEXT)')
            ->migrate();
        $db = new SQLite3($this->db_path);
        $result = $db->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='foo'");
        $this->assertEquals('foo', $result);
        $db->close();
    }

    public function test_migrate_records_migration_in_log(): void
    {
        $before = time();
        $this->migrator()
            ->addMigration('001_create_foo', 'CREATE TABLE foo (id INTEGER PRIMARY KEY, name TEXT)')
            ->migrate();
        $after = time();
        $db = new SQLite3($this->db_path);
        $row = $db->querySingle("SELECT run_at FROM _migrations WHERE migration_name = '001_create_foo'");
        $this->assertGreaterThanOrEqual($before, $row);
        $this->assertLessThanOrEqual($after, $row);
        $db->close();
    }

    public function test_migrate_runs_multiple_migrations_in_order(): void
    {
        $this->migrator()
            ->addMigration('002_create_bar', 'CREATE TABLE bar (id INTEGER PRIMARY KEY)')
            ->addMigration('001_create_foo', 'CREATE TABLE foo (id INTEGER PRIMARY KEY, bar_id INTEGER REFERENCES bar(id))')
            ->migrate();
        $db = new SQLite3($this->db_path);
        $this->assertEquals('foo', $db->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='foo'"));
        $this->assertEquals('bar', $db->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='bar'"));
        $db->close();
    }

    public function test_migrate_skips_already_run_migrations(): void
    {
        $migrator = $this->migrator()
            ->addMigration('001_create_foo', 'CREATE TABLE foo (id INTEGER PRIMARY KEY)');
        $migrator->migrate();
        // running again should not throw or duplicate
        $migrator->migrate();
        $db = new SQLite3($this->db_path);
        $count = $db->querySingle("SELECT COUNT(*) FROM _migrations WHERE migration_name = '001_create_foo'");
        $this->assertEquals(1, $count);
        $db->close();
    }

    public function test_migrate_runs_only_new_migrations_on_second_run(): void
    {
        $migrator = $this->migrator()
            ->addMigration('001_create_foo', 'CREATE TABLE foo (id INTEGER PRIMARY KEY)');
        $migrator->migrate();

        $migrator->addMigration('002_create_bar', 'CREATE TABLE bar (id INTEGER PRIMARY KEY)');
        $migrator->migrate();

        $db = new SQLite3($this->db_path);
        $this->assertEquals('bar', $db->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='bar'"));
        $count = $db->querySingle("SELECT COUNT(*) FROM _migrations");
        $this->assertEquals(2, $count);
        $db->close();
    }

    public function test_migrate_detects_out_of_order_migrations(): void
    {
        $migrator = $this->migrator()
            ->addMigration('002_create_bar', 'CREATE TABLE bar (id INTEGER PRIMARY KEY)');
        $migrator->migrate();

        $migrator->addMigration('001_create_foo', 'CREATE TABLE foo (id INTEGER PRIMARY KEY)');

        $this->expectException(MigrationException::class);
        $migrator->migrate();
    }

    public function test_migrate_rolls_back_on_failure(): void
    {
        $migrator = $this->migrator()
            ->addMigration('001_create_foo', 'CREATE TABLE foo (id INTEGER PRIMARY KEY)')
            ->addMigration('002_bad_sql', 'THIS IS NOT VALID SQL');

        $exception_thrown = false;
        try {
            $migrator->migrate();
        }
        catch (MigrationException $e) {
            $exception_thrown = true;
        }
        $this->assertTrue($exception_thrown);

        $db = new SQLite3($this->db_path);
        $result = $db->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='foo'");
        $this->assertNull($result);
        $count = $db->querySingle("SELECT COUNT(*) FROM _migrations");
        $this->assertEquals(0, $count);
        $db->close();
    }

    public function test_migrate_supports_multi_statement_sql(): void
    {
        $this->migrator()
            ->addMigration('001_create_tables', "
                CREATE TABLE foo (id INTEGER PRIMARY KEY, name TEXT);
                CREATE TABLE bar (id INTEGER PRIMARY KEY, foo_id INTEGER REFERENCES foo(id));
                CREATE INDEX idx_bar_foo_id ON bar(foo_id);
            ")
            ->migrate();

        $db = new SQLite3($this->db_path);
        $this->assertEquals('foo', $db->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='foo'"));
        $this->assertEquals('bar', $db->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='bar'"));
        $this->assertEquals('idx_bar_foo_id', $db->querySingle("SELECT name FROM sqlite_master WHERE type='index' AND name='idx_bar_foo_id'"));
        $db->close();
    }

    public function test_migrations_already_run_returns_empty_on_fresh_db(): void
    {
        $result = $this->migrator()->migrationsAlreadyRun();
        $this->assertEmpty($result);
    }

    public function test_migrations_already_run_returns_run_migrations(): void
    {
        $this->migrator()
            ->addMigration('001_create_foo', 'CREATE TABLE foo (id INTEGER PRIMARY KEY)')
            ->migrate();

        $result = $this->migrator()->migrationsAlreadyRun();
        $this->assertArrayHasKey('001_create_foo', $result);
        $this->assertIsInt($result['001_create_foo']);
    }

    public function test_add_migration_directory_loads_sql_files(): void
    {
        $dir = sys_get_temp_dir() . '/smol_query_migrations_' . uniqid();
        mkdir($dir);
        file_put_contents($dir . '/001_create_foo.sql', 'CREATE TABLE foo (id INTEGER PRIMARY KEY)');
        file_put_contents($dir . '/002_create_bar.sql', 'CREATE TABLE bar (id INTEGER PRIMARY KEY)');

        $this->migrator()
            ->addMigrationDirectory($dir)
            ->migrate();

        $db = new SQLite3($this->db_path);
        $this->assertEquals('foo', $db->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='foo'"));
        $this->assertEquals('bar', $db->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='bar'"));
        $db->close();

        unlink($dir . '/001_create_foo.sql');
        unlink($dir . '/002_create_bar.sql');
        rmdir($dir);
    }

    public function test_add_migration_directory_throws_on_invalid_path(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->migrator()->addMigrationDirectory('/nonexistent/path');
    }

    public function test_custom_log_table_name(): void
    {
        $migrator = new Migrator($this->db_path, '_custom_log');
        $migrator->addMigration('001_create_foo', 'CREATE TABLE foo (id INTEGER PRIMARY KEY)')->migrate();

        $db = new SQLite3($this->db_path);
        $result = $db->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='_custom_log'");
        $this->assertEquals('_custom_log', $result);
        $db->close();
    }

    public function test_natural_sort_ordering(): void
    {
        // Without natural sort, '10_' would sort before '2_' lexicographically
        $this->migrator()
            ->addMigration('10_create_bar', 'CREATE TABLE bar (id INTEGER PRIMARY KEY, foo_id INTEGER REFERENCES foo(id))')
            ->addMigration('2_create_foo', 'CREATE TABLE foo (id INTEGER PRIMARY KEY)')
            ->migrate();

        $db = new SQLite3($this->db_path);
        $this->assertEquals('foo', $db->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='foo'"));
        $this->assertEquals('bar', $db->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='bar'"));
        $db->close();
    }

}
