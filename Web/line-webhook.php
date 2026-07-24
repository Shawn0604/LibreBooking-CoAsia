<?php

define('ROOT_DIR', '../');
require_once(ROOT_DIR . 'Presenters/Line/LineWebhookPresenter.php');

// LINE calls this endpoint anonymously (no PHP session, no CSRF token) — auth is entirely
// via the X-Line-Signature header, an HMAC-SHA256 of the raw body keyed by the channel
// secret. This can't reuse Page::EnforceCSRFCheck(), which compares against a logged-in
// session's token.

if (!Configuration::Instance()->GetKey(ConfigKeys::LINE_ENABLED, new BooleanConverter())) {
    http_response_code(404);
    exit;
}

$rawBody = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_LINE_SIGNATURE'] ?? null;
$channelSecret = Configuration::Instance()->GetKey(ConfigKeys::LINE_CHANNEL_SECRET);

if (!LineSignatureVerifier::Verify($rawBody, $signature, $channelSecret)) {
    Log::Error('LINE webhook: invalid or missing X-Line-Signature');
    http_response_code(400);
    exit;
}

$payload = json_decode($rawBody, true);
$events = $payload['events'] ?? [];

try {
    $presenter = new LineWebhookPresenter(new UserRepository(), ServiceLocator::GetLineService());
    $presenter->HandleEvents($events);
} catch (Exception $ex) {
    Log::Error('LINE webhook: error handling events: %s', $ex);
}

http_response_code(200);
