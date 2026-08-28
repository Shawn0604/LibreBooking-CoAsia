<?php

require_once(ROOT_DIR . 'Domain/Access/namespace.php');
require_once(ROOT_DIR . 'Presenters/ActionPresenter.php');
require_once(ROOT_DIR . 'lib/Application/Authentication/namespace.php');
require_once(ROOT_DIR . 'lib/Application/Admin/GroupImportCsv.php');
require_once(ROOT_DIR . 'lib/Application/Admin/CsvImportResult.php');
require_once(ROOT_DIR . 'lib/Application/Admin/ResourcePermissionService.php');
require_once(ROOT_DIR . 'lib/Application/Admin/GroupDisplay.php');
require_once(ROOT_DIR . 'Pages/Admin/ManageGroupsPage.php');

class ManageGroupsActions
{
    public const Activate = 'activate';
    public const Deactivate = 'deactivate';
    public const Password = 'password';
    public const Permissions = 'permissions';
    public const RemoveUser = 'removeUser';
    public const AddUser = 'addUser';
    public const AddGroup = 'addGroup';
    public const UpdateGroup = 'updateGroup';
    public const DeleteGroup = 'deleteGroup';
    public const Roles = 'roles';
    public const GroupAdmin = 'groupAdmin';
    public const AdminGroups = 'adminGroups';
    public const ResourceGroups = 'resourceGroups';
    public const ScheduleGroups = 'scheduleGroups';
    public const Import = 'import';
}

class ManageGroupsPresenter extends ActionPresenter
{
    /**
     * @var IManageGroupsPage
     */
    private $page;
    /**
     * @var IGroupRepository&IGroupViewRepository
     */
    private $groupRepository;
    /**
     * @var IResourceRepository
     */
    private $resourceRepository;
    /**
     * @var IScheduleRepository
     */
    private $scheduleRepository;
    /**
     * @var IUserRepository
     */
    private $userRepository;
    /**
     * @var IResourcePermissionService
     */
    private $resourcePermissionService;

    /**
     * @param IManageGroupsPage $page
     * @param IGroupRepository&IGroupViewRepository $groupRepository
     * @param IResourceRepository $resourceRepository
     * @param IScheduleRepository $scheduleRepository
     * @param IUserRepository $userRepository
     * @param IResourcePermissionService|null $resourcePermissionService
     */
    public function __construct(
        IManageGroupsPage $page,
        IGroupRepository&IGroupViewRepository $groupRepository,
        IResourceRepository $resourceRepository,
        IScheduleRepository $scheduleRepository,
        IUserRepository $userRepository,
        ?IResourcePermissionService $resourcePermissionService = null,
    ) {
        parent::__construct($page);

        $this->page = $page;
        $this->groupRepository = $groupRepository;
        $this->resourceRepository = $resourceRepository;
        $this->scheduleRepository = $scheduleRepository;
        $this->userRepository = $userRepository;
        $this->resourcePermissionService = $resourcePermissionService ?? new ResourcePermissionService($resourceRepository);

        $this->AddAction(ManageGroupsActions::AddUser, 'AddUser');
        $this->AddAction(ManageGroupsActions::RemoveUser, 'RemoveUser');
        $this->AddAction(ManageGroupsActions::Permissions, 'ChangePermissions');
        $this->AddAction(ManageGroupsActions::AddGroup, 'AddGroup');
        $this->AddAction(ManageGroupsActions::UpdateGroup, 'UpdateGroup');
        $this->AddAction(ManageGroupsActions::DeleteGroup, 'DeleteGroup');
        $this->AddAction(ManageGroupsActions::Roles, 'ChangeRoles');
        $this->AddAction(ManageGroupsActions::GroupAdmin, 'ChangeGroupAdmin');
        $this->AddAction(ManageGroupsActions::AdminGroups, 'ChangeAdminGroups');
        $this->AddAction(ManageGroupsActions::ResourceGroups, 'ChangeResourceGroups');
        $this->AddAction(ManageGroupsActions::ScheduleGroups, 'ChangeScheduleGroups');
        $this->AddAction(ManageGroupsActions::Import, 'Import');
    }

