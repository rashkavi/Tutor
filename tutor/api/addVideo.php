<?php
$status = new stdClass();
require_once("../../includes/UserClass.php");
require_once("../../includes/connection.php");
require_once("../../includes/UserPermissionsClass.php");
require_once("../../includes/Project.php");
if (SessionUser::ifUserIsTutor()) {
    $sessionUserObject = SessionUser::getCurrentUserObject('Tutor');

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {

        $BUNNY_LIBRARY_ID = Project::getBunnyLibraryId();
        $BUNNY_API_KEY   = Project::getBunnyApiKey();
        if ($BUNNY_API_KEY === '' || $BUNNY_LIBRARY_ID === '') {
            die("Video upload is not configured (set BUNNY_API_KEY on the server)");
        }

        // 1️⃣ Validate input
        if (!isset($_POST['title'], $_FILES['video'])) {
            die("Invalid request");
        }

        $title = trim($_POST['title']);
        $description = trim($_POST['desc']);
        $videoTmpPath = $_FILES['video']['tmp_name'];

        // 2️⃣ Create video on Bunny
        $createData = json_encode([
            "title" => $title
        ]);

        $ch = curl_init("https://video.bunnycdn.com/library/$BUNNY_LIBRARY_ID/videos");
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "AccessKey: $BUNNY_API_KEY",
            "Content-Type: application/json"
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $createData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($response, true);

        if (!isset($result['guid'])) {
            die("Failed to create video on Bunny");
        }

        $bunnyVideoId = $result['guid'];

        // 3️⃣ Upload video file
        $uploadUrl = "https://video.bunnycdn.com/library/$BUNNY_LIBRARY_ID/videos/$bunnyVideoId";

        $ch = curl_init($uploadUrl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "AccessKey: $BUNNY_API_KEY",
            "Content-Type: application/octet-stream"
        ]);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents($videoTmpPath));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $uploadResponse = curl_exec($ch);
        curl_close($ch);

        $col = "`title`,`description`,`bunny_video_id`,`library_id`,`status`";
        $val = "'" . $title . "','" . $description . "','" . $bunnyVideoId . "','" . $BUNNY_LIBRARY_ID . "','processing'";

        Database::iud("INSERT INTO `videos` (" . $col . ") VALUES (" . $val . ")");

        http_response_code(200);
        $status->done = 'The video has been successfully added';
    } else {
        http_response_code(400);
        $status->error = 'Invalid request method';
    }
} else {
    $status->error = 'Authentication Failed!';
}

echo (json_encode($status));
