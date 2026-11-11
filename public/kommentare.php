<?php
/**
 * Kommentarsystem für walter-uehli.ch (Blog).
 *
 *   GET  ?slug=<beitrag>               -> freigegebene Kommentare als JSON
 *   POST (slug, name, email?, text)    -> neuer Kommentar (wartet auf Freigabe)
 *   GET  ?mod=1&slug=..&id=..&t=..     -> Moderationsseite (Link aus der E-Mail)
 *   POST ?mod=1 (slug,id,t,do,reply)   -> freigeben / löschen / antworten
 *
 * Daten liegen AUSSERHALB von public_html (../comments_data), damit Deploys und
 * der Hostinger-Git-Hook sie nicht überschreiben. Keine Datenbank nötig.
 * Spam-Schutz: Honeypot, Zeitprüfung, Rate-Limit, Link-/Keyword-Filter,
 * Duplikat-Erkennung, Freigabe durch Walter.
 */

declare(strict_types=1);
mb_internal_encoding('UTF-8');

$NOTIFY   = 'walter@walter-uehli.ch';
$ABSENDER = 'no-reply@walter-uehli.ch';
$SITE_URL = 'https://walter-uehli.ch';

const MAX_NAME     = 60;
const MAX_EMAIL    = 120;
const MIN_TEXT     = 10;
const MAX_TEXT     = 2000;
const MAX_PER_POST = 300;   // inkl. wartende Kommentare

$DOCROOT = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? __DIR__), '/');
$DATA    = (getenv('COMMENTS_DATA_DIR') ?: dirname($DOCROOT) . '/comments_data');

// ── Hilfsfunktionen ──────────────────────────────────────────────────────────
function json_out(array $d, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}
function fail(string $msg, int $code = 400): void { json_out(['ok' => false, 'error' => $msg], $code); }
function silent_ok(): void { json_out(['ok' => true, 'message' => 'Danke! Dein Kommentar wird nach meiner Prüfung veröffentlicht.']); }
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function clean_line(string $s): string {
    $s = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $s) ?? '';
    return trim(preg_replace('/\s+/u', ' ', $s) ?? '');
}
function clean_text(string $s): string {
    $s = str_replace(["\r\n", "\r"], "\n", $s);
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s) ?? '';
    $s = preg_replace("/\n{3,}/", "\n\n", $s) ?? '';
    return trim($s);
}
function valid_slug(string $slug, string $docroot): bool {
    return (bool)preg_match('/^[a-z0-9][a-z0-9-]{0,100}$/', $slug)
        && is_file($docroot . '/blog/' . $slug . '/index.html');
}

function init_store(string $dir): string {
    if (!is_dir($dir)) { @mkdir($dir, 0700, true); }
    if (!is_dir($dir)) { fail('Kommentare sind gerade nicht verfügbar.', 503); }
    $ht = $dir . '/.htaccess';
    if (!file_exists($ht)) { @file_put_contents($ht, "Require all denied\nDeny from all\n"); }
    $keyfile = $dir . '/secret.key';
    if (!file_exists($keyfile)) {
        @file_put_contents($keyfile, bin2hex(random_bytes(32)));
        @chmod($keyfile, 0600);
    }
    $secret = trim((string)@file_get_contents($keyfile));
    if ($secret === '') { fail('Kommentare sind gerade nicht verfügbar.', 503); }
    return $secret;
}

