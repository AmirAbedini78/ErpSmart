<?php

namespace Modules\Saas\Database\ConnectionDrivers;

use Modules\Saas\Database\CustomQueryBuilder;
use Illuminate\Database\PostgresConnection as BasePostgresConnection;

class PostgresConnection extends BasePostgresConnection
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
