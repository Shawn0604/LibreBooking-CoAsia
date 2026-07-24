<?php

require_once(ROOT_DIR . 'lib/Line/namespace.php');

class LineService implements ILineService
{
    private const PUSH_URL = 'https://api.line.me/v2/bot/message/push';
    private const REPLY_URL = 'https://api.line.me/v2/bot/message/reply';

    /**
     * @var string
     */
    private $channelAccessToken;

    public function __construct($channelAccessToken = null)
    {
        $this->channelAccessToken = $channelAccessToken ?? Configuration::Instance()->GetKey(ConfigKeys::LINE_CHANNEL_ACCESS_TOKEN);
    }

    public function Push($lineUserId, $text)
    {
        $this->send(self::PUSH_URL, ['to' => $lineUserId, 'messages' => [['type' => 'text', 'text' => $text]]], 'push', $lineUserId);
    }

    public function Reply($replyToken, $text)
    {
        $this->send(self::REPLY_URL, ['replyToken' => $replyToken, 'messages' => [['type' => 'text', 'text' => $text]]], 'reply', $replyToken);
    }

    private function send($url, $data, $action, $target)
    {
        try {
            $headers = ['Content-Type' => 'application/json', 'Authorization' => "Bearer {$this->channelAccessToken}"];
            $body = Unirest\Request\Body::json($data);
            $response = Unirest\Request::post($url, $headers, $body);

            if (Log::DebugEnabled()) {
                Log::Debug('LINE %s to %s. Response code: %s, Response: %s', $action, $target, $response->code, json_encode($response->body));
            }

            if ($response->code >= 300) {
                Log::Error('LINE %s failed. Target: %s, Response code: %s, Response: %s', $action, $target, $response->code, json_encode($response->body));
            }
        } catch (Exception $exception) {
            Log::Error('LINE %s error. Target: %s, Error: %s', $action, $target, $exception);
        }
    }
}

class NullLineService implements ILineService
{
    public function Push($lineUserId, $text)
    {
        // no-op
    }

    public function Reply($replyToken, $text)
    {
        // no-op
    }
}
