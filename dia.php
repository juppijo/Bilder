<?php
/*
  dia.php  –  Zenjio Photo Gallery, Version 3.0
  ------------------------------------------------
  Ersetzt dia.php, diaf.php und diaff.php in EINER Datei.

  Einfach in den Bilderordner legen und aufrufen.
  Bilder in Unterverzeichnissen werden automatisch mit angezeigt.

  URL-Parameter (alle optional, alte Links funktionieren weiter):
    ?pic=5          Startbild (0 = erstes Bild)
    ?sstop=stop     Diashow angehalten starten (Standard: go = läuft)
    ?delay=5        Sekunden pro Bild (1–60, Standard 3)
    ?fit=fit|fill|frame   Bildanpassung (diaff = fit, diaf = frame)
    ?theme=dunkelblau|gold|nacht|smaragd
    ?shuffle=1      Zufällige Reihenfolge

  Tasten:  ← →  Bild wechseln     Leertaste  Start/Stopp
           F  Vollbild            G  Übersicht      T  Vorschaustreifen
           B  Bildanpassung       S  Zufall         C  Stil
           + / –  Tempo           Pos1 / Ende  erstes / letztes Bild
*/
declare(strict_types=1);

$TITLE      = 'Zenjio Photo Gallery';
$EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'avif'];

/* ---------- Bilder einsammeln ---------- */
$dir    = __DIR__;
$images = [];
try {
    $it = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            function (SplFileInfo $cur): bool {
                return $cur->getFilename()[0] !== '.';   // versteckte Dateien/Ordner auslassen
            }
        ),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    $it->setMaxDepth(12);
    foreach ($it as $fi) {
        if (!$fi->isFile()) {
            continue;
        }
        $ext = strtolower($fi->getExtension());
        if (in_array($ext, $EXTENSIONS, true)) {
            $images[] = str_replace('\\', '/', substr($fi->getPathname(), strlen($dir) + 1));
        }
    }
} catch (Throwable $e) {
    // unlesbare Ordner werden übersprungen
}
natcasesort($images);
$images = array_values($images);
$count  = count($images);

/* ---------- Parameter prüfen ---------- */
$pic = filter_input(INPUT_GET, 'pic', FILTER_VALIDATE_INT);
$pic = ($pic === false || $pic === null) ? 0 : $pic;
if ($count > 0) {
    $pic = (($pic % $count) + $count) % $count;
} else {
    $pic = 0;
}

$autoplay = (($_GET['sstop'] ?? 'go') !== 'stop');

$delay = filter_input(INPUT_GET, 'delay', FILTER_VALIDATE_INT);
$delay = ($delay === false || $delay === null) ? 3 : max(1, min(60, $delay));

$fit = $_GET['fit'] ?? '';
$fit = in_array($fit, ['fit', 'fill', 'frame'], true) ? $fit : '';

$theme = $_GET['theme'] ?? '';
$theme = in_array($theme, ['dunkelblau', 'gold', 'nacht', 'smaragd'], true) ? $theme : '';

$shuffle = (($_GET['shuffle'] ?? '0') === '1');

/* ---------- Daten für JavaScript ---------- */
$list = [];
foreach ($images as $f) {
    $list[] = ['src' => implode('/', array_map('rawurlencode', explode('/', $f))), 'name' => $f];
}
$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
$config = [
    'images'   => $list,
    'start'    => $pic,
    'autoplay' => $autoplay,
    'delay'    => $delay,
    'fit'      => $fit,
    'theme'    => $theme,
    'shuffle'  => $shuffle,
];

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

