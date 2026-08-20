<?php

declare(strict_types=1);

class FakeUserRepository implements IUserRepository
{
    /**
     * @var User
     */
    public $_User;
    /**
     * @var User
     */
    public $_UpdatedUser;
    /**
     * @var User
     */
    public $_AddedUser;

    /**
     * @var int|null
     */
    public $_Exists = null;

    /**
     * @var UserDto
     */
    public $_UserDto;
    /**
     * @var UserDto[]
     */
    public $_UserDtos;
    /**
     * @var PageableData
     */
    public $_UserList;
    /**
     * @var User[]
     */
    public $_UserById = [];
    /**
     * @var UserDto[]
     */
    public $_AllUsers = [];
    public $_DeletedUserId;

    public function __construct()
    {
        $this->_User = new FakeUser(123);
    }
    /**
     * @param int $userId
     * @return User
     */
    public function LoadById($userId)
    {
        if (array_key_exists($userId, $this->_UserById)) {
            return $this->_UserById[$userId];
        }
        return $this->_User;
    }

    /**
     * @param string $publicId
     * @return User
     */
    public function LoadByPublicId($publicId)
    {
        return $this->_User;
    }

    /**
     * @param string $userName
     * @return User
     */
    public function LoadByUsername($userName)
    {
        return $this->_User;
    }

    /**
     * @var User|null
     */
    public $_UserByPreference = null;

    /**
     * Optional precise (name, value) -> User map, keyed as "$name:$value" — checked before
     * falling back to $_UserByPreference, for tests that need LoadByPreference() to return
     * different users depending on which preference/value was actually queried (e.g.
     * distinguishing a LINE_LINK_CODE lookup from a LINE_USER_ID lookup in the same test).
     * @var array<string, User>
     */
    public $_UserByPreferenceMap = [];

    /**
     * @param string $name
     * @param string $value
     * @return User
     */
    public function LoadByPreference($name, $value)
    {
        $key = "$name:$value";
        if (array_key_exists($key, $this->_UserByPreferenceMap)) {
            return $this->_UserByPreferenceMap[$key];
        }
        return $this->_UserByPreference ?? new NullUser();
    }

    /**
     * @var User[]
     */
    public $_UpdatedUsers = [];

    /**
     * @param User $user
     * @return void
     */
    public function Update(User $user)
    {
        $this->_UpdatedUser = $user;
        $this->_UpdatedUsers[] = $user;
    }

    /**
     * @param User $user
     * @return int
     */
    public function Add(User $user)
    {
        $this->_AddedUser = $user;
        return 1;
    }

    /**
     * @param $userId int
     * @return void
     */
    public function DeleteById($userId)
    {
        $this->_DeletedUserId = $userId;
    }

    /**
     * @param int $userId
     * @return UserDto
     */
    public function GetById($userId)
    {
        if ($this->_UserDto != null) {
            return $this->_UserDto;
        }

        return $this->_UserDtos[$userId];
    }

    /**
     * @return UserDto[]
     */
    public function GetAll()
    {
        return $this->_AllUsers;
    }

    /**
     * @param int $pageNumber
     * @param int $pageSize
     * @param null|string $sortField
     * @param null|string $sortDirection
     * @param null|ISqlFilter $filter
     * @param AccountStatus|int $accountStatus
     * @return PageableData|UserItemView[]
     */
    public function GetList(
        $pageNumber,
        $pageSize,
        $sortField = null,
        $sortDirection = null,
        $filter = null,
        $accountStatus = AccountStatus::ALL
    ) {
        return $this->_UserList;
    }

    /**
     * @param int $resourceId
     * @return array|UserDto[]
     */
    public function GetResourceAdmins($resourceId)
    {
        return [];
    }

    /**
     * @return array|UserDto[]
     */
    public function GetApplicationAdmins()
    {
        return [];
    }

    /**
     * @param int $userId
     * @return array|UserDto[]
     */
    public function GetGroupAdmins($userId)
    {
        return [];
    }

    /**
     * @param $userId int
     * @param $roleLevels int|null|array|int[]
     * @return array|UserGroup[]
     */
    public function LoadGroups($userId, $roleLevels = null)
    {
        return [];
    }

    /**
     * @param string $emailAddress
     * @param string $userName
     * @return int|null
     */
    public function UserExists($emailAddress, $userName)
    {
        return $this->_Exists;
    }

    /**
     * @return int
     */
    public function GetCount()
    {
        return 0;
    }

    /**
     * @var string|null
     */
    public $_CredentialsInvalidatedAt = null;

    /**
     * @param int $userId
     * @return string|null
     */
    public function GetCredentialsInvalidatedAt($userId)
    {
        return $this->_CredentialsInvalidatedAt;
    }
}
