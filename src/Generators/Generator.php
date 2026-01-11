<?php

namespace Eril\DbTbl\Generators;

use Eril\DbTbl\Cli\CliPrinter;
use Eril\DbTbl\Config;
use Eril\DbTbl\Introspection\GeneratedClassMetadata;
use Eril\DbTbl\Introspection\SchemaHasher;
use Eril\DbTbl\Schema\SchemaReaderInterface;
use RuntimeException;

abstract class Generator
{
    public function __construct(
        protected SchemaReaderInterface $schema,
        protected Config $config,
        protected bool $checkMode = false
    ) {}

    final public function run(): void
    {
        $tables = $this->schema->getTables();

        if (empty($tables)) {
            throw new RuntimeException('No tables found in database schema');
        }

        $foreignKeys = $this->schema->getForeignKeys();

        $schemaData = $this->buildSchemaHashData($tables, $foreignKeys);
        $currentHash = SchemaHasher::hash($schemaData);

        if ($this->checkMode) {
            $this->checkSchema($currentHash);
            return;
        }

        $content = $this->generateContent(
            $tables,
            $foreignKeys,
            $currentHash
        );

        $this->ensureDirectory();
        $this->write($content, count($tables), count($foreignKeys));
    }

    protected function ensureDirectory(): void
    {
        $dir = $this->config->getOutputPath();

        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            throw new RuntimeException("Cannot create output directory: {$dir}");
        }
    }

    /**
     * Deve retornar o conteúdo PHP completo a ser escrito no arquivo final
     */
    abstract protected function generateContent(
        array $tables,
        array $foreignKeys = [],
        ?string $schemaHash = null
    ): string;

    /**
     * @internal
     * @param array $tables
     * @param array $foreignKeys
     * @return array|array{database: string, foreignKeys: array, tables: array}
     */
    public function buildSchemaHashData(array $tables, array $foreignKeys): array
    {
        $schemaData = [
            'database'    => $this->schema->getDatabaseName(),
            'tables'      => [],
            'foreignKeys' => $foreignKeys,
        ];

        foreach ($tables as $table) {
            $columns = $this->schema->getColumns($table);
            if (!empty($columns)) {
                $schemaData['tables'][$table] = $columns;
            }
        }

        // Garantir hash determinístico
        ksort($schemaData['tables']);
        sort($schemaData['foreignKeys']);

        return $schemaData;
    }

    protected function checkSchema(string $currentHash): void
    {
        CliPrinter::infoIcon('Checking schema changes...');

        $outputFile = $this->config->getOutputFile();
        $savedHash  = GeneratedClassMetadata::extractSchemaHash($outputFile);

        if (!$savedHash) {
            CliPrinter::warnIcon('Initial generation required');
            return;
        }

        if ($savedHash === $currentHash) {
            CliPrinter::successIcon('Schema unchanged');
            return;
        }

        throw new RuntimeException('Schema changed');
    }

    protected function write(string $content, int $tables, int $foreignKeys): void
    {
        $file = $this->config->getOutputFile();

        if (file_put_contents($file, $content) === false) {
            throw new RuntimeException("Failed to write output file: {$file}");
        }

        $size = number_format(filesize($file) / (1024 / 1024), 0, '.', '.');

        CliPrinter::success("✓ Generated: {$file}");
        // CliPrinter::line("  > Size: {$size} KB");
        CliPrinter::line("  > Tables: {$tables}");
        CliPrinter::line("  > Foreign Keys: {$foreignKeys}");
        CliPrinter::line("  > Foreign Keys: {$foreignKeys}");
        CliPrinter::line("  > Database: " . $this->schema->getDatabaseName());

        $this->printInstructions();
    }

    protected function printInstructions(): void
    {
        $outputFile = $this->config->getOutputFile();
        $relative   = str_replace(getcwd() . DIRECTORY_SEPARATOR, '', $outputFile);

        CliPrinter::line('');
        CliPrinter::info("To use generated classes globally, add to \033[1mcomposer.json:");

        CliPrinter::line("  \"autoload\": {");
        CliPrinter::line("    \"files\": [");
        CliPrinter::line("      \"{$relative}\"", 'bold');
        CliPrinter::line("    ]");
        CliPrinter::line("  }");

        CliPrinter::line('');
        CliPrinter::line('Then run: composer dump-autoload', 'magenta');
    }
}
