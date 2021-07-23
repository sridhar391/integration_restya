<?php
/**
 * Nextcloud - Restyaboard
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Restya <info@restya.com>
 * @copyright Restya 2021
 */

return [
    'routes' => [
        ['name' => 'config#oauthRedirect', 'url' => '/oauth-redirect', 'verb' => 'GET'],
        ['name' => 'config#connectToSoftware', 'url' => '/soft-connect', 'verb' => 'PUT'],
        ['name' => 'config#setConfig', 'url' => '/config', 'verb' => 'PUT'],
        ['name' => 'config#setAdminConfig', 'url' => '/admin-config', 'verb' => 'PUT'],
        ['name' => 'restyaAPI#getNotifications', 'url' => '/notifications', 'verb' => 'GET'],
        ['name' => 'restyaAPI#getRestyaUrl', 'url' => '/url', 'verb' => 'GET'],
        ['name' => 'restyaAPI#getRestyaAvatar', 'url' => '/avatar', 'verb' => 'GET'],
    ]
];
