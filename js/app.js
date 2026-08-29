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
        <img class="card__img" src="${esc(p.cover)}" alt="${esc(p.title)}" loading="lazy">
        <span class="card__label">${esc(p.title)} <span>— ${esc(p.type)}, ${esc(p.year)}</span></span>
      </a>`
      )
      .join("");
  }
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
      <h1>${esc(p.title)}</h1>
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
    <h1>${esc(site.name)}</h1>
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
  })
  .catch((err) => {
    const main = document.querySelector("main");
    if (main) main.innerHTML = `<p class="empty">${esc(err.message)}</p>`;
    console.error(err);
  });
