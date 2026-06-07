<?php

namespace Modules\Saas\Fields;

use Modules\Contacts\Http\Resources\ContactResource;
use Modules\Contacts\Models\Contact;
use Modules\Core\Fields\BelongsTo;

class Customer extends BelongsTo
{
    /**
     * Create new instance of Deal field
     *
     * @param  string  $relationName  The relation name, snake case format
     * @param  string  $label  Custom label
     * @param  string  $foreignKey  Custom foreign key
     */
    public function __construct($relationName = 'customer', $label = null, $foreignKey = null)
    {
        parent::__construct($relationName, Contact::class, $label ?? __('saas::saas.fields.tenant.customer'), $foreignKey);

        $this->onOptionClick('float', ['resourceName' => 'contacts'])
            ->setJsonResource(ContactResource::class)
            ->async('/contacts/search');
    }
}
