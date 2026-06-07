<?php

namespace Modules\Saas\Database;

use Illuminate\Database\Connection;
use Modules\Saas\Database\ConnectionDrivers\MariaDbConnection;
use Modules\Saas\Database\ConnectionDrivers\MysqlConnection;
use Modules\Saas\Database\ConnectionDrivers\PostgresConnection;
use Modules\Saas\Database\ConnectionDrivers\SQLiteConnection;
use Modules\Saas\Database\ConnectionDrivers\SqlServerConnection;

class CustomConnectionFactory
{
    public static function resolveDatabaseConnections(): void
    {
        Connection::resolverFor('mysql', function ($connection, $database, $prefix, $config) {
            return new MysqlConnection(
                $connection, $database, $prefix, $config
            );
        });

        Connection::resolverFor('mariadb', function ($connection, $database, $prefix, $config) {
            return new MariaDbConnection(
                $connection, $database, $prefix, $config
            );
        });

        Connection::resolverFor('pgsql', function ($connection, $database, $prefix, $config) {
            return new PostgresConnection(
                $connection, $database, $prefix, $config
            );
        });

        Connection::resolverFor('sqlite', function ($connection, $database, $prefix, $config) {
            return new SQLiteConnection(
                $connection, $database, $prefix, $config
            );
        });

        Connection::resolverFor('sqlsrv', function ($connection, $database, $prefix, $config) {
            return new SqlServerConnection(
                $connection, $database, $prefix, $config
            );
        });
    }
}
