<?php

namespace Modules\Saas\Services\Cpanel;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Modules\Saas\Services\Database\DatabaseServiceInterface;
use Modules\Saas\Services\Domain\DomainServiceInterface;
use Modules\Saas\Services\Subdomain\SubdomainServiceInterface;
use Modules\Saas\Traits\ValidationTrait;
use RuntimeException;

class CpanelService implements DatabaseServiceInterface, DomainServiceInterface, SubdomainServiceInterface
{
    use ValidationTrait;

    private Client $httpClient;

    private string $cpanelDomain;

    private string $cpanelPort;

    private string $cpanelUsername;

    private string $cpanelPassword;

    /**
     * @throws RuntimeException
     */
    public function __construct()
    {
        $this->validateConfiguration();

        $this->httpClient = new Client([
            'timeout' => config('saas.cpanel.timeout', 30),
            'verify' => config('saas.cpanel.verify_ssl', true),
        ]);

        $this->cpanelDomain = settings()->get('cpanel_login_domain');
        $this->cpanelPort = settings()->get('cpanel_port') ?? 2083;
        $this->cpanelUsername = settings()->get('cpanel_username');
        $this->cpanelPassword = settings()->get('cpanel_password');
        $this->db_prefix = settings()->get('cpanel_db_prefix');
    }

    public function addPrefix($text)
    {
        if (empty($this->cpanelUsername)) {
            return $text;
        }

        return str_starts_with($text, $this->cpanelUsername) ? $text : $this->cpanelUsername.'_'.$text;
    }

    private function makeAPIRequest($module, $func, $params = [])
    {
        $url = "https://{$this->cpanelDomain}:{$this->cpanelPort}/execute/{$module}/{$func}";

        return $this->httpClient->post($url, [
            'form_params' => $params,
            'headers' => [
                'Authorization' => 'Basic '.base64_encode("{$this->cpanelUsername}:{$this->cpanelPassword}"),
                'Accept' => 'application/json',
            ],
        ]);
    }

    /**
     * Create a new subdomain using cPanel API
     *
     * @param  string  $subdomain  The subdomain name
     * @param  string  $domain  The main domain name
     * @param  string  $documentRoot  The document root path
     *
     * @throws RuntimeException
     */
    public function createSubdomain(string $subdomain, string $domain, string $documentRoot = '/public_html'): bool
    {
        $this->validateInputs($subdomain, $domain, $documentRoot);

        $query = [
            'domain' => $subdomain,
            'rootdomain' => $domain,
            'dir' => $documentRoot,
        ];

        $response = $this->makeAPIRequest('SubDomain', 'addsubdomain', $query);

        $statusCode = $response->getStatusCode();
        $responseBody = json_decode($response->getBody()->getContents(), true);
        if ($statusCode !== 200) {
            Log::error('Failed to create subdomain in CPanel', [
                'subdomain' => $subdomain,
                'domain' => $domain,
                'document_root' => $documentRoot,
                'status_code' => $statusCode,
                'response_body' => $responseBody,
            ]);
            throw new RuntimeException(
                "CPanel API returned non-200 status code: {$statusCode}"
            );
        }

        return $this->validateResponse($responseBody);
    }

    public function deleteSubdomain(string $subdomain): bool
    {
        $query = [
            'domain' => $subdomain,
        ];
        $response = $this->makeAPIRequest('SubDomain', 'delsubdomain', $query);

        $statusCode = $response->getStatusCode();

        $responseBody = json_decode($response->getBody()->getContents(), true);
        if ($statusCode !== 200) {
            Log::error('Failed to delete subdomain in CPanel', [
                'subdomain' => $subdomain,
                'status_code' => $statusCode,
                'response_body' => $responseBody,
            ]);

            throw new RuntimeException(
                "CPanel API returned non-200 status code: {$statusCode}"
            );
        }

        return $this->validateResponse($responseBody);
    }

    /**
     * Validate configuration settings
     *
     * @throws RuntimeException
     */
    private function validateConfiguration(): void
    {
        $requiredSettings = [
            'cpanel_domain' => settings()->get('cpanel_login_domain'),
            'cpanel_username' => settings()->get('cpanel_username'),
            'cpanel_password' => settings()->get('cpanel_password'),
            'cpanel_db_prefix' => settings()->get('cpanel_db_prefix'),
        ];

        foreach ($requiredSettings as $setting => $value) {
            if (empty($value)) {
                throw new RuntimeException("Missing required CPanel configuration: {$setting}");
            }
        }
    }

    /**
     * Validate API response
     *
     * @throws RuntimeException
     */
    private function validateResponse(array $response): bool
    {
        if (isset($response['cpanelresult']['error'])) {
            $error = $response['cpanelresult']['error'];
            if (is_array($error)) {
                $error = implode(', ', $error);
            }

            abort(400, "CPanel API error: {$error}");
        }

        return true;
    }

