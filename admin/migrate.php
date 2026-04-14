<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';

require_login();

if (!current_user_has_role(['admin'])) {
    set_flash('You do not have permission to access the migration system.', 'warning');
    redirect('dashboard.php');
}

$pageTitle = 'Database Migrations';
$activePage = 'admin';
$pageError = null;
$executedMigrations = [];
$pendingMigrations = [];
$sqlDir = dirname(__DIR__) . '/sql';

try {
    // 1. Ensure the migrations tracking table exists
    db()->exec('
        CREATE TABLE IF NOT EXISTS migrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(255) NOT NULL UNIQUE,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ');

    // 2. Fetch already executed migrations
    $statement = db()->query('SELECT migration, executed_at FROM migrations ORDER BY id ASC');
    foreach ($statement->fetchAll() as $row) {
        $executedMigrations[(string) $row['migration']] = (string) $row['executed_at'];
    }

    // 3. Scan the sql/ directory for .sql files
    $allFiles = is_dir($sqlDir) ? scandir($sqlDir) : [];
    $sqlFiles = [];
    foreach ($allFiles as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
            $sqlFiles[] = $file;
        }
    }
    sort($sqlFiles);

    // 4. Identify which files haven't been run yet
    foreach ($sqlFiles as $file) {
        if (!isset($executedMigrations[$file])) {
            $pendingMigrations[] = $file;
        }
    }

    // 5. Handle the migration execution request
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();

        $action = $_POST['action'] ?? '';

        if ($action === 'migrate') {
            if ($pendingMigrations === []) {
                set_flash('No pending migrations to run.', 'info');
                redirect('admin/migrate.php');
            }

            $runCount = 0;
            $pdo = db();

            foreach ($pendingMigrations as $migrationFile) {
                $filePath = $sqlDir . '/' . $migrationFile;
                $sql = file_get_contents($filePath);

                if ($sql === false) {
                    throw new RuntimeException("Could not read migration file: {$migrationFile}");
                }

                try {
                    // Execute the raw SQL file
                    $pdo->exec($sql);

                    // Record the successful execution
                    $insertStmt = $pdo->prepare('INSERT INTO migrations (migration) VALUES (:migration)');
                    $insertStmt->execute(['migration' => $migrationFile]);

                    $runCount++;
                } catch (Throwable $e) {
                    throw new RuntimeException("Migration failed on file [{$migrationFile}]. Error: " . $e->getMessage());
                }
            }

            set_flash("Successfully ran {$runCount} migration(s).", 'success');
            redirect('admin/migrate.php');
        }
    }
} catch (Throwable $e) {
    $pageError = $e->getMessage();
}

require_once dirname(__DIR__) . '/includes/header.php';
?>
<section class="section">
    <div class="container">
        <div class="section-heading section-heading-rich">
            <div>
                <p class="eyebrow">Admin Panel</p>
                <h1>Database Migrations</h1>
                <p>Manage schema changes and database updates securely. Any new <code>.sql</code> files added to the <code>sql/</code> directory will appear here.</p>
            </div>
            
            <div class="hero-actions">
                <a class="button button-secondary" href="<?= e(app_url('dashboard.php')); ?>">Back to Dashboard</a>
            </div>
        </div>

        <?php if ($pageError): ?>
            <div class="flash flash-warning"><?= e($pageError); ?></div>
        <?php endif; ?>

        <div class="two-column top-gap">
            <section class="panel">
                <div class="panel-heading">
                    <div>
                        <h2>Pending Migrations</h2>
                        <p class="muted-copy">These SQL files have not been executed on the database yet.</p>
                    </div>
                    <span class="mini-card"><?= count($pendingMigrations); ?> pending</span>
                </div>

                <?php if ($pendingMigrations === []): ?>
                    <p class="empty-state">The database schema is up to date.</p>
                <?php else: ?>
                    <div class="stack-list top-gap-sm">
                        <?php foreach ($pendingMigrations as $pending): ?>
                            <article class="list-card">
                                <div>
                                    <strong><?= e($pending); ?></strong>
                                </div>
                                <span class="pill pill-dark">Pending</span>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <form method="post" class="top-gap">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                        <input type="hidden" name="action" value="migrate">
                        <button type="submit" class="button button-primary">Run Migrations</button>
                    </form>
                <?php endif; ?>
            </section>

            <section class="panel">
                <div class="panel-heading">
                    <div>
                        <h2>Migration History</h2>
                        <p class="muted-copy">Previously executed migration files.</p>
                    </div>
                    <span class="mini-card"><?= count($executedMigrations); ?> executed</span>
                </div>

                <?php if ($executedMigrations === []): ?>
                    <p class="empty-state">No migrations have been recorded yet.</p>
                <?php else: ?>
                    <div class="stack-list top-gap-sm">
                        <?php foreach (array_reverse($executedMigrations) as $migration => $executedAt): ?>
                            <article class="list-card">
                                <div>
                                    <strong><?= e($migration); ?></strong>
                                    <span class="muted-copy">Ran on <?= e(date('M j, Y g:i A', strtotime($executedAt))); ?></span>
                                </div>
                                <span class="pill">Done</span>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </div>
</section>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>