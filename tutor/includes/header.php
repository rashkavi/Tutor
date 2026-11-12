<?php require_once __DIR__ . "/../../includes/enlixo/icons.php"; ?>
<header class="overflow-visible">
    <div class="col-12 px-4 pt-2 pb-2 component-theme">
        <div class="row align-items-center">
            <div class="col-10 d-none d-xl-block">
                <div class="h-100 w-75 d-flex align-items-center p-1">
                    <span class="sub-topic-color pe-3 cursor-pointer d-inline-flex" id="toggleMenu" role="button" tabindex="0" aria-label="Show or hide the menu"><?= enlixo_icon('sidebar', 24) ?></span>
                    <span class="text-18 fw-semibold">Hello</span>
                    <span class="mx-1 text-18 fw-medium"><?= $sessionUserObject['user_fname'] . " " . $sessionUserObject['user_lname'] ?></span>
                </div>
            </div>
            <div class="col-10 d-block d-xl-none">
                <div class="d-flex align-items-center flex-grow-1">
                    <span class="sub-topic-color pe-3 cursor-pointer d-inline-flex" data-bs-toggle="offcanvas" data-bs-target="#offcanvas"><?= enlixo_icon('sidebar', 24) ?></span>
                </div>
            </div>

            <div class="col-2 d-flex align-items-center justify-content-end h-100 gap-3">

                <!-- Theme Switcher Dropdown -->
                <div class="dropdown">
                    <button class="btn p-0 border-0 bg-transparent" type="button" id="themeDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="light-theme-icon d-none"><?= enlixo_icon('sun', 24) ?></span>
                        <span class="dark-theme-icon d-none"><?= enlixo_icon('moon', 24) ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end p-2 border-0 shadow" aria-labelledby="themeDropdown">
                        <li>
                            <a class="dropdown-item d-flex align-items-center theme-item" href="#" data-theme="light">
                                <span class="me-2"><?= enlixo_icon('sun', 20) ?></span>
                                Light
                                <span class="ms-2 theme-check" style="display: none;"><?= enlixo_icon('check', 20) ?></span>
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item d-flex align-items-center theme-item" href="#" data-theme="dark">
                                <span class="me-2"><?= enlixo_icon('moon', 20) ?></span>
                                Dark
                                <span class="ms-2 theme-check" style="display: none;"><?= enlixo_icon('check', 20) ?></span>
                            </a>
                        </li>
                    </ul>
                </div>
                <span class="ex-header-sep" aria-hidden="true"></span>


                <div class="dropdown">
                    <button class="btn p-0 border-0 bg-transparent d-flex align-items-center"
                        type="button"
                        id="langDropdown"
                        data-bs-toggle="dropdown"
                        aria-expanded="false">

                        <span class="current-lang-text fw-semibold">EN</span>
                    </button>

                    <ul class="dropdown-menu dropdown-menu-end p-2 border-0 shadow"
                        aria-labelledby="langDropdown">

                        <li>
                            <a class="dropdown-item d-flex align-items-center lang-item"
                                href="#"
                                data-lang="en">

                                English

                                <span class="ms-auto lang-check" style="display: none;"><?= enlixo_icon('check', 20) ?></span>
                            </a>
                        </li>

                        <li>
                            <a class="dropdown-item d-flex align-items-center lang-item"
                                href="#"
                                data-lang="si">

                                සිංහල

                                <span class="ms-auto lang-check" style="display: none;"><?= enlixo_icon('check', 20) ?></span>
                            </a>
                        </li>

                        <li>
                            <a class="dropdown-item d-flex align-items-center lang-item"
                                href="#"
                                data-lang="ta">

                                தமிழ்

                                <span class="ms-auto lang-check" style="display: none;"><?= enlixo_icon('check', 20) ?></span>
                            </a>
                        </li>

                    </ul>
                </div>

                <span class="ex-header-sep" aria-hidden="true"></span>

                <div class="dropdown text-decoration-none">
                    <?php
                    $imgPath = "";
                    if (SessionUser::ifUserIsParent() && file_exists(Project::getAssetsPath() . '/imgs/users/' . $sessionUserObject["user_image"])) {
                        $imgPath = Project::getAssetsPath() . '/imgs/users/' . $sessionUserObject["user_image"];
                    }
                    if (SessionUser::ifUserIsTutor() && file_exists(Project::getAssetsPath() . '/imgs/users/' . $sessionUserObject["user_image"])) {
                        $imgPath = Project::getAssetsPath() . '/imgs/users/' . $sessionUserObject["user_image"];
                    }
                    if (SessionUser::ifUserIsStudent() && file_exists(Project::getAssetsPath() . '/imgs/students/' . $sessionUserObject["user_image"])) {
                        $imgPath = Project::getAssetsPath() . '/imgs/students/' . $sessionUserObject["user_image"];
                    }
                    if (!empty($sessionUserObject["user_image"]) && file_exists($imgPath)) {
                    ?>
                        <img src="<?= $imgPath ?>"
                            class="hwr-40 rounded-circle header-ud-toggle-area"
                            id="userDropdown"
                            data-bs-toggle="dropdown"
                            aria-expanded="false"
                            alt="User image"
                            style="cursor: pointer;">
                    <?php
                    } else {
                        $firstNameInitial = strtoupper(substr($sessionUserObject["user_fname"], 0, 1));
                        $lastNameInitial = strtoupper(substr($sessionUserObject["user_lname"], 0, 1));
                    ?>
                        <div class="d-flex hwr-40 rounded-circle align-items-center justify-content-center fw-medium cursor-pointer bg-light-theme dark-theme header-ud-toggle-area"
                            id="userDropdown"
                            data-bs-toggle="dropdown"
                            aria-expanded="false"
                            alt="User image"
                            style="cursor: pointer;">
                            <?php echo $firstNameInitial . $lastNameInitial; ?>
                        </div>
                    <?php } ?>

                    <!-- Dropdown Menu -->
                    <ul class="dropdown-menu dropdown-menu-end user-dropdown shadow border-0 mt-2" aria-labelledby="userDropdown">
                        <li>
                            <a class="dropdown-item d-flex align-items-center gap-2"
                                href="#"
                                data-bs-toggle="offcanvas"
                                data-bs-target="#settingsOffcanvas">
                                <?= enlixo_icon('settings', 18) ?>
                                Settings
                            </a>
                        </li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li> <a class="dropdown-item d-flex align-items-center gap-2" href="my-profile"> <?= enlixo_icon('edit', 18) ?> Edit Profile </a> </li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li> <a class="dropdown-item d-flex align-items-center gap-2 text-danger" href="api/signOut.php"> <?= enlixo_icon('logout', 18) ?> Log Out </a> </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</header>