    public function PageLoad()
    {
        if ($this->page->GetGroupId() != null) {
            $groupList = $this->groupRepository->GetList(
                1,
                1,
                null,
                null,
                new SqlFilterEquals(
                    new SqlFilterColumn(TableNames::GROUPS_ALIAS, ColumnNames::GROUP_ID),
                    $this->page->GetGroupId()
                )
            );
        } else {
            $groupList = $this->groupRepository->GetList(
                $this->page->GetPageNumber(),
                null,
                $this->page->GetSortField(),
                $this->page->GetSortDirection()
            );
        }

        $groups = $groupList->Results();
        $this->page->BindGroups($groups);
        $this->page->BindPageInfo($groupList->PageInfo());

        // Chinese display label + purpose + permission summary + isReserved, keyed by
        // group id. Pure presentation overlay -- see GroupDisplay. The stored English
        // name and group_id are never touched.
        $displays = [];
        foreach ($groups as $group) {
            $displays[$group->Id()] = GroupDisplay::Describe($group);
        }
        $this->page->BindGroupDisplays($displays);

        $this->page->BindResources($this->resourceRepository->GetResourceList());
        $this->page->BindSchedules($this->scheduleRepository->GetAll());

        $this->page->BindRoles([
            new RoleDto(1, 'Group Admin', RoleLevel::GROUP_ADMIN),
            new RoleDto(2, 'Application Admin', RoleLevel::APPLICATION_ADMIN),
            new RoleDto(3, 'Resource Admin', RoleLevel::RESOURCE_ADMIN),
            new RoleDto(4, 'Schedule Admin', RoleLevel::SCHEDULE_ADMIN)
        ]);
        $this->page->BindAdminGroups($this->groupRepository->GetGroupsByRole(RoleLevel::GROUP_ADMIN));
    }

    public function ChangePermissions()
    {
        $currentUser = $this->userRepository->LoadById(ServiceLocator::GetServer()->GetUserSession()->UserId);
        $managedResourceIds = $this->resourcePermissionService->GetResourceIdsThatUserCanAdminister($currentUser);

        $group = $this->groupRepository->LoadById($this->page->GetGroupId());

        $permissionsByType = $this->resourcePermissionService->ParseSubmittedPermissions(
            $this->page->GetAllowedResourceIds(),
            $managedResourceIds,
        );

        $group->ChangeAllowedPermissions(
            $this->resourcePermissionService->MergeWithPreservedPermissions(
                existingIds: $group->AllowedResourceIds(),
                managedResourceIds: $managedResourceIds,
                submittedIds: $permissionsByType['full'],
            )
        );
        $group->ChangeViewPermissions(
            $this->resourcePermissionService->MergeWithPreservedPermissions(
                existingIds: $group->AllowedViewResourceIds(),
                managedResourceIds: $managedResourceIds,
                submittedIds: $permissionsByType['view'],
            )
        );

        $this->groupRepository->Update($group);
    }

    public function ChangeRoles()
    {
        $userSession = ServiceLocator::GetServer()->GetUserSession();
        if (!$userSession->IsAdmin) {
            // only application administrators may change group roles
            Log::Error('ChangeRoles denied. UserId=%s is not an application administrator', $userSession->UserId);
            return;
        }

        $groupId = $this->page->GetGroupId();
        Log::Debug('Changing roles for groupId: %s', $groupId);

        $group = $this->groupRepository->LoadById($groupId);
        $roles = [];

        if (is_array($this->page->GetRoleIds())) {
            $roles = $this->page->GetRoleIds();
        }
        $group->ChangeRoles($roles);
        $this->groupRepository->Update($group);
    }

    public function ProcessDataRequest()
    {
        $response = '';
        $request = $this->page->GetDataRequest();
        switch ($request) {
            case 'groupMembers':
                $users = $this->groupRepository->GetUsersInGroup($this->page->GetGroupId());
                $response = new UserGroupResults($users->Results(), $users->PageInfo()->Total);
                break;
            case 'permissions':
                $response = $this->GetGroupResourcePermissions();
                break;
            case 'roles':
                $response = $this->GetGroupRoles();
                break;
            case ManageGroupsActions::AdminGroups:
                $response = $this->GetAdminGroups();
                break;
            case ManageGroupsActions::ResourceGroups:
                $response = $this->GetResourceAdminGroups();
                break;
            case ManageGroupsActions::ScheduleGroups:
                $response = $this->GetScheduleAdminGroups();
                break;
            case 'export':
                $this->Export();
                return;
            case 'template':
                $this->page->ShowTemplateCsv();
                return;
        }

        $this->page->SetJsonResponse($response);
    }

