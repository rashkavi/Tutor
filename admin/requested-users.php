<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

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
        <title>Users - Admin Dashboard | <?= Project::getBaseName() ?></title>

        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/style.css?v=<?= Project::getVersion() ?>">
        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/skeleton.css?v=<?= Project::getVersion() ?>">
        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">

        <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
        <script src="<?= Project::getAssetsPath() ?>/js/init.js?v=<?= Project::getVersion() ?>"></script>
    </head>

    <body>
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
                                        <div class="col-12">
                                            <div class="row">
                                                <span class="main-topic lh-lg">Requested Users</span>
                                                <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                    <ol class="breadcrumb m-0">
                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./users">Users</a></li>
                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="#">Requested Users</a></li>
                                                    </ol>
                                                </nav>
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
                                                        $users_count = Database::search("SELECT COUNT(*) as 'count' FROM `requested_users` WHERE `request_status_request_status_id`='1'");
                                                        $data = $users_count->fetch_assoc();
                                                        ?>
                                                        <span class="text-14 dark-theme fw-medium p-0 m-0"><?php echo $data['count']; ?> Pending Request</span>
                                                    </div>
                                                </div>
                                                <div class="col-12 col-md-8 col-lg-9 d-flex flex-column">
                                                    <div class="h-100 d-flex align-items-center p-1 bl-solid">
                                                        <iconify-icon icon="teenyicons:search-outline" width="20" height="20" class="text-dark-color px-3 pe-3 py-2"></iconify-icon>
                                                        <input type="text" class="h-100 sub-text form-control border-0 shadow-none" placeholder="What do you want to search..." data-i18n="[placeholder]search-placeholder" id="user-search-text" onkeyup="handleRequestedUsersPagination('filter')">
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
                                                            <div class="row gy-3 justify-content-end">
                                                                <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                                    <select class="form-select custom-select" aria-label="Default select example" id="sort-selector" onchange="handleRequestedUsersPagination('filter')">
                                                                        <option selected value="0">- Flter User Status -</option>
                                                                        <option value="1">Pending</option>
                                                                        <option value="2">Approved</option>
                                                                        <option value="3">Rejected</option>
                                                                    </select>
                                                                </div>
                                                                <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                                    <select class="form-select custom-select" aria-label="Default select example" id="filter-selector" onchange="handleRequestedUsersPagination('filter')">
                                                                        <option selected value="0">- Payment Plan -</option>
                                                                        <option value="1">Start</option>
                                                                        <option value="2">Grow</option>
                                                                        <option value="3">Pro</option>
                                                                    </select>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-4 col-lg-3 d-flex align-items-center justify-content-end">
                                                    <label for="show-result-selector" class="pe-2 sub-text" data-i18n="results-per-page">Results Per Page</label>
                                                    <select class="form-select custom-select w-25" aria-label="Default select example" id="show-result-selector" onchange="handleRequestedUsersPagination('filter')">
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
                                                                            <div class="d-flex justify-content-between align-items-center w-100" onclick="handleRequestedUsersPagination('sortByName')">
                                                                                <span class="text-14 main-text fw-medium mx-2">User Name</span>
                                                                                <iconify-icon id="sort-icon" icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                                            </div>
                                                                        </div>
                                                                    </th>
                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Email Address</th>
                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Mobile</th>
                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Whatsapp</th>
                                                                    <th scope="col" class="py-3 bg-table-header align-content-center cursor-pointer" onclick="handleRequestedUsersPagination('sortByDate')">
                                                                        <div class="d-flex justify-content-between align-items-center w-100">
                                                                            <span class="text-14 main-text fw-medium">Date Requested</span>
                                                                            <iconify-icon id="sort-icon" icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                                        </div>
                                                                    </th>
                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Status</th>
                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center"></th>
                                                                </tr>
                                                            </thead>
                                                            <tbody id="user-table-data">
                                                                <?php
                                                                $query = "SELECT 
                                                                    `requested_users`.`requested_user_id` AS 'user_id', 
                                                                    `requested_users`.`user_fname`, 
                                                                    `requested_users`.`user_lname`, 
                                                                    `requested_users`.`email`, 
                                                                    `requested_users`.`mobile`, 
                                                                    `requested_users`.`whatsapp`, 
                                                                    `requested_users`.`requested_date_time`,
                                                                                                                                    
                                                                    `title`.`title`, 
                                                                    `request_status`.`request_status`,
                                                                    `request_status`.`request_status_id`
                                                                  
                                                                FROM `requested_users` 
                                                               
                                                                INNER JOIN `title` 
                                                                    ON `requested_users`.`title_title_id` = `title`.`title_id` 
                                                                INNER JOIN `request_status` 
                                                                    ON `requested_users`.`request_status_request_status_id` = `request_status`.`request_status_id`
                                                             
                                                                ORDER BY `requested_user_id` DESC";

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

                                                                ?>
                                                                        <tr>
                                                                            <td class="px-4 py-3 pe-0 align-middle">
                                                                                <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                                    <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check" id="check-user<?php echo $data["user_id"]; ?>" onchange="checkRowEvents(<?php echo $data['user_id']; ?>);">
                                                                                    <div class="flex-grow-1 d-flex align-items-center cursor-pointer" onclick="toggleCheckbox(<?php echo $data['user_id']; ?>)">
                                                                                        <?php

                                                                                        $firstNameInitial = strtoupper(substr($data["user_fname"], 0, 1));
                                                                                        $lastNameInitial = strtoupper(substr($data["user_lname"], 0, 1));
                                                                                        ?>
                                                                                        <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?php echo $firstNameInitial . $lastNameInitial; ?></div>
                                                                                        <span class="text-14 main-text mx-2"><?php echo $data["title"] . "." . $data["user_fname"] . " " . $data["user_lname"]; ?></span>
                                                                                    </div>
                                                                                </div>
                                                                            </td>
                                                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $data["email"]; ?></span></td>
                                                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $data["mobile"]; ?></span></td>
                                                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $data["whatsapp"]; ?></span></td>

                                                                            <td class="py-2 align-middle"><span class="text-14 main-text"> <?php echo date("Y-m-d h:i A", strtotime($data["requested_date_time"])); ?></span></td>

                                                                            <td class="py-2 align-middle">
                                                                                <?php
                                                                                $status_classes = [
                                                                                    "1"     => "table-btn-yellow",
                                                                                    "2"  => "table-btn-success",
                                                                                    "3"  => "table-btn-danger",
                                                                                ];
                                                                                $sbutton_class = $status_classes[$data["request_status_id"]];
                                                                                ?>
                                                                                <button class="table-btn <?php echo $sbutton_class; ?> w-100"> <?php echo $data["request_status"]; ?></button>
                                                                            </td>

                                                                            <?php
                                                                            if ($data['request_status_id'] != 2) {
                                                                            ?>
                                                                                <td class="py-3 align-middle">
                                                                                    <div class="dropdown">
                                                                                        <iconify-icon icon="charm:menu-kebab" width="20" height="20" class="justify-content-center sub-text cursor-pointer" id="dropdownMenuButton2" data-bs-toggle="dropdown" aria-expanded="false"></iconify-icon>
                                                                                        <ul class="dropdown-menu border-0 shadow br-5" aria-labelledby="dropdownMenuButton2">
                                                                                            <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="#" onclick="openStatusUpdateModal(<?= $data['user_id'] ?>,<?= $data['request_status_id'] ?>);">
                                                                                                    <iconify-icon icon="wordpress:update" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                    Update Status</a></li>
                                                                                        </ul>
                                                                                    </div>
                                                                                </td>
                                                                            <?php
                                                                            } else {
                                                                            ?>
                                                                                <td class="py-3 align-middle"></td>
                                                                            <?php
                                                                            }
                                                                            ?>

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
                                                                    <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleRequestedUsersPagination('previousPage')">
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
                                                                                $action = $isActive ? '' : "onclick=\"handleRequestedUsersPagination('goToPage', '{$i}')\"";
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
                                                                    <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleRequestedUsersPagination('nextPage',null,'<?= $number_of_pages ?>')">
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

        <div class="modal fade" id="updateRegRequestStatus" tabindex="-1" role="dialog" aria-labelledby="updateRegRequestStatusTitle" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLongTitle">Update Registration Request Status</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="col-12 p-0 g-0">
                            <div class="row gx-4 gy-3">
                                <div class="col-12 d-flex flex-column">
                                    <label class="text-14 main-text required" for="">Request Status</label>
                                    <select class="form-select custom-select mt-2" id="status-selector" onchange="changePaymentPlan(this.value)">
                                        <option value="0">- Select -</option>
                                        <?php
                                        $status = Database::search("SELECT * FROM `request_status` WHERE `request_status_id` != 1");
                                        while ($data = $status->fetch_assoc()) {
                                        ?>
                                            <option value="<?= $data["request_status_id"]; ?>"><?= $data["request_status"]; ?></option>
                                        <?php
                                        }
                                        ?>
                                    </select>
                                    <span id="status-feedback" class="text-dark-red text-12"></span>
                                </div>

                                <div class="col-12 d-flex flex-column d-none" id="payment-plan-area">
                                    <label class="text-14 main-text required" for="">Payment Plan</label>
                                    <select class="form-select custom-select mt-2" id="plan-selector">
                                        <option value="0">- Select -</option>
                                        <?php
                                        $status = Database::search("SELECT * FROM `payment_plans`");
                                        while ($data = $status->fetch_assoc()) {
                                        ?>
                                            <option value="<?= $data["payment_plan_id"]; ?>"><?= $data["payment_plan"]; ?></option>
                                        <?php
                                        }
                                        ?>
                                    </select>
                                    <span id="plan-feedback" class="text-dark-red text-12"></span>
                                </div>

                                <div class="col-12 d-flex flex-column">
                                    <label class="text-14 main-text" for="">Note</label>
                                    <textarea class="form-control custom-input mt-2" id="note" rows="5"></textarea>
                                    <span id="note-feedback" class="text-dark-red text-12"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="col-12 col-md-4 custom-btn" id="update-reg-request-status">Update</button>
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