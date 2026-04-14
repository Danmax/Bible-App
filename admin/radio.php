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
                <p>You do not have permission to manage Christian radio stations.</p>
            </div>
        </div>
    </section>
    <?php
    require_once dirname(__DIR__) . '/includes/footer.php';
    exit;
}

function admin_public_radio_url(string $value, bool $allowEmpty = false): ?string
{
    $trimmed = trim($value);

    if ($trimmed === '') {
        return $allowEmpty ? null : null;
    }

    if (!filter_var($trimmed, FILTER_VALIDATE_URL)) {
        return null;
    }

    return $trimmed;
}

function admin_extract_youtube_video_id(string $value): ?string
{
    $trimmed = trim($value);

    if ($trimmed === '') {
        return null;
    }

    // Direct video ID (exactly 11 chars)
    if (preg_match('/^[A-Za-z0-9_-]{11}$/', $trimmed) === 1) {
        return $trimmed;
    }

    $parsedUrl = parse_url($trimmed);

    if (!is_array($parsedUrl)) {
        return null;
    }

    $host = strtolower((string) ($parsedUrl['host'] ?? ''));
    $path = trim((string) ($parsedUrl['path'] ?? ''), '/');

    // youtu.be/VIDEO_ID
    if (str_contains($host, 'youtu.be')) {
        $segment = explode('/', $path)[0] ?? '';
        if (preg_match('/^[A-Za-z0-9_-]{11}$/', $segment) === 1) {
            return $segment;
        }
    }

    // ?v=VIDEO_ID
    parse_str((string) ($parsedUrl['query'] ?? ''), $query);
    $videoId = trim((string) ($query['v'] ?? ''));

    if ($videoId === '') {
        // /embed/ID or /live/ID path segments
        $segments = explode('/', $path);
        foreach (['embed', 'live', 'shorts'] as $prefix) {
            $idx = array_search($prefix, $segments, true);
            if ($idx !== false && isset($segments[$idx + 1])) {
                $videoId = $segments[$idx + 1];
                break;
            }
        }
    }

    if ($videoId === '' || preg_match('/^[A-Za-z0-9_-]{11}$/', $videoId) !== 1) {
        return null;
    }

    return $videoId;
}

function admin_extract_youtube_playlist_id(string $value): ?string
{
    $trimmed = trim($value);

    if ($trimmed === '') {
        return null;
    }

    if (preg_match('/^[A-Za-z0-9_-]{8,80}$/', $trimmed) === 1) {
        return $trimmed;
    }

    $parsedUrl = parse_url($trimmed);

    if (!is_array($parsedUrl)) {
        return null;
    }

    parse_str((string) ($parsedUrl['query'] ?? ''), $query);
    $playlistId = trim((string) ($query['list'] ?? ''));

    if ($playlistId === '' || preg_match('/^[A-Za-z0-9_-]{8,80}$/', $playlistId) !== 1) {
        return null;
    }

    return $playlistId;
}

$pageTitle = 'Admin Radio';
$activePage = 'admin';
$pageError = null;
$stations = [];
$editingStation = null;
$statusOptions = [
    'draft' => 'Draft',
    'published' => 'Published',
    'archived' => 'Archived',
];
$formValues = [
    'name' => '',
    'kind' => 'Music',
    'tagline' => '',
    'stream_url' => '',
    'listen_url' => '',
    'youtube_playlist' => '',
    'youtube_live_video' => '',
    'is_live' => '0',
    'sort_order' => '0',
    'is_featured' => '0',
    'status' => 'published',
];

