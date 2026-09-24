<?php
require_once __DIR__ . '/auth.php';

$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();
$hasUserinfo = dbTableExists('userinfo');

// Handle GET: List users or get single user
if ($method === 'GET') {
    $username = trim($_GET['username'] ?? '');

    if ($username !== '') {
        // Single user details
        $checkStmt = $db->prepare("SELECT attribute, op, value FROM radcheck WHERE username = ?");
        $checkStmt->execute([$username]);
        $checkAttrs = $checkStmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($checkAttrs)) {
            apiError("User '$username' not found.", 404);
        }

        $replyStmt = $db->prepare("SELECT attribute, op, value FROM radreply WHERE username = ?");
        $replyStmt->execute([$username]);
        $replyAttrs = $replyStmt->fetchAll(PDO::FETCH_ASSOC);

        $groupStmt = $db->prepare("SELECT groupname, priority FROM radusergroup WHERE username = ? ORDER BY priority ASC");
        $groupStmt->execute([$username]);
        $groups = $groupStmt->fetchAll(PDO::FETCH_ASSOC);

        $profile = null;
        if ($hasUserinfo) {
            $uStmt = $db->prepare("SELECT firstname, lastname, email, department FROM userinfo WHERE username = ?");
            $uStmt->execute([$username]);
            $profile = $uStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        $lastSessionStmt = $db->prepare("SELECT * FROM radacct WHERE username = ? ORDER BY radacctid DESC LIMIT 1");
        $lastSessionStmt->execute([$username]);
        $lastSession = $lastSessionStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $isDisabled = false;
        $expiration = null;
        foreach ($checkAttrs as $ca) {
            if ($ca['attribute'] === 'Auth-Type' && strcasecmp($ca['value'], 'Reject') === 0) {
                $isDisabled = true;
            }
            if ($ca['attribute'] === 'Expiration') {
                $expiration = $ca['value'];
            }
        }

        $staticIp = null;
        foreach ($replyAttrs as $ra) {
            if ($ra['attribute'] === 'Framed-IP-Address') {
                $staticIp = $ra['value'];
            }
        }

        apiSuccess([
            'username'         => $username,
            'is_disabled'      => $isDisabled,
            'expiration'       => $expiration,
            'static_ip'        => $staticIp,
            'groups'           => array_column($groups, 'groupname'),
            'profile'          => $profile,
            'check_attributes' => $checkAttrs,
            'reply_attributes' => $replyAttrs,
            'last_session'     => $lastSession,
        ]);
    }

    // List users (paginated)
    $page    = max(1, (int)($_GET['page'] ?? 1));
    $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 25)));
    $offset  = ($page - 1) * $perPage;
    $q       = trim($_GET['q'] ?? '');
    $group   = trim($_GET['group'] ?? '');

    $where = ["1=1"];
    $params = [];

    if ($q !== '') {
        $where[] = "rc.username LIKE ?";
        $params[] = "%$q%";
    }

    if ($group !== '') {
        $where[] = "EXISTS (SELECT 1 FROM radusergroup rug WHERE rug.username = rc.username AND rug.groupname = ?)";
        $params[] = $group;
    }

    $whereSql = implode(' AND ', $where);

    // Total count
    $countStmt = $db->prepare("SELECT COUNT(DISTINCT rc.username) FROM radcheck rc WHERE $whereSql");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    // Paginated fetch of distinct usernames
    $userStmt = $db->prepare("
        SELECT rc.username,
               MAX(CASE WHEN rc.attribute = 'Cleartext-Password' THEN rc.value END) AS password,
               MAX(CASE WHEN rc.attribute = 'Expiration' THEN rc.value END) AS expiration,
               MAX(CASE WHEN rc.attribute = 'Auth-Type' AND rc.value = 'Reject' THEN 1 ELSE 0 END) AS is_disabled
        FROM radcheck rc
        WHERE $whereSql
        GROUP BY rc.username
        ORDER BY rc.username ASC
        LIMIT $perPage OFFSET $offset
    ");
    $userStmt->execute($params);
    $users = $userStmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($users)) {
        $usernames = array_column($users, 'username');
        $placeholders = implode(',', array_fill(0, count($usernames), '?'));

        // Batch groups
        $grpStmt = $db->prepare("SELECT username, groupname FROM radusergroup WHERE username IN ($placeholders)");
        $grpStmt->execute($usernames);
        $grpMap = [];
        while ($r = $grpStmt->fetch(PDO::FETCH_ASSOC)) {
            $grpMap[$r['username']][] = $r['groupname'];
        }

        // Batch profiles
        $profMap = [];
        if ($hasUserinfo) {
            $pStmt = $db->prepare("SELECT username, firstname, lastname, email, department FROM userinfo WHERE username IN ($placeholders)");
            $pStmt->execute($usernames);
            while ($r = $pStmt->fetch(PDO::FETCH_ASSOC)) {
                $profMap[$r['username']] = $r;
            }
        }

        // Batch static IPs
        $ipStmt = $db->prepare("SELECT username, value AS ip FROM radreply WHERE attribute = 'Framed-IP-Address' AND username IN ($placeholders)");
        $ipStmt->execute($usernames);
        $ipMap = $ipStmt->fetchAll(PDO::FETCH_KEY_PAIR);

        foreach ($users as &$u) {
            $u['is_disabled'] = (bool)$u['is_disabled'];
            $u['groups']      = $grpMap[$u['username']] ?? [];
            $u['static_ip']   = $ipMap[$u['username']] ?? null;
            $u['profile']     = $profMap[$u['username']] ?? null;
            unset($u['password']); // Omit cleartext password from bulk list for security
        }
    }

    apiSuccess($users, [
        'total'    => $total,
        'page'     => $page,
        'per_page' => $perPage,
        'pages'    => max(1, (int)ceil($total / $perPage)),
    ]);
}

