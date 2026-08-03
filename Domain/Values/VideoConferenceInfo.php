<?php

class VideoConferenceInfo
{
    /**
     * @var int
     */
    public $ResourceId;

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
     * Shared host-login credentials -- only ever populated when the caller explicitly asked
     * for them (ReservationEmailTemplateContext::VideoConferenceResources($includeHostCredentials
     * = true), which only the reservation-owner-facing email/LINE code paths ever pass as
     * true). Null for every participant/guest-facing render, by construction rather than by a
     * template-level permission check -- there's nothing to accidentally leak because the
     * value was never fetched in the first place.
     * @var string|null
     */
    public $HostAccount;

    /**
     * @var string|null
     */
    public $HostPassword;

    /**
     * @param $resourceId int
     * @param $resourceName string
     * @param $url string
     * @param $meetingId string|null
     * @param $passcode string|null
     * @param $hostAccount string|null
     * @param $hostPassword string|null
     */
    public function __construct($resourceId, $resourceName, $url, $meetingId, $passcode, $hostAccount = null, $hostPassword = null)
    {
        $this->ResourceId = $resourceId;
        $this->ResourceName = $resourceName;
        $this->Url = $url;
        $this->MeetingId = $meetingId;
        $this->Passcode = $passcode;
        $this->HostAccount = $hostAccount;
        $this->HostPassword = $hostPassword;
    }
}
