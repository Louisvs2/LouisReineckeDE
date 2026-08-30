/* Lädt data/projects.json und rendert die Seite. Kein Build, keine Abhängigkeiten. */

const esc = (s) =>
  String(s == null ? "" : s).replace(/[&<>"']/g, (c) => ({
    "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;"
  }[c]));

const qs = (k) => new URLSearchParams(location.search).get(k);

// Von /projekt.html aus ist die JSON eine Ebene höher erreichbar wie von /index.html:
// alle Seiten liegen flach im Root, daher reicht ein relativer Pfad.
async function loadData() {
  const res = await fetch("data/projects.json", { cache: "no-cache" });
  if (!res.ok) throw new Error("projects.json nicht gefunden (" + res.status + ")");
  return res.json();
}

function renderChrome(site) {
  document.querySelectorAll("[data-site-name]").forEach((el) => {
    el.textContent = site.name;
  });

  const foot = document.querySelector("[data-foot]");
  if (!foot) return;

  const links = (site.links || [])
    .map((l) => `<a href="${esc(l.url)}" target="_blank" rel="noopener">${esc(l.label)}</a>`)
    .join("");

  foot.innerHTML =
    `<span>${esc(site.name)}</span>` +
    `<span>${esc(site.location)}</span>` +
    `<span class="foot__spacer"></span>` +
    links +
    `<a href="mailto:${esc(site.email)}">${esc(site.email)}</a>` +
    `<a href="impressum.html">Impressum</a>`;
}

function renderHome(data) {
  const { site, projects } = data;

  const lede = document.querySelector("[data-lede]");
  if (lede) lede.textContent = site.intro;

  const index = document.querySelector("[data-index]");
  if (index) {
    index.innerHTML = projects
      .map(
        (p) => `
      <a class="row" href="projekt.html?p=${encodeURIComponent(p.slug)}">
        <span class="row__title">${esc(p.title)}</span>
        <span class="row__meta row__meta--client">${esc(p.client)}</span>
        <span class="row__meta row__meta--type">${esc(p.type)}</span>
        <span class="row__meta">${esc(p.year)}</span>
      </a>`
      )
      .join("");
  }

  const grid = document.querySelector("[data-grid]");
  if (grid) {
    grid.innerHTML = projects
      .map(
        (p) => `
      <a class="card" href="projekt.html?p=${encodeURIComponent(p.slug)}">
        <img class="card__img" src="${esc(p.cover)}" alt="${esc(p.title)}" loading="lazy" onerror="this.dataset.broken=1">
        <span class="card__label">${esc(p.title)} <span>— ${esc(p.type)}, ${esc(p.year)}</span></span>
      </a>`
      )
      .join("");
  }
}

/* Impressum. Die Angaben stehen in data/projects.json, damit sie an einer
   Stelle gepflegt werden. Leere Felder werden weggelassen. */
function renderImpressum(data) {
  const root = document.querySelector("[data-impressum]");
  if (!root) return;

  const i = data.impressum || {};
  const zeile = (t) => (t ? `<p>${esc(t)}</p>` : "");

  const anschrift = [i.firma, i.betreiber, i.strasse, i.ort]
    .filter(Boolean)
    .map((t) => esc(t))
    .join("<br>");

  const steuer = i.ustid
    ? `<h2>Umsatzsteuer</h2><p>Umsatzsteuer-Identifikationsnummer nach § 27 a Umsatzsteuergesetz:<br>${esc(i.ustid)}</p>`
    : i.kleinunternehmer
    ? `<h2>Umsatzsteuer</h2><p>Als Kleinunternehmer im Sinne von § 19 Umsatzsteuergesetz wird keine Umsatzsteuer berechnet.</p>`
    : "";

  document.title = "Impressum — " + data.site.name;

  root.innerHTML = `
    <h1 data-split>Impressum</h1>

    <h2>Angaben gemäß § 5 DDG</h2>
    <p>${anschrift}</p>

    <h2>Kontakt</h2>
    <p>E-Mail: <a href="mailto:${esc(i.email)}">${esc(i.email)}</a></p>
    ${zeile(i.telefon ? "Telefon: " + i.telefon : "")}

    ${steuer}

    <h2>Verantwortlich für den Inhalt</h2>
    <p>${esc(i.betreiber)}<br>Anschrift wie oben.</p>

    <h2>Urheberrecht</h2>
    <p>Alle Fotografien und Filme auf dieser Seite sind urheberrechtlich
    geschützt. Eine Verwendung, Vervielfältigung oder Bearbeitung außerhalb
    der Grenzen des Urheberrechts bedarf meiner schriftlichen Zustimmung.
    Die unter Software angebotenen Dateien dürfen frei genutzt werden.</p>

    <h2>Haftung für Links</h2>
    <p>Diese Seite verweist an einzelnen Stellen auf externe Angebote, auf
    deren Inhalte ich keinen Einfluss habe. Für diese Inhalte ist der
    jeweilige Anbieter verantwortlich.</p>

    <h2>Streitbeilegung</h2>
    <p>Ich bin nicht bereit und nicht verpflichtet, an
    Streitbeilegungsverfahren vor einer Verbraucherschlichtungsstelle
    teilzunehmen.</p>`;
}

