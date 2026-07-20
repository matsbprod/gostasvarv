<?php
/**
 * admin.php — Adminpanel för bokningar
 * Lösenord ändras på raden nedan.
 */

define('ADMIN_LOSENORD', 'gostasvarv2026');
define('DATA_FILE', __DIR__ . '/data/bokningar.json');
define('ADMIN_EMAIL', 'info@gostasvarv.se');

session_start();

// ── Inloggning ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['losenord'])) {
    if ($_POST['losenord'] === ADMIN_LOSENORD) {
        $_SESSION['inloggad'] = true;
    } else {
        $fel = 'Fel lösenord.';
    }
}
if (isset($_POST['logga_ut'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

// ── Uppdatera status ──────────────────────────────────────────
if (isset($_SESSION['inloggad']) && isset($_POST['uppdatera_id'])) {
    $data = json_decode(file_get_contents(DATA_FILE), true);
    foreach ($data['bokningar'] as &$b) {
        if ($b['id'] === $_POST['uppdatera_id']) {
            $ny_status = $_POST['ny_status'];
            $b['status'] = $ny_status;

            // Skicka mejl vid bekräftelse/avvisning
            $epost = $b['epost'];
            $namn  = $b['namn'];
            $bat   = $b['bat'];
            $datum = $b['datum'];

            if ($ny_status === 'bekräftad') {
                $amne = "=?UTF-8?B?" . base64_encode("Bokning bekräftad – $bat $datum") . "?=";
                $msg  = "Hej $namn,\n\nDin bokning är bekräftad!\n\nBåt:   $bat\nDatum: $datum\n\nVälkommen!\n\nKulturföreningen Gösta Johanssons Varv";
            } else {
                $amne = "=?UTF-8?B?" . base64_encode("Bokning ej möjlig – $bat $datum") . "?=";
                $msg  = "Hej $namn,\n\nTyvärr kan vi inte bekräfta din bokning av $bat den $datum.\n\nKontakta oss på " . ADMIN_EMAIL . " för mer information.\n\nKulturföreningen Gösta Johanssons Varv";
            }
            mail($epost, $amne, $msg, "From: " . ADMIN_EMAIL . "\r\nContent-Type: text/plain; charset=UTF-8");
            break;
        }
    }
    file_put_contents(DATA_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    header('Location: admin.php');
    exit;
}

// ── Ta bort bokning ───────────────────────────────────────────
if (isset($_SESSION['inloggad']) && isset($_POST['ta_bort_id'])) {
    $data = json_decode(file_get_contents(DATA_FILE), true);
    $data['bokningar'] = array_values(array_filter($data['bokningar'], fn($b) => $b['id'] !== $_POST['ta_bort_id']));
    file_put_contents(DATA_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    header('Location: admin.php');
    exit;
}

// ── Läs data ──────────────────────────────────────────────────
$bokningar = [];
if (file_exists(DATA_FILE)) {
    $data = json_decode(file_get_contents(DATA_FILE), true);
    $bokningar = $data['bokningar'] ?? [];
    usort($bokningar, fn($a, $b) => strcmp($a['datum'], $b['datum']));
}

$status_farg = ['väntar' => '#c8733d', 'bekräftad' => '#4a9a5a', 'avvisad' => '#666'];
?>
<!DOCTYPE html>
<html lang="sv">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Adminpanel – Bokningar</title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: system-ui, sans-serif; background: #111; color: #eee; min-height: 100vh; }
  .wrap { max-width: 900px; margin: 0 auto; padding: 2rem 1rem; }
  h1 { font-size: 1.4rem; margin-bottom: 0.3rem; }
  .sub { color: #888; font-size: 0.85rem; margin-bottom: 2rem; }

  /* Login */
  .login-box { background: #1a1a18; border: 1px solid #333; padding: 2rem; max-width: 360px; border-radius: 4px; }
  .login-box h2 { margin-bottom: 1.5rem; }
  input[type=password], input[type=text] { width: 100%; padding: 0.7rem; background: #222; border: 1px solid #444; color: #eee; border-radius: 2px; margin-bottom: 1rem; font-size: 1rem; }
  .btn { padding: 0.7rem 1.4rem; background: #a0522d; color: #fff; border: none; border-radius: 2px; cursor: pointer; font-size: 0.9rem; }
  .btn:hover { background: #c8733d; }
  .btn-sm { padding: 0.35rem 0.8rem; font-size: 0.78rem; }
  .btn-ok { background: #2d6a3f; }
  .btn-ok:hover { background: #3a8a52; }
  .btn-nej { background: #555; }
  .btn-nej:hover { background: #777; }
  .btn-del { background: #6a2d2d; }
  .btn-del:hover { background: #8a3a3a; }
  .fel { color: #e07070; margin-bottom: 1rem; }

  /* Tabs */
  .tabs { display: flex; gap: 0; margin-bottom: 1.5rem; border-bottom: 1px solid #333; }
  .tab-btn { background: none; border: none; color: #888; padding: 0.6rem 1.2rem; cursor: pointer; font-size: 0.85rem; border-bottom: 2px solid transparent; margin-bottom: -1px; }
  .tab-btn.active { color: #eee; border-bottom-color: #c8733d; }

  /* Stats */
  .stats { display: flex; gap: 1rem; margin-bottom: 2rem; flex-wrap: wrap; }
  .stat { background: #1a1a18; border: 1px solid #333; padding: 1rem 1.5rem; border-radius: 4px; text-align: center; }
  .stat-num { font-size: 1.8rem; font-weight: 700; }
  .stat-lbl { font-size: 0.72rem; color: #888; text-transform: uppercase; letter-spacing: 0.08em; }

  /* Table */
  table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
  th { text-align: left; padding: 0.6rem 0.8rem; color: #888; font-weight: 500; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.08em; border-bottom: 1px solid #333; }
  td { padding: 0.8rem 0.8rem; border-bottom: 1px solid #222; vertical-align: middle; }
  tr:hover td { background: #1a1a18; }
  .badge { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 20px; font-size: 0.72rem; font-weight: 600; }
  .actions { display: flex; gap: 0.4rem; flex-wrap: wrap; }
  .empty { color: #555; text-align: center; padding: 3rem; }
  .logout { float: right; }
</style>
</head>
<body>
<div class="wrap">

<?php if (!isset($_SESSION['inloggad'])): ?>

  <h1>Adminpanel · Gösta Johanssons Varv</h1>
  <p class="sub">Logga in för att hantera bokningar</p>
  <div class="login-box">
    <h2>Logga in</h2>
    <?php if (!empty($fel)): ?><p class="fel"><?= htmlspecialchars($fel) ?></p><?php endif; ?>
    <form method="POST">
      <input type="password" name="losenord" placeholder="Lösenord" autofocus>
      <button class="btn" type="submit">Logga in</button>
    </form>
  </div>

<?php else: ?>

  <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:0.3rem;">
    <h1>Bokningar · Gösta Johanssons Varv</h1>
    <form method="POST"><button class="btn btn-sm logout" name="logga_ut" value="1">Logga ut</button></form>
  </div>
  <p class="sub">Hantera bokningsförfrågningar för Josefin och Myra</p>

  <?php
    $vantande  = array_filter($bokningar, fn($b) => $b['status'] === 'väntar');
    $bekraftade = array_filter($bokningar, fn($b) => $b['status'] === 'bekräftad');
    $avvisade  = array_filter($bokningar, fn($b) => $b['status'] === 'avvisad');
  ?>

  <div class="stats">
    <div class="stat"><div class="stat-num" style="color:#c8733d"><?= count($vantande) ?></div><div class="stat-lbl">Väntar</div></div>
    <div class="stat"><div class="stat-num" style="color:#4a9a5a"><?= count($bekraftade) ?></div><div class="stat-lbl">Bekräftade</div></div>
    <div class="stat"><div class="stat-num" style="color:#666"><?= count($avvisade) ?></div><div class="stat-lbl">Avvisade</div></div>
    <div class="stat"><div class="stat-num"><?= count($bokningar) ?></div><div class="stat-lbl">Totalt</div></div>
  </div>

  <?php
  $filter = $_GET['filter'] ?? 'väntar';
  $visade = match($filter) {
    'bekräftad' => $bekraftade,
    'avvisad'   => $avvisade,
    'alla'      => $bokningar,
    default     => $vantande,
  };
  ?>

  <div class="tabs">
    <?php foreach (['väntar' => 'Väntar (' . count($vantande) . ')', 'bekräftad' => 'Bekräftade', 'avvisad' => 'Avvisade', 'alla' => 'Alla'] as $k => $lbl): ?>
      <a href="?filter=<?= $k ?>"><button class="tab-btn <?= $filter === $k ? 'active' : '' ?>"><?= $lbl ?></button></a>
    <?php endforeach; ?>
  </div>

  <?php if (empty($visade)): ?>
    <p class="empty">Inga bokningar att visa.</p>
  <?php else: ?>
  <table>
    <thead>
      <tr>
        <th>Datum</th>
        <th>Båt</th>
        <th>Namn</th>
        <th>Kontakt</th>
        <th>Inkommen</th>
        <th>Status</th>
        <th>Åtgärd</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($visade as $b): ?>
      <tr>
        <td><strong><?= htmlspecialchars($b['datum']) ?></strong></td>
        <td><?= htmlspecialchars($b['bat']) ?></td>
        <td><?= htmlspecialchars($b['namn']) ?></td>
        <td>
          <a href="tel:<?= htmlspecialchars($b['telefon']) ?>" style="color:#c8733d"><?= htmlspecialchars($b['telefon']) ?></a><br>
          <a href="mailto:<?= htmlspecialchars($b['epost']) ?>" style="color:#888; font-size:0.82rem"><?= htmlspecialchars($b['epost']) ?></a>
        </td>
        <td style="font-size:0.8rem; color:#888"><?= substr($b['skapad'], 0, 10) ?></td>
        <td>
          <span class="badge" style="background:<?= $status_farg[$b['status']] ?>22; color:<?= $status_farg[$b['status']] ?>">
            <?= htmlspecialchars($b['status']) ?>
          </span>
        </td>
        <td>
          <div class="actions">
            <?php if ($b['status'] !== 'bekräftad'): ?>
            <form method="POST">
              <input type="hidden" name="uppdatera_id" value="<?= $b['id'] ?>">
              <input type="hidden" name="ny_status" value="bekräftad">
              <button class="btn btn-sm btn-ok" type="submit">✓ Bekräfta</button>
            </form>
            <?php endif; ?>
            <?php if ($b['status'] !== 'avvisad'): ?>
            <form method="POST">
              <input type="hidden" name="uppdatera_id" value="<?= $b['id'] ?>">
              <input type="hidden" name="ny_status" value="avvisad">
              <button class="btn btn-sm btn-nej" type="submit">✕ Avvisa</button>
            </form>
            <?php endif; ?>
            <form method="POST" onsubmit="return confirm('Ta bort bokningen?')">
              <input type="hidden" name="ta_bort_id" value="<?= $b['id'] ?>">
              <button class="btn btn-sm btn-del" type="submit">🗑</button>
            </form>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>

<?php endif; ?>

</div>
</body>
</html>
