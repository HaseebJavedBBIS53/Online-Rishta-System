<?php
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_admin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Online Rishta</title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --sidebar-bg: #111827; /* Midnight Slate */
            --sidebar-width: 280px;
            --sidebar-collapsed-width: 80px;
            --primary-accent: #6366f1;
            --text-muted: #9ca3af;
        }
        body { font-family: 'Inter', sans-serif; background-color: #f3f4f6; overflow-x: hidden; color: #1f2937; }
        
        /* Sidebar Styles */
        #wrapper { display: flex; transition: all 0.3s; }
        #sidebar {
            min-width: var(--sidebar-width);
            max-width: var(--sidebar-width);
            min-height: 100vh;
            background: var(--sidebar-bg);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: fixed;
            z-index: 1000;
            overflow-y: auto;
            max-height: 100vh;
            box-shadow: 4px 0 10px rgba(0,0,0,0.1);
        }
        #sidebar::-webkit-scrollbar { width: 4px; }
        #sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.05); border-radius: 10px; }

        #sidebar.collapsed {
            min-width: var(--sidebar-collapsed-width);
            max-width: var(--sidebar-collapsed-width);
        }
        #sidebar .nav-link {
            color: var(--text-muted);
            padding: 14px 24px;
            margin: 4px 16px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            white-space: nowrap;
            transition: 0.3s;
            font-weight: 500;
            font-size: 0.95rem;
        }
        #sidebar .nav-link:hover { color: #fff; background: rgba(255,255,255,0.08); transform: translateX(5px); }
        #sidebar .nav-link.active { color: #fff; background: var(--primary-accent); box-shadow: 0 10px 15px -3px rgba(99, 102, 241, 0.4); }
        
        #sidebar .nav-link i { font-size: 1.3rem; min-width: 40px; opacity: 0.8; }
        #sidebar .link-text { transition: opacity 0.3s; }
        #sidebar.collapsed .link-text { display: none; }
        #sidebar.collapsed .nav-link { padding: 14px; margin: 4px 12px; justify-content: center; }
        #sidebar.collapsed .nav-link i { min-width: 0; margin-right: 0; }
        #sidebar.collapsed .sidebar-heading { display: none; }

        #sidebar .sidebar-header { border-bottom: 1px solid rgba(255,255,255,0.05); }

        /* Content Area */
        #content {
            width: 100%;
            margin-left: var(--sidebar-width);
            transition: all 0.3s;
        }
        #content.expanded { margin-left: var(--sidebar-collapsed-width); }

        .navbar { background: #fff; padding: 15px 30px; }
        
        /* Dashboard Widgets Styling */
        .card-vibrant { border-radius: 1.25rem; border: none; overflow: hidden; position: relative; color: white; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); min-height: 160px; }
        .card-vibrant-inner { padding: 1.75rem; position: relative; z-index: 1; }
        
        .grad-primary { background: linear-gradient(135deg, #6366f1 0%, #4338ca 100%); }
        .grad-success { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
        .grad-info { background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%); }
        .grad-warning { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
        .grad-danger { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); }
        .grad-rose { background: linear-gradient(135deg, #f43f5e 0%, #e11d48 100%); }
        .grad-emerald { background: linear-gradient(135deg, #34d399 0%, #059669 100%); }
        .grad-purple { background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); }

        .card-vibrant:hover { transform: translateY(-8px); box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1) !important; }
        .ls-1 { letter-spacing: 0.05em; }
        
        /* Simplified Wording labels */
        .sidebar-heading { color: #475569; font-size: 0.75rem; font-weight: 700; letter-spacing: 1px; padding: 20px 25px 10px; }
    </style>
</head>
<body>

<div id="wrapper">
    <!-- Sidebar -->
    <?php $page = basename($_SERVER['PHP_SELF']); ?>
    <nav id="sidebar" class="<?= ($_SESSION['sidebar_collapsed'] ?? false) ? 'collapsed' : '' ?>">
        <div class="sidebar-header p-4 d-flex align-items-center justify-content-between">
            <h5 class="text-white fw-bold mb-0 link-text">ADMIN PANEL</h5>
            <button id="toggleSidebar" class="btn btn-sm btn-link text-white-50 p-0 fs-4 border-0">
                <i class="bi bi-list"></i>
            </button>
        </div>

        <ul class="nav flex-column mt-2">
            <li class="nav-item">
                <a class="nav-link <?= $page == 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php">
                    <i class="bi bi-speedometer2"></i> <span class="link-text">Dashboard</span>
                </a>
            </li>
            
            <?php if (has_permission('manage_users') || has_permission('manage_verifications') || has_permission('manage_subscriptions')): ?>
                <div class="sidebar-heading">MANAGEMENT</div>
                <?php if (has_permission('manage_users')): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $page == 'profile_management.php' || $page == 'user_form.php' || $page == 'user_details.php' ? 'active' : '' ?>" href="profile_management.php">
                        <i class="bi bi-people-fill"></i> <span class="link-text">Members</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (has_permission('manage_verifications')): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $page == 'verifications.php' ? 'active' : '' ?>" href="verifications.php">
                        <i class="bi bi-patch-check-fill"></i> <span class="link-text">Verification Review</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (has_permission('manage_subscriptions')): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $page == 'subscriptions.php' ? 'active' : '' ?>" href="subscriptions.php">
                        <i class="bi bi-gem"></i> <span class="link-text">Membership Plans</span>
                    </a>
                </li>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (has_permission('manage_meetings')): ?>
                <div class="sidebar-heading">ENGAGEMENT</div>
                <li class="nav-item">
                    <a class="nav-link <?= $page == 'meetings.php' ? 'active' : '' ?>" href="meetings.php">
                        <i class="bi bi-calendar-check-fill text-primary"></i> <span class="link-text">Meeting Safety</span>
                    </a>
                </li>
            <?php endif; ?>

            <?php if (has_permission('manage_payments') || has_permission('view_analytics')): ?>
                <div class="sidebar-heading">FINANCIAL & DATA</div>
                <?php if (has_permission('manage_payments')): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $page == 'payments.php' ? 'active' : '' ?>" href="payments.php">
                        <i class="bi bi-wallet2 text-success"></i> <span class="link-text">Payments Tracking</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $page == 'manual_payments.php' ? 'active' : '' ?>" href="manual_payments.php">
                        <i class="bi bi-cash-stack text-success"></i> <span class="link-text">Manual Verification</span>
                        <?php 
                        $pendingCount = $pdo->query("SELECT COUNT(*) FROM manual_payments WHERE status = 'Pending'")->fetchColumn();
                        if ($pendingCount > 0): 
                        ?>
                            <span class="badge bg-danger rounded-pill ms-2"><?= $pendingCount ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (has_permission('view_analytics')): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $page == 'analytics.php' ? 'active' : '' ?>" href="analytics.php">
                        <i class="bi bi-bar-chart-fill text-info"></i> <span class="link-text">Advanced Analytics</span>
                    </a>
                </li>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (has_permission('manage_content')): ?>
                <div class="sidebar-heading">COMMUNICATION</div>
                <li class="nav-item">
                    <a class="nav-link <?= $page == 'announcements.php' ? 'active' : '' ?>" href="announcements.php">
                        <i class="bi bi-broadcast text-warning"></i> <span class="link-text">Broadcast Center</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $page == 'support.php' ? 'active' : '' ?>" href="support.php">
                        <i class="bi bi-chat-left-dots-fill"></i> <span class="link-text">Support Center</span>
                    </a>
                </li>
                
                <div class="sidebar-heading">MODERATION</div>
                <li class="nav-item">
                    <a class="nav-link <?= $page == 'feed_moderation.php' ? 'active' : '' ?>" href="feed_moderation.php">
                        <i class="bi bi-globe-americas text-danger"></i> <span class="link-text">Feed Moderation</span>
                    </a>
                </li>
            <?php endif; ?>

            <?php if (has_permission('manage_reports')): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $page == 'reports.php' ? 'active' : '' ?>" href="reports.php">
                        <i class="bi bi-shield-fill-exclamation"></i> <span class="link-text">Reports & Bans</span>
                    </a>
                </li>
            <?php endif; ?>

            <div class="sidebar-heading">SYSTEM</div>
            <?php if (has_permission('manage_highlights') || has_permission('manage_rbac') || has_permission('manage_settings')): ?>
                <?php if (has_permission('manage_highlights')): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo $page == 'highlighted_profiles.php' ? 'active' : ''; ?>" href="highlighted_profiles.php">
                        <i class="bi bi-star-fill text-warning"></i> <span class="link-text">Highlighted Profiles</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if (has_permission('manage_rbac')): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo $page == 'rbac_management.php' ? 'active' : ''; ?>" href="rbac_management.php">
                        <i class="bi bi-shield-lock-fill text-purple"></i> <span class="link-text">Staff RBAC</span>
                    </a>
                </li>
                <?php endif; ?>

                <?php if (has_permission('manage_settings')): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo $page == 'settings.php' ? 'active' : ''; ?>" href="settings.php">
                        <i class="bi bi-gear-fill"></i> <span class="link-text">General Settings</span>
                    </a>
                </li>
                <?php endif; ?>
            <?php endif; ?>

            <li class="nav-item mt-5">
                <a class="nav-link text-danger" href="/online-rishta-system/logout.php">
                    <i class="bi bi-box-arrow-left"></i> <span class="link-text">Logout</span>
                </a>
            </li>
        </ul>
    </nav>

    <!-- Main Content -->
    <div id="content" class="<?= ($_SESSION['sidebar_collapsed'] ?? false) ? 'expanded' : '' ?>">
        <nav class="navbar navbar-expand-lg">
            <div class="container-fluid">
                <span class="navbar-text fw-bold text-dark d-none d-md-inline">
                    Welcome, <?= htmlspecialchars(explode(' ', $_SESSION['full_name'])[0]) ?>
                </span>
                <div class="ms-auto d-flex align-items-center">
                    <div class="me-4 position-relative">
                        <i class="bi bi-bell-fill fs-5 text-muted"></i>
                        <span class="position-absolute top-0 start-100 translate-middle p-1 bg-danger border border-light rounded-circle"></span>
                    </div>
                    <img src="/online-rishta-system/assets/images/uploads/<?= $_SESSION['profile_pic'] ?? 'default.jpg' ?>" class="rounded-circle shadow-sm" width="40" height="40" style="object-fit:cover;">
                </div>
            </div>
        </nav>
        
        <div class="p-4">
            <?php display_flash(); ?>
