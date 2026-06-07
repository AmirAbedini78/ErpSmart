<?php

namespace Modules\Saas\Database\ConnectionDrivers;

use Modules\Saas\Database\CustomQueryBuilder;
use Illuminate\Database\SqlServerConnection as BaseSqlServerConnection;

class SqlServerConnection extends BaseSqlServerConnection
{
    public function query(): CustomQueryBuilder
    {
        return new CustomQueryBuilder(
            $this,
            $this->getQueryGrammar(),
            $this->getPostProcessor()
        );
    }
}
