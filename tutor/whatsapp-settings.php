<?php
// Tutor → Settings → WhatsApp: how Message Center sends WhatsApp messages (src/Messaging/MessageService.php):
// from the tutor's phone (free), ENLIXO's number (add-on), or the tutor's own WhatsApp Business number (GROW),
// connected with Facebook (Embedded Signup, when ENLIXO has set it up) or with the API details.
// Drawn by assets/js/enlixo/pages/app-tutor-whatsapp.js.
$tp = [
    'title' => 'WhatsApp Settings',
    'menu' => 'WhatsApp',
    'crumbs' => [['Settings', null], ['WhatsApp', null]],
    'guide' => [
        'intro' => 'Choose how your Message Center WhatsApp messages are sent.',
        'points' => [
            'From my phone: free and ready now. Message Center opens WhatsApp with the message for each student; you press Send.',
            'My WhatsApp Business number: messages go out by themselves from your own number. Connect it once.',
            'You can keep using the WhatsApp Business app on your phone after connecting with Facebook.',
        ],
    ],
    'actions' => 'wa-actions',
    'root' => 'wa-page',
    'js' => 'app-tutor-whatsapp.js',
    'css' => ['messages.css'],
];
include 'includes/frame-top.php';
include 'includes/frame-bottom.php';
