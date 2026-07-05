<?php
/**
 * Formular-Handler für walter-uehli.ch (Kontakt & Testleser).
 * Läuft auf PHP-Hosting (z. B. Hostinger). Nimmt POST-Daten entgegen,
 * prüft auf Spam (Honeypot) und sendet eine E-Mail an den Empfänger.
 * Antwortet als JSON, damit das Formular per AJAX abgesendet werden kann.
 */

header('Content-Type: application/json; charset=utf-8');

// --- Konfiguration ---
$EMPFAENGER   = 'info@walter-uehli.ch';
$ABSENDER     = 'no-reply@walter-uehli.ch'; // muss eine Adresse der eigenen Domain sein
$SITE         = 'walter-uehli.ch';

function fail($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('Methode nicht erlaubt.', 405);
}

// Spam-Honeypot: von Menschen unsichtbares Feld. Ist es ausgefüllt → stiller Erfolg.
if (!empty($_POST['_gotcha'])) {
    echo json_encode(['ok' => true]);
    exit;
}

// Pflichtfelder
$name  = trim($_POST['name']  ?? '');
$email = trim($_POST['email'] ?? '');

if ($name === '' || $email === '') {
    fail('Bitte Name und E-Mail ausfüllen.');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fail('Bitte eine gültige E-Mail-Adresse angeben.');
}

// Art des Formulars (für Betreff) und lesbare Feldnamen
$formType = trim($_POST['_form'] ?? 'Kontakt');
$betreff  = 'Neue Nachricht (' . $formType . ') – ' . $SITE;

$labels = [
    'name'            => 'Name',
    'email'           => 'E-Mail',
    'message'         => 'Nachricht',
    'motivation'      => 'Motivation',
    'lesertyp'        => 'Liest am liebsten',
    'format'          => 'Format',
    'vertraulichkeit' => 'Vertraulichkeit bestätigt',
];

// Body zusammenbauen (alle übermittelten Felder ausser Steuerfelder)
$skip = ['_gotcha', '_form', '_subject'];
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

// Header (Reply-To = Absender:in, damit direktes Antworten möglich ist)
$headers  = 'From: ' . $SITE . ' <' . $ABSENDER . ">\r\n";
$headers .= 'Reply-To: ' . $name . ' <' . $email . ">\r\n";
$headers .= "Content-Type: text/plain; charset=utf-8\r\n";
$headers .= 'X-Mailer: PHP/' . phpversion();

$ok = @mail($EMPFAENGER, '=?UTF-8?B?' . base64_encode($betreff) . '?=', $body, $headers);

if ($ok) {
    echo json_encode(['ok' => true]);
} else {
    fail('Die Nachricht konnte nicht gesendet werden. Bitte später erneut versuchen oder direkt an ' . $EMPFAENGER . ' schreiben.', 500);
}
