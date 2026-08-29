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
    `<a href="mailto:${esc(site.email)}">${esc(site.email)}</a>`;
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
      <p class="project__lede">${esc(p.excerpt)}</p>
      <dl class="specs">
        <div><dt>Kunde</dt><dd>${esc(p.client)}</dd></div>
        <div><dt>Jahr</dt><dd>${esc(p.year)}</dd></div>
        <div><dt>Leistung</dt><dd>${esc(p.type)}</dd></div>
        <div><dt>Stichworte</dt><dd>${esc(tags)}</dd></div>
      </dl>
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
    renderHome(data);
    renderProject(data);
    renderInfo(data);
    // Zuletzt: die Titel der Unterseiten entstehen erst beim Rendern.
    initTitle();
    initPeek(data.projects);
  })
  .catch((err) => {
    const main = document.querySelector("main");
    if (main) main.innerHTML = `<p class="empty">${esc(err.message)}</p>`;
    console.error(err);
  });
