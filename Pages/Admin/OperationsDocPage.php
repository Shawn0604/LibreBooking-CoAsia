<?php

require_once(ROOT_DIR . 'Pages/Admin/AdminPage.php');

/**
 * "維運文件" -- reads and displays COASIA-DEPLOYMENT.md as-is (raw Markdown in a <pre> block,
 * no Markdown-to-HTML renderer added for this) rather than duplicating its content, so the one
 * actively-maintained deployment/ops doc doesn't fork into two copies that drift apart.
 */
class OperationsDocPage extends AdminPage
{
    private const DOC_PATH = 'COASIA-DEPLOYMENT.md';

    public function __construct()
    {
        parent::__construct('DiagnosticOpsDoc');
    }

    public function PageLoad()
    {
        $path = ROOT_DIR . self::DOC_PATH;
        $content = file_exists($path) ? file_get_contents($path) : '';

        $this->Set('DocContent', $content);
        $this->Display('Configuration/operations_doc.tpl');
    }
}
