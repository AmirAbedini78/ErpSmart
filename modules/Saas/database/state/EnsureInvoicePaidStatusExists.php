<?php

namespace Modules\Saas\Database\State;

use Modules\Deals\Models\Stage;
use Modules\Deals\Models\Pipeline;

class EnsureInvoicePaidStatusExists
{
    public function __invoke(): void
    {
        if ($this->present()) {
            return;
        }

        $pipeline = Pipeline::where('name', 'Sales Pipeline')->first();
        $latestStage = Stage::orderBy('display_order', 'desc')->first();

        Stage::create(
            [
                'name' => 'Invoice Paid',
                'pipeline_id' => $pipeline->id,
                'display_order' => $latestStage->display_order + 1
            ]
        );
    }

    private function present(): bool
    {
        return Stage::where('name', 'Invoice Paid')->count() > 0;
    }
}
