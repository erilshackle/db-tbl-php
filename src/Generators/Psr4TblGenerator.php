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
    protected function generateContent(array $tables, array $foreignKeys = [], ?string $schemaHash = null): string
    {
        // In PSR-4 mode, the content is written directly per file
        // This method can return empty string
        $this->ensureNamespaceAndPath();

        foreach ($tables as $table) {
            $this->writeTableClass($table, $foreignKeys);
        }

        $content = $this->generateTblRegistry($tables, $schemaHash);
        // $this->writeFile('Tbl', $content);
        // Nothing to return; all files written individually
        return $content;
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
    private function writeTableClass(string $table, array $foreignKeys): void
    {
        $namespace = rtrim($this->config->get('output.namespace'), '\\');
        $className = 'Tbl' . $this->tableClassName($table);
        $alias     = $this->naming->getTableAlias($table);

        $columns = $this->schema->getColumns($table);
        $enums   = $this->schema->getEnums($table);
        $fks     = array_filter($foreignKeys, fn($fk) => $fk['from_table'] === $table);

        $code  = "<?php\n\n";
        $code .= "namespace {$namespace};\n\n";
        $code .= "/** `table: {$table}` (alias: `{$alias}`) */\n";
        $code .= "final class {$className}\n{\n";

        $code .= "    public const __table = '{$table}';\n";
        $code .= "    public const __alias = '{$table} {$alias}';\n\n";

        // Columns
        foreach ($columns as $column) {
            $code .= "    /** column: {$column} */\n";
            $code .= "    public const {$column} = '{$column}';\n";
        }

        // Enums
        if (!empty($enums)) {
            $grouped = [];
            foreach ($enums as $name => $value) {
                [$col] = explode('_', $name, 2);
                $grouped[$col][] = $value;
            }

            foreach ($grouped as $col => $values) {
                $list = implode('|', $values);
                $code .= "\n    /** enum {$col}: {$list} */\n";
                foreach ($values as $val) {
                    $const = strtolower($col . '_' . $val);
                    $const = preg_replace('/[^a-z0-9_]/', '_', $const);
                    $code .= "    public const enum_{$const} = '{$val}';\n";
                }
            }
        }

        // Foreign keys
        if (!empty($fks)) {
            $code .= "\n";
            foreach ($fks as $fk) {
                $fkConst = $this->naming->getForeignKeyConstName($fk['to_table'], false);
                $code .= "    /** references `{$fk['to_table']}` → `{$fk['to_column']}` */\n";
                $code .= "    public const {$fkConst} = '{$fk['from_column']}';\n";
            }
        }

        $code .= "}\n";

        $this->writeFile($className, $code);
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
 * Database Schema: {$db}
 *
 * @schema-hash md5:{$hash}
 * @generated   {$time}
 * @tool        db-tbl
 *
 * ⚠ AUTO-GENERATED FILE — DO NOT EDIT
 */

PHP;
    }

    private function generateClassDoc(string $table): string
    {
        $time = date('Y-m-d H:i:s');
        $hash = md5($table); // optional lightweight hash

        return <<<PHP
/**
 * Table class: {$table}
 * 
 * @generated   {$time}
 */

PHP;
    }

    private function writeFile(string $className, string $content): void
    {
        $dir = rtrim($this->config->getOutputPath(), '/') . '/';
        $filePath = "{$dir}{$className}.php";

        if (file_put_contents($filePath, $content) === false) {
            throw new RuntimeException("Failed to write table file: {$filePath}");
        }
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
