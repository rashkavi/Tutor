<?php
require_once './includes/UserClass.php';
require_once './includes/UserPermissionsClass.php';
require_once "./includes/connection.php";
require_once "./includes/Project.php";
?>
<!DOCTYPE html>
<html lang="en" data-custom-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Application</title>

    <meta property="og:locale" content="en_US" />
    <meta property="og:type" content="website" />
    <meta property="og:title" content="Tutor Registration Application | <?= Project::getBaseName(); ?>" />
    <meta property="og:description" content="ENLIXO Tutor helps educators manage classes, students, and academic activities with a modern and efficient system." />
    <meta property="og:url" content="<?= Project::getBaseUrl(); ?>" />
    <meta property="og:site_name" content="Tutor Registration Application | <?= Project::getBaseName(); ?>" />
    <meta property="og:image" content="./assets/imgs/favicon/web-app-manifest-512x512.png" />

    <meta name="twitter:title" content="Tutor Registration Application | <?= Project::getBaseName(); ?>" />
    <meta name="twitter:description" content="ENLIXO Tutor helps educators manage classes, students, and academic activities with a modern and efficient system." />
    <meta name="twitter:image" content="./assets/imgs/favicon/web-app-manifest-512x512.png" />

    <link rel="apple-touch-icon" sizes="180x180" href="assets/imgs/favicon/apple-touch-icon.png" />
    <meta name="apple-mobile-web-app-title" content="Tutor Registration Application | <?= Project::getBaseName(); ?>" />
    <link rel="manifest" href="./assets/imgs/favicon/site.webmanifest" />

    <link rel="stylesheet" href="assets/css/style.css?v=<?= Project::getVersion() ?>">
    <link rel="stylesheet" href="assets/css/registration.css?v=<?= Project::getVersion() ?>">
    <link rel="stylesheet" href="assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">

    <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
</head>

