<?php

namespace Eril\DbTbl\Generators;

use Eril\DbTbl\Cli\CliPrinter;
use Eril\DbTbl\Config;
use Eril\DbTbl\Resolvers\NamingResolver;
use Eril\DbTbl\Schema\SchemaReaderInterface;
use RuntimeException;

/**
 * Generates one PHP class per table, following PSR-4 directory and namespace conventions.
 *
 * Requirements:
 * - output.mode must be "psr4"
 * - output.namespace must be defined
 */
final class Psr4TblGenerator extends Generator
{
    private NamingResolver $naming;

    public function __construct(
        SchemaReaderInterface $schema,
        Config $config,
        bool $checkMode = false
    ) {
        parent::__construct($schema, $config, $checkMode);
        $this->naming = new NamingResolver($config->getNamingConfig());
    }

    /**
     * Generate all table classes in PSR-4 structure.
     */
    protected function generateContent(
        array $tables,
        array $foreignKeys = [],
        ?string $schemaHash = null
    ): string {
        $this->ensureNamespaceAndPath();

        $renderer = new TableClassRenderer(
            $this->schema,
            $this->naming
        );

        foreach ($tables as $table) {
            $this->writeClassFile(
                $table,
                $renderer->render($table, $foreignKeys)
            );
        }

        return $this->generateTblRegistry($tables, $schemaHash);
    }


    // ------------------------------------------------------------------
    // Ensure proper output
    // ------------------------------------------------------------------
    private function ensureNamespaceAndPath(): void
    {
        $ns = $this->config->get('output.namespace');
        if (empty($ns)) {
            throw new RuntimeException('PSR-4 output requires "output.namespace" to be set.');
        }

        $dir = rtrim($this->config->getOutputPath(), '/') . '/';
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            throw new RuntimeException("Cannot create PSR-4 output directory: {$dir}");
        }
    }

    // ------------------------------------------------------------------
    // Write individual table class
    // ------------------------------------------------------------------
    private function writeClassFile(string $table, string $classBody): void
    {
        $namespace = rtrim($this->config->get('output.namespace'), '\\');
        $className = 'Tbl' . $this->tableClassName($table);

        $code  = "<?php\n\n";
        $code .= "namespace {$namespace};\n\n";
        $code .= $classBody;

        $file = rtrim($this->config->getOutputPath(), '/') . "/{$className}.php";

        if (file_put_contents($file, $code) === false) {
            throw new RuntimeException("Failed to write: {$file}");
        }
    }



    private function generateTblRegistry(array $tables, string $schemaHash): string
    {
        $namespace = rtrim($this->config->get('output.namespace'), '\\');

        $content  = "<?php\n\n";
        $content .= "namespace {$namespace};\n\n";
        $content .= $this->generateHeader($this->schema->getDatabaseName(), $schemaHash);
        $content .= "final class Tbl\n{\n";

        foreach ($tables as $table) {
            $const = $this->naming->getTableConstName($table, 'full');
            $content .= "    public const {$const} = '{$table}';\n";
        }

        $content .= "\n    // Table aliases\n";

        foreach ($tables as $table) {
            $const = $this->naming->getTableConstName($table, 'full');
            $alias = $this->naming->getTableAlias($table);
            $content .= "    public const as_{$const} = '{$table} {$alias}';\n";
        }

        $content .= "}\n\n";

        return $content;
    }

    private function generateHeader(string $db, string $hash): string
    {
        $time = date('Y-m-d H:i:s');

        return <<<PHP
/**
 * Database schema mapping for "{$db}"
 *
 * This file is generated from the live database schema and
 * provides a stable, type-safe reference to tables and columns.
 *
 * @schema-hash md5:{$hash}
 * @generated   {$time}
 * @tool        db-tbl
 *
 * ⚠ AUTO-GENERATED FILE
 * Any manual changes will be lost on regeneration.
 */

PHP;
    }


    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------
    private function tableClassName(string $table): string
    {
        $name = $this->naming->getTableConstName($table);
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $name)));
    }


    // ------------------------------------------------------------------
    // Instruction Autoload
    // ------------------------------------------------------------------
    protected function printInstructions(): void {}
}
