<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';

$pageTitle = 'Community';
$activePage = 'community';

require_once dirname(__DIR__) . '/includes/header.php';
?>
<section class="section">
    <div class="container">
        <div class="section-heading">
            <p class="eyebrow">Community Feed</p>
            <h1>Gather around faith, service, learning, and fellowship</h1>
            <p>Plan for church life and broader community rhythms in the same app as your Bible study.</p>
        </div>

        <div class="filter-row" data-filter-group>
            <button class="filter-chip is-active" type="button" data-filter="all">All</button>
            <button class="filter-chip" type="button" data-filter="service">Service</button>
            <button class="filter-chip" type="button" data-filter="meeting">Meeting</button>
            <button class="filter-chip" type="button" data-filter="zoom">Zoom</button>
            <button class="filter-chip" type="button" data-filter="meetup">Dev Meetup</button>
            <button class="filter-chip" type="button" data-filter="career">Career</button>
            <button class="filter-chip" type="button" data-filter="education">Education</button>
            <button class="filter-chip" type="button" data-filter="celebration">Celebration</button>
        </div>

        <div class="card-grid card-grid-3" data-filter-results>
            <article class="event-card" data-category="service">
                <span class="pill">Service</span>
                <h3>Sunday Worship Service</h3>
                <p>Main sanctuary, 10:00 AM. Worship, teaching, and prayer.</p>
                <div class="event-meta">
                    <span>Mar 23</span>
                    <span>On Site</span>
                </div>
            </article>
            <article class="event-card" data-category="zoom">
                <span class="pill">Zoom Call</span>
                <h3>Midweek Bible Study</h3>
                <p>Romans discussion with shared notes and prayer requests.</p>
                <div class="event-meta">
                    <span>Mar 26</span>
                    <span>Online</span>
                </div>
            </article>
            <article class="event-card" data-category="meetup">
                <span class="pill">Dev Meetup</span>
                <h3>Faith and Code Night</h3>
                <p>Christian builders gathering for conversation, demos, and networking.</p>
                <div class="event-meta">
                    <span>Mar 28</span>
                    <span>Community Hall</span>
                </div>
            </article>
            <article class="event-card" data-category="meeting">
                <span class="pill">Meeting</span>
                <h3>Volunteer Team Meeting</h3>
                <p>Review service roles, announcements, and outreach coordination.</p>
                <div class="event-meta">
                    <span>Apr 01</span>
                    <span>Room 204</span>
                </div>
            </article>
            <article class="event-card" data-category="career">
                <span class="pill">Career</span>
                <h3>Career Prayer and Resume Night</h3>
                <p>Prayer support, resume feedback, and job search encouragement.</p>
                <div class="event-meta">
                    <span>Apr 05</span>
                    <span>Learning Center</span>
                </div>
            </article>
            <article class="event-card" data-category="celebration">
                <span class="pill">Celebration</span>
                <h3>Church Family Pot Luck</h3>
                <p>Food, testimony, and fellowship after service.</p>
                <div class="event-meta">
                    <span>Apr 07</span>
                    <span>Fellowship Hall</span>
                </div>
            </article>
        </div>

        <div class="two-column">
            <div class="panel">
                <h2>Community event types</h2>
                <ul class="check-list">
                    <li>Dev meetups and technical fellowship</li>
                    <li>Church meetings and ministry planning</li>
                    <li>Zoom calls for remote discipleship</li>
                    <li>Services, pot lucks, celebrations, and outreach</li>
                    <li>Career nights and education sessions</li>
                </ul>
            </div>

            <div class="panel">
                <h2>Planned workflow</h2>
                <p>Leaders create events, members RSVP, and each event can be copied into a personal planner with reminders.</p>
                <a class="button button-primary" href="<?= e(app_url('planner.php')); ?>">Open Planner</a>
            </div>
        </div>
    </div>
</section>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
