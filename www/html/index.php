<?php
declare(strict_types=1);

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function formatBytes(int|float $bytes): string {
    $bytes = (float)$bytes;
    $units = ['B','KB','MB','GB','TB'];
    $i = 0;
    $v = $bytes;
    while ($v >= 1024 && $i < count($units)-1) { $v /= 1024; $i++; }
    return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.') . ' ' . $units[$i];
}

function isHidden(string $name): bool {
    return $name === '.' || $name === '..' || str_starts_with($name, '.');
}

function safeScandir(string $dir): array {
    $items = @scandir($dir);
    return ($items === false) ? [] : $items;
}

function listChapters(string $dir): array {
    if (!is_dir($dir) || !is_readable($dir)) return [];
    $out = [];
    foreach (safeScandir($dir) as $it) {
        if (isHidden($it)) continue;
        $full = $dir . DIRECTORY_SEPARATOR . $it;
        if (is_dir($full)) $out[] = $it;
    }
    natcasesort($out);
    return array_values($out);
}

function countFilesOneLevel(string $baseDir, array $exts): array {
    if (!is_dir($baseDir) || !is_readable($baseDir)) return [0, 0];

    $chapters = listChapters($baseDir);
    $totalFiles = 0;

    foreach ($chapters as $ch) {
        $p = $baseDir . DIRECTORY_SEPARATOR . $ch;
        if (!is_dir($p) || !is_readable($p)) continue;

        foreach (safeScandir($p) as $it) {
            if (isHidden($it)) continue;
            $full = $p . DIRECTORY_SEPARATOR . $it;
            if (!is_file($full)) continue;

            foreach ($exts as $ext) {
                if (preg_match('/\.' . preg_quote($ext, '/') . '$/i', $it)) { $totalFiles++; break; }
            }
        }
    }
    return [count($chapters), $totalFiles];
}

// Storage
$total = @disk_total_space('/') ?: 0;
$free  = @disk_free_space('/') ?: 0;
$used  = max(0, (float)$total - (float)$free);
$percent = ($total > 0) ? (int)round(($used / (float)$total) * 100) : 0;

