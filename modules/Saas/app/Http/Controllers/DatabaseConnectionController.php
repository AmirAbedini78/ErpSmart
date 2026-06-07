<?php

namespace Modules\Saas\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Database\Connection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Modules\Installer\DatabaseTest;
use Modules\Installer\PrivilegesChecker;
use Symfony\Component\HttpFoundation\Response;

class DatabaseConnectionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function __invoke(Request $request)
    {
        try {
            $privileges = new PrivilegesChecker(
                new DatabaseTest($this->testDatabaseConnection($request))
            );

            $privileges->check();
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }

        return response()->json([
            'success' => true,
            'message' => 'Database connection successful',
        ]);
    }

    /**
     * Test the database connection.
     */
    protected function testDatabaseConnection(Request $request): Connection
    {
        $config = [
            'driver' => 'mysql',
            'host' => $request->db_host,
            'database' => $request->database,
            'username' => $request->db_user,
            'password' => $request->db_password,
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => config('database.connections.mysql.prefix'),
        ];

        $connectionKey = 'install'.md5(json_encode($config));

        Config::set('database.connections.'.$connectionKey, $config);

        /**
         * @var \Illuminate\Database\Connection
         */
        $connection = DB::connection($connectionKey);

        // Triggers PDO init, in case of errors, will fail and throw exception
        $connection->getPdo();

        return $connection;
    }
}
