{include file='globalheader.tpl'}

<div id="system-architecture-page" class="admin-page">
	{include file='Admin/admin_page_shell.tpl' TitleKey='DiagnosticArchitecture' DescriptionKey='DiagnosticArchitectureDescription'}

	<div class="card shadow-sm mb-4">
		<div class="card-body">
			<p>這套系統是傳統的 PHP 單體應用（沒有前後端分離的獨立 API 專案），沒有使用 Laravel/Symfony 之類的現代框架，而是自製的 MVP（Model-View-Presenter）架構：</p>
			<ul>
				<li><strong>Pages</strong>（<code>Pages/</code>）— 每個網址對應一個實體 PHP 檔（<code>Web/*.php</code>），負責讀取表單/網址參數，本身不含業務邏輯</li>
				<li><strong>Presenters</strong>（<code>Presenters/</code>）— 實際的業務邏輯，呼叫 Repository 讀寫資料，組出要顯示的內容</li>
				<li><strong>Domain/Access Repository</strong>（<code>Domain/Access/</code>）— 資料存取層，組 SQL 查詢，透過 <code>lib/Database</code> 執行</li>
				<li><strong>Smarty 樣板</strong>（<code>tpl/</code>）— 伺服器端渲染 HTML，回傳給瀏覽器</li>
			</ul>
			<p>會議室/資源相關的自訂欄位（例如 Zoom 連結、會議 ID）就是靠這條路徑存取：管理介面的每一次修改，都是「瀏覽器 → Page → Presenter → Repository → MySQL」這樣一路往下寫入。</p>
		</div>
	</div>

	<div class="card shadow-sm mb-4">
		<div class="card-body">
			<h2 class="fs-5 mb-3">請求流程圖</h2>
			<svg viewBox="0 0 920 640" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="系統請求流程圖：瀏覽器透過三種入口（整頁表單、AJAX局部更新、REST API）進入 Pages 層，經過 Presenters、Domain/Access Repository 存取 MySQL，並在 Presenters 層與 AD/LDAP、SMTP、LINE 等外部系統整合，最後由 Smarty 樣板把結果渲染回瀏覽器。" style="width:100%; height:auto; font-family: sans-serif;">
				<defs>
					<marker id="arrow" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse">
						<path d="M0,0 L10,5 L0,10 z" fill="#5f5e5a"></path>
					</marker>
				</defs>

				<rect x="360" y="10" width="200" height="46" rx="8" fill="#e6f1fb" stroke="#378add"></rect>
				<text x="460" y="38" text-anchor="middle" font-size="15" fill="#0c447c">瀏覽器 / 使用者</text>

				<rect x="20" y="100" width="260" height="60" rx="8" fill="#eeedfe" stroke="#7f77dd"></rect>
				<text x="150" y="124" text-anchor="middle" font-size="14" fill="#26215c">Web/*.php</text>
				<text x="150" y="144" text-anchor="middle" font-size="11.5" fill="#3c3489">整頁表單提交 / 頁面導覽</text>

				<rect x="330" y="100" width="260" height="60" rx="8" fill="#eeedfe" stroke="#7f77dd"></rect>
				<text x="460" y="124" text-anchor="middle" font-size="14" fill="#26215c">Web/ajax/*.php</text>
				<text x="460" y="144" text-anchor="middle" font-size="11.5" fill="#3c3489">jQuery AJAX 局部更新</text>

				<rect x="640" y="100" width="260" height="60" rx="8" fill="#eeedfe" stroke="#7f77dd"></rect>
				<text x="770" y="124" text-anchor="middle" font-size="14" fill="#26215c">Web/Services (REST API)</text>
				<text x="770" y="144" text-anchor="middle" font-size="11.5" fill="#3c3489">Slim 路由，給行動端/第三方</text>

				<rect x="310" y="210" width="300" height="50" rx="8" fill="#e1f5ee" stroke="#1d9e75"></rect>
				<text x="460" y="240" text-anchor="middle" font-size="14" fill="#04342c">Pages（Pages/*.php）</text>

				<rect x="310" y="300" width="300" height="50" rx="8" fill="#e1f5ee" stroke="#1d9e75"></rect>
				<text x="460" y="330" text-anchor="middle" font-size="14" fill="#04342c">Presenters（Presenters/*.php）</text>

				<rect x="140" y="400" width="300" height="60" rx="8" fill="#faece7" stroke="#d85a30"></rect>
				<text x="290" y="424" text-anchor="middle" font-size="14" fill="#4a1b0c">Domain/Access Repository</text>
				<text x="290" y="444" text-anchor="middle" font-size="11.5" fill="#993c1d">組 SQL 查詢，透過 lib/Database 執行</text>

				<rect x="500" y="400" width="300" height="60" rx="8" fill="#fbeaf0" stroke="#d4537e"></rect>
				<text x="650" y="424" text-anchor="middle" font-size="14" fill="#4b1528">外部整合</text>
				<text x="650" y="444" text-anchor="middle" font-size="11.5" fill="#993556">AD/LDAP、SMTP、LINE API</text>

				<rect x="190" y="500" width="200" height="50" rx="8" fill="#faece7" stroke="#d85a30"></rect>
				<text x="290" y="530" text-anchor="middle" font-size="14" fill="#4a1b0c">MySQL</text>

				<rect x="330" y="580" width="260" height="50" rx="8" fill="#e6f1fb" stroke="#378add"></rect>
				<text x="460" y="605" text-anchor="middle" font-size="13" fill="#0c447c">Smarty 樣板渲染 HTML 回瀏覽器</text>

				<path d="M460,56 L150,100" fill="none" stroke="#5f5e5a" marker-end="url(#arrow)"></path>
				<path d="M460,56 L460,100" fill="none" stroke="#5f5e5a" marker-end="url(#arrow)"></path>
				<path d="M460,56 L770,100" fill="none" stroke="#5f5e5a" marker-end="url(#arrow)"></path>

				<path d="M150,160 L400,210" fill="none" stroke="#5f5e5a" marker-end="url(#arrow)"></path>
				<path d="M460,160 L460,210" fill="none" stroke="#5f5e5a" marker-end="url(#arrow)"></path>
				<path d="M770,160 L520,210" fill="none" stroke="#5f5e5a" marker-end="url(#arrow)"></path>

				<path d="M460,260 L460,300" fill="none" stroke="#5f5e5a" marker-end="url(#arrow)"></path>

				<path d="M400,350 L290,400" fill="none" stroke="#5f5e5a" marker-end="url(#arrow)"></path>
				<path d="M520,350 L650,400" fill="none" stroke="#5f5e5a" marker-end="url(#arrow)"></path>

				<path d="M290,460 L290,500" fill="none" stroke="#5f5e5a" marker-end="url(#arrow)"></path>

				<path d="M400,330 C 500,420 500,520 460,580" fill="none" stroke="#888780" stroke-dasharray="4 3" marker-end="url(#arrow)"></path>
			</svg>
			<p class="text-muted small mt-2">虛線：Presenters 把資料交回 Pages，由 Smarty 渲染成 HTML 回傳（AJAX/REST API 則是直接回傳 JSON，不經過這一步）。</p>
		</div>
	</div>

	<div class="card shadow-sm">
		<div class="card-body">
			<h2 class="fs-5 mb-2">資料庫層</h2>
			<p class="mb-1">只支援 MySQL/MariaDB，沒有 ORM，SQL 查詢集中定義在 <code>lib/Database/Commands/Queries.php</code>，透過 <code>lib/Database/Database.php</code> 執行。Schema 版本控制放在 <code>database_schema/upgrades/</code>，每個版本一個資料夾。</p>
		</div>
	</div>
</div>

{include file="javascript-includes.tpl"}
{include file='globalfooter.tpl'}
