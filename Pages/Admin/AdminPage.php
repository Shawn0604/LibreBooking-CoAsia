<?php

require_once(ROOT_DIR . 'Pages/ActionPage.php');
require_once(ROOT_DIR . 'Pages/SecurePage.php');

class AdminPageDecorator extends ActionPage implements IActionPage
{
    /**
     * @var ActionPage
     */
    private $page;

    public function __construct(ActionPage $page)
    {
        $this->page = $page;
    }

    public function PageLoad()
    {
        $user = ServiceLocator::GetServer()->GetUserSession();

        if (!$this->page->IsAuthenticated() || !$user->IsAdmin) {
            $this->RedirectResume(sprintf('%s%s?%s=%s', $this->page->path, Pages::LOGIN, QueryStringKeys::REDIRECT, urlencode($this->page->server->GetUrl())));
            die();
        }

        // Same opt-in point as AdminPage::Display() below, for the majority of admin pages
        // that extend plain ActionPage and only ever go through this decorator rather than
        // through AdminPage directly.
        $this->page->Set('IsAdminPage', true);
        $this->page->Set('AdminCurrentScript', basename($_SERVER['SCRIPT_NAME'] ?? ''));
        $this->page->PageLoad();
    }

    public function RedirectResume($url)
    {
        $this->page->RedirectResume($url);
    }

    public function IsValid()
    {
        return $this->page->IsValid();
    }

    public function ProcessAction()
    {
        $this->page->ProcessAction();
    }

    public function ProcessDataRequest($dataRequest)
    {
        $this->page->ProcessDataRequest($dataRequest);
    }

    public function ProcessPageLoad()
    {
        $this->page->ProcessPageLoad();
    }
}

abstract class AdminPage extends SecurePage implements IActionPage
{
    public function __construct($titleKey = '', $pageDepth = 1)
    {
        parent::__construct($titleKey, $pageDepth);

        $user = ServiceLocator::GetServer()->GetUserSession();

        if (!$user->IsAdmin) {
            $this->RedirectResume(sprintf('%s%s?%s=%s', $this->path, Pages::LOGIN, QueryStringKeys::REDIRECT, urlencode($this->server->GetUrl())));
            die();
        }
    }

    /**
     * The framework's generic error page (Pages/Page.php::RedirectToError()) calls
     * $this->Display('error.tpl') polymorphically -- without this override that would hit
     * AdminPage::Display() below and go looking for a nonexistent 'Admin/error.tpl', turning
     * any ordinary uncaught exception on a page that extends AdminPage directly (this one,
     * ServerSettingsPage, DataCleanupPage) into a second, unrelated "template not found"
     * fatal error instead of the app's normal styled error page.
     */
    public function RedirectToError($errorMessageId = ErrorMessages::UNKNOWN_ERROR, $lastPage = '')
    {
        $errorMessageKey = ErrorMessages::Instance()->GetResourceKey($errorMessageId);
        $this->Set('ErrorMessage', $errorMessageKey);
        $this->Set('TitleKey', 'Error');
        parent::Display('error.tpl');
        die();
    }

    public function Display($adminTemplateName)
    {
        // Single opt-in point for every admin page's shared visual system (Web/css/admin.css,
        // loaded by globalheader.tpl only when this is set) -- new admin pages get it for free
        // by simply extending AdminPage, instead of every template remembering to pass its own
        // cssFiles param. AdminCurrentScript drives tpl/Admin/admin-nav.tpl's active-link
        // highlighting -- computed here in PHP (not via a Smarty modifier) because this
        // project's Smarty build doesn't allow arbitrary PHP functions as modifiers, only its
        // own registered ones.
        $this->Set('IsAdminPage', true);
        $this->Set('AdminCurrentScript', basename($_SERVER['SCRIPT_NAME'] ?? ''));
        parent::Display('Admin/' . $adminTemplateName);
    }

    public function TakingAction()
    {
        $action = $this->GetAction();
        return !empty($action);
    }

    public function RequestingData()
    {
        $dataRequest = $this->GetDataRequest();
        return !empty($dataRequest);
    }

    public function GetAction()
    {
        return $this->GetQuerystring(QueryStringKeys::ACTION);
    }

    public function GetDataRequest()
    {
        return $this->GetQuerystring(QueryStringKeys::DATA_REQUEST);
    }

    public function IsValid()
    {
        if (parent::IsValid()) {
            Log::Debug('Action passed all validations');
            return true;
        }

        $errors = new ActionErrors();
        $inlineErrors = [];

        foreach ($this->smarty->failedValidators as $validator) {
            Log::Debug('Failed validator %s', $validator);
            $errors->Add($validator);

            if ($validator->ReturnsErrorResponse()) {
                http_response_code(400);
                $inlineErrors = array_merge($validator->Messages(), $inlineErrors);
            }
        }

        if (!empty($inlineErrors)) {
            $this->SetJson(implode(',', $inlineErrors));
        } else {
            $this->SetJson($errors);
        }
        return false;
    }
}
