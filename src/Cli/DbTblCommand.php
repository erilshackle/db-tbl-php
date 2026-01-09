<?php

namespace Eril\DbTbl\Cli;

use Eril\DbTbl\Schema\PgSqlSchemaReader;
use Eril\DbTbl\Schema\SqliteSchemaReader;
use Eril\DbTbl\Resolvers\ConnectionResolver;
use Eril\DbTbl\Schema\MySqlSchemaReader;
use Eril\DbTbl\Schema\SchemaReaderInterface;
use Eril\DbTbl\Config;
use Eril\DbTbl\Generators\TblClassesGenerator;
use PDO;
use RuntimeException;

final class DbTblCommand
{
    private Config $config;
    private PDO $pdo;
    private SchemaReaderInterface $schema;
    private ?string $output = null;
    private bool $check = false;

    public function run(array $argv): void
    {
        try {
            $this->parseArgs($argv);
            $this->bootstrap();
            $this->connect();
            $this->execute();
        } catch (\Throwable $e) {
            $this->handleError($e);
        }
    }

    private function parseArgs(array $argv): void
    {
        foreach ($argv as $i => $arg) {
            if ($i === 0) continue;

            match ($arg) {
                '--check' => $this->check = true,
                '--help', '-h' => $this->showHelp(),
                default => $arg[0] !== '-' && $this->output === null
                    ? $this->output = $arg
                    : null,
            };
        }
    }

    private function bootstrap(): void
    {
        $this->config = new Config();

        if ($this->output) {
            $this->config->set('output.path', $this->output);
            CliPrinter::info("Output path set to {$this->output}");
        }

        if ($this->config->isNew()) {
            CliPrinter::success("Config created: {$this->config->getConfigFile()}");
            CliPrinter::warn("Edit it and run again.");
            exit(0);
        }
    }

    private function connect(): void
    {
        $this->pdo = ConnectionResolver::fromConfig($this->config);

        $this->schema = match ($this->config->getDriver()) {
            'mysql'  => new MySqlSchemaReader($this->pdo, $this->config->getDatabaseName()),
            'pgsql'  => new PgSqlSchemaReader($this->pdo, $this->config->getDatabaseName()),
            'sqlite' => new SqliteSchemaReader($this->pdo, $this->config->getDatabaseName()),
            default  => throw new RuntimeException('Unsupported database driver'),
        };

        CliPrinter::success("Database connected ({$this->config->getDriver()})");
    }

    private function execute(): void
    {
        $generator = new TblClassesGenerator(
            $this->schema,
            $this->config,
            $this->check
        );

        $generator->run();
    }

    private function showHelp(): void
    {
        echo <<<HELP
db-tbl — Generate schema-based table classes

Usage:
  db-tbl [output]

Options:
  --check     Compare schema hash
  --help      Show this help

HELP;
        exit(0);
    }

    private function handleError(\Throwable $e): void
    {
        $error = $e->getMessage();

        CliPrinter::errorIcon("Error: $error \n");

        if (str_contains($error, 'DB_NAME') || str_contains($error, 'database name')) {
            CliPrinter::warn("💡 Tip: Set 'database.name' in " . $this->config->getConfigFile());
            CliPrinter::warn(" Try use environment variable: export DB_NAME=your_database");
        } else 
        if (str_contains($error, 'connection failed')) {
            CliPrinter::warn("💡 Tip: Check your database credentials in " . $this->config->getConfigFile());
            CliPrinter::warn("   Make sure your database server is running.\n");
        }
        exit(1);
    }
}
