<?php

/**
 * smolQuery
 * https://github.com/joby-lol/smol-query
 * (c) 2026 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Query;

use InvalidArgumentException;
use RuntimeException;
use SQLite3;
use Throwable;

/**
 * Bare-minimum SQLite database migration tool. Supports programmatic and directory-based migrations, which are sorted alphabetically (including natural number sorting) by name before executing. Also checks that nothing has executed out of order.
 */
class Migrator
{

    /**
     * List of migrations to potentially apply.
     * @var array<string,string>
     */
    protected array $migrations = [];

    protected SQLite3|null $db = null;

    public function __construct(protected string $db_path, protected string $log_table = '_migrations') {}

    /**
     * Get a database instance for this migrator. Lazy-instantiated.
     */
    protected function db(): SQLite3
    {
        return $this->db
            ??= $this->instantiateDb();
    }

    protected function instantiateDb(): SQLite3
    {
        $db = new SQLite3($this->db_path, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
        return $db;
    }

    /**
     * Attempt to run all current un-run migrations on the database. Execution is wrapped in a transaction and will roll back if anything fails.
     */
    public function migrate(): static
    {
        $name = '[pre_run]';
        try {
            uksort($this->migrations, 'strnatcmp');
            $existing = $this->migrationsAlreadyRun();
            $this->db()->exec('BEGIN TRANSACTION');
            $migrations_run = [];
            foreach ($this->migrations as $name => $sql) {
                if (array_key_exists($name, $existing)) {
                    if ($migrations_run) {
                        $this->db()->exec('ROLLBACK');
                        throw new RuntimeException(sprintf(
                            'Migrations failed because a migration was run out of order: %s was previously run but %s was not)',
                            end($migrations_run),
                            $name,
                        ));
                    }
                }
                else {
                    $migrations_run[] = $name;
                    $result = $this->db()->exec($sql);
                    if ($result === false)
                        throw new MigrationException("Error running $name: " . $this->db()->lastErrorMsg());
                    $this->db()->exec(sprintf(
                        'INSERT INTO %s (migration_name, run_at) VALUES ("%s", %s)',
                        $this->log_table,
                        addcslashes($name, '"'),
                        time(),
                    ));
                }
            }
            $name = '[commit]';
            $this->db()->exec('COMMIT');
            return $this;
        }
        catch (Throwable $th) {
            throw new MigrationException('Migration error while running ' . $name . ': ' . $th->getMessage(), $th->getCode(), $th);
        }
    }

    /**
     * Try to retrieve a list of all already-run migrations for this database.
     * 
     * @return array<string,int>
     */
    public function migrationsAlreadyRun(): array
    {
        $db = $this->db();
        $db->exec("
            CREATE TABLE IF NOT EXISTS {$this->log_table} (
                migration_name TEXT PRIMARY KEY,
                run_at INTEGER NOT NULL
            )
        ");
        $query = $db->query("SELECT * FROM {$this->log_table}");
        if ($query === false)
            throw new RuntimeException("Failed to get migrations from {$this->log_table}");
        $migrations = [];
        while ($row = $query->fetchArray(SQLITE3_ASSOC)) {
            /** @var array{migration_name:string,run_at:int} $row */
            $migrations[$row['migration_name']] = $row['run_at'];
        }
        return $migrations;
    }

    /**
     * Manually add a single migration.
     */
    public function addMigration(string $migration_name, string $migration_sql): static
    {
        $this->migrations[$migration_name] = $migration_sql;
        return $this;
    }

    /**
     * Add all migrations in a given directory. All .sql files will be loaded as migrations and placed in natural alphabetical order.
     */
    public function addMigrationDirectory(string $migration_directory): static
    {
        $path = realpath($migration_directory);
        if (!$path || !is_dir($path))
            throw new InvalidArgumentException("Migration directory not found: $migration_directory");
        $files = glob($path . "/*.sql");
        if ($files === false)
            throw new RuntimeException("Failed to glob sql files from migration directory: $migration_directory");
        foreach ($files as $file) {
            $this->addMigrationFile($file);
        }
        return $this;
    }

    /**
     * Add a single file's SQL contents as a migration. The filename (sans directory and extension) will be used as the name for the migration.
     */
    protected function addMigrationFile(string $migration_file): static
    {
        $path = realpath($migration_file);
        if (!$path || !is_file($path))
            throw new InvalidArgumentException("Migration file not found: $migration_file");
        $content = file_get_contents($path);
        if ($content === false)
            throw new RuntimeException("Failed to read migration file: $migration_file");
        return $this->addMigration(
            pathinfo($path, PATHINFO_FILENAME),
            $content,
        );
    }

}