// Library counts
[$pdfChapters, $pdfCount] = countFilesOneLevel('/var/www/html/pdfs', ['pdf']);
[$imgChapters, $imgCount] = countFilesOneLevel('/var/www/html/images', ['jpg','jpeg','png','webp','gif','svg']);
[$audChapters, $audCount] = countFilesOneLevel('/var/www/html/audio', ['mp3','wav','m4a','ogg','flac']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>OGKB - Off Grid Knowledge Base</title>

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

    body{ margin:0; background:var(--bg); color:var(--fg);
      font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif; }

    header{ padding:22px 20px 16px; border-bottom:1px solid var(--border); background:var(--card); }
    .topbar{ display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px; }
    h1{ margin:0; font-size:28px; letter-spacing:.5px; line-height:1.05; }
    .tagline{ margin:8px 0 0; color:var(--muted); font-size:14px; line-height:1.35; white-space:pre-line; }

    .btn{
      border:1px solid var(--border); background:var(--card); color:var(--fg);
      border-radius:999px; padding:8px 14px; cursor:pointer; box-shadow:var(--shadow); font-size:14px;
    }
    .btnDanger{
      border:1px solid var(--danger);
      color:var(--danger);
    }

    main{ padding:22px; max-width:1100px; margin:auto; }

    .panel{ background:var(--card); border:1px solid var(--border); border-radius:18px; padding:16px 18px;
      box-shadow:var(--shadow); margin-bottom:18px; }
    .panel h2{ margin:0 0 10px; font-size:16px; color:var(--muted); font-weight:600; letter-spacing:.3px; }
    .storageRow{ display:flex; justify-content:space-between; gap:10px; flex-wrap:wrap; align-items:baseline; margin-bottom:10px; }
    .big{ font-size:18px; font-weight:650; }
    .bar{ height:10px; background:var(--border); border-radius:999px; overflow:hidden; }
    .bar > div{ height:100%; width:0%; background:var(--fg); }

    .grid{ display:grid; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); gap:18px; }
    .card{ display:block; text-decoration:none; background:var(--card); border:1px solid var(--border); border-radius:18px;
      padding:18px; box-shadow:var(--shadow); color:var(--link); transition:transform .15s ease; }
    .card:hover{ transform:translateY(-3px); box-shadow:var(--shadowHover); }
    .card h3{ margin:0 0 8px; font-size:20px; }
    .card p{ margin:0; color:var(--muted); font-size:14px; line-height:1.4; }

    .meta{ margin-top:10px; font-size:12px; color:var(--muted); display:flex; justify-content:space-between; gap:10px; flex-wrap:wrap; }
    code{ background:var(--codebg); padding:2px 6px; border-radius:8px; }

    footer{ margin-top:26px; font-size:12px; color:var(--muted); text-align:center; }

    .statusMsg{ margin-left:10px; font-size:12px; color:var(--muted); }
  </style>

  <script>
    (function(){
      const saved = localStorage.getItem("ogkb_theme");
      if(saved === "dark" || saved === "light"){
        document.documentElement.setAttribute("data-theme", saved);
      } else {
        const prefersDark = window.matchMedia &&
          window.matchMedia("(prefers-color-scheme: dark)").matches;
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

    function shutdownNode(){
      const ok = confirm("Shutdown OGKB now?\n\nThis will turn the device off safely.\nTo turn it back on, use your power switch / power bank.");
      if(!ok) return;

      const btn = document.getElementById("shutdownBtn");
      const msg = document.getElementById("shutdownMsg");
      if(btn) btn.disabled = true;
      if(msg) msg.textContent = "Opening shutdown page…";

      // The shutdown flow requires session + CSRF token.
      // /shutdown.php is the dedicated page that creates the token and performs the POST.
      window.location.href = "/shutdown.php";
    }

    document.addEventListener("DOMContentLoaded", ()=>{
      const cur = document.documentElement.getAttribute("data-theme") || "light";
      const btn = document.getElementById("themeBtn");
      if(btn) btn.textContent = (cur === "dark") ? "Light mode" : "Dark mode";

      const bar = document.getElementById("usageBar");
      if(bar) bar.style.width = bar.getAttribute("data-pct") + "%";
    });
  </script>
</head>

<body>
  <header>
    <div class="topbar">
      <div>
        <h1>OGKB</h1>
        <p class="tagline">Off Grid Knowledge Base
Version 1.00 - 02-2026</p>
      </div>
      <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
        <button class="btn" id="themeBtn" onclick="toggleTheme()">Dark mode</button>
        <button class="btn btnDanger" id="shutdownBtn" onclick="shutdownNode()">Shutdown</button>
        <span class="statusMsg" id="shutdownMsg"></span>
      </div>
    </div>
  </header>

  <main>
    <div class="panel">
      <h2>Node Storage</h2>
      <div class="storageRow">
        <div class="big"><?= h(formatBytes($used)) ?> used</div>
        <div class="muted"><?= h(formatBytes($total)) ?> total · <?= (int)$percent ?>%</div>
      </div>
      <div class="bar">
        <div id="usageBar" data-pct="<?= (int)$percent ?>"></div>
      </div>
    </div>

    <div class="grid">
      <a class="card" href="/pdfs/">
        <h3>PDF Library</h3>
        <p>Manuals, survival guides, engineering references, radio documentation.</p>
        <div class="meta">
          <span><?= (int)$pdfChapters ?> chapters</span>
          <span><?= (int)$pdfCount ?> PDFs</span>
          <span>Path: <code>/pdfs</code></span>
        </div>
      </a>

      <a class="card" href="/images/">
        <h3>Image Library</h3>
        <p>Maps, schematics, visual checklists, field diagrams.</p>
        <div class="meta">
          <span><?= (int)$imgChapters ?> chapters</span>
          <span><?= (int)$imgCount ?> images</span>
          <span>Path: <code>/images</code></span>
        </div>
      </a>

      <a class="card" href="/audio/">
        <h3>Audio Library</h3>
        <p>Recorded procedures, comms training, offline lessons.</p>
        <div class="meta">
          <span><?= (int)$audChapters ?> chapters</span>
          <span><?= (int)$audCount ?> audio files</span>
          <span>Path: <code>/audio</code></span>
        </div>
      </a>
    </div>

    <footer>
      OGKB Node running locally — no internet required.
    </footer>
  </main>
</body>
</html>
