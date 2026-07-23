<?php

class VideoConferenceInfo
{
    /**
     * @var string
     */
    public $ResourceName;

    /**
     * @var string
     */
    public $Url;

    /**
     * @var string|null
     */
    public $MeetingId;

    /**
     * @var string|null
     */
    public $Passcode;

    /**
     * @param $resourceName string
     * @param $url string
     * @param $meetingId string|null
     * @param $passcode string|null
     */
    public function __construct($resourceName, $url, $meetingId, $passcode)
    {
        $this->ResourceName = $resourceName;
        $this->Url = $url;
        $this->MeetingId = $meetingId;
        $this->Passcode = $passcode;
    }
}
