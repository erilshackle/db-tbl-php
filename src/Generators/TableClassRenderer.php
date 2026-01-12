<?php

namespace Eril\DbTbl\Generators;

use Eril\DbTbl\Resolvers\NamingResolver;
use Eril\DbTbl\Schema\SchemaReaderInterface;

final class TableClassRenderer
{
    public function __construct(
        private SchemaReaderInterface $schema,
        private NamingResolver $naming
    ) {}

    public function render(string $table, array $foreignKeys): string
    {
        $className = 'Tbl' . $this->tableClassName($table);
        $alias     = $this->naming->getTableAlias($table);

        $columns = $this->schema->getColumns($table);
        $enums   = $this->schema->getEnums($table);
        $fks     = array_filter(
            $foreignKeys,
            fn($fk) => $fk['from_table'] === $table
        );

        $code  = "/** `table: {$table}` (alias: `{$alias}`) */\n";
        $code .= "final class {$className}\n{\n";

        $code .= "    public const __table = '{$table}';\n";
        $code .= "    public const __alias = '{$table} {$alias}';\n\n";

        foreach ($columns as $column) {
            $code .= "    public const {$column} = '{$column}';\n";
        }

        if (!empty($enums)) {
            $code .= "\n";
            foreach ($enums as $name => $value) {
                $code .= "    public const enum_{$name} = '{$value}';\n";
            }
        }

        if (!empty($fks)) {
            $code .= "\n";
            foreach ($fks as $fk) {
                $fkConst = $this->naming
                    ->getForeignKeyConstName($fk['to_table'], false);

                $code .= "    /** references `{$fk['to_table']}` → `{$fk['to_column']}` */\n";
                $code .= "    public const {$fkConst} = '{$fk['from_column']}';\n";
            }
        }

        return $code . "}\n";
    }

    private function tableClassName(string $table): string
    {
        $name = $this->naming->getTableConstName($table);
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $name)));
    }
}
