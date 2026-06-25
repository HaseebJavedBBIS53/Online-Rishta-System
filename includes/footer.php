    <!-- ================================================
         PREMIUM FOOTER
    ================================================ -->
    <footer class="premium-footer mt-5">
        <div class="container">
            <div class="row g-5 mb-4">
                <!-- Brand Column -->
                <div class="col-lg-4">
                    <a href="/online-rishta-system/index.php" class="footer-logo d-flex align-items-center gap-2 text-decoration-none mb-4">
                        <i class="bi bi-heart-fill" style="color:#e83e8c; font-size:1.3rem;"></i>
                        ERishta<span style="color:#6366f1;">.</span><span class="logo-pk">PK</span>
                    </a>
                    <p class="footer-desc mb-4">
                        Pakistan's most trusted matrimony platform, connecting families with verified, compatible matches through smart technology and heartfelt care.
                    </p>
                    <div class="d-flex gap-2">
                        <a href="#" class="social-btn" title="Facebook"><i class="bi bi-facebook"></i></a>
                        <a href="#" class="social-btn" title="Instagram"><i class="bi bi-instagram"></i></a>
                        <a href="#" class="social-btn" title="Twitter"><i class="bi bi-twitter-x"></i></a>
                        <a href="#" class="social-btn" title="WhatsApp"><i class="bi bi-whatsapp"></i></a>
                        <a href="#" class="social-btn" title="YouTube"><i class="bi bi-youtube"></i></a>
                    </div>
                </div>

                <!-- Quick Links -->
                <div class="col-6 col-lg-2">
                    <div class="footer-heading">Quick Links</div>
                    <a href="/online-rishta-system/index.php" class="footer-link">Home</a>
                    <a href="/online-rishta-system/index.php#how-it-works" class="footer-link">How It Works</a>
                    <a href="/online-rishta-system/index.php#services" class="footer-link">Services</a>
                    <a href="/online-rishta-system/index.php#premium" class="footer-link">Pricing</a>
                    <a href="/online-rishta-system/index.php#success" class="footer-link">Success Stories</a>
                    <a href="/online-rishta-system/index.php#contact" class="footer-link">Contact</a>
                </div>

                <!-- Account Links -->
                <div class="col-6 col-lg-2">
                    <div class="footer-heading">Account</div>
                    <a href="/online-rishta-system/register.php" class="footer-link">Register Free</a>
                    <a href="/online-rishta-system/login.php" class="footer-link">Login</a>
                    <a href="/online-rishta-system/user/subscription.php" class="footer-link">Membership</a>
                    <a href="/online-rishta-system/user/dashboard.php" class="footer-link">Dashboard</a>
                    <a href="/online-rishta-system/user/preferences.php" class="footer-link">My Preferences</a>
                </div>

                <!-- Contact Info -->
                <div class="col-lg-4">
                    <div class="footer-heading">Stay Connected</div>
                    <div class="d-flex flex-column gap-3 mb-4">
                        <div class="d-flex align-items-start gap-3">
                            <i class="bi bi-geo-alt-fill mt-1" style="color:#e83e8c; font-size:1rem;"></i>
                            <span class="footer-link" style="padding:0;">Karachi, Sindh, Pakistan</span>
                        </div>
                        <div class="d-flex align-items-center gap-3">
                            <i class="bi bi-envelope-fill" style="color:#6366f1; font-size:1rem;"></i>
                            <a href="mailto:contact@erishta.pk" class="footer-link" style="padding:0;">contact@erishta.pk</a>
                        </div>
                        <div class="d-flex align-items-center gap-3">
                            <i class="bi bi-telephone-fill" style="color:#10b981; font-size:1rem;"></i>
                            <a href="tel:+923001234567" class="footer-link" style="padding:0;">+92 300 1234567</a>
                        </div>
                    </div>
                    <!-- Newsletter -->
                    <div style="background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.08); border-radius:16px; padding:20px;">
                        <div style="font-size:0.85rem; font-weight:600; color:rgba(255,255,255,0.7); margin-bottom:12px;">
                            <i class="bi bi-envelope me-2"></i>Newsletter
                        </div>
                        <div class="d-flex gap-2">
                            <input type="email" placeholder="Enter your email" class="form-control form-control-sm" style="background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.1); color:#fff; border-radius:10px; font-family:'Poppins',sans-serif; font-size:0.85rem;">
                            <button class="btn btn-sm flex-shrink-0" style="background:linear-gradient(135deg,#e83e8c,#6366f1); color:#fff; border-radius:10px; padding:6px 16px; border:none; font-weight:600; white-space:nowrap;">
                                Subscribe
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <hr class="footer-divider">

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div class="footer-bottom">
                    © 2026 ERishta.PK — All Rights Reserved. Made with <i class="bi bi-heart-fill" style="color:#e83e8c;"></i> in Pakistan.
                </div>
                <div class="d-flex gap-4">
                    <a href="#" class="footer-link" style="font-size:0.82rem; padding:0;">Privacy Policy</a>
                    <a href="#" class="footer-link" style="font-size:0.82rem; padding:0;">Terms of Service</a>
                    <a href="#" class="footer-link" style="font-size:0.82rem; padding:0;">Cookie Policy</a>
                    <a href="admin/index.php" class="footer-link" style="font-size:0.82rem; padding:0; opacity:0.4;">
                        <i class="bi bi-shield-lock me-1"></i>Staff Portal
                    </a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/online-rishta-system/assets/js/script.js"></script>

    <script>
    // Mobile sidebar toggle (for logged-in user pages)
    const mobileSidebarToggle = document.getElementById('mobileSidebarToggle');
    if (mobileSidebarToggle) {
        mobileSidebarToggle.addEventListener('click', function() {
            const sidebar = document.querySelector('.sidebar');
            if (sidebar) sidebar.classList.toggle('show-mobile');
        });
    }
    </script>
</body>
</html>
