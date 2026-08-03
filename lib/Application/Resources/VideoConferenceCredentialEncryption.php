<?php

/**
 * Reversible encryption for video conference host account passwords at rest
 * (resource_video_conference_host.host_password_encrypted) -- the reservation owner needs to
 * actually read the real password back (unlike a login password, which only ever needs a
 * one-way hash for comparison), so this uses AES-256-CBC with a random IV per value rather
 * than PasswordEncryption.php's hashing.
 *
 * Storage format: base64(iv . ciphertext) -- the IV isn't secret, it just needs to be unique
 * per encryption and available again at decrypt time, so it travels alongside the ciphertext
 * rather than in a separate column.
 */
class VideoConferenceCredentialEncryption
{
    private const CIPHER = 'aes-256-cbc';

    public static function Encrypt(string $plaintext): string
    {
        $key = self::GetKey();
        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        $iv = openssl_random_pseudo_bytes($ivLength);
        $ciphertext = openssl_encrypt($plaintext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);
        if ($ciphertext === false) {
            throw new Exception('Failed to encrypt video conference host credential');
        }
        return base64_encode($iv . $ciphertext);
    }

    public static function Decrypt(string $encoded): string
    {
        $key = self::GetKey();
        $raw = base64_decode($encoded, true);
        if ($raw === false) {
            throw new Exception('Malformed encrypted video conference host credential');
        }
        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        $iv = substr($raw, 0, $ivLength);
        $ciphertext = substr($raw, $ivLength);
        $plaintext = openssl_decrypt($ciphertext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);
        if ($plaintext === false) {
            throw new Exception('Failed to decrypt video conference host credential -- wrong key or corrupted value');
        }
        return $plaintext;
    }

    /**
     * AES-256 needs exactly 32 raw bytes -- hashing the configured key (whatever length an
     * admin happened to paste/generate) into a fixed-length digest avoids a confusing
     * openssl_encrypt() failure if the config value isn't already exactly 32 bytes.
     */
    private static function GetKey(): string
    {
        $configured = Configuration::Instance()->GetKey(ConfigKeys::VIDEO_CONFERENCE_HOST_ENCRYPTION_KEY);
        if (empty($configured)) {
            throw new Exception('video.conference.host.encryption.key is not configured -- cannot encrypt/decrypt video conference host credentials');
        }
        return hash('sha256', $configured, true);
    }
}
