<?php

namespace Modules\Saas\Resources;

use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Actions\CloneAction;
use Modules\Core\Actions\DeleteAction;
use Modules\Core\Contracts\Resources\Importable;
use Modules\Core\Contracts\Resources\Tableable;
use Modules\Core\Contracts\Resources\WithResourceRoutes;
use Modules\Core\Facades\Innoclapps;
use Modules\Core\Fields\CreatedAt;
use Modules\Core\Fields\FieldsCollection;
use Modules\Core\Fields\Select;
use Modules\Core\Fields\Text;
use Modules\Core\Fields\Textarea;
use Modules\Core\Fields\UpdatedAt;
use Modules\Core\Http\Requests\ActionRequest;
use Modules\Core\Http\Requests\ResourceRequest;
use Modules\Core\Models\Model;
use Modules\Core\Resource\Import\Import;
use Modules\Core\Resource\Resource;
use Modules\Core\Table\Table;
use Modules\Saas\Cards\TenantStatistics;
use Modules\Saas\Services\ModuleInitializationService;
use Modules\Saas\Transformers\PageResource;

class TenantStat extends Resource implements Importable, Tableable, WithResourceRoutes
{
    public static string $model = 'Modules\Saas\Models\TenantUsage';

    /**
     * The column the records should be default ordered by when retrieving
     */
    public static string $orderBy = 'name';

    /**
     * Indicates whether the resource is globally searchable
     */
    public static bool $globallySearchable = true;

    /**
     * The resource displayable icon.
     */
    public static ?string $icon = 'Bars3CenterLeft';

    /**
     * The attribute to be used when the resource should be displayed.
     */
    public static string $title = 'name';

    public function table(Builder $query, ResourceRequest $request, string $identifier): Table
    {
        if (!ModuleInitializationService::isModuleActive("saas")) {
            abort(403, "Module is inactive. Please activate it via settings/saas.");
        }

        return Table::make($query, $request, $identifier)
            ->withActionsColumn()
            ->withViews()
            ->withDefaultView(name: 'saas::saas.views.pages.all', flag: 'all-pages')
            ->orderBy('name', 'asc');
    }


    /**
     * Get resource available cards
     */
    public function cards(): array
    {
        return [
            (new TenantStatistics())
                ->onlyOnDashboard(),
        ];
    }


    /**
     * Register permissions for the resource
     */
    public function registerPermissions(): void
    {
        $this->registerCommonPermissions();

        Innoclapps::permissions(function ($manager) {
            $manager->group($this->name(), function ($manager) {
                $manager->view('export', [
                    'permissions' => [
                        'export products' => __('core::app.export.export'),
                    ],
                ]);
            });
        });
    }

    public function jsonResource(): string
    {
        return PageResource::class;
    }
}
