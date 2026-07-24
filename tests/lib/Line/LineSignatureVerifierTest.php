<?php

declare(strict_types=1);

require_once(ROOT_DIR . 'lib/Line/LineSignatureVerifier.php');

class LineSignatureVerifierTest extends TestBase
{
    public function testValidSignaturePasses()
    {
        $secret = 'my-channel-secret';
        $body = '{"events":[]}';
        $signature = base64_encode(hash_hmac('sha256', $body, $secret, true));

        $this->assertTrue(LineSignatureVerifier::Verify($body, $signature, $secret));
    }

    public function testWrongSecretFails()
    {
        $body = '{"events":[]}';
        $signature = base64_encode(hash_hmac('sha256', $body, 'right-secret', true));

        $this->assertFalse(LineSignatureVerifier::Verify($body, $signature, 'wrong-secret'));
    }

    public function testTamperedBodyFails()
    {
        $secret = 'my-channel-secret';
        $signature = base64_encode(hash_hmac('sha256', 'original body', $secret, true));

        $this->assertFalse(LineSignatureVerifier::Verify('tampered body', $signature, $secret));
    }

    public function testMissingSignatureFails()
    {
        $this->assertFalse(LineSignatureVerifier::Verify('body', null, 'secret'));
        $this->assertFalse(LineSignatureVerifier::Verify('body', '', 'secret'));
    }

    public function testMissingChannelSecretFails()
    {
        $this->assertFalse(LineSignatureVerifier::Verify('body', 'some-signature', ''));
    }
}
