<?php
/**
 * The admin-users write spec, shared by the accounts screen and the first-run
 * setup page.
 *
 * One declaration rather than two: the bootstrap account created by
 * setup/admin.php goes through the same validation and the same password
 * hashing as one created from the panel, so there is no second, weaker path
 * into the accounts table.
 */

declare(strict_types=1);

/** Used by record_save() for both create and edit. */
function user_spec(): array
{
    return [
        'table'  => 'admin_users',
        'module' => 'users',
        'entity' => 'admin_user',
        'fields' => [
            ['k' => 'email', 't' => 'Email', 'type' => 'email', 'required' => true, 'maxlen' => 160,
             'help' => 'This is the sign-in name. There is no password-reset email — if it is forgotten, an admin sets a new one on this screen.'],
            ['k' => 'name', 't' => 'Name', 'maxlen' => 96],
            ['k' => 'role', 't' => 'Role', 'type' => 'enum',
             'values' => ['editor', 'admin'], 'default' => 'editor',
             'help' => 'An editor writes content. An admin additionally manages people, settings, backups and the destructive actions.'],
            // The key is the column name, not a friendly alias: record_save()
            // writes `INSERT INTO … (k1, k2 …)` straight from these keys, so a
            // spec that says 'password' against a column called password_hash
            // fails with "Unknown column" on every single insert.
            ['k' => 'password_hash', 't' => 'Password', 'type' => 'password', 'maxlen' => 200,
             'help' => 'At least 10 characters. Leave blank on an edit to keep the current one.'],
            ['k' => 'is_active', 't' => 'Active', 'type' => 'bool', 'on' => 'This account can sign in'],
        ],
    ];
}
