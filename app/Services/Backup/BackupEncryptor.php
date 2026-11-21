<?php

declare(strict_types=1);

namespace App\Services\Backup;

use RuntimeException;

/**
 * Encrypts backup archives with AES-256-GCM (spec section 10.8). The key comes from BACKUP_ENCRYPTION_KEY and is
 * never written to disk or to logs. File layout: "ZDB1" | 12-byte nonce | 16-byte tag | ciphertext.
 */
final class BackupEncryptor
{
    private const MAGIC = 'ZDB1';

    private const CIPHER = 'aes-256-gcm';

    private const NONCE_BYTES = 12;

    private const TAG_BYTES = 16;

    public function encrypt(string $plain): string
    {
        $nonce = random_bytes(self::NONCE_BYTES);
        $tag = '';

        $cipher = openssl_encrypt($plain, self::CIPHER, $this->key(), OPENSSL_RAW_DATA, $nonce, $tag, '', self::TAG_BYTES);

        if ($cipher === false) {
            throw new RuntimeException('The backup could not be encrypted.');
        }

        return self::MAGIC.$nonce.$tag.$cipher;
    }

    public function decrypt(string $blob): string
    {
        $header = strlen(self::MAGIC) + self::NONCE_BYTES + self::TAG_BYTES;

        if (! str_starts_with($blob, self::MAGIC) || strlen($blob) < $header) {
            throw new RuntimeException('The file is not a ZeeDiet backup.');
        }

        $offset = strlen(self::MAGIC);
        $nonce = substr($blob, $offset, self::NONCE_BYTES);
        $tag = substr($blob, $offset + self::NONCE_BYTES, self::TAG_BYTES);
        $cipher = substr($blob, $header);

        $plain = openssl_decrypt($cipher, self::CIPHER, $this->key(), OPENSSL_RAW_DATA, $nonce, $tag);

        if ($plain === false) {
            throw new RuntimeException('The backup cannot be opened: the key is wrong or the file is damaged.');
        }

        return $plain;
    }

    private function key(): string
    {
        $configured = (string) config('backup.encryption_key');

        if ($configured === '') {
            throw new RuntimeException('BACKUP_ENCRYPTION_KEY is not set. Backups are not written without it.');
        }

        $raw = str_starts_with($configured, 'base64:')
            ? base64_decode(substr($configured, 7), true)
            : false;

        if ($raw === false || strlen($raw) !== 32) {
            throw new RuntimeException('BACKUP_ENCRYPTION_KEY must be "base64:" followed by 32 random bytes in base64.');
        }

        return $raw;
    }
}