if (public_radio_stations_available() && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = trim((string) ($_POST['action'] ?? ''));
    $stationId = (int) ($_POST['station_id'] ?? 0);
    $formValues = [
        'name' => trim((string) ($_POST['name'] ?? '')),
        'kind' => trim((string) ($_POST['kind'] ?? 'Music')),
        'tagline' => trim((string) ($_POST['tagline'] ?? '')),
        'stream_url' => trim((string) ($_POST['stream_url'] ?? '')),
        'listen_url' => trim((string) ($_POST['listen_url'] ?? '')),
        'youtube_playlist' => trim((string) ($_POST['youtube_playlist'] ?? '')),
        'youtube_live_video' => trim((string) ($_POST['youtube_live_video'] ?? '')),
        'is_live' => trim((string) ($_POST['is_live'] ?? '0')) === '1' ? '1' : '0',
        'sort_order' => trim((string) ($_POST['sort_order'] ?? '0')),
        'is_featured' => trim((string) ($_POST['is_featured'] ?? '0')) === '1' ? '1' : '0',
        'status' => trim((string) ($_POST['status'] ?? 'published')),
    ];

    try {
        if ($action === 'delete') {
            if ($stationId <= 0) {
                throw new RuntimeException('Select a valid station.');
            }

            delete_public_radio_station($stationId);
            record_audit_event((int) current_user()['id'], 'public_radio_station.deleted', null, [
                'public_radio_station_id' => $stationId,
            ]);
            set_flash('Radio station removed.', 'success');
            redirect('admin/radio.php');
        }

        if ($formValues['name'] === '' || $formValues['kind'] === '' || $formValues['tagline'] === '') {
            throw new RuntimeException('Name, type, and tagline are required.');
        }

        if (!isset($statusOptions[$formValues['status']])) {
            throw new RuntimeException('Select a valid status.');
        }

        $listenUrl = admin_public_radio_url($formValues['listen_url']);
        $streamUrl = admin_public_radio_url($formValues['stream_url'], true);
        $youtubePlaylistId = admin_extract_youtube_playlist_id($formValues['youtube_playlist']);
        $youtubeLiveVideoId = admin_extract_youtube_video_id($formValues['youtube_live_video']);

        if ($listenUrl === null) {
            throw new RuntimeException('Enter a valid official listening URL.');
        }

        if ($formValues['stream_url'] !== '' && $streamUrl === null) {
            throw new RuntimeException('Enter a valid stream URL or leave it blank for a link-only station.');
        }

        if ($formValues['youtube_playlist'] !== '' && $youtubePlaylistId === null) {
            throw new RuntimeException('Enter a valid YouTube playlist URL or playlist ID.');
        }

        if ($formValues['youtube_live_video'] !== '' && $youtubeLiveVideoId === null) {
            throw new RuntimeException('Enter a valid YouTube video URL or video ID for the live stream.');
        }

        $sortOrder = (int) $formValues['sort_order'];

        if ($sortOrder < 0) {
            throw new RuntimeException('Sort order must be zero or higher.');
        }

        if ($action === 'update') {
            if ($stationId <= 0) {
                throw new RuntimeException('Select a valid station.');
            }

            update_public_radio_station(
                $stationId,
                $formValues['name'],
                $formValues['kind'],
                $formValues['tagline'],
                $streamUrl ?? '',
                $listenUrl,
                $youtubePlaylistId,
                $sortOrder,
                $formValues['is_featured'] === '1',
                $formValues['status'],
                $youtubeLiveVideoId,
                $formValues['is_live'] === '1'
            );
            record_audit_event((int) current_user()['id'], 'public_radio_station.updated', null, [
                'public_radio_station_id' => $stationId,
                'status' => $formValues['status'],
            ]);
            set_flash('Radio station updated.', 'success');
            redirect('admin/radio.php');
        }

        $createdStationId = create_public_radio_station(
            (int) current_user()['id'],
            $formValues['name'],
            $formValues['kind'],
            $formValues['tagline'],
            $streamUrl ?? '',
            $listenUrl,
            $youtubePlaylistId,
            $sortOrder,
            $formValues['is_featured'] === '1',
            $formValues['status'],
            $youtubeLiveVideoId,
            $formValues['is_live'] === '1'
        );
        record_audit_event((int) current_user()['id'], 'public_radio_station.created', null, [
            'public_radio_station_id' => $createdStationId,
            'status' => $formValues['status'],
        ]);
        set_flash('Radio station created.', 'success');
        redirect('admin/radio.php');
    } catch (Throwable $exception) {
        $pageError = $exception instanceof RuntimeException
            ? $exception->getMessage()
            : 'Radio station changes could not be saved because the database is unavailable.';
    }
}

