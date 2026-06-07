<?php

namespace Modules\Saas\ModuleRepo;

use Modules\Core\Module\Module;

class CustomLaravelFileRepository extends CustomFileRepository
{
    /**
     * {@inheritdoc}
     */
    protected function createModule(...$args)
    {
        return new Module(...$args);
    }
}
