<?php
declare(strict_types=1);

session_start();

if (!isset($_SESSION['csrf']) || !is_string($_SESSION['csrf']) || strlen($_SESSION['csrf']) < 16) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$token = $_SESSION['csrf'];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>OGKB Power</title>
  <style>
    body { font-family: system-ui, sans-serif; padding: 16px; }
    button { font-size: 18px; padding: 14px 18px; border-radius: 10px; border: 1px solid #999; }
    .danger { border-color: #b00; }
    .status { margin-top: 12px; opacity: .95; white-space: pre-wrap; }
    a { display:inline-block; margin-top: 14px; text-decoration:none; }
  </style>
</head>
<body>
  <h2>OGKB Node</h2>
  <p>Hold the button for 2 seconds to shutdown.</p>

  <button id="btn" class="danger">Hold to Shutdown</button>
  <div class="status" id="status"></div>

  <a href="/index.php">← Back</a>

<script>
(() => {
  const btn = document.getElementById('btn');
  const status = document.getElementById('status');
  const token = <?= json_encode($token) ?>;

  let timer = null;
  let fired = false;

  function setStatus(msg) { status.textContent = msg; }

  async function doShutdown() {
    if (fired) return;
    fired = true;

    setStatus('Sending shutdown command...');

    try {
      const form = new URLSearchParams();
      form.set('token', token);

      const url = window.location.origin + '/api/shutdown.php';

      const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: form.toString(),
        credentials: 'same-origin',
        cache: 'no-store'
      });

      const text = await res.text();
      setStatus(`HTTP ${res.status}\nResponse:\n${text}\n`);
    } catch (e) {
      setStatus('Fetch failed: ' + e);
    }
  }

  function startHold() {
    fired = false;
    setStatus('Keep holding...');
    timer = setTimeout(() => {
      setStatus('Confirmed.\nCalling shutdown...');
      doShutdown();
    }, 2000);
  }

  function cancelHold() {
    if (timer) clearTimeout(timer);
    timer = null;
    if (!fired) setStatus('Cancelled.');
  }

  btn.addEventListener('mousedown', startHold);
  btn.addEventListener('mouseup', cancelHold);
  btn.addEventListener('mouseleave', cancelHold);

  btn.addEventListener('touchstart', (e) => { e.preventDefault(); startHold(); }, { passive: false });
  btn.addEventListener('touchend', cancelHold);
})();
</script>
</body>
</html>
