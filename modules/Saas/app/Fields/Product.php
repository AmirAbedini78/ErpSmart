<?php

namespace Modules\Saas\Fields;

use Modules\Billable\Http\Resources\ProductResource;
use Modules\Billable\Models\Product as ModelsProduct;
use Modules\Core\Fields\BelongsTo;

class Product extends BelongsTo
{
    /**
     * Create new instance of Product field
     *
     * @param  string  $relationName  The relation name, snake case format
     * @param  string  $label  Custom label
     * @param  string  $foreignKey  Custom foreign key
     */
    public function __construct($relationName = 'product', $label = null, $foreignKey = null)
    {
        parent::__construct($relationName, ModelsProduct::class, $label ?? __('products::product.product'), $foreignKey);

        $this->setJsonResource(ProductResource::class)
            ->async('/products/search');
    }
}
