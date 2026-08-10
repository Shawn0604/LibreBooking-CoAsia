<?php

require_once(ROOT_DIR . 'Pages/Admin/AdminPage.php');
require_once(ROOT_DIR . 'lib/Application/Admin/namespace.php');
require_once(ROOT_DIR . 'plugins/Authentication/ActiveDirectory/IActiveDirectory.php');
require_once(ROOT_DIR . 'plugins/Authentication/ActiveDirectory/ActiveDirectoryConfigKeys.php');
require_once(ROOT_DIR . 'plugins/Authentication/ActiveDirectory/ActiveDirectoryOptions.php');
require_once(ROOT_DIR . 'plugins/Authentication/ActiveDirectory/AdLdapWrapper.php');
require_once(ROOT_DIR . 'Presenters/Line/LineWebhookPresenter.php');

use PHPMailer\PHPMailer\PHPMailer;

/**
 * "系統診斷" -- status dashboard for the integrations admins actually care about day to day
 * (AD/SMTP/LINE reachability, LINE webhook liveness, required-directory permissions), replacing
 * the old raw debug dump (server time, plugin list, literal filesystem paths). Checks run
 * on-demand via AJAX (dr=checkAd/checkSmtp/checkLine/checkFiles) rather than on every page load,
 * since AD/SMTP/LINE are real network calls that can hang for several seconds when the remote
 * side is down -- blocking the whole page on that would defeat the point of a diagnostics page.
 */
class ServerSettingsPage extends AdminPage
{
    public function __construct()
    {
        parent::__construct('ServerSettings');
    }

    public function PageLoad()
    {
        if ($this->RequestingData()) {
            $this->SetJson($this->HandleCheck($this->GetDataRequest()));
            return;
        }

        $this->Display('Configuration/server_settings.tpl');
    }

    private function HandleCheck($check)
    {
        switch ($check) {
            case 'checkAd':
                return $this->CheckActiveDirectory();
            case 'checkSmtp':
                return $this->CheckSmtp();
            case 'checkLine':
                return $this->CheckLine();
            case 'checkFiles':
                return $this->CheckFilePermissions();
            default:
                return $this->Result('error', '');
        }
    }

    private function CheckActiveDirectory()
    {
        $pluginType = Configuration::Instance()->GetKey(ConfigKeys::PLUGIN_AUTHENTICATION);
        if ($pluginType !== 'ActiveDirectory') {
            return $this->Result('unconfigured', Resources::GetInstance()->GetString('DiagnosticAdNotConfigured'));
        }

        $configPath = ROOT_DIR . 'plugins/Authentication/ActiveDirectory/ActiveDirectory.config.php';
        if (!file_exists($configPath)) {
            return $this->Result('error', Resources::GetInstance()->GetString('DiagnosticAdConfigMissing'));
        }

        try {
            $ad = new AdLdapWrapper(new ActiveDirectoryOptions());
            $ad->Connect();
            return $this->Result('ok');
        } catch (Exception $ex) {
            Log::Error('System diagnostics: AD connectivity check failed. %s', $ex);
            return $this->Result('error', Resources::GetInstance()->GetString('DiagnosticAdConnectionFailed'));
        }
    }

    private function CheckSmtp()
    {
        $config = Configuration::Instance();

        if (!$config->GetKey(ConfigKeys::EMAIL_ENABLED, new BooleanConverter())) {
            return $this->Result('unconfigured', Resources::GetInstance()->GetString('DiagnosticSmtpNotConfigured'));
        }

        if ($config->GetKey(ConfigKeys::PHPMAILER_MAILER) !== 'smtp') {
            return $this->Result('unconfigured', Resources::GetInstance()->GetString('DiagnosticSmtpNotConfigured'));
        }

        $phpMailer = new PHPMailer();
        $phpMailer->isSMTP();
        $phpMailer->Host = $config->GetKey(ConfigKeys::PHPMAILER_SMTP_HOST);
        $phpMailer->Port = $config->GetKey(ConfigKeys::PHPMAILER_SMTP_PORT, new IntConverter());
        $phpMailer->SMTPSecure = $config->GetKey(ConfigKeys::PHPMAILER_SMTP_SECURE);
        $phpMailer->SMTPAutoTLS = $config->GetKey(ConfigKeys::PHPMAILER_SMTP_AUTOTLS, new BooleanConverter());
        $phpMailer->Timeout = 5;

        $connected = false;
        try {
            $connected = $phpMailer->smtpConnect();
        } catch (Exception $ex) {
            Log::Error('System diagnostics: SMTP connectivity check failed. %s', $ex);
        }
        $phpMailer->smtpClose();

        if ($connected) {
            return $this->Result('ok');
        }
        return $this->Result('error', Resources::GetInstance()->GetString('DiagnosticSmtpConnectionFailed'));
    }

    private function CheckLine()
    {
        $config = Configuration::Instance();

        if (!$config->GetKey(ConfigKeys::LINE_ENABLED, new BooleanConverter())) {
            return $this->Result('unconfigured', Resources::GetInstance()->GetString('DiagnosticLineNotConfigured'), $this->GetLineLastWebhook());
        }

        $token = $config->GetKey(ConfigKeys::LINE_CHANNEL_ACCESS_TOKEN);

        try {
            $response = Unirest\Request::get('https://api.line.me/v2/bot/info', ['Authorization' => "Bearer {$token}"]);
            if ($response->code == 200) {
                return $this->Result('ok', '', $this->GetLineLastWebhook());
            }
            return $this->Result('error', Resources::GetInstance()->GetString('DiagnosticLineTokenInvalid'), $this->GetLineLastWebhook());
        } catch (Exception $ex) {
            Log::Error('System diagnostics: LINE connectivity check failed. %s', $ex);
            return $this->Result('error', Resources::GetInstance()->GetString('DiagnosticLineConnectionFailed'), $this->GetLineLastWebhook());
        }
    }

    private function GetLineLastWebhook()
    {
        $markerFile = LineWebhookPresenter::LastSeenMarkerPath();
        if (file_exists($markerFile)) {
            return trim(file_get_contents($markerFile));
        }
        return null;
    }

    private function CheckFilePermissions()
    {
        $checkDirs = [ROOT_DIR . 'tpl_c', (new ImageUploadDirectory())->GetDirectory()];

        foreach ($checkDirs as $dir) {
            if (!is_dir($dir) || !is_writable($dir)) {
                return $this->Result('error', Resources::GetInstance()->GetString('DiagnosticFilesNotWritable'));
            }
        }

        return $this->Result('ok');
    }

    private function Result($status, $message = '', $lastWebhookAt = null)
    {
        $result = [
            'status' => $status,
            'message' => $message,
            'checkedAt' => date('Y-m-d H:i:s'),
        ];
        if ($lastWebhookAt !== null) {
            $result['lastWebhookAt'] = $lastWebhookAt;
        }
        return $result;
    }
}
