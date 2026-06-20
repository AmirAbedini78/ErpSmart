<?php

namespace Modules\Warehouse\Resources;

use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Contracts\Resources\AcceptsCustomFields;
use Modules\Core\Contracts\Resources\AcceptsUniqueCustomFields;
use Modules\Core\Contracts\Resources\Exportable;
use Modules\Core\Contracts\Resources\Importable;
use Modules\Core\Contracts\Resources\Tableable;
use Modules\Core\Contracts\Resources\WithResourceRoutes;
use Modules\Core\Facades\Fields;
use Modules\Core\Fields\Boolean;
use Modules\Core\Fields\CreatedAt;
use Modules\Core\Fields\ID;
use Modules\Core\Fields\Text;
use Modules\Core\Fields\UpdatedAt;
use Modules\Core\Filters\CreatedAt as CreatedAtFilter;
use Modules\Core\Filters\Text as TextFilter;
use Modules\Core\Filters\UpdatedAt as UpdatedAtFilter;
use Modules\Core\Http\Requests\ResourceRequest;
use Modules\Core\Menu\MenuItem;
use Modules\Core\Resource\Resource;
use Modules\Core\Rules\StringRule;
use Modules\Core\Table\Column;
use Modules\Core\Table\Table;
use Modules\Warehouse\Models\Warehouse as WarehouseModel;

class Warehouse extends Resource implements AcceptsCustomFields, AcceptsUniqueCustomFields, Exportable, Importable, Tableable, WithResourceRoutes
{
    public static string $orderBy = 'name';

    public static bool $hasDetailView = true;

    public static bool $globallySearchable = true;

    public static string $globalSearchAction = 'float';

    public static ?string $icon = 'ArchiveBox';

    public static string $model = WarehouseModel::class;

    public static string $title = 'name';

    public function menu(): array
    {
        return [
            MenuItem::make(static::label(), '/warehouses')
                ->icon(static::$icon)
                ->position(30)
                ->inQuickCreate()
                ->keyboardShortcutChar('W')
                ->singularName(static::singularLabel()),
        ];
    }

    public function associateableName(): string
    {
        return 'warehouses';
    }

    public function table(Builder $query, ResourceRequest $request, string $identifier): Table
    {
        return WarehouseTable::make($query, $request, $identifier)
            ->withDefaultView(
                name: 'warehouse::warehouse.warehouses',
                flag: 'all-warehouses',
            )
            ->orderBy('created_at', 'desc');
    }

    public function fields(ResourceRequest $request): array
    {
        return [
            ID::make()->hidden(),

            Text::make('name', __('warehouse::warehouse.fields.name'))
                ->primary()
                ->tapIndexColumn(fn (Column $column) => $column
                    ->width('300px')
                    ->minWidth('200px')
                    ->primary()
                    ->route('/warehouses/{id}')
                )
                ->rules(StringRule::make())
                ->creationRules('required')
                ->updateRules('filled')
                ->required(true),

            Text::make('code', __('warehouse::warehouse.fields.code'))
                ->rules(['nullable', StringRule::make()])
                ->creationRules('nullable', 'unique:warehouses,code')
                ->updateRules('nullable', 'unique:warehouses,code,{{resourceId}}')
                ->hideFromDetail()
                ->excludeFromSettings(Fields::DETAIL_VIEW),

            Text::make('description', __('warehouse::warehouse.fields.description'))
                ->rules(['nullable', StringRule::make()])
                ->hideFromIndex(),

            Boolean::make('is_active', __('warehouse::warehouse.fields.is_active'))
                ->rules(['nullable', 'boolean'])
                ->creationRules('nullable', 'boolean')
                ->updateRules('nullable', 'boolean'),

            CreatedAt::make()->hidden(),

            UpdatedAt::make()->hidden(),
        ];
    }

    public function filters(ResourceRequest $request): array
    {
        return [
            TextFilter::make('name', __('warehouse::warehouse.fields.name'))->withoutNullOperators(),
            TextFilter::make('code', __('warehouse::warehouse.fields.code')),
            CreatedAtFilter::make()->inQuickFilter(),
            UpdatedAtFilter::make(),
        ];
    }

    public function globalSearchQuery(ResourceRequest $request): Builder
    {
        return parent::globalSearchQuery($request)->select(['id', 'name', 'code', 'created_at']);
    }

    public static function label(): string
    {
        return __('warehouse::warehouse.warehouses');
    }

    public static function singularLabel(): string
    {
        return __('warehouse::warehouse.warehouse');
    }

    public function registerPermissions(): void
    {
        $this->registerCommonPermissions();
    }
}

