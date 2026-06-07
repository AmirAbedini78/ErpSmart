<?php

namespace Modules\Saas\Services;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class TenantMigrationService
{
    private string $tablePrefix;

    private array $excludedTables = ['migrations', 'tenants', 'password_resets', 'packages', 'quotas', 'tenant_databases', 'tenant_modules', 'countries', 'dashboards', 'jobs', 'failed_jobs', 'job_batches', 'subscription_histories', 'tenant_usages', 'pages'];

    public function __construct()
    {
        $this->tablePrefix = DB::getTablePrefix();
    }

    /**
     * Add tenant_id column to all relevant tables
     *
     * @return array Array of affected table names
     */
    public function addTenantIdToTables(): array
    {
        $affectedTables = [];
        $tables = $this->getRelevantTables();

        foreach ($tables as $table) {
            $table = $this->removePrefix($table);

            if (! Schema::hasColumn($table, 'tenant_id')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->unsignedBigInteger('tenant_id')->nullable();
                    $table->foreign('tenant_id')
                        ->references('id')
                        ->on('tenants')
                        ->onDelete('cascade');

                    // Add index for better query performance
                    $table->index('tenant_id');
                });

                $affectedTables[] = $table;
            }
        }

        $this->handleUniqueIndexCreation();

        return $affectedTables;
    }

    /**
     * Remove prefix from table name
     */
    private function removePrefix(string $tableName): string
    {
        $prefix = $this->tablePrefix;

        if (! empty($prefix) && strpos($tableName, $prefix) === 0) {
            return substr($tableName, strlen($prefix));
        }

        return $tableName;
    }

    /**
     * Remove tenant_id column from all relevant tables
     *
     * @return array Array of affected table names
     */
    public function removeTenantIdFromTables(): array
    {
        $this->reverseUniqueIndexCreation();

        $affectedTables = [];
        $tables = $this->getRelevantTables();

        foreach ($tables as $table) {
            $table = $this->removePrefix($table);

            if (Schema::hasColumn($table, 'tenant_id')) {

                DB::table($table)->where('tenant_id', '!=', null)->delete();

                Schema::table($table, function (Blueprint $table) {
                    // Drop foreign key constraint first
                    try {
                        $table->dropForeign(['tenant_id']);
                    } catch (\Exception $e) {
                        Log::error("Failed to remove foreign key from database table: " . $e->getMessage());
                    }

                    try {
                        $table->dropIndex(['tenant_id']);
                    } catch (\Exception $e) {
                        Log::error("Failed to remove tenant id from database table: " . $e->getMessage());
                    }
                    $table->dropColumn('tenant_id');
                });

                $affectedTables[] = $table;
            }
        }

        return $affectedTables;
    }

    /**
     * Get all relevant tables that should be modified
     *
     * @return array Array of table names
     */
    private function getRelevantTables(): array
    {
        $allTables = DB::select('SHOW TABLES');
        $allTables = array_map(fn ($table) => array_values((array) $table)[0], $allTables);

        return array_filter(array_values($allTables), function ($table) {
            // Only include tables with the configured prefix and exclude specific tables
            return empty($this->tablePrefix) ||
                (str_starts_with($table, $this->tablePrefix) &&
                    ! in_array($this->removePrefix($table), $this->excludedTables));
        });
    }

    /**
     * Set tables to exclude from modifications
     *
     * @param  array  $tables  Array of table names to exclude
     */
    public function setExcludedTables(array $tables): self
    {
        $this->excludedTables = array_merge($this->excludedTables, $tables);

        return $this;
    }

    /**
     * Set the table prefix
     *
     * @param  string  $prefix  Table prefix
     */
    public function setTablePrefix(string $prefix): self
    {
        $this->tablePrefix = $prefix;

        return $this;
    }

    public function handleUniqueIndexCreation()
    {
        $connection = DB::connection();
        $tables = $this->getRelevantTables();

        foreach ($tables as $table) {

            $tableName = $this->removePrefix($table);
            $indexes = $connection->getSchemaBuilder()->getIndexes($tableName);
            $foreignKeys = $connection->getSchemaBuilder()->getForeignKeys($tableName);

            // Gather foreign key information
            $foreignKeyInfo = [];
            foreach ($foreignKeys as $foreignKey) {
                $foreignKeyName = $foreignKey['name'];
                $foreignKeyColumns = $foreignKey['columns'];
                $referencedTable = $foreignKey['foreign_table'];
                $referencedColumns = $foreignKey['foreign_columns'];

                $foreignKeyInfo[$foreignKeyName] = [
                    'columns' => $foreignKeyColumns,
                    'name' => $foreignKeyName,
                    'references' => [
                        'table' => $referencedTable,
                        'columns' => $referencedColumns,
                        'on_delete' => $foreignKey['on_delete'],
                        'on_update' => $foreignKey['on_update'],
                    ],
                ];
            }

            foreach ($indexes as $index) {
                if ($index['unique'] && ! $index['primary'] && ! $this->hasTenantIdColumn($index)) {
                    if (Schema::hasColumn($tableName, 'tenant_id')) {
                        $columns = $index['columns'];
                        $indexName = $index['name'];

                        // Check if this index is referenced by any foreign key
                        $referencedForeignKeys = $this->isIndexReferencedByForeignKey($columns, $foreignKeyInfo);

                        if ($referencedForeignKeys->isNotEmpty()) {
                            $this->handleReferencedIndex($tableName, $indexName, $columns, $referencedForeignKeys);
                        } else {
                            // Drop the existing unique index
                            Schema::table($tableName, function ($tableName) use ($indexName) {
                                $tableName->dropIndex($indexName);
                            });

                            // Create new unique index with tenant_id
                            $newColumns = array_merge($columns, ['tenant_id']);
                            Schema::table($tableName, function ($table) use ($indexName, $newColumns) {
                                $table->unique($newColumns, $indexName);
                            });

                        }
                    }
                }
            }
        }

    }

    private function hasTenantIdColumn($index)
    {
        return in_array('tenant_id', $index['columns']);
    }

    private function isIndexReferencedByForeignKey(array $columns, array $foreignKeys)
    {
        $referencedForeignKeys = collect();
        foreach ($foreignKeys as $foreignKey) {
            $foreignKeyColumns = collect($foreignKey['columns']);
            foreach ($columns as $column) {
                if ($foreignKeyColumns->contains($column)) {
                    $referencedForeignKeys->push($foreignKey);
                }
            }
        }

        return $referencedForeignKeys;
    }

    private function handleReferencedIndex($table, $indexName, $columns, $foreignKeys)
    {
        foreach ($foreignKeys as $foreignKeyInfo) {
            Schema::table($table, function ($table) use ($foreignKeyInfo) {
                $table->dropForeign($foreignKeyInfo['name']);
            });
        }

        // Drop the existing unique index
        Schema::table($table, function ($table) use ($indexName) {
            $table->dropIndex($indexName);
        });

        // Create new unique index with tenant_id
        $newColumns = array_merge($columns, ['tenant_id']);
        Schema::table($table, function ($table) use ($indexName, $newColumns) {
            $table->unique($newColumns, $indexName);
        });

        $this->recreateForeignKeys($table, $foreignKeys);
    }

    private function dropForeignKeys($table, $indexName, $foreignKeyInfo)
    {
        foreach ($foreignKeyInfo as $foreignKeyName => $info) {
            // Assuming the foreign key name is related to the index name
            if (str_contains($foreignKeyName, $indexName)) {
                Schema::table($table, function ($table) use ($foreignKeyName) {
                    $table->dropForeign($foreignKeyName);
                });
            }
        }
    }

    private function recreateForeignKeys($table, $foreignKeys)
    {

        foreach ($foreignKeys as $name => $foreignKey) {
            if ($name == 1) {
                $name = null;
            }
            Schema::table($table, function (Blueprint $table) use ($foreignKey, $name) {
                $table->foreign($foreignKey['columns'], $name)
                    ->references($foreignKey['references']['columns'])
                    ->on($this->removePrefix($foreignKey['references']['table']))
                    ->onDelete($foreignKey['references']['on_delete'])
                    ->onUpdate($foreignKey['references']['on_update']);
            });
        }
    }

    public function reverseUniqueIndexCreation()
    {
        $connection = DB::connection();
        $tables = $this->getRelevantTables();

        foreach ($tables as $table) {

            $tableName = $this->removePrefix($table);
            $indexes = $connection->getSchemaBuilder()->getIndexes($tableName);
            $foreignKeys = $connection->getSchemaBuilder()->getForeignKeys($tableName);

            // Gather foreign key information
            $foreignKeyInfo = [];
            foreach ($foreignKeys as $foreignKey) {
                $foreignKeyName = $foreignKey['name'];
                $foreignKeyColumns = $foreignKey['columns'];
                $referencedTable = $foreignKey['foreign_table'];
                $referencedColumns = $foreignKey['foreign_columns'];

                $foreignKeyInfo[$foreignKeyName] = [
                    'columns' => $foreignKeyColumns,
                    'name' => $foreignKeyName,
                    'references' => [
                        'table' => $referencedTable,
                        'columns' => $referencedColumns,
                        'on_delete' => $foreignKey['on_delete'],
                        'on_update' => $foreignKey['on_update'],
                    ],
                ];
            }

            foreach ($indexes as $index) {
                if ($index['unique'] && ! $index['primary'] && $this->hasTenantIdColumn($index)) {
                    $columns = $index['columns'];
                    $indexName = $index['name'];

                    // Check if this index is referenced by any foreign key
                    $isReferenced = $this->isIndexReferencedByForeignKey($columns, $foreignKeys);

                    if ($isReferenced) {
                        $this->handleReversingReferencedIndex($tableName, $indexName, $columns, $foreignKeyInfo);
                    } else {
                        // Drop the existing unique index
                        Schema::table($tableName, function ($tableName) use ($indexName) {
                            $tableName->dropIndex($indexName);
                        });

                        // Create new unique index without tenant_id
                        $newColumns = array_diff($columns, ['tenant_id']);

                        Schema::table($tableName, function ($tableName) use ($newColumns) {
                            if (! empty($newColumns)) {
                                $tableName->unique($newColumns);
                            }
                        });

                    }
                }
            }
        }

    }

    private function handleReversingReferencedIndex($table, $indexName, $columns, $foreignKeys)
    {
        foreach ($foreignKeys as $foreignKeyInfo) {
            Schema::table($table, function ($table) use ($foreignKeyInfo) {
                $table->dropForeign($foreignKeyInfo['name']);
            });
        }

        // Drop the existing unique index
        Schema::table($table, function ($table) use ($indexName) {
            $table->dropIndex($indexName);
        });

        // Create new unique index with tenant_id
        $newColumns = array_diff($columns, ['tenant_id']);
        Schema::table($table, function ($table) use ($indexName, $newColumns) {
            $table->unique($newColumns, $indexName);
        });

        $this->recreateForeignKeys($table, $foreignKeys);
    }
}
