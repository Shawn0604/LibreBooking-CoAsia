<?php

class LineSignatureVerifier
{
    /**
     * @param string $rawBody
     * @param string|null $signatureHeader
     * @param string $channelSecret
     * @return bool
     */
    public static function Verify($rawBody, $signatureHeader, $channelSecret)
    {
        if (empty($signatureHeader) || empty($channelSecret)) {
            return false;
        }

        $expected = base64_encode(hash_hmac('sha256', $rawBody, $channelSecret, true));
        return hash_equals($expected, $signatureHeader);
    }
}
