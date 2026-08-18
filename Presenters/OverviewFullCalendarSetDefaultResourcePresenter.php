<?php

require_once(ROOT_DIR . 'Domain/Access/namespace.php');
require_once(ROOT_DIR . 'lib/Application/Authorization/namespace.php');

/**
 * Backs the resource picker's "設為預設" star (tier 1 of the priority chain resolved in
 * OverviewFullCalendarPresenter::ResolveDefaultResourceIds() / documented in
 * overview_fullcalendar.js's loadStoredResourceSelection()) — persists (or clears) the
 * user's own explicit default room via the generic user_preferences table
 * (UserPreferences::OVERVIEW_DEFAULT_RESOURCE_ID), independent of both the last-selected
 * room (client-side localStorage) and the classic app's default-schedule setting.
 */
class OverviewFullCalendarSetDefaultResourcePresenter
{
    /**
     * @var IOverviewFullCalendarSetDefaultResourcePage
     */
    private $page;

    /**
     * @var IUserRepository
     */
    private $userRepository;

    /**
     * @var IResourceRepository
     */
    private $resourceRepository;

    /**
     * @var IPermissionServiceFactory
     */
    private $permissionServiceFactory;

    public function __construct(
        IOverviewFullCalendarSetDefaultResourcePage $page,
        IUserRepository $userRepository,
        IResourceRepository $resourceRepository,
        IPermissionServiceFactory $permissionServiceFactory
    ) {
        $this->page = $page;
        $this->userRepository = $userRepository;
        $this->resourceRepository = $resourceRepository;
        $this->permissionServiceFactory = $permissionServiceFactory;
    }

    public function PageLoad(UserSession $userSession)
    {
        if ($userSession->UserId == 0) {
            $this->page->SetErrorResponse('Not signed in');
            return;
        }

        // Empty resourceId is the "取消預設" (remove default) case, not an invalid id —
        // clears the preference instead of getting rejected below.
        $resourceId = $this->page->GetResourceId();
        if (!empty($resourceId)) {
            $resource = $this->resourceRepository->LoadById($resourceId);
            $permissionService = $this->permissionServiceFactory->GetPermissionService();
            if ($resource->GetId() != $resourceId || !$permissionService->CanAccessResource($resource, $userSession)) {
                $this->page->SetErrorResponse('Invalid resource');
                return;
            }
        }

        $user = $this->userRepository->LoadById($userSession->UserId);
        $user->ChangePreference(UserPreferences::OVERVIEW_DEFAULT_RESOURCE_ID, $resourceId ?: '');
        $this->userRepository->Update($user);

        $this->page->SetSuccessResponse($resourceId);
    }
}

interface IOverviewFullCalendarSetDefaultResourcePage
{
    /**
     * @return string empty string clears the preference
     */
    public function GetResourceId();

    /**
     * @param string $resourceId
     */
    public function SetSuccessResponse($resourceId);

    /**
     * @param string $message
     */
    public function SetErrorResponse($message);
}
