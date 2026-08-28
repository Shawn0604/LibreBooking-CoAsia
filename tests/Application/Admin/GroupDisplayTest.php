<?php

declare(strict_types=1);

require_once(ROOT_DIR . 'lib/Application/Admin/GroupDisplay.php');
require_once(ROOT_DIR . 'Domain/Access/namespace.php');

class GroupDisplayTest extends TestBase
{
    private function group(array $roleLevels, int $isDefault = 0, string $name = 'Some Group'): GroupItemView
    {
        return new GroupItemView(9, $name, null, $isDefault, $roleLevels);
    }

    public function testApplicationAdminGroupMapsToChineseLabelAndIsReserved()
    {
        $info = GroupDisplay::Describe($this->group([RoleLevel::APPLICATION_ADMIN], 0, 'Application Administrators'));

        $this->assertEquals('GroupLabelApplicationAdmin', $info['labelKey']);
        $this->assertEquals('Application Administrators', $info['storedName']);
        $this->assertEquals('GroupPermSummaryApplicationAdmin', $info['permissionSummaryKey']);
        $this->assertTrue($info['isReserved']);
        $this->assertFalse($info['isDefault']);
        $this->assertEquals(RoleLevel::APPLICATION_ADMIN, $info['primaryRoleLevel']);
    }

    public function testResourceAdminGroupMapsToRoomAdminLabel()
    {
        $info = GroupDisplay::Describe($this->group([RoleLevel::RESOURCE_ADMIN], 0, 'Resource Administrators'));

        $this->assertEquals('GroupLabelResourceAdmin', $info['labelKey']);
        $this->assertEquals('GroupPermSummaryResourceAdmin', $info['permissionSummaryKey']);
        $this->assertTrue($info['isReserved']);
    }

    public function testScheduleAdminGroupMapsToScheduleAdminLabel()
    {
        $info = GroupDisplay::Describe($this->group([RoleLevel::SCHEDULE_ADMIN]));
        $this->assertEquals('GroupLabelScheduleAdmin', $info['labelKey']);
        $this->assertEquals(RoleLevel::SCHEDULE_ADMIN, $info['primaryRoleLevel']);
    }

    public function testGroupAdminGroupMapsToGroupAdminLabel()
    {
        $info = GroupDisplay::Describe($this->group([RoleLevel::GROUP_ADMIN]));
        $this->assertEquals('GroupLabelGroupAdmin', $info['labelKey']);
    }

    public function testDefaultGroupKeepsStoredNameAndIsReserved()
    {
        $info = GroupDisplay::Describe($this->group([], 1, '全體員工'));

        $this->assertEquals('', $info['labelKey'], 'default group shows its stored name verbatim');
        $this->assertEquals('全體員工', $info['storedName']);
        $this->assertEquals('GroupPurposeDefault', $info['purposeKey']);
        $this->assertTrue($info['isReserved']);
        $this->assertTrue($info['isDefault']);
        $this->assertNull($info['primaryRoleLevel']);
    }

    public function testCustomGroupIsNotReservedAndHasNoCannedLabel()
    {
        $info = GroupDisplay::Describe($this->group([], 0, 'Marketing Team'));

        $this->assertEquals('', $info['labelKey']);
        $this->assertEquals('Marketing Team', $info['storedName']);
        $this->assertEquals('', $info['purposeKey']);
        $this->assertFalse($info['isReserved']);
        $this->assertNull($info['primaryRoleLevel']);
    }

    public function testMultiRoleGroupPrefersHighestPrivilege()
    {
        $info = GroupDisplay::Describe($this->group([RoleLevel::SCHEDULE_ADMIN, RoleLevel::APPLICATION_ADMIN]));

        $this->assertEquals(RoleLevel::APPLICATION_ADMIN, $info['primaryRoleLevel']);
        $this->assertEquals('GroupLabelApplicationAdmin', $info['labelKey']);
        $this->assertEqualsCanonicalizing(
            [RoleLevel::APPLICATION_ADMIN, RoleLevel::SCHEDULE_ADMIN],
            $info['roleLevels']
        );
    }

    public function testIsReservedHelperMatchesDescribe()
    {
        $this->assertTrue(GroupDisplay::IsReserved($this->group([RoleLevel::GROUP_ADMIN])));
        $this->assertTrue(GroupDisplay::IsReserved($this->group([], 1)));
        $this->assertFalse(GroupDisplay::IsReserved($this->group([], 0)));
    }
}
