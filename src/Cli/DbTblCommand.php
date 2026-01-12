<?php

namespace Eril\DbTbl\Cli;

use Eril\DbTbl\Schema\PgSqlSchemaReader;
use Eril\DbTbl\Schema\SqliteSchemaReader;
use Eril\DbTbl\Resolvers\ConnectionResolver;
use Eril\DbTbl\Schema\MySqlSchemaReader;
use Eril\DbTbl\Schema\SchemaReaderInterface;
use Eril\DbTbl\Config;
use Eril\DbTbl\Generators\FileTblGenerator;
use Eril\DbTbl\Generators\Psr4TblGenerator;
use PDO;
use RuntimeException;

final class DbTblCommand
{
    private Config $config;
    private PDO $pdo;
    private SchemaReaderInterface $schema;

    private ?string $mode = null;
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

    // -------------------------------------------------
    // CLI parsing
    // -------------------------------------------------
    private function parseArgs(array $argv): void
    {
        foreach ($argv as $i => $arg) {
            if ($i === 0) {
                continue;
            }

            match ($arg) {
                '--check' => $this->check = true,
                '--psr4'  => $this->mode  = 'psr4',
                '--file'  => $this->mode  = 'file',
                '--help', '-h' => $this->showHelp(),
                default => null,
            };
        }
    }

    // -------------------------------------------------
    // Bootstrap & config
    // -------------------------------------------------
    private function bootstrap(): void
    {
        $this->config = new Config();

        if ($this->config->isNew()) {
            CliPrinter::success("Config created: {$this->config->getConfigFile()}");
            CliPrinter::warn("Edit it and run again.");
            exit(0);
        }
    }

    // -------------------------------------------------
    // Database connection
    // -------------------------------------------------
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

    // -------------------------------------------------
    // Execution
    // -------------------------------------------------
    private function execute(): void
    {
        $generator = $this->createGenerator();
        $generator->run();
    }

    private function createGenerator()
    {
        $this->mode = $this->mode ?? $this->config->getOutputMode();
        return match ($this->mode) {
            'psr4' => $this->createPsr4Generator(),
            default => new FileTblGenerator(
                $this->schema,
                $this->config,
                $this->check
            ),
        };
    }

    // -------------------------------------------------
    // PSR-4 handling
    // -------------------------------------------------
    private function createPsr4Generator(): Psr4TblGenerator
    {
        $this->validatePsr4Config();
        $this->confirmPsr4Overwrite($this->config->getOutputPath());

        return new Psr4TblGenerator(
            $this->schema,
            $this->config,
            $this->check
        );
    }

    private function validatePsr4Config(): void
    {
        if (!$this->config->get('output.namespace')) {
            throw new RuntimeException(
                "PSR-4 mode requires 'output.namespace' to be defined in dbtbl.yaml"
            );
        }
    }

    private function confirmPsr4Overwrite(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $files = glob(rtrim($path, '/') . '/*.php');
        if (empty($files)) {
            return;
        }

        CliPrinter::warnIcon("The output directory already contains PHP files:");
        foreach ($files as $file) {
            CliPrinter::line('  - ' . basename($file), 'yellow');
        }

        CliPrinter::line();
        CliPrinter::warn("Generating in this directory may overwrite existing classes.");
        CliPrinter::out("Continue? [y/N]: ", 'bold');

        $answer = trim(fgets(STDIN));

        if (!in_array(strtolower($answer), ['y', 'yes'], true)) {
            CliPrinter::info("Operation aborted by user.");
            exit(0);
        }
    }

    // -------------------------------------------------
    // Help & errors
    // -------------------------------------------------
    private function showHelp(): void
    {
        echo <<<HELP
db-tbl — Generate schema-based table classes

Usage:
  db-tbl

Options:
  --psr4     Generate PSR-4 classes (one class per table)
  --check    Compare schema hash
  --help     Show this help

Configuration:
  All output settings are defined in dbtbl.yaml

HELP;
        exit(0);
    }

    private function handleError(\Throwable $e): void
    {
        $error = $e->getMessage();
        CliPrinter::errorIcon("Error: $error\n");

        if (str_contains($error, 'DB_NAME')) {
            CliPrinter::warn("Tip: Set 'database.name' in {$this->config->getConfigFile()}");
        } elseif (str_contains($error, 'connection failed')) {
            CliPrinter::warn("Tip: Check your database credentials.");
        }

        exit(1);
    }
}
