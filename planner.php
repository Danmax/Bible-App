<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

require_login();

$pageTitle = 'Planner';
$activePage = 'planner';

require_once __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="container">
        <div class="section-heading">
            <p class="eyebrow">Yearly Planner</p>
            <h1>Keep your year intentional</h1>
            <p>Combine Bible reading goals, prayer rhythms, and shared events in one calendar-driven workflow.</p>
        </div>

        <div class="two-column">
            <div class="panel">
                <h2>Yearly goals</h2>
                <div class="list-card">
                    <div>
                        <strong>Read the New Testament</strong>
                        <span>42 of 260 days complete</span>
                    </div>
                    <span class="pill">16%</span>
                </div>
                <div class="list-card">
                    <div>
                        <strong>Attend 12 community events</strong>
                        <span>Service, meetup, and education mix</span>
                    </div>
                    <span class="pill">4 / 12</span>
                </div>
                <div class="list-card">
                    <div>
                        <strong>Weekly family devotion</strong>
                        <span>Build a recurring home rhythm</span>
                    </div>
                    <span class="pill">On Track</span>
                </div>
            </div>

            <div class="panel">
                <h2>Upcoming schedule</h2>
                <div class="list-card">
                    <div>
                        <strong>Sunday Service</strong>
                        <span>Mar 23 at 10:00 AM</span>
                    </div>
                    <span class="pill pill-dark">Service</span>
                </div>
                <div class="list-card">
                    <div>
                        <strong>Zoom Bible Study</strong>
                        <span>Mar 26 at 7:00 PM</span>
                    </div>
                    <span class="pill pill-dark">Zoom</span>
                </div>
                <div class="list-card">
                    <div>
                        <strong>Pot Luck Celebration</strong>
                        <span>Apr 07 at 1:00 PM</span>
                    </div>
                    <span class="pill pill-dark">Community</span>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
