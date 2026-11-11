<?php
// Admin header: same look as the Tutor header (menu button, greeting, theme, user menu), no database reads.
require_once __DIR__ . "/../../includes/enlixo/icons.php";
?>

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

                <div class="dropdown text-decoration-none">
                    <?php
                    $imgPath = Project::getAssetsPath() . '/imgs/users/' . ($sessionUserObject["user_image"] ?? '');
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
                        <li> <a class="dropdown-item d-flex align-items-center gap-2 text-danger" href="api/signOut.php"> <?= enlixo_icon('logout', 18) ?> Log Out </a> </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</header>

<?php
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