if (public_radio_stations_available()) {
    try {
        $editId = (int) ($_GET['edit'] ?? 0);

        if ($editId > 0) {
            $editingStation = fetch_manageable_public_radio_station_by_id($editId);

            if ($editingStation === null) {
                set_flash('That radio station could not be found.', 'warning');
                redirect('admin/radio.php');
            }

            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $formValues = [
                    'name' => (string) ($editingStation['name'] ?? ''),
                    'kind' => (string) ($editingStation['kind'] ?? 'Music'),
                    'tagline' => (string) ($editingStation['tagline'] ?? ''),
                    'stream_url' => (string) ($editingStation['stream_url'] ?? ''),
                    'listen_url' => (string) ($editingStation['listen_url'] ?? ''),
                    'youtube_playlist' => (string) ($editingStation['youtube_playlist_id'] ?? ''),
                    'youtube_live_video' => (string) ($editingStation['youtube_live_video_id'] ?? ''),
                    'is_live' => (int) ($editingStation['is_live'] ?? 0) === 1 ? '1' : '0',
                    'sort_order' => isset($editingStation['sort_order']) ? (string) $editingStation['sort_order'] : '0',
                    'is_featured' => (int) ($editingStation['is_featured'] ?? 0) === 1 ? '1' : '0',
                    'status' => (string) ($editingStation['status'] ?? 'published'),
                ];
            }
        }

        $stations = fetch_manageable_public_radio_stations();
    } catch (Throwable $exception) {
        $pageError = 'Radio stations could not be loaded because the database is unavailable.';
    }
}

require_once dirname(__DIR__) . '/includes/header.php';
?>
<section class="section">
    <div class="container">
        <div class="section-heading section-heading-rich">
            <div>
                <p class="eyebrow">Admin</p>
                <h1>Christian radio management</h1>
                <p>Manage station settings, stream connections, YouTube playlist connections, and official listening links for Good News Radio and Divine Radio.</p>
            </div>

            <div class="hero-actions">
                <a class="button button-primary" href="<?= e(app_url('good-news.php#christian-radio')); ?>">Open Public Page</a>
