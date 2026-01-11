<?php

namespace Eril\DbTbl\Generators;

use Eril\DbTbl\Config;
use Eril\DbTbl\Resolvers\NamingResolver;
use Eril\DbTbl\Schema\SchemaReaderInterface;

/**
 * Generates all schema classes into a single PHP file.
 *
 * Output:
 *  - Tbl (table registry)
 *  - Tbl<Table> (one class per table)
 */
final class FileTblGenerator extends Generator
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

    protected function generateContent(
        array $tables,
        array $foreignKeys = [],
        ?string $schemaHash = null
    ): string {
        $code  = "<?php\n\n";
        $code .= $this->generateHeader($schemaHash);
        $code .= $this->generateNamespace();
        $code .= $this->generateTblRegistry($tables);
        $code .= $this->generateTableClasses($tables, $foreignKeys);
        $code .= "\n// end of auto-generated file\n";

        return $code;
    }

    // ------------------------------------------------------------------
    // Sections
    // ------------------------------------------------------------------

    private function generateHeader(?string $schemaHash): string
    {
        $time = date('Y-m-d H:i:s');

        return <<<PHP
/**
 * Database table constants
 *
 * @schema-hash md5:{$schemaHash}
 * @generated   {$time}
 * @tool        db-tbl
 *
 * ⚠ AUTO-GENERATED FILE — DO NOT EDIT
 */

PHP;
    }

    private function generateNamespace(): string
    {
        $namespace = $this->config->getOutputNamespace();

        return $namespace
            ? "namespace {$namespace};\n\n"
            : '';
    }

    /**
     * Generates the Tbl registry class
     */
    private function generateTblRegistry(array $tables): string
    {
        $code = "final class Tbl\n{\n";

        foreach ($tables as $table) {
            $const = $this->naming->getTableConstName($table, 'full');
            $code .= "    public const {$const} = '{$table}';\n";
        }

        $code .= "\n    // Table aliases\n";

        foreach ($tables as $table) {
            $const = $this->naming->getTableConstName($table, 'full');
            $alias = $this->naming->getTableAlias($table);
            $code .= "    public const as_{$const} = '{$table} {$alias}';\n";
        }

        $code .= "}\n\n";

        return $code;
    }

    /**
     * Generates one class per table
     */
    private function generateTableClasses(array $tables, array $foreignKeys): string
    {

        $code = '';
        foreach ($tables as $table) {
            $className = 'Tbl' . $this->tableClassName($table);
            $alias = $this->naming->getTableAlias($table);
            $columns   = $this->schema->getColumns($table);
            $enums     = $this->schema->getEnums($table);
            $fks       = array_filter(
                $foreignKeys,
                fn($fk) => $fk['from_table'] === $table
            );

            $code .= "    /** `table: $table` (alias: `$alias`)*/\n";
            $code .= "final class {$className}\n{\n";

            $code .= "    public const __table = '{$table}';\n";
            $code .= "    public const __alias = '{$table} {$alias}';\n\n";
            // todo $code .= "    public const __pk = '{$primaryKey}';\n";

            // Columns
            foreach ($columns as $column) {
                $code .= "    public const {$column} = '{$column}';\n";
            }

            // Enums
            if (!empty($enums)) {
                $code .= "\n";
                foreach ($enums as $name => $value) {
                    $code .= "    public const enum_{$name} = '{$value}';\n";
                }
            }

            // Foreign keys
            if (!empty($fks)) {
                $code .= "\n";
                foreach ($fks as $fk) {
                    $fkCol = $this->naming->getForeignKeyConstName($fk['to_table'], false);
                    $code .= "    /** references `{$fk['to_table']}` → `{$fk['to_column']}` */";
                    $code .= "  public const {$fkCol} = '{$fk['from_column']}';\n";
                }
            }

            $code .= "}\n\n";
        }

        return $code;
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