    /**
     * @return int[] all resource ids the user has permission to
     */
    public function GetGroupResourcePermissions()
    {
        $group = $this->groupRepository->LoadById($this->page->GetGroupId());
        return ['full' => $group->AllowedResourceIds(), 'view' => $group->AllowedViewResourceIds()];
    }

    public function AddUser()
    {
        $groupId = $this->page->GetGroupId();
        $userId = $this->page->GetUserId();

        Log::Debug('Adding userId: %s to groupId: %s', $userId, $groupId);

        $group = $this->groupRepository->LoadById($groupId);
        $group->AddUser($userId);
        $this->groupRepository->Update($group);
    }

    public function RemoveUser()
    {
        $groupId = $this->page->GetGroupId();
        $userId = $this->page->GetUserId();

        Log::Debug('Removing userId: %s from groupId: %s', $userId, $groupId);

        $group = $this->groupRepository->LoadById($groupId);
        $group->RemoveUser($userId);
        $this->groupRepository->Update($group);
    }

    public function ChangeUsers()
    {
        $groupId = $this->page->GetGroupId();
        $userIds = $this->page->GetUserIds();

        Log::Debug('Changing group users: groupId: %s', $groupId);

        $group = $this->groupRepository->LoadById($groupId);
        $group->ChangeUsers($userIds);
        $this->groupRepository->Update($group);
    }

    public function AddGroup()
    {
        $groupName = $this->page->GetGroupName();
        // Custom groups are never the default group -- only the one seeded 全體員工 group
        // (or whatever the install designated) carries isdefault, and that flag is managed
        // from its own detail panel, not created here.
        Log::Debug('Adding new custom group with name: %s', $groupName);

        $group = new Group(0, $groupName, 0);
        return $this->groupRepository->Add($group);
    }

    /**
     * A group is "reserved" when it grants a role or is the default group. Its stored
     * name and default flag drive role lookup / auto-join and must not be edited from
     * this screen (mirrors GroupDisplay::IsReserved, but off the domain Group which is
     * what UpdateGroup/DeleteGroup already load).
     */
    private function IsReservedGroup(Group $group): bool
    {
        return $group->IsDefault() || count($group->RoleIds()) > 0;
    }

    public function UpdateGroup()
    {
        $groupId = $this->page->GetGroupId();
        $groupName = $this->page->GetGroupName();
        $isDefault = $this->page->AutomaticallyAddToGroup();

        $group = $this->groupRepository->LoadById($groupId);

        if ($this->IsReservedGroup($group)) {
            Log::Error('UpdateGroup denied: groupId=%s is a reserved (role-granting or default) group', $groupId);
            return;
        }

        Log::Debug('Renaming group id: %s to: %s', $groupId, $groupName);
        $group->Rename($groupName);
        $group->ChangeDefault($isDefault);

        $this->groupRepository->Update($group);
    }

    public function DeleteGroup()
    {
        $groupId = $this->page->GetGroupId();

        $group = $this->groupRepository->LoadById($groupId);

        if ($this->IsReservedGroup($group)) {
            Log::Error('DeleteGroup denied: groupId=%s is a reserved (role-granting or default) group', $groupId);
            return;
        }

        Log::Debug('Deleting groupId: %s', $groupId);
        $this->groupRepository->Remove($group);
    }

    public function ChangeGroupAdmin()
    {
        $userSession = ServiceLocator::GetServer()->GetUserSession();
        if (!$userSession->IsAdmin) {
            // only application administrators may change group administrators
            Log::Error('ChangeGroupAdmin denied. UserId=%s is not an application administrator', $userSession->UserId);
            return;
        }

        $groupId = $this->page->GetGroupId();
        $adminGroupId = $this->page->GetAdminGroupId();

        Log::Debug('Changing admin for groupId: %s to %s', $groupId, $adminGroupId);

        $group = $this->groupRepository->LoadById($groupId);

        $group->ChangeAdmin($adminGroupId);

        $this->groupRepository->Update($group);
    }

