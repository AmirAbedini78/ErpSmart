<?php

namespace Modules\Saas\Settings\Stores;

use Modules\Core\Settings\Stores\DatabaseStore as BaseStore;

class DatabaseStore extends BaseStore
{

    /**
     * Completely refresh settings data from the database.
     *
     * @return $this
     */
    public function refresh(): self
    {
        $this->resetLoaded();

        return $this;
    }
}