/* Downloadliste. Die Dateigroesse wird beim Laden vom Server erfragt,
   damit sie nicht von Hand gepflegt werden muss. */
function renderDownloads(data) {
  const host = document.querySelector("[data-downloads]");
  if (!host) return;

  const items = data.downloads || [];
  if (!items.length) {
    host.innerHTML = `<p class="empty">Zurzeit stehen keine Dateien bereit.</p>`;
    return;
  }

  host.innerHTML = items
    .map(
      (d, i) => `
      <a class="row row--dl" href="${esc(d.file)}" download>
        <span class="row__title">${esc(d.name)}</span>
        <span class="row__meta row__meta--client">${esc(d.note || "")}</span>
        <span class="row__meta row__meta--type">${esc(d.kind || "")}</span>
        <span class="row__meta" data-size="${i}">Laden</span>
      </a>`
    )
    .join("");

  items.forEach((d, i) => {
    const cell = host.querySelector(`[data-size="${i}"]`);
    fetch(d.file, { method: "HEAD" })
      .then((res) => {
        const bytes = Number(res.headers.get("content-length"));
        if (!res.ok) throw new Error("fehlt");
        cell.textContent = bytes ? groesse(bytes) : "Laden";
      })
      .catch(() => {
        // Datei liegt noch nicht auf dem Server: Eintrag bleibt sichtbar,
        // aber ohne Downloadversprechen.
        cell.textContent = "Bald";
        cell.closest(".row").classList.add("row--soon");
      });
  });
}

function groesse(bytes) {
  if (bytes >= 1e9) return (bytes / 1e9).toFixed(1).replace(".", ",") + " GB";
  if (bytes >= 1e6) return Math.round(bytes / 1e6) + " MB";
  return Math.max(1, Math.round(bytes / 1e3)) + " KB";
}

/* Rotierender Ring neben dem Titel. Die Form entsteht aus einer Kreisbahn,
   deren Punkte jeweils eigene Abweichungen bekommen — dadurch ist der Ring
   nie exakt rund und sitzt bei jedem Aufruf anders. Aussen- und Innenkante
   haben getrennte Abweichungen, wodurch die Strichstaerke im Umlauf
   an- und abschwillt wie bei einem gemalten Strich. */
const RING = { umlauf: 4, unruhe: 0.05, punkte: 9, aussen: 80, innen: 68 };

function ringPfad(radius, unruhe, punkte) {
  const pts = [];
  for (let i = 0; i < punkte; i++) {
    const a = (i / punkte) * Math.PI * 2;
    const r = radius * (1 + (Math.random() - 0.5) * unruhe * 2);
    pts.push([100 + Math.cos(a) * r, 100 + Math.sin(a) * r]);
  }
  // Catmull-Rom in kubische Beziers, geschlossen.
  const n = pts.length;
  const at = (i) => pts[(i % n + n) % n];
  let d = `M${pts[0][0].toFixed(2)},${pts[0][1].toFixed(2)}`;
  for (let i = 0; i < n; i++) {
    const p0 = at(i - 1), p1 = at(i), p2 = at(i + 1), p3 = at(i + 2);
    const c1 = [p1[0] + (p2[0] - p0[0]) / 6, p1[1] + (p2[1] - p0[1]) / 6];
    const c2 = [p2[0] - (p3[0] - p1[0]) / 6, p2[1] - (p3[1] - p1[1]) / 6];
    d += `C${c1[0].toFixed(2)},${c1[1].toFixed(2)} ${c2[0].toFixed(2)},${c2[1].toFixed(2)} ${p2[0].toFixed(2)},${p2[1].toFixed(2)}`;
  }
  return d + "Z";
}