/** Datei sperren, JSON laden, $fn($data) ändern lassen, speichern. */
function with_store(string $dir, string $slug, callable $fn) {
    $path = $dir . '/' . $slug . '.json';
    $fh = @fopen($path, 'c+');
    if (!$fh) { fail('Kommentare sind gerade nicht verfügbar.', 503); }
    flock($fh, LOCK_EX);
    $raw  = stream_get_contents($fh);
    $data = $raw !== '' && $raw !== false ? (json_decode($raw, true) ?: []) : [];
    $result = $fn($data);
    ftruncate($fh, 0);
    rewind($fh);
    fwrite($fh, json_encode(array_values($data), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    fflush($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
    return $result;
}
function read_store(string $dir, string $slug): array {
    $path = $dir . '/' . $slug . '.json';
    if (!is_file($path)) return [];
    $fh = @fopen($path, 'r');
    if (!$fh) return [];
    flock($fh, LOCK_SH);
    $raw = stream_get_contents($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
    return ($raw !== '' && $raw !== false) ? (json_decode($raw, true) ?: []) : [];
}
function mod_token(string $secret, string $slug, string $id): string {
    return hash_hmac('sha256', $slug . '|' . $id, $secret);
}
function log_err(string $dir, string $msg): void {
    @file_put_contents($dir . '/errors.log', date('c') . ' ' . $msg . "\n", FILE_APPEND | LOCK_EX);
}

// ── Moderationsseite (Link aus der E-Mail) ───────────────────────────────────
function mod_page(string $title, string $body): void {
    header('Content-Type: text/html; charset=utf-8');
    header('X-Robots-Tag: noindex, nofollow');
    header('Referrer-Policy: no-referrer');
    header('Cache-Control: no-store');
    echo '<!doctype html><html lang="de"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<meta name="robots" content="noindex,nofollow"><title>' . h($title) . '</title>'
       . '<style>body{font-family:system-ui,sans-serif;background:#1a1a1a;color:#f5f2ef;margin:0;padding:1.5rem}'
       . '.box{max-width:640px;margin:0 auto;background:#2d2d2d;border:1px solid #383634;border-radius:12px;padding:1.5rem}'
       . 'h1{font-size:1.3rem;margin:0 0 1rem}.meta{color:#aca69f;font-size:.9rem;margin:.2rem 0}'
       . '.txt{white-space:pre-wrap;background:#202020;border-radius:8px;padding:1rem;margin:1rem 0}'
       . 'textarea{width:100%;box-sizing:border-box;min-height:100px;border-radius:8px;border:1px solid #383634;background:#202020;color:#f5f2ef;padding:.6rem;font:inherit}'
       . 'button{font:inherit;border:0;border-radius:8px;padding:.7rem 1.2rem;margin:.8rem .6rem 0 0;cursor:pointer;color:#fff}'
       . '.ok{background:#2e7d32}.del{background:#8b1a2b}.badge{display:inline-block;border-radius:6px;padding:.1rem .5rem;background:#383634}'
       . 'a{color:#e8b4bc}</style></head><body><div class="box">' . $body . '</div></body></html>';
    exit;
}

function handle_moderation(string $DATA, string $secret, string $DOCROOT, string $SITE_URL): void {
    $src  = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
    $slug = (string)($src['slug'] ?? '');
    $id   = (string)($src['id'] ?? '');
    $tok  = (string)($src['t'] ?? '');
    if (!preg_match('/^[a-z0-9][a-z0-9-]{0,100}$/', $slug) || !preg_match('/^[a-f0-9]{16}$/', $id)
        || !hash_equals(mod_token($secret, $slug, $id), $tok)) {
        http_response_code(403);
        mod_page('Kein Zugriff', '<h1>Kein Zugriff</h1><p>Dieser Link ist ungültig.</p>');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $do    = (string)($_POST['do'] ?? '');
        $reply = mb_substr(clean_text((string)($_POST['reply'] ?? '')), 0, MAX_TEXT);
        $found = with_store($DATA, $slug, function (array &$data) use ($id, $do, $reply) {
            foreach ($data as $i => &$c) {
                if (($c['id'] ?? '') !== $id) continue;
                if ($do === 'delete') { unset($data[$i]); return 'deleted'; }
                if ($do === 'approve') {
                    $c['status'] = 'approved';
                    $c['reply'] = $reply;
                    $c['replied_at'] = $reply !== '' ? gmdate('c') : '';
                    return 'approved';
                }
                return 'noop';
            }
            return 'missing';
        });
        $msg = ['deleted' => 'Der Kommentar wurde gelöscht.',
                'approved' => 'Der Kommentar ist jetzt veröffentlicht.',
                'missing' => 'Dieser Kommentar existiert nicht mehr.',
                'noop' => 'Nichts geändert.'][$found] ?? 'Erledigt.';
        mod_page('Erledigt', '<h1>' . h($msg) . '</h1><p><a href="' . h($SITE_URL) . '/blog/' . h($slug) . '/#kommentare">Zum Beitrag</a></p>');
    }

    $comment = null;
    foreach (read_store($DATA, $slug) as $c) { if (($c['id'] ?? '') === $id) { $comment = $c; break; } }
    if (!$comment) { mod_page('Nicht gefunden', '<h1>Dieser Kommentar existiert nicht mehr.</h1>'); }

    $status = ($comment['status'] ?? '') === 'approved' ? 'veröffentlicht' : 'wartet auf Freigabe';
    $mail   = (string)($comment['email'] ?? '');
    $body  = '<h1>Kommentar zu «' . h($slug) . '»</h1>'
           . '<p class="meta">Status: <span class="badge">' . h($status) . '</span></p>'
           . '<p class="meta">Von: <strong>' . h((string)$comment['name']) . '</strong>'
           . ($mail !== '' ? ' (' . h($mail) . ')' : ' (keine E-Mail angegeben)') . '</p>'
           . '<p class="meta">Am: ' . h((string)$comment['created']) . '</p>'
           . '<div class="txt">' . h((string)$comment['text']) . '</div>'
           . '<form method="post" action="?mod=1">'
           . '<input type="hidden" name="slug" value="' . h($slug) . '">'
           . '<input type="hidden" name="id" value="' . h($id) . '">'
           . '<input type="hidden" name="t" value="' . h($tok) . '">'
           . '<label class="meta" for="reply">Deine Antwort (freiwillig, erscheint unter dem Kommentar):</label>'
           . '<textarea id="reply" name="reply" maxlength="' . MAX_TEXT . '">' . h((string)($comment['reply'] ?? '')) . '</textarea>'
           . '<button class="ok" name="do" value="approve">Freigeben &amp; speichern</button>'
           . '<button class="del" name="do" value="delete" onclick="return confirm(\'Kommentar wirklich löschen?\')">Löschen</button>'
           . '</form>';
    mod_page('Kommentar prüfen', $body);
}

// ── Einstieg ─────────────────────────────────────────────────────────────────
$secret = init_store($DATA);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if (isset($_GET['mod'])) {
    handle_moderation($DATA, $secret, $DOCROOT, $SITE_URL);
}

// Liste freigegebener Kommentare
if ($method === 'GET') {
    $slug = (string)($_GET['slug'] ?? '');
    if (!valid_slug($slug, $DOCROOT)) { json_out(['ok' => true, 'comments' => []]); }
    $out = [];
    foreach (read_store($DATA, $slug) as $c) {
        if (($c['status'] ?? '') !== 'approved') continue;
        $out[] = [
            'name'       => (string)$c['name'],
            'text'       => (string)$c['text'],
            'created'    => (string)$c['created'],
            'reply'      => (string)($c['reply'] ?? ''),
            'replied_at' => (string)($c['replied_at'] ?? ''),
        ];
    }
    json_out(['ok' => true, 'comments' => $out]);
}

if ($method !== 'POST') { fail('Methode nicht erlaubt.', 405); }

// ── Neuer Kommentar ──────────────────────────────────────────────────────────
// 1. Honeypot
if (!empty($_POST['_gotcha'])) { silent_ok(); }

// 2. Zeitprüfung: Menschen brauchen mehr als 4 Sekunden
$ts = (int)($_POST['_ts'] ?? 0);
if ($ts === 0 || (time() - $ts) < 4) { silent_ok(); }

// 3. Rate-Limit: max. 3 Kommentare pro IP und Stunde
$ip      = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
$ip_hash = hash('sha256', $ip . $secret);
$rl_dir  = sys_get_temp_dir() . '/wucomment_rl';
if (!is_dir($rl_dir)) { @mkdir($rl_dir, 0700, true); }
$rl_file = $rl_dir . '/' . substr($ip_hash, 0, 32) . '.json';
$now  = time();
$hits = [];
if (is_file($rl_file)) {
    $hits = array_filter(json_decode((string)file_get_contents($rl_file), true) ?: [], fn($t) => ($now - (int)$t) < 3600);
}
if (count($hits) >= 3) { fail('Zu viele Kommentare in kurzer Zeit. Bitte versuche es später noch einmal.', 429); }
$hits[] = $now;
@file_put_contents($rl_file, json_encode(array_values($hits)));

// 4. Felder prüfen
$slug  = (string)($_POST['slug'] ?? '');
if (!valid_slug($slug, $DOCROOT)) { fail('Dieser Beitrag existiert nicht.'); }

$name  = clean_line((string)($_POST['name'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$text  = clean_text((string)($_POST['text'] ?? ''));

if (mb_strlen($name) < 2 || mb_strlen($name) > MAX_NAME) { fail('Bitte gib einen Namen an (2 bis ' . MAX_NAME . ' Zeichen).'); }
if (preg_match('#https?://|www\.#i', $name)) { silent_ok(); }
if ($email !== '') {
    if (mb_strlen($email) > MAX_EMAIL || !filter_var($email, FILTER_VALIDATE_EMAIL)) { fail('Bitte gib eine gültige E-Mail-Adresse an oder lass das Feld leer.'); }
    $blocked = ['mailinator.com','trashmail.com','guerrillamail.com','yopmail.com','sharklasers.com','grr.la',
                'spam4.me','maildrop.cc','dispostable.com','tempmail.com','fakeinbox.com','temp-mail.org','getnada.com'];
    if (in_array(strtolower(substr((string)strrchr($email, '@'), 1)), $blocked, true)) { silent_ok(); }
}
$len = mb_strlen($text);
if ($len < MIN_TEXT) { fail('Dein Kommentar ist zu kurz (mindestens ' . MIN_TEXT . ' Zeichen).'); }
if ($len > MAX_TEXT) { fail('Dein Kommentar ist zu lang (höchstens ' . MAX_TEXT . ' Zeichen).'); }
if (preg_match('#https?://|www\.[a-z]{2,}#i', $text)) { fail('Links sind in Kommentaren nicht erlaubt.'); }
if (preg_match('/\b(casino|poker|viagra|cialis|crypto|bitcoin|backlinks?|click here|earn money|make money|work from home|seo)\b/iu', $name . ' ' . $text)) { silent_ok(); }

// 5. Speichern
$id    = bin2hex(random_bytes(8));
$thash = hash('sha256', mb_strtolower($text));
$stored = with_store($DATA, $slug, function (array &$data) use ($id, $name, $email, $text, $thash, $ip_hash) {
    if (count($data) >= MAX_PER_POST) return 'full';
    foreach ($data as $c) { if (($c['thash'] ?? '') === $thash) return 'dupe'; }
    $data[] = [
        'id' => $id, 'name' => $name, 'email' => $email, 'text' => $text,
        'created' => gmdate('c'), 'status' => 'pending',
        'reply' => '', 'replied_at' => '', 'thash' => $thash, 'ip' => substr($ip_hash, 0, 16),
    ];
    return 'ok';
});
if ($stored === 'full') { fail('Unter diesem Beitrag sind gerade keine weiteren Kommentare möglich.', 429); }
if ($stored === 'dupe') { silent_ok(); }

// 6. Benachrichtigung an Walter
$link    = $SITE_URL . '/kommentare.php?mod=1&slug=' . rawurlencode($slug) . '&id=' . $id . '&t=' . mod_token($secret, $slug, $id);
$betreff = 'Neuer Kommentar zu «' . $slug . '» (Freigabe nötig)';
$body    = "Neuer Kommentar auf walter-uehli.ch\n" . str_repeat('-', 48) . "\n"
         . 'Beitrag: ' . $SITE_URL . '/blog/' . $slug . "/\n"
         . 'Name: ' . $name . "\n"
         . 'E-Mail: ' . ($email !== '' ? $email : '(keine angegeben)') . "\n\n"
         . $text . "\n\n" . str_repeat('-', 48) . "\n"
         . "Prüfen, freigeben, antworten oder löschen:\n" . $link . "\n";
$headers  = 'From: walter-uehli.ch <' . $ABSENDER . ">\r\n";
if ($email !== '') { $headers .= 'Reply-To: ' . $email . "\r\n"; }
$headers .= "Content-Type: text/plain; charset=utf-8\r\n";
$headers .= 'X-Mailer: PHP/' . phpversion();
if (!@mail($NOTIFY, '=?UTF-8?B?' . base64_encode($betreff) . '?=', $body, $headers)) {
    log_err($DATA, 'Mail fehlgeschlagen fuer Kommentar ' . $id . ' (' . $slug . ')');
}

silent_ok();
