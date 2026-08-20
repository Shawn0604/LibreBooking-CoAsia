<?php

// uncomment to allow self signed and untrusted certs for ldaps
//putenv('LDAPTLS_REQCERT=never');

require_once(ROOT_DIR . 'lib/Application/Authentication/namespace.php');
require_once(ROOT_DIR . 'plugins/Authentication/ActiveDirectory/namespace.php');

/**
 * Provides ActiveDirectory LDAP authentication/synchronization for LibreBooking
 * @see IAuthorization
 */
class ActiveDirectory extends Authentication implements IAuthentication
{
    /**
     * @var IAuthentication
     */
    private $authToDecorate;

    /**
     * @var AdLdapWrapper
     */
    private $ldap;

    /**
     * @var ActiveDirectoryOptions
     */
    private $options;

    /**
     * @var IRegistration
     */
    private $_registration;

    /**
     * @var IUserRepository
     */
    private $_userRepository;

    /**
     * @var ActiveDirectoryUser
     */
    private $user = null;

    /**
     * @var string
     */
    private $password;

    public function SetRegistration($registration)
    {
        $this->_registration = $registration;
    }

    private function GetRegistration()
    {
        if ($this->_registration == null) {
            $this->_registration = new Registration();
        }

        return $this->_registration;
    }

    public function SetUserRepository($userRepository)
    {
        $this->_userRepository = $userRepository;
    }

    private function GetUserRepository()
    {
        if ($this->_userRepository == null) {
            $this->_userRepository = new UserRepository();
        }

        return $this->_userRepository;
    }

    /**
     * @param IAuthentication $authentication Authentication class to decorate
     * @param IActiveDirectory $ldapImplementation The actual LDAP implementation to work against
     * @param ActiveDirectoryOptions $ldapOptions Options to use for LDAP configuration
     */
    public function __construct(IAuthentication $authentication, $ldapImplementation = null, $ldapOptions = null)
    {
        if (!function_exists('ldap_connect')) {
            echo 'No LDAP support for PHP.  See: http://www.php.net/ldap';
        }

        $this->authToDecorate = $authentication;

        $this->options = $ldapOptions;
        if ($ldapOptions == null) {
            $this->options = new ActiveDirectoryOptions();
        }

        $this->ldap = $ldapImplementation;
        if ($ldapImplementation == null) {
            $this->ldap = new AdLdapWrapper($this->options);
        }
    }

    public function Validate($username, $password)
    {
        $this->password = $password;

        $username = $this->CleanUsername($username);

        // Per-account "emergency admin" exemption (users.is_local_auth_only, set from the admin UI)
        // -- checked before ever touching LDAP. LoadByUsername() safely returns a NullUser (falsy
        // IsLocalAuthOnly()) for a brand-new AD employee who has never logged in before, so this
        // never blocks a genuine first-ever AD login. This REPLACES the old global
        // database.auth.when.ldap.user.not.found fallback below, which let ANY account with a local
        // password bypass AD -- not just a designated emergency one -- whenever AD was unreachable
        // or rejected a login.
        $localUser = $this->GetUserRepository()->LoadByUsername($username);
        if ($localUser != null && $localUser->IsLocalAuthOnly()) {
            Log::Debug('ActiveDirectory - %s is flagged local-auth-only, skipping AD entirely', $username);
            return $this->authToDecorate->Validate($username, $password);
        }

        // AdLdapWrapper::Connect() (and the adLDAP library underneath it) doesn't just return
        // false on a connection/bind failure the way its own "if (!$connected)" check below
        // assumes — a failed *admin/service-account* bind (wrong bind credentials, or the LDAP
        // server(s) unreachable/unresponsive, e.g. a firewall dropping the TLS handshake on
        // 636) throws an adLDAPException instead. Uncaught, that would propagate all the way
        // out of login for every account, including local-auth-only ones. Caught here and
        // treated as "AD unavailable" -- fails closed (see below) rather than crashing.
        try {
            $connected = $this->ldap->Connect();
        } catch (Throwable $ex) {
            Log::Error('ActiveDirectory - could not connect/bind to LDAP server(s): %s', $ex->getMessage());
            $connected = false;
        }

        if (!$connected) {
            // Fails closed for everyone except the is_local_auth_only accounts already handled
            // above -- an AD outage must not let any other account fall back to a local password
            // (that fallback, when it existed, was global/unscoped -- see the comment above).
            Log::Error('ActiveDirectory - LDAP unavailable, denying login for user %s (local-password fallback is restricted to accounts flagged local-auth-only)', $username);
            return false;
        }

        $isValid = $this->ldap->Authenticate($username, $password);
        Log::Debug('Result of ActiveDirectory LDAP Authenticate for user %s: %d', $username, $isValid);

        if ($isValid) {
            $this->user = $this->ldap->GetLdapUser($username);
            $userLoaded = $this->LdapUserExists();

            if (!$userLoaded) {
                Log::Error('Could not load user details from ActiveDirectory LDAP. Check your basedn setting. User: %s', $username);
            }
            return $userLoaded;
        }

        return false;
    }