<a class="button button-secondary" href="<?= e(app_url('admin/index.php')); ?>">Back to Admin</a>
            </div>
        </div>

        <?php if ($pageError): ?>
            <div class="flash flash-warning"><?= e($pageError); ?></div>
        <?php endif; ?>

        <?php if (!public_radio_stations_available()): ?>
            <article class="panel">
                <h2>Christian radio migration required</h2>
                <p>Run <code>sql/add_public_radio_stations.sql</code> to enable admin-managed Christian radio stations.</p>
            </article>
        <?php else: ?>
            <div class="card-grid card-grid-2">
                <article class="panel">
                    <div class="panel-heading">
                        <div>
                            <p class="eyebrow"><?= $editingStation ? 'Edit Station' : 'New Station'; ?></p>
                            <h2><?= $editingStation ? 'Update Christian radio station' : 'Create a Christian radio station'; ?></h2>
                            <p class="muted-copy">Configure the station details and its live connections. Use a stream URL, a YouTube playlist, or leave playback blank for an official link-only station.</p>
                        </div>
                    </div>

                    <form class="form-stack top-gap-sm" method="post">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                        <input type="hidden" name="action" value="<?= e($editingStation ? 'update' : 'create'); ?>">
                        <input type="hidden" name="station_id" value="<?= e($editingStation ? (string) $editingStation['id'] : ''); ?>">

                        <label>
                            <span>Name</span>
                            <input type="text" name="name" value="<?= e($formValues['name']); ?>" placeholder="Moody Radio Praise & Worship" required>
                        </label>

                        <div class="two-column">
                            <label>
                                <span>Type</span>
                                <input type="text" name="kind" value="<?= e($formValues['kind']); ?>" placeholder="Music, Gospel, Teaching" required>
                            </label>

                            <label>
                                <span>Status</span>
                                <select name="status">
                                    <?php foreach ($statusOptions as $value => $label): ?>
                                        <option value="<?= e($value); ?>" <?= $formValues['status'] === $value ? 'selected' : ''; ?>>
                                            <?= e($label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        </div>

                        <label>
                            <span>Tagline</span>
                            <input type="text" name="tagline" value="<?= e($formValues['tagline']); ?>" placeholder="Uplifting worship songs and Christ-centered encouragement." required>
                        </label>

                        <label>
                            <span>Stream URL</span>
                            <input type="url" name="stream_url" value="<?= e($formValues['stream_url']); ?>" placeholder="https://...">
                        </label>

                        <label>
                            <span>Official listening URL</span>
                            <input type="url" name="listen_url" value="<?= e($formValues['listen_url']); ?>" placeholder="https://..." required>
                        </label>

                        <label>
                            <span>YouTube playlist URL or ID</span>
                            <input type="text" name="youtube_playlist" value="<?= e($formValues['youtube_playlist']); ?>" placeholder="PLxxxxxxxxxxxxxxxx or https://www.youtube.com/playlist?list=...">
                        </label>

                        <div class="two-column">
                            <label>
                                <span>YouTube live video URL or ID</span>
                                <input type="text" name="youtube_live_video" value="<?= e($formValues['youtube_live_video']); ?>" placeholder="11-char ID or https://youtube.com/watch?v=...">
                            </label>

                            <label>
                                <span>Live now</span>
                                <select name="is_live">
                                    <option value="0" <?= $formValues['is_live'] === '0' ? 'selected' : ''; ?>>Off Air</option>
                                    <option value="1" <?= $formValues['is_live'] === '1' ? 'selected' : ''; ?>>Live Now</option>
                                </select>
                            </label>
                        </div>

                        <div class="two-column">
                            <label>
                                <span>Sort order</span>
                                <input type="number" min="0" name="sort_order" value="<?= e($formValues['sort_order']); ?>">
                            </label>

                            <label>
                                <span>Featured</span>
                                <select name="is_featured">
                                    <option value="0" <?= $formValues['is_featured'] === '0' ? 'selected' : ''; ?>>Standard</option>
                                    <option value="1" <?= $formValues['is_featured'] === '1' ? 'selected' : ''; ?>>Featured</option>
                                </select>
                            </label>
                        </div>

                        <div class="inline-actions">
                            <button class="button button-primary" type="submit"><?= $editingStation ? 'Save Changes' : 'Create Station'; ?></button>
                            <?php if ($editingStation): ?>
                                <a class="button button-secondary" href="<?= e(app_url('admin/radio.php')); ?>">Cancel</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </article>

                <article class="panel">
                    <div class="panel-heading">
                        <div>
                            <p class="eyebrow">Current Stations</p>
                            <h2>Admin-managed list</h2>
                        </div>
                    </div>

                    <?php if ($stations === []): ?>
                        <p class="empty-state top-gap-sm">No Christian radio stations have been created yet.</p>
                    <?php else: ?>
                        <div class="stack-list top-gap-sm">
                            <?php foreach ($stations as $station): ?>
                                <?php
                                $stationStatus = (string) ($station['status'] ?? 'published');
                                $isStreamable = trim((string) ($station['stream_url'] ?? '')) !== '';
                                $hasPlaylist = trim((string) ($station['youtube_playlist_id'] ?? '')) !== '';
                                $hasLiveVideo = trim((string) ($station['youtube_live_video_id'] ?? '')) !== '';
                                $stationIsLive = (int) ($station['is_live'] ?? 0) === 1;
                                ?>
                                <div class="list-card">
                                    <div>
                                        <strong><?= e((string) $station['name']); ?></strong>
                                        <p class="muted-copy"><?= e((string) ($station['tagline'] ?? '')); ?></p>
                                        <div class="inline-actions">
                                            <span class="pill"><?= e($statusOptions[$stationStatus] ?? 'Published'); ?></span>
                                            <span class="pill pill-dark"><?= e((string) ($station['kind'] ?? 'Music')); ?></span>
                                            <span class="pill"><?= $isStreamable ? 'Caster' : 'Link Only'; ?></span>
                                            <?php if ($hasPlaylist): ?>
                                                <span class="pill">Playlist</span>
                                            <?php endif; ?>
                                            <?php if ($hasLiveVideo): ?>
                                                <span class="pill">Live Video</span>
                                            <?php endif; ?>
                                            <?php if ($stationIsLive): ?>
                                                <span class="radio-live-badge">Live</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="inline-actions">
                                        <a class="button button-secondary" href="<?= e(app_url('admin/radio.php?edit=' . (int) $station['id'])); ?>">Edit</a>
                                        <form method="post">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="station_id" value="<?= e((string) $station['id']); ?>">
                                            <button class="button button-secondary" type="submit">Delete</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </article>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