/* ---------- Fallback ohne JavaScript ---------- */
$self = basename(__FILE__);
$noJsHead = '';
$noJsBody = '';
if ($count > 0) {
    $prev = ($pic - 1 + $count) % $count;
    $next = ($pic + 1) % $count;
    $stopQ = $autoplay ? '' : '&sstop=stop';
    if ($autoplay) {
        $noJsHead = '<noscript><meta http-equiv="refresh" content="' . $delay . '; URL=' . h($self) . '?pic=' . $next . '&delay=' . $delay . '"></noscript>';
    }
    $noJsBody = '<noscript><div class="nojs">'
        . '<a href="' . h($self) . '?pic=' . $prev . $stopQ . '">‹ Zurück</a> '
        . '<img src="' . h($list[$pic]['src']) . '" alt="' . h($list[$pic]['name']) . '"> '
        . '<a href="' . h($self) . '?pic=' . $next . $stopQ . '">Weiter ›</a></div></noscript>';
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= h($TITLE) ?></title>
<?= $noJsHead ?>

<style>
/* ===== Stile (Themen) ===== */
:root {
  --bg1: #0f2350; --bg2: #020611;
  --panel: rgba(6, 16, 42, .74);
  --line: rgba(110, 160, 255, .30);
  --text: #e6eeff; --muted: #94a9d6;
  --accent: #4d8dff; --accent-ink: #030b1f;
  --ease: cubic-bezier(.2, .7, .2, 1);
}
[data-theme="gold"] {
  --bg1: #1a1408; --bg2: #050403;
  --panel: rgba(18, 14, 6, .72);
  --line: rgba(230, 190, 90, .28);
  --text: #f3ead3; --muted: #b8a67a;
  --accent: #e3b341; --accent-ink: #1a1408;
}
[data-theme="nacht"] {
  --bg1: #1d1038; --bg2: #07030f;
  --panel: rgba(20, 10, 40, .72);
  --line: rgba(170, 140, 255, .30);
  --text: #ece6ff; --muted: #a79bd1;
  --accent: #9b7bff; --accent-ink: #0e0620;
}
[data-theme="smaragd"] {
  --bg1: #06241a; --bg2: #010806;
  --panel: rgba(4, 22, 16, .72);
  --line: rgba(80, 220, 160, .28);
  --text: #e2f6ec; --muted: #8fbfa8;
  --accent: #2fd08c; --accent-ink: #03150e;
}

* { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
html, body { height: 100%; margin: 0; }
body {
  background: radial-gradient(ellipse at 50% 35%, var(--bg1), var(--bg2) 75%);
  color: var(--text);
  font: 15px/1.4 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
  overflow: hidden;
  user-select: none;
  -webkit-user-select: none;
}

/* ===== Bühne ===== */
#stage { position: fixed; inset: 0; display: grid; place-items: center; }
.layer {
  position: absolute; inset: 0; width: 100%; height: 100%;
  object-fit: contain;
  opacity: 0;
  transition: opacity .7s var(--ease);
  pointer-events: none;
}
.layer.on { opacity: 1; }
#stage[data-fit="fill"]  .layer { object-fit: cover; }
#stage[data-fit="frame"] .layer {
  inset: 7vh 8vw; width: calc(100% - 16vw); height: calc(100% - 14vh);
  box-shadow: 0 20px 80px rgba(0, 0, 0, .65), 0 0 0 1px var(--line);
  border-radius: 4px;
}
@media (orientation: portrait) {
  #stage[data-fit="frame"] .layer { inset: 12vh 3vw; width: calc(100% - 6vw); height: calc(100% - 24vh); }
}

/* ===== Bedienleisten ===== */
.bar {
  position: fixed; left: 0; right: 0; z-index: 5;
  display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: 6px;
  padding: 10px 12px;
  background: var(--panel);
  backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
  transition: transform .35s var(--ease), opacity .35s var(--ease);
}
#top { top: 0; border-bottom: 1px solid var(--line); padding-top: max(10px, env(safe-area-inset-top)); }
#bottom { bottom: 0; border-top: 1px solid var(--line); flex-direction: column; padding-bottom: max(10px, env(safe-area-inset-bottom)); }
body.idle #top    { transform: translateY(-100%); opacity: 0; }
body.idle #bottom { transform: translateY(100%);  opacity: 0; }
body.idle { cursor: none; }

button, .num {
  font: inherit; color: var(--text);
  background: rgba(255, 255, 255, .06);
  border: 1px solid var(--line);
  border-radius: 10px;
  padding: 7px 12px; min-height: 36px;
  cursor: pointer;
  transition: background .15s, border-color .15s, color .15s;
}
button:hover { background: rgba(255, 255, 255, .13); border-color: var(--accent); }
button:active { transform: translateY(1px); }
button.primary { background: var(--accent); border-color: var(--accent); color: var(--accent-ink); font-weight: 600; }
button.primary:hover { filter: brightness(1.1); }
button.active { border-color: var(--accent); color: var(--accent); }
button:focus-visible, input:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
.num { width: 64px; text-align: center; padding: 7px 6px; cursor: text; user-select: text; -webkit-user-select: text; }
.sep { width: 1px; height: 22px; background: var(--line); margin: 0 4px; }
#info { display: flex; gap: 10px; align-items: baseline; justify-content: center; width: 100%; font-size: 13px; color: var(--muted); }
#info b { color: var(--text); font-weight: 600; max-width: 55vw; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

/* Fortschritt */
#progress { position: fixed; left: 0; top: 0; height: 3px; width: 0; background: var(--accent); z-index: 9; }

/* Seitenpfeile */
.arrow {
  position: fixed; top: 50%; transform: translateY(-50%); z-index: 4;
  width: 52px; height: 84px; font-size: 28px; padding: 0;
  background: var(--panel); border-radius: 12px;
  transition: opacity .35s var(--ease);
}
#prev { left: 10px; } #next { right: 10px; }
body.idle .arrow { opacity: 0; pointer-events: none; }
@media (max-width: 600px) { .arrow { width: 40px; height: 64px; font-size: 22px; } }

/* Vorschaustreifen */
#strip {
  display: none; width: 100%; gap: 6px; overflow-x: auto; padding: 4px 2px 2px;
  scrollbar-width: thin; scrollbar-color: var(--accent) transparent;
}
#strip.show { display: flex; }
.th {
  flex: 0 0 auto; width: 84px; height: 58px; border-radius: 6px; overflow: hidden;
  border: 2px solid transparent; opacity: .55; cursor: pointer; padding: 0; background: #000;
  transition: opacity .2s, border-color .2s;
}
.th img { width: 100%; height: 100%; object-fit: cover; display: block; }
.th:hover { opacity: 1; }
.th.cur { opacity: 1; border-color: var(--accent); }

