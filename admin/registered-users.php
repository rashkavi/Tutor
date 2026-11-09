<?php
require_once '../includes/UserClass.php';
require_once '../includes/UserPermissionsClass.php';
require_once "../includes/connection.php";
require_once "../includes/Project.php";
if (!SessionUser::ifUserIsAdmin()) {
    header("Location: ./index");
} else {
    $sessionUserObject = SessionUser::getCurrentUserObject('Admin');
?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="robots" content="noindex, nofollow">
        <title>Registered Users - Admin Dashboard | <?= Project::getBaseName() ?></title>

        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/style.css?v=<?= Project::getVersion() ?>">
        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/skeleton.css?v=<?= Project::getVersion() ?>">
        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">

        <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
        <script src="<?= Project::getAssetsPath() ?>/js/init.js?v=<?= Project::getVersion() ?>"></script>
    </head>

    <body onload="initializeUsers();">
        <div class="notifications"></div>
        <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
            <div id="appContainer" class="row p-0">
                <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                    <?php
                    include "./includes/side-menu.php";
                    load_side_menu::load("Users");
                    ?>
                </div>
                <div id="app-wrapper" class="app-wrapper p-0 m-0">
                    <div class="row p-0 m-0 g-0">
                        <div class="col-12 p-0 m-0 g-0">
                            <?php
                            include "../includes/header.php";
                            selectItem::setSelected("Users");
                            ?>
                        </div>

                        <div class="container p-4 m-0" id="skeleton-list">
                            <?php
                            include "../includes/components/skeleton-header.php";
                            include "../includes/components/skeleton-table.php"; ?>
                        </div>

                        <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                            <div class="row p-4 m-0 g-0">
                                <div class="col-12 px-4 py-3 component-theme mb-4">
                                    <div class="row p-0 m-0 g-0">
                                        <div class="col-12 col-md-8">
                                            <div class="row">
                                                <span class="main-topic lh-lg">Registered Users</span>
                                                <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                    <ol class="breadcrumb m-0">
                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./users">Users</a></li>
                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="#">Registered Users</a></li>
                                                    </ol>
                                                </nav>
                                            </div>
                                        </div>

                                        <div class="col-12 col-lg-4 mt-3 mt-lg-0">
                                            <div class="row align-items-center justify-content-end gx-3 h-100">
                                                <div class="col-6 mt-3 mt-lg-0 m-md-0 text-end">
                                                    <button class="bg-transparent border-0 align-middle" data-bs-target="#shareLink" data-bs-toggle="modal">
                                                        <iconify-icon icon="ion:share-social" width="30" height="30" class="dark-theme p-0 m-0"></iconify-icon>
                                                    </button>
                                                </div>
                                                <div class="col-6 mt-3 mt-lg-0 m-md-0">
                                                    <button class="custom-btn w-100" data-i18n="add-new" type="button" onclick="window.location.href='add-user'">
                                                        Add User
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 p-4 component-theme">
                                    <div class="row p-0 m-0 g-0">
                                        <div class="col-12 mb-4">
                                            <div class="row p-0 gy-3 gx-3 align-content-center justify-content-end">
                                                <div class="col-12 col-md-4 col-lg-2 d-flex flex-column">
                                                    <div class="d-flex custom-div align-items-center justify-content-center">
                                                        <?php
                                                        $users_count = Database::search("SELECT COUNT(*) as 'count' FROM `users`");
                                                        $data = $users_count->fetch_assoc();
                                                        ?>
                                                        <span class="text-14 dark-theme fw-medium p-0 m-0"><?php echo $data['count']; ?> <span data-i18n="users"></span></span>
                                                    </div>
                                                </div>
                                                <div class="col-12 col-md-8 col-lg-9 d-flex flex-column">
                                                    <div class="h-100 d-flex align-items-center p-1 bl-solid">
                                                        <iconify-icon icon="teenyicons:search-outline" width="20" height="20" class="text-dark-color px-3 pe-3 py-2"></iconify-icon>
                                                        <input type="text" class="h-100 sub-text form-control border-0 shadow-none" placeholder="What do you want to search..." data-i18n="[placeholder]search-placeholder" id="user-search-text" onkeyup="handleUsersPagination('filter')">
                                                    </div>
                                                </div>

                                                <div class="col-12 col-md-4 col-lg-1 d-flex flex-column justify-content-end">
                                                    <button class="custom-btn w-100" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse" aria-expanded="false" aria-controls="filterCollapse">
                                                        <iconify-icon icon="mage:filter" width="26" height="26" class="text-white px-3 pe-3 py-2"></iconify-icon>
                                                    </button>
                                                </div>
                                                <div class="col-12 collapse" id="filterCollapse">
                                                    <div class="card card-body border-0 p-0">
                                                        <div class="col-12">
                                                            <div class="row gy-3">

                                                                <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                                    <select class="form-select custom-select" aria-label="Default select example" id="update-selector" onchange="filterUsers()">
                                                                        <option selected value="0">- Update Users -</option>
                                                                        <option value="1">Active Users</option>
                                                                        <option value="2">Inactive Users</option>
                                                                    </select>
                                                                </div>
                                                                <div class="col-12 col-md-2 col-lg-2 d-flex flex-column justify-content-end">
                                                                    <button class="custom-btn w-100" type="button" onclick="applyUsersUpdate(this)">Apply</button>
                                                                </div>

                                                                <div class="col-12 offset-0 offset-md-2 offset-lg-4 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                                    <select class="form-select custom-select" aria-label="Default select example" id="role-selector" onchange="handleUsersPagination('filter')">
                                                                        <option selected value="0">- Filter User Role -</option>
                                                                        <?php
                                                                        $user_roles = Database::search("SELECT * FROM `user_roles`");
                                                                        while ($data = $user_roles->fetch_assoc()) {
                                                                        ?>
                                                                            <option value="<?php echo $data["user_role_id"]; ?>"><?php echo $data["user_role"]; ?></option>
                                                                        <?php
                                                                        }
                                                                        ?>
                                                                    </select>
                                                                </div>
                                                                <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                                    <select class="form-select custom-select" aria-label="Default select example" id="sort-selector" onchange="handleUsersPagination('filter')">
                                                                        <option selected value="0">- Flter User Status -</option>
                                                                        <option value="1">Active</option>
                                                                        <option value="2">Inactive</option>
                                                                    </select>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-4 col-lg-3 d-flex align-items-center justify-content-end">
                                                    <label for="show-result-selector" class="pe-2 sub-text" data-i18n="results-per-page">Results Per Page</label>
                                                    <select class="form-select custom-select w-25" aria-label="Default select example" id="show-result-selector" onchange="handleUsersPagination('filter')">
                                                        <option selected value="10">10</option>
                                                        <option value="25">25</option>
                                                        <option value="50">50</option>
                                                        <option value="100">100</option>
                                                        <option value="250">250</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12">
                                            <div class="row p-0 m-0 g-0 align-content-center">
                                                <div class="table-responsive-wrapper">
                                                    <div class="table-responsive" id="users-table-container">
                                                        <table class="table bl-solid m-0 p-0 g-0" id="users-table">
                                                            <thead>
                                                                <tr class="br-t-5 align-content-center">
                                                                    <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium align-content-center w-25">
                                                                        <div class="d-flex flex-row align-items-center cursor-pointer">
                                                                            <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                                                            <div class="d-flex justify-content-between align-items-center w-100" onclick="handleUsersPagination('sortByName')">
                                                                                <span class="text-14 main-text fw-medium mx-2">User Name</span>
                                                                                <iconify-icon id="sort-icon" icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                                            </div>
                                                                        </div>
                                                                    </th>
                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Email Address</th>
                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Mobile</th>
                                                                    <th scope="col" class="py-3 bg-table-header align-content-center cursor-pointer" onclick="handleUsersPagination('sortByDate')">
                                                                        <div class="d-flex justify-content-between align-items-center w-100">
                                                                            <span class="text-14 main-text fw-medium">Date Registered</span>
                                                                            <iconify-icon id="sort-icon" icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                                        </div>
                                                                    </th>
                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">User Role</th>
                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Status</th>
                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center"></th>
                                                                </tr>
                                                            </thead>
                                                            <tbody id="user-table-data">
                                                                <?php
                                                                $query = "SELECT 
                                                                    `users`.`user_id`, 
                                                                    `users`.`user_fname`, 
                                                                    `users`.`user_lname`, 
                                                                    `users`.`user_email`, 
                                                                    `users`.`user_mobile`, 
                                                                    `users`.`user_reg_date`,
                                                                    `users`.`user_verification`,
                                                                    `user_roles`.`user_role`, 
                                                                    `user_roles`.`user_role_id`, 
                                                                    `title`.`title`, 
                                                                    `status`.`status`,
                                                                    `status`.`status_id`,
                                                                    `users`.`profile_img`
                                                                FROM `users` 
                                                                INNER JOIN `user_roles` 
                                                                    ON `users`.`user_roles_user_role_id` = `user_roles`.`user_role_id` 
                                                                INNER JOIN `title` 
                                                                    ON `users`.`title_title_id` = `title`.`title_id` 
                                                                INNER JOIN `status` 
                                                                    ON `users`.`status_status_id` = `status`.`status_id`
                                                                ORDER BY `user_reg_date` DESC";

                                                                $results = Database::search($query);
                                                                $pageno = 1;
                                                                $results_count = $results->num_rows;
                                                                $result_per_page = 10;  // Consistent variable name

                                                                if ($results_count > $result_per_page) {
                                                                    $number_of_pages = ceil($results_count / $result_per_page);  // Corrected variable name
                                                                } else {
                                                                    $number_of_pages = 1;
                                                                }

                                                                $page_results = ($pageno - 1) * $result_per_page;
                                                                $users = Database::search("" . $query . " LIMIT " . $result_per_page . " OFFSET " . $page_results . "");

                                                                if ($users->num_rows > 0) {
                                                                    while ($data = $users->fetch_assoc()) {
                                                                        $sbutton_class = "label-role-" . $data["user_role_id"];
                                                                ?>
                                                                        <tr>
                                                                            <td class="px-4 py-3 pe-0 align-middle">
                                                                                <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                                    <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check" id="check-user<?php echo $data["user_id"]; ?>" onchange="checkRowEvents(<?php echo $data['user_id']; ?>);">
                                                                                    <div class="flex-grow-1 d-flex align-items-center cursor-pointer" onclick="toggleCheckbox(<?php echo $data['user_id']; ?>)">
                                                                                        <?php
                                                                                        if (!empty($data["profile_img"]) && file_exists('<?= Project::getAssetsPath() ?>/imgs/users/' . $data["profile_img"])) {
                                                                                        ?>
                                                                                            <img src="<?= Project::getAssetsPath() ?>/imgs/users/<?php echo $data["profile_img"]; ?>" class="hwr-40" alt="User image">
                                                                                        <?php
                                                                                        } else {
                                                                                            $firstNameInitial = strtoupper(substr($data["user_fname"], 0, 1));
                                                                                            $lastNameInitial = strtoupper(substr($data["user_lname"], 0, 1));
                                                                                        ?>
                                                                                            <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?php echo $firstNameInitial . $lastNameInitial; ?></div>
                                                                                        <?php
                                                                                        }
                                                                                        ?>
                                                                                        <span class="text-14 main-text mx-2"><?php echo $data["title"] . "." . $data["user_fname"] . " " . $data["user_lname"]; ?></span>
                                                                                    </div>
                                                                                </div>
                                                                            </td>
                                                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $data["user_email"]; ?></span></td>
                                                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $data["user_mobile"]; ?></span></td>
                                                                            <td class="py-2 align-middle"><span class="text-14 main-text"> <?php echo date("Y-m-d h:i A", strtotime($data["user_reg_date"])); ?></span></td>
                                                                            <td class="py-2 align-middle">
                                                                                <button class="table-btn <?php echo $sbutton_class; ?> w-100"> <?php echo $data["user_role"]; ?></button>
                                                                            </td>
                                                                            <td class="py-2 align-middle">
                                                                                <?php
                                                                                $status_classes = [
                                                                                    "1"     => "table-btn-success",
                                                                                    "2"  => "table-btn-danger",
                                                                                ];
                                                                                $sbutton_class = $status_classes[$data["status_id"]];
                                                                                ?>
                                                                                <button class="table-btn <?php echo $sbutton_class; ?> w-100"> <?php echo $data["status"]; ?></button>
                                                                            </td>

                                                                            <td class="py-3 align-middle">
                                                                                <div class="dropdown">
                                                                                    <iconify-icon icon="charm:menu-kebab" width="20" height="20" class="justify-content-center sub-text cursor-pointer" id="dropdownMenuButton2" data-bs-toggle="dropdown" aria-expanded="false"></iconify-icon>
                                                                                    <ul class="dropdown-menu border-0 shadow br-5" aria-labelledby="dropdownMenuButton2">

                                                                                        <?php
                                                                                        if (!empty($data['user_role_id'] == 2)) {
                                                                                        ?>

                                                                                            <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="tutor-activity?user-id=<?php echo $data['user_id']; ?>">
                                                                                                    <iconify-icon icon="lets-icons:send-fill" width="20" height="20" class="sub-text-color me-3"></iconify-icon>
                                                                                                    Activity</a></li>
                                                                                            <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                                        <?php
                                                                                        }
                                                                                        ?>
                                                                                        <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="edit-user?user-id=<?php echo $data['user_id']; ?>">
                                                                                                <iconify-icon icon="ic:sharp-edit" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                Edit</a></li>

                                                                                        <hr class="mt-1 mb-1 mb-0 me-2 mx-2">

                                                                                        <?php
                                                                                        if ($data["status"] == "Active") {
                                                                                        ?>
                                                                                            <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="#" onclick="updateUserStatus(2,'<?php echo $data['user_id']; ?>')">
                                                                                                    <iconify-icon icon="lsicon:minus-filled" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                    Deactivate</a></li>
                                                                                        <?php
                                                                                        } else {
                                                                                        ?>
                                                                                            <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="#" onclick="updateUserStatus(1,'<?php echo $data['user_id']; ?>')">
                                                                                                    <iconify-icon icon="bxs:plus-circle" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                    Activate</a></li>
                                                                                        <?php
                                                                                        }

                                                                                        ?>
                                                                                    </ul>
                                                                                </div>
                                                                            </td>

                                                                        </tr>
                                                                    <?php
                                                                    }
                                                                } else {
                                                                    ?>
                                                                    <tr class="justify-content-center align-middle">
                                                                        <td colspan="13" class="align-middle justify-content-center">
                                                                            <div class="d-flex flex-column align-items-center">
                                                                                <img src="<?= Project::getAssetsPath() ?>/imgs/common/no-data.png" class="no-data-icon" alt="No data" />
                                                                            </div>
                                                                        </td>
                                                                    </tr>
                                                                <?php
                                                                }
                                                                ?>
                                                            </tbody>
                                                        </table>
                                                        <div class="bl-solid-lrb br-b-5 m-0 g-0 component-theme pagination-wrapper" id="pagination-container">
                                                            <nav aria-label="..." class="col-12 px-2 pe-2 m-0 container-fluid">
                                                                <div class="row p-4 align-items-center">
                                                                    <!-- Previous Button (col-1) -->
                                                                    <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleUsersPagination('previousPage')">
                                                                        <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                                                        <span class="text-14 main-text fw-semibold d-none d-md-block">Previous</span>
                                                                    </button>
                                                                    <!-- Page Numbers (col-10, centered) -->
                                                                    <div class="col-8 col-lg-10 custom-pagination d-flex justify-content-center">
                                                                        <ul class="pagination-list d-flex mb-0">
                                                                            <?php
                                                                            $shudderPages1 = false;
                                                                            $shudderPages2 = false;

                                                                            function renderPageButton($i, $isActive = false)
                                                                            {
                                                                                $activeClass = $isActive ? 'active' : '';
                                                                                $action = $isActive ? '' : "onclick=\"handleUsersPagination('goToPage', '{$i}')\"";
                                                                                echo "<li class='page-item {$activeClass}' id='page-{$i}'><button class='hw-40' {$action}>{$i}</button></li>";
                                                                            }

                                                                            for ($i = 1; $i <= $number_of_pages; $i++) {
                                                                                if ($i == 1 || $i == $number_of_pages || abs($pageno - $i) <= 1) {
                                                                                    renderPageButton($i, $pageno == $i);
                                                                                } elseif (!$shudderPages1 && ($i < $pageno - 1 && $i > 1)) {
                                                                                    $shudderPages1 = true;
                                                                                    echo "<li class='d-flex align-items-end h-100'><iconify-icon icon='bi:three-dots' width='20' height='20' class='sub-text m-0 p-0'></iconify-icon></li>";
                                                                                } elseif (!$shudderPages2 && ($i > $pageno + 1 && $i < $number_of_pages)) {
                                                                                    $shudderPages2 = true;
                                                                                    echo "<li class='d-flex align-items-end h-100'><iconify-icon icon='bi:three-dots' width='20' height='20' class='sub-text m-0 p-0'></iconify-icon></li>";
                                                                                }
                                                                            }
                                                                            ?>
                                                                        </ul>
                                                                    </div>
                                                                    <!-- Next Button (col-1) -->
                                                                    <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleUsersPagination('nextPage',null,'<?= $number_of_pages ?>')">
                                                                        <span class="text-14 main-text fw-semibold d-none d-md-block">Next</span>
                                                                        <iconify-icon icon="tabler:arrow-right" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                                                    </button>
                                                                </div>
                                                            </nav>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="flex-grow-1"></div>
                            <footer>
                                <?php include '../includes/footer.php' ?>
                            </footer>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade br-0" id="shareLink" aria-hidden="true" aria-labelledby="shareLinkLabel" tabindex="-1">
            <div class="modal-dialog modal-lg modal-dialog-centered br-0">
                <div class="modal-content p-4">
                    <div class="modal-header p-0 border-0">
                        <h1 class="modal-title text-16 darkColor" id="shareLinkLabel">Share Registration Link</h1>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-0 mt-4">
                        <div class="col-12 p-2">
                            <div class="d-flex p-3 m-0 g-0 align-content-center bg-light-theme">
                                <iconify-icon icon="mage:attachment" width="24" height="24" class="px-2"></iconify-icon>
                                <?php
                                $link = null;
                                $link = Project::getBaseUrl() . "/tutor-registration-application";
                                ?>
                                <span id="generated-link" class="fw-semibold"><?= $link ?></span>
                            </div>
                        </div>

                        <div class="col-12 d-flex justify-content-end mt-4">
                            <button id="copy-button" type="button" class="custom-btn px-5 pe-5">Copy Link</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script src="<?= Project::getAssetsPath() ?>/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
        <script src="<?= Project::getAssetsPath() ?>/plugins/jquery/jquery-3.7.1.min.js"></script>
        <script src="<?= Project::getAssetsPath() ?>/js/script.js?v=<?= Project::getVersion() ?>"></script>
        <script src="js/script.js?v=<?= Project::getVersion() ?>"></script>
        <script src="js/functions.js?v=<?= Project::getVersion() ?>"></script>
    </body>

    </html>
<?php
}

?>