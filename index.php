<?php
require_once __DIR__ . '/includes/header.php';
?>

<style>
    /* ================================================
   INDEX PAGE — PREMIUM LANDING STYLES
================================================ */
    :root {
        --pink: #e83e8c;
        --indigo: #6366f1;
        --dark: #1a1a2e;
        --soft-bg: #f8f9fc;
    }

    /* HERO */
    .hero-section {
        min-height: 92vh;
        background: linear-gradient(135deg, #0f0c29 0%, #302b63 50%, #24243e 100%);
        display: flex;
        align-items: center;
        position: relative;
        overflow: hidden;
    }

    .hero-section::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -20%;
        width: 700px;
        height: 700px;
        background: radial-gradient(circle, rgba(99, 102, 241, 0.25) 0%, transparent 60%);
        border-radius: 50%;
        pointer-events: none;
    }

    .hero-section::after {
        content: '';
        position: absolute;
        bottom: -20%;
        right: -10%;
        width: 600px;
        height: 600px;
        background: radial-gradient(circle, rgba(232, 62, 140, 0.2) 0%, transparent 60%);
        border-radius: 50%;
        pointer-events: none;
    }

    .hero-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: rgba(255, 255, 255, 0.08);
        border: 1px solid rgba(255, 255, 255, 0.15);
        color: rgba(255, 255, 255, 0.85);
        padding: 6px 18px;
        border-radius: 100px;
        font-size: 0.82rem;
        font-weight: 500;
        letter-spacing: 0.05em;
        margin-bottom: 24px;
        backdrop-filter: blur(10px);
    }

    .hero-badge .dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: #e83e8c;
        animation: pulse-dot 1.5s infinite;
    }

    @keyframes pulse-dot {

        0%,
        100% {
            opacity: 1;
            transform: scale(1);
        }

        50% {
            opacity: 0.5;
            transform: scale(1.4);
        }
    }

    .hero-title {
        font-size: clamp(2.5rem, 6vw, 4.5rem);
        font-weight: 800;
        line-height: 1.1;
        color: #fff;
        letter-spacing: -1px;
    }

    .hero-title .gradient-text {
        background: linear-gradient(135deg, #f093fb 0%, #e83e8c 50%, #fd7ba4 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .hero-sub {
        font-size: 1.15rem;
        font-weight: 400;
        color: rgba(255, 255, 255, 0.65);
        max-width: 540px;
        line-height: 1.7;
        margin-bottom: 36px;
    }

    .btn-hero-primary {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: linear-gradient(135deg, #e83e8c 0%, #6366f1 100%);
        color: #fff;
        padding: 14px 32px;
        border-radius: 100px;
        font-weight: 700;
        font-size: 1rem;
        text-decoration: none;
        transition: all 0.3s ease;
        box-shadow: 0 8px 30px rgba(232, 62, 140, 0.4);
    }

    .btn-hero-primary:hover {
        transform: translateY(-3px);
        box-shadow: 0 14px 40px rgba(232, 62, 140, 0.5);
        color: #fff;
    }

    .btn-hero-outline {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: rgba(255, 255, 255, 0.08);
        color: #fff;
        padding: 13px 32px;
        border-radius: 100px;
        font-weight: 600;
        font-size: 1rem;
        text-decoration: none;
        border: 1.5px solid rgba(255, 255, 255, 0.2);
        backdrop-filter: blur(10px);
        transition: all 0.3s ease;
    }

    .btn-hero-outline:hover {
        background: rgba(255, 255, 255, 0.15);
        border-color: rgba(255, 255, 255, 0.4);
        transform: translateY(-2px);
        color: #fff;
    }

    .hero-stats {
        display: flex;
        gap: 40px;
        margin-top: 48px;
        padding-top: 40px;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
    }

    .hero-stat-num {
        font-size: 1.85rem;
        font-weight: 800;
        color: #fff;
        line-height: 1;
    }

    .hero-stat-label {
        font-size: 0.82rem;
        color: rgba(255, 255, 255, 0.5);
        margin-top: 4px;
        font-weight: 400;
    }

    .hero-visual {
        position: relative;
        z-index: 2;
    }

    .hero-visual .floating-card {
        background: rgba(255, 255, 255, 0.07);
        backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.12);
        border-radius: 20px;
        padding: 20px;
        animation: float 6s ease-in-out infinite;
    }

    @keyframes float {

        0%,
        100% {
            transform: translateY(0px);
        }

        50% {
            transform: translateY(-20px);
        }
    }

    .hero-visual .floating-card.delay-2 {
        animation-delay: 2s;
    }

    .hero-visual .floating-card.delay-4 {
        animation-delay: 4s;
    }

    .match-card-preview {
        width: 280px;
        background: rgba(255, 255, 255, 0.08);
        backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.12);
        border-radius: 24px;
        padding: 24px;
        color: white;
        margin: 0 auto;
    }

    .match-avatar {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid #e83e8c;
    }

    .compatibility-ring {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: linear-gradient(135deg, #e83e8c, #6366f1);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.85rem;
        font-weight: 700;
        color: #fff;
    }

    /* SECTION COMMON */
    .section-title {
        font-size: 2.2rem;
        font-weight: 800;
        color: var(--dark);
        letter-spacing: -0.5px;
        line-height: 1.2;
    }

    .section-sub {
        font-size: 1rem;
        color: #6b7280;
        font-weight: 400;
        line-height: 1.7;
    }

    .pill-badge {
        display: inline-block;
        background: linear-gradient(135deg, rgba(232, 62, 140, 0.1), rgba(99, 102, 241, 0.1));
        color: #e83e8c;
        padding: 5px 16px;
        border-radius: 100px;
        font-size: 0.78rem;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        border: 1px solid rgba(232, 62, 140, 0.2);
        margin-bottom: 16px;
    }

    /* HOW IT WORKS */
    .step-card {
        background: #fff;
        border-radius: 24px;
        padding: 36px 28px;
        border: 1px solid #f0f0f5;
        transition: all 0.35s ease;
        position: relative;
        overflow: hidden;
    }

    .step-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 4px;
        background: linear-gradient(90deg, #e83e8c, #6366f1);
        opacity: 0;
        transition: opacity 0.3s;
    }

    .step-card:hover {
        transform: translateY(-10px);
        box-shadow: 0 20px 50px rgba(0, 0, 0, 0.08);
    }

    .step-card:hover::before {
        opacity: 1;
    }

    .step-number {
        font-size: 3rem;
        font-weight: 900;
        color: #f0f0f5;
        line-height: 1;
        margin-bottom: 8px;
        font-family: 'Poppins', sans-serif;
    }

    .step-icon {
        width: 64px;
        height: 64px;
        border-radius: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.6rem;
        margin-bottom: 20px;
    }

    /* SERVICES */
    .service-card {
        background: #fff;
        border-radius: 20px;
        padding: 30px 24px;
        border: 1px solid #f0f0f5;
        text-align: center;
        transition: all 0.3s ease;
    }

    .service-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 16px 40px rgba(0, 0, 0, 0.08);
        border-color: rgba(232, 62, 140, 0.2);
    }

    .service-icon {
        width: 70px;
        height: 70px;
        border-radius: 20px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.8rem;
        margin-bottom: 20px;
    }

    /* PROFILE CARDS */
    .premium-profile-card {
        background: #fff;
        border-radius: 24px;
        overflow: hidden;
        border: 1px solid #f0f0f5;
        transition: all 0.35s ease;
        position: relative;
    }

    .premium-profile-card:hover {
        transform: translateY(-12px);
        box-shadow: 0 30px 60px rgba(0, 0, 0, 0.1);
    }

    .premium-profile-card .profile-img {
        height: 260px;
        object-fit: cover;
        width: 100%;
    }

    .premium-profile-card .profile-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(to bottom, transparent 40%, rgba(0, 0, 0, 0.7) 100%);
        pointer-events: none;
    }

    .premium-profile-card .verified-badge {
        position: absolute;
        top: 16px;
        right: 16px;
        background: rgba(255, 255, 255, 0.95);
        color: #059669;
        border-radius: 100px;
        padding: 4px 10px;
        font-size: 0.72rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .premium-profile-card .match-score {
        position: absolute;
        bottom: 130px;
        left: 16px;
        background: linear-gradient(135deg, #e83e8c, #6366f1);
        color: #fff;
        border-radius: 100px;
        padding: 3px 12px;
        font-size: 0.75rem;
        font-weight: 700;
    }

    /* TESTIMONIALS */
    .testimonial-card {
        background: #fff;
        border-radius: 24px;
        padding: 32px;
        border: 1px solid #f0f0f5;
        position: relative;
        transition: all 0.3s ease;
    }

    .testimonial-card:hover {
        box-shadow: 0 16px 40px rgba(0, 0, 0, 0.07);
        transform: translateY(-5px);
    }

    .testimonial-card .quote-icon {
        font-size: 3rem;
        color: #f0f0f5;
        line-height: 1;
        margin-bottom: 16px;
        font-family: Georgia, serif;
    }

    .testimonial-avatar {
        width: 52px;
        height: 52px;
        border-radius: 50%;
        object-fit: cover;
    }

    /* PRICING */
    .pricing-card-premium {
        background: #fff;
        border-radius: 28px;
        padding: 40px 32px;
        border: 1.5px solid #f0f0f5;
        transition: all 0.3s ease;
        position: relative;
    }

    .pricing-card-premium.featured {
        background: linear-gradient(135deg, #1a1a2e 0%, #302b63 100%);
        border-color: transparent;
        transform: scale(1.05);
    }

    .pricing-card-premium:hover {
        box-shadow: 0 20px 50px rgba(0, 0, 0, 0.1);
    }

    .pricing-card-premium.featured:hover {
        box-shadow: 0 20px 50px rgba(99, 102, 241, 0.3);
    }

    /* STATS SECTION */
    .stats-section {
        background: linear-gradient(135deg, #e83e8c 0%, #6366f1 100%);
        color: white;
        padding: 80px 0;
    }

    .stat-item {
        text-align: center;
    }

    .stat-number {
        font-size: 3rem;
        font-weight: 900;
        line-height: 1;
    }

    .stat-label {
        font-size: 0.95rem;
        opacity: 0.75;
        margin-top: 8px;
    }



    /* CTA SECTION */
    .cta-section {
        background: linear-gradient(135deg, #0f0c29 0%, #302b63 100%);
        padding: 100px 0;
        position: relative;
        overflow: hidden;
    }

    .cta-section::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -10%;
        width: 400px;
        height: 400px;
        background: radial-gradient(circle, rgba(232, 62, 140, 0.2) 0%, transparent 70%);
        border-radius: 50%;
    }
</style>

<!-- ================================================
     HERO SECTION
================================================ -->
<section class="hero-section">
    <div class="container position-relative" style="z-index: 2;">
        <div class="row align-items-center g-5 py-5">
            <!-- Left Content -->
            <div class="col-lg-6">
                <div class="hero-badge">
                    <span class="dot"></span> Pakistan's #1 Matrimony Platform
                </div>
                <h1 class="hero-title mb-4">
                    Find Your<br>
                    <span class="gradient-text">Perfect Life Partner</span><br>

                </h1>
                <p class="hero-sub">
                    Join thousands of verified Pakistani families who found their life partners through ERishta. Smart
                    matching. Secure. Private.
                </p>
                <div class="d-flex flex-wrap gap-3">
                    <a href="register.php" class="btn-hero-primary">
                        <i class="bi bi-stars"></i> Start for Free
                    </a>
                    <a href="#how-it-works" class="btn-hero-outline">
                        <i class="bi bi-play-circle"></i> How it Works
                    </a>
                </div>

                <div class="hero-stats">
                    <div>
                        <div class="hero-stat-num">50K+</div>
                        <div class="hero-stat-label">Active Members</div>
                    </div>
                    <div>
                        <div class="hero-stat-num">8K+</div>
                        <div class="hero-stat-label">Matches Made</div>
                    </div>
                    <div>
                        <div class="hero-stat-num">98%</div>
                        <div class="hero-stat-label">Match Satisfaction</div>
                    </div>
                </div>
            </div>

            <!-- Right Visual -->
            <div class="col-lg-6 hero-visual d-flex justify-content-center">
                <div class="position-relative">
                    <!-- Main Match Card -->
                    <div class="match-card-preview floating-card">
                        <div class="d-flex align-items-center gap-3 mb-4">
                            <img src="https://randomuser.me/api/portraits/women/45.jpg" class="match-avatar"
                                alt="Profile">
                            <div>
                                <div style="font-weight:700; font-size:1rem;">Fatima A.</div>
                                <div style="font-size:0.8rem; opacity:0.6;">Karachi, PK</div>
                                <div class="d-flex gap-1 mt-1">
                                    <span
                                        style="background:rgba(255,255,255,0.1); border-radius:100px; padding:2px 10px; font-size:0.75rem;">Doctor</span>
                                    <span
                                        style="background:rgba(255,255,255,0.1); border-radius:100px; padding:2px 10px; font-size:0.75rem;">26
                                        yrs</span>
                                </div>
                            </div>
                            <div class="ms-auto compatibility-ring">94%</div>
                        </div>
                        <div style="height:1px; background:rgba(255,255,255,0.1); margin-bottom:16px;"></div>
                        <div class="d-flex justify-content-between" style="font-size:0.8rem; opacity:0.7;">
                            <span><i class="bi bi-book me-1"></i>MSc Medicine</span>
                            <span><i class="bi bi-heart me-1"></i>Muslim</span>
                        </div>
                        <a href="register.php" class="d-block text-center mt-4"
                            style="background:linear-gradient(135deg,#e83e8c,#6366f1); color:white; padding:10px; border-radius:12px; text-decoration:none; font-weight:600; font-size:0.875rem;">
                            <i class="bi bi-heart me-1"></i> Send Interest
                        </a>
                    </div>

                    <!-- Floating notification -->
                    <div class="floating-card delay-2"
                        style="position:absolute; top:-30px; right:-40px; padding:12px 18px; max-width:190px; font-size:0.8rem; color:rgba(255,255,255,0.9);">
                        <div class="d-flex align-items-center gap-2">
                            <div style="background:#10b981; width:8px; height:8px; border-radius:50%;"></div>
                            <span>New match found!</span>
                        </div>
                        <div style="font-size:0.72rem; opacity:0.6; margin-top:4px;">96% compatibility</div>
                    </div>

                    <!-- Second floating card -->
                    <div class="floating-card delay-4"
                        style="position:absolute; bottom:-30px; left:-30px; padding:12px 18px; color:rgba(255,255,255,0.9);">
                        <div style="font-size:0.78rem;">
                            <i class="bi bi-patch-check-fill me-1" style="color:#e83e8c;"></i>
                            Verified Profile ✓
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Wave Bottom -->
    <div style="position:absolute; bottom:0; left:0; right:0; line-height:0;">
        <svg viewBox="0 0 1440 80" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M0,40 C360,80 1080,0 1440,40 L1440,80 L0,80 Z" fill="#f8f9fc" />
        </svg>
    </div>
</section>

<!-- ================================================
     STATS STRIP
================================================ -->
<section class="stats-section">
    <div class="container">
        <div class="row g-4 text-center">
            <div class="col-6 col-md-3 stat-item">
                <div class="stat-number">50K+</div>
                <div class="stat-label">Registered Members</div>
            </div>
            <div class="col-6 col-md-3 stat-item">
                <div class="stat-number">8K+</div>
                <div class="stat-label">Successful Matches</div>
            </div>
            <div class="col-6 col-md-3 stat-item">
                <div class="stat-number">100+</div>
                <div class="stat-label">Cities Covered</div>
            </div>
            <div class="col-6 col-md-3 stat-item">
                <div class="stat-number">24/7</div>
                <div class="stat-label">Support Available</div>
            </div>
        </div>
    </div>
</section>

<!-- ================================================
     HOW IT WORKS
================================================ -->
<section id="how-it-works" class="py-5" style="background: var(--soft-bg);">
    <div class="container py-5">
        <div class="text-center mb-5">
            <span class="pill-badge">Simple Process</span>
            <h2 class="section-title mt-2">How ERishta Works</h2>
            <p class="section-sub mx-auto" style="max-width:520px;">Three simple steps to find your life partner with
                complete privacy and trust.</p>
        </div>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="step-card">
                    <div class="step-number">01</div>
                    <div class="step-icon" style="background: rgba(232,62,140,0.1);">
                        <i class="bi bi-person-plus-fill" style="color: #e83e8c;"></i>
                    </div>
                    <h4 class="fw-bold mb-3" style="font-size:1.15rem;">Create Your Profile</h4>
                    <p class="text-muted" style="font-size:0.92rem; line-height:1.7;">Register for free and build your
                        detailed profile with photos, education, profession, and family background.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="step-card">
                    <div class="step-number">02</div>
                    <div class="step-icon" style="background: rgba(99,102,241,0.1);">
                        <i class="bi bi-cpu-fill" style="color: #6366f1;"></i>
                    </div>
                    <h4 class="fw-bold mb-3" style="font-size:1.15rem;">Smart Matching Engine</h4>
                    <p class="text-muted" style="font-size:0.92rem; line-height:1.7;">Our AI-powered engine analyzes
                        your preferences and shows compatibility scores to find the most suitable matches.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="step-card">
                    <div class="step-number">03</div>
                    <div class="step-icon" style="background: rgba(16,185,129,0.1);">
                        <i class="bi bi-chat-heart-fill" style="color: #10b981;"></i>
                    </div>
                    <h4 class="fw-bold mb-3" style="font-size:1.15rem;">Connect Securely</h4>
                    <p class="text-muted" style="font-size:0.92rem; line-height:1.7;">Once both parties accept each
                        other's interest, unlock premium chat and share contact info with full family oversight.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ================================================
     SERVICES
================================================ -->
<section id="services" class="py-5 bg-white">
    <div class="container py-5">
        <div class="text-center mb-5">
            <span class="pill-badge">Our Services</span>
            <h2 class="section-title mt-2">Everything You Need<br>to Find Your Rishta</h2>
        </div>
        <div class="row g-4">
            <?php
            $services = [
                ['icon' => 'bi-shield-check', 'color' => '#e83e8c', 'bg' => 'rgba(232,62,140,0.08)', 'title' => 'Verified Profiles', 'desc' => 'Every profile is manually reviewed and verified with supporting documentation.'],
                ['icon' => 'bi-cpu', 'color' => '#6366f1', 'bg' => 'rgba(99,102,241,0.08)', 'title' => 'Smart Matches', 'desc' => 'AI-powered compatibility scores based on family background, education & preferences.'],
                ['icon' => 'bi-chat-square-text', 'color' => '#10b981', 'bg' => 'rgba(16,185,129,0.08)', 'title' => 'Secure Messaging', 'desc' => 'End-to-end encrypted conversations once mutual interest is established.'],
                ['icon' => 'bi-eye-slash', 'color' => '#f59e0b', 'bg' => 'rgba(245,158,11,0.08)', 'title' => 'Privacy Control', 'desc' => 'Control who sees your photos and contact details with granular privacy settings.'],
                ['icon' => 'bi-people', 'color' => '#8b5cf6', 'bg' => 'rgba(139,92,246,0.08)', 'title' => 'Family Connect', 'desc' => 'Involve your family in the search process with family-verified profile features.'],
                ['icon' => 'bi-headset', 'color' => '#ec4899', 'bg' => 'rgba(236,72,153,0.08)', 'title' => '24/7 Support', 'desc' => 'Our dedicated team supports you through every step of your journey.'],
            ];
            foreach ($services as $s): ?>
                <div class="col-md-4">
                    <div class="service-card h-100">
                        <div class="service-icon" style="background: <?= $s['bg'] ?>;">
                            <i class="bi <?= $s['icon'] ?>" style="color: <?= $s['color'] ?>;"></i>
                        </div>
                        <h5 class="fw-bold mb-2" style="font-size:1.05rem;"><?= $s['title'] ?></h5>
                        <p class="text-muted mb-0" style="font-size:0.9rem; line-height:1.7;"><?= $s['desc'] ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ================================================
     FEATURED PROFILES
================================================ -->
<section id="featured" class="py-5" style="background: var(--soft-bg);">
    <div class="container py-5">
        <div class="text-center mb-5">
            <span class="pill-badge">Top Profiles</span>
            <h2 class="section-title mt-2">Featured Members</h2>
            <p class="section-sub">Register to see full profiles, photos, and contact details.</p>
        </div>
        <div class="row g-4">
            <?php
            $demo_profiles = [
                ['name' => 'Ahmed (29)', 'city' => 'Lahore', 'prof' => 'Software Engineer', 'img' => 'https://randomuser.me/api/portraits/men/32.jpg', 'score' => '92%'],
                ['name' => 'Sara (26)', 'city' => 'Karachi', 'prof' => 'Doctor (MBBS)', 'img' => 'https://randomuser.me/api/portraits/women/44.jpg', 'score' => '88%'],
                ['name' => 'Omar (31)', 'city' => 'Islamabad', 'prof' => 'Business Owner', 'img' => 'https://randomuser.me/api/portraits/men/67.jpg', 'score' => '85%'],
                ['name' => 'Aisha (24)', 'city' => 'Peshawar', 'prof' => 'Teacher (M.Ed)', 'img' => 'https://randomuser.me/api/portraits/women/68.jpg', 'score' => '90%'],
            ];
            foreach ($demo_profiles as $p): ?>
                <div class="col-md-3 col-sm-6">
                    <div class="premium-profile-card h-100">
                        <div class="position-relative">
                            <img src="<?= $p['img'] ?>" class="profile-img" alt="<?= $p['name'] ?>">
                            <div class="profile-overlay"></div>
                            <div class="verified-badge">
                                <i class="bi bi-patch-check-fill"></i> Verified
                            </div>
                            <div class="match-score"><?= $p['score'] ?> Match</div>
                        </div>
                        <div class="p-4">
                            <h5 class="fw-bold mb-1" style="font-size:1rem;"><?= $p['name'] ?></h5>
                            <p class="text-muted small mb-3">
                                <i class="bi bi-geo-alt me-1"></i><?= $p['city'] ?> &bull;
                                <i class="bi bi-briefcase ms-1 me-1"></i><?= $p['prof'] ?>
                            </p>
                            <a href="register.php" class="d-block text-center py-2 rounded-pill fw-600"
                                style="background:linear-gradient(135deg,rgba(232,62,140,0.1),rgba(99,102,241,0.1)); color:#e83e8c; font-size:0.88rem; text-decoration:none; font-weight:600; border: 1px solid rgba(232,62,140,0.2);">
                                View Profile
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="text-center mt-5">
            <a href="register.php" class="btn-hero-primary" style="display:inline-flex;">
                <i class="bi bi-person-plus-fill"></i> Browse All Profiles
            </a>
        </div>
    </div>
</section>

<!-- ================================================
     SUCCESS STORIES
================================================ -->
<section id="success" class="py-5 bg-white">
    <div class="container py-5">
        <div class="text-center mb-5">
            <span class="pill-badge">Success Stories</span>
            <h2 class="section-title mt-2">Couples Who Found<br>Love on ERishta</h2>
        </div>
        <div class="row g-4">
            <?php
            $stories = [
                ['quote' => 'ERishta matched us based on our values and backgrounds. Within 6 months we were engaged! The privacy controls and family features made our parents very comfortable.', 'name' => 'Hira & Bilal', 'location' => 'Lahore → Karachi', 'img1' => 'https://randomuser.me/api/portraits/women/32.jpg', 'img2' => 'https://randomuser.me/api/portraits/men/44.jpg'],
                ['quote' => 'I was skeptical at first, but the verified profiles gave me confidence. We matched at 94% compatibility and every detail was accurate. Now we are happily married.', 'name' => 'Amna & Usman', 'location' => 'Islamabad → Peshawar', 'img1' => 'https://randomuser.me/api/portraits/women/55.jpg', 'img2' => 'https://randomuser.me/api/portraits/men/67.jpg'],
                ['quote' => 'The secure messaging system allowed us to talk properly before meeting. Our families appreciated the professional and respectful approach of the platform.', 'name' => 'Sana & Farhan', 'location' => 'Multan → Quetta', 'img1' => 'https://randomuser.me/api/portraits/women/78.jpg', 'img2' => 'https://randomuser.me/api/portraits/men/89.jpg'],
            ];
            foreach ($stories as $story): ?>
                <div class="col-md-4">
                    <div class="testimonial-card h-100">
                        <div class="quote-icon">"</div>
                        <p class="text-muted mb-4" style="font-size:0.9rem; line-height:1.8;"><?= $story['quote'] ?></p>
                        <div class="d-flex align-items-center gap-3 mt-auto">
                            <div class="position-relative">
                                <img src="<?= $story['img1'] ?>" class="testimonial-avatar" alt="">
                                <img src="<?= $story['img2'] ?>" class="testimonial-avatar position-absolute"
                                    style="left:28px; top:0; border:2px solid white;" alt="">
                            </div>
                            <div style="margin-left:36px;">
                                <div class="fw-bold" style="font-size:0.9rem;"><?= $story['name'] ?></div>
                                <div class="text-muted" style="font-size:0.8rem;"><i
                                        class="bi bi-geo-alt me-1"></i><?= $story['location'] ?></div>
                                <div class="d-flex gap-1 mt-1">
                                    <?php for ($i = 0; $i < 5; $i++): ?>
                                        <i class="bi bi-star-fill" style="color:#f59e0b; font-size:0.7rem;"></i>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ================================================
     PRICING
================================================ -->
<section id="premium" class="py-5" style="background: var(--soft-bg);">
    <div class="container py-5">
        <div class="text-center mb-5">
            <span class="pill-badge">Membership Plans</span>
            <h2 class="section-title mt-2">Simple, Transparent Pricing</h2>
            <p class="section-sub">Start free. Upgrade when you're ready.</p>
        </div>
        <div class="row g-4 justify-content-center align-items-center">
            <!-- Free -->
            <div class="col-md-4">
                <div class="pricing-card-premium">
                    <div class="mb-2"><span
                            style="font-size:0.8rem; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; color:#9ca3af;">Free
                            Plan</span></div>
                    <div class="d-flex align-items-baseline gap-1 mb-4">
                        <span style="font-size:2.8rem; font-weight:900; color:#1a1a2e;">$0</span>
                        <span style="color:#9ca3af; font-size:0.9rem;">/forever</span>
                    </div>
                    <ul class="list-unstyled mb-5" style="font-size:0.9rem;">
                        <li class="d-flex align-items-center gap-2 mb-3"><i
                                class="bi bi-check2-circle text-success"></i> 5 Profile Views / Day</li>
                        <li class="d-flex align-items-center gap-2 mb-3 text-muted"><i class="bi bi-x-circle"></i>
                            Blurred Profile Photos</li>
                        <li class="d-flex align-items-center gap-2 mb-3 text-muted"><i class="bi bi-x-circle"></i>
                            Hidden Contact Info</li>
                        <li class="d-flex align-items-center gap-2 text-muted"><i class="bi bi-x-circle"></i> Chat
                            Disabled</li>
                    </ul>
                    <a href="register.php" class="d-block text-center py-3 rounded-pill fw-bold"
                        style="border:1.5px solid #e5e7eb; color:#1a1a2e; text-decoration:none; font-size:0.9rem; transition:all 0.2s;"
                        onmouseover="this.style.borderColor='#6366f1'; this.style.color='#6366f1';"
                        onmouseout="this.style.borderColor='#e5e7eb'; this.style.color='#1a1a2e';">
                        Get Started Free
                    </a>
                </div>
            </div>
            <!-- Premium — Featured -->
            <div class="col-md-4">
                <div class="pricing-card-premium featured">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span
                            style="font-size:0.8rem; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; color:rgba(255,255,255,0.5);">Premium</span>
                        <span
                            style="background:linear-gradient(135deg,#e83e8c,#f59e0b); color:white; border-radius:100px; padding:4px 14px; font-size:0.72rem; font-weight:700;">✦
                            Most Popular</span>
                    </div>
                    <div class="d-flex align-items-baseline gap-1 mb-4">
                        <span style="font-size:2.8rem; font-weight:900; color:#fff;">$19.99</span>
                        <span style="color:rgba(255,255,255,0.4); font-size:0.9rem;">/month</span>
                    </div>
                    <ul class="list-unstyled mb-5" style="font-size:0.9rem; color:rgba(255,255,255,0.8);">
                        <li class="d-flex align-items-center gap-2 mb-3"><i class="bi bi-check2-circle"
                                style="color:#34d399;"></i> Unlimited Profile Views</li>
                        <li class="d-flex align-items-center gap-2 mb-3"><i class="bi bi-check2-circle"
                                style="color:#34d399;"></i> Unblurred Photos</li>
                        <li class="d-flex align-items-center gap-2 mb-3"><i class="bi bi-check2-circle"
                                style="color:#34d399;"></i> Full Contact Info</li>
                        <li class="d-flex align-items-center gap-2"><i class="bi bi-check2-circle"
                                style="color:#34d399;"></i> Chat Feature Unlocked</li>
                    </ul>
                    <a href="register.php" class="d-block text-center py-3 rounded-pill fw-bold"
                        style="background:linear-gradient(135deg,#e83e8c,#6366f1); color:white; text-decoration:none; font-size:0.9rem; box-shadow: 0 8px 25px rgba(232,62,140,0.4);">
                        <i class="bi bi-stars me-1"></i> Get Premium Access
                    </a>
                </div>
            </div>
            <!-- 6-Month -->
            <div class="col-md-4">
                <div class="pricing-card-premium">
                    <div class="mb-2"><span
                            style="font-size:0.8rem; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; color:#9ca3af;">6-Month
                            Bundle</span></div>
                    <div class="d-flex align-items-baseline gap-1 mb-1">
                        <span style="font-size:2.8rem; font-weight:900; color:#1a1a2e;">$89.99</span>
                        <span style="color:#9ca3af; font-size:0.9rem;">/6 months</span>
                    </div>
                    <div class="mb-4"><span
                            style="background:#dcfce7; color:#16a34a; border-radius:100px; padding:3px 12px; font-size:0.75rem; font-weight:700;">Save
                            25%</span></div>
                    <ul class="list-unstyled mb-5" style="font-size:0.9rem;">
                        <li class="d-flex align-items-center gap-2 mb-3"><i
                                class="bi bi-check2-circle text-success"></i> Everything in Premium</li>
                        <li class="d-flex align-items-center gap-2 mb-3"><i
                                class="bi bi-check2-circle text-success"></i> Priority Matching</li>
                        <li class="d-flex align-items-center gap-2 mb-3"><i
                                class="bi bi-check2-circle text-success"></i> Profile Boost</li>
                        <li class="d-flex align-items-center gap-2"><i class="bi bi-check2-circle text-success"></i>
                            Dedicated Support</li>
                    </ul>
                    <a href="register.php" class="d-block text-center py-3 rounded-pill fw-bold"
                        style="border:1.5px solid #e5e7eb; color:#1a1a2e; text-decoration:none; font-size:0.9rem; transition:all 0.2s;"
                        onmouseover="this.style.borderColor='#6366f1'; this.style.color='#6366f1';"
                        onmouseout="this.style.borderColor='#e5e7eb'; this.style.color='#1a1a2e';">
                        Get Best Value
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ================================================
     CTA SECTION
================================================ -->
<section class="cta-section">
    <div class="container text-center position-relative" style="z-index:2;">
        <span class="pill-badge"
            style="background:rgba(255,255,255,0.08); color:rgba(255,255,255,0.7); border-color:rgba(255,255,255,0.15);">Join
            Us Today</span>
        <h2 class="section-title mt-3 mb-4" style="color:#fff; font-size: clamp(1.8rem, 4vw, 3rem);">Your Perfect
            Match<br>is Waiting for You</h2>
        <p style="color:rgba(255,255,255,0.55); max-width:480px; margin:0 auto 40px; font-size:1rem; line-height:1.7;">
            Thousands of families have already trusted ERishta. Take the first step today — it's completely free to
            start.</p>
        <div class="d-flex flex-wrap justify-content-center gap-3">
            <a href="register.php" class="btn-hero-primary">
                <i class="bi bi-stars"></i> Create Free Account
            </a>
            <a href="login.php" class="btn-hero-outline">
                <i class="bi bi-box-arrow-in-right"></i> Sign In
            </a>
        </div>
    </div>
</section>

<!-- ================================================
     CONTACT SECTION
================================================ -->
<section id="contact" class="py-5 bg-white">
    <div class="container py-5">
        <div class="row g-5 align-items-center">
            <div class="col-lg-5">
                <span class="pill-badge">Get in Touch</span>
                <h2 class="section-title mt-2 mb-4">We're Here to Help</h2>
                <p class="section-sub mb-5">Have questions? Our team is available 24/7 to assist you find your perfect
                    match.</p>
                <div class="d-flex flex-column gap-4">
                    <div class="d-flex align-items-center gap-3">
                        <div
                            style="width:50px;height:50px;border-radius:16px;background:rgba(232,62,140,0.08);display:flex;align-items:center;justify-content:center;color:#e83e8c;font-size:1.3rem;">
                            <i class="bi bi-envelope-fill"></i>
                        </div>
                        <div>
                            <div style="font-size:0.8rem;color:#9ca3af;font-weight:600;">Email Us</div>
                            <div style="font-size:0.95rem;font-weight:600;">contact@erishta.pk</div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <div
                            style="width:50px;height:50px;border-radius:16px;background:rgba(99,102,241,0.08);display:flex;align-items:center;justify-content:center;color:#6366f1;font-size:1.3rem;">
                            <i class="bi bi-telephone-fill"></i>
                        </div>
                        <div>
                            <div style="font-size:0.8rem;color:#9ca3af;font-weight:600;">Call Us</div>
                            <div style="font-size:0.95rem;font-weight:600;">+92 300 1234567</div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <div
                            style="width:50px;height:50px;border-radius:16px;background:rgba(16,185,129,0.08);display:flex;align-items:center;justify-content:center;color:#10b981;font-size:1.3rem;">
                            <i class="bi bi-geo-alt-fill"></i>
                        </div>
                        <div>
                            <div style="font-size:0.8rem;color:#9ca3af;font-weight:600;">Location</div>
                            <div style="font-size:0.95rem;font-weight:600;">Karachi, Sindh, Pakistan</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-7">
                <div style="background:#f8f9fc; border-radius:28px; padding:40px;">
                    <h5 class="fw-bold mb-4">Send us a Message</h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <input type="text" class="form-control" placeholder="Your Name"
                                style="border-radius:12px; border:1.5px solid #e5e7eb; padding:12px 16px; font-family:'Poppins',sans-serif; font-size:0.9rem;">
                        </div>
                        <div class="col-md-6">
                            <input type="email" class="form-control" placeholder="Email Address"
                                style="border-radius:12px; border:1.5px solid #e5e7eb; padding:12px 16px; font-family:'Poppins',sans-serif; font-size:0.9rem;">
                        </div>
                        <div class="col-12">
                            <input type="text" class="form-control" placeholder="Subject"
                                style="border-radius:12px; border:1.5px solid #e5e7eb; padding:12px 16px; font-family:'Poppins',sans-serif; font-size:0.9rem;">
                        </div>
                        <div class="col-12">
                            <textarea rows="4" class="form-control" placeholder="Your message..."
                                style="border-radius:12px; border:1.5px solid #e5e7eb; padding:12px 16px; font-family:'Poppins',sans-serif; font-size:0.9rem; resize:none;"></textarea>
                        </div>
                        <div class="col-12">
                            <button class="btn-nav-register w-100 py-3"
                                style="border-radius:14px; font-size:1rem; display:block; text-align:center; cursor:pointer; border:none;">
                                <i class="bi bi-send me-2"></i> Send Message
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>