/* Übersicht */
#grid {
  position: fixed; inset: 0; z-index: 20; overflow-y: auto;
  background: color-mix(in srgb, var(--bg2) 94%, transparent);
  padding: 64px 16px 24px;
  display: none; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 10px; align-content: start;
}
#grid.show { display: grid; }
#grid .th { position: relative; width: 100%; height: 0; min-height: 0; padding: 0 0 75%; opacity: .85; }
#grid .th img { position: absolute; inset: 0; width: 100%; height: 100%; }
#gridclose { position: fixed; top: 12px; right: 14px; z-index: 21; display: none; }
#gridclose.show { display: block; }

/* Leer / Hinweis */
#empty { position: fixed; inset: 0; display: grid; place-content: center; text-align: center; gap: 6px; padding: 24px; }
#empty h1 { margin: 0; font-size: 22px; }
#empty p { margin: 0; color: var(--muted); }
#toast {
  position: fixed; left: 50%; bottom: 120px; transform: translate(-50%, 10px);
  background: var(--panel); border: 1px solid var(--line); padding: 8px 16px; border-radius: 999px;
  opacity: 0; transition: opacity .25s, transform .25s; z-index: 30; pointer-events: none;
}
#toast.show { opacity: 1; transform: translate(-50%, 0); }
.nojs { text-align: center; padding: 20px; position: relative; z-index: 50; }
.nojs img { max-width: 100%; max-height: 80vh; display: block; margin: 10px auto; }
.nojs a { color: var(--accent); }

@media (prefers-reduced-motion: reduce) {
  .layer, .bar, .arrow, #toast { transition: none; }
}
:fullscreen body, :fullscreen { background: var(--bg2); }
</style>
</head>
<body>
<?= $noJsBody ?>

<?php if ($count === 0): ?>
  <div id="empty">
    <h1>Keine Bilder gefunden</h1>
    <p>Lege Fotos (<?= h(implode(', ', $EXTENSIONS)) ?>) in denselben Ordner wie <?= h($self) ?>.</p>
  </div>
<?php else: ?>

<div id="progress"></div>
<div id="stage"><img class="layer" id="l0" alt=""><img class="layer" id="l1" alt=""></div>

<div class="bar" id="top">
  <button id="bFs" title="Vollbild (F)">⛶ Vollbild</button>
  <button id="bTheme" title="Stil wechseln (C)">◐ Stil</button>
  <button id="bFit" title="Bildanpassung (B)">▭ Einpassen</button>
  <span class="sep"></span>
  <button id="bGrid" title="Übersicht (G)">▦ Übersicht</button>
  <button id="bThumbs" title="Vorschaustreifen (T)">▤ Streifen</button>
  <button id="bShuffle" title="Zufall (S)">⤮ Zufall</button>