// Handle POST: Create user
if ($method === 'POST') {
    $input = getJsonInput();

    $username   = trim($input['username'] ?? '');
    $password   = $input['password'] ?? '';
    $groupname  = trim($input['groupname'] ?? '');
    $firstname  = trim($input['firstname'] ?? '');
    $lastname   = trim($input['lastname'] ?? '');
    $email      = trim($input['email'] ?? '');
    $department = trim($input['department'] ?? '');
    $framed_ip  = trim($input['framed_ip'] ?? '');
    $expiration = trim($input['expiration'] ?? '');

    if ($username === '' || $password === '') {
        apiError('Missing required fields: username and password are required.', 422);
    }

    if (!preg_match('/^[a-zA-Z0-9_.@-]{1,64}$/', $username)) {
        apiError('Invalid username format. Allowed: letters, numbers, _, ., @, - (1-64 characters).', 422);
    }

    if ($framed_ip !== '' && !filter_var($framed_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        apiError('Invalid IPv4 address in framed_ip.', 422);
    }

    // Check duplicate
    $chkStmt = $db->prepare("SELECT COUNT(*) FROM radcheck WHERE username = ?");
    $chkStmt->execute([$username]);
    if ((int)$chkStmt->fetchColumn() > 0) {
        apiError("User '$username' already exists.", 409);
    }

    $db->beginTransaction();
    try {
        // 1. Password
        $insRc = $db->prepare("INSERT INTO radcheck (username, attribute, op, value) VALUES (?, 'Cleartext-Password', ':=', ?)");
        $insRc->execute([$username, $password]);

        // 2. Expiration
        if ($expiration !== '') {
            $expDate = date('d M Y 23:59:59', strtotime($expiration));
            $insExp = $db->prepare("INSERT INTO radcheck (username, attribute, op, value) VALUES (?, 'Expiration', ':=', ?)");
            $insExp->execute([$username, $expDate]);
        }

        // 3. Group
        if ($groupname !== '') {
            $insGrp = $db->prepare("INSERT INTO radusergroup (username, groupname, priority) VALUES (?, ?, 1)");
            $insGrp->execute([$username, $groupname]);
        }

        // 4. Static IP
        if ($framed_ip !== '') {
            $insIp = $db->prepare("INSERT INTO radreply (username, attribute, op, value) VALUES (?, 'Framed-IP-Address', ':=', ?)");
            $insIp->execute([$username, $framed_ip]);
        }

        // 5. Userinfo
        if ($hasUserinfo && ($firstname !== '' || $lastname !== '' || $email !== '' || $department !== '')) {
            $insUi = $db->prepare("INSERT INTO userinfo (username, firstname, lastname, email, department) VALUES (?, ?, ?, ?, ?)");
            $insUi->execute([$username, $firstname, $lastname, $email, $department]);
        }

        $db->commit();
        auditLog('api_user_create', $username, "Created user via REST API (Group: $groupname)");

        apiSuccess([
            'username'   => $username,
            'groupname'  => $groupname ?: null,
            'static_ip'  => $framed_ip ?: null,
            'expiration' => $expiration ?: null,
            'profile'    => [
                'firstname'  => $firstname,
                'lastname'   => $lastname,
                'email'      => $email,
                'department' => $department
            ]
        ], null, 201);

    } catch (Exception $e) {
        $db->rollBack();
        apiError('Database error creating user: ' . $e->getMessage(), 500);
    }
}

// Handle PUT: Update user
if ($method === 'PUT') {
    $input = getJsonInput();
    $username = trim($_GET['username'] ?? $input['username'] ?? '');

    if ($username === '') {
        apiError('Username is required for update.', 422);
    }

    $chkStmt = $db->prepare("SELECT COUNT(*) FROM radcheck WHERE username = ?");
    $chkStmt->execute([$username]);
    if ((int)$chkStmt->fetchColumn() === 0) {
        apiError("User '$username' does not exist.", 404);
    }

    $db->beginTransaction();
    try {
        // 1. Update Password
        if (!empty($input['password'])) {
            $updPass = $db->prepare("UPDATE radcheck SET value = ? WHERE username = ? AND attribute = 'Cleartext-Password'");
            $updPass->execute([$input['password'], $username]);
        }

        // 2. Update Group
        if (isset($input['groupname'])) {
            $newGroup = trim($input['groupname']);
            $db->prepare("DELETE FROM radusergroup WHERE username = ?")->execute([$username]);
            if ($newGroup !== '') {
                $db->prepare("INSERT INTO radusergroup (username, groupname, priority) VALUES (?, ?, 1)")->execute([$username, $newGroup]);
            }
        }

        // 3. Update Disabled status
        if (isset($input['disabled'])) {
            $isDisabled = (bool)$input['disabled'];
            $db->prepare("DELETE FROM radcheck WHERE username = ? AND attribute = 'Auth-Type'")->execute([$username]);
            if ($isDisabled) {
                $db->prepare("INSERT INTO radcheck (username, attribute, op, value) VALUES (?, 'Auth-Type', ':=', 'Reject')")->execute([$username]);
            }
        }

        // 4. Update Static IP
        if (isset($input['framed_ip'])) {
            $ip = trim($input['framed_ip']);
            $db->prepare("DELETE FROM radreply WHERE username = ? AND attribute = 'Framed-IP-Address'")->execute([$username]);
            if ($ip !== '') {
                if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    throw new Exception('Invalid IPv4 address.');
                }
                $db->prepare("INSERT INTO radreply (username, attribute, op, value) VALUES (?, 'Framed-IP-Address', ':=', ?)")->execute([$username, $ip]);
            }
        }

        // 5. Update Expiration
        if (isset($input['expiration'])) {
            $exp = trim($input['expiration']);
            $db->prepare("DELETE FROM radcheck WHERE username = ? AND attribute = 'Expiration'")->execute([$username]);
            if ($exp !== '') {
                $expFormatted = date('d M Y 23:59:59', strtotime($exp));
                $db->prepare("INSERT INTO radcheck (username, attribute, op, value) VALUES (?, 'Expiration', ':=', ?)")->execute([$username, $expFormatted]);
            }
        }

        // 6. Update Userinfo
        if ($hasUserinfo && (isset($input['firstname']) || isset($input['lastname']) || isset($input['email']) || isset($input['department']))) {
            $chkUi = $db->prepare("SELECT COUNT(*) FROM userinfo WHERE username = ?");
            $chkUi->execute([$username]);
            if ((int)$chkUi->fetchColumn() > 0) {
                $fields = [];
                $params = [];
                foreach (['firstname', 'lastname', 'email', 'department'] as $f) {
                    if (isset($input[$f])) {
                        $fields[] = "$f = ?";
                        $params[] = trim($input[$f]);
                    }
                }
                if (!empty($fields)) {
                    $params[] = $username;
                    $db->prepare("UPDATE userinfo SET " . implode(', ', $fields) . " WHERE username = ?")->execute($params);
                }
            } else {
                $db->prepare("INSERT INTO userinfo (username, firstname, lastname, email, department) VALUES (?, ?, ?, ?, ?)")
                   ->execute([$username, $input['firstname'] ?? '', $input['lastname'] ?? '', $input['email'] ?? '', $input['department'] ?? '']);
            }
        }

        $db->commit();
        auditLog('api_user_update', $username, 'Updated user via REST API');

        apiSuccess(['username' => $username, 'message' => "User '$username' updated successfully."]);

    } catch (Exception $e) {
        $db->rollBack();
        apiError('Database error updating user: ' . $e->getMessage(), 500);
    }
}

