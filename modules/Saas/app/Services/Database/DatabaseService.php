<?php

namespace Modules\Saas\Services\Database;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Saas\Exceptions\DatabaseCreationException;
use Modules\Saas\Exceptions\DatabaseUserAssignmentException;
use Modules\Saas\Exceptions\DatabaseUserCreationException;

class DatabaseService implements DatabaseServiceInterface
{
    /**
     * Create a new database
     *
     * @throws DatabaseCreationException
     */
    public function createDatabase(string $databaseName): bool
    {

        if (settings()->get('mysql_root_enabled') ) {

            if (!settings()->get('mysql_root_password')) {
                throw new DatabaseCreationException('Root password is not set. Please set it in the settings panel');
            }

            $dbConnection = DB::connection()->getConfig();
            $dbConnection['username'] = settings()->get('mysql_root_username') ?? 'root';
            $dbConnection['password'] = settings()->get('mysql_root_password');
            $dbConnection['host'] = settings()->get('mysql_root_host') ?? 'localhost';
            $dbConnection['port'] = settings()->get('mysql_root_port') ?? 3306;
            $this->resetDBConnection($dbConnection);
        }

        try {
            if (! $this->isValidDatabaseName($databaseName)) {
                throw new DatabaseCreationException('Invalid database name format');
            }

            $query = "CREATE DATABASE IF NOT EXISTS `{$databaseName}`
                     DEFAULT CHARACTER SET utf8mb4
                     DEFAULT COLLATE utf8mb4_unicode_ci";

            DB::statement($query);

            Log::info('Database created successfully', ['database' => $databaseName]);

            return true;

        } catch (Exception $e) {
            Log::error('Database creation failed', [
                'database' => $databaseName,
                'error' => $e->getMessage(),
            ]);

            throw new DatabaseCreationException(
                "Failed to create database: {$databaseName}",
                previous: $e
            );
        }
    }

    /**
     * Create a new database user
     *
     * @throws DatabaseUserCreationException
     */
    public function createDatabaseUser(string $username, string $password): bool
    {
        try {
            if (! $this->isValidUsername($username)) {
                throw new DatabaseUserCreationException('Invalid username format');
            }

            if (! $this->isValidPassword($password)) {
                throw new DatabaseUserCreationException(
                    'Password must be at least 8 characters long and contain mixed case letters, numbers, and symbols'
                );
            }

            // Safely escape and quote the username
            $usernameQuoted = DB::connection()->getPdo()->quote($username);
            $passwordQuoted = DB::connection()->getPdo()->quote($password);

            $hosts = ['%', 'localhost'];
            foreach ($hosts as $host) {
                $hostQuoted = DB::connection()->getPdo()->quote($host);
                $query = "CREATE USER IF NOT EXISTS $usernameQuoted@$hostQuoted IDENTIFIED BY $passwordQuoted";
                DB::statement($query);
            }

            Log::info('Database user created successfully', ['username' => $username]);

            return true;

        } catch (Exception $e) {
            Log::error('Database user creation failed', [
                'username' => $username,
                'error' => $e->getMessage(),
            ]);

            throw new DatabaseUserCreationException(
                "Failed to create database user: {$username}",
            );
        }
    }

    /**
     * Assign user to database with appropriate privileges
     *
     * @throws DatabaseUserAssignmentException
     */
    public function assignUserToDatabase(string $databaseName, string $username): bool
    {
        try {
            if (! $this->isValidDatabaseName($databaseName) || ! $this->isValidUsername($username)) {
                throw new DatabaseUserAssignmentException('Invalid database name or username format');
            }

            $usernameQuoted = DB::connection()->getPdo()->quote($username);
            $hosts = ['%', 'localhost'];

            foreach ($hosts as $host) {
                $hostQuoted = DB::connection()->getPdo()->quote($host);
                $query = "GRANT ALL PRIVILEGES ON `{$databaseName}`.* TO $usernameQuoted@$hostQuoted";
                DB::statement($query);
            }
            DB::statement('FLUSH PRIVILEGES');

            Log::info('User assigned to database successfully', [
                'database' => $databaseName,
                'username' => $username,
            ]);

            return true;

        } catch (Exception $e) {
            Log::error('Database user assignment failed', [
                'database' => $databaseName,
                'username' => $username,
                'error' => $e->getMessage(),
            ]);

            throw new DatabaseUserAssignmentException(
                "Failed to assign user {$username} to database {$databaseName}",
                previous: $e
            );
        }
    }

    /**
     * Validate database name format
     */
    private function isValidDatabaseName(string $databaseName): bool
    {
        return preg_match('/^[a-zA-Z0-9_]+$/', $databaseName)
            && strlen($databaseName) <= 64;
    }

    /**
     * Validate username format
     */
    private function isValidUsername(string $username): bool
    {
        return preg_match('/^[a-zA-Z0-9_]+$/', $username)
            && strlen($username) <= 32;
    }

    /**
     * Validate password strength
     */
    private function isValidPassword(string $password): bool
    {
        return strlen($password) >= 8
            && preg_match('/[A-Z]/', $password)
            && preg_match('/[a-z]/', $password)
            && preg_match('/[0-9]/', $password)
            && preg_match('/[^A-Za-z0-9]/', $password);
    }

    private function resetDBConnection($dbConfig)
    {
        $connectionName = DB::connection()->getName();
        config()->set('database.connections.'.$connectionName, $dbConfig);

        DB::purge($connectionName);
        DB::reconnect($connectionName);
    }

    public function removeTenantDatabaseAndUser($database, $username)
    {
        try {

            if (settings()->get('mysql_root_enabled') ) {

                if (!settings()->get('mysql_root_password')) {
                    throw new DatabaseCreationException('Root password is not set. Please set it in the settings panel');
                }

                $dbConnection = DB::connection()->getConfig();
                $dbConnection['username'] = settings()->get('mysql_root_username') ?? 'root';
                $dbConnection['password'] = settings()->get('mysql_root_password');
                $dbConnection['host'] = settings()->get('mysql_root_host') ?? 'localhost';
                $dbConnection['port'] = settings()->get('mysql_root_port') ?? 3306;
                $this->resetDBConnection($dbConnection);
            }

            DB::statement("DROP DATABASE IF EXISTS `$database`");

            $usernameQuoted = DB::connection()->getPdo()->quote($username);
            $hosts = ['%', 'localhost'];

            foreach ($hosts as $host) {
                $hostQuoted = DB::connection()->getPdo()->quote($host);
                $query = "DROP USER IF EXISTS $usernameQuoted@$hostQuoted]]]";

                DB::statement("REVOKE ALL PRIVILEGES, GRANT OPTION FROM '$usernameQuoted'@'$hostQuoted'");
                DB::statement($query);
            }

            DB::statement("FLUSH PRIVILEGES");

            return true;
        } catch (\Exception $e) {
            Log::error('Database user remove failed', [
                'username' => $username,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