    /**
     * @return array|int[]
     */
    public function GetGroupRoles()
    {
        $groupId = $this->page->GetGroupId();
        $group = $this->groupRepository->LoadById($groupId);

        $ids = $group->RoleIds();

        return $ids;
    }

    public function GetAdminGroups()
    {
        $groupId = $this->page->GetGroupId();

        $result = $this->groupRepository->GetList(
            null,
            null,
            null,
            null,
            new SqlFilterEquals(
                new SqlFilterColumn(TableNames::GROUPS_ALIAS, ColumnNames::GROUP_ADMIN_GROUP_ID),
                $groupId
            )
        );
        $ids = [];
        /** @var GroupItemView $group */
        foreach ($result->Results() as $group) {
            $ids[] = $group->Id();
        }

        return $ids;
    }

    public function GetResourceAdminGroups()
    {
        $groupId = $this->page->GetGroupId();

        $result = $this->resourceRepository->GetList(
            null,
            null,
            null,
            null,
            new SqlFilterEquals(
                new SqlFilterColumn(TableNames::RESOURCES_ALIAS, ColumnNames::RESOURCE_ADMIN_GROUP_ID),
                $groupId
            )
        );
        $ids = [];
        /** @var BookableResource $resource */
        foreach ($result->Results() as $resource) {
            $ids[] = $resource->GetId();
        }

        return $ids;
    }

    public function GetScheduleAdminGroups()
    {
        $groupId = $this->page->GetGroupId();

        $result = $this->scheduleRepository->GetList(
            null,
            null,
            null,
            null,
            new SqlFilterEquals(
                new SqlFilterColumn(TableNames::SCHEDULES_ALIAS, ColumnNames::SCHEDULE_ADMIN_GROUP_ID),
                $groupId
            )
        );
        $ids = [];
        /** @var Schedule $schedule */
        foreach ($result->Results() as $schedule) {
            $ids[] = $schedule->GetId();
        }

        return $ids;
    }

    public function ChangeAdminGroups()
    {
        $userSession = ServiceLocator::GetServer()->GetUserSession();
        if (!$userSession->IsAdmin) {
            // only application administrators may change administered groups
            Log::Error('ChangeAdminGroups denied. UserId=%s is not an application administrator', $userSession->UserId);
            return;
        }

        $groupId = $this->page->GetGroupId();
        $groupIds = $this->page->GetGroupAdminIds();
        if (empty($groupIds)) {
            $groupIds = [];
        }
        Log::Debug('Changing group admins. Setting group admin id to %s for %s', $groupId, var_export($groupIds, true));

        $currentAdminGroups = $this->GetAdminGroups();
        foreach ($currentAdminGroups as $id) {
            $group = $this->groupRepository->LoadById($id);
            $group->ChangeAdmin(null);
            $this->groupRepository->Update($group);
        }

        foreach ($groupIds as $id) {
            $group = $this->groupRepository->LoadById($id);
            $group->ChangeAdmin($groupId);
            $this->groupRepository->Update($group);
        }
    }

    public function ChangeResourceGroups()
    {
        $userSession = ServiceLocator::GetServer()->GetUserSession();
        if (!$userSession->IsAdmin) {
            // only application administrators may change administered resources
            Log::Error('ChangeResourceGroups denied. UserId=%s is not an application administrator', $userSession->UserId);
            return;
        }

        $groupId = $this->page->GetGroupId();
        $resourceIds = $this->page->GetResourceAdminIds();
        if (empty($resourceIds)) {
            $resourceIds = [];
        }
        Log::Debug('Changing resource admins. Setting group admin id to %s for %s', $groupId, var_export($resourceIds, true));

        $currentAdminGroups = $this->GetResourceAdminGroups();
        foreach ($currentAdminGroups as $id) {
            $resource = $this->resourceRepository->LoadById($id);
            $resource->SetAdminGroupId(null);
            $this->resourceRepository->Update($resource);
        }

        foreach ($resourceIds as $id) {
            $resource = $this->resourceRepository->LoadById($id);
            $resource->SetAdminGroupId($groupId);
            $this->resourceRepository->Update($resource);
        }
    }

