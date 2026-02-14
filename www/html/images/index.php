<?php
declare(strict_types=1);

$baseDir = __DIR__;
$exts = ['jpg','jpeg','png','webp','gif','svg'];

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function isHidden(string $name): bool { return $name === '.' || $name === '..' || str_starts_with($name, '.'); }

function listChapters(string $baseDir): array {
    $chapters = [];
    $items = @scandir($baseDir);
    if ($items === false) return $chapters;
    foreach ($items as $item) {
        if (isHidden($item)) continue;
        $full = $baseDir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($full)) $chapters[] = $item;
    }
    natcasesort($chapters);
    return array_values($chapters);
}

function listFiles(string $dirPath, array $exts): array {
    $out = [];
    $items = @scandir($dirPath);
    if ($items === false) return $out;

    foreach ($items as $item) {
        if (isHidden($item)) continue;
        $full = $dirPath . DIRECTORY_SEPARATOR . $item;
        if (!is_file($full)) continue;

        foreach ($exts as $ext) {
            if (preg_match('/\.' . preg_quote($ext, '/') . '$/i', $item)) { $out[] = $item; break; }
        }
    }
    natcasesort($out);
    return array_values($out);
}

function safeChapter(string $baseDir, ?string $chapter): array {
    if ($chapter === null || $chapter === '') return [null, null, null];
    if (str_contains($chapter, '/') || str_contains($chapter, '\\') || str_contains($chapter, '..')) {
        return [null, null, 'Invalid chapter name.'];
    }
    $candidate = $baseDir . DIRECTORY_SEPARATOR . $chapter;
    if (!is_dir($candidate)) return [null, null, 'Chapter not found.'];
    return [$chapter, $candidate, null];
}

$chapterParam = isset($_GET['chapter']) ? (string)$_GET['chapter'] : null;
[$chapterName, $chapterPath, $chapterErr] = safeChapter($baseDir, $chapterParam);

$chapters = listChapters($baseDir);
$files = ($chapterPath !== null) ? listFiles($chapterPath, $exts) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>OGKB - Image Library</title>

  <style>
    :root{
      --bg:#fafafa; --fg:#111; --muted:#444;
      --card:#fff; --border:#e6e6e6;
      --shadow: 0 2px 10px rgba(0,0,0,0.04);
      --shadowHover: 0 4px 16px rgba(0,0,0,0.06);
      --codebg:#f0f0f0;
      --link:#111;
      --danger:#b00020;
    }
    [data-theme="dark"]{
      --bg:#0f1115; --fg:#f2f4f7; --muted:#b7bec8;
      --card:#151924; --border:#2a3140;
      --shadow: 0 2px 12px rgba(0,0,0,0.35);
      --shadowHover: 0 4px 18px rgba(0,0,0,0.45);
      --codebg:#222a38;
      --link:#f2f4f7;
      --danger:#ff6b81;
    }
    body{ font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif; margin:0; background:var(--bg); color:var(--fg); }
    header{ padding:18px 18px 12px; border-bottom:1px solid var(--border); background:var(--card); }
    h1{ margin:0; font-size:22px; }
    .muted{ color:var(--muted); margin:6px 0 0; }
    main{ padding:16px 18px 24px; max-width:1100px; }
    .topbar{ display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap; }
    .pill{ display:inline-block; padding:6px 10px; border:1px solid var(--border); border-radius:999px; margin-right:8px; text-decoration:none; color:var(--link); background:var(--card); box-shadow:var(--shadow); }
    .btn{ border:1px solid var(--border); background:var(--card); color:var(--fg); border-radius:999px; padding:8px 12px; cursor:pointer; box-shadow:var(--shadow); }
    .card{ background:var(--card); border:1px solid var(--border); border-radius:14px; padding:14px 16px; box-shadow:var(--shadow); margin:14px 0; }
    .gridCh{ display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:12px; }
    .chap{ display:block; text-decoration:none; color:var(--link); border:1px solid var(--border); border-radius:14px; padding:14px 16px; background:var(--card); box-shadow:var(--shadow); }
    .chap:hover{ box-shadow:var(--shadowHover); }
    .chap .name{ font-weight:650; }
    .chap .meta{ margin-top:6px; color:var(--muted); font-size:12px; }
    code{ background:var(--codebg); padding:2px 6px; border-radius:8px; }
    .empty{ color:var(--muted); font-style:italic; }
    .error{ color:var(--danger); font-weight:600; }

    .toolbar{ display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-top:10px; }
    .search{
      border:1px solid var(--border); background:var(--card); color:var(--fg);
      border-radius:12px; padding:10px 12px; min-width: 240px; flex: 1;
      box-shadow:var(--shadow);
    }
    .gridImg{ display:grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap:10px; }
    .thumb{ display:block; border:1px solid var(--border); border-radius:12px; overflow:hidden; background:var(--card); box-shadow:var(--shadow); text-decoration:none; color:var(--link); }
    .thumb:hover{ box-shadow:var(--shadowHover); }
    .thumb img{ width:100%; height:140px; object-fit:cover; display:block; background:var(--bg); }

    /* ✅ filename handling for captions */
    .cap{
      padding:8px 10px;
      font-size:12px;
      color:var(--muted);
      word-break:break-word;
      overflow-wrap:anywhere;
      display:-webkit-box;
      -webkit-line-clamp: 2;      /* clamp to 2 lines */
      -webkit-box-orient: vertical;
      overflow:hidden;
    }
  </style>

  <script>
    (function(){
      const saved = localStorage.getItem("ogkb_theme");
      if(saved === "dark" || saved === "light"){
        document.documentElement.setAttribute("data-theme", saved);
      } else {
        const prefersDark = window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches;
        document.documentElement.setAttribute("data-theme", prefersDark ? "dark" : "light");
      }
    })();

    function toggleTheme(){
      const cur = document.documentElement.getAttribute("data-theme") || "light";
      const next = (cur === "dark") ? "light" : "dark";
      document.documentElement.setAttribute("data-theme", next);
      localStorage.setItem("ogkb_theme", next);
      const btn = document.getElementById("themeBtn");
      if(btn) btn.textContent = (next === "dark") ? "Light mode" : "Dark mode";
    }

    document.addEventListener("DOMContentLoaded", ()=>{
      const cur = document.documentElement.getAttribute("data-theme") || "light";
      const btn = document.getElementById("themeBtn");
      if(btn) btn.textContent = (cur === "dark") ? "Light mode" : "Dark mode";

      const search = document.getElementById("searchBox");
      const sortBtn = document.getElementById("sortBtn");

      if(search){
        search.addEventListener("input", ()=>{
          const q = search.value.toLowerCase();
          document.querySelectorAll("[data-fname]").forEach(el=>{
            const n = (el.getAttribute("data-fname") || "").toLowerCase();
            el.style.display = n.includes(q) ? "" : "none";
          });
        });
      }

      if(sortBtn){
        sortBtn.addEventListener("click", ()=>{
          const grid = document.getElementById("imgGrid");
          if(!grid) return;

          const items = Array.from(grid.querySelectorAll("a.thumb"));
          const mode = sortBtn.getAttribute("data-mode") || "asc";
          const next = (mode === "asc") ? "desc" : "asc";

          items.sort((a,b)=>{
            const an = (a.getAttribute("data-fname") || "").toLowerCase();
            const bn = (b.getAttribute("data-fname") || "").toLowerCase();
            if(an < bn) return (next === "asc") ? -1 : 1;
            if(an > bn) return (next === "asc") ? 1 : -1;
            return 0;
          });

          grid.innerHTML = "";
          items.forEach(i => grid.appendChild(i));
          sortBtn.setAttribute("data-mode", next);
          sortBtn.textContent = (next === "asc") ? "Sort: A→Z" : "Sort: Z→A";
        });
      }
    });
  </script>
