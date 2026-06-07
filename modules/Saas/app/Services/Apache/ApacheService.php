<?php

namespace Modules\Saas\Services\Apache;

use Modules\Saas\Services\Domain\DomainServiceInterface;
use Modules\Saas\Services\Subdomain\SubdomainServiceInterface;
use Modules\Saas\Traits\ValidationTrait;

class ApacheService implements DomainServiceInterface, SubdomainServiceInterface
{
    public function createSubdomain(string $subdomain, string $domain, string $documentRoot): bool
    {
       return true;
    }

    public function createDomain(string $domain, string $documentRoot): bool
    {
        return true;
    }

}
