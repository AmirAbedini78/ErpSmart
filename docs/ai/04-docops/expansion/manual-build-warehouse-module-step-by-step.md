# Manual Build — Warehouse Module Step by Step

## Purpose
This is the human execution guide for building Warehouse MVP manually. After it works, the steps can become an Artisan generator command and later a UI Builder flow.

## Safety rules
- Work on Git branch, not main.
- Keep `Saas` disabled while building this module.
- Keep node stopped unless you need Vite dev.
- Use build mode for smoke tests.
- After each phase, commit or at least inspect `git diff`.

## Starting state checks
Run:

```bash
cd ~/projects/ErpSmart
php -v || true
docker compose exec app php artisan --version
docker compose exec app php artisan module:list
cat modules_statuses.json
```

Expected:
- Laravel artisan works.
- Saas is disabled.
- Existing app loads.

If `public/hot` exists while node is stopped:

```bash
sudo rm -f public/hot
```

---

# Phase 1 — Create module skeleton

Try the native generator first:

```bash
docker compose exec app php artisan module:make Warehouse
```

If the command does not exist, create folders manually:

```bash
mkdir -p modules/Warehouse/{app/Providers,app/Models,app/Policies,app/Resources,app/Http/Resources,database/migrations,database/factories,lang/en,resources/js/views,routes}
```

Check:

```bash
ls modules/Warehouse
cat modules_statuses.json
```

If `Warehouse` is not listed in `modules_statuses.json`, add:

```json
"Warehouse": true
```

Keep JSON valid.

---

# Phase 2 — module.json and provider registration

Create `modules/Warehouse/module.json`:

```json
{
  "name": "Warehouse",
  "alias": "warehouse",
  "description": "Warehouse management module for ErpSmart",
  "keywords": ["warehouse", "inventory", "stock"],
  "priority": 0,
  "providers": [
    "Modules\\Warehouse\\Providers\\WarehouseServiceProvider"
  ],
  "files": []
}
```

Create `modules/Warehouse/composer.json`:

```json
{
  "name": "modules/warehouse",
  "description": "Warehouse module",
  "autoload": {
    "psr-4": {
      "Modules\\Warehouse\\": "app/"
    }
  }
}
```

Create `modules/Warehouse/app/Providers/RouteServiceProvider.php`:

```php
<?php

namespace Modules\Warehouse\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    public function map(): void
    {
        $this->mapApiRoutes();
    }

    protected function mapApiRoutes(): void
    {
        Route::moduleApi('Warehouse', 'routes/api.php');
    }
}
```

Create `modules/Warehouse/app/Providers/WarehouseServiceProvider.php`:

```php
<?php

namespace Modules\Warehouse\Providers;

use Modules\Core\Support\ModuleServiceProvider;

class WarehouseServiceProvider extends ModuleServiceProvider
{
    protected array $resources = [
        \Modules\Warehouse\Resources\Warehouse::class,
    ];

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }

    protected function setup(): void
    {
        // Future: settings defaults, workflow triggers, cards, metrics.
    }

    protected function moduleName(): string
    {
        return 'Warehouse';
    }

    protected function moduleNameLower(): string
    {
        return 'warehouse';
    }
}
```

Validate:

```bash
docker compose exec app composer dump-autoload
docker compose exec app php artisan optimize:clear
docker compose exec app php artisan module:list
```

Expected: Warehouse appears enabled.

---

# Phase 3 — Migration and model

Create migration:

```bash
docker compose exec app php artisan make:migration create_warehouses_table --path=modules/Warehouse/database/migrations
```

Edit migration:

```php
Schema::create('warehouses', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('code')->nullable()->unique();
    $table->text('description')->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

Run:

```bash
docker compose exec app php artisan migrate --force
```

Create model `modules/Warehouse/app/Models/Warehouse.php`:

```php
<?php

namespace Modules\Warehouse\Models;

use Modules\Core\Models\Model;

class Warehouse extends Model
{
    protected $table = 'warehouses';

