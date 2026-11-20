<?php
/**
 * Interessentenliste Brunneneck – Anmeldung mit Double-Opt-in.
 *
 * 1. POST (Formular):  speichert die Anmeldung vorläufig und sendet eine Bestätigungsmail.
 * 2. GET ?bestaetigen=TOKEN: Eintrag wird bestätigt, in die Liste übernommen
 *    und als Mail an kontakt@tiedemann-gruppe.de gemeldet.
 *
 * Daten liegen im geschützten Ordner _daten/ (kein Abruf über das Web).
 */

$empfaenger = 'kontakt@tiedemann-gruppe.de';
$absender   = 'website@brunneneckberlin.de';
$basis      = 'https://brunneneckberlin.de/';
$seite      = 'interessent.html';
$gueltig    = 7 * 24 * 3600;   // Bestätigungslink 7 Tage gültig

$dir     = __DIR__ . '/_daten';
$pending = $dir . '/offen';
$liste   = $dir . '/interessenten.csv';

$en = (($_POST['sprache'] ?? $_GET['lang'] ?? '') === 'en');

function weiter($status) {
    global $seite, $en;
    header('Location: ' . $seite . '?s=' . $status . ($en ? '&lang=en' : ''));
    exit;
}
function zeile($wert) {
    return trim(preg_replace('/[\r\n;]+/', ' ', (string) $wert));
}
function senden($an, $betreff, $text, $antwortAn = null) {
    global $absender;
    $kopf  = "From: Brunneneck <$absender>\r\n";
    if ($antwortAn) $kopf .= "Reply-To: $antwortAn\r\n";
    $kopf .= "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n";
    return mail($an, '=?UTF-8?B?' . base64_encode($betreff) . '?=', $text, $kopf, '-f' . $absender);
}

// Ordner anlegen und gegen Abruf über das Web sperren
if (!is_dir($pending)) { @mkdir($pending, 0750, true); }
if (!file_exists($dir . '/.htaccess')) {
    file_put_contents($dir . '/.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\n  Deny from all\n</IfModule>\n");
}
// Abgelaufene, nie bestätigte Anmeldungen löschen
foreach (glob($pending . '/*.json') ?: [] as $f) {
    if (filemtime($f) < time() - $gueltig) @unlink($f);
}

/* ---------- 2. Bestätigung ---------- */
if (isset($_GET['bestaetigen'])) {
    $token = (string) $_GET['bestaetigen'];
    if (!preg_match('/^[a-f0-9]{32}$/', $token)) weiter('ungueltig');
    $datei = $pending . '/' . $token . '.json';
    if (!is_file($datei)) weiter('ungueltig');
    $d = json_decode(file_get_contents($datei), true);
    @unlink($datei);
    if (!$d) weiter('ungueltig');
    $en = (($d['sprache'] ?? '') === 'en');

    $jetzt = date('d.m.Y H:i');
    $neu = !file_exists($liste);
    $fh = fopen($liste, 'a');
    if ($neu) fwrite($fh, "\xEF\xBB\xBFName;E-Mail;Interesse;Angemeldet;Bestätigt\n");
    fwrite($fh, implode(';', [zeile($d['name']), zeile($d['email']), zeile($d['interesse']), $d['zeit'], $jetzt]) . "\n");
    fclose($fh);

    $text  = "Neuer Eintrag in der Interessentenliste Brunneneck (Double-Opt-in bestätigt)\n\n";
    $text .= "Name:        {$d['name']}\n";
    $text .= "E-Mail:      {$d['email']}\n";
    $text .= "Interesse:   {$d['interesse']}\n";
    $text .= "Sprache:     " . ($en ? 'Englisch' : 'Deutsch') . "\n";
    $text .= "Angemeldet:  {$d['zeit']}\n";
    $text .= "Bestätigt:   $jetzt\n\n";
    $text .= "Abmeldungen bitte formlos bearbeiten und den Eintrag aus dem Verteiler löschen.\n";
    senden($empfaenger, 'Interessentenliste Brunneneck: ' . $d['name'], $text, $d['email']);
    weiter('bestaetigt');
}

/* ---------- 1. Anmeldung ---------- */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: index.html'); exit; }
if (!empty($_POST['website'])) weiter('gesendet');   // Spam-Schutz

$name      = mb_substr(zeile($_POST['name'] ?? ''), 0, 200);
$email     = mb_substr(zeile($_POST['email'] ?? ''), 0, 200);
$interesse = mb_substr(zeile($_POST['interesse'] ?? 'Penthouses'), 0, 100);
if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || empty($_POST['einwilligung'])) weiter('fehler');

$token = bin2hex(random_bytes(16));
$ok = file_put_contents($pending . '/' . $token . '.json', json_encode([
    'name' => $name, 'email' => $email, 'interesse' => $interesse, 'zeit' => date('d.m.Y H:i'), 'sprache' => $en ? 'en' : 'de',
], JSON_UNESCAPED_UNICODE));
if ($ok === false) weiter('fehler');

$link  = $basis . 'interessent.php?bestaetigen=' . $token;
if ($en) {
    $betreff = 'Please confirm: Brunneneck interest list';
    $text  = "Dear $name,\n\n";
    $text .= "thank you for your interest in the Brunneneck in Berlin-Mitte.\n\n";
    $text .= "Please confirm your registration for our interest list by clicking this link:\n\n";
    $text .= "$link\n\n";
    $text .= "We will only add you once you have confirmed. The link is valid for 7 days. If you did not register, simply ignore this email - nothing will be stored.\n\n";
    $text .= "You can unsubscribe at any time with a short message to $empfaenger.\n\n";
    $text .= "Kind regards\nTiedemann Gruppe\nGroße Hamburger Straße 32, 10115 Berlin, Germany\nPhone +49 30 208480216\n";
} else {
    $betreff = 'Bitte bestätigen: Interessentenliste Brunneneck';
    $text  = "Guten Tag $name,\n\n";
    $text .= "vielen Dank für Ihr Interesse am Brunneneck in Berlin-Mitte.\n\n";
    $text .= "Bitte bestätigen Sie Ihre Anmeldung zur Interessentenliste mit einem Klick auf diesen Link:\n\n";
    $text .= "$link\n\n";
    $text .= "Erst danach nehmen wir Sie auf. Der Link ist 7 Tage gültig. Wenn Sie sich nicht angemeldet haben, ignorieren Sie diese E-Mail einfach – es wird nichts gespeichert.\n\n";
    $text .= "Abmelden können Sie sich jederzeit mit einer kurzen Nachricht an $empfaenger.\n\n";
    $text .= "Mit freundlichen Grüßen\nTiedemann Gruppe\nGroße Hamburger Straße 32, 10115 Berlin\nTelefon 030 / 20 84 80 216\n";
}

weiter(senden($email, $betreff, $text, $empfaenger) ? 'gesendet' : 'fehler');
