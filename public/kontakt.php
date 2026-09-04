<?php
/**
 * Formular-Handler für walter-uehli.ch (Kontakt & Testleser).
 * Spam-Schutz: Honeypot, Rate-Limit, Mindestlänge, URL-Erkennung,
 * Name-Validierung, Blockliste, Timing-Check.
 */

header('Content-Type: application/json; charset=utf-8');

$EMPFAENGER = 'info@walter-uehli.ch';
$ABSENDER   = 'no-reply@walter-uehli.ch';
$SITE       = 'walter-uehli.ch';

function fail($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

function silent_ok() {
    echo json_encode(['ok' => true]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('Methode nicht erlaubt.', 405);
}

// ── 1. HONEYPOT ──────────────────────────────────────────────────────────────
if (!empty($_POST['_gotcha'])) {
    silent_ok();
}

// ── 2. TIMING-CHECK ──────────────────────────────────────────────────────────
// Bots senden sofort. Echter Mensch braucht mindestens 4 Sekunden.
$submitted_at = intval($_POST['_ts'] ?? 0);
if ($submitted_at === 0 || (time() - $submitted_at) < 4) {
    silent_ok();
}

// ── 3. RATE-LIMIT (max. 3 Einreichungen pro IP pro Stunde) ───────────────────
$ip       = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$ip_hash  = md5($ip);
$rl_dir   = sys_get_temp_dir() . '/wuform_rl';
if (!is_dir($rl_dir)) @mkdir($rl_dir, 0700, true);
$rl_file  = $rl_dir . '/' . $ip_hash . '.json';
$now      = time();
$window   = 3600;
$max_hits = 3;
$hits     = [];
if (file_exists($rl_file)) {
    $data = json_decode(file_get_contents($rl_file), true) ?: [];
    $hits = array_filter($data, fn($t) => ($now - $t) < $window);
}
if (count($hits) >= $max_hits) {
    fail('Zu viele Anfragen. Bitte später erneut versuchen oder direkt per E-Mail schreiben.', 429);
}
$hits[] = $now;
@file_put_contents($rl_file, json_encode(array_values($hits)));

// ── 4. PFLICHTFELDER ─────────────────────────────────────────────────────────
$name  = trim($_POST['name']  ?? '');
$email = trim($_POST['email'] ?? '');

if ($name === '' || $email === '') {
    fail('Bitte Name und E-Mail ausfüllen.');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fail('Bitte eine gültige E-Mail-Adresse angeben.');
}

// ── 5. NAME-VALIDIERUNG ───────────────────────────────────────────────────────
// Kein Leerzeichen + über 12 Zeichen = Bot-Username (z.B. «Vitamixyob»)
if (strlen($name) > 12 && strpos($name, ' ') === false) {
    silent_ok();
}
// Name darf keine URLs enthalten
if (preg_match('#https?://#i', $name) || preg_match('#www\.#i', $name)) {
    silent_ok();
}

// ── 6. E-MAIL-DOMAIN-BLOCKLISTE ──────────────────────────────────────────────
$blocked_domains = [
    'mailinator.com', 'trashmail.com', 'guerrillamail.com', 'yopmail.com',
    'throwam.com', 'sharklasers.com', 'grr.la', 'spam4.me', 'maildrop.cc',
    'dispostable.com', 'tempmail.com', 'fakeinbox.com', 'temp-mail.org',
    'getnada.com', 'guerrillamailblock.com',
];
$email_domain = strtolower(substr(strrchr($email, '@'), 1));
if (in_array($email_domain, $blocked_domains, true)) {
    silent_ok();
}

// ── 7. NACHRICHT: MINDESTLÄNGE + URL- UND SPAM-CHECK ─────────────────────────
$formType   = trim($_POST['_form'] ?? 'Kontakt');
$message    = trim($_POST['message']    ?? '');
$motivation = trim($_POST['motivation'] ?? '');
$main_text  = $formType === 'Testleser' ? $motivation : $message;

// Kontakt: Nachricht ist Pflicht
if ($formType === 'Kontakt' && strlen($main_text) < 10) {
    fail('Bitte eine Nachricht eingeben (mindestens 10 Zeichen).');
}
// URL in der Nachricht = fast immer Spam
if (preg_match('#https?://#i', $main_text) || preg_match('#www\.[a-z]{2,}#i', $main_text)) {
    silent_ok();
}
// Typische Spam-Schlüsselwörter
$spam_keywords = [
    'casino', 'poker', 'viagra', 'cialis', 'crypto', 'bitcoin',
    'investment', 'loan', 'mortgage', 'backlink', 'SEO', 'ranking',
    'click here', 'earn money', 'make money', 'work from home',
];
$combined_text = strtolower($main_text . ' ' . $name);
foreach ($spam_keywords as $kw) {
    if (str_contains($combined_text, strtolower($kw))) {
        silent_ok();
    }
}

// ── 8. E-MAIL ZUSAMMENBAUEN UND SENDEN ───────────────────────────────────────
$betreff = 'Neue Nachricht (' . $formType . ') – ' . $SITE;

$labels = [
    'name'            => 'Name',
    'email'           => 'E-Mail',
    'message'         => 'Nachricht',
    'motivation'      => 'Motivation',
    'lesertyp'        => 'Liest am liebsten',
    'format'          => 'Format',
    'vertraulichkeit' => 'Vertraulichkeit bestätigt',
];

$skip  = ['_gotcha', '_form', '_subject', '_ts'];
$lines = ["Neue Einsendung über {$formType}-Formular auf {$SITE}", str_repeat('-', 48)];
foreach ($_POST as $key => $value) {
    if (in_array($key, $skip, true)) continue;
    if (is_array($value)) $value = implode(', ', $value);
    $value = trim($value);
    if ($value === '') continue;
    $label = $labels[$key] ?? ucfirst($key);
    if ($key === 'vertraulichkeit') $value = 'Ja';
    $lines[] = $label . ': ' . $value;
}
$body = implode("\n", $lines) . "\n";

$headers  = 'From: ' . $SITE . ' <' . $ABSENDER . ">\r\n";
$headers .= 'Reply-To: ' . $name . ' <' . $email . ">\r\n";
$headers .= "Content-Type: text/plain; charset=utf-8\r\n";
$headers .= 'X-Mailer: PHP/' . phpversion();

$ok = @mail($EMPFAENGER, '=?UTF-8?B?' . base64_encode($betreff) . '?=', $body, $headers);

if ($ok) {
    echo json_encode(['ok' => true]);
} else {
    fail('Die Nachricht konnte nicht gesendet werden. Bitte direkt an ' . $EMPFAENGER . ' schreiben.', 500);
}
