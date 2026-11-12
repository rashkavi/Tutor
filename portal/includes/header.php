<header class="overflow-visible">
    <div class="col-12 px-4 pt-2 pb-2 component-theme">
        <div class="row align-items-center">
            <div class="col-10 d-none d-xl-block">
                <div class="h-100 w-75 d-flex align-items-center p-1">
                    <iconify-icon icon="gg:menu-left" width="24" height="24" class="sub-topic-color pe-3 pt-2 pb-2 cursor-pointer" id="toggleMenu"></iconify-icon>
                    <span class="text-18 fw-semibold">Hello</span>
                    <span class="mx-1 text-18 fw-medium"><?= $sessionUserObject['user_fname'] . " " . $sessionUserObject['user_lname'] ?></span>
                    <iconify-icon icon="noto:clapping-hands" width="24" height="24" class="sub-topic-color pe-3 pt-2 pb-2 cursor-pointer" id="toggleMenu"></iconify-icon>
                </div>
            </div>
            <div class="col-10 d-block d-xl-none">
                <div class="d-flex align-items-center flex-grow-1">
                    <iconify-icon icon="gg:menu-left" width="24" height="24" class="sub-topic-color pe-3 pt-2 pb-2 cursor-pointer" data-bs-toggle="offcanvas" data-bs-target="#offcanvas"></iconify-icon>
                </div>
            </div>

            <div class="col-2 d-flex align-items-center justify-content-end h-100 gap-3">

                <!-- Theme Switcher Dropdown -->
                <div class="dropdown">
                    <button class="btn p-0 border-0 bg-transparent" type="button" id="themeDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <iconify-icon icon="line-md:moon-alt-to-sunny-outline-loop-transition" width="24" height="24" class="light-theme-icon d-none"></iconify-icon>
                        <iconify-icon icon="line-md:sunny-filled-loop-to-moon-filled-loop-transition" width="24" height="24" class="dark-theme-icon d-none"></iconify-icon>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end p-2 border-0 shadow" aria-labelledby="themeDropdown">
                        <li>
                            <a class="dropdown-item d-flex align-items-center theme-item" href="#" data-theme="light">
                                <iconify-icon icon="line-md:moon-alt-to-sunny-outline-loop-transition" width="20" height="20" class="me-2"></iconify-icon>
                                Light
                                <iconify-icon icon="material-symbols:check" width="20" height="20" class="ms-2 theme-check" style="display: none;"></iconify-icon>
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item d-flex align-items-center theme-item" href="#" data-theme="dark">
                                <iconify-icon icon="line-md:sunny-filled-loop-to-moon-filled-loop-transition" width="20" height="20" class="me-2"></iconify-icon>
                                Dark
                                <iconify-icon icon="material-symbols:check" width="20" height="20" class="ms-2 theme-check" style="display: none;"></iconify-icon>
                            </a>
                        </li>
                    </ul>
                </div>
                <iconify-icon icon="vaadin:line-v" width="16" height="20" class="text-dark-color px-1"></iconify-icon>


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

                                <iconify-icon icon="material-symbols:check"
                                    width="20"
                                    height="20"
                                    class="ms-auto lang-check"
                                    style="display: none;">
                                </iconify-icon>
                            </a>
                        </li>

                        <li>
                            <a class="dropdown-item d-flex align-items-center lang-item"
                                href="#"
                                data-lang="si">

                                සිංහල

                                <iconify-icon icon="material-symbols:check"
                                    width="20"
                                    height="20"
                                    class="ms-auto lang-check"
                                    style="display: none;">
                                </iconify-icon>
                            </a>
                        </li>

                        <li>
                            <a class="dropdown-item d-flex align-items-center lang-item"
                                href="#"
                                data-lang="ta">

                                தமிழ்

                                <iconify-icon icon="material-symbols:check"
                                    width="20"
                                    height="20"
                                    class="ms-auto lang-check"
                                    style="display: none;">
                                </iconify-icon>
                            </a>
                        </li>

                    </ul>
                </div>

                <iconify-icon icon="vaadin:line-v" width="16" height="20" class="text-dark-color px-1"></iconify-icon>

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
                                <iconify-icon icon="carbon:settings" width="18" height="18"></iconify-icon>
                                Settings
                            </a>
                        </li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li> <a class="dropdown-item d-flex align-items-center gap-2" href="my-profile"> <iconify-icon icon="line-md:edit" width="18" height="18"></iconify-icon> Edit Profile </a> </li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li> <a class="dropdown-item d-flex align-items-center gap-2 text-danger" href="api/signOut.php"> <iconify-icon icon="line-md:logout" width="18" height="18"></iconify-icon> Log Out </a> </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</header>
<style>
    .hover-bg:hover {
        background-color: var(--light-theme);
        cursor: pointer;
        transition: background 0.2s;
    }
</style>

<div class="modal fade" id="searchModal" tabindex="-1" aria-labelledby="searchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <div class="w-100 d-flex align-items-center p-1 bl-solid search-container">
                    <iconify-icon icon="teenyicons:search-outline" width="20" height="20" class="dark-theme px-3 pe-3 pt-2 pb-2"></iconify-icon>
                    <input type="text" class="h-100 sub-text form-control border-0 shadow-none live-search-input" placeholder="What do you want to search..." data-i18n="[placeholder]search-placeholder" id="live-search-input" onkeyup="getLiveSearchResults()">
                </div>
            </div>
            <div class="modal-body">
                <div class="modal-body px-1 py-0" id="live-search-results">

                </div>
            </div>
        </div>
    </div>
</div>

<!-- Settings Offcanvas -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="settingsOffcanvas">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title">Settings</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>

    <div class="offcanvas-body">
        <style>
            .theme-toggle-btn {
                background-color: var(--body-color) !important;
                color: var(--dark-color) !important;
            }

            .theme-toggle-btn.btn-primary {
                background-color: var(--dark-theme) !important;
                color: white !important;
            }
        </style>
        <!-- Theme -->
        <div class="mb-3">
            <label class="text-muted small">Theme</label>

            <div class="d-flex gap-2 mt-1">
                <!-- Dark Mode -->
                <button id="lightThemeBtn"
                    class="d-flex align-items-center justify-content-center theme-toggle-btn btn rounded-pill px-3"
                    data-theme="light">
                    <iconify-icon icon="line-md:moon-alt-to-sunny-outline-loop-transition"
                        width="20" height="20" class="p-1"></iconify-icon>
                </button>

                <!-- Light Mode -->
                <button id="darkThemeBtn"
                    class="d-flex align-items-center justify-content-center theme-toggle-btn btn rounded-pill px-3"
                    data-theme="dark">
                    <iconify-icon icon="line-md:sunny-filled-loop-to-moon-filled-loop-transition"
                        width="20" height="20" class="p-1"></iconify-icon>
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
            <div class="offcanvas-body overflow-hidden p-0">
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