<body>
    <div class="notifications"></div>
    <div class="container-fluid registration-wrapper p-0">
        <div class="row g-0 page-layout">


            <div class="col-12 col-xl-7 left-panel overflow-scroll vh-100">
                <div class="form-shell">
                    <div class="form-card">
                        <div class="form-header">
                            <div class="d-flex flex-column align-items-center gap-3">
                                <div class="mini-logo">
                                    <img src="assets/imgs/<?= Project::getInstituteLogo() ?>" class="h-auto enviiz-logo institute-logo" alt="<?= Project::getBaseName() ?>">
                                </div>
                                <div class="d-flex flex-column align-items-center">
                                    <div class="form-title">Tutor Registration</div>
                                    <p class="form-subtitle">Complete the application form to join our teaching platform.</p>
                                </div>
                            </div>
                        </div>

                        <div class="form-body">
                            <div id="successBanner" class="success-banner">
                                Application submitted successfully.
                            </div>

                            <div class="d-flex flex-wrap gap-2 mb-4">
                                <span class="info-chip">
                                    <iconify-icon icon="solar:shield-check-outline" width="16" height="16"></iconify-icon>
                                    Secure Form
                                </span>
                                <span class="info-chip">
                                    <iconify-icon icon="solar:document-text-outline" width="16" height="16"></iconify-icon>
                                    Professional Application
                                </span>
                            </div>

                            <div class="section-title">Basic Information</div>

                            <form id="registrationForm" novalidate>
                                <div class="row">
                                    <div class="col-12 col-md-4 field-wrap">
                                        <label class="custom-label required">Title</label>
                                        <select class="form-select custom-select" id="title">
                                            <option value="">- Select -</option>
                                            <?php
                                            $user_titles = Database::search("SELECT * FROM `title`");
                                            while ($data = $user_titles->fetch_assoc()) {
                                            ?>
                                                <option value="<?php echo $data["title_id"]; ?>"><?php echo $data["title"]; ?></option>
                                            <?php
                                            }
                                            ?>
                                        </select>
                                        <div class="feedback-text" id="title-feedback"></div>
                                    </div>
                                    <div class="col-12 col-md-4 field-wrap">
                                        <label class="custom-label required">First Name</label>
                                        <div class="input-group-modern">
                                            <span class="input-icon">
                                                <iconify-icon icon="solar:user-outline" width="18" height="18"></iconify-icon>
                                            </span>
                                            <input type="text" class="form-control custom-input with-icon" id="firstName" maxlength="45">
                                        </div>
                                        <div class="feedback-text" id="fname-feedback"></div>
                                    </div>

                                    <div class="col-12 col-md-4 field-wrap">
                                        <label class="custom-label required">Last Name</label>
                                        <div class="input-group-modern">
                                            <span class="input-icon">
                                                <iconify-icon icon="solar:user-outline" width="18" height="18"></iconify-icon>
                                            </span>
                                            <input type="text" class="form-control custom-input with-icon" id="lastName" maxlength="45">
                                        </div>
                                        <div class="feedback-text" id="lname-feedback"></div>
                                    </div>


                                    <div class="col-12 col-md-8 field-wrap">
                                        <label class="custom-label required">Email</label>
                                        <div class="input-group-modern">
                                            <span class="input-icon">
                                                <iconify-icon icon="solar:letter-outline" width="18" height="18"></iconify-icon>
                                            </span>
                                            <input type="email" class="form-control custom-input with-icon" id="email" maxlength="100">
                                        </div>
                                        <div class="feedback-text" id="email-feedback"></div>
                                    </div>

                                    <div class="col-12 col-md-4 field-wrap">
                                        <label class="custom-label required">NIC</label>
                                        <div class="input-group-modern">
                                            <span class="input-icon">
                                                <iconify-icon icon="tabler:id-filled" width="18" height="18"></iconify-icon>
                                            </span>
                                            <input type="text" class="form-control custom-input with-icon" id="nic" maxlength="45">
                                        </div>
                                        <div class="feedback-text" id="nic-feedback"></div>
                                    </div>

                                    <div class="col-12 col-md-6 field-wrap">
                                        <label class="custom-label required">Mobile</label>
                                        <div class="input-group-modern">
                                            <span class="input-icon">
                                                <iconify-icon icon="solar:phone-outline" width="18" height="18"></iconify-icon>
                                            </span>
                                            <input type="text" class="form-control custom-input with-icon" id="mobile" maxlength="15">
                                        </div>
                                        <div class="feedback-text" id="mobile-feedback"></div>
                                    </div>

                                    <div class="col-12 col-md-6 field-wrap">
                                        <label class="custom-label required">WhatsApp</label>
                                        <div class="input-group-modern">
                                            <span class="input-icon">
                                                <iconify-icon icon="ri:whatsapp-line" width="18" height="18"></iconify-icon>
                                            </span>
                                            <input type="text" class="form-control custom-input with-icon" id="whatsapp" maxlength="15">
                                        </div>
                                        <div class="feedback-text" id="whatsapp-feedback"></div>
                                    </div>

                                    <div class="col-12">
                                        <button type="submit" class="custom-btn w-100 d-flex align-items-center justify-content-center gap-2"
                                            onclick="submitTutorRegistrationApplication(this);">
                                            <span>Submit Application</span>
                                            <iconify-icon icon="solar:arrow-right-linear" width="18" height="18"></iconify-icon>
                                        </button>
                                    </div>
                                </div>
                            </form>

                            <div class="footer-note">
                                <div class="d-flex flex-column pb-4 align-items-center justify-content-end">
                                    <p class="text-12 sub-text p-0 m-1 g-0 text-center">&copy;<?php echo date("Y"); ?> ENLIXO Tutor. All Rights Reserved | Powered by
                                        <a href="https://enviiz.com" target="_blank" class="text-decoration-none footer-link">
                                            <img src="assets/imgs/common/enviiz-theme.webp" alt="ENVIIZ Softwares (PVT) LTD" style="width: 40px;height: auto;" class="enviiz-logo">
                                        </a>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-5 d-none d-xl-block right-panel">
                <div class="floating-shape shape-1"></div>
                <div class="floating-shape shape-2"></div>
                <div class="floating-shape shape-3"></div>

                <div class="right-content">
                    <div class="hero-badge">
                        <iconify-icon icon="solar:stars-outline" width="16" height="16"></iconify-icon>
                        Professional Teaching Platform
                    </div>

                    <div>
                        <h1 class="hero-title">
                            Teach Smarter, Manage Better
                        </h1>

                        <p class="hero-text">
                            ENLIXO Tutor empowers educators to manage classes, students, attendance, and academic activities efficiently with a modern and intuitive system.
                        </p>

                        <div class="hero-stats">
                            <div class="hero-stat">
                                <h4>50+</h4>
                                <p>Active Tutors</p>
                            </div>
                            <div class="hero-stat">
                                <h4>40+</h4>
                                <p>Subjects Covered</p>
                            </div>
                            <div class="hero-stat">
                                <h4>98%</h4>
                                <p>Application Accuracy</p>
                            </div>
                        </div>
                    </div>

                    <div class="brand-logo">ENLIXO Tutor</div>
                </div>
            </div>

        </div>
    </div>

    <script src="assets/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
    <script src="assets/plugins/jquery/jquery-3.7.1.min.js"></script>
    <script src="assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
    <script src="assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>

</body>

</html>