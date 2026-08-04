{* Shared 管理中心 sub-page header -- back link + title/description + optional header actions
   slot. Included at the top of every admin sub-page's `.admin-page` div so all 5 sections
   (預約管理/會議室管理/使用者與權限/開放時間設定/系統設定) share one header instead of each page
   rolling its own `<div class="border-bottom mb-3 clearfix">`. Params:
     TitleKey (required), DescriptionKey (optional) -- lang keys, translated here rather than
     passed as already-translated strings so callers don't need a nested {translate} inside an
     {include} param list. HeaderActions (optional raw HTML -- build with {capture} in the
     calling page so pages keep full control over their own action buttons/dropdowns instead of
     this partial trying to model every possible action). *}

<a class="admin-back-link" href="{$Path}admin/manage_overview.php">
    <i class="bi bi-arrow-left"></i> {translate key=AdminBackToOverview}
</a>

<div class="admin-page-header admin-page-shell-header">
    <div>
        <h1>{translate key=$TitleKey}</h1>
        {if isset($DescriptionKey) && $DescriptionKey}<p class="admin-page-subtitle">{translate key=$DescriptionKey}</p>{/if}
    </div>
    {if isset($HeaderActions) && $HeaderActions}
        <div class="admin-page-header-actions">{$HeaderActions}</div>
    {/if}
</div>

{include file='Admin/admin_confirm_dialog.tpl'}
