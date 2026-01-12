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
    private TableClassRenderer $renderer;

    public function __construct(
        SchemaReaderInterface $schema,
        Config $config,
        bool $checkMode = false
    ) {
        parent::__construct($schema, $config, $checkMode);
        $this->naming = new NamingResolver($config->getNamingConfig());
        $this->renderer = new TableClassRenderer($schema, $this->naming);
    }


    protected function generateContent(
        array $tables,
        array $foreignKeys = [],
        ?string $schemaHash = null
    ): string {
        $code  = "<?php\n\n";
        $code .= $this->generateNamespace();
        $code .= $this->generateHeader($schemaHash);
        $code .= $this->generateTblRegistry($tables);
        $code .= $this->generateTableClasses($tables, $foreignKeys);
        $code .= "\n// end of auto-generated file\n";

        return $code;
    }

    // ------------------------------------------------------------------
    // Sections
    // ------------------------------------------------------------------

    private function generateHeader(?string $hash): string
    {
        $time = date('Y-m-d H:i:s');

        return <<<PHP
/**
 * Table registry for the database schema
 *
 * This class contains constants representing all tables in the
 * database, including full table names and table aliases.
 * It provides a central reference for type-safe access to tables
 * throughout the application.
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
            $code .= $this->renderer->render($table, $foreignKeys);
            $code .= "\n\n";
        }

        return $code;
    }
}
