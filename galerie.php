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
    --grund: #0d0d0f;
    --schrift: #ffffff;
    --leise: #8d8d92;
    --akzent: #ff3b00;
  }
  * { box-sizing: border-box; }
  html { scroll-behavior: auto; }
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
    pointer-events: none;
  }
  .kopf h1 {
    margin: 0; font-size: 15px; font-weight: 700;
    text-transform: uppercase; letter-spacing: -0.01em;
  }
  .kopf span { font-size: 11px; font-weight: 500; letter-spacing: .08em;
               text-transform: uppercase; color: var(--leise); }

  /* ---------- Die Bühne ---------- */

  /* Der Körper wird künstlich hoch gemacht; die Scrollhöhe steuert, wie weit
     die Kamera durch die Ringe fährt. */
  .strecke { height: 100vh; }

  .buehne {
    position: fixed; inset: 0; z-index: 10;
    perspective: 900px;
    overflow: hidden;
  }

  .raum {
    position: absolute; inset: 0;
    transform-style: preserve-3d;
    will-change: transform;
    /* Die Ebene selbst liegt vor den nach hinten versetzten Bildern und
       wuerde sonst alle Klicks abfangen. */
    pointer-events: none;
  }

  .foto {
    position: absolute; top: 50%; left: 50%;
    width: 200px; margin: -70px 0 0 -100px;
    border-radius: 12px;
    overflow: hidden;
    transform-style: preserve-3d;
    cursor: pointer;
    pointer-events: auto;
    background: #1a1a1e;
    box-shadow: 0 18px 50px rgba(0,0,0,.55);
  }
  .foto img {
    display: block; width: 100%; height: 140px;
    object-fit: cover;
    /* Bilder sollen sich nicht bequem einzeln wegziehen lassen. */
    -webkit-user-drag: none; user-select: none; pointer-events: none;
  }

  /* ---------- Hinweis zum Scrollen ---------- */

  .wink {
    position: fixed; left: 50%; bottom: 92px; z-index: 25;
    transform: translateX(-50%);
    font-size: 11px; font-weight: 500; letter-spacing: .1em;
    text-transform: uppercase; color: var(--leise);
    transition: opacity .4s linear;
    pointer-events: none;
  }
  .wink.weg { opacity: 0; }

  /* ---------- Download ---------- */

  .holen {
    position: fixed; left: 50%; bottom: 26px; z-index: 30;
    transform: translateX(-50%);
    display: inline-flex; align-items: center; gap: 10px;
    padding: 13px 26px;
    background: var(--akzent); color: #fff;
    font: inherit; font-weight: 700; font-size: 13px;
    text-transform: uppercase; letter-spacing: .06em;
    text-decoration: none; border: 0; border-radius: 999px;
    cursor: pointer;
    transition: transform .12s ease-out;
  }
  .holen:hover { transform: translateX(-50%) scale(1.04); }
  .holen:focus-visible { outline: 3px solid #fff; outline-offset: 3px; }

  /* ---------- Vollbild ---------- */

  .voll {
    position: fixed; inset: 0; z-index: 60;
    display: none; place-items: center;
    background: #08080a;
    padding: 64px 22px;
  }
  .voll.an { display: grid; }
  .voll img {
    max-width: 100%; max-height: calc(100vh - 128px);
    object-fit: contain; border-radius: 6px;
    -webkit-user-drag: none; user-select: none;
  }
  .voll__zu, .voll__vor, .voll__zurueck {
    position: absolute; background: none; border: 0; cursor: pointer;
    color: #fff; font: inherit; font-size: 26px; line-height: 1;
    padding: 14px 18px; opacity: .65;
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

  .leer { display: grid; place-items: center; height: 100vh; text-align: center; }
  .leer p { color: var(--leise); max-width: 40ch; }

  @media (prefers-reduced-motion: reduce) {
    .holen { transition: none; }
  }
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

  <div class="buehne" id="buehne">
    <div class="raum" id="raum"></div>
  </div>
  <div class="strecke" id="strecke"></div>

  <p class="wink" id="wink">Scrollen</p>

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

  const raum    = document.getElementById('raum');
  const strecke = document.getElementById('strecke');
  const wink    = document.getElementById('wink');

  /* Die Bilder sitzen auf mehreren Ringen, die hintereinander im Raum stehen.
     Beim Scrollen fährt die Kamera durch die Ringe hindurch: erst sieht man
     einen Ring als Ganzes, dann ist man mittendrin und die Bilder ziehen
     seitlich vorbei. */
  const PRO_RING  = 12;
  const ABSTAND   = 620;    // Tiefe zwischen zwei Ringen
  const RADIUS    = 300;    // Grundradius eines Rings
  const ringe     = Math.max(1, Math.ceil(BILDER.length / PRO_RING));

  // Aus dem Index abgeleitete Streuung: bei jedem Aufruf gleich, nie geordnet.
  const streu = (i, n) => {
    const x = Math.sin(i * 12.9898 + n * 78.233) * 43758.5453;
    return x - Math.floor(x);
  };

  const karten = BILDER.map((src, i) => {
    const ring   = Math.floor(i / PRO_RING);
    const platz  = i % PRO_RING;
    const anzahl = Math.min(PRO_RING, BILDER.length - ring * PRO_RING);

    // Jeder Ring startet etwas verdreht, damit keine Speichen entstehen.
    const winkel = (platz / anzahl) * 360 + ring * 17;
    const radius = RADIUS * (0.82 + streu(i, 1) * 0.42);
    const tiefe  = -ring * ABSTAND - streu(i, 2) * 160;
    const groesse = 0.72 + streu(i, 3) * 0.7;

    const el = document.createElement('figure');
    el.className = 'foto';
    el.style.margin = 0;
    el.dataset.nr = String(i);

    const img = document.createElement('img');
    img.src = src;
    img.alt = '';
    img.loading = i < PRO_RING * 2 ? 'eager' : 'lazy';
    el.appendChild(img);
    raum.appendChild(el);

    return { el, winkel, radius, tiefe, groesse };
  });

  // Scrollweg: pro Ring eine Bildschirmhöhe, plus etwas Vor- und Nachlauf.
  strecke.style.height = ((ringe + 1.4) * 100) + 'vh';

  const setzen = () => {
    const maximum = strecke.offsetHeight - window.innerHeight;
    const anteil  = maximum > 0 ? Math.min(1, Math.max(0, window.scrollY / maximum)) : 0;

    // Kamera startet vor dem ersten Ring und endet hinter dem letzten.
    const kamera = -ABSTAND * 0.75 + anteil * (ringe * ABSTAND + ABSTAND * 1.5);
    const drehung = anteil * 46;

    for (const k of karten) {
      const w = k.winkel + drehung;
      k.el.style.transform =
        `translate3d(-50%, -50%, 0)` +
        `translateZ(${(k.tiefe + kamera).toFixed(1)}px)` +
        `rotate(${w.toFixed(2)}deg)` +
        `translate(${k.radius.toFixed(1)}px)` +
        `rotate(${(-w).toFixed(2)}deg)` +
        `scale(${k.groesse.toFixed(3)})`;
    }

    wink.classList.toggle('weg', window.scrollY > 40);
  };

  let warte = false;
  const beiScroll = () => {
    if (warte) return;
    warte = true;
    requestAnimationFrame(() => { setzen(); warte = false; });
  };

  window.addEventListener('scroll', beiScroll, { passive: true });
  window.addEventListener('resize', setzen);
  setzen();

  /* ---------- Großansicht ---------- */

  const voll      = document.getElementById('voll');
  const vollBild  = document.getElementById('vollBild');
  const vollZahl  = document.getElementById('vollZahl');
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

  raum.addEventListener('click', (e) => {
    const karte = e.target.closest('.foto');
    if (karte) zeigen(Number(karte.dataset.nr));
  });

  document.getElementById('vollZu').addEventListener('click', schliessen);
  document.getElementById('vollVor').addEventListener('click', () => zeigen(aktuell + 1));
  document.getElementById('vollZurueck').addEventListener('click', () => zeigen(aktuell - 1));
  voll.addEventListener('click', (e) => { if (e.target === voll) schliessen(); });

  document.addEventListener('keydown', (e) => {
    if (!voll.classList.contains('an')) return;
    if (e.key === 'Escape') schliessen();
    if (e.key === 'ArrowRight') zeigen(aktuell + 1);
    if (e.key === 'ArrowLeft') zeigen(aktuell - 1);
  });
})();
</script>
<?php endif; ?>

</body>
</html>
