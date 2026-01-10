<?php

namespace Eril\DbTbl\Generators;

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

        return ''; // Nothing to return; all files written individually
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
        $columns   = $this->schema->getColumns($table);
        $enums     = $this->schema->getEnums($table);
        $fks       = array_filter($foreignKeys, fn($fk) => $fk['from_table'] === $table);

        $content  = "<?php\n\n";
        $content .= "namespace {$namespace};\n\n";
        $content .= $this->generateHeader($table);
        $content .= "final class {$className}\n{\n";

        // Columns
        foreach ($columns as $column) {
            $content .= "    public const {$column} = '{$column}';\n";
        }

        // ENUMs
        if (!empty($enums)) {
            $content .= "\n    // Enum values\n";
            foreach ($enums as $name => $value) {
                $content .= "    public const enum_{$name} = '{$value}';\n";
            }
        }

        // Foreign Keys
        if (!empty($fks)) {
            $content .= "\n    // Foreign Keys\n";
            foreach ($fks as $fk) {
                $content .= "    /** FK → {$fk['to_table']}.{$fk['to_column']} */\n";
                $content .= "    public const fk_{$fk['to_table']} = '{$fk['from_column']}';\n";
            }
        }

        $content .= "\n    public const __table = '{$table}';\n";
        $content .= "    public const __alias = '{$this->naming->getTableAlias($table)}';\n";
        $content .= "}\n";

        $this->writeFile($className, $content);
    }

    private function generateHeader(string $table): string
    {
        $time = date('Y-m-d H:i:s');
        $hash = md5($table); // optional lightweight hash

        return <<<PHP
/**
 * Table class: {$table}
 *
 * @schema-hash md5:{$hash}
 * @generated   {$time}
 * @tool        db-tbl
 *
 * ⚠ AUTO-GENERATED FILE — DO NOT EDIT
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
}
