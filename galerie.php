<?php
/**
 * Kundengalerie.
 *
 * Aufruf über louisreinecke.de/galerie.php?k=<schluessel>. Der Schlüssel ist
 * eine lange Zufallsfolge und die einzige Zugangsvoraussetzung — die Galerie
 * ist nirgends verlinkt und für Suchmaschinen gesperrt.
 *
 * Mit &dl=1 leitet die Datei zum hinterlegten Übertragungslink weiter. So
 * steht dessen Adresse nirgends im Quelltext und der Kunde sieht sie nicht.
 */

declare(strict_types=1);

const GALERIEN = __DIR__ . '/data/galerien.json';

function h(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

$schluessel = (string)($_GET['k'] ?? '');
$galerie = null;

// Nur Hex-Zeichen zulassen, damit der Parameter nie in einen Pfad geraten kann.
if (preg_match('/^[a-f0-9]{16,64}$/', $schluessel) && is_file(GALERIEN)) {
    $alle = json_decode((string)file_get_contents(GALERIEN), true);
    if (is_array($alle)) {
        foreach ($alle as $g) {
            if (hash_equals((string)($g['key'] ?? ''), $schluessel)) {
                $galerie = $g;
                break;
            }
        }
    }
}

if ($galerie === null) {
    http_response_code(404);
    $titel = 'Nicht gefunden';
} else {
    $titel = (string)($galerie['title'] ?? 'Galerie');
}

// Weiterleitung zu den Originaldateien, ohne die Adresse preiszugeben.
if ($galerie !== null && ($_GET['dl'] ?? '') === '1') {
    $ziel = (string)($galerie['transfer'] ?? '');
    if ($ziel !== '' && preg_match('~^https://~i', $ziel)) {
        header('Location: ' . $ziel, true, 302);
        exit;
    }
    http_response_code(404);
    $galerie = null;
    $titel = 'Nicht gefunden';
}

$bilder = $galerie['images'] ?? [];
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= h($titel) ?> — Louis Reinecke</title>
<style>
  :root {
    --grund: #ffffff;
    --schrift: #000000;
    --leise: #8a8a8a;
    --akzent: #ff3b00;
  }
  * { box-sizing: border-box; }
  body {
    margin: 0;
    background: var(--grund);
    color: var(--schrift);
    font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text", "Inter",
      "Helvetica Neue", Helvetica, Arial, sans-serif;
    font-size: 15px;
    letter-spacing: -0.01em;
    -webkit-font-smoothing: antialiased;
    overscroll-behavior-y: none;
  }

  /* ---------- Kopf ---------- */

  .kopf {
    position: fixed; inset: 0 0 auto 0; z-index: 30;
    display: flex; align-items: baseline; justify-content: space-between;
    gap: 16px; padding: 18px 22px;
    background: linear-gradient(var(--grund) 60%, rgba(255,255,255,0));
    pointer-events: none;
  }
  .kopf h1 {
    margin: 0; font-size: 15px; font-weight: 700;
    text-transform: uppercase; letter-spacing: -0.01em;
  }
  .kopf span {
    font-size: 11px; font-weight: 500; letter-spacing: .08em;
    text-transform: uppercase; color: var(--leise);
  }

  /* ---------- Das Rad ----------

     Alle Bilder sitzen auf einem Kreis. Beim Scrollen dreht sich der Kreis;
     welches Bild oben steht, wird gross und deckend, die uebrigen treten
     zurueck. Die Scrollhoehe legt fest, wie weit sich das Rad dreht. */

  .strecke { height: 100vh; }

  .rad {
    position: fixed; inset: 0; z-index: 10;
    overflow: hidden;
  }

  .foto {
    position: absolute; top: 0; left: 0;
    width: var(--breite, 230px);
    margin: 0;
    border-radius: 3px;
    overflow: hidden;
    background: #f0efee;
    cursor: pointer;
    will-change: transform, opacity;
    box-shadow: 0 10px 34px rgba(0,0,0,.14);
  }
  .foto img {
    display: block; width: 100%; aspect-ratio: 3 / 2;
    object-fit: cover;
    -webkit-user-drag: none; user-select: none; pointer-events: none;
  }

  /* ---------- Hinweis und Zähler ---------- */

  .wink {
    position: fixed; left: 50%; bottom: 96px; z-index: 25;
    transform: translateX(-50%);
    font-size: 11px; font-weight: 500; letter-spacing: .1em;
    text-transform: uppercase; color: var(--leise);
    transition: opacity .4s linear;
    pointer-events: none;
  }
  .wink.weg { opacity: 0; }

  .zaehler {
    position: fixed; right: 22px; bottom: 30px; z-index: 25;
    font-size: 11px; font-weight: 500; letter-spacing: .08em;
    color: var(--leise); font-variant-numeric: tabular-nums;
    pointer-events: none;
  }

  /* ---------- Download ---------- */

  .holen {
    position: fixed; left: 50%; bottom: 26px; z-index: 30;
    transform: translateX(-50%);
    display: inline-flex; align-items: center;
    padding: 14px 28px;
    background: var(--akzent); color: #fff;
    font: inherit; font-weight: 700; font-size: 13px;
    text-transform: uppercase; letter-spacing: .06em;
    text-decoration: none; border: 0; border-radius: 0;
    cursor: pointer;
  }
  .holen:hover { background: var(--schrift); }
  .holen:focus-visible { outline: 3px solid var(--akzent); outline-offset: 3px; }

  /* ---------- Vollbild ---------- */

  .voll {
    position: fixed; inset: 0; z-index: 60;
    display: none; place-items: center;
    background: var(--grund);
    padding: 64px 22px;
  }
  .voll.an { display: grid; }
  .voll img {
    max-width: 100%; max-height: calc(100vh - 128px);
    object-fit: contain;
    -webkit-user-drag: none; user-select: none;
  }
  .voll__zu, .voll__vor, .voll__zurueck {
    position: absolute; background: none; border: 0; cursor: pointer;
    color: var(--schrift); font: inherit; font-size: 26px; line-height: 1;
    padding: 14px 18px; opacity: .55;
  }
  .voll__zu:hover, .voll__vor:hover, .voll__zurueck:hover { opacity: 1; }
  .voll__zu { top: 10px; right: 12px; }
  .voll__zurueck { left: 4px; top: 50%; transform: translateY(-50%); }
  .voll__vor { right: 4px; top: 50%; transform: translateY(-50%); }
  .voll__zahl {
    position: absolute; bottom: 20px; left: 50%; transform: translateX(-50%);
    font-size: 11px; letter-spacing: .1em; color: var(--leise);
    font-variant-numeric: tabular-nums;
  }

  .leer { display: grid; place-items: center; height: 100vh; text-align: center; padding: 22px; }
  .leer p { color: var(--leise); max-width: 40ch; }
</style>
</head>
<body>

<?php if ($galerie === null): ?>
  <div class="leer">
    <p>Diese Galerie gibt es nicht mehr oder der Link ist unvollständig.
       Melde dich gern bei mir, dann schicke ich dir einen neuen.</p>
  </div>

<?php elseif (!$bilder): ?>
  <div class="leer">
    <p>Hier sind noch keine Bilder hinterlegt.</p>
  </div>

<?php else: ?>
  <header class="kopf">
    <h1><?= h($titel) ?></h1>
    <span><?= count($bilder) ?> Aufnahmen<?= !empty($galerie['client']) ? ' · ' . h($galerie['client']) : '' ?></span>
  </header>

  <div class="rad" id="rad"></div>
  <div class="strecke" id="strecke"></div>

  <p class="wink" id="wink">Scrollen zum Drehen</p>
  <p class="zaehler" id="zaehler"></p>

  <?php if (!empty($galerie['transfer'])): ?>
    <a class="holen" href="galerie.php?k=<?= h($schluessel) ?>&amp;dl=1" target="_blank" rel="noopener">
      Originale herunterladen
    </a>
  <?php endif; ?>

  <div class="voll" id="voll" role="dialog" aria-modal="true" aria-label="Großansicht">
    <button class="voll__zu" id="vollZu" aria-label="Schließen">&times;</button>
    <button class="voll__zurueck" id="vollZurueck" aria-label="Vorheriges Bild">&#8249;</button>
    <img id="vollBild" alt="">
    <button class="voll__vor" id="vollVor" aria-label="Nächstes Bild">&#8250;</button>
    <span class="voll__zahl" id="vollZahl"></span>
  </div>

<script>
(() => {
  const BILDER = <?= json_encode(array_values($bilder), JSON_UNESCAPED_SLASHES) ?>;

  const rad     = document.getElementById('rad');
  const strecke = document.getElementById('strecke');
  const wink    = document.getElementById('wink');
  const zaehler = document.getElementById('zaehler');

  /* Ein voller Umlauf verteilt sich auf die Scrollstrecke, sodass jedes Bild
     einmal oben zu stehen kommt. Etwas Nachlauf am Ende, damit das letzte
     Bild nicht schon beim Anschlag vorbeigezogen ist. */
  strecke.style.height = ((BILDER.length * 0.6) + 1.2) * 100 + 'vh';

  const karten = BILDER.map((src, i) => {
    const el = document.createElement('figure');
    el.className = 'foto';
    el.dataset.nr = String(i);

    const img = document.createElement('img');
    img.src = src;
    img.alt = '';
    img.loading = i < 6 ? 'eager' : 'lazy';
    el.appendChild(img);
    rad.appendChild(el);
    return el;
  });

  // Masse des Rads an die Fenstergroesse anpassen.
  let mitteX = 0, mitteY = 0, radius = 0, breite = 0;

  const masse = () => {
    const b = window.innerWidth, h = window.innerHeight;
    breite = Math.max(150, Math.min(300, b * 0.24));
    // Der Mittelpunkt liegt unter dem Bildausschnitt, damit oben Platz für
    // das grosse Bild bleibt und die Kreisbahn nur als Bogen sichtbar ist.
    radius = Math.max(260, Math.min(b, h) * 0.62);
    mitteX = b / 2;
    mitteY = h * 0.52 + radius * 0.72;
    karten.forEach((el) => el.style.setProperty('--breite', breite + 'px'));
  };

  let obenNr = 0;

  const setzen = () => {
    const maximum = strecke.offsetHeight - window.innerHeight;
    const anteil  = maximum > 0 ? Math.min(1, Math.max(0, window.scrollY / maximum)) : 0;
    const drehung = anteil * 360;

    let bestNaehe = -1;
    let bestNr = 0;

    karten.forEach((el, i) => {
      // Winkel 0 heisst: dieses Bild steht oben.
      const grad = (i / karten.length) * 360 - drehung;
      const bogen = grad * Math.PI / 180;

      const x = mitteX + Math.sin(bogen) * radius;
      const y = mitteY - Math.cos(bogen) * radius;

      /* Der Winkelabstand zur Spitze entscheidet, nicht der Kosinus: bei
         vielen Bildern liegen die Nachbarn sonst so dicht, dass sie fast
         gleich gross erscheinen und sich gegenseitig verdecken. Die
         Glockenkurve laesst nur die Spitze gross und die Nachbarn zuegig
         zurueckfallen. */
      // Kuerzester Weg zur Spitze, also 0 bis 180 Grad.
      const roh = ((grad % 360) + 360) % 360;
      const abstand = Math.min(roh, 360 - roh);
      const naehe = Math.exp(-Math.pow(abstand / 26, 2));
      const groesse = 0.42 + naehe * 1.25;

      el.style.transform =
        `translate3d(${x.toFixed(1)}px, ${y.toFixed(1)}px, 0)` +
        `translate(-50%, -50%)` +
        `scale(${groesse.toFixed(3)})`;
      el.style.opacity = (0.22 + naehe * 0.78).toFixed(3);
      el.style.zIndex = String(Math.round(naehe * 100));

      if (naehe > bestNaehe) { bestNaehe = naehe; bestNr = i; }
    });

    obenNr = bestNr;
    zaehler.textContent = (bestNr + 1) + ' / ' + BILDER.length;
    wink.classList.toggle('weg', window.scrollY > 40);
  };

  let warte = false;
  const beiScroll = () => {
    if (warte) return;
    warte = true;
    requestAnimationFrame(() => { setzen(); warte = false; });
  };

  window.addEventListener('scroll', beiScroll, { passive: true });
  window.addEventListener('resize', () => { masse(); setzen(); });
  masse();
  setzen();

  /* ---------- Großansicht ---------- */

  const voll     = document.getElementById('voll');
  const vollBild = document.getElementById('vollBild');
  const vollZahl = document.getElementById('vollZahl');
  let aktuell = 0;

  const zeigen = (nr) => {
    aktuell = (nr + BILDER.length) % BILDER.length;
    vollBild.src = BILDER[aktuell];
    vollZahl.textContent = (aktuell + 1) + ' von ' + BILDER.length;
    voll.classList.add('an');
    document.body.style.overflow = 'hidden';
  };

  const schliessen = () => {
    voll.classList.remove('an');
    vollBild.removeAttribute('src');
    document.body.style.overflow = '';
  };

  rad.addEventListener('click', (e) => {
    const karte = e.target.closest('.foto');
    if (karte) zeigen(Number(karte.dataset.nr));
  });

  document.getElementById('vollZu').addEventListener('click', schliessen);
  document.getElementById('vollVor').addEventListener('click', () => zeigen(aktuell + 1));
  document.getElementById('vollZurueck').addEventListener('click', () => zeigen(aktuell - 1));
  voll.addEventListener('click', (e) => { if (e.target === voll) schliessen(); });

  document.addEventListener('keydown', (e) => {
    if (voll.classList.contains('an')) {
      if (e.key === 'Escape') schliessen();
      if (e.key === 'ArrowRight') zeigen(aktuell + 1);
      if (e.key === 'ArrowLeft') zeigen(aktuell - 1);
      return;
    }
    // Ohne Grossansicht öffnet die Eingabetaste das obere Bild.
    if (e.key === 'Enter') zeigen(obenNr);
  });
})();
</script>
<?php endif; ?>

</body>
</html>
