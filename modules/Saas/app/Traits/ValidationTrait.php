<?php

namespace Modules\Saas\Traits;

use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use RuntimeException;

trait ValidationTrait
{
    /**
     * Validate input parameters
     *
     * @throws InvalidArgumentException
     */
    private function validateInputs(string $subdomain, string $domain, string $documentRoot): void
    {
        if (empty($subdomain) || ! preg_match('/^[a-zA-Z0-9]+([-.][a-zA-Z0-9]+)*$/', $subdomain)) {
            throw new InvalidArgumentException('Invalid subdomain format');
        }

        $this->validateDomain($domain);
    }

    /**
     * Validate domain name
     *
     * @throws InvalidArgumentException
     */
    private function validateDomain(string $domain): void
    {
        if (empty($domain) || ! preg_match('/^(?!:\/\/)([a-zA-Z0-9-_]+\.)*[a-zA-Z0-9][a-zA-Z0-9-_]+\.[a-zA-Z]{2,}$/', $domain)) {
            throw new InvalidArgumentException('Invalid domain format');
        }
    }

    /**
     * Validate document root directory
     *
     * @throws RuntimeException
     */
    private function validateDocumentRoot(string $documentRoot): void
    {
        if (empty($documentRoot) || strlen($documentRoot) > 255) {
            throw new InvalidArgumentException('Invalid document root path');
        }

    }
}