// Handle DELETE: Remove user
if ($method === 'DELETE') {
    $input = getJsonInput();
    $username = trim($_GET['username'] ?? $input['username'] ?? '');

    if ($username === '') {
        apiError('Username is required for deletion.', 422);
    }

    $chkStmt = $db->prepare("SELECT COUNT(*) FROM radcheck WHERE username = ?");
    $chkStmt->execute([$username]);
    if ((int)$chkStmt->fetchColumn() === 0) {
        apiError("User '$username' not found.", 404);
    }

    $db->beginTransaction();
    try {
        $db->prepare("DELETE FROM radcheck WHERE username = ?")->execute([$username]);
        $db->prepare("DELETE FROM radreply WHERE username = ?")->execute([$username]);
        $db->prepare("DELETE FROM radusergroup WHERE username = ?")->execute([$username]);
        if ($hasUserinfo) {
            $db->prepare("DELETE FROM userinfo WHERE username = ?")->execute([$username]);
        }
        if (dbTableExists('rm_vouchers')) {
            $db->prepare("DELETE FROM rm_vouchers WHERE username = ?")->execute([$username]);
        }

        $db->commit();
        auditLog('api_user_delete', $username, 'Deleted user via REST API');

        apiSuccess(['username' => $username, 'message' => "User '$username' and all associated RADIUS attributes deleted successfully."]);

    } catch (Exception $e) {
        $db->rollBack();
        apiError('Database error deleting user: ' . $e->getMessage(), 500);
    }
}

apiError("Method '$method' not allowed.", 405);
