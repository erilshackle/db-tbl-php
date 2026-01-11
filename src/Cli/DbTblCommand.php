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
    private ?string $output = null;
    private ?string $mode = "file";
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
                '--psr4' => $this->mode = "psr4",
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
        $mode = $this->mode ?? $this->config->getOutputMode();

        if ($mode === 'psr4') {
            $outputPath = $this->config->getOutputPath();

            if ($mode === 'psr4' && !$this->config->get('output.namespace')) {
                throw new RuntimeException(
                    "PSR-4 mode requires 'output.namespace' to be defined in dbtbl.yaml"
                );
            }

            $this->confirmPsr4Overwrite($outputPath);

            $generator = new Psr4TblGenerator(
                $this->schema,
                $this->config,
                $this->check
            );
        } else {
            $generator = new FileTblGenerator(
                $this->schema,
                $this->config,
                $this->check
            );
        }

        $generator->run();
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
        $isUpdate = false;
        $default = 'N';

        CliPrinter::warnIcon("The output directory already contains PHP files:");
        foreach ($files as $file) {
            $filename = basename($file);
            if ($filename == 'Tbl.php') {
                $isUpdate = true;
            };
            CliPrinter::line("  - " . $filename, 'yellow');
        }

        CliPrinter::line();
        CliPrinter::warn("Generating in this directory may overwrite existing classes.");
        if ($isUpdate) {
            CliPrinter::out("Update classes? [Y/n]: ", 'bold');
            $default = 'y';
        } else {
            CliPrinter::out("Continue? [y/N]: ", 'bold');
        }

        $answer = trim(fgets(STDIN));

        if (!in_array(strtolower($answer ?: $default), ['y', 'yes'], true)) {
            CliPrinter::info("Operation aborted by user.");
            exit(0);
        }
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
