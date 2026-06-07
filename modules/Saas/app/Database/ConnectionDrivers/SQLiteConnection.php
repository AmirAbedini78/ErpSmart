<?php

namespace Modules\Saas\Database\ConnectionDrivers;

use Modules\Saas\Database\CustomQueryBuilder;
use Illuminate\Database\SQLiteConnection as BaseSQLiteConnection;

class SQLiteConnection extends BaseSQLiteConnection
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
