<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/functions.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ERishta.PK — Find Your Perfect Match</title>
    <meta name="description"
        content="Pakistan's most trusted matrimony platform. Find your perfect match with verified profiles, smart matching, and secure communication.">

    <!-- Poppins Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap"
        rel="stylesheet">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/online-rishta-system/assets/css/style.css">

    <style>
        /* ===========================
           GLOBAL FONT & BASE
        =========================== */
        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            color: #1a1a2e;
            background: #f8f9fc;
        }

        /* ===========================
           PREMIUM NAVBAR
        =========================== */
        .premium-navbar {
            background: rgba(255, 255, 255, 0.97);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(0, 0, 0, 0.06);
            padding: 0 0;
            position: sticky;
            top: 0;
            z-index: 1030;
            transition: all 0.3s ease;
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.05);
        }

        .premium-navbar.scrolled {
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
        }

        .navbar-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 72px;
            padding: 0 24px;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Logo */
        .nav-logo {
            display: flex;
            align-items: center;
            gap: 4px;
            text-decoration: none;
            font-weight: 800;
            font-size: 1.4rem;
            letter-spacing: -0.5px;
            color: #1a1a2e;
        }

        .nav-logo .logo-pk {
            color: #e83e8c;
        }

        .nav-logo .logo-dot {
            color: #6366f1;
        }

        /* Nav Links */
        .nav-links-center {
            display: flex;
            align-items: center;
            gap: 4px;
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .nav-links-center .nav-lnk {
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            color: #4a4a6a;
            text-decoration: none;
            transition: all 0.2s ease;
            white-space: nowrap;
        }

        .nav-links-center .nav-lnk:hover,
        .nav-links-center .nav-lnk.active {
            color: #e83e8c;
            background: rgba(232, 62, 140, 0.06);
        }

        /* CTA Buttons */
        .nav-cta {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-nav-login {
            padding: 9px 22px;
            border-radius: 100px;
            font-size: 0.875rem;
            font-weight: 600;
            color: #1a1a2e;
            background: transparent;
            border: 1.5px solid #d1d5db;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .btn-nav-login:hover {
            border-color: #6366f1;
            color: #6366f1;
            background: rgba(99, 102, 241, 0.04);
        }

        .btn-nav-register {
            padding: 9px 22px;
            border-radius: 100px;
            font-size: 0.875rem;
            font-weight: 700;
            color: #fff;
            background: linear-gradient(135deg, #e83e8c 0%, #6366f1 100%);
            border: none;
            text-decoration: none;
            transition: all 0.25s ease;
            box-shadow: 0 4px 15px rgba(232, 62, 140, 0.35);
        }

        .btn-nav-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(232, 62, 140, 0.45);
            color: #fff;
        }

        /* User Avatar */
        .user-avatar-nav {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e83e8c;
        }

        .nav-username {
            font-size: 0.875rem;
            font-weight: 600;
            color: #1a1a2e;
            text-decoration: none;
        }

        .btn-nav-logout {
            padding: 7px 20px;
            border-radius: 100px;
            font-size: 0.85rem;
            font-weight: 600;
            color: #e83e8c;
            background: rgba(232, 62, 140, 0.08);
            border: 1.5px solid rgba(232, 62, 140, 0.3);
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-nav-logout:hover {
            background: #e83e8c;
            color: #fff;
        }

        /* Mobile Hamburger */
        .hamburger-btn {
            display: none;
            background: none;
            border: none;
            padding: 6px;
            border-radius: 8px;
            cursor: pointer;
            color: #1a1a2e;
        }

        .hamburger-btn:hover {
            background: #f3f4f6;
        }

        /* Mobile Menu */
        .mobile-menu {
            display: none;
            flex-direction: column;
            gap: 4px;
            padding: 12px 20px 20px;
            border-top: 1px solid #f1f1f1;
            background: #fff;
        }

        .mobile-menu.open {
            display: flex;
        }

        .mobile-lnk {
            padding: 10px 14px;
            border-radius: 10px;
            font-size: 0.9rem;
            font-weight: 500;
            color: #4a4a6a;
            text-decoration: none;
            transition: all 0.2s;
        }

        .mobile-lnk:hover {
            color: #e83e8c;
            background: rgba(232, 62, 140, 0.06);
        }

        .mobile-divider {
            height: 1px;
            background: #f1f1f1;
            margin: 8px 0;
        }

        /* Responsive */
        @media (max-width: 991px) {
            .nav-links-center {
                display: none !important;
            }

            .nav-cta {
                display: none !important;
            }

            .hamburger-btn {
                display: flex !important;
                align-items: center;
            }
        }

        /* ===========================
           SIDEBAR SYSTEM
        =========================== */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.4);
            z-index: 1045;
            display: none;
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
        }

        .sidebar-overlay.active {
            display: block;
        }

        @media (max-width: 991.98px) {
            .sidebar {
                position: fixed !important;
                left: -280px !important;
                top: 0 !important;
                bottom: 0 !important;
                height: 100vh !important;
                width: 280px !important;
                z-index: 1050 !important;
                box-shadow: 15px 0 30px rgba(0, 0, 0, 0.1);
                overflow-y: auto !important;
                background: white !important;
                padding-top: 20px !important;
                transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                margin: 0 !important;
            }

            .sidebar.show-mobile {
                transform: translateX(280px);
            }

            .sidebar.collapsed {
                width: 280px !important;
                transform: translateX(0);
            }

            .sidebar.collapsed.show-mobile {
                transform: translateX(280px);
            }

            .main-content {
                margin-left: 0 !important;
                width: 100% !important;
                flex: 0 0 100% !important;
                max-width: 100% !important;
                padding: 15px !important;
                margin-top: 10px;
            }

            #sidebarToggle {
                display: none !important;
            }
        }

        /* Global Responsive Spacing */
        .display-4 {
            font-size: 2rem !important;
        }

        .h1,
        h1 {
            font-size: 1.75rem !important;
        }

        .h2,
        h2 {
            font-size: 1.5rem !important;
        }

        .p-5 {
            padding: 1.5rem !important;
        }

        .p-4 {
            padding: 1rem !important;
        }
        }

        .sidebar {
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            min-height: calc(100vh - 72px);
            z-index: 1000;
            background: #ffffff !important;
            border-right: 1px solid rgba(0, 0, 0, 0.08) !important;
        }

        /* Collapsed State: Strictly Icons Only */
        .sidebar.collapsed {
            width: 80px !important;
            flex: 0 0 80px !important;
            max-width: 80px !important;
        }

        .sidebar.collapsed .link-text,
        .sidebar.collapsed .group-header {
            display: none !important;
            opacity: 0;
            visibility: hidden;
        }

        .sidebar.collapsed .nav-link {
            text-align: center;
            justify-content: center;
            padding: 12px 0;
            margin: 4px 10px;
        }

        .sidebar.collapsed .nav-link i {
            margin-right: 0 !important;
            font-size: 1.4rem;
        }

        .group-header {
            letter-spacing: 1.2px;
            margin-top: 15px;
            font-size: 0.65rem;
            color: #9ca3af;
            transition: opacity 0.3s;
        }

        .nav-link {
            border-radius: 0 12px 12px 0; /* Modern capsule shape with left indicator */
            margin: 4px 0 4px 0;
            padding: 10px 24px;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            color: #4b5563;
            display: flex;
            align-items: center;
            border-left: 4px solid transparent; /* Placeholder for active indicator */
        }

        .nav-link i {
            font-size: 1.15rem;
            margin-right: 15px;
            width: 24px;
            text-align: center;
            transition: color 0.2s;
        }

        .nav-link:hover {
            background: rgba(99, 102, 241, 0.04);
            color: #6366f1;
        }

        .nav-link.active {
            background: rgba(99, 102, 241, 0.08) !important;
            color: #6366f1 !important;
            border-left: 4px solid #6366f1 !important;
            font-weight: 600;
        }

        .nav-link.active i {
            color: #6366f1 !important;
        }

        /* Collapsed state adjustments for left indicator */
        .sidebar.collapsed .nav-link {
            border-radius: 0;
            padding: 12px 0;
            margin: 4px 0;
        }

        .sidebar.collapsed .nav-link.active {
            border-left: 4px solid #6366f1 !important;
        }

        #sidebarToggle {
            width: 38px;
            height: 38px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s, color 0.2s;
            border-radius: 10px;
            color: #374151;
        }

        #sidebarToggle:hover {
            background: #f3f4f6;
            color: #6366f1;
        }

        .main-content {
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .card-vibrant {
            transition: box-shadow 0.3s, border-color 0.3s;
            cursor: pointer;
        }

        .card-vibrant:hover {
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08) !important;
            border-color: rgba(99, 102, 241, 0.3) !important;
        }

        .ls-2 {
            letter-spacing: 2px !important;
        }

        /* Utility for touch devices */
        .btn,
        .nav-link,
        select,
        input {
            min-height: 44px;
            display: flex;
            align-items: center;
        }

        .btn {
            justify-content: center;
        }

        select.form-select,
        input.form-control {
            display: block;
        }

        /* Mobile Bottom Nav */
        .mobile-bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: 70px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-top: 1px solid rgba(0, 0, 0, 0.05);
            display: flex;
            justify-content: space-around;
            align-items: center;
            z-index: 1040;
            box-shadow: 0 -5px 20px rgba(0, 0, 0, 0.03);
        }

        .bottom-nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            color: #64748b;
            font-size: 0.75rem;
            font-weight: 700;
            transition: all 0.2s;
            flex: 1;
        }

        .bottom-nav-item i {
            font-size: 1.3rem;
            margin-bottom: 3px;
        }

        .bottom-nav-item.active {
            color: #e83e8c;
        }

        .bottom-nav-item.active i {
            color: #e83e8c;
        }

        @media (max-width: 991px) {
            body {
                padding-bottom: 80px;
            }
        }
    </style>
