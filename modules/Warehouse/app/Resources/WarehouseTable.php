<?php

namespace Modules\Warehouse\Resources;

use Modules\Core\Table\Table;

class WarehouseTable extends Table
{
    public bool $withViews = true;
    public bool $withActionsColumn = true;
}
