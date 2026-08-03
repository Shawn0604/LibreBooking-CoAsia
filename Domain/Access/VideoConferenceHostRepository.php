<?php

require_once(ROOT_DIR . 'lib/Application/Resources/VideoConferenceCredentialEncryption.php');

/**
 * Value object for one resource's video conference host login -- HostPassword here is always
 * the DECRYPTED plaintext (decryption happens once, in GetByResourceId()); nothing in this
 * class or its caller chain re-encrypts or logs it. Keep it out of __toString()/var_dump-style
 * debugging paths if this class ever grows one.
 */
class VideoConferenceHostCredentials
{
    public function __construct(
        public readonly int $ResourceId,
        public readonly string $HostAccount,
        public readonly string $HostPassword
    ) {
    }
}

interface IVideoConferenceHostRepository
{
    public function GetByResourceId(int $resourceId): ?VideoConferenceHostCredentials;

    public function Save(int $resourceId, string $hostAccount, string $hostPasswordPlaintext): void;
}

class VideoConferenceHostRepository implements IVideoConferenceHostRepository
{
    public function GetByResourceId(int $resourceId): ?VideoConferenceHostCredentials
    {
        $reader = ServiceLocator::GetDatabase()->Query(new GetVideoConferenceHostCommand($resourceId));
        $row = $reader->GetRow();
        $reader->Free();

        if (empty($row)) {
            return null;
        }

        return new VideoConferenceHostCredentials(
            $resourceId,
            $row['host_account'],
            VideoConferenceCredentialEncryption::Decrypt($row['host_password_encrypted'])
        );
    }

    /**
     * Upsert -- Jobs/import-video-conference-resources.php is the only current caller, and it
     * always re-imports the full set from the source-of-truth INI file, so "insert or replace
     * whatever's there" is the correct semantics rather than tracking create-vs-update.
     */
    public function Save(int $resourceId, string $hostAccount, string $hostPasswordPlaintext): void
    {
        $encrypted = VideoConferenceCredentialEncryption::Encrypt($hostPasswordPlaintext);
        ServiceLocator::GetDatabase()->Execute(new SaveVideoConferenceHostCommand($resourceId, $hostAccount, $encrypted));
    }
}

class GetVideoConferenceHostCommand extends SqlCommand
{
    public function __construct($resourceId)
    {
        parent::__construct(
            'SELECT `host_account`, `host_password_encrypted`
             FROM `resource_video_conference_host`
             WHERE `resource_id` = @resourceid'
        );
        $this->AddParameter(new Parameter('@resourceid', $resourceId));
    }
}

class SaveVideoConferenceHostCommand extends SqlCommand
{
    public function __construct($resourceId, $hostAccount, $hostPasswordEncrypted)
    {
        parent::__construct(
            'INSERT INTO `resource_video_conference_host`
                (`resource_id`, `host_account`, `host_password_encrypted`, `date_created`, `last_modified`)
             VALUES (@resourceid, @hostaccount, @hostpassword, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                `host_account` = @hostaccount,
                `host_password_encrypted` = @hostpassword,
                `last_modified` = NOW()'
        );
        $this->AddParameter(new Parameter('@resourceid', $resourceId));
        $this->AddParameter(new Parameter('@hostaccount', $hostAccount));
        $this->AddParameter(new Parameter('@hostpassword', $hostPasswordEncrypted));
    }
}