function initRing() {
  const host = document.querySelector("[data-ring]");
  if (!host) return;

  const aussen = ringPfad(RING.aussen, RING.unruhe, RING.punkte);
  const innen = ringPfad(RING.innen, RING.unruhe * 1.3, RING.punkte);

  host.innerHTML =
    `<svg viewBox="0 0 200 200" aria-hidden="true">` +
    `<g class="ring__spin" style="--umlauf:${RING.umlauf}s">` +
    `<path d="${aussen} ${innen}" fill="currentColor" fill-rule="evenodd"></path>` +
    `</g></svg>`;
}

/* Bildvorschau, die beim Überfahren einer Projektzeile dem Cursor folgt.
   Sie erscheint nur, wenn das Coverbild wirklich geladen werden konnte —
   sonst haette man eine leere Flaeche im Bild. */
function initPeek(projects) {
  const rows = document.querySelectorAll("[data-index] .row");
  if (!rows.length || !window.matchMedia("(hover: hover)").matches) return;

  const peek = document.createElement("img");
  peek.className = "peek";
  peek.alt = "";
  document.body.appendChild(peek);

  let x = 0, y = 0, frame = null;
  const place = () => {
    frame = null;
    peek.style.transform = `translate3d(${x}px, ${y}px, 0) translate(-50%, -50%)`;
  };

  const hide = () => {
    peek.classList.remove("is-on");
    if (frame) { cancelAnimationFrame(frame); frame = null; }
  };

  rows.forEach((row, i) => {
    const cover = projects[i] && projects[i].cover;
    if (!cover) return;

    // Vorab laden, damit beim Hover nichts blinkt und fehlende Bilder auffallen.
    const probe = new Image();
    let ready = false;
    probe.onload = () => { ready = true; };
    probe.src = cover;

    row.addEventListener("mousemove", (e) => {
      x = e.clientX;
      y = e.clientY;
      if (!frame) frame = requestAnimationFrame(place);
      if (ready && !peek.classList.contains("is-on")) {
        peek.src = cover;
        peek.classList.add("is-on");
      }
    });
    row.addEventListener("mouseleave", hide);
  });

  // Sicherheitsnetz: verlaesst der Zeiger das Fenster oder wird gescrollt,
  // darf die Vorschau nicht stehen bleiben.
  window.addEventListener("scroll", hide, { passive: true });
  document.addEventListener("mouseleave", hide);
}

/* Jeder Buchstabe bekommt eigene Werte, damit die Bewegung nicht als
   gleichmaessige Welle lesbar wird. Die Stufenzahl gibt den Ruckel-Look:
   wenige Stufen wirken wie eine niedrige Bildrate. */
function styleLetter(span, i) {
  const rnd = (min, max) => min + Math.random() * (max - min);

  const steps = Math.round(rnd(3, 8));
  const dur = rnd(1.8, 5.4);

  // Negativer Versatz: die Buchstaben stehen von Anfang an verteilt,
  // statt gemeinsam loszulaufen.
  const delay = -rnd(0, dur) - i * 0.04;

  span.style.setProperty("--w-min", Math.round(rnd(100, 300) / 100) * 100);
  span.style.setProperty("--w-max", Math.round(rnd(600, 900) / 100) * 100);
  span.style.setProperty("--s-min", rnd(0.86, 0.97).toFixed(3));
  span.style.setProperty("--s-max", rnd(1.03, 1.14).toFixed(3));

  span.style.animationDuration = dur.toFixed(2) + "s";
  span.style.animationDelay = delay.toFixed(2) + "s";
  span.style.animationTimingFunction = `steps(${steps}, end)`;
}

