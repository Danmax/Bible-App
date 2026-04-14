<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';

require_login();

if (!current_user_has_role(['admin'])) {
    http_response_code(403);
    $pageTitle = 'Forbidden';
    $activePage = '';
    require_once dirname(__DIR__) . '/includes/header.php';
    ?>
    <section class="section">
        <div class="container">
            <div class="section-heading">
                <p class="eyebrow">Restricted</p>
                <h1>Admin access required</h1>
                <p>You do not have permission to manage users.</p>
            </div>
        </div>
    </section>
    <?php
    require_once dirname(__DIR__) . '/includes/footer.php';
    exit;
}

require_once dirname(__DIR__) . '/includes/repository.php';

$pageTitle = 'Admin Users';
$activePage = 'admin';
$pageError = null;

$validRoles = ['member', 'leader', 'admin'];
$currentUserId = (int) current_user()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = trim((string) ($_POST['action'] ?? ''));

    try {
        if ($action === 'update-role') {
            $targetUserId = (int) ($_POST['user_id'] ?? 0);
            $newRole = trim((string) ($_POST['role'] ?? ''));

            if ($targetUserId <= 0) {
                throw new RuntimeException('Invalid user selected.');
            }

            if (!in_array($newRole, $validRoles, true)) {
                throw new RuntimeException('Invalid role selected.');
            }

            if ($targetUserId === $currentUserId) {
                throw new RuntimeException('You cannot change your own role.');
            }

            $statement = db()->prepare('UPDATE users SET role = :role WHERE id = :id');
            $statement->execute(['role' => $newRole, 'id' => $targetUserId]);

            set_flash('User role updated.', 'success');
            redirect('admin/users.php' . (isset($_GET['q']) ? '?q=' . urlencode($_GET['q']) : '') . (isset($_GET['page']) ? (isset($_GET['q']) ? '&' : '?') . 'page=' . (int) $_GET['page'] : ''));
        }

        throw new RuntimeException('Unknown action.');
    } catch (Throwable $exception) {
        $pageError = $exception instanceof RuntimeException
            ? $exception->getMessage()
            : 'The change could not be saved because the database is unavailable.';
    }
}

$q = trim((string) ($_GET['q'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = 50;
$offset = ($page - 1) * $limit;

$users = [];
$totalUsers = 0;

try {
    if ($q !== '') {
        $qLike = '%' . $q . '%';

        $countStatement = db()->prepare(
            'SELECT COUNT(*) FROM users WHERE name LIKE :q_like OR email LIKE :q_like2'
        );
        $countStatement->execute(['q_like' => $qLike, 'q_like2' => $qLike]);
        $totalUsers = (int) $countStatement->fetchColumn();

        $statement = db()->prepare(
            'SELECT id, name, email, role, city, created_at
            FROM users
            WHERE name LIKE :q_like OR email LIKE :q_like2
            ORDER BY created_at DESC
            LIMIT 50 OFFSET :offset'
        );
        $statement->bindValue(':q_like', $qLike, PDO::PARAM_STR);
        $statement->bindValue(':q_like2', $qLike, PDO::PARAM_STR);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();
        $users = $statement->fetchAll();
    } else {
        $totalUsers = (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn();

        $statement = db()->prepare(
            'SELECT id, name, email, role, city, created_at
            FROM users
            ORDER BY created_at DESC
            LIMIT 50 OFFSET :offset'
        );
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();
        $users = $statement->fetchAll();
    }
} catch (Throwable $exception) {
    $pageError = 'Users could not be loaded: ' . $exception->getMessage();
}

$totalPages = $totalUsers > 0 ? (int) ceil($totalUsers / $limit) : 1;

require_once dirname(__DIR__) . '/includes/header.php';
?>
<section class="section">
    <div class="container">
        <div class="section-heading section-heading-rich">
            <div>
                <p class="eyebrow">Admin</p>
                <h1>Users</h1>
                <p><?= e(number_format($totalUsers)); ?> total member<?= $totalUsers !== 1 ? 's' : ''; ?> registered.</p>
            </div>

            <div class="hero-actions">
                <a class="button button-secondary" href="<?= e(app_url('admin/index.php')); ?>">Back to Admin</a>
            </div>
        </div>

        <?php if ($pageError): ?>
            <div class="flash flash-warning"><?= e($pageError); ?></div>
        <?php endif; ?>

        <form class="top-gap-sm" method="get" action="<?= e(app_url('admin/users.php')); ?>">
            <div class="inline-actions">
                <input type="text" name="q" value="<?= e($q); ?>" placeholder="Search by name or email&hellip;" style="flex:1">
                <button class="button button-secondary" type="submit">Search</button>
                <?php if ($q !== ''): ?>
                    <a class="button button-secondary" href="<?= e(app_url('admin/users.php')); ?>">Clear</a>
                <?php endif; ?>
            </div>
        </form>

        <?php if ($users === []): ?>
            <p class="empty-state top-gap-sm">No users found<?= $q !== '' ? ' matching ' . e('"' . $q . '"') : ''; ?>.</p>
        <?php else: ?>
            <div class="stack-list top-gap-sm">
                <?php foreach ($users as $user): ?>
                    <?php
                    $userId = (int) $user['id'];
                    $userRole = (string) ($user['role'] ?? 'member');
                    $isSelf = $userId === $currentUserId;
                    $rolePillClass = $userRole === 'member' ? 'pill' : 'pill pill-dark';
                    ?>
                    <div class="list-card">
                        <div>
                            <strong><?= e((string) $user['name']); ?></strong>
                            <p class="muted-copy"><?= e((string) $user['email']); ?></p>
                            <div class="inline-actions">
                                <span class="<?= e($rolePillClass); ?>"><?= e(ucfirst($userRole)); ?></span>
                                <?php if ((string) ($user['city'] ?? '') !== ''): ?>
                                    <span class="pill"><?= e((string) $user['city']); ?></span>
                                <?php endif; ?>
                                <span class="muted-copy">Joined <?= e(date('M j, Y', strtotime((string) $user['created_at']))); ?></span>
                            </div>
                        </div>

                        <?php if ($isSelf): ?>
                            <span class="muted-copy" title="You cannot change your own role.">Your account</span>
                        <?php else: ?>
                            <form class="inline-actions" method="post" action="<?= e(app_url('admin/users.php')); ?>">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                <input type="hidden" name="action" value="update-role">
                                <input type="hidden" name="user_id" value="<?= e((string) $userId); ?>">
                                <select name="role" aria-label="Role for <?= e((string) $user['name']); ?>">
                                    <?php foreach ($validRoles as $roleOption): ?>
                                        <option value="<?= e($roleOption); ?>" <?= $userRole === $roleOption ? 'selected' : ''; ?>>
                                            <?= e(ucfirst($roleOption)); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="button button-secondary" type="submit">Save</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($totalPages > 1): ?>
                <div class="inline-actions top-gap-sm">
                    <?php if ($page > 1): ?>
                        <a class="button button-secondary" href="<?= e(app_url('admin/users.php?' . ($q !== '' ? 'q=' . urlencode($q) . '&' : '') . 'page=' . ($page - 1))); ?>">&larr; Previous</a>
                    <?php endif; ?>
                    <span class="muted-copy">Page <?= e((string) $page); ?> of <?= e((string) $totalPages); ?></span>
                    <?php if ($page < $totalPages): ?>
                        <a class="button button-secondary" href="<?= e(app_url('admin/users.php?' . ($q !== '' ? 'q=' . urlencode($q) . '&' : '') . 'page=' . ($page + 1))); ?>">Next &rarr;</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
