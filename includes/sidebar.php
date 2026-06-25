<?php
// includes/sidebar.php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="col-md-3 col-lg-2 d-md-block sidebar p-0 border-end" id="userSidebar">
    <div class="position-sticky pt-3">
        <!-- Sidebar Toggle (Desktop) -->
        <div class="px-4 mb-4 d-flex justify-content-between align-items-center mobile-hide">
            <span class="fw-bold text-dark small link-text text-uppercase ls-2">Portal Menu</span>
            <button class="btn btn-link link-dark p-0 border-0" id="sidebarToggle" title="Toggle Sidebar">
                <i class="bi bi-list fs-4" id="toggleIcon"></i>
            </button>
        </div>

        <ul class="nav flex-column gap-1">
            <!-- GROUP 1 -->
            <li class="nav-item mb-3">
                <a class="nav-link <?= $current_page == 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php" title="Dashboard">
                    <i class="bi bi-grid-1x2-fill"></i> <span class="link-text fw-semibold">Dashboard</span>
                </a>
                <a class="nav-link <?= $current_page == 'profile.php' ? 'active' : '' ?>" href="profile.php" title="My Profile">
                    <i class="bi bi-person-badge-fill"></i> <span class="link-text fw-semibold">My Profile</span>
                </a>
                <a class="nav-link <?= $current_page == 'preferences.php' ? 'active' : '' ?>" href="preferences.php" title="Partner Preferences">
                    <i class="bi bi-person-gear"></i> <span class="link-text fw-semibold">Preferences</span>
                </a>
            </li>

            <!-- GROUP 2 -->
            <li class="nav-item mb-3">
                <a class="nav-link <?= $current_page == 'search.php' ? 'active' : '' ?>" href="search.php" title="Search Profiles">
                    <i class="bi bi-search-heart-fill"></i> <span class="link-text fw-semibold">Search Profiles</span>
                </a>
                <a class="nav-link <?= $current_page == 'matches.php' ? 'active' : '' ?>" href="matches.php" title="New Matches">
                    <i class="bi bi-heart-fill text-danger"></i> <span class="link-text fw-semibold">New Matches</span>
                </a>
                <a class="nav-link <?= $current_page == 'interests.php' ? 'active' : '' ?>" href="interests.php" title="Sent Interests">
                    <i class="bi bi-envelope-heart-fill text-primary"></i> <span class="link-text fw-semibold">Interests</span>
                </a>
            </li>

            <!-- GROUP 3 -->
            <li class="nav-item mb-3">
                <a class="nav-link <?= $current_page == 'meetings.php' ? 'active' : '' ?>" href="meetings.php" title="My Meetings">
                    <i class="bi bi-calendar-check-fill text-success"></i> <span class="link-text fw-semibold">My Meetings</span>
                </a>
                <a class="nav-link <?= $current_page == 'feed.php' ? 'active' : '' ?>" href="feed.php" title="Community Wall">
                    <i class="bi bi-globe-americas"></i> <span class="link-text fw-semibold">Community Wall</span>
                </a>
                <a class="nav-link <?= $current_page == 'chat.php' ? 'active' : '' ?>" href="chat.php" title="Messages">
                    <i class="bi bi-chat-left-dots-fill text-info"></i> <span class="link-text fw-semibold">Messages</span>
                </a>
            </li>

            <!-- GROUP 4 -->
            <li class="nav-item mb-3">
                <a class="nav-link <?= $current_page == 'shortlist.php' ? 'active' : '' ?>" href="shortlist.php" title="Shortlisted">
                    <i class="bi bi-bookmark-heart-fill text-warning"></i> <span class="link-text fw-semibold">Shortlisted</span>
                </a>
                <a class="nav-link <?= $current_page == 'subscription.php' ? 'active' : '' ?>" href="subscription.php" title="Membership">
                    <i class="bi bi-gem text-primary"></i> <span class="link-text fw-semibold">Membership</span>
                </a>
                <a class="nav-link <?= $current_page == 'payment_history.php' ? 'active' : '' ?>" href="payment_history.php" title="Billing">
                    <i class="bi bi-receipt"></i> <span class="link-text fw-semibold">Billing History</span>
                </a>
            </li>

            <!-- GROUP 5 -->
            <li class="nav-item mt-4 pb-5">
                <a class="nav-link <?= $current_page == 'support.php' ? 'active' : '' ?>" href="support.php" title="Help & Support">
                    <i class="bi bi-headset"></i> <span class="link-text fw-semibold">Help & Support</span>
                </a>
                <a class="nav-link <?= $current_page == 'settings.php' ? 'active' : '' ?>" href="settings.php" title="Settings">
                    <i class="bi bi-gear-fill"></i> <span class="link-text fw-semibold">Settings</span>
                </a>
                <a class="nav-link text-danger mt-3" href="/online-rishta-system/logout.php" title="Logout">
                    <i class="bi bi-box-arrow-right"></i> <span class="link-text fw-semibold">Logout</span>
                </a>
            </li>
        </ul>
    </div>
</div>