    protected $fillable = [
        'name',
        'code',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
```

Smoke test:

```bash
docker compose exec app php artisan tinker
```

Inside tinker:

```php
\Modules\Warehouse\Models\Warehouse::create(['name'=>'Main Warehouse','code'=>'MAIN']);
\Modules\Warehouse\Models\Warehouse::count();
exit
```

---

# Phase 4 — Policy

Create `modules/Warehouse/app/Policies/WarehousePolicy.php`:

```php
<?php

namespace Modules\Warehouse\Policies;

use Modules\Users\Models\User;
use Modules\Warehouse\Models\Warehouse;

class WarehousePolicy
{
    public function viewAny(User $user): bool { return true; }
    public function view(User $user, Warehouse $warehouse): bool { return true; }
    public function create(User $user): bool { return true; }
    public function update(User $user, Warehouse $warehouse): bool { return true; }
    public function delete(User $user, Warehouse $warehouse): bool { return true; }
}
```

Note: This policy is permissive for MVP only. Later replace with permission names consistent with Users/Core permission system.

---

# Phase 5 — Resource and table

Create `modules/Warehouse/app/Resources/WarehouseTable.php`:

```php
<?php

namespace Modules\Warehouse\Resources;

use Modules\Core\Table\Table;

class WarehouseTable extends Table
{
    public bool $withViews = true;
    public bool $withActionsColumn = true;
}
```

Create `modules/Warehouse/app/Resources/Warehouse.php`.
Use `modules/Deals/app/Resources/Deal.php` and `modules/Contacts/app/Resources/Company.php` as references, but keep it minimal.

Minimum requirements:

```text
class Warehouse extends Resource implements Tableable, WithResourceRoutes
static $model = Modules\Warehouse\Models\Warehouse::class
static $title = 'name'
static $icon = 'BuildingStorefront' or 'ArchiveBox'
menu() returns MenuItem to /warehouses
fields() returns ID, Text name, Text code, Text description, Boolean is_active if available, CreatedAt, UpdatedAt
table() returns WarehouseTable
```

Because exact Field class signatures can differ, copy method style from existing Resource files. Do not invent field API blindly.

After writing Resource:

```bash
docker compose exec app php artisan optimize:clear
docker compose exec app php artisan route:list | grep -i warehouse
```

Expected: resource routes or custom routes appear. If no routes appear, Resource did not register correctly or `WithResourceRoutes` is missing.

---

# Phase 6 — Frontend registration

Create `modules/Warehouse/resources/js/routes.js`:

```js
import { translate } from '@/Core/i18n'
import WarehousesIndex from './views/WarehousesIndex.vue'
import WarehousesCreate from './views/WarehousesCreate.vue'
import WarehousesView from './views/WarehousesView.vue'

export default [
  {
    path: '/warehouses',
    name: 'warehouse-index',
    component: WarehousesIndex,
    meta: { title: translate('warehouse::warehouse.warehouses') },
  },
  {
    path: '/warehouses/create',
    name: 'create-warehouse',
    component: WarehousesCreate,
    meta: { title: translate('warehouse::warehouse.create') },
  },
  {
    path: '/warehouses/:id',
    name: 'view-warehouse',
    component: WarehousesView,
    meta: { title: translate('warehouse::warehouse.warehouse') },
  },
]
```

Create `modules/Warehouse/resources/js/app.js`:

```js
import routes from './routes'

if (window.Innoclapps) {
  Innoclapps.booting((app, router) => {
    routes.forEach(route => router.addRoute(route))
  })
}
```

Create three minimal Vue views:

```text
modules/Warehouse/resources/js/views/WarehousesIndex.vue
modules/Warehouse/resources/js/views/WarehousesCreate.vue
modules/Warehouse/resources/js/views/WarehousesView.vue
```

Start with simple template text only. After route works, replace with Core resource components.

Important: ensure root `resources/js/app.js` imports the module app file if auto-discovery is not present. Search for existing module imports.

```bash
grep -R "modules/Deals/resources/js/app" -n resources modules | head
```

---

# Phase 7 — Translations

Create `modules/Warehouse/lang/en/warehouse.php`:

```php
<?php

return [
    'warehouse' => 'Warehouse',
    'warehouses' => 'Warehouses',
    'create' => 'Create Warehouse',
    'fields' => [
        'name' => 'Name',
        'code' => 'Code',
        'description' => 'Description',
        'is_active' => 'Active',
    ],
];
```

---

# Phase 8 — Build and browser smoke test

```bash
sudo rm -f public/hot
docker compose run --rm node npm run build
docker compose exec app php artisan optimize:clear
docker compose restart app nginx
```

Browser:

```text
http://localhost:8080/warehouses
```

Check Network:
- no 500
- no requests to `localhost:5173`
- JS loaded from `/build/assets/`

---

# Phase 9 — Update SmartDocs after each result

Update:

```text
docs/ai/02-domains/warehouse.md
docs/ai/04-docops/history/YYYY-MM-DD-warehouse-mvp-progress.md
```

Record:
- files created
- route names
- errors
- fixes
- next phase

## Stop conditions
Stop and ask for review if:
- `artisan --version` fails
- module provider causes boot error
- route list hangs
- migration hangs more than 60 seconds
- frontend build fails
- browser shows blank page with missing JS
