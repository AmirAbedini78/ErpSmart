<?php

namespace Modules\Saas\Services\Domain;

use InvalidArgumentException;
use RuntimeException;

interface DomainServiceInterface
{
    /**
     * Create a new Domain
     *
     * @param  string  $domain  The domain name
     * @param  string  $documentRoot  The document root path
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function createDomain(string $domain, string $documentRoot): bool;
}