</div>

<button class="arrow" id="prev" aria-label="Voriges Bild">‹</button>
<button class="arrow" id="next" aria-label="Nächstes Bild">›</button>

<div class="bar" id="bottom">
  <div id="strip"></div>
  <div id="info"><b id="name"></b><span id="count"></span><span id="mode"></span></div>
  <div style="display:flex;flex-wrap:wrap;gap:6px;justify-content:center;align-items:center">
    <button id="bFirst" title="Erstes Bild (Pos1)">⏮</button>
    <button id="bPrev" title="Zurück (←)">◀</button>
    <button id="bPlay" class="primary" title="Start/Stopp (Leertaste)">⏸ Stopp</button>
    <button id="bNext" title="Weiter (→)">▶</button>
    <button id="bLast" title="Letztes Bild (Ende)">⏭</button>
    <span class="sep"></span>
    <button id="bSlow" title="Langsamer (–)">−</button>
    <span id="delayLbl" style="min-width:44px;text-align:center"></span>
    <button id="bFast" title="Schneller (+)">+</button>
    <span class="sep"></span>
    <input class="num" id="goto" type="number" min="1" inputmode="numeric" aria-label="Zu Bild springen">
    <button id="bGo">Gehe zu</button>
  </div>
</div>

<div id="gridclose"><button id="bGridClose">✕ Schließen</button></div>
<div id="grid"></div>
<div id="toast"></div>

