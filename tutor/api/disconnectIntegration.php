<?php
require_once '../../includes/UserClass.php';
require_once "../../includes/connection.php";
require_once "../../includes/UserPermissionsClass.php";

if (!SessionUser::ifUserIsTutor()) {
    echo "Unauthorized request.";
    exit;
}

if (!isset($_POST['integration_id']) || !is_numeric($_POST['integration_id'])) {
    echo "Invalid integration.";
    exit;
}

$integrationId = (int) $_POST['integration_id'];

try {

    Database::iud("
        DELETE FROM `integration_connections`
        WHERE `integration_connection_id` = '" . $integrationId . "'
    ");

    echo "success";
} catch (Exception $e) {
    echo "Failed to disconnect integration.";
}
