<?php
// Payments (student): every class fee of the student, laid out like the ENLIXO V3 student portal Payments page
// (balance card, next payment, timeline with All / To Pay / Being Checked / Paid). Data: includes/enlixo/student-fees.php.
require_once '../includes/UserClass.php';
require_once "../includes/connection.php";
require_once "../includes/Project.php";
require_once "../includes/enlixo/student-fees.php";
if (!SessionUser::ifUserIsStudent()) {
    header("Location: ./index");
    exit;
}
$sessionUserObject = SessionUser::getCurrentUserObject('Student');
$rows = sf_fees((int) $sessionUserObject['student_id']);

$lf = ['title' => 'Payments', 'menu' => 'Payments', 'css' => [Project::getAssetsPath() . '/css/student-fees.css']];
include __DIR__ . '/includes/learning-frame-top.php';
sl_page_title('Payments', [], 'Your class fees, discounts and slips in one place. Pay a fee by uploading the bank slip; your tutor checks it.');
sf_render($rows, 'Fees appear here once your tutor sets up the payment plan of your class.');
include __DIR__ . '/includes/learning-frame-bottom.php';
