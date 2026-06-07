<?php

namespace Modules\Saas\Enums;

enum TenantDatabaseProvision: string
{
    case USE_FROM_PACKAGE = 'from_package';
    case USE_CURRENT_ACTIVE = 'current';
    case CREATE_SEPARATE = 'new';
    case CUSTOM_CREDENTIAL = 'custom';
    case TABLE_PREFIX = 'tbl_prefix';

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

    public static function getPackageOptions(): array
    {
        $cases = self::cases();
        $packageOptions = [];
        foreach ($cases as $case) {
            if ($case === self::USE_FROM_PACKAGE) { continue; }
            $packageOptions[] = [
                'label' => $case->getlabel(),
                'value' => $case->value,
            ];
        }

        return $packageOptions;
    }
}
