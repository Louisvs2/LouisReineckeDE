<?php
/**
 * Verwaltung für louisreinecke.de
 *
 * Eine einzelne Datei, die Login, Übersicht und Uploads erledigt. Sie schreibt
 * data/projects.json und legt Bilder in media/ bzw. locations/ ab, Archive in
 * files/. Die Seite selbst bleibt statisches HTML — PHP läuft nur hier.
 *
 * Das Passwort steht als Hash in admin-config.php. Existiert die Datei noch
 * nicht, führt der erste Aufruf durch die Einrichtung.
 */

declare(strict_types=1);

const WURZEL      = __DIR__;
const DATEN       = WURZEL . '/data/projects.json';
const CONFIG      = WURZEL . '/admin-config.php';
const SPERRDATEI  = WURZEL . '/data/.login-versuche';
const MAX_KANTE   = 2200;
const JPEG_GUETE  = 78;
const MAX_VERSUCHE = 5;
const SPERRE_SEK  = 900;

session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Strict',
    'secure'   => !empty($_SERVER['HTTPS']),
]);
session_start();

$hash = is_file(CONFIG) ? (require CONFIG) : null;
$fehler = '';
$erfolg = '';

/* ---------------------------------------------------------------- Helfer */

function h(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function slug(string $text): string {
    $um = ['ä'=>'ae','ö'=>'oe','ü'=>'ue','ß'=>'ss','Ä'=>'ae','Ö'=>'oe','Ü'=>'ue',
           'é'=>'e','è'=>'e','à'=>'a','â'=>'a','ç'=>'c'];
    $text = strtr(mb_strtolower(trim($text), 'UTF-8'), $um);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    return trim($text, '-') ?: 'eintrag';
}

function daten_lesen(): array {
    if (!is_file(DATEN)) {
        return ['site' => [], 'impressum' => [], 'downloads' => [], 'locations' => [], 'projects' => []];
    }
    $d = json_decode((string)file_get_contents(DATEN), true);
    if (!is_array($d)) {
        throw new RuntimeException('data/projects.json ist beschädigt und wurde nicht angefasst.');
    }
    $d += ['downloads' => [], 'locations' => [], 'projects' => []];
    return $d;
}

/** Erst in eine Nebendatei schreiben, dann umbenennen — so bleibt die
 *  bestehende Datei heil, falls der Schreibvorgang abbricht. */
function daten_schreiben(array $d): void {
    $json = json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException('Die Daten liessen sich nicht speichern.');
    }
    $tmp = DATEN . '.tmp';
    if (file_put_contents($tmp, $json . "\n", LOCK_EX) === false || !rename($tmp, DATEN)) {
        @unlink($tmp);
        throw new RuntimeException('Schreiben fehlgeschlagen. Sind die Rechte auf data/ gesetzt?');
    }
}

