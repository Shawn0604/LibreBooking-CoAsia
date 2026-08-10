<?php

require_once(ROOT_DIR . 'Pages/Admin/AdminPage.php');

/**
 * "系統架構圖" -- static admin-facing explainer of how the frontend/backend/database fit
 * together (request flow, MVP layers, external integrations). Content lives directly in the
 * .tpl rather than being generated from README.md, since this is written for an admin
 * audience rather than a developer setting up the repo.
 */
class SystemArchitecturePage extends AdminPage
{
    public function __construct()
    {
        parent::__construct('DiagnosticArchitecture');
    }

    public function PageLoad()
    {
        $this->Display('Configuration/system_architecture.tpl');
    }
}
