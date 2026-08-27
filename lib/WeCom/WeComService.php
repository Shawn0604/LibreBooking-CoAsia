<?php

require_once(ROOT_DIR . 'lib/WeCom/namespace.php');

/**
 * Sends push notifications through the CoAsia internal WeCom gateway
 * (`wecoms.coasia.app`). This is the plaintext API: a form POST of
 * {touser, content}, no auth token. The gateway holds the actual WeCom
 * application credentials — this class only forwards messages to it.
 *
 * Fire-and-forget, exactly like LineService: a gateway failure is logged and
 * swallowed so it never blocks the reservation flow.
 */
class WeComService implements IWeComService
{
    /**
     * @var string
     */
    private $apiUrl;

    public function __construct($apiUrl = null)
    {
        $this->apiUrl = $apiUrl ?? Configuration::Instance()->GetKey(ConfigKeys::WECOM_API_URL);
    }

    public function Push($wecomUserId, $text)
    {
        if (empty($wecomUserId)) {
            return;
        }

        try {
            $body = Unirest\Request\Body::form(['touser' => $wecomUserId, 'content' => $text]);
            $response = Unirest\Request::post($this->apiUrl, [], $body);

            $payload = $this->decodePayload($response->body);
            // The gateway returns HTTP 200 even when WeChat Work rejects the message
            // (unknown touser, member outside the app's visible scope, ...), reporting the
            // failure only in the body: {"success":false,"errcode":81013,...}. A clean send
            // is {"success":true,"errcode":0,"errmsg":"ok","msgid":"..."}.
            $failed = $response->code >= 300
                || (array_key_exists('success', $payload) && !$payload['success'])
                || (array_key_exists('errcode', $payload) && (int) $payload['errcode'] !== 0);

            if ($failed) {
                Log::Error('WeCom push failed. Target: %s, HTTP: %s, Response: %s', $wecomUserId, $response->code, json_encode($response->body));
            } elseif (Log::DebugEnabled()) {
                Log::Debug('WeCom push to %s. HTTP: %s, Response: %s', $wecomUserId, $response->code, json_encode($response->body));
            }
        } catch (Exception $exception) {
            Log::Error('WeCom push error. Target: %s, Error: %s', $wecomUserId, $exception);
        }
    }

    /**
     * Normalise whatever Unirest handed back — a JSON-parsed object, a JSON-parsed array,
     * or a raw string (depends on the response Content-Type) — into an array, so Push()
     * can read `success`/`errcode` regardless. Returns [] when the body isn't JSON.
     *
     * @param mixed $body
     * @return array
     */
    private function decodePayload($body)
    {
        if (is_string($body)) {
            $decoded = json_decode($body, true);
            return is_array($decoded) ? $decoded : [];
        }
        if (is_object($body)) {
            return (array) $body;
        }
        return is_array($body) ? $body : [];
    }
}

class NullWeComService implements IWeComService
{
    public function Push($wecomUserId, $text)
    {
        // no-op
    }
}
