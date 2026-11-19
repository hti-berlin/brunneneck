<?php
/**
 * Kontaktformular Brunneneck – versendet Anfragen per E-Mail.
 * Läuft auf dem IONOS-Webspace (PHP mail()).
 */

$empfaenger = 'kontakt@tiedemann-gruppe.de';
$absender   = 'website@brunneneckberlin.de';   // Absenderadresse der Website
$danke      = 'danke.html';
$fehler     = 'danke.html?fehler=1';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.html');
    exit;
}

// Spam-Schutz: verstecktes Feld muss leer bleiben
if (!empty($_POST['website'])) {
    header('Location: ' . $danke);
    exit;
}

function feld($name, $max = 2000) {
    $wert = isset($_POST[$name]) ? trim((string) $_POST[$name]) : '';
    $wert = str_replace(["\r\n", "\r"], "\n", $wert);
    return mb_substr($wert, 0, $max);
}
function zeile($wert) {
    // keine Zeilenumbrüche in Kopfzeilen
    return trim(preg_replace('/[\r\n]+/', ' ', $wert));
}

$name      = zeile(feld('name', 200));
$telefon   = zeile(feld('telefon', 100));
$email     = zeile(feld('email', 200));
$interesse = zeile(feld('interesse', 200));
$nachricht = feld('nachricht', 5000);
$seite     = zeile(feld('seite', 200));

if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || empty($_POST['datenschutz'])) {
    header('Location: ' . $fehler);
    exit;
}

$betreff = 'Anfrage Brunneneck' . ($interesse !== '' ? ' – ' . $interesse : '');
$text  = "Neue Anfrage über brunneneckberlin.de\n\n";
$text .= "Name:       $name\n";
$text .= "E-Mail:     $email\n";
$text .= "Telefon:    " . ($telefon !== '' ? $telefon : '–') . "\n";
$text .= "Interesse:  " . ($interesse !== '' ? $interesse : '–') . "\n";
$text .= "Seite:      " . ($seite !== '' ? $seite : '–') . "\n\n";
$text .= "Nachricht:\n" . ($nachricht !== '' ? $nachricht : '–') . "\n\n";
$text .= "Datenschutzerklärung akzeptiert: ja\n";
$text .= "Gesendet: " . date('d.m.Y H:i') . "\n";

$kopf  = "From: Brunneneck Website <$absender>\r\n";
$kopf .= "Reply-To: " . (preg_match('/^[^<>]+$/', $name) ? "$name <$email>" : $email) . "\r\n";
$kopf .= "MIME-Version: 1.0\r\n";
$kopf .= "Content-Type: text/plain; charset=UTF-8\r\n";
$kopf .= "Content-Transfer-Encoding: 8bit\r\n";

$ok = mail($empfaenger, '=?UTF-8?B?' . base64_encode($betreff) . '?=', $text, $kopf, '-f' . $absender);

header('Location: ' . ($ok ? $danke : $fehler));
exit;