    public function ChangeScheduleGroups()
    {
        $userSession = ServiceLocator::GetServer()->GetUserSession();
        if (!$userSession->IsAdmin) {
            // only application administrators may change administered schedules
            Log::Error('ChangeScheduleGroups denied. UserId=%s is not an application administrator', $userSession->UserId);
            return;
        }

        $groupId = $this->page->GetGroupId();
        $scheduleIds = $this->page->GetScheduleAdminIds();
        if (empty($scheduleIds)) {
            $scheduleIds = [];
        }
        Log::Debug('Changing schedule admins. Setting group admin id to %s for %s', $groupId, var_export($scheduleIds, true));

        $currentAdminGroups = $this->GetScheduleAdminGroups();
        foreach ($currentAdminGroups as $id) {
            $schedule = $this->scheduleRepository->LoadById($id);
            $schedule->SetAdminGroupId(null);
            $this->scheduleRepository->Update($schedule);
        }

        foreach ($scheduleIds as $id) {
            $schedule = $this->scheduleRepository->LoadById($id);
            $schedule->SetAdminGroupId($groupId);
            $this->scheduleRepository->Update($schedule);
        }
    }

    public function Export()
    {
        $userSession = ServiceLocator::GetServer()->GetUserSession();
        if (!$userSession->IsAdmin) {
            // only application administrators may export groups
            Log::Error('Export denied. UserId=%s is not an application administrator', $userSession->UserId);
            return;
        }

        /** @var GroupItemView[] $groups */
        $groups = $this->groupRepository->GetList()->Results();
        /** @var UserItemView[] $userGroups */
        $userGroups = $this->userRepository->GetList(null, null, null, null, null, AccountStatus::ACTIVE)->Results();
        $groupPermissions = $this->groupRepository->GetPermissionList();

        $indexedPermissionsWrite = [];
        $indexedPermissionsRead = [];
        $indexedUsers = [];
        foreach ($groups as $group) {
            $indexedPermissionsWrite[$group->Id()] = [];
            $indexedPermissionsRead[$group->Id()] = [];
            $indexedUsers[$group->Id()] = [];
        }

        foreach ($userGroups as $user) {
            foreach ($user->GroupIds as $groupId) {
                $indexedUsers[$groupId][] = $user;
            }
        }

        foreach ($groupPermissions as $groupPermission) {
            if ($groupPermission->PermissionType() == ResourcePermissionType::Full) {
                $indexedPermissionsWrite[$groupPermission->GroupId()][] = $groupPermission;
            }
            if ($groupPermission->PermissionType() == ResourcePermissionType::View) {
                $indexedPermissionsRead[$groupPermission->GroupId()][] = $groupPermission;
            }
        }

        $this->page->Export($groups, $indexedUsers, $indexedPermissionsWrite, $indexedPermissionsRead);
    }

