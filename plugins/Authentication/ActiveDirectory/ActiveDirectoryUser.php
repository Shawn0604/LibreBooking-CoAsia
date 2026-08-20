<?php

class ActiveDirectoryUser
{
    private $fname;
    private $lname;
    private $mail;
    private $phone;
    private $institution;
    private $title;
    private $department;
    private $mapping;
    private $groups;

    /**
     * @param adLDAPUserCollection $entry
     * @param string[]|array $mapping
     * @param string $groups
     */
    public function __construct($entry, $mapping, $groups = null)
    {
        $this->mapping = $mapping;

        $this->fname = $this->Get($entry, 'givenname');
        $this->lname = $this->Get($entry, 'sn');
        // AD accounts aren't guaranteed to have every mapped attribute populated (e.g. a
        // service/test account with no 'mail' set) — strtolower(null) is a deprecation warning
        // under PHP 8.1+, and with display_errors on (this dev environment) that warning gets
        // printed into the response body *before* Page.php's post-login header('Location: ...')
        // redirect runs, which then fails with "headers already sent" and strands the user on a
        // raw warning page instead of being redirected in — even though the login itself already
        // succeeded. The account still gets created/updated correctly either way; this only
        // fixes the broken redirect.
        $this->mail = strtolower($this->Get($entry, 'mail') ?? '');
        $this->phone = $this->Get($entry, 'telephonenumber');
        $this->institution = $this->Get($entry, 'physicaldeliveryofficename');
        $this->title = $this->Get($entry, 'title');
        $this->department = $this->Get($entry, 'department');

        $this->groups = null;
        if ($groups != null) {
            if (is_array($groups)) {
                $this->groups = $groups;
            } else {
                $this->groups = explode(',', $groups);
            }
        }
    }

    public function GetFirstName()
    {
        return $this->fname;
    }

    public function GetLastName()
    {
        return $this->lname;
    }

    public function GetEmail()
    {
        return $this->mail;
    }

    public function GetPhone()
    {
        return $this->phone;
    }

    public function GetInstitution()
    {
        return $this->institution;
    }

    public function GetTitle()
    {
        return $this->title;
    }

    public function GetDepartment()
    {
        return $this->department;
    }

    /**
     * @return string[]|null
     */
    public function GetGroups()
    {
        return $this->groups;
    }

    public function __toString()
    {
        return sprintf(
            'ActiveDirectoryUser - First Name: %s, Last Name: %s, Email: %s, Phone: %s, Institution: %s, Title: %s, Department: %s',
            $this->fname,
            $this->lname,
            $this->mail,
            $this->phone,
            $this->institution,
            $this->title,
            $this->department
        );
    }

    private function Get($entry, $field)
    {
        $actualField = $field;
        if (array_key_exists($field, $this->mapping)) {
            $actualField = $this->mapping[$field];
        }
        $value = $entry->$actualField;

        if (is_array($value)) {
            return $value[0];
        }

        return $value;
    }
}
