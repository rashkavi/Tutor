<?php
require_once '../includes/UserClass.php';
require_once '../includes/Project.php';
if (SessionUser::ifUserIsTutor()) {
    header("Location: ./dashboard");
} else {
    $email = null;
    $password = null;
    $rememberMe = null;
    if (isset($_COOKIE['enviiz_lms_user_email'])) {
        $email = $_COOKIE['enviiz_lms_user_email'];
    }
    if (isset($_COOKIE['enviiz_lms_user_password'])) {
        $password = $_COOKIE['enviiz_lms_user_password'];
    }

?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="robots" content="noindex, nofollow">
        <title>Admin Dashboard | <?= Project::getBaseName() ?></title>

        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/style.css?v=<?= Project::getVersion() ?>">
        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/plugins/bootstrap/css/bootstrap.css">
        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">

        <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
        <script src="<?= Project::getAssetsPath() ?>/js/init.js?v=<?= Project::getVersion() ?>"></script>

    </head>

    <body class="overflow-hidden">
        <div class="notifications"></div>
        <!-- Include Section -->
        <!-- <?php include './includes/loader.php' ?> -->
        <!-- Main Content Section (Initially Hidden) -->
        <div class="vh-100 container-fluid overflow-hidden">
            <div class="row h-100 align-items-center justify-content-center">
                <div class="col-12 col-xl-6 component-theme">
                    <div class="row align-items-center justify-content-center p-0">
                        <div class="col-10 h-100">
                            <div class="d-flex flex-column align-items-center justify-content-center" style="height: calc(100vh - 2rem);">
                                <div class="col-12 col-lg-6 p-0 text-center">
                                    <img src="<?= Project::getAssetsPath() ?>/imgs/<?= Project::getInstituteLogo() ?>" class="h-auto enviiz-logo institute-logo" alt="<?= Project::getBaseName() ?>">
                                </div>

                                <div class="col-12 col-lg-6 d-flex flex-column mb-4 mb-md-2 mb-xl-4 align-items-center justify-content-center" id="content-div">
                                    <p class="text-24 sub-topic-color fw-medium lh-lg">Tutor Login</p>
                                    <div class="w-100 mb-4 mb-md-2 mb-xl-4 justify-content-start">
                                        <input
                                            type="tel"
                                            class="form-control custom-input"
                                            id="mobile"
                                            name="mobile"
                                            placeholder="WhatsApp Number"
                                            required />
                                        <span id="mobile-feedback" class="text-dark-red text-12 mt-1"></span>
                                    </div>
                                    <button class="w-100 custom-btn d-flex align-items-center justify-content-center" onclick="sendOTP(this);">
                                        <span class="text-16 fw-semibold pe-3">Send OTP</span>
                                        <iconify-icon icon="rivet-icons:arrow-right" width="16" height="16" class="text-white"></iconify-icon>
                                    </button>
                                </div>
                            </div>
                            <div class="d-flex flex-column pb-4 m-0 align-items-center justify-content-end" style="height: 2rem;">
                                <p class="text-12 sub-text p-0 m-1 g-0 text-center">&copy;<?php echo date("Y"); ?> ENLIXO Tutor. All Rights Reserved | Powered by
                                    <a href="https://enviiz.com" target="_blank" class="text-decoration-none footer-link">
                                        <img src="<?= Project::getAssetsPath() ?>/imgs/common/enviiz-theme.webp" alt="ENVIIZ Softwares (PVT) LTD" style="width: 40px;height: auto;" class="enviiz-logo">
                                    </a>
                                </p>
                            </div>

                        </div>
                    </div>
                </div>
                <div class="col-xl-6 d-none d-xl-block p-0">
                    <div class="row vh-100 d-flex align-items-center justify-content-center">
                        <div id="loginPageImageCarousel" class="carousel slide" data-bs-ride="carousel">
                            <div class="carousel-indicators">
                                <button type="button" data-bs-target="#loginPageImageCarousel" data-bs-slide-to="0" class="active" aria-current="true"></button>
                                <button type="button" data-bs-target="#loginPageImageCarousel" data-bs-slide-to="1"></button>
                                <button type="button" data-bs-target="#loginPageImageCarousel" data-bs-slide-to="2"></button>
                            </div>
                            <div class="carousel-inner">
                                <div class="carousel-item active">
                                    <div class="carousel-overlay"></div>
                                    <img src="<?= Project::getAssetsPath() ?>/imgs/slide-img/slide-img-1.png" class="d-block w-100" alt="Slide 1">
                                </div>
                                <div class="carousel-item">
                                    <div class="carousel-overlay"></div>
                                    <img src="<?= Project::getAssetsPath() ?>/imgs/slide-img/slide-img-2.png" class="d-block w-100" alt="Slide 2">
                                </div>
                                <div class="carousel-item">
                                    <div class="carousel-overlay"></div>
                                    <img src="<?= Project::getAssetsPath() ?>/imgs/slide-img/slide-img-3.png" class="d-block w-100" alt="Slide 3">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script src="<?= Project::getAssetsPath() ?>/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
        <script src="<?= Project::getAssetsPath() ?>/plugins/jquery/jquery-3.7.1.min.js"></script>
        <script src="<?= Project::getAssetsPath() ?>/js/script.js?v=<?= Project::getVersion() ?>"></script>
        <script src="js/functions.js?v=<?= Project::getVersion() ?>"></script>
    </body>

    </html>
<?php
}
?>