    public function Import()
    {
        $userSession = ServiceLocator::GetServer()->GetUserSession();
        if (!$userSession->IsAdmin) {
            $this->page->SetImportResult(new CsvImportResult(0, [], 'User is not an admin'));
            return;
        }

        Log::Debug('Importing groups');

        ini_set('max_execution_time', 600);

        $resources = $this->resourceRepository->GetResourceList();
        /** @var int[] $resourcesIndexed */
        $resourcesIndexed = [];
        foreach ($resources as $resource) {
            $resourcesIndexed[$resource->GetName()] = $resource->GetId();
        }

        /** @var UserItemView[] $users */
        $users = $this->userRepository->GetList(null, null, null, null, null, AccountStatus::ACTIVE)->Results();
        /** @var int[] $usersIndexed */
        $usersIndexed = [];
        foreach ($users as $user) {
            $usersIndexed[$user->Email] = $user->Id;
        }

        /** @var GroupItemView[] $groups */
        $groups = $this->groupRepository->GetList()->Results();
        /** @var int[] $groupsIndexed */
        $groupsIndexed = [];
        foreach ($groups as $group) {
            $groupsIndexed[$group->Name()] = $group->Id();
        }

        $importFile = $this->page->GetImportFile();
        $csv = new GroupImportCsv($importFile);

        $importCount = 0;
        $messages = [];

        $rows = $csv->GetRows();

        if (count($rows) == 0) {
            $this->page->SetImportResult(new CsvImportResult(0, [], 'Empty file or missing header row'));
            return;
        }

        for ($i = 0; $i < count($rows); $i++) {
            $row = $rows[$i];

            $shouldUpdate = $this->page->GetUpdateOnImport() && array_key_exists($row->name, $groupsIndexed);

            try {
                if ($shouldUpdate) {
                    $group = $this->groupRepository->LoadById($groupsIndexed[$row->name]);
                    $group->ChangeDefault($row->autoAdd);

                    if (!empty($row->groupAdministrator) && array_key_exists($row->groupAdministrator, $groupsIndexed)) {
                        $group->ChangeAdmin($groupsIndexed[$row->groupAdministrator]);
                    }

                    $group->ChangeRoles($this->GetImportRoles($row));

                    if (!empty($row->members)) {
                        $memberIds = $this->GetImportMembers($row->members, $usersIndexed);
                        $group->ChangeUsers($memberIds);
                    }

                    if (!empty($row->permissionsFull)) {
                        $resourceIds = $this->GetImportResources($row->permissionsFull, $resourcesIndexed);
                        $group->ChangeAllowedPermissions($resourceIds);
                    }

                    if (!empty($row->permissionsView)) {
                        $resourceIds = $this->GetImportResources($row->permissionsRead, $resourcesIndexed);

                        $group->ChangeViewPermissions($resourceIds);
                    }

                    Log::Debug('Updating group from import. Id %s', $group->Id());

                    $this->groupRepository->Update($group);
                } else {
                    Log::Debug('Adding group from import. Name %s', $row->name);
                    $group = new Group(0, $row->name, $row->autoAdd);
                    $this->groupRepository->Add($group);

                    if (!empty($row->groupAdministrator) && array_key_exists($row->groupAdministrator, $groupsIndexed)) {
                        $group->ChangeAdmin($groupsIndexed[$row->groupAdministrator]);
                    }

                    $group->ChangeRoles($this->GetImportRoles($row));
                    $group->ChangeUsers($this->GetImportMembers($row->members, $usersIndexed));
                    $group->ChangeAllowedPermissions($this->GetImportResources($row->permissionsFull, $resourcesIndexed));
                    $group->ChangeViewPermissions($this->GetImportResources($row->permissionsRead, $resourcesIndexed));

                    $this->groupRepository->Update($group);
                }

                $importCount++;
            } catch (Exception $ex) {
                Log::Error('Error importing groups. %s', $ex);
            }
        }

        $this->page->SetImportResult(new CsvImportResult($importCount, $csv->GetSkippedRowNumbers(), $messages));
    }

    public function LoadValidators($action)
    {
        if ($action == ManageGroupsActions::Import) {
            $this->page->RegisterValidator('fileExtensionValidator', new FileExtensionValidator('csv', $this->page->GetImportFile()));
        }
    }

    private function GetImportMembers($members, $usersIndexed)
    {
        $memberIds = [];
        foreach ($members as $member) {
            if (array_key_exists($member, $usersIndexed)) {
                $memberIds[] = $usersIndexed[$member];
            }
        }

        return $memberIds;
    }

    private function GetImportResources($resources, $resourcesIndexed)
    {
        $resourceIds = [];
        foreach ($resources as $resource) {
            if (array_key_exists($resource, $resourcesIndexed)) {
                $resourceIds[] = $resourcesIndexed[$resource];
            }
        }

        return $resourceIds;
    }

    private function GetImportRoles(GroupImportCsvRow $row)
    {
        $roles = [];
        if ($row->isAdmin) {
            $roles[] = RoleLevel::APPLICATION_ADMIN;
        }
        if ($row->isGroupAdmin) {
            $roles[] = RoleLevel::GROUP_ADMIN;
        }
        if ($row->isResourceAdmin) {
            $roles[] = RoleLevel::RESOURCE_ADMIN;
        }
        if ($row->isScheduleAdmin) {
            $roles[] = RoleLevel::SCHEDULE_ADMIN;
        }

        return $roles;
    }
}

class UserGroupResults
{
    /**
     * @param UserItemView[] $users
     * @param int $totalUsers
     */
    public function __construct($users, $totalUsers)
    {
        foreach ($users as $user) {
            $this->Users[] = new AutocompleteUser($user->Id, $user->First, $user->Last, $user->Email, $user->Username);
        }
        $this->Total = $totalUsers;
    }

    /**
     * @var int
     */
    public $Total;

    /**
     * @var AutocompleteUser[]
     */
    public $Users;
}