<div class="offcanvas offcanvas-end" tabindex="-1" id="settingsOffcanvas">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title">Settings</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>

    <div class="offcanvas-body">

        <!-- Theme -->
        <div class="mb-3">
            <label class="text-muted small">Theme</label>

            <div class="d-flex gap-2 mt-1">
                <!-- Dark Mode -->
                <button id="lightThemeBtn"
                    class="d-flex align-items-center justify-content-center theme-toggle-btn btn rounded-pill px-3"
                    data-theme="light">
                    <span class="p-1"><?= enlixo_icon('sun', 20) ?></span>
                </button>

                <!-- Light Mode -->
                <button id="darkThemeBtn"
                    class="d-flex align-items-center justify-content-center theme-toggle-btn btn rounded-pill px-3"
                    data-theme="dark">
                    <span class="p-1"><?= enlixo_icon('moon', 20) ?></span>
                </button>
            </div>
        </div>


        <div class="mb-3 d-flex justify-content-between align-items-center">
            <span>Language</span>

            <div class="dropdown">
                <button class="btn bg-content-body px-3 rounded-pill dropdown-toggle"
                    id="languageDropdown"
                    type="button"
                    data-bs-toggle="dropdown">
                    English
                </button>

                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item language-select" data-value="en" data-text="English" href="#">English</a></li>
                    <li><a class="dropdown-item language-select" data-value="si" data-text="Sinhala" href="#">සිංහල</a></li>
                    <li><a class="dropdown-item language-select" data-value="ta" data-text="Tamil" href="#">தமிழ்</a></li>
                </ul>
            </div>
        </div>

        <?php
        $tutorPaymentPlan = Database::search("
       SELECT * FROM `users_has_payment_plans`
       INNER JOIN `payment_plans`
           ON `users_has_payment_plans`.`payment_plans_payment_plan_id` = `payment_plans`.`payment_plan_id`
       LEFT JOIN `users_has_payment_plans_payments`
           ON `users_has_payment_plans`.`users_has_payment_plan_id` =
              `users_has_payment_plans_payments`.`users_has_payment_plans_users_has_payment_plan_id`
           AND `users_has_payment_plans_payments`.`payment_status_payment_status_id` = '3'
           AND `users_has_payment_plans_payments`.`users_has_payment_plans_payment_id` = (
               SELECT MAX(`users_has_payment_plans_payment_id`)
               FROM `users_has_payment_plans_payments`
               WHERE `users_has_payment_plans_users_has_payment_plan_id` =
                     `users_has_payment_plans`.`users_has_payment_plan_id`
               AND `payment_status_payment_status_id` = '3'
           )
       WHERE `users_user_id` = '" . $sessionUserObject["user_id"] . "'
       ");

        if ($tutorPaymentPlan->num_rows > 0) {
            $tutorPaymentPlan = $tutorPaymentPlan->fetch_assoc();
            $duration = "Trial";

            if (!empty($tutorPaymentPlan['users_has_payment_plans_payment_id'])) {
                $fromDate = new DateTime($tutorPaymentPlan['from']);
                $toDate = new DateTime($tutorPaymentPlan['to']);

                $diff = $fromDate->diff($toDate);

                $monthCount = ($diff->y * 12) + $diff->m;
                $duration = $monthCount == 1 ? "1 Month" : $monthCount . " Months";
            }
        ?>
            <!-- Plan -->
            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="fw-semibold">Subscription Plan</span>
                </div>

                <!-- Selected Plan Card -->
                <div class="selected-plan-card" id="selectedPlanCard">
                    <div class="selected-plan-top">
                        <div>
                            <div class="selected-plan-label">Current Plan</div>
                            <div class="selected-plan-name" id="selectedPlanName"><?= $tutorPaymentPlan["payment_plan"] ?></div>
                        </div>
                        <span class="selected-plan-badge" id="selectedPlanBadge"><?= $duration ?></span>
                    </div>

                    <div class="selected-plan-text" id="selectedPlanText">
                        Access essential features to manage your classes and daily teaching tasks.
                    </div>

                    <div class="selected-plan-features mt-3" id="selectedPlanFeatures">
                        <span class="plan-feature-chip">Class Management</span>
                        <span class="plan-feature-chip">Attendance</span>
                        <span class="plan-feature-chip">Basic Access</span>
                    </div>
                    <?php
                    if (empty($tutorPaymentPlan['users_has_payment_plans_payment_id'])) {
                    ?>
                        <button class="btn upgrade-plan-btn mt-3 w-100" id="upgradePlanBtn" onclick="window.location.href='subscription'">

                            Proceed to Payment
                        </button>
                    <?php
                    }
                    ?>


                </div>
            </div>
        <?php
        }
        ?>



        <!-- Currency -->
        <div class="mb-3 d-flex justify-content-between align-items-center">
            <span>Devices and Activities</span>
        </div>

        <div class="mt-3">
            <?php
            $userDevices = Database::search("SELECT * FROM `users_devices` 
            WHERE `users_user_id`='" . $sessionUserObject["user_id"] . "' ORDER BY `last_active` DESC LIMIT 3");

            while ($userDevice = $userDevices->fetch_assoc()) {
            ?>
                <div class="device-item d-flex align-items-center gap-3 p-3 rounded-3 bl-solid mb-2">
                    <div class="d-flex align-items-center justify-content-center icon-wrapper p-2 br-5 bg-content-body">
                        <?php
                        if ($userDevice["device_type"] == "Desktop") {
                            echo enlixo_icon('laptop', 26);
                        } else if ($userDevice["device_type"] == "Mobile") {
                            echo enlixo_icon('smartphone', 26);
                        } else {
                            echo enlixo_icon('tablet', 26);
                        }
                        ?>
                    </div>

                    <div class="flex-grow-1">
                        <div class="fw-semibold text-dark-theme"><?php echo $userDevice["device_name"]; ?> | <?php echo $userDevice["os"]; ?> | <?php echo $userDevice["browser"]; ?></div>
                        <?php
                        if ($userDevice['is_active'] == 1) {
                        ?>
                            <div class="d-flex align-items-center gap-3 mt-1">
                                <span class="d-flex align-items-center justify-content-center table-btn table-btn-success fw-medium rounded-pill px-2 py-1">
                                    <?= enlixo_icon('dot', 12) ?>
                                    <span class="me-1">
                                        <span class="rounded-circle d-inline-block table-btn-success"></span>
                                    </span>
                                    Current session
                                </span>
                            </div>
                        <?php
                        } else {
                        ?>
                            <div class="d-flex align-items-center gap-3 mt-1">
                                <span class="text-muted text-12">Last Active: <?= date("Y-m-d h:i A", strtotime($userDevice["last_active"])) ?>
                                </span>
                            </div>
                        <?php
                        }
                        ?>
                    </div>
                </div>
            <?php
            }
            ?>

        </div>
    </div>
</div>



<?php
global $setSelectItem;
class selectItem
{
    public static function setSelected($selectItem)
    {
        global $setSelectItem;
        $setSelectItem = $selectItem;
?>
        <div class="offcanvas offcanvas-start d-block d-xl-none" style="max-width: max-content;" data-bs-scroll="true" data-bs-show="false" tabindex="-1" id="offcanvas" aria-labelledby="offcanvasLabel">
            <div class="offcanvas-body overflow-hidden pe-0">
                <button type="button" class="btn-close inside-close-btn" data-bs-dismiss="offcanvas" aria-label="Close"></button>
                <?php
                load_side_menu::load($setSelectItem);
                ?>
            </div>
        </div>
<?php
    }
}
?>