<script>
(() => {
  'use strict';
  const CFG = <?= json_encode($config, $jsonFlags) ?>;
  const IMG = CFG.images, N = IMG.length;
  const $ = id => document.getElementById(id);

  /* ----- Zustand (Einstellungen werden im Browser gemerkt) ----- */
  const store = {
    get(k, d) { try { const v = localStorage.getItem('zenjio.' + k); return v === null ? d : v; } catch (e) { return d; } },
    set(k, v) { try { localStorage.setItem('zenjio.' + k, v); } catch (e) {} }
  };
  const THEMES = ['dunkelblau', 'gold', 'nacht', 'smaragd'];
  const THEME_NAMES = { dunkelblau: 'Dunkelblau', gold: 'Dunkelgold', nacht: 'Nacht', smaragd: 'Smaragd' };
  const FITS = ['fit', 'fill', 'frame'];
  const FIT_NAMES = { fit: '▭ Einpassen', fill: '⬚ Füllen', frame: '▣ Rahmen' };

  let idx = Math.min(Math.max(CFG.start, 0), N - 1);
  let playing = CFG.autoplay;
  let delay = CFG.delay;
  let shuffle = CFG.shuffle || store.get('shuffle', '0') === '1';
  let theme = CFG.theme || store.get('theme', 'dunkelblau');
  let fit = CFG.fit || store.get('fit', 'fit');
  let cur = 0, token = 0, timer = 0, idleTimer = 0;
  const layers = [$('l0'), $('l1')];

  /* ----- Anzeige ----- */
  function toast(msg) {
    const t = $('toast'); t.textContent = msg; t.classList.add('show');
    clearTimeout(toast.t); toast.t = setTimeout(() => t.classList.remove('show'), 1400);
  }
  function applyTheme() {
    document.documentElement.setAttribute('data-theme', theme);
    store.set('theme', theme);
  }
  function applyFit() {
    $('stage').dataset.fit = fit; store.set('fit', fit);
    $('bFit').textContent = FIT_NAMES[fit];
  }
  function updatePlayUI() {
    $('bPlay').textContent = playing ? '⏸ Stopp' : '▶ Start';
    $('mode').textContent = playing ? '· Diashow läuft' : '· angehalten';
    $('delayLbl').textContent = delay + ' s';
    $('bShuffle').classList.toggle('active', shuffle);
  }
  function syncUrl() {
    const p = new URLSearchParams();
    p.set('pic', idx);
    if (!playing) p.set('sstop', 'stop');
    if (delay !== 3) p.set('delay', delay);
    try { history.replaceState(null, '', '?' + p.toString()); } catch (e) {}
  }
  function updateInfo() {
    $('name').textContent = IMG[idx].name;
    $('count').textContent = (idx + 1) + ' / ' + N;
    $('goto').value = idx + 1;
    document.title = IMG[idx].name + ' – Zenjio Photo Gallery';
    document.querySelectorAll('.th.cur').forEach(e => e.classList.remove('cur'));
    document.querySelectorAll('.th[data-i="' + idx + '"]').forEach(e => e.classList.add('cur'));
    const c = document.querySelector('#strip .th.cur');
    if (c) c.scrollIntoView({ block: 'nearest', inline: 'center', behavior: 'smooth' });
  }

  /* ----- Fortschrittsbalken + Timer ----- */
  function restartTimer() {
    clearTimeout(timer);
    const bar = $('progress');
    bar.style.transition = 'none'; bar.style.width = '0';
    if (!playing || N < 2) return;
    void bar.offsetWidth;
    bar.style.transition = 'width ' + delay + 's linear'; bar.style.width = '100%';
    timer = setTimeout(() => step(1), delay * 1000);
  }

  /* ----- Bildwechsel (Überblendung, wartet bis das Bild geladen ist) ----- */
  function show(i) {
    idx = ((i % N) + N) % N;
    const mine = ++token;
    const probe = new Image();
    const done = () => {
      if (mine !== token) return;
      const nxt = layers[1 - cur], prv = layers[cur];
      nxt.src = probe.src; nxt.alt = IMG[idx].name;
      nxt.classList.add('on'); prv.classList.remove('on');
      cur = 1 - cur;
      updateInfo(); syncUrl(); preload(); restartTimer();
    };
    clearTimeout(timer);
    probe.onload = done; probe.onerror = done;
    probe.src = IMG[idx].src;
  }
  function preload() {
    [1, 2].forEach(d => { const p = new Image(); p.src = IMG[(idx + d) % N].src; });
  }
  function step(dir) {
    if (shuffle && dir > 0 && N > 2) {
      let r; do { r = Math.floor(Math.random() * N); } while (r === idx);
      show(r);
    } else show(idx + dir);
  }
  function setPlaying(v) { playing = v; updatePlayUI(); syncUrl(); restartTimer(); toast(v ? 'Diashow läuft' : 'Diashow angehalten'); }
  function setDelay(d) { delay = Math.max(1, Math.min(60, d)); updatePlayUI(); syncUrl(); restartTimer(); toast('Tempo: ' + delay + ' s pro Bild'); }

  /* ----- Vollbild ----- */
  function toggleFs() {
    const d = document, e = d.documentElement;
    if (d.fullscreenElement || d.webkitFullscreenElement) {
      (d.exitFullscreen || d.webkitExitFullscreen).call(d);
    } else {
      const f = e.requestFullscreen || e.webkitRequestFullscreen;
      if (f) f.call(e); else toast('Vollbild wird hier nicht unterstützt');
    }
  }

  /* ----- Vorschaubilder (Streifen + Übersicht), erst bei Bedarf ----- */
  function makeThumb(i) {
    const b = document.createElement('button');
    b.className = 'th'; b.dataset.i = i; b.title = IMG[i].name;
    b.innerHTML = '<img loading="lazy" decoding="async" alt="">';
    b.firstChild.src = IMG[i].src;
    b.addEventListener('click', () => { closeGrid(); show(i); });
    return b;
  }
  function buildStrip() {
    const s = $('strip'); if (s.childElementCount) return;
    const f = document.createDocumentFragment();
    for (let i = 0; i < N; i++) f.appendChild(makeThumb(i));
    s.appendChild(f);
  }
  function buildGrid() {
    const g = $('grid'); if (g.childElementCount) return;
    const f = document.createDocumentFragment();
    for (let i = 0; i < N; i++) f.appendChild(makeThumb(i));
    g.appendChild(f);
  }
  function toggleStrip() {
    buildStrip();
    const s = $('strip'); s.classList.toggle('show');
    $('bThumbs').classList.toggle('active', s.classList.contains('show'));
    store.set('strip', s.classList.contains('show') ? '1' : '0');
    updateInfo();
  }
  function openGrid() { buildGrid(); $('grid').classList.add('show'); $('gridclose').classList.add('show'); updateInfo(); clearTimeout(timer); }
  function closeGrid() { $('grid').classList.remove('show'); $('gridclose').classList.remove('show'); restartTimer(); }
  const gridOpen = () => $('grid').classList.contains('show');

  /* ----- Bedienelemente automatisch ausblenden ----- */
  function wake() {
    document.body.classList.remove('idle');
    clearTimeout(idleTimer);
    idleTimer = setTimeout(() => {
      if (playing && !gridOpen() && !document.activeElement.matches('input')) document.body.classList.add('idle');
    }, 3000);
  }
  ['mousemove', 'mousedown', 'touchstart', 'keydown'].forEach(ev => addEventListener(ev, wake, { passive: true }));

  /* ----- Ereignisse ----- */
  $('bFs').onclick = toggleFs;
  $('bTheme').onclick = () => { theme = THEMES[(THEMES.indexOf(theme) + 1) % THEMES.length]; applyTheme(); toast('Stil: ' + THEME_NAMES[theme]); };
  $('bFit').onclick = () => { fit = FITS[(FITS.indexOf(fit) + 1) % FITS.length]; applyFit(); toast(FIT_NAMES[fit].slice(2)); };
  $('bGrid').onclick = openGrid;
  $('bGridClose').onclick = closeGrid;
  $('bThumbs').onclick = toggleStrip;
  $('bShuffle').onclick = () => { shuffle = !shuffle; store.set('shuffle', shuffle ? '1' : '0'); updatePlayUI(); toast(shuffle ? 'Zufällige Reihenfolge an' : 'Zufällige Reihenfolge aus'); };
  $('bPlay').onclick = () => setPlaying(!playing);
  $('prev').onclick = $('bPrev').onclick = () => show(idx - 1);
  $('next').onclick = $('bNext').onclick = () => step(1);
  $('bFirst').onclick = () => show(0);
  $('bLast').onclick = () => show(N - 1);
  $('bSlow').onclick = () => setDelay(delay + 1);
  $('bFast').onclick = () => setDelay(delay - 1);
  const goto = () => { const v = parseInt($('goto').value, 10); if (!isNaN(v)) show(Math.min(Math.max(v, 1), N) - 1); $('goto').blur(); };
  $('bGo').onclick = goto;
  $('goto').addEventListener('keydown', e => { if (e.key === 'Enter') goto(); e.stopPropagation(); });

  addEventListener('keydown', e => {
    if (e.ctrlKey || e.metaKey || e.altKey) return;
    const k = e.key;
    if (k === 'ArrowRight') { step(1); }
    else if (k === 'ArrowLeft') { show(idx - 1); }
    else if (k === ' ') { e.preventDefault(); setPlaying(!playing); }
    else if (k === 'Home') { show(0); }
    else if (k === 'End') { show(N - 1); }
    else if (k === 'Escape') { if (gridOpen()) closeGrid(); }
    else if (k === '+' || k === '=') { setDelay(delay - 1); }
    else if (k === '-') { setDelay(delay + 1); }
    else { switch (k.toLowerCase()) {
      case 'f': toggleFs(); break;
      case 'g': gridOpen() ? closeGrid() : openGrid(); break;
      case 't': toggleStrip(); break;
      case 'b': $('bFit').click(); break;
      case 's': $('bShuffle').click(); break;
      case 'c': $('bTheme').click(); break;
    } }
  });

  /* Doppelklick = Vollbild, Wischen = blättern */
  $('stage').addEventListener('dblclick', toggleFs);
  let tx = 0, ty = 0;
  addEventListener('touchstart', e => { tx = e.touches[0].clientX; ty = e.touches[0].clientY; }, { passive: true });
  addEventListener('touchend', e => {
    const dx = e.changedTouches[0].clientX - tx, dy = e.changedTouches[0].clientY - ty;
    if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy) * 1.5 && !gridOpen()) dx < 0 ? step(1) : show(idx - 1);
  }, { passive: true });
  /* Tab im Hintergrund: Timer pausieren */
  document.addEventListener('visibilitychange', () => { if (document.hidden) { clearTimeout(timer); } else restartTimer(); });

  /* ----- Start ----- */
  applyTheme(); applyFit(); updatePlayUI();
  if (store.get('strip', '0') === '1') toggleStrip();
  show(idx); wake();
})();
</script>
<?php endif; ?>
</body>
</html>
