<?php

namespace Modules\Saas\Services\Subdomain;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use InvalidArgumentException;
use Modules\Saas\Services\Domain\DomainServiceInterface;
use Modules\Saas\Traits\ValidationTrait;
use RuntimeException;

class NginxService implements DomainServiceInterface, SubdomainServiceInterface
{
    use ValidationTrait;

    /**
     * Create a new Nginx subdomain configuration
     *
     * @param  string  $subdomain  The subdomain name
     * @param  string  $domain  The main domain name
     * @param  string  $documentRoot  The document root path
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     * @throws FileNotFoundException
     */
    public function createSubdomain(string $subdomain, string $domain, string $documentRoot): bool
    {
        // Validate inputs
        $this->validateInputs($subdomain, $domain, $documentRoot);

        // Prepare paths
        $vhostConfigPath = config('saas.nginx.sites_available_path', '/etc/nginx/sites-available')
            ."/{$subdomain}.{$domain}";
        $enabledPath = config('saas.nginx.sites_enabled_path', '/etc/nginx/sites-enabled')
            ."/{$subdomain}.{$domain}";

        try {
            // Check if configuration already exists
            if (File::exists($vhostConfigPath)) {
                Log::warning('Virtual host configuration already exists', [
                    'path' => $vhostConfigPath,
                    'subdomain' => $subdomain,
                ]);
                throw new RuntimeException("Virtual host configuration already exists at {$vhostConfigPath}");
            }

            // Check if document root exists and is writable
            $this->validateDocumentRoot($documentRoot);

            // Generate and write configuration
            $vhostConfig = $this->generateVhostConfig($subdomain.'.'.$domain, $documentRoot);

            // Create the virtual host configuration file
            File::put($vhostConfigPath, $vhostConfig);

            // Ensure the configuration file is readable
            chmod($vhostConfigPath, 0644);

            // Create symlink if it doesn't exist
            if (! File::exists($enabledPath)) {
                if (! symlink($vhostConfigPath, $enabledPath)) {
                    throw new RuntimeException("Failed to create symlink from {$vhostConfigPath} to {$enabledPath}");
                }
            }

            // Test and reload Nginx
            $this->testNginxConfig();
            $this->reloadNginx();

            Log::info('Successfully created subdomain', [
                'subdomain' => $subdomain,
                'domain' => $domain,
                'document_root' => $documentRoot,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to create subdomain', [
                'subdomain' => $subdomain,
                'domain' => $domain,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Clean up any created files if there's an error
            $this->cleanup($vhostConfigPath, $enabledPath);

            throw $e;
        }
    }

    public function createDomain(string $domain, string $documentRoot): bool
    {
        // Validate inputs
        $this->validateDomain($domain);
        $this->validateDocumentRoot($documentRoot);

        // Prepare paths
        $vhostConfigPath = config('saas.nginx.sites_available_path', '/etc/nginx/sites-available')
            ."/{$domain}";
        $enabledPath = config('saas.nginx.sites_enabled_path', '/etc/nginx/sites-enabled')
            ."/{$domain}";

        try {
            // Check if configuration already exists
            if (File::exists($vhostConfigPath)) {
                Log::warning('Virtual host configuration already exists', [
                    'path' => $vhostConfigPath,
                    'subdomain' => $domain,
                ]);
                throw new RuntimeException("Virtual host configuration already exists at {$vhostConfigPath}");
            }

            // Check if document root exists and is writable
            $this->validateDocumentRoot($documentRoot);

            // Generate and write configuration
            $vhostConfig = $this->generateVhostConfig($domain, $documentRoot);

            // Create the virtual host configuration file
            File::put($vhostConfigPath, $vhostConfig);

            // Ensure the configuration file is readable
            chmod($vhostConfigPath, 0644);

            // Create symlink if it doesn't exist
            if (! File::exists($enabledPath)) {
                if (! symlink($vhostConfigPath, $enabledPath)) {
                    throw new RuntimeException("Failed to create symlink from {$vhostConfigPath} to {$enabledPath}");
                }
            }

            // Test and reload Nginx
            $this->testNginxConfig();
            $this->reloadNginx();

            Log::info('Successfully created subdomain', [
                'domain' => $domain,
                'document_root' => $documentRoot,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to create subdomain', [
                'domain' => $domain,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Clean up any created files if there's an error
            $this->cleanup($vhostConfigPath, $enabledPath);

            throw $e;
        }
    }

    /**
     * Generate virtual host configuration
     */
    private function generateVhostConfig(string $host, string $documentRoot): string
    {
        $phpVersion = phpversion();
        $majorMinorVersion = explode('.', $phpVersion, 2)[0].'.'.explode('.', $phpVersion, 2)[1];
        $fastcgiPass = config('saas.nginx.fastcgi_pass', "unix:/var/run/php/php{$majorMinorVersion}-fpm.sock");

        return "server {
    listen 80;
    listen [::]:80;
    server_name {$host};
    root {$documentRoot};

    add_header X-Frame-Options \"SAMEORIGIN\";
    add_header X-Content-Type-Options \"nosniff\";

    index index.php;

    charset utf-8;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php\$ {
        fastcgi_pass {$fastcgiPass};
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}";
    }

    /**
     * Test Nginx configuration
     *
     * @throws RuntimeException
     */
    private function testNginxConfig(): void
    {
        $result = Process::run('sudo nginx -t');

        if (! $result->successful()) {
            throw new RuntimeException('Nginx configuration test failed: '.$result->errorOutput());
        }
    }

    /**
     * Reload Nginx configuration
     *
     * @throws RuntimeException
     */
    private function reloadNginx(): void
    {
        $result = Process::run('sudo systemctl reload nginx');

        if (! $result->successful()) {
            throw new RuntimeException('Failed to reload Nginx: '.$result->errorOutput());
        }
    }

    /**
     * Clean up created files in case of error
     */
    private function cleanup(string $configPath, string $enabledPath): void
    {
        try {
            if (File::exists($enabledPath)) {
                File::delete($enabledPath);
            }

            if (File::exists($configPath)) {
                File::delete($configPath);
            }
        } catch (\Exception $e) {
            Log::error('Failed to cleanup subdomain files', [
                'error' => $e->getMessage(),
                'config_path' => $configPath,
                'enabled_path' => $enabledPath,
            ]);
        }
    }
}