/* Zerlegt die Titelzeile in einzelne Buchstaben, damit jeder fuer sich
   in Gewicht und Groesse atmen kann. <br> bleibt als Zeilenumbruch erhalten. */
function initTitle() {
  document.querySelectorAll("[data-split]").forEach(splitTitle);
}

function splitTitle(el) {
  // Ein bereits zerlegter Titel darf nicht erneut aufgeteilt werden.
  if (el.dataset.split === "done") return;
  el.dataset.split = "done";

  const nodes = Array.from(el.childNodes);
  el.textContent = "";
  let i = 0;

  nodes.forEach((node) => {
    if (node.nodeName === "BR") {
      el.appendChild(document.createElement("br"));
      return;
    }
    for (const ch of node.textContent) {
      if (ch.trim() === "") {
        el.appendChild(document.createTextNode(" "));
        continue;
      }
      const span = document.createElement("span");
      span.className = "ltr";
      span.textContent = ch;
      styleLetter(span, i);
      el.appendChild(span);
      i++;
    }
  });
}

// Ein Feld nur zeigen, wenn es gefuellt ist.
const spec = (label, value) =>
  value ? `<div><dt>${esc(label)}</dt><dd>${esc(value)}</dd></div>` : "";

function renderProject(data) {
  const { projects } = data;
  const slug = qs("p");
  const i = projects.findIndex((p) => p.slug === slug);
  const root = document.querySelector("[data-project]");
  if (!root) return;

  if (i === -1) {
    root.innerHTML = `<p class="empty">Projekt nicht gefunden. <a href="index.html">Zur Übersicht</a></p>`;
    return;
  }

  const p = projects[i];
  document.title = p.title + " — " + data.site.name;

  const embed = p.video
    ? `<div class="embed"><iframe src="${esc(p.video)}" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen title="${esc(p.title)}"></iframe></div>`
    : "";

  const images = (p.images || [])
    .map((src) => `<img src="${esc(src)}" alt="${esc(p.title)}" loading="lazy" onerror="this.remove()">`)
    .join("");

  const tags = (p.tags || []).join(", ");

  const prev = projects[(i - 1 + projects.length) % projects.length];
  const next = projects[(i + 1) % projects.length];

  root.innerHTML = `
    <header class="project">
      <h1 data-split>${esc(p.title)}</h1>
      ${p.excerpt ? `<p class="project__lede">${esc(p.excerpt)}</p>` : ""}
      <dl class="specs">${spec("Kunde", p.client)}${spec("Jahr", p.year)}${spec("Leistung", p.type)}${spec("Stichworte", tags)}</dl>
      ${p.text ? `<p class="project__lede" style="margin-bottom:40px">${esc(p.text)}</p>` : ""}
    </header>
    <div class="media">${embed}${images}</div>
    <nav class="pager">
      <a href="projekt.html?p=${encodeURIComponent(prev.slug)}">&larr; ${esc(prev.title)}</a>
      <a href="index.html">Alle Projekte</a>
      <a href="projekt.html?p=${encodeURIComponent(next.slug)}">${esc(next.title)} &rarr;</a>
    </nav>`;
}

function renderInfo(data) {
  const { site } = data;
  const root = document.querySelector("[data-info]");
  if (!root) return;

  const links = (site.links || [])
    .map((l) => `<a href="${esc(l.url)}" target="_blank" rel="noopener">${esc(l.label)}</a>`)
    .join(" · ");

  root.innerHTML = `
    <h1 data-split>${esc(site.name)}</h1>
    <p>${esc(site.intro)}</p>
    <p>${esc(site.role)} — ${esc(site.location)}</p>
    <p>Anfragen: <a href="mailto:${esc(site.email)}">${esc(site.email)}</a></p>
    <p>${links}</p>`;
}

loadData()
  .then((data) => {
    renderChrome(data.site);
    initRing();
    renderHome(data);
    renderProject(data);
    renderInfo(data);
    renderDownloads(data);
    renderImpressum(data);
    // Zuletzt: die Titel der Unterseiten entstehen erst beim Rendern.
    initTitle();
    initPeek(data.projects);
  })
  .catch((err) => {
    const main = document.querySelector("main");
    if (main) main.innerHTML = `<p class="empty">${esc(err.message)}</p>`;
    console.error(err);
  });
