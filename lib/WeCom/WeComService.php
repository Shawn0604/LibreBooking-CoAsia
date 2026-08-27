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

            if (Log::DebugEnabled()) {
                Log::Debug('WeCom push to %s. Response code: %s, Response: %s', $wecomUserId, $response->code, json_encode($response->body));
            }

            if ($response->code >= 300) {
                Log::Error('WeCom push failed. Target: %s, Response code: %s, Response: %s', $wecomUserId, $response->code, json_encode($response->body));
            }
        } catch (Exception $exception) {
            Log::Error('WeCom push error. Target: %s, Error: %s', $wecomUserId, $exception);
        }
    }
}

class NullWeComService implements IWeComService
{
    public function Push($wecomUserId, $text)
    {
        // no-op
    }
}
