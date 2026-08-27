<?php

interface IWeComService
{
    /**
     * Push a plain-text message to a specific WeCom (企業微信) user via the CoAsia gateway.
     * @param string $wecomUserId the recipient's WeCom user id (touser)
     * @param string $text message body
     */
    public function Push($wecomUserId, $text);
}
