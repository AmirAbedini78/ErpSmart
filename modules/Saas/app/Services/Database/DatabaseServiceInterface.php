<?php

namespace Modules\Saas\Services\Database;

interface DatabaseServiceInterface
{
    public function createDatabase(string $databaseName): bool;

    public function createDatabaseUser(string $username, string $password): bool;

    public function assignUserToDatabase(string $databaseName, string $username): bool;
    public function removeTenantDatabaseAndUser(string $database, string $username);

}