    public function Login($username, $loginContext)
    {
        $username = $this->CleanUsername($username);
        Log::Debug('ActiveDirectory - Login() in with username: %s', $username);
        if ($this->LdapUserExists()) {
            Log::Debug('Running ActiveDirectory user synchronization for username: %s, Attributes: %s', $username, $this->user->__toString());
            $this->Synchronize($username);
            $this->ReactivateAndMarkAsAdManaged($username);
        } else {
            Log::Debug('Skipping ActiveDirectory user synchronization, user not loaded');
        }

        return $this->authToDecorate->Login($username, $loginContext);
    }

    /**
     * Runs right after a successful AD sync, before delegating to authToDecorate->Login() (where
     * the StatusId() == ACTIVE gate lives, see lib/Application/Authentication/Authentication.php).
     * Two independent bits of post-sync housekeeping, combined into one load/update so a returning
     * AD login doesn't cost two extra round trips:
     *   - reactivates a previously-INACTIVE account (rehire/return from leave restores the original
     *     account instead of leaving it stuck inactive forever) -- deliberately only INACTIVE, not
     *     "!= ACTIVE", so AWAITING_ACTIVATION accounts aren't silently activated by an AD login
     *   - marks the account is_ad_managed, so Jobs/ad-reconcile-account-status.php can scope itself
     *     to real AD accounts -- set here (every successful AD login), not just by the one-off HR
     *     import job, so accounts first created by a live AD login are covered too
     */
    private function ReactivateAndMarkAsAdManaged($username)
    {
        $repository = $this->GetUserRepository();
        $localUser = $repository->LoadByUsername($username);

        $needsReactivation = $localUser->StatusId() == AccountStatus::INACTIVE;
        $needsAdManagedFlag = !$localUser->IsAdManaged();

        if (!$needsReactivation && !$needsAdManagedFlag) {
            return;
        }

        if ($needsReactivation) {
            Log::Debug('ActiveDirectory - reactivating previously-inactive account for username: %s', $username);
            $localUser->Activate();
        }
        if ($needsAdManagedFlag) {
            $localUser->MarkAsAdManaged();
        }
        $repository->Update($localUser);
    }

    public function Logout(UserSession $user)
    {
        $this->authToDecorate->Logout($user);
    }

    public function AreCredentialsKnown()
    {
        return false;
    }

    private function LdapUserExists()
    {
        return $this->user != null;
    }

    private function Synchronize($username)
    {
        // Always a random, unguessable local password now -- local-password fallback is no longer
        // a global possibility for AD accounts (see Validate()'s per-account is_local_auth_only
        // check), so mirroring the user's real current AD password into the local `users` table
        // would serve no purpose anymore and would just be an unnecessary exposure (local DB
        // compromise becoming equivalent to AD password compromise for every AD user).
        $password = Password::GenerateRandom();

        $registration = $this->GetRegistration();

        $registration->Synchronize(
            new AuthenticatedUser(
                $username,
                $this->user->GetEmail(),
                $this->user->GetFirstName(),
                $this->user->GetLastName(),
                $password,
                Configuration::Instance()->GetKey(ConfigKeys::DEFAULT_LANGUAGE),
                Configuration::Instance()->GetDefaultTimezone(),
                $this->user->GetPhone(),
                $this->user->GetInstitution(),
                $this->user->GetTitle(),
                $this->user->GetGroups(),
                $this->user->GetDepartment()
            )
        );
    }

    private function CleanUsername($username)
    {
        if (!$this->options->CleanUsername()) {
            return $username;
        }

        if (BookedStringHelper::Contains($username, '@')) {
            Log::Debug('ActiveDirectory - Username %s appears to be an email address. Cleaning...', $username);
            $parts = explode('@', $username);
            $username = $parts[0];
        }
        if (BookedStringHelper::Contains($username, '\\')) {
            Log::Debug('ActiveDirectory - Username %s appears contain a domain. Cleaning...', $username);
            $parts = explode('\\', $username);
            $username = $parts[1];
        }

        return $username;
    }

    public function AllowUsernameChange()
    {
        return false;
    }

    public function AllowEmailAddressChange()
    {
        return false;
    }

    public function AllowPasswordChange()
    {
        return false;
    }

    public function AllowNameChange()
    {
        return false;
    }

    public function AllowPhoneChange()
    {
        return true;
    }

    public function AllowOrganizationChange()
    {
        return true;
    }

    public function AllowPositionChange()
    {
        return true;
    }
}
