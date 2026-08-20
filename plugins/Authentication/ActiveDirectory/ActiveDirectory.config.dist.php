<?php

return [
    'settings' => [
        'activedirectory' => [
            // comma separated list of ldap servers such as domaincontroller1,controller2
            'domain.controllers' => 'mydomain,local',

            // default ldap port 389 or 636 for ssl.
            'port' => 389,

            // admin user - bind to ldap service with an authorized account user/password
            'username' => '',

            // admin password - corresponding password
            'password' => '',

            // The base dn for your domain. This is generally the same as your account suffix, but broken up and prefixed with DC=. Your base dn can be located in the extended attributes in Active Directory Users and Computers MMC.
            'basedn' => 'ou=uidauthent,o=domain.com',

            // LDAP protocol version
            'version' => 3,

            // 'true' if 636 was used.
            'use.ssl' => 'false',

            // The full account suffix for your domain. Example: @uidauthent.domain.com.
            'account.suffix' => '',

            // Deprecated (CoAsia fork) -- no longer consulted by ActiveDirectory::Validate().
            // The local-password fallback this used to gate globally (for EVERY account, whenever
            // AD was unreachable or rejected a login) is now a per-account decision instead:
            // check "本機專屬登入" ("local login only") on the specific accounts that should be
            // exempt from AD (Admin > Manage Users > edit user), typically just one or two
            // emergency admin accounts. Every other account now fails closed on any AD outage,
            // with no fallback at all. This key is left here only so an existing config file with
            // it set doesn't fail to parse -- it has no effect anymore.
            'database.auth.when.ldap.user.not.found' => false,

            // mapping of required attributes to attribute names in your directory. 'department'
            // (CoAsia fork) is now a built-in default alongside sn/givenname/mail/etc -- only
            // list it here if your AD schema uses a different literal attribute name for it.
            'attribute.mapping' => 'sn=sn,givenname=givenname,mail=mail,telephonenumber=telephonenumber,physicaldeliveryofficename=physicaldeliveryofficename,title=title',

            // Required groups (empty if not necessary) User only needs to belong to at least one listed (eg. Group1,Group2)
            'required.groups' => '',

            // Whether or not groups should be synced into LibreBooking. When true then be sure that the attribute.mapping config value contains a correct map for groups
            'sync.groups' => false,

            // Whether or not to use single sign on
            'use.sso' => false,

            // If the username is an email address or contains the domain, clean it
            'prevent.clean.username' => false,
        ],
    ],
];
