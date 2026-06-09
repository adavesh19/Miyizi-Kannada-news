<?php
declare(strict_types=1);
require_once __DIR__ . '/public_html/includes/config.php';

$secret     = getenv('CRON_SECRET') ?: '';
$inputPass  = trim((string) ($_POST['secret'] ?? $_GET['secret'] ?? ''));
$authed     = $secret !== '' && hash_equals($secret, $inputPass);
$triggered  = false;
$result     = '';

if ($authed && isset($_POST['run'])) {
    // Run the agent inline
    ob_start();
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $secret;
    require __DIR__ . '/public_html/api/agent-cron.php';
    $result = ob_get_clean();
    $triggered = true;
}
?>
<!DOCTYPE html>
<html lang="kn">
<head>
<meta charset="UTF-8">
<title>MIYIZE Agent Control</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: system-ui, sans-serif; background: #0f0f1a; color: #e0e0f0; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
  .card { background: #1a1a2e; border: 1px solid #2d2d4e; border-radius: 16px; padding: 40px; width: 100%; max-width: 520px; }
  h1 { font-size: 1.5rem; color: #a78bfa; margin-bottom: 8px; }
  p  { color: #888; font-size: 0.9rem; margin-bottom: 24px; }
  input[type=password], input[type=text] { width: 100%; padding: 12px 16px; background: #0f0f1a; border: 1px solid #3d3d6e; border-radius: 8px; color: #e0e0f0; font-size: 1rem; margin-bottom: 16px; }
  button { width: 100%; padding: 14px; background: linear-gradient(135deg, #7c3aed, #4f46e5); border: none; border-radius: 8px; color: #fff; font-size: 1rem; font-weight: 600; cursor: pointer; }
  button:hover { opacity: 0.9; }
  .result { margin-top: 24px; background: #0f0f1a; border: 1px solid #2d2d4e; border-radius: 8px; padding: 16px; font-family: monospace; font-size: 0.8rem; white-space: pre-wrap; color: #a0f0a0; max-height: 300px; overflow-y: auto; }
  .status { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; margin-bottom: 16px; }
  .ok   { background: #064e3b; color: #34d399; }
  .fail { background: #450a0a; color: #f87171; }
  .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 24px; }
  .info-item { background: #0f0f1a; border-radius: 8px; padding: 10px 14px; font-size: 0.82rem; }
  .info-label { color: #666; font-size: 0.72rem; margin-bottom: 2px; }
  .info-val { color: #a78bfa; font-weight: 600; }
  .info-val.ok { color: #34d399; }
  .info-val.bad { color: #f87171; }
</style>
</head>
<body>
<div class="card">
  <h1>🚀 MIYIZE Agent Control</h1>
  <p>Trigger the AI news agent manually. Requires the CRON_SECRET password.</p>

  <?php if ($authed): ?>
    <span class="status ok">✓ Authenticated</span>
    <div class="info-grid">
      <div class="info-item"><div class="info-label">Groq AI</div><div class="info-val <?= getenv('MIYIZE_GROQ_KEY') ? 'ok' : 'bad' ?>"><?= getenv('MIYIZE_GROQ_KEY') ? '✓ Set' : '✗ Missing' ?></div></div>
      <div class="info-item"><div class="info-label">GitHub Token</div><div class="info-val <?= getenv('MIYIZE_GITHUB_TOKEN') ? 'ok' : 'bad' ?>"><?= getenv('MIYIZE_GITHUB_TOKEN') ? '✓ Set' : '✗ Missing' ?></div></div>
      <div class="info-item"><div class="info-label">Make Webhook</div><div class="info-val <?= getenv('MIYIZE_MAKE_WEBHOOK') ? 'ok' : 'bad' ?>"><?= getenv('MIYIZE_MAKE_WEBHOOK') ? '✓ Set' : '✗ Missing' ?></div></div>
      <div class="info-item"><div class="info-label">Last Cron</div><div class="info-val ok">Every 4h</div></div>
    </div>
    <form method="POST">
      <input type="hidden" name="secret" value="<?= htmlspecialchars($inputPass) ?>">
      <button type="submit" name="run" value="1">⚡ Run Agent Now</button>
    </form>
    <?php if ($triggered): ?>
      <div class="result"><?= htmlspecialchars($result ?: 'Agent ran. Check GitHub for new commits to articles.json') ?></div>
    <?php endif; ?>
  <?php else: ?>
    <form method="POST">
      <input type="password" name="secret" placeholder="Enter CRON_SECRET password..." autocomplete="current-password">
      <button type="submit">🔓 Login</button>
    </form>
    <?php if ($_POST && !$authed): ?>
      <p style="color:#f87171;margin-top:12px;">❌ Incorrect password</p>
    <?php endif; ?>
  <?php endif; ?>
</div>
</body>
</html>