function token(): string {
    if (empty($_SESSION['token'])) {
        $_SESSION['token'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['token'];
}

function token_pruefen(): void {
    $gesendet = (string)($_POST['token'] ?? '');
    if ($gesendet === '' || !hash_equals((string)($_SESSION['token'] ?? ''), $gesendet)) {
        throw new RuntimeException('Die Sitzung ist abgelaufen. Bitte erneut anmelden.');
    }
}

/* Einfache Bremse gegen automatisiertes Durchprobieren. */
function versuche_lesen(): array {
    if (!is_file(SPERRDATEI)) return ['n' => 0, 'zeit' => 0];
    $v = json_decode((string)file_get_contents(SPERRDATEI), true);
    return is_array($v) ? $v + ['n' => 0, 'zeit' => 0] : ['n' => 0, 'zeit' => 0];
}

function gesperrt_bis(): int {
    $v = versuche_lesen();
    return $v['n'] >= MAX_VERSUCHE ? $v['zeit'] + SPERRE_SEK : 0;
}

function versuch_vermerken(bool $ok): void {
    if ($ok) { @unlink(SPERRDATEI); return; }
    $v = versuche_lesen();
    @file_put_contents(SPERRDATEI, json_encode(['n' => $v['n'] + 1, 'zeit' => time()]), LOCK_EX);
}

/* ------------------------------------------------------------- Dateien */

/** Legt die Schutzdatei in genau diesen Ordner — niemals eine Ebene hoeher,
 *  sonst wuerde am Ende PHP fuer die ganze Seite abgeschaltet. */
function schutz_sichern(string $ordner): void {
    $ht = $ordner . '/.htaccess';
    if (is_file($ht)) return;
    // php_flag steht in IfModule, weil die Direktive unter FastCGI sonst
    // einen Serverfehler ausloest statt zu wirken.
    @file_put_contents($ht,
        "<IfModule mod_php.c>\n  php_flag engine off\n</IfModule>\n"
      . "<IfModule mod_php7.c>\n  php_flag engine off\n</IfModule>\n"
      . "RemoveHandler .php .phtml .php3 .php4 .php5 .php7 .php8\n"
      . "AddType text/plain .php .phtml\n");
}

/**
 * Stellt einen Upload-Ordner her. $schutz ist der Ordner, der die Schutzdatei
 * bekommt — bei media/<projekt> also media/, bei files/ eben files/ selbst.
 */
function ordner_sichern(string $pfad, string $schutz): void {
    if (!is_dir($pfad) && !mkdir($pfad, 0755, true) && !is_dir($pfad)) {
        throw new RuntimeException("Ordner $pfad liess sich nicht anlegen.");
    }
    if (is_dir($schutz)) {
        schutz_sichern($schutz);
    }
}

/**
 * Nimmt ein hochgeladenes Bild an, prüft es anhand seines Inhalts und legt es
 * verkleinert als JPEG ab. Der ursprüngliche Dateiname wird nie übernommen.
 */
function bild_speichern(array $datei, string $zielPfad): void {
    if ($datei['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload unvollständig: ' . $datei['name']);
    }
    if (!is_uploaded_file($datei['tmp_name'])) {
        throw new RuntimeException('Ungültiger Upload.');
    }

    $info = @getimagesize($datei['tmp_name']);
    if ($info === false) {
        throw new RuntimeException(
            $datei['name'] . ' ist kein lesbares Bild. HEIC aus dem iPhone wird nicht '
            . 'unterstützt — in der Fotos-App als JPEG exportieren.'
        );
    }

    [$breite, $hoehe, $typ] = $info;
    $quelle = match ($typ) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($datei['tmp_name']),
        IMAGETYPE_PNG  => @imagecreatefrompng($datei['tmp_name']),
        IMAGETYPE_WEBP => @imagecreatefromwebp($datei['tmp_name']),
        default        => null,
    };
    if (!$quelle) {
        throw new RuntimeException($datei['name'] . ': nur JPEG, PNG und WebP werden unterstützt.');
    }

    $faktor = min(1, MAX_KANTE / max($breite, $hoehe));
    $nb = max(1, (int)round($breite * $faktor));
    $nh = max(1, (int)round($hoehe * $faktor));

    $ziel = imagecreatetruecolor($nb, $nh);
    imagefill($ziel, 0, 0, imagecolorallocate($ziel, 255, 255, 255));
    imagecopyresampled($ziel, $quelle, 0, 0, 0, 0, $nb, $nh, $breite, $hoehe);
    imagejpeg($ziel, $zielPfad, JPEG_GUETE);
    imagedestroy($ziel);
    imagedestroy($quelle);
}

/** Nimmt ein ZIP an. Geprüft wird die Signatur, nicht die Endung. */
function archiv_speichern(array $datei, string $zielPfad): void {
    if ($datei['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($datei['tmp_name'])) {
        throw new RuntimeException('Upload unvollständig: ' . $datei['name']);
    }
    $kopf = (string)file_get_contents($datei['tmp_name'], false, null, 0, 4);
    if (!str_starts_with($kopf, "PK\x03\x04") && !str_starts_with($kopf, "PK\x05\x06")) {
        throw new RuntimeException('Das ist kein ZIP-Archiv.');
    }
    if (!move_uploaded_file($datei['tmp_name'], $zielPfad)) {
        throw new RuntimeException('Die Datei liess sich nicht ablegen.');
    }
    @chmod($zielPfad, 0644);
}

/** Bringt die Formularfelder eines Mehrfach-Uploads in eine brauchbare Form. */
function dateien_liste(string $feld): array {
    if (empty($_FILES[$feld]['name'][0])) return [];
    $raus = [];
    foreach ($_FILES[$feld]['name'] as $i => $name) {
        if ($_FILES[$feld]['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
        $raus[] = [
            'name'     => $name,
            'tmp_name' => $_FILES[$feld]['tmp_name'][$i],
            'error'    => $_FILES[$feld]['error'][$i],
        ];
    }
    return $raus;
}

function ordner_leeren(string $pfad): void {
    foreach (glob($pfad . '/*.jpg') ?: [] as $f) @unlink($f);
}

function rekursiv_loeschen(string $pfad): void {
    if (!is_dir($pfad)) return;
    foreach (glob($pfad . '/*') ?: [] as $f) {
        is_dir($f) ? rekursiv_loeschen($f) : @unlink($f);
    }
    @rmdir($pfad);
}

/* ------------------------------------------------------------ Einrichtung */

if ($hash === null) {
    if (($_POST['tat'] ?? '') === 'einrichten') {
        $pw  = (string)($_POST['passwort'] ?? '');
        $pw2 = (string)($_POST['passwort2'] ?? '');
        if (mb_strlen($pw) < 10) {
            $fehler = 'Mindestens zehn Zeichen, bitte.';
        } elseif ($pw !== $pw2) {
            $fehler = 'Die beiden Eingaben stimmen nicht überein.';
        } else {
            $inhalt = "<?php\n// Zugangsdaten für admin.php. Zum Zuruecksetzen diese Datei loeschen.\nreturn "
                    . var_export(password_hash($pw, PASSWORD_DEFAULT), true) . ";\n";
            if (file_put_contents(CONFIG, $inhalt) === false) {
                $fehler = 'admin-config.php liess sich nicht schreiben. Rechte im Hauptverzeichnis prüfen.';
            } else {
                @chmod(CONFIG, 0600);
                header('Location: admin.php');
                exit;
            }
        }
    }
    $ansicht = 'einrichten';
} elseif (($_GET['abmelden'] ?? '') !== '') {
    session_destroy();
    header('Location: admin.php');
    exit;
} elseif (empty($_SESSION['angemeldet'])) {
    if (($_POST['tat'] ?? '') === 'anmelden') {
        $bis = gesperrt_bis();
        if ($bis > time()) {
            $fehler = 'Zu viele Fehlversuche. Erneut möglich in '
                    . (int)ceil(($bis - time()) / 60) . ' Minuten.';
        } elseif (password_verify((string)($_POST['passwort'] ?? ''), $hash)) {
            versuch_vermerken(true);
            session_regenerate_id(true);
            $_SESSION['angemeldet'] = true;
            header('Location: admin.php');
            exit;
        } else {
            versuch_vermerken(false);
            $fehler = 'Passwort stimmt nicht.';
        }
    }
    $ansicht = 'anmelden';
} else {
    $ansicht = 'panel';
}

/* ---------------------------------------------------------- Verarbeitung */

if ($ansicht === 'panel' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        token_pruefen();
        $d = daten_lesen();
        $tat = (string)($_POST['tat'] ?? '');

        if ($tat === 'projekt') {
            $titel = trim((string)($_POST['titel'] ?? ''));
            if ($titel === '') throw new RuntimeException('Ein Titel wird gebraucht.');
            $s = slug($titel);

            $bilder = dateien_liste('bilder');
            $ordner = WURZEL . '/media/' . $s;
            ordner_sichern($ordner, WURZEL . '/media');

            $vorhanden = array_values(array_filter($d['projects'], fn($p) => ($p['slug'] ?? '') === $s));
            $pfade = $vorhanden ? ($vorhanden[0]['images'] ?? []) : [];

            if ($bilder) {
                ordner_leeren($ordner);
                $pfade = [];
                $n = 1;
                foreach ($bilder as $b) {
                    $name = sprintf('%02d.jpg', $n);
                    bild_speichern($b, $ordner . '/' . $name);
                    $pfade[] = "media/$s/$name";
                    $n++;
                }
            }

            $video = trim((string)($_POST['video'] ?? ''));
            if ($video !== '' && preg_match('~(?:v=|youtu\.be/|/embed/|/shorts/)([A-Za-z0-9_-]{6,})~', $video, $m)) {
                $video = 'https://www.youtube.com/embed/' . $m[1];
            } elseif ($video !== '' && preg_match('~vimeo\.com/(?:video/)?(\d+)~', $video, $m)) {
                $video = 'https://player.vimeo.com/video/' . $m[1];
            }

            $ort = trim((string)($_POST['ort'] ?? ''));
            $eintrag = [
                'slug'    => $s,
                'title'   => $titel,
                'client'  => trim((string)($_POST['kunde'] ?? '')),
                'year'    => trim((string)($_POST['jahr'] ?? '')),
                'type'    => $video !== '' ? 'Video' : 'Fotografie',
                'tags'    => $ort !== '' ? [$ort] : [],
                'excerpt' => trim((string)($_POST['kurz'] ?? '')),
                'text'    => trim((string)($_POST['text'] ?? '')),
                'cover'   => $pfade[0] ?? '',
                'video'   => $video,
                'images'  => $pfade,
            ];

            $d['projects'] = array_values(array_filter($d['projects'], fn($p) => ($p['slug'] ?? '') !== $s));
            $d['projects'][] = $eintrag;
            usort($d['projects'], fn($a, $b) => strcmp((string)($b['year'] ?? ''), (string)($a['year'] ?? '')));
            daten_schreiben($d);
            $erfolg = sprintf('Projekt %s gespeichert, %d %s.', $titel, count($pfade),
                                count($pfade) === 1 ? 'Bild' : 'Bilder');

        } elseif ($tat === 'ort') {
            $name = trim((string)($_POST['name'] ?? ''));
            if ($name === '') throw new RuntimeException('Ein Name wird gebraucht.');
            $s = slug($name);

            $ordner = WURZEL . '/locations/' . $s;
            ordner_sichern($ordner, WURZEL . '/locations');

            $vorhanden = array_values(array_filter($d['locations'], fn($o) => ($o['slug'] ?? '') === $s));
            $pfade = $vorhanden ? ($vorhanden[0]['images'] ?? []) : [];

            $bilder = dateien_liste('bilder');
            if ($bilder) {
                ordner_leeren($ordner);
                $pfade = [];
                $n = 1;
                foreach ($bilder as $b) {
                    $dn = sprintf('%02d.jpg', $n);
                    bild_speichern($b, $ordner . '/' . $dn);
                    $pfade[] = "locations/$s/$dn";
                    $n++;
                }
            }

            $d['locations'] = array_values(array_filter($d['locations'], fn($o) => ($o['slug'] ?? '') !== $s));
            $d['locations'][] = [
                'slug'    => $s,
                'name'    => $name,
                'address' => trim((string)($_POST['adresse'] ?? '')),
                'images'  => $pfade,
            ];
            daten_schreiben($d);
            $erfolg = sprintf('Ort %s gespeichert, %d %s.', $name, count($pfade),
                                count($pfade) === 1 ? 'Bild' : 'Bilder');

        } elseif ($tat === 'software') {
            $name = trim((string)($_POST['name'] ?? ''));
            if ($name === '') throw new RuntimeException('Ein Name wird gebraucht.');
            $s = slug($name);

            $ordner = WURZEL . '/files';
            ordner_sichern($ordner, $ordner);

            $vorhanden = array_values(array_filter($d['downloads'], fn($x) => ($x['file'] ?? '') === "files/$s.zip"));
            $datei = $vorhanden ? $vorhanden[0]['file'] : '';

            if (!empty($_FILES['archiv']['name'])) {
                archiv_speichern($_FILES['archiv'], $ordner . "/$s.zip");
                $datei = "files/$s.zip";
            }
            if ($datei === '') throw new RuntimeException('Es fehlt das ZIP-Archiv.');

            $d['downloads'] = array_values(array_filter($d['downloads'], fn($x) => ($x['file'] ?? '') !== "files/$s.zip"));
            array_unshift($d['downloads'], [
                'name' => $name,
                'kind' => trim((string)($_POST['art'] ?? '')) ?: 'Script',
                'note' => trim((string)($_POST['beschreibung'] ?? '')),
                'file' => $datei,
            ]);
            daten_schreiben($d);
            $erfolg = $name . ' gespeichert.';

        } elseif ($tat === 'loeschen') {
            $bereich = (string)($_POST['bereich'] ?? '');
            $kennung = (string)($_POST['kennung'] ?? '');

            if ($bereich === 'projekt') {
                $d['projects'] = array_values(array_filter($d['projects'], fn($p) => ($p['slug'] ?? '') !== $kennung));
                rekursiv_loeschen(WURZEL . '/media/' . slug($kennung));
            } elseif ($bereich === 'ort') {
                $d['locations'] = array_values(array_filter($d['locations'], fn($o) => ($o['slug'] ?? '') !== $kennung));
                rekursiv_loeschen(WURZEL . '/locations/' . slug($kennung));
            } elseif ($bereich === 'software') {
                $d['downloads'] = array_values(array_filter($d['downloads'], fn($x) => ($x['file'] ?? '') !== $kennung));
                $pfad = WURZEL . '/' . $kennung;
                if (str_starts_with(realpath(dirname($pfad)) ?: '', WURZEL . '/files')) @unlink($pfad);
            }
            daten_schreiben($d);
            $erfolg = 'Eintrag entfernt.';
        }
    } catch (Throwable $e) {
        $fehler = $e->getMessage();
    }
}

$daten = $ansicht === 'panel' ? daten_lesen() : ['downloads' => [], 'locations' => [], 'projects' => []];
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Verwaltung — Louis Reinecke</title>
<style>
  :root { --ink:#000; --paper:#fff; --akzent:#ff3b00; --grau:#8a8a8a; --linie:#000; }
  * { box-sizing: border-box; }
  body {
    margin:0; background:var(--paper); color:var(--ink);
    font-family:-apple-system, BlinkMacSystemFont, "SF Pro Text", "Inter", "Helvetica Neue", Helvetica, Arial, sans-serif;
    font-size:15px; line-height:1.4; letter-spacing:-0.01em; -webkit-font-smoothing:antialiased;
  }
  .kopf { display:flex; align-items:baseline; justify-content:space-between; gap:16px;
          padding:14px 20px; border-bottom:1px solid var(--linie); }
  .kopf h1 { margin:0; font-size:17px; font-weight:700; letter-spacing:-0.02em; text-transform:uppercase; }
  .kopf a { color:inherit; font-size:12px; font-weight:500; text-transform:uppercase; letter-spacing:.06em; }
  .kopf a:hover { color:var(--akzent); }

  .mitte { max-width:560px; margin:0 auto; padding:56px 20px; }
  .breit { padding:0 20px 60px; }

  h2 { margin:44px 0 14px; font-size:11px; font-weight:500; letter-spacing:.08em;
       text-transform:uppercase; color:var(--grau); }

  form.block { border-top:1px solid var(--linie); padding-top:18px; }

  label { display:block; margin:0 0 14px; }
  label span { display:block; font-size:11px; font-weight:500; letter-spacing:.06em;
               text-transform:uppercase; color:var(--grau); margin-bottom:5px; }
  input[type=text], input[type=password], input[type=file], textarea {
    width:100%; padding:9px 10px; border:1px solid var(--linie); border-radius:0;
    font:inherit; background:var(--paper); color:var(--ink);
  }
  textarea { min-height:78px; resize:vertical; }
  input:focus, textarea:focus { outline:2px solid var(--akzent); outline-offset:-2px; }
  .paar { display:grid; grid-template-columns:repeat(auto-fit, minmax(150px,1fr)); gap:0 16px; }

  button {
    font:inherit; font-weight:700; padding:10px 20px; border:1px solid var(--linie);
    background:var(--akzent); color:var(--paper); cursor:pointer;
    text-transform:uppercase; letter-spacing:.04em; font-size:13px;
  }
  button:hover { background:var(--ink); }
  button.stumm { background:none; color:var(--ink); font-weight:500; padding:4px 10px; font-size:11px; }
  button.stumm:hover { background:var(--ink); color:var(--paper); }

  .hinweis { padding:11px 14px; margin:0 0 20px; font-weight:500; border:1px solid var(--linie); }
  .hinweis--gut { border-color:var(--akzent); color:var(--akzent); }

  .liste { border-top:1px solid var(--linie); }
  .zeile { display:flex; align-items:center; justify-content:space-between; gap:14px;
           padding:9px 0; border-bottom:1px solid #e3e3e3; }
  .zeile b { font-weight:700; }
  .zeile em { font-style:normal; color:var(--grau); font-size:12px; }

  .fuss { padding:26px 20px 50px; color:var(--grau); font-size:12.5px; max-width:70ch; }
</style>
</head>
<body>

<?php if ($ansicht === 'einrichten'): ?>
  <div class="kopf"><h1>Einrichtung</h1></div>
  <div class="mitte">
    <?php if ($fehler): ?><p class="hinweis"><?= h($fehler) ?></p><?php endif; ?>
    <p>Vergib ein Passwort für die Verwaltung. Es wird verschlüsselt in
       <code>admin-config.php</code> abgelegt und lässt sich nicht auslesen.</p>
    <form method="post" class="block">
      <input type="hidden" name="tat" value="einrichten">
      <label><span>Passwort, mindestens zehn Zeichen</span>
        <input type="password" name="passwort" autocomplete="new-password" required></label>
      <label><span>Noch einmal</span>
        <input type="password" name="passwort2" autocomplete="new-password" required></label>
      <button type="submit">Passwort setzen</button>
    </form>
    <p class="fuss">Mach das gleich jetzt: Solange kein Passwort gesetzt ist, könnte
       jeder, der die Adresse kennt, diese Seite übernehmen.</p>
  </div>

<?php elseif ($ansicht === 'anmelden'): ?>
  <div class="kopf"><h1>Verwaltung</h1></div>
  <div class="mitte">
    <?php if ($fehler): ?><p class="hinweis"><?= h($fehler) ?></p><?php endif; ?>
    <form method="post" class="block">
      <input type="hidden" name="tat" value="anmelden">
      <label><span>Passwort</span>
        <input type="password" name="passwort" autocomplete="current-password" autofocus required></label>
      <button type="submit">Anmelden</button>
    </form>
  </div>

<?php else: ?>
  <div class="kopf">
    <h1>Verwaltung</h1>
    <a href="?abmelden=1">Abmelden</a>
  </div>
  <div class="breit">
    <?php if ($fehler): ?><p class="hinweis"><?= h($fehler) ?></p><?php endif; ?>
    <?php if ($erfolg): ?><p class="hinweis hinweis--gut"><?= h($erfolg) ?></p><?php endif; ?>

    <h2>Projekt anlegen oder ersetzen</h2>
    <form method="post" enctype="multipart/form-data" class="block">
      <input type="hidden" name="token" value="<?= h(token()) ?>">
      <input type="hidden" name="tat" value="projekt">
      <label><span>Titel</span><input type="text" name="titel" required></label>
      <div class="paar">
        <label><span>Kunde oder Künstler</span><input type="text" name="kunde"></label>
        <label><span>Jahr</span><input type="text" name="jahr" value="<?= date('Y') ?>"></label>
        <label><span>Ort</span><input type="text" name="ort"></label>
      </div>
      <label><span>Videolink, falls vorhanden</span><input type="text" name="video" placeholder="youtube.com/watch?v=…"></label>
      <label><span>Ein Satz für den Seitenkopf</span><input type="text" name="kurz"></label>
      <label><span>Beschreibung</span><textarea name="text"></textarea></label>
      <label><span>Bilder — mehrere auswählbar, Reihenfolge wie ausgewählt</span>
        <input type="file" name="bilder[]" accept="image/jpeg,image/png,image/webp" multiple></label>
      <button type="submit">Projekt speichern</button>
    </form>

    <?php if ($daten['projects']): ?>
      <div class="liste">
        <?php foreach ($daten['projects'] as $p): ?>
          <div class="zeile">
            <span><b><?= h($p['title'] ?? '') ?></b> <em><?= h($p['year'] ?? '') ?> · <?= count($p['images'] ?? []) ?> Bilder</em></span>
            <form method="post" onsubmit="return confirm('Projekt und Bilder wirklich löschen?')">
              <input type="hidden" name="token" value="<?= h(token()) ?>">
              <input type="hidden" name="tat" value="loeschen">
              <input type="hidden" name="bereich" value="projekt">
              <input type="hidden" name="kennung" value="<?= h($p['slug'] ?? '') ?>">
              <button class="stumm" type="submit">Löschen</button>
            </form>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <h2>Ort anlegen oder ersetzen</h2>
    <form method="post" enctype="multipart/form-data" class="block">
      <input type="hidden" name="token" value="<?= h(token()) ?>">
      <input type="hidden" name="tat" value="ort">
      <label><span>Name</span><input type="text" name="name" required></label>
      <label><span>Adresse</span><input type="text" name="adresse" placeholder="Augsburger Straße, 10789 Berlin"></label>
      <label><span>Bilder</span>
        <input type="file" name="bilder[]" accept="image/jpeg,image/png,image/webp" multiple></label>
      <button type="submit">Ort speichern</button>
    </form>

    <?php if ($daten['locations']): ?>
      <div class="liste">
        <?php foreach ($daten['locations'] as $o): ?>
          <div class="zeile">
            <span><b><?= h($o['name'] ?? '') ?></b> <em><?= h($o['address'] ?? '') ?> · <?= count($o['images'] ?? []) ?> Bilder</em></span>
            <form method="post" onsubmit="return confirm('Ort und Bilder wirklich löschen?')">
              <input type="hidden" name="token" value="<?= h(token()) ?>">
              <input type="hidden" name="tat" value="loeschen">
              <input type="hidden" name="bereich" value="ort">
              <input type="hidden" name="kennung" value="<?= h($o['slug'] ?? '') ?>">
              <button class="stumm" type="submit">Löschen</button>
            </form>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <h2>Software anlegen oder ersetzen</h2>
    <form method="post" enctype="multipart/form-data" class="block">
      <input type="hidden" name="token" value="<?= h(token()) ?>">
      <input type="hidden" name="tat" value="software">
      <label><span>Name</span><input type="text" name="name" required></label>
      <label><span>Art</span><input type="text" name="art" value="AE-Script" placeholder="AE-Script, Premiere-Panel, LUT"></label>
      <label><span>Beschreibung</span><textarea name="beschreibung"></textarea></label>
      <label><span>ZIP-Archiv</span><input type="file" name="archiv" accept=".zip,application/zip"></label>
      <button type="submit">Software speichern</button>
    </form>

    <?php if ($daten['downloads']): ?>
      <div class="liste">
        <?php foreach ($daten['downloads'] as $x): ?>
          <div class="zeile">
            <span><b><?= h($x['name'] ?? '') ?></b> <em><?= h($x['kind'] ?? '') ?></em></span>
            <form method="post" onsubmit="return confirm('Eintrag und Datei wirklich löschen?')">
              <input type="hidden" name="token" value="<?= h(token()) ?>">
              <input type="hidden" name="tat" value="loeschen">
              <input type="hidden" name="bereich" value="software">
              <input type="hidden" name="kennung" value="<?= h($x['file'] ?? '') ?>">
              <button class="stumm" type="submit">Löschen</button>
            </form>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <p class="fuss">Bilder werden beim Hochladen auf <?= MAX_KANTE ?> Pixel längste Kante
      verkleinert und als JPEG gespeichert. Ein erneutes Speichern mit demselben Titel
      ersetzt den Eintrag; lädst du dabei keine Bilder hoch, bleiben die bisherigen erhalten.
      HEIC vom iPhone kann der Server nicht lesen — in der Fotos-App als JPEG exportieren.</p>
  </div>
<?php endif; ?>

</body>
</html>
