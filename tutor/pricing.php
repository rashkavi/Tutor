<?php
require_once '../includes/UserClass.php';
require_once '../includes/UserPermissionsClass.php';
require_once "../includes/connection.php";
require_once "../includes/Project.php";
if (!SessionUser::hasValidSession()) {
    header("Location: ./index");
} else {

    $sessionUserObject = SessionUser::getCurrentUserObject('Tutor');
    UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

    $class_id = 0;

    if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['class-id']) && !empty($_GET['class-id'])) {

        $class_id = $_GET["class-id"];

        $query = "SELECT * FROM `classes` WHERE `class_id` = '" . $class_id . "'";
        if (SessionUser::ifUserIsTutor()) {
            $query .= " AND `tutor_id`='" . $sessionUserObject['user_id'] . "'";
        }
        $class = Database::search($query);
        if ($class->num_rows == 0) {
            header("Location: ./classes");
        } else {
            $classData = $class->fetch_assoc();
        }
    }

    $selectedDate = "";
    if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['date']) && !empty($_GET['date'])) {
        $selectedDate = $_GET["date"];
    }
?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="robots" content="noindex, nofollow">
        <title>Pricing - Tutor Dashboard | <?= Project::getBaseName(); ?></title>

        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/style.css?v=<?= Project::getVersion() ?>">
        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/skeleton.css?v=<?= Project::getVersion() ?>">
        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
        <link href="<?= Project::getAssetsPath() ?>/plugins/cropperjs/cropper.min.css" rel="stylesheet">

        <script src="<?= Project::getAssetsPath() ?>/plugins/cropperjs/cropper.min.js"></script>
        <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
        <script src="<?= Project::getAssetsPath() ?>/js/init.js?v=<?= Project::getVersion() ?>"></script>
    </head>

    <body>
        <style>
            .enlixo-plan-section {
                background:
                    radial-gradient(circle at top left, color-mix(in srgb, var(--dark-theme) 10%, transparent), transparent 30%),
                    radial-gradient(circle at top right, color-mix(in srgb, var(--highlight-theme) 10%, transparent), transparent 32%),
                    linear-gradient(180deg, var(--body-color) 0%, var(--custom-white) 100%);
                position: relative;
                overflow: hidden;
            }

            .plan-mini-badge {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 7px 14px;
                border-radius: 999px;
                background: var(--light-theme);
                color: var(--dark-theme);
                font-size: 12px;
                font-weight: 800;
                letter-spacing: .3px;
                margin-bottom: 14px;
                border: 1px solid var(--line-color);
            }

            .plan-main-title {
                font-size: clamp(2rem, 4vw, 3.2rem);
                font-weight: 900;
                color: var(--dark-color);
                line-height: 1.1;
                margin-bottom: 14px;
            }

            .plan-main-text {
                max-width: 720px;
                margin: 0 auto;
                font-size: 15px;
                line-height: 1.8;
                color: var(--sub-text);
            }

            .pricing-card {
                position: relative;
                height: 100%;
                padding: 28px;
                border-radius: 28px;
                background: linear-gradient(180deg, var(--custom-white) 0%, var(--white) 100%);
                border: 1px solid var(--line-color);
                box-shadow: 0 18px 60px rgba(15, 23, 42, 0.08);
                transition: all .28s ease;
                overflow: hidden;
            }

            .pricing-card:hover {
                transform: translateY(-8px);
                box-shadow: 0 28px 80px rgba(15, 23, 42, 0.14);
            }

            .plan-glow {
                position: absolute;
                inset: auto auto -70px -70px;
                width: 180px;
                height: 180px;
                border-radius: 50%;
                opacity: .16;
                filter: blur(18px);
            }

            .pricing-card-start .plan-glow {
                background: var(--dark-green);
            }

            .pricing-card-grow .plan-glow {
                background: var(--dark-theme);
            }

            .pricing-card-pro .plan-glow {
                background: var(--dark-purple);
            }

            .active-plan {
                border: 1px solid color-mix(in srgb, var(--dark-theme) 30%, var(--line-color));
                transform: translateY(-10px);
                box-shadow: 0 30px 90px color-mix(in srgb, var(--dark-theme) 18%, transparent);
            }

            .popular-ribbon {
                position: absolute;
                top: 18px;
                right: 18px;
                padding: 8px 14px;
                border-radius: 999px;
                background: linear-gradient(135deg, var(--dark-theme), var(--highlight-theme));
                color: #fff;
                font-size: 11px;
                font-weight: 800;
                letter-spacing: .4px;
                box-shadow: 0 12px 25px color-mix(in srgb, var(--dark-theme) 28%, transparent);
            }

            .pricing-top {
                position: relative;
                z-index: 2;
            }

            .plan-icon-wrap {
                width: 54px;
                height: 54px;
                border-radius: 18px;
                display: flex;
                align-items: center;
                justify-content: center;
                background: var(--light-theme);
                color: var(--dark-theme);
                margin-bottom: 18px;
                border: 1px solid var(--line-color);
            }

            .plan-name-row {
                display: flex;
                align-items: center;
                gap: 10px;
                margin-bottom: 8px;
                flex-wrap: wrap;
            }

            .plan-name {
                margin: 0;
                font-size: 28px;
                font-weight: 900;
                color: var(--dark-color);
                letter-spacing: .3px;
            }

            .plan-badge {
                padding: 6px 11px;
                border-radius: 999px;
                font-size: 11px;
                font-weight: 800;
                border: 1px solid transparent;
            }

            .plan-badge-start {
                background: var(--light-green);
                color: var(--dark-green);
            }

            .plan-badge-grow {
                background: var(--light-blue);
                color: var(--dark-blue);
            }

            .plan-badge-pro {
                background: var(--light-purple);
                color: var(--dark-purple);
            }

            .plan-desc {
                min-height: 48px;
                font-size: 14px;
                line-height: 1.7;
                color: var(--sub-text);
                margin-bottom: 20px;
            }

            .plan-price {
                display: flex;
                align-items: flex-end;
                gap: 8px;
                margin-bottom: 12px;
                flex-wrap: wrap;
            }

            .price-currency {
                font-size: 16px;
                font-weight: 800;
                color: var(--sub-topic);
                margin-bottom: 6px;
            }

            .price-value {
                font-size: 36px;
                line-height: 1;
                font-weight: 900;
                color: var(--dark-color);
            }

            .price-duration {
                font-size: 14px;
                color: var(--sub-text);
                margin-bottom: 4px;
            }

            .student-limit {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                min-height: 38px;
                padding: 9px 14px;
                border-radius: 999px;
                background: var(--body-color);
                color: var(--main-text-color);
                font-size: 13px;
                font-weight: 700;
                border: 1px solid var(--line-color);
            }

            .plan-divider {
                height: 1px;
                background: linear-gradient(to right, transparent, var(--line-color), transparent);
                margin: 24px 0;
            }

            .plan-feature-list {
                display: flex;
                flex-direction: column;
                gap: 12px;
            }

            .plan-feature-item {
                display: flex;
                align-items: flex-start;
                gap: 10px;
                color: var(--main-text-color);
                font-size: 14px;
                font-weight: 600;
                line-height: 1.5;
            }

            .plan-feature-item iconify-icon {
                flex-shrink: 0;
                margin-top: 2px;
                color: var(--dark-theme);
            }

            .strong-feature {
                font-weight: 800;
                color: var(--dark-color);
            }

            .feature-muted {
                color: var(--sub-text);
                opacity: .75;
            }

            .feature-muted iconify-icon {
                color: var(--sub-text);
            }

            .addon-feature iconify-icon {
                color: var(--dark-purple);
            }

            .plan-footer {
                margin-top: 26px;
            }

            .plan-btn {
                min-height: 52px;
                border-radius: 16px;
                font-size: 14px;
                font-weight: 800;
                border: none;
                transition: .25s ease;
            }

            .plan-btn-outline {
                background: var(--custom-white);
                color: var(--dark-color);
                border: 1px solid var(--line-color);
            }

            .plan-btn-outline:hover {
                background: var(--body-color);
                color: var(--dark-color);
            }

            .plan-btn-solid {
                background: linear-gradient(135deg, var(--dark-theme), var(--highlight-theme));
                color: #fff;
                box-shadow: 0 16px 35px color-mix(in srgb, var(--dark-theme) 22%, transparent);
            }

            .plan-btn-solid:hover {
                color: #fff;
                transform: translateY(-1px);
                box-shadow: 0 22px 40px color-mix(in srgb, var(--dark-theme) 28%, transparent);
            }

            .plan-btn-dark {
                background: linear-gradient(135deg, var(--info-gradient-start), var(--info-gradient-end));
                color: #fff;
                box-shadow: 0 16px 35px rgba(15, 23, 42, 0.22);
            }

            .plan-btn-dark:hover {
                color: #fff;
                transform: translateY(-1px);
            }

            .addon-strip {
                margin-top: 10px;
                padding: 24px;
                border-radius: 24px;
                background: linear-gradient(135deg, var(--info-gradient-start), var(--info-gradient-end));
                color: #fff;
                box-shadow: 0 20px 50px rgba(15, 23, 42, .18);
            }

            .addon-strip-title {
                font-size: 18px;
                font-weight: 800;
                margin-bottom: 14px;
            }

            .addon-chip-wrap {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
            }

            .addon-chip {
                padding: 8px 14px;
                border-radius: 999px;
                background: color-mix(in srgb, var(--white) 14%, transparent);
                color: #fff;
                font-size: 12px;
                font-weight: 700;
                border: 1px solid color-mix(in srgb, var(--white) 16%, transparent);
            }
        </style>
        <section class="enlixo-plan-section py-5">
            <div class="container">
                <div class="text-center mb-5">
                    <span class="plan-mini-badge">Upgrade Your Plan</span>
                    <h2 class="plan-main-title">Choose the right plan for your classes</h2>
                    <p class="plan-main-text">
                        Flexible plans for individual tutors, from essential class management to advanced teaching operations.
                    </p>
                </div>

                <div class="row g-4 justify-content-center">

                    <!-- START PLAN -->
                    <div class="col-12 col-lg-4">
                        <div class="pricing-card pricing-card-start">
                            <div class="plan-glow"></div>

                            <div class="pricing-top">
                                <div class="plan-icon-wrap">
                                    <iconify-icon icon="solar:stars-line-duotone" width="24"></iconify-icon>
                                </div>

                                <div class="plan-name-row">
                                    <h3 class="plan-name">START</h3>
                                    <span class="plan-badge plan-badge-start">Starter</span>
                                </div>

                                <p class="plan-desc">
                                    Best for tutors starting with essential class and student management tools.
                                </p>

                                <div class="plan-price">
                                    <span class="price-currency">LKR</span>
                                    <span class="price-value">2,500</span>
                                    <span class="price-duration">/ month</span>
                                </div>

                                <div class="student-limit">
                                    <iconify-icon icon="solar:users-group-rounded-bold-duotone" width="18"></iconify-icon>
                                    Up to 50 students
                                </div>
                            </div>

                            <div class="plan-divider"></div>

                            <div class="plan-feature-list">
                                <div class="plan-feature-item">
                                    <iconify-icon icon="solar:check-circle-bold-duotone" width="18"></iconify-icon>
                                    Subject / Grade / Language Setup
                                </div>
                                <div class="plan-feature-item">
                                    <iconify-icon icon="solar:check-circle-bold-duotone" width="18"></iconify-icon>
                                    Student Registration
                                </div>
                                <div class="plan-feature-item">
                                    <iconify-icon icon="solar:check-circle-bold-duotone" width="18"></iconify-icon>
                                    Parent Login
                                </div>
                                <div class="plan-feature-item">
                                    <iconify-icon icon="solar:check-circle-bold-duotone" width="18"></iconify-icon>
                                    Timetable Management
                                </div>
                                <div class="plan-feature-item">
                                    <iconify-icon icon="solar:check-circle-bold-duotone" width="18"></iconify-icon>
                                    Physical & Online Scheduling
                                </div>
                                <div class="plan-feature-item">
                                    <iconify-icon icon="solar:check-circle-bold-duotone" width="18"></iconify-icon>
                                    Tute / Material Management
                                </div>
                                <div class="plan-feature-item feature-muted">
                                    <iconify-icon icon="solar:close-circle-line-duotone" width="18"></iconify-icon>
                                    Online Card Payments
                                </div>
                                <div class="plan-feature-item feature-muted">
                                    <iconify-icon icon="solar:close-circle-line-duotone" width="18"></iconify-icon>
                                    Attendance Export & Digital ID
                                </div>
                            </div>

                            <div class="plan-footer">
                                <button class="btn plan-btn plan-btn-outline w-100">Choose Start</button>
                            </div>
                        </div>
                    </div>

                    <!-- GROW PLAN -->
                    <div class="col-12 col-lg-4">
                        <div class="pricing-card pricing-card-grow active-plan">
                            <div class="popular-ribbon">Most Popular</div>
                            <div class="plan-glow"></div>

                            <div class="pricing-top">
                                <div class="plan-icon-wrap">
                                    <iconify-icon icon="solar:rocket-2-bold-duotone" width="24"></iconify-icon>
                                </div>

                                <div class="plan-name-row">
                                    <h3 class="plan-name">GROW</h3>
                                    <span class="plan-badge plan-badge-grow">Popular</span>
                                </div>

                                <p class="plan-desc">
                                    Perfect for active tutors who need more communication, payments, and analytics tools.
                                </p>

                                <div class="plan-price">
                                    <span class="price-currency">LKR</span>
                                    <span class="price-value">5,000</span>
                                    <span class="price-duration">/ month</span>
                                </div>

                                <div class="student-limit">
                                    <iconify-icon icon="solar:users-group-rounded-bold-duotone" width="18"></iconify-icon>
                                    Up to 150 students
                                </div>
                            </div>

                            <div class="plan-divider"></div>

                            <div class="plan-feature-list">
                                <div class="plan-feature-item strong-feature">
                                    <iconify-icon icon="solar:check-circle-bold-duotone" width="18"></iconify-icon>
                                    Everything in Start
                                </div>
                                <div class="plan-feature-item">
                                    <iconify-icon icon="solar:check-circle-bold-duotone" width="18"></iconify-icon>
                                    Online Card Payments
                                </div>
                                <div class="plan-feature-item">
                                    <iconify-icon icon="solar:check-circle-bold-duotone" width="18"></iconify-icon>
                                    WhatsApp Integration
                                </div>
                                <div class="plan-feature-item">
                                    <iconify-icon icon="solar:check-circle-bold-duotone" width="18"></iconify-icon>
                                    SMS Support
                                </div>
                                <div class="plan-feature-item">
                                    <iconify-icon icon="solar:check-circle-bold-duotone" width="18"></iconify-icon>
                                    Attendance Reports Export
                                </div>
                                <div class="plan-feature-item">
                                    <iconify-icon icon="solar:check-circle-bold-duotone" width="18"></iconify-icon>
                                    Digital Student ID
                                </div>
                                <div class="plan-feature-item">
                                    <iconify-icon icon="solar:check-circle-bold-duotone" width="18"></iconify-icon>
                                    Attendance & Payment Analytics
                                </div>
                                <div class="plan-feature-item feature-muted">
                                    <iconify-icon icon="solar:close-circle-line-duotone" width="18"></iconify-icon>
                                    Advanced Class Analytics
                                </div>
                            </div>

                            <div class="plan-footer">
                                <button class="btn plan-btn plan-btn-solid w-100">Upgrade to Grow</button>
                            </div>
                        </div>
                    </div>

                    <!-- PRO PLAN -->
                    <div class="col-12 col-lg-4">
                        <div class="pricing-card pricing-card-pro">
                            <div class="plan-glow"></div>

                            <div class="pricing-top">
                                <div class="plan-icon-wrap">
                                    <iconify-icon icon="solar:crown-star-bold-duotone" width="24"></iconify-icon>
                                </div>

                                <div class="plan-name-row">
                                    <h3 class="plan-name">PRO</h3>
                                    <span class="plan-badge plan-badge-pro">Advanced</span>
                                </div>

                                <p class="plan-desc">
                                    Built for tutors who want premium control, automation, and advanced academic operations.
                                </p>

                                <div class="plan-price">
                                    <span class="price-currency">LKR</span>
                                    <span class="price-value">8,000</span>
                                    <span class="price-duration">/ month</span>
                                </div>

                                <div class="student-limit">
                                    <iconify-icon icon="solar:users-group-rounded-bold-duotone" width="18"></iconify-icon>
                                    Up to 300 students
                                </div>
                            </div>

                            <div class="plan-divider"></div>

                            <div class="plan-feature-list">
                                <div class="plan-feature-item strong-feature">
                                    <iconify-icon icon="solar:check-circle-bold-duotone" width="18"></iconify-icon>
                                    Everything in Grow
                                </div>
                                <div class="plan-feature-item">
                                    <iconify-icon icon="solar:check-circle-bold-duotone" width="18"></iconify-icon>
                                    Advanced Class-Based Fee Logic
                                </div>
                                <div class="plan-feature-item">
                                    <iconify-icon icon="solar:check-circle-bold-duotone" width="18"></iconify-icon>
                                    Arrears Tracking Dashboard
                                </div>
                                <div class="plan-feature-item">
                                    <iconify-icon icon="solar:check-circle-bold-duotone" width="18"></iconify-icon>
                                    Parent Payment Dashboard
                                </div>
                                <div class="plan-feature-item">
                                    <iconify-icon icon="solar:check-circle-bold-duotone" width="18"></iconify-icon>
                                    Controlled Recording Access
                                </div>
                                <div class="plan-feature-item">
                                    <iconify-icon icon="solar:check-circle-bold-duotone" width="18"></iconify-icon>
                                    Expiry-Based Recording Access
                                </div>
                                <div class="plan-feature-item">
                                    <iconify-icon icon="solar:check-circle-bold-duotone" width="18"></iconify-icon>
                                    Advanced Class Analytics
                                </div>
                                <div class="plan-feature-item addon-feature">
                                    <iconify-icon icon="solar:settings-bold-duotone" width="18"></iconify-icon>
                                    Add-on Ready Modules
                                </div>
                            </div>

                            <div class="plan-footer">
                                <button class="btn plan-btn plan-btn-dark w-100">Upgrade to Pro</button>
                            </div>
                        </div>
                    </div>

                </div>
              
            </div>
        </section>

        <script src="<?= Project::getAssetsPath() ?>/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
        <script src="<?= Project::getAssetsPath() ?>/plugins/jquery/jquery-3.7.1.min.js"></script>
        <script src="<?= Project::getAssetsPath() ?>/js/script.js?v=<?= Project::getVersion() ?>"></script>
        <script src="js/script.js?v=<?= Project::getVersion() ?>"></script>
        <script src="js/createInfo.js?v=<?= Project::getVersion() ?>"></script>
        <script src="js/functions.js?v=<?= Project::getVersion() ?>"></script>
        <script src="<?= Project::getAssetsPath() ?>/js/fileChooser.js?v=<?= Project::getVersion() ?>"></script>
    </body>

    </html>
<?php

}
?>