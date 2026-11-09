<?php
$status = new stdClass();
require_once("../../includes/UserClass.php");
require_once("../../includes/connection.php");
require_once("../../includes/Project.php");
if (SessionUser::ifUserIsAdmin()) {
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
            $content = trim(file_get_contents('php://input'));
            $decoded = json_decode($content, true);
            if (json_last_error() == JSON_ERROR_NONE) {

                $sessionUserObject = SessionUser::getCurrentUserObject('Admin');

                $search_text = isset($decoded['text']) ? $decoded['text'] : '';
                $user_sort = isset($decoded['sort']) ? $decoded['sort'] : 0;
                $user_filter = isset($decoded['filter']) ? $decoded['filter'] : 0;
                $sort_name = isset($decoded['sortName']) ? $decoded['sortName'] : '';
                $sort_date = isset($decoded['sortDate']) ? $decoded['sortDate'] : '';
                $resultPerPage = isset($decoded['resultPerPage']) ? $decoded['resultPerPage'] : 10;
                $pageno = isset($decoded['page']) ? $decoded['page'] : 1;

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
                `request_status`.`request_status_id`,
                `payment_plans`.`payment_plan`,
                `payment_plans`.`payment_plan_id`
            FROM `requested_users` 
           
            INNER JOIN `title` 
                ON `requested_users`.`title_title_id` = `title`.`title_id` 
            INNER JOIN `request_status` 
                ON `requested_users`.`request_status_request_status_id` = `request_status`.`request_status_id`
            INNER JOIN  `payment_plans` ON `requested_users`.`payment_plans_payment_plan_id` = `payment_plans`.`payment_plan_id`    
           ";

                if ($search_text != "") {
                    $query .= " WHERE (`user_fname` LIKE '%" . $search_text . "%' OR `user_lname` LIKE '%" . $search_text . "%' OR `email` LIKE '%" . $search_text . "%' OR `mobile` LIKE '%" . $search_text . "%')";
                }

                if ($user_sort != 0) {
                    if (strpos($query, 'WHERE') == false) {
                        $query .= " WHERE `request_status`.`request_status_id` = '" . $user_sort . "'";
                    } else {
                        $query .= " AND `request_status`.`request_status_id` = '" . $user_sort . "'";
                    }
                }
                if ($user_filter != 0) {
                    if (strpos($query, 'WHERE') == false) {
                        $query .= " WHERE `payment_plans`.`payment_plan_id` = '" . $user_filter . "'";
                    } else {
                        $query .= " AND `payment_plans`.`payment_plan_id` = '" . $user_filter . "'";
                    }
                }

                if ($sort_name != 0) {
                    $sort = $sort_name == 1 ? 'ASC' : 'DESC';
                    if (strpos($query, 'ORDER BY') === false) {
                        $query .= " ORDER BY `user_fname` " . $sort;
                    } else {
                        $query .= ", `user_fname` " . $sort;
                    }
                }

                if ($sort_date != 0) {
                    $sort = $sort_date == 1 ? 'ASC' : 'DESC';
                    if (strpos($query, 'ORDER BY') === false) {
                        $query .= " ORDER BY `requested_date_time` " . $sort;
                    } else {
                        $query .= ", `requested_date_time` " . $sort;
                    }
                }


                if ($sort_name == 0 && $sort_date == 0) {
                    $query .= " ORDER BY `requested_user_id` DESC";
                }

                $results = Database::search($query);
                $results_count = $results->num_rows;
                $result_per_page = (int)$resultPerPage;

                if ($results_count > $result_per_page) {
                    $number_of_pages = ceil($results_count / $result_per_page);  // Corrected variable name
                } else {
                    $number_of_pages = 1;
                }

                $page_results = ($pageno - 1) * $result_per_page;
                $users = Database::search("" . $query . " LIMIT " . $result_per_page . " OFFSET " . $page_results . "");

?>
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
                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Payment Plan</th>
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
                                    <td class="py-2 align-middle">
                                        <?php
                                        $status_classes = [
                                            "1"     => "table-btn-orange",
                                            "2"  => "table-btn-pink",
                                            "2"  => "table-btn-yellow-green",
                                        ];
                                        $sbutton_class = $status_classes[$data["payment_plan_id"]];
                                        ?>
                                        <button class="table-btn <?php echo $sbutton_class; ?> w-100"> <?php echo $data["payment_plan"]; ?></button>
                                    </td>
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
<?php

            } else {
                http_response_code(400);
                $status->error = 'Invalid JSON';
            }
        } else {
            http_response_code(400);
            $status->error = 'Invalid Content-Type';
        }
    } else {
        http_response_code(400);
        $status->error = 'Invalid request method';
    }
} else {
    $status->error = 'Authentication Failed!';
}

if (property_exists($status, 'error')) {
    echo (json_encode($status));
}


?>