    /**
     * Create a new database
     *
     * @throws RuntimeException
     */
    public function createDatabase(string $databaseName): bool
    {
        $this->validateDatabaseName($databaseName);
        $args = [
            'name' => $this->addPrefix($databaseName),
        ];

        $response = $this->makeAPIRequest('Mysql', 'create_database', $args);
        $responseBody = json_decode($response->getBody()->getContents(), true);
        $statusCode = $response->getStatusCode();

        return $this->validateResponse($responseBody);
    }

    public function deleteDatabase(string $databaseName): bool
    {
        $args = [
            'name' => $this->addPrefix($databaseName),
        ];

        $response = $this->makeAPIRequest('Mysql', 'delete_database', $args);
        $responseBody = json_decode($response->getBody()->getContents(), true);
        $statusCode = $response->getStatusCode();

        return $this->validateResponse($responseBody);
    }

    public function deleteDatabaseUser(string $username): bool
    {
        $args = [
            'name' => $this->addPrefix($username),
        ];

        $response = $this->makeAPIRequest('Mysql', 'delete_user', $args);
        $responseBody = json_decode($response->getBody()->getContents(), true);
        $statusCode = $response->getStatusCode();

        return $this->validateResponse($responseBody);
    }

    public function removeTenantDatabaseAndUser(string $database, string $username)
    {
        $this->deleteDatabase($database);
        $this->deleteDatabaseUser($username);
    }


    /**
     * Create a new database user
     *
     * @throws RuntimeException
     */
    public function createDatabaseUser(string $username, string $password): bool
    {
        $this->validateDatabaseUser($username, $password);

        $args = [
            'name' => $this->addPrefix($username),
            'password' => $password,
        ];

        $response = $this->makeAPIRequest('Mysql', 'create_user', $args);
        $statusCode = $response->getStatusCode();

        $responseBody = json_decode($response->getBody()->getContents(), true);

        return $this->validateResponse($responseBody);
    }

    /**
     * Assign a user to a database with all permissions
     *
     * @throws RuntimeException
     */
    public function assignUserToDatabase(string $databaseName, string $username): bool
    {
        $this->validateDatabaseAssignment($databaseName, $username);

        $args = [
            'database' => $this->addPrefix($databaseName),
            'user' => $this->addPrefix($username),
            'privileges' => 'ALL',
        ];

        $response = $this->makeAPIRequest('Mysql', 'set_privileges_on_database', $args);
        $responseBody = json_decode($response->getBody()->getContents(), true);
        $statusCode = $response->getStatusCode();

        return $this->validateResponse($responseBody);
    }

    /**
     * Validate database name
     *
     * @throws InvalidArgumentException
     */
    private function validateDatabaseName(string $databaseName): void
    {
        if (empty($databaseName) || strlen($databaseName) > 64) {
            throw new InvalidArgumentException('Invalid database name');
        }
    }

    /**
     * Validate database user
     *
     * @throws InvalidArgumentException
     */
    private function validateDatabaseUser(string $username, string $password): void
    {
        if (empty($username) || strlen($username) > 16) {
            throw new InvalidArgumentException('Invalid database username');
        }

        if (empty($password) || strlen($password) < 8) {
            throw new InvalidArgumentException('Invalid database user password');
        }
    }

    /**
     * Validate database assignment
     *
     * @throws InvalidArgumentException
     */
    private function validateDatabaseAssignment(string $databaseName, string $username): void
    {
        $this->validateDatabaseName($databaseName);
        $this->validateDatabaseUser($username, 'dummy_password');
    }

    public function createDomain(string $domain, string $documentRoot): bool
    {
        if (!settings()->get('cpanel_addon_domain')) return true;

        $this->validateDomain($domain);

        $query = [
            'newdomain' => $domain,
            'subdomain' => $domain . $this->cpanelDomain,
            'dir' => $documentRoot,
        ];
        $response = $this->makeAPIRequest('DomainAddon', 'addaddondomain', $query);

        $statusCode = $response->getStatusCode();
        $responseBody = json_decode($response->getBody()->getContents(), true);

        if ($statusCode !== 200) {
            Log::error('Failed to create domain in CPanel', [
                'domain' => $domain,
                'document_root' => $documentRoot,
                'status_code' => $statusCode,
                'response_body' => $responseBody,
            ]);
            throw new RuntimeException(
                "CPanel API returned non-200 status code: {$statusCode}"
            );
        }

        return $this->validateResponse($responseBody);
    }

    public function runAutoSsl()
    {
        $response = $this->makeAPIRequest('SSL', 'start_autossl_check');

        $statusCode = $response->getStatusCode();
        $responseBody = json_decode($response->getBody()->getContents(), true);

        return $this->validateResponse($responseBody);
    }
}
