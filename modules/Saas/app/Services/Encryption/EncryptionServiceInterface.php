<?php

namespace Modules\Saas\Services\Encryption;

interface EncryptionServiceInterface
{
    public function decrypt(string $value): string;

    public function encrypt(string $value): string;

    public function encryptArrayOfStrings(array $array): array;
}
