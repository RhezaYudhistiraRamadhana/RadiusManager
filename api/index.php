<?php
require_once __DIR__ . '/auth.php';

apiSuccess([
    'app_name'    => APP_NAME,
    'version'     => APP_VERSION,
    'api_version' => 'v1',
    'status'      => 'online',
    'endpoints'   => [
        [
            'method'      => 'GET',
            'endpoint'    => '/api/users.php',
            'description' => 'List users with pagination, search, and group filter',
            'params'      => ['page', 'per_page', 'q', 'group']
        ],
        [
            'method'      => 'GET',
            'endpoint'    => '/api/users.php?username=:username',
            'description' => 'Get user details, check attributes, reply attributes, group, and profile'
        ],
        [
            'method'      => 'POST',
            'endpoint'    => '/api/users.php',
            'description' => 'Create a new RADIUS user',
            'body'        => ['username', 'password', 'groupname', 'firstname', 'lastname', 'email', 'framed_ip', 'expiration']
        ],
        [
            'method'      => 'PUT',
            'endpoint'    => '/api/users.php?username=:username',
            'description' => 'Update user attributes, group, password, or disable state',
            'body'        => ['password', 'groupname', 'firstname', 'lastname', 'email', 'framed_ip', 'expiration', 'disabled']
        ],
        [
            'method'      => 'DELETE',
            'endpoint'    => '/api/users.php?username=:username',
            'description' => 'Delete user and all associated RADIUS attributes'
        ],
        [
            'method'      => 'GET',
            'endpoint'    => '/api/sessions.php',
            'description' => 'List currently active RADIUS accounting sessions',
            'params'      => ['username', 'nasipaddress']
        ],
        [
            'method'      => 'POST',
            'endpoint'    => '/api/sessions.php',
            'description' => 'Disconnect / kick an active session via CoA',
            'body'        => ['action' => 'disconnect', 'id' => ':radacctid']
        ],
        [
            'method'      => 'GET',
            'endpoint'    => '/api/accounting.php',
            'description' => 'Query session accounting history',
            'params'      => ['from', 'to', 'username', 'nasipaddress', 'page', 'per_page']
        ],
    ]
]);