</head>

<body>

    <!-- ========================
     PREMIUM NAVBAR
======================== -->
    <header class="premium-navbar" id="mainNavbar">
        <div class="navbar-inner">

            <!-- Logo -->
            <a href="/online-rishta-system/index.php" class="nav-logo">
                <i class="bi bi-heart-fill" style="color:#e83e8c; font-size:1.2rem;"></i>
                &nbsp;ERishta<span class="logo-dot">.</span><span class="logo-pk">PK</span>
            </a>

            <!-- Center Nav Links (desktop) -->
            <?php $current_page = basename($_SERVER['PHP_SELF']); ?>
            <ul class="nav-links-center d-none d-lg-flex">
                <li><a href="/online-rishta-system/index.php"
                        class="nav-lnk <?= $current_page === 'index.php' ? 'active' : '' ?>">Home</a></li>
                <li><a href="/online-rishta-system/index.php#how-it-works" class="nav-lnk">How It Works</a></li>
                <li><a href="/online-rishta-system/index.php#services" class="nav-lnk">Services</a></li>
                <li><a href="/online-rishta-system/index.php#premium" class="nav-lnk">Pricing</a></li>
                <li><a href="/online-rishta-system/index.php#success" class="nav-lnk">Success Stories</a></li>
                <li><a href="/online-rishta-system/index.php#contact" class="nav-lnk">Contact</a></li>
            </ul>

            <!-- CTA Area -->
            <div class="nav-cta d-none d-lg-flex">
                <?php if (is_logged_in()): ?>
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin'): ?>
                        <a href="/online-rishta-system/admin/dashboard.php" class="btn-nav-login">Admin Panel</a>
                    <?php else:
                        // Fetch fresh profile pic to avoid caching issues
                        $stmt = $pdo->prepare("SELECT profile_pic FROM users WHERE id = ?");
                        $stmt->execute([$_SESSION['user_id']]);
                        $session_pic = $stmt->fetchColumn() ?: 'default.jpg';
                        $_SESSION['profile_pic'] = $session_pic;
                        ?>
                        <a href="/online-rishta-system/user/dashboard.php"
                            class="d-flex align-items-center gap-2 text-decoration-none">
                            <img src="/online-rishta-system/assets/images/uploads/<?= htmlspecialchars($session_pic) ?>"
                                class="user-avatar-nav" alt="Profile">
                            <span class="nav-username"><?= htmlspecialchars(explode(' ', $_SESSION['full_name'])[0]) ?></span>
                        </a>
                    <?php endif; ?>
                    <a href="/online-rishta-system/logout.php" class="btn-nav-logout">
                        <i class="bi bi-box-arrow-right me-1"></i> Logout
                    </a>
                <?php else: ?>
                    <a href="/online-rishta-system/login.php" class="btn-nav-login">Login</a>
                    <a href="/online-rishta-system/register.php" class="btn-nav-register">
                        <i class="bi bi-stars me-1"></i> Register Free
                    </a>
                <?php endif; ?>
            </div>

            <!-- Mobile Icons: Show ONLY sidebar icon on inner pages, ONLY hamburger on public pages -->
            <div class="d-flex align-items-center gap-2 d-lg-none">
                <?php if (basename($_SERVER['PHP_SELF']) !== 'index.php' && is_logged_in()): ?>
                    <!-- Inner pages: ONLY sidebar icon -->
                    <button class="btn btn-sm btn-light border d-flex align-items-center justify-content-center"
                        id="mobileSidebarToggle" style="border-radius:8px; width: 38px; height: 38px;"
                        aria-label="Open Sidebar">
                        <i class="bi bi-layout-sidebar fs-5"></i>
                    </button>
                <?php else: ?>
                    <!-- Public pages: ONLY hamburger -->
                    <button class="hamburger-btn d-flex align-items-center justify-content-center m-0" id="mobileMenuBtn"
                        onclick="toggleMobileMenu()" aria-label="Menu" style="width: 38px; height: 38px;">
                        <i class="bi bi-list fs-4" id="hamburgerIcon"></i>
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Mobile Dropdown Menu -->
        <div class="mobile-menu" id="mobileMenu">
            <a href="/online-rishta-system/index.php" class="mobile-lnk"><i class="bi bi-house me-2"></i>Home</a>
            <a href="/online-rishta-system/index.php#how-it-works" class="mobile-lnk"><i
                    class="bi bi-question-circle me-2"></i>How It Works</a>
            <a href="/online-rishta-system/index.php#services" class="mobile-lnk"><i
                    class="bi bi-grid me-2"></i>Services</a>
            <a href="/online-rishta-system/index.php#premium" class="mobile-lnk"><i
                    class="bi bi-gem me-2"></i>Pricing</a>
            <a href="/online-rishta-system/index.php#success" class="mobile-lnk"><i class="bi bi-heart me-2"></i>Success
                Stories</a>
            <a href="/online-rishta-system/index.php#contact" class="mobile-lnk"><i
                    class="bi bi-envelope me-2"></i>Contact</a>
            <div class="mobile-divider"></div>
            <?php if (is_logged_in()): ?>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin'): ?>
                    <a href="/online-rishta-system/admin/dashboard.php" class="mobile-lnk"><i
                            class="bi bi-shield me-2"></i>Admin Panel</a>
                <?php else: ?>
                    <a href="/online-rishta-system/user/dashboard.php" class="mobile-lnk"><i class="bi bi-person me-2"></i>My
                        Dashboard</a>
                <?php endif; ?>
                <a href="/online-rishta-system/logout.php" class="mobile-lnk" style="color:#e83e8c;"><i
                        class="bi bi-box-arrow-right me-2"></i>Logout</a>
            <?php else: ?>
                <a href="/online-rishta-system/login.php" class="mobile-lnk"><i
                        class="bi bi-box-arrow-in-right me-2"></i>Login</a>
                <a href="/online-rishta-system/register.php" class="btn-nav-register text-center"
                    style="border-radius:12px; padding:12px;">
                    <i class="bi bi-stars me-1"></i> Register Free
                </a>
            <?php endif; ?>
        </div>
    </header>

    <!-- Flash Messages -->
    <div class="container mt-3">
        <?php display_flash(); ?>
    </div>

    <script>
        function toggleMobileMenu() {
            const menu = document.getElementById('mobileMenu');
            const icon = document.getElementById('hamburgerIcon');
            menu.classList.toggle('open');
            icon.className = menu.classList.contains('open') ? 'bi bi-x-lg fs-4' : 'bi bi-list fs-4';
        }

        // Navbar scroll effect
        window.addEventListener('scroll', function () {
            const nav = document.getElementById('mainNavbar');
            if (nav && window.scrollY > 20) { nav.classList.add('scrolled'); }
            else if (nav) { nav.classList.remove('scrolled'); }
        });

        // Active nav link on scroll
        const sections = document.querySelectorAll('section[id]');
        const navLinks = document.querySelectorAll('.nav-lnk');
        window.addEventListener('scroll', () => {
            let current = '';
            sections.forEach(s => {
                if (window.scrollY >= s.offsetTop - 100) current = s.getAttribute('id');
            });
            navLinks.forEach(link => {
                link.classList.remove('active');
                if (link.getAttribute('href').includes(current)) link.classList.add('active');
            });
        });

        // Mobile Sidebar Toggle Fix
        document.addEventListener('DOMContentLoaded', function () {
            const sidebarToggleBtn = document.getElementById('mobileSidebarToggle');
            const userSidebar = document.getElementById('userSidebar');
            const overlay = document.createElement('div');
            overlay.className = 'sidebar-overlay';
            document.body.appendChild(overlay);

            if (sidebarToggleBtn && userSidebar) {
                sidebarToggleBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    userSidebar.classList.toggle('show-mobile');
                    overlay.classList.toggle('active');
                });

                overlay.addEventListener('click', function () {
                    userSidebar.classList.remove('show-mobile');
                    overlay.classList.remove('active');
                });         }
        });
    </script>

    <?php if (is_logged_in() && basename($_SERVER['PHP_SELF']) !== 'index.php'): ?>
        <nav class="mobile-bottom-nav d-lg-none">
            <a href="/online-rishta-system/user/dashboard.php"
                class="bottom-nav-item <?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>">
                <i class="bi bi-grid-fill"></i>
                <span>Home</span>
            </a>
            <a href="/online-rishta-system/user/search.php"
                class="bottom-nav-item <?= basename($_SERVER['PHP_SELF']) == 'search.php' ? 'active' : '' ?>">
                <i class="bi bi-search"></i>
                <span>Search</span>
            </a>
            <a href="/online-rishta-system/user/chat.php"
                class="bottom-nav-item <?= basename($_SERVER['PHP_SELF']) == 'chat.php' ? 'active' : '' ?>">
                <i class="bi bi-chat-heart-fill"></i>
                <span>Chat</span>
            </a>
            <a href="/online-rishta-system/user/notifications.php"
                class="bottom-nav-item <?= basename($_SERVER['PHP_SELF']) == 'notifications.php' ? 'active' : '' ?>">
                <i class="bi bi-bell-fill"></i>
                <span>Alerts</span>
            </a>
            <a href="/online-rishta-system/user/profile.php"
                class="bottom-nav-item <?= basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : '' ?>">
                <i class="bi bi-person-fill"></i>
                <span>Profile</span>
            </a>
        </nav>
    <?php endif; ?>