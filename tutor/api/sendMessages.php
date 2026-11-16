<?php
// Replaced by the Message Center API (POST /api/v1/tutor/messages, src/Messaging/MessageService.php), which really
// sends the e-mails / WhatsApp messages. The old endpoint only saved a row and took class ids straight into SQL.
http_response_code(410);
header('Content-Type: application/json');
echo json_encode(['error' => 'Message Center was updated. Please reload the page.']);
