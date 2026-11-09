<?php
$status = new stdClass();
require_once '../../includes/UserClass.php';
require_once '../../includes/UserPermissionsClass.php';
require_once "../../includes/connection.php";
require_once "../../includes/Project.php";
if (SessionUser::ifUserIsTutor()) {
    $sessionUserObject = SessionUser::getCurrentUserObject('Tutor');

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
            $content = trim(file_get_contents('php://input'));
            $decoded = json_decode($content, true);
            if (json_last_error() == JSON_ERROR_NONE) {

                $text = isset($decoded['text']) ? $decoded['text'] : '';
                $paymentStatus = isset($decoded['paymentStatus']) ? $decoded['paymentStatus'] : 0;
                $paymentMethod = isset($decoded['paymentMethod']) ? $decoded['paymentMethod'] : 0;
                $year = isset($decoded['year']) ? $decoded['year'] : 0;
                $month = isset($decoded['month']) ? $decoded['month'] : 0;
                $sortByName = isset($decoded['sortByName']) ? $decoded['sortByName'] : 0;
                $sortById = isset($decoded['sortById']) ? $decoded['sortById'] : 0;
                $sortByDate = isset($decoded['sortByDate']) ? $decoded['sortByDate'] : 0;
                $resultPerPage = isset($decoded['resultPerPage']) ? $decoded['resultPerPage'] : 0;
                $pageno = isset($decoded['page']) ? $decoded['page'] : 0;


                $query = "
                        SELECT 
                            `cpp`.`student_has_payment_id`,
                                `shi`.`student_class_id`,
                            `cpp`.`discount`,
                            `cpp`.`discount_type_discount_type_id`,
                            `cpp`.`due_date`,
                            `cpp`.`amount`,
                            `ps`.`payment_status`,
                            `ps`.`payment_status_id`,
                          
                            `pm`.`payment_method`,
                            `pm`.`payment_method_id`,
                            `cpp`.`amount`,
                            `cpp`.`date_paid`,
                        
                            `ld`.`first_name`,
                            `ld`.`last_name`,
                          
                            `s`.`profile_img`,
                            `s`.`student_id` AS `aId`,
                           SUM(`penalty`.`amount`) AS `panelty_charge`
                                                                                              
                        FROM `student_has_payments` `cpp`
                       
                        INNER JOIN `payment_status` `ps` 
                            ON `cpp`.`payment_status_payment_status_id` = `ps`.`payment_status_id`
                        LEFT JOIN `payment_method` `pm` 
                            ON `cpp`.`payment_method_payment_method_id` = `pm`.`payment_method_id`
                        INNER JOIN `students_has_classes` `shi` 
                            ON `cpp`.`students_has_class_students_has_class_id` = `shi`.`students_has_class_id`
                        INNER JOIN `students` `s` 
                            ON `shi`.`students_student_id` = `s`.`student_id`
                        INNER JOIN `login_details` `ld`
                            ON `s`.`login_details_login_details_id` = `ld`.`login_details_id`    
                       INNER JOIN `classes` `c`
                            ON `shi`.`classes_class_id` = `c`.`class_id`
                       LEFT JOIN student_has_penalty_payments penalty
                            ON penalty.student_has_payments_student_has_payment_id = cpp.student_has_payment_id
                        WHERE `c`.`tutor_id` = '" . $sessionUserObject['user_id'] . "'
                       
                        ";

                if (!empty($text)) {
                    $query .= " AND (`ld`.`first_name` LIKE '%" . $text . "%' OR `ld`.`last_name` LIKE '%" . $text . "%')";
                }

                if ($paymentStatus != 0) {
                    $query .= " AND `ps`.`payment_status_id` = '" . $paymentStatus . "'";
                }

                if ($paymentMethod != 0) {
                    $query .= " AND `pm`.`payment_method_id` = '" . $paymentMethod . "'";
                }

                if ($year != 0) {
                    $query .= " AND YEAR(`cpp`.`date_paid`) = '" . $year . "'";
                }

                if ($month != 0) {
                    $query .= " AND MONTH(`cpp`.`date_paid`) = '" . $month . "'";
                }

                $query .= " GROUP BY `cpp`.`student_has_payment_id`
                        ";

                if ($sortByName != 0) {
                    $query .= " ORDER BY `ld`.`first_name` ASC";
                }

                if ($sortById != 0) {
                    $query .= " ORDER BY `s`.`student_id` ASC";
                }

                if ($sortByDate != 0) {
                    $query .= " ORDER BY `cpp`.`date_paid` ASC";
                }

                if (strpos($query, 'ORDER BY') == false) {
                    $query .= " ORDER BY `cpp`.`student_has_payment_id` DESC";
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
                $payments = Database::search("" . $query . " LIMIT " . $result_per_page . " OFFSET " . $page_results . "");

?>
                <table class="table bl-solid m-0 p-0 g-0" id="payments-table">
                    <thead>
                        <tr class="br-t-5">
                            <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium align-content-center cursor-pointer">
                                <div class="d-flex flex-row align-items-center">
                                    <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                    <div class="d-flex justify-content-between align-items-center w-100" onclick="handleStudentsClassPaymentsPagination(<?= $class_id ?>,'sortByName')">
                                        <span class="text-14 main-text fw-medium mx-2">Student Name</span>
                                        <?php
                                        if ($sortByName == 0) {
                                        ?>
                                            <iconify-icon icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                        <?php
                                        } else {
                                        ?>
                                            <iconify-icon icon="<?= $sortByName == 2 ? 'mingcute:up-fill' : 'mingcute:down-fill' ?>" width="20" height="20" class="dark-theme"></iconify-icon>
                                        <?php
                                        }
                                        ?>
                                    </div>
                                </div>
                            </th>
                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center" onclick="handleStudentsClassPaymentsPagination(<?= $class_id ?>,'sortById')">
                                <div class="d-flex justify-content-between align-items-center cursor-pointer">
                                    <span class="text-14 main-text fw-medium me-2">Student ID</span>
                                    <?php
                                    if ($sortByDate == 0) {
                                    ?>
                                        <iconify-icon icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                    <?php
                                    } else {
                                    ?>
                                        <iconify-icon icon="<?= $sortByDate == 2 ? 'mingcute:up-fill' : 'mingcute:down-fill' ?>" width="20" height="20" class="dark-theme"></iconify-icon>
                                    <?php
                                    }
                                    ?>
                                </div>
                            </th>

                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">
                                <div class="d-flex justify-content-between align-items-center cursor-pointer">
                                    <span class="text-14 main-text fw-medium me-2">Payable Amount</span>
                                </div>
                            </th>
                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Due Date</th>
                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center" onclick="handleStudentsClassPaymentsPagination(<?= $class_id ?>,'sortByDate')">
                                <div class="d-flex justify-content-between align-items-center cursor-pointer">
                                    <span class="text-14 main-text fw-medium me-2">Paid Date</span>
                                    <iconify-icon id="sort-icon" icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                </div>
                            </th>
                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Payment method</th>
                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Status</th>
                            <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                        </tr>
                    </thead>
                    <tbody id="user-table-data">
                        <?php
                        if ($payments->num_rows > 0) {
                            while ($data = $payments->fetch_assoc()) {
                                $discount = $data['discount'];
                                if ($data['discount_type_discount_type_id'] == 3) {
                                    $discount = $data['amount'] * $data['discount'] / 100;
                                }
                                $payableAmount = $data['amount'] - $discount + ($data['panelty_charge'] == null ? 0 : $data['panelty_charge']);
                        ?>
                                <tr>
                                    <td class="px-4 py-3 pe-0 align-middle">
                                        <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                            <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check">
                                            <?php
                                            if (!empty($data["profile_img"]) && file_exists(Project::getAssetsPath() . '/imgs/students/' . $data["profile_img"])) {
                                            ?>
                                                <img src="<?= Project::getAssetsPath() ?>/imgs/students/<?= $data["profile_img"]; ?>" class="hwr-40" alt="User image">
                                            <?php
                                            } else {
                                                $firstNameInitial = strtoupper(substr($data["first_name"], 0, 1));
                                                $lastNameInitial = strtoupper(substr($data["last_name"], 0, 1));
                                            ?>
                                                <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?= $firstNameInitial . $lastNameInitial; ?></div>
                                            <?php
                                            }
                                            ?>
                                            <span class="text-14 main-text mx-2"><?= $data["first_name"] . " " . $data["last_name"]; ?></span>
                                        </div>
                                    </td>

                                    <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data["student_class_id"]; ?></span></td>
                                    <td class="py-2 align-middle"><span class="text-14 main-text"><?= number_format($payableAmount, 2); ?></span></td>
                                    <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data['due_date'] == '' ? "N/A" : $data['due_date'] ?></span></td>


                                    <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data["date_paid"] == '' ? "N/A" : date("Y-m-d h:i A", strtotime($data["date_paid"])); ?>
                                        </span></td>
                                    <td class="py-2 align-middle">
                                        <?php

                                        if ($data['payment_method_id'] == null) {
                                        ?>
                                            <span class="text-14 main-text">N/A</span>
                                        <?php
                                        } else {
                                            $status_classes = [
                                                "1"     => "table-btn-orange",
                                                "2"  => "table-btn-pink",
                                                "3"  => "table-btn-purple",
                                                "4"  => "table-btn-yellow-green",
                                                "5"  => "table-btn-purle-pink",
                                            ];
                                            $sbutton_class = $status_classes[$data["payment_method_id"]];
                                        ?>
                                            <button class="table-btn <?= $sbutton_class; ?> w-100"> <?= $data["payment_method"]; ?></button>
                                        <?php
                                        }

                                        ?>

                                    </td>
                                    <td class="py-2 align-middle">
                                        <?php
                                        $status_classes = [
                                            "0" => "table-btn-purple",
                                            "1" => "table-btn-yellow",
                                            "2" => "table-btn-orange",
                                            "3" => "table-btn-success",
                                            "4" => "table-btn-danger",
                                            "5" => "table-btn-orange",
                                            "6" => "table-btn-success",
                                            "7" => "table-btn-danger",
                                            "8" => "table-btn-success",
                                        ];
                                        $sbutton_class = $status_classes[$data["payment_status_id"]];
                                        ?>
                                        <button class="table-btn <?= $sbutton_class; ?> w-100"> <?= $data["payment_status"]; ?></button>
                                    </td>

                                    <td class="py-3 align-middle">
                                        <div class="dropdown">
                                            <iconify-icon icon="charm:menu-kebab" width="20" height="20" class="justify-content-center sub-text cursor-pointer" id="dropdownMenuButton2" data-bs-toggle="dropdown" aria-expanded="false"></iconify-icon>
                                            <ul class="dropdown-menu border-0 shadow w-100 br-5" aria-labelledby="dropdownMenuButton2">

                                                <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="student-payment?payment-id=<?php echo $data['student_has_payment_id']; ?>&class-id=<?php echo $class_id; ?>">
                                                        <iconify-icon icon="solar:eye-broken" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                        View</a></li>
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
                            <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleStudentsPaymentsPagination('previousPage')">
                                <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                <span class="text-14 main-text fw-semibold">Previous</span>
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
                                        $action = $isActive ? '' : "onclick=\"handleStudentsPaymentsPagination('goToPage', '{$i}')\"";
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
                            <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleStudentsPaymentsPagination('nextPage',null,'<?= $number_of_pages ?>')">
                                <span class="text-14 main-text fw-semibold">Next</span>
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