</head>

<body>
  <header>
    <div class="topbar">
      <div>
        <h1>OGKB Image Library</h1>
        <p class="muted">Folder-first navigation. Chapters are folders inside <code>/images</code>.</p>
      </div>
      <div>
        <a class="pill" href="../">Home</a>
        <a class="pill" href="./">Image Library</a>
        <button class="btn" id="themeBtn" onclick="toggleTheme()">Dark mode</button>
      </div>
    </div>
  </header>

  <main>
    <?php if ($chapterErr !== null): ?>
      <div class="card"><div class="error"><?= h($chapterErr) ?></div></div>
    <?php endif; ?>

    <?php if ($chapterName === null): ?>
      <div class="card">
        <div class="muted">Select a chapter:</div>
      </div>

      <?php if (count($chapters) === 0): ?>
        <p class="empty">No chapter folders yet. Create folders inside <code>/var/www/html/images</code>.</p>
      <?php else: ?>
        <div class="gridCh">
          <?php foreach ($chapters as $ch): ?>
            <?php $count = count(listFiles($baseDir . DIRECTORY_SEPARATOR . $ch, $exts)); ?>
            <a class="chap" href="./?chapter=<?= h(rawurlencode($ch)) ?>">
              <div class="name"><?= h($ch) ?></div>
              <div class="meta"><?= (int)$count ?> image<?= ($count === 1 ? '' : 's') ?></div>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

    <?php else: ?>
      <div class="card">
        <div class="topbar">
          <div>
            <div class="muted">Chapter</div>
            <h2 style="margin:4px 0 0; font-size:20px;"><?= h($chapterName) ?></h2>
          </div>
          <div>
            <a class="pill" href="./">Back to chapters</a>
          </div>
        </div>

        <div class="toolbar">
          <input class="search" id="searchBox" type="search" placeholder="Search filenames...">
          <button class="btn" id="sortBtn" data-mode="desc">Sort: Z→A</button>
        </div>
      </div>

      <?php if (count($files) === 0): ?>
        <p class="empty">No images in this chapter yet.</p>
      <?php else: ?>
        <div class="card">
          <div class="gridImg" id="imgGrid">
            <?php foreach ($files as $f): ?>
              <a class="thumb" data-fname="<?= h($f) ?>" href="<?= h(rawurlencode($chapterName) . '/' . rawurlencode($f)) ?>">
                <img src="<?= h(rawurlencode($chapterName) . '/' . rawurlencode($f)) ?>" alt="<?= h($f) ?>">
                <div class="cap"><?= h($f) ?></div>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </main>
</body>
</html>
