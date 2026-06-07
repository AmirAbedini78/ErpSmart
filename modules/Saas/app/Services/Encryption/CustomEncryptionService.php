<?php

namespace Modules\Saas\Services\Encryption;

use Exception;
use Modules\Saas\Exceptions\EncryptionException;

class CustomEncryptionService implements EncryptionServiceInterface
{
    /**
     * @throws EncryptionException
     */
    public function encrypt(string $value): string
    {
        try {
            $key = config('saas.encryption_key');
            if (! $key) {
                throw new EncryptionException('Encryption key is not set.');
            }

            $key = substr(hash('sha256', $key, true), 0, SODIUM_CRYPTO_SECRETBOX_KEYBYTES); // Normalize key size
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

            $ciphertext = sodium_crypto_secretbox($value, $nonce, $key);

            return base64_encode($nonce.$ciphertext);
        } catch (EncryptionException $e) {
            throw $e;
        } catch (Exception $e) {
            // Log the exception and rethrow a generic encryption exception
            logger()->error('Encryption failed: '.$e->getMessage());
            throw new EncryptionException('Failed to encrypt the given value.');
        }
    }

    /**
     * @throws EncryptionException
     */
    public function decrypt(string $value): string
    {
        try {
            $key = config('saas.encryption_key');
            if (! $key) {
                throw new EncryptionException('Encryption key is not set.');
            }

            $key = substr(hash('sha256', $key, true), 0, SODIUM_CRYPTO_SECRETBOX_KEYBYTES); // Normalize key size
            $decoded = base64_decode($value, true);

            if ($decoded === false) {
                throw new EncryptionException('Failed to decode the encrypted value.');
            }

            $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $ciphertext = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

            $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $key);

            if ($plaintext === false) {
                throw new EncryptionException('Decryption failed. Invalid key or corrupted data.');
            }

            return $plaintext;
        } catch (Exception $e) {
            // Log the exception and rethrow a generic decryption exception
            logger()->error('Decryption failed: '.$e->getMessage());
            throw new EncryptionException('Failed to decrypt the given value.');
        }
    }

    public function encryptArrayOfStrings(array $array): array
    {
        return array_map(fn ($value) => $this->encrypt($value), $array);
    }
}
