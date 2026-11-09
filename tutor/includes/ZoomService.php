<?php

class ZoomService
{
    private static $clientId = "BVj_HavbTf2ZdI1IWnt5Q";
    private static $clientSecret = "Bg4udnjPCW1lM61uaj8lZM0NogXcVCiu";

    public static function refreshAccessToken($connection)
    {
        $refreshToken = $connection["refresh_token"];

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => "https://zoom.us/oauth/token",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                "grant_type" => "refresh_token",
                "refresh_token" => $refreshToken
            ]),
            CURLOPT_HTTPHEADER => [
                "Authorization: Basic " . base64_encode(self::$clientId . ":" . self::$clientSecret),
                "Content-Type: application/x-www-form-urlencoded"
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);

        if ($httpCode !== 200 || !isset($data["access_token"])) {
            throw new Exception("Zoom token refresh failed");
        }

        $accessToken = Database::escape($data["access_token"]);
        $newRefreshToken = Database::escape($data["refresh_token"]);
        $expiresAt = date("Y-m-d H:i:s", time() + ((int)$data["expires_in"] - 60));

        Database::iud("UPDATE `integration_connections`
            SET 
                `access_token`='$accessToken',
                `refresh_token`='$newRefreshToken',
                `token_expires_at`='$expiresAt'
            WHERE `integration_connection_id`='" . $connection["integration_connection_id"] . "'");

        $data["token_expires_at"] = $expiresAt;
        return $data;
    }

    public static function getValidAccessToken($connection)
    {
        if (
            empty($connection["access_token"]) ||
            empty($connection["token_expires_at"]) ||
            strtotime($connection["token_expires_at"]) <= time() + 60
        ) {
            $tokenData = self::refreshAccessToken($connection);
            return $tokenData["access_token"];
        }

        return $connection["access_token"];
    }

    public static function createMeeting($connection, $title, $startDateTime, $duration)
    {
        $accessToken = self::getValidAccessToken($connection);

        $payload = [
            "topic" => $title,
            "type" => 2,
            "start_time" => date("Y-m-d\TH:i:s", strtotime($startDateTime)),
            "timezone" => "Asia/Colombo",
            "duration" => (int)$duration,
            "settings" => [
                "join_before_host" => true,
                "waiting_room" => false,
                "approval_type" => 2,
                "mute_upon_entry" => true
            ]
        ];

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => "https://api.zoom.us/v2/users/me/meetings",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer " . $accessToken,
                "Content-Type: application/json"
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);

        if ($httpCode !== 201 || !isset($data["join_url"])) {
            throw new Exception("Zoom meeting create failed. HTTP: " . $httpCode . " Response: " . $response);
        }

        return $data;
    }
}
