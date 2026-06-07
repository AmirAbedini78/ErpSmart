<?php

namespace Modules\Saas\Fields;

use Closure;
use Modules\Core\Fields\Field;
use Modules\Core\Http\Requests\ResourceRequest;
use Modules\Core\Models\Model;
use Modules\Core\Table\Column;
use Modules\Saas\Models\Quota;

class PackageQuotas extends Field
{
    protected static $component = 'package-quotas-field';

    public array $with = ['quotas'];

    public array $quotaOptions = [];

    public function __construct($attribute, $label = null)
    {
        parent::__construct($attribute, $label);

        $this->rules([
            '*.quota_id' => ['required', 'exists:quotas,id'],
            '*.limit' => ['required', 'numeric', 'min:0'],
        ])->prepareForValidation(function ($value) {
            return $this->parsePreValidationValue($value);
        })->fillUsing(function (Model $model, string $attribute, ResourceRequest $request, $value) {
            if (! is_null($value)) {
                $this->fillCallback($value, $model)();
            }

            return null;
        });
    }

    /**
     * Parse the value for validation
     */
    protected function parsePreValidationValue($value): array
    {
        if (is_null($value)) {
            return [];
        }

        return collect($value)
            ->filter(fn ($item) => ! empty($item['quota_id']))
            ->map(function ($item) {
                return [
                    'quota_id' => (int) $item['quota_id'],
                    'limit' => (int) ($item['limit'] ?? 0),
                ];
            })->all();
    }

    /**
     * Get the fill callback for the field
     */
    protected function fillCallback(array $value, Model $model): Closure
    {
        return function () use ($value, $model) {
            $quotaData = collect($value)
                ->reject(fn ($attributes) => empty($attributes['quota_id']))
                ->mapWithKeys(function ($item) {
                    return [(int) $item['quota_id'] => ['limit' => (int) $item['limit']]];
                })
                ->all();

            if ($model->wasRecentlyCreated) {
                $model->quotas()->attach($quotaData);
            } else {
                $model->quotas()->sync($quotaData);
            }
        };
    }

    /**
     * Provide the column used for index
     */
    public function indexColumn(): Column
    {
        return tap(new Column($this->attribute, $this->label), function (Column $column) {
            $column->displayUsing(function ($model) {
                return $model->quotas->map(function ($quota) {
                    return sprintf(
                        '%s: %s',
                        $quota->name,
                        $quota->pivot->limit
                    );
                })->implode(', ');
            });
        });
    }

    /**
     * Resolve the field value
     */
    public function resolve($model): mixed
    {
        if (! $model->relationLoaded('quotas')) {
            $model->load('quotas');
        }

        return $model->quotas->map(function ($quota) {
            return [
                'quota_id' => $quota->id,
                'limit' => (int) $quota->pivot->limit,
            ];
        })->values();
    }

    /**
     * Set the available quota options
     */
    public function options(array $options): static
    {
        $this->quotaOptions = $options;

        return $this;
    }

    /**
     * jsonSerialize
     */
    public function jsonSerialize(): array
    {
        return array_merge(parent::jsonSerialize(), [
            'quotaOptions' => $this->quotaOptions,
        ]);
    }
}
