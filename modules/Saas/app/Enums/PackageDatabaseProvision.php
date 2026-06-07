<?php

namespace Modules\Saas\Enums;

enum PackageDatabaseProvision: string
{
    //    case USE_FROM_PACKAGE = 'from_package';
    case USE_CURRENT_ACTIVE = 'current';
    //    case CUSTOM_CREDENTIAL = 'custom';

    public function getlabel(): string
    {
        return __("saas::saas.database.{$this->value}");
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
