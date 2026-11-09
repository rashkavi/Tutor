<?php
require_once '../includes/UserClass.php';
require_once "../includes/connection.php";
require_once "../includes/Project.php";
if (!SessionUser::ifUserIsStudent()) {
    header("Location: ./index");
} else {
    $sessionUserObject = SessionUser::getCurrentUserObject('Student');

?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="robots" content="noindex, nofollow">
        <title>Couriered Tute - <?= Project::getPortalBasePath() ?></title>
        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/style.css?v=<?= Project::getVersion() ?>">
        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/skeleton.css?v=<?= Project::getVersion() ?>">
        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/tute-tracker.css?v=<?= Project::getVersion() ?>">
        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/plugins/bootstrap/css/bootstrap.min.css">
        <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
        <script src="<?= Project::getAssetsPath() ?>/js/init.js?v=<?= Project::getVersion() ?>"></script>
        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/lecture-card.css">

    </head>

    <body class="overflow-hidden">
        <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
            <div id="appContainer" class="row p-0">
                <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                    <?php
                    include "includes/side-menu.php";
                    load_side_menu::load("Tutes");
                    ?>
                </div>
                <div id="app-wrapper" class="app-wrapper p-0 m-0">
                    <div class="row p-0 m-0 g-0">
                        <div class="col-12 p-0 m-0 g-0">
                            <?php
                            include "includes/header.php";
                            selectItem::setSelected("Tutes");
                            ?>
                        </div>

                        <div class="container p-4 m-0" id="skeleton-list">
                            <?php
                            include Project::getIncludesPath() . '/components/skeleton-header.php';
                            include Project::getIncludesPath() . '/components/skeleton-add-edit.php'; ?>
                        </div>
                        <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                            <div class="row p-4 m-0 g-0">
                                <div class="col-12 py-4 px-4 pe-4 component-theme mb-4">
                                    <div class="row p-0 m-0 g-0">
                                        <div class="col-12 col-lg-8">
                                            <div class="row">
                                                <span class="main-topic lh-lg" data-i18n="">Rashmi Kaveesha - TD_00256</span>
                                                <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                    <ol class="breadcrumb m-0">
                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./tutes">Classes</a></li>
                                                        <li class="breadcrumb-item sub-text text-decoration-none">Class Details</li>
                                                        <li class="breadcrumb-item sub-text text-decoration-none">Class Tutes</li>
                                                        <li class="breadcrumb-item sub-text text-decoration-none">Couriered Tutes</li>
                                                        <li class="breadcrumb-item sub-text text-decoration-none">TD_00256</li>
                                                    </ol>
                                                </nav>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="container-fluid p-0">
                                    <div class="row g-3 mb-3">
                                        <div class="col-12 col-xl-3">
                                            <div class="card border-0 h-100">
                                                <div class="card-body p-4">
                                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                                        <div class="sub-topic">Student Details</div>
                                                    </div>
                                                    <div class="d-flex align-items-center gap-3 mb-3">
                                                        <div class="avatar-xl">RK</div>
                                                        <div class="flex-grow-1">
                                                            <div class="fw-bold fs-5 mb-0">Rashmi Kaveesha</div>
                                                            <div class="text-muted small">Student ID: ST-1021</div>
                                                        </div>
                                                    </div>

                                                    <div class="d-flex flex-wrap gap-2">
                                                        <span class="pill pill-soft">
                                                            <iconify-icon icon="solar:phone-bold-duotone" class="me-2"></iconify-icon>
                                                            </i> +94 77 123 4567
                                                        </span>
                                                        <span class="pill pill-soft">
                                                            <iconify-icon icon="solar:letter-bold-duotone" class="me-2"></iconify-icon>
                                                            </i> rashmi@email.com
                                                        </span>
                                                        <span class="pill pill-soft">
                                                            <iconify-icon icon="icon-park-solid:classroom" class="me-2"></iconify-icon>
                                                            Grade 11 • Science
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12 col-xl-3">
                                            <div class="card border-0 h-100">
                                                <div class="card-body p-4">

                                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                                        <div class="sub-topic">Delivery Address</div>
                                                    </div>

                                                    <div class="text-muted small mb-3">
                                                        No. 21, Temple Road,<br>
                                                        Nugegoda, Colombo 05,<br>
                                                        Sri Lanka
                                                    </div>

                                                    <div class="d-flex flex-wrap gap-2">
                                                        <span class="pill pill-soft">
                                                            <iconify-icon icon="solar:user-check-bold-duotone" class="me-2"></iconify-icon>
                                                            </i>Receiver: Rashmi
                                                        </span>
                                                        <span class="pill pill-soft">
                                                            <iconify-icon icon="solar:phone-bold-duotone" class="me-2"></iconify-icon>
                                                            +94 77 123 4567
                                                        </span>
                                                    </div>

                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12 col-xl-6">
                                            <div class="card border-0 h-100">
                                                <div class="card-body p-4">

                                                    <div class="d-flex align-items-start justify-content-between gap-2">
                                                        <div>
                                                            <div class="sub-topic mb-1">Current Status</div>

                                                            <!-- Change class based on status: status-chip delivered/intransit/pending/cancelled -->
                                                            <div class="status-chip intransit mb-2">
                                                                <span class="dot"></span>
                                                                In Delivery
                                                            </div>

                                                            <div class="text-muted small">
                                                                Last updated: <span class="fw-semibold">2026-02-16 • 10:24 AM</span>
                                                            </div>
                                                        </div>

                                                        <div class="text-end">
                                                            <div class="text-muted small mb-1">Tracking ID</div>
                                                            <div class="fw-bold fs-5 tracking-id">TRK-ENX-902144</div>
                                                            <button class="btn btn-outline-primary btn-sm rounded-pill px-3 mt-2"
                                                                onclick="copyTracking('TRK-ENX-902144')">
                                                                <iconify-icon icon="solar:clipboard-text-bold-duotone" class="me-2"></iconify-icon>
                                                                Copy
                                                            </button>
                                                        </div>
                                                    </div>

                                                    <hr class="my-3">

                                                    <div class="d-flex flex-wrap gap-2">
                                                        <span class="pill pill-soft">
                                                            <iconify-icon icon="solar:delivery-bold-duotone" class="me-2"></iconify-icon>
                                                            Courier: CityLine Express
                                                        </span>
                                                        <span class="pill pill-soft">
                                                            <iconify-icon icon="solar:box-bold-duotone" class="me-2"></iconify-icon>
                                                            Package: 1.2 kg
                                                        </span>
                                                        <span class="pill pill-soft">
                                                            <iconify-icon icon="solar:calendar-mark-bold-duotone" class="me-2"></iconify-icon>
                                                            Dispatch: 2026-02-15
                                                        </span>
                                                    </div>

                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row g-3">

                                        <!-- Tute Package Details -->
                                        <div class="col-12 col-xl-7">
                                            <div class="card border-0">
                                                <div class="card-body p-4">

                                                    <div class="d-flex align-items-center justify-content-between mb-3">
                                                        <div class="sub-topic">Tute Package Details</div>
                                                        <span class="badge rounded-pill bg-primary-subtle text-primary border border-primary-subtle px-3 py-2">
                                                            Package ID: ORD-7021
                                                        </span>
                                                    </div>

                                                    <div class="table-responsive-wrapper">
                                                        <div class="table-responsive" id="students-table-container">
                                                            <table class="table bl-solid m-0 p-0 g-0" id="students-table">
                                                                <thead>
                                                                    <tr class="br-t-5">
                                                                        <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium align-content-center w-25">
                                                                            <div class="d-flex flex-row align-items-center cursor-pointer">
                                                                                <span class="text-14 main-text fw-medium">Tute</span>
                                                                            </div>
                                                                        </th>
                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center"></th>
                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Subject</th>
                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Month</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody id="user-table-data">
                                                                    <tr>
                                                                        <td colspan="2" class="px-4 py-3 align-middle">
                                                                            <div class="d-flex flex-column">
                                                                                <span class="fw-semibold">Mathematics Tute</span>
                                                                                <span class="text-muted small">Printed • A4 • Stapled</span>
                                                                            </div>
                                                                        </td>
                                                                        <td class="py-2 align-middle"><span class="text-14 main-text">Mathematics</span></td>
                                                                        <td class="py-2 align-middle"><span class="text-14 main-text">March</span></td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td colspan="2" class="px-4 py-3 align-middle">
                                                                            <div class="d-flex flex-column">
                                                                                <span class="fw-semibold">Mathematics Paper</span>
                                                                                <span class="text-muted small">Printed • A4 • Stapled</span>
                                                                            </div>
                                                                        </td>
                                                                        <td class="py-2 align-middle"><span class="text-14 main-text">Mathematics</span></td>
                                                                        <td class="py-2 align-middle"><span class="text-14 main-text">March</span></td>
                                                                    </tr>
                                                                </tbody>
                                                            </table>

                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Timeline -->
                                        <div class="col-12 col-xl-5">
                                            <div class="card border-0 h-100">
                                                <div class="card-body p-4">

                                                    <div class="d-flex align-items-center justify-content-between mb-3">
                                                        <div class="sub-topic">Tracking Timeline</div>
                                                        <span class="badge rounded-pill bg-light text-dark border px-3 py-2 timeline-pill">
                                                            <span class="live-dot me-2"></span>Updated live
                                                        </span>
                                                    </div>

                                                    <div class="timeline-v2">
                                                        <div class="tl-item active">
                                                            <div class="tl-dot"></div>
                                                            <div class="tl-card">
                                                                <div class="d-flex align-items-start justify-content-between gap-2">
                                                                    <div>
                                                                        <div class="tl-title">In Delivery</div>
                                                                        <div class="tl-desc">Courier is delivering the package.</div>
                                                                    </div>
                                                                    <span class="tl-badge tl-badge-blue">In Transit</span>
                                                                </div>

                                                                <div class="tl-time">
                                                                    <iconify-icon icon="solar:clock-circle-bold-duotone" class="me-2"></iconify-icon>
                                                                    2026-02-16 • 10:24 AM
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="tl-item">
                                                            <div class="tl-dot"></div>
                                                            <div class="tl-card">
                                                                <div class="tl-title">Arrived at Distribution Hub</div>
                                                                <div class="tl-desc">Package received at the local hub.</div>
                                                                <div class="tl-time">
                                                                    <iconify-icon icon="solar:clock-circle-bold-duotone" class="me-2"></iconify-icon>
                                                                    2026-02-16 • 08:10 AM
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="tl-item">
                                                            <div class="tl-dot"></div>
                                                            <div class="tl-card">
                                                                <div class="tl-title">Dispatched</div>
                                                                <div class="tl-desc">Shipment has been dispatched.</div>
                                                                <div class="tl-time">
                                                                    <iconify-icon icon="solar:clock-circle-bold-duotone" class="me-2"></iconify-icon>
                                                                    2026-02-15 • 06:40 PM
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="tl-item">
                                                            <div class="tl-dot"></div>
                                                            <div class="tl-card">
                                                                <div class="tl-title">Order Confirmed</div>
                                                                <div class="tl-desc">Order confirmed by the system.</div>
                                                                <div class="tl-time">
                                                                    <iconify-icon icon="solar:clock-circle-bold-duotone" class="me-2"></iconify-icon>
                                                                    2026-02-15 • 02:12 PM
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                </div>

                                <!-- Update Status Modal (Optional) -->
                                <div class="modal fade" id="updateStatusModal" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content border-0 rounded-4 shadow">
                                            <div class="modal-header border-0 px-4 pt-4">
                                                <h5 class="modal-title fw-bold">Update Delivery Status</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body px-4 pb-4">
                                                <label class="form-label text-muted small">Status</label>
                                                <select class="form-select rounded-3">
                                                    <option>Pending Shipment</option>
                                                    <option>Dispatched</option>
                                                    <option>Arrived at Hub</option>
                                                    <option>In Delivery</option>
                                                    <option>Delivered</option>
                                                    <option>Cancelled</option>
                                                </select>

                                                <label class="form-label text-muted small mt-3">Note (optional)</label>
                                                <textarea class="form-control rounded-3" rows="3" placeholder="Add update note..."></textarea>

                                                <div class="d-flex justify-content-end gap-2 mt-4">
                                                    <button class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                                                    <button class="btn btn-primary rounded-pill px-4">Save Update</button>
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

        <script src="<?= Project::getAssetsPath() ?>/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
        <script src="<?= Project::getAssetsPath() ?>/plugins/jquery/jquery-3.7.1.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script src="<?= Project::getAssetsPath() ?>/js/script.js?v=<?= Project::getVersion() ?>"></script>
        <script src="<?= Project::getAssetsPath() ?>/js/functions.js?v=<?= Project::getVersion() ?>"></script>
        <script src="js/script.js?v=<?= Project::getVersion() ?>"></script>

    </body>

    </html>
<?php

}
?>