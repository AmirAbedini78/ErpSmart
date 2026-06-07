<?php

namespace Modules\Saas\Enums;

enum PageStatus: string
{
    //    case USE_FROM_PACKAGE = 'from_package';
    case DRAFT = 'draft';
    case PUBLISHED = 'published';
    case ARCHIVED = 'archived';

    //    case CUSTOM_CREDENTIAL = 'custom';

    public function getlabel(): string
    {
        return __("saas::saas.page.status.{$this->value}");
    }

    public static function getAllValues(): array
    {
        return array_map(fn ($e) => $e->value, self::cases());
    }

    public static function getOptions(): array
    {
        return array_map(fn ($e) => [
            'label' => $e->getlabel(),
            'value' => $e->value,
        ], self::cases());
    }
}
