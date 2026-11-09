<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tutor LMS - Login Dashboard</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Animate.css for animations -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <style>
        :root {
            --primary-color: #4a6cf7;
            --secondary-color: #6c757d;
            --admin-color: #dc3545;
            --tutor-color: #17a2b8;
            --parent-color: #28a745;
            --student-color: #ffc107;
            --dark-bg: #1a1d28;
            --card-bg: #222738;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            color: #333;
            min-height: 100vh;
            overflow-x: hidden;
        }

        .dark-mode {
            background-color: var(--dark-bg);
            color: #e9ecef;
        }

        .hero-section {
            background: linear-gradient(135deg, var(--primary-color) 0%, #6a11cb 100%);
            color: white;
            padding: 100px 0 80px;
            border-radius: 0 0 40px 40px;
            margin-bottom: 60px;
            position: relative;
            overflow: hidden;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            width: 300px;
            height: 300px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            top: -150px;
            right: -100px;
        }

        .hero-section::after {
            content: '';
            position: absolute;
            width: 200px;
            height: 200px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.05);
            bottom: -100px;
            left: -50px;
        }

        .logo {
            font-weight: 700;
            font-size: 28px;
            color: white;
            text-decoration: none;
        }

        .logo span {
            color: #ffdd40;
        }

        .page-title {
            font-weight: 700;
            font-size: 3.2rem;
            margin-bottom: 20px;
        }

        .page-subtitle {
            font-size: 1.2rem;
            opacity: 0.9;
            margin-bottom: 30px;
        }

        .shortcut-card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            height: 100%;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            transition: all 0.4s ease;
            border: 1px solid rgba(0, 0, 0, 0.05);
            position: relative;
            overflow: hidden;
        }

        .dark-mode .shortcut-card {
            background: var(--card-bg);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .shortcut-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.12);
        }

        .shortcut-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 25px;
            font-size: 32px;
            color: white;
            position: relative;
            z-index: 1;
        }

        .shortcut-icon::after {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: inherit;
            opacity: 0.2;
            z-index: -1;
            transform: scale(1.2);
        }

        .admin-icon {
            background-color: var(--admin-color);
        }

        .tutor-icon {
            background-color: var(--tutor-color);
        }

        .parent-icon {
            background-color: var(--parent-color);
        }

        .student-icon {
            background-color: var(--student-color);
        }

        .shortcut-title {
            font-weight: 700;
            font-size: 1.5rem;
            margin-bottom: 15px;
        }

        .shortcut-desc {
            color: #6c757d;
            margin-bottom: 25px;
            line-height: 1.6;
        }

        .dark-mode .shortcut-desc {
            color: #a0a7b5;
        }

        .btn-login {
            padding: 10px 25px;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
        }

        .btn-admin {
            background-color: var(--admin-color);
            color: white;
        }

        .btn-tutor {
            background-color: var(--tutor-color);
            color: white;
        }

        .btn-parent {
            background-color: var(--parent-color);
            color: white;
        }

        .btn-student {
            background-color: var(--student-color);
            color: #212529;
        }

        .btn-login:hover {
            transform: translateY(-3px);
            box-shadow: 0 7px 15px rgba(0, 0, 0, 0.1);
        }

        .footer {
            margin-top: 80px;
            padding: 30px 0;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
            color: #6c757d;
        }

        .dark-mode .footer {
            border-top: 1px solid rgba(255, 255, 255, 0.05);
            color: #a0a7b5;
        }

        .mode-toggle {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            cursor: pointer;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .mode-toggle:hover {
            transform: scale(1.1);
        }

        .floating-shapes {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            pointer-events: none;
            z-index: 0;
        }

        .shape {
            position: absolute;
            opacity: 0.1;
            border-radius: 50%;
        }

        .shape-1 {
            width: 80px;
            height: 80px;
            background-color: var(--admin-color);
            top: 10%;
            left: 5%;
            animation: float 20s infinite linear;
        }

        .shape-2 {
            width: 120px;
            height: 120px;
            background-color: var(--tutor-color);
            bottom: 10%;
            right: 10%;
            animation: float 25s infinite linear reverse;
        }

        .shape-3 {
            width: 60px;
            height: 60px;
            background-color: var(--parent-color);
            top: 50%;
            left: 15%;
            animation: float 15s infinite linear;
        }

        .shape-4 {
            width: 100px;
            height: 100px;
            background-color: var(--student-color);
            bottom: 20%;
            left: 10%;
            animation: float 30s infinite linear reverse;
        }

        @keyframes float {
            0% {
                transform: translateY(0) rotate(0deg);
            }

            100% {
                transform: translateY(-100px) rotate(360deg);
            }
        }

        @media (max-width: 768px) {
            .hero-section {
                padding: 60px 0 40px;
            }

            .page-title {
                font-size: 2.5rem;
            }

            .shortcut-card {
                margin-bottom: 30px;
            }
        }
    </style>
</head>

<body>
    <!-- Hero Section -->
    <section class="hero-section">
        <div class="floating-shapes">
            <div class="shape shape-1"></div>
            <div class="shape shape-2"></div>
            <div class="shape shape-3"></div>
            <div class="shape shape-4"></div>
        </div>

        <div class="container position-relative" style="z-index: 1;">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <a href="https://tutor.enlixo.info/" class="logo animate__animated animate__fadeIn">Tutor<span>LMS</span></a>
                    <h1 class="page-title animate__animated animate__fadeInUp">Login Dashboard</h1>
                    <p class="page-subtitle animate__animated animate__fadeInUp animate__delay-1s">
                        Select your role to access the Tutor LMS platform. Quick login shortcuts for administrators, tutors, parents, and students.
                    </p>
                </div>
                <div class="col-lg-4 text-lg-end animate__animated animate__fadeIn">
                    <div class="badge bg-light text-dark p-3 d-inline-block">
                        <i class="fas fa-rocket me-2"></i> Bootstrap 5 Powered
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Shortcuts Section -->
    <section class="shortcuts-section">
        <div class="container">
            <div class="row">
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="shortcut-card animate__animated animate__fadeInLeft">
                        <div class="shortcut-icon admin-icon">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <h3 class="shortcut-title">Administrator</h3>
                        <p class="shortcut-desc">
                            Full system access to manage courses, users, settings, and platform configuration.
                        </p>
                        <a href="https://tutor.enlixo.info/admin" class="btn btn-login btn-admin" target="_blank">
                            <i class="fas fa-sign-in-alt me-2"></i> Admin Login
                        </a>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="shortcut-card animate__animated animate__fadeInLeft animate__delay-1s">
                        <div class="shortcut-icon tutor-icon">
                            <i class="fas fa-chalkboard-teacher"></i>
                        </div>
                        <h3 class="shortcut-title">Tutor / Instructor</h3>
                        <p class="shortcut-desc">
                            Access to create and manage courses, assignments, and interact with students.
                        </p>
                        <a href="https://tutor.enlixo.info/tutor" class="btn btn-login btn-tutor" target="_blank">
                            <i class="fas fa-sign-in-alt me-2"></i> Tutor Login
                        </a>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="shortcut-card animate__animated animate__fadeInRight animate__delay-1s">
                        <div class="shortcut-icon parent-icon">
                            <i class="fas fa-user-friends"></i>
                        </div>
                        <h3 class="shortcut-title">Parent / Guardian</h3>
                        <p class="shortcut-desc">
                            Monitor your child's progress, view grades, and track course completion.
                        </p>
                        <a href="https://tutor.enlixo.info/parent" class="btn btn-login btn-parent" target="_blank">
                            <i class="fas fa-sign-in-alt me-2"></i> Parent Login
                        </a>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="shortcut-card animate__animated animate__fadeInRight">
                        <div class="shortcut-icon student-icon">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <h3 class="shortcut-title">Student</h3>
                        <p class="shortcut-desc">
                            Access your courses, submit assignments, track progress, and interact with tutors.
                        </p>
                        <a href="https://tutor.enlixo.info/portal" class="btn btn-login btn-student" target="_blank">
                            <i class="fas fa-sign-in-alt me-2"></i> Student Login
                        </a>
                    </div>
                </div>
            </div>

            <!-- Additional Info -->
            <div class="row mt-5">
                <div class="col-12">
                    <div class="card border-0 shadow-sm animate__animated animate__fadeInUp animate__delay-2s">
                        <div class="card-body p-4">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h4 class="mb-2">Need help accessing your account?</h4>
                                    <p class="mb-0 text-muted">
                                        Contact support at <strong>support@tutor.enlixo.info</strong> or visit our help center for guidance.
                                    </p>
                                </div>
                                <div class="col-md-4 text-md-end mt-3 mt-md-0">
                                    <a href="https://tutor.enlixo.info/help" class="btn btn-outline-primary" target="_blank">
                                        <i class="fas fa-question-circle me-2"></i> Visit Help Center
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <p>&copy; 2023 Tutor LMS. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p>Designed with <i class="fas fa-heart text-danger"></i> for Tutor LMS</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Dark/Light Mode Toggle -->
    <div class="mode-toggle" id="modeToggle">
        <i class="fas fa-moon" id="modeIcon"></i>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Dark/Light Mode Toggle
        const modeToggle = document.getElementById('modeToggle');
        const modeIcon = document.getElementById('modeIcon');
        const body = document.body;

        // Check for saved theme or prefer-color-scheme
        const prefersDarkScheme = window.matchMedia('(prefers-color-scheme: dark)');
        const currentTheme = localStorage.getItem('theme');

        if (currentTheme === 'dark' || (!currentTheme && prefersDarkScheme.matches)) {
            body.classList.add('dark-mode');
            modeIcon.classList.remove('fa-moon');
            modeIcon.classList.add('fa-sun');
        }

        modeToggle.addEventListener('click', function() {
            body.classList.toggle('dark-mode');

            if (body.classList.contains('dark-mode')) {
                modeIcon.classList.remove('fa-moon');
                modeIcon.classList.add('fa-sun');
                localStorage.setItem('theme', 'dark');
            } else {
                modeIcon.classList.remove('fa-sun');
                modeIcon.classList.add('fa-moon');
                localStorage.setItem('theme', 'light');
            }
        });

        // Add animation to cards on scroll
        document.addEventListener('DOMContentLoaded', function() {
            // Animate cards on load
            const cards = document.querySelectorAll('.shortcut-card');
            cards.forEach((card, index) => {
                // Already animated with animate.css classes
            });

            // Add hover effect with JavaScript for additional interactivity
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.zIndex = '10';
                });

                card.addEventListener('mouseleave', function() {
                    this.style.zIndex = '1';
                });
            });

            // Add click animation to buttons
            const buttons = document.querySelectorAll('.btn-login');
            buttons.forEach(button => {
                button.addEventListener('click', function(e) {
                    // Create ripple effect
                    const ripple = document.createElement('span');
                    const rect = this.getBoundingClientRect();
                    const size = Math.max(rect.width, rect.height);
                    const x = e.clientX - rect.left - size / 2;
                    const y = e.clientY - rect.top - size / 2;

                    ripple.style.width = ripple.style.height = size + 'px';
                    ripple.style.left = x + 'px';
                    ripple.style.top = y + 'px';
                    ripple.classList.add('ripple');

                    this.appendChild(ripple);

                    setTimeout(() => {
                        ripple.remove();
                    }, 600);
                });
            });
        });
    </script>
</body>

</html>