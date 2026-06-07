<?php

namespace Modules\Saas\Database\ConnectionDrivers;

use Illuminate\Database\MySqlConnection as BaseMySqlConnection;
use Modules\Saas\Database\CustomQueryBuilder;

class MysqlConnection extends BaseMySqlConnection
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
