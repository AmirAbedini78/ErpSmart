<?php

namespace Modules\Saas\Services\Subdomain;

interface SubdomainServiceInterface
{
    public function createSubdomain(string $subdomain, string $domain, string $documentRoot): bool;
}
