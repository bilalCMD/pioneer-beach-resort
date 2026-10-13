<?php
/**
 * Events & Activities admin.
 *
 * Lets Pioneer staff add, edit, reschedule, cancel and delete everything that
 * appears on the public events page, with no developer involved. Writes to
 * events.json, which the public page reads.
 */
declare(strict_types=1);

$CFG = require __DIR__ . '/events-config.php';

session_name($CFG['session_name']);
session_start();

function verify_password(string $pw, array $cfg): bool {
    $calc = hash_pbkdf2('sha256', $pw, $cfg['pw_salt'], (int)$cfg['pw_iter'], 64);
    return hash_equals($cfg['pw_hash'], $calc);
}

function load_events(array $cfg): array {
    if (!is_file($cfg['data_file'])) return ['updated' => date('Y-m-d'), 'events' => []];
    $raw = json_decode((string)file_get_contents($cfg['data_file']), true);
    if (!is_array($raw) || !isset($raw['events'])) return ['updated' => date('Y-m-d'), 'events' => []];
    return $raw;
}

function save_events(array $cfg, array $data): bool {
    $data['updated'] = date('Y-m-d');
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return file_put_contents($cfg['data_file'], $json, LOCK_EX) !== false;
}

function field(string $k, int $max = 400): string {
    $v = trim((string)($_POST[$k] ?? ''));
    return mb_substr($v, 0, $max);
}

$err = '';
$msg = '';

/* ---------------------------------------------------------------- auth */
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: events-admin.php');
    exit;
}

if (!empty($_POST['action']) && $_POST['action'] === 'login') {
    if (verify_password((string)($_POST['password'] ?? ''), $CFG)) {
        session_regenerate_id(true);
        $_SESSION['ok'] = true;
        $_SESSION['token'] = bin2hex(random_bytes(16));
        header('Location: events-admin.php');
        exit;
    }
    $err = 'That password was not recognised.';
}

$authed = !empty($_SESSION['ok']);

/* ------------------------------------------------------------- actions */
if ($authed && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') !== 'login') {
    if (!hash_equals((string)($_SESSION['token'] ?? ''), (string)($_POST['token'] ?? ''))) {
        $err = 'Your session expired. Please try again.';
    } else {
        $data = load_events($CFG);
        $action = $_POST['action'] ?? '';

        if ($action === 'delete') {
            $id = field('id', 60);
            $data['events'] = array_values(array_filter(
                $data['events'], fn($e) => ($e['id'] ?? '') !== $id));
            $msg = save_events($CFG, $data) ? 'Deleted.' : 'Could not save.';

        } elseif ($action === 'save') {
            $id = field('id', 60);
            $ev = [
                'id'         => $id !== '' ? $id : 'evt_' . bin2hex(random_bytes(5)),
                'kind'       => in_array(field('kind', 20), ['activity','event','promotion'], true)
                                 ? field('kind', 20) : 'event',
                'title'      => field('title', 120),
                'date'       => field('date', 10),
                'endDate'    => field('endDate', 10),
                'recurs'     => field('recurs', 60),
                'time'       => field('time', 40),
                'location'   => field('location', 80),
                'description'=> field('description', 1200),
                'image'      => field('image_existing', 200),
                'status'     => in_array(field('status', 20), ['scheduled','cancelled','rescheduled'], true)
                                 ? field('status', 20) : 'scheduled',
                'statusNote' => field('statusNote', 160),
            ];

            if ($ev['title'] === '') {
                $err = 'An event needs a title.';
            } else {
                /* optional flyer / photo */
                if (!empty($_FILES['image']['tmp_name']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
                    $okTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                    $mime = (string)(new finfo(FILEINFO_MIME_TYPE))->file($_FILES['image']['tmp_name']);
                    if (!isset($okTypes[$mime])) {
                        $err = 'Images must be a JPG, PNG or WebP.';
                    } elseif ($_FILES['image']['size'] > 6 * 1024 * 1024) {
                        $err = 'Images must be under 6MB.';
                    } else {
                        if (!is_dir($CFG['upload_dir'])) @mkdir($CFG['upload_dir'], 0775, true);
                        $name = $ev['id'] . '_' . bin2hex(random_bytes(3)) . '.' . $okTypes[$mime];
                        if (move_uploaded_file($_FILES['image']['tmp_name'], $CFG['upload_dir'] . '/' . $name)) {
                            $ev['image'] = $CFG['upload_url'] . '/' . $name;
                        } else {
                            $err = 'The image could not be saved.';
                        }
                    }
                }

                if ($err === '') {
                    $found = false;
                    foreach ($data['events'] as $i => $old) {
                        if (($old['id'] ?? '') === $ev['id']) { $data['events'][$i] = $ev; $found = true; break; }
                    }
                    if (!$found) $data['events'][] = $ev;
                    $msg = save_events($CFG, $data)
                        ? ($found ? 'Saved.' : 'Added.')
                        : 'Could not write events.json - check file permissions.';
                }
            }
        }
    }
}

/* ---- helper for rotating the password ---- */
if ($authed && isset($_GET['newhash']) && $_GET['newhash'] !== '') {
    $salt = bin2hex(random_bytes(16));
    $iter = 120000;
    $hash = hash_pbkdf2('sha256', (string)$_GET['newhash'], $salt, $iter, 64);
    $msg = "Paste these three lines into events-config.php:\n"
         . "'pw_salt' => '$salt',\n'pw_iter' => $iter,\n'pw_hash' => '$hash',";
}

$data   = $authed ? load_events($CFG) : ['events' => []];
$events = $data['events'];
usort($events, function ($a, $b) {
    return strcmp(($a['date'] ?? '9999'), ($b['date'] ?? '9999'));
});

$edit = null;
if ($authed && isset($_GET['edit'])) {
    foreach ($events as $e) if (($e['id'] ?? '') === $_GET['edit']) $edit = $e;
}
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Events Admin | Pioneer Beach RV Resort</title>
<link rel="icon" href="favicon.ico" sizes="any">
<style>
  :root{--red:#ED1F24;--ink:#1a1a1a;--mut:#6b6b6b;--line:#e6e6e6;--bg:#f5f5f6}
  *{box-sizing:border-box}
  body{margin:0;background:var(--bg);color:var(--ink);
       font:15px/1.6 -apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif}
  header{background:var(--red);color:#fff;padding:18px 20px}
  header h1{margin:0;font-size:1.1rem;letter-spacing:.02em}
  header a{color:#fff;float:right;font-size:.85rem}
  .wrap{max-width:960px;margin:0 auto;padding:22px 16px 60px}
  .card{background:#fff;border:1px solid var(--line);border-radius:12px;padding:20px;margin-bottom:18px}
  h2{font-size:1rem;margin:0 0 14px}
  label{display:block;font-size:.78rem;font-weight:700;text-transform:uppercase;
        letter-spacing:.06em;color:var(--mut);margin:12px 0 5px}
  input,select,textarea{width:100%;padding:10px 12px;border:1px solid var(--line);
        border-radius:8px;font:inherit;background:#fff}
  textarea{min-height:90px;resize:vertical}
  .row{display:grid;grid-template-columns:1fr 1fr;gap:14px}
  .btn{display:inline-block;background:var(--red);color:#fff;border:none;border-radius:999px;
       padding:11px 22px;font:inherit;font-weight:700;cursor:pointer;text-decoration:none}
  .btn.sec{background:#fff;color:var(--ink);border:1px solid var(--line)}
  .btn.warn{background:#fff;color:var(--red);border:1px solid var(--red)}
  .msg{background:#eaf7ed;border:1px solid #bfe3c9;padding:11px 14px;border-radius:8px;
       margin-bottom:16px;white-space:pre-wrap;font-size:.9rem}
  .err{background:#fdeaea;border:1px solid #f3c2c2;padding:11px 14px;border-radius:8px;margin-bottom:16px}
  table{width:100%;border-collapse:collapse}
  th,td{text-align:left;padding:10px 8px;border-bottom:1px solid var(--line);font-size:.9rem;vertical-align:top}
  th{font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:var(--mut)}
  .pill{display:inline-block;font-size:.68rem;font-weight:700;text-transform:uppercase;
        letter-spacing:.05em;padding:3px 9px;border-radius:999px;background:#eee;color:#555}
  .pill.cancelled{background:#fdeaea;color:#c0272d}
  .pill.rescheduled{background:#fff4e0;color:#9a6200}
  .pill.activity{background:#e8f1fb;color:#1c5fa8}
  .pill.event{background:#fdeaea;color:#c0272d}
  .pill.promotion{background:#eaf7ed;color:#1f7a3d}
  .acts a,.acts button{font-size:.82rem;margin-right:10px}
  .hint{font-size:.8rem;color:var(--mut);margin-top:6px}
  .login{max-width:360px;margin:60px auto}
  @media(max-width:620px){.row{grid-template-columns:1fr}}
</style>
</head>
<body>

<?php if (!$authed): ?>
<div class="wrap login">
  <div class="card">
    <h2>Pioneer Events Admin</h2>
    <?php if ($err): ?><div class="err"><?= h($err) ?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="action" value="login">
      <label for="pw">Password</label>
      <input id="pw" type="password" name="password" required autofocus>
      <p><button class="btn" type="submit" style="margin-top:14px">Sign in</button></p>
    </form>
  </div>
</div>

<?php else: ?>
<header><div class="wrap" style="padding:0">
  <a href="?logout=1">Sign out</a>
  <h1>Events &amp; Activities &mdash; Admin</h1>
</div></header>

<div class="wrap">
  <?php if ($msg): ?><div class="msg"><?= h($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="err"><?= h($err) ?></div><?php endif; ?>

  <div class="card">
    <h2><?= $edit ? 'Edit' : 'Add' ?> an entry</h2>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="token" value="<?= h($_SESSION['token']) ?>">
      <input type="hidden" name="id" value="<?= h($edit['id'] ?? '') ?>">
      <input type="hidden" name="image_existing" value="<?= h($edit['image'] ?? '') ?>">

      <label for="title">Title</label>
      <input id="title" name="title" required value="<?= h($edit['title'] ?? '') ?>">

      <div class="row">
        <div>
          <label for="kind">Type</label>
          <select id="kind" name="kind">
            <?php foreach ([
              'event' => 'Special event (has a date)',
              'activity' => 'Regular activity (repeats)',
              'promotion' => 'Promotion',
            ] as $k => $lbl): ?>
              <option value="<?= $k ?>" <?= ($edit['kind'] ?? 'event') === $k ? 'selected' : '' ?>><?= $lbl ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label for="status">Status</label>
          <select id="status" name="status">
            <?php foreach (['scheduled' => 'Going ahead', 'cancelled' => 'Cancelled',
                            'rescheduled' => 'Rescheduled'] as $k => $lbl): ?>
              <option value="<?= $k ?>" <?= ($edit['status'] ?? 'scheduled') === $k ? 'selected' : '' ?>><?= $lbl ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="row">
        <div>
          <label for="date">Date</label>
          <input id="date" type="date" name="date" value="<?= h($edit['date'] ?? '') ?>">
          <p class="hint">Leave blank for a repeating activity.</p>
        </div>
        <div>
          <label for="endDate">End date (optional)</label>
          <input id="endDate" type="date" name="endDate" value="<?= h($edit['endDate'] ?? '') ?>">
          <p class="hint">For multi-day events or promotions.</p>
        </div>
      </div>

      <div class="row">
        <div>
          <label for="recurs">Repeats</label>
          <input id="recurs" name="recurs" placeholder="Every Thursday" value="<?= h($edit['recurs'] ?? '') ?>">
          <p class="hint">Only for regular activities.</p>
        </div>
        <div>
          <label for="time">Time</label>
          <input id="time" name="time" placeholder="7:00 PM" value="<?= h($edit['time'] ?? '') ?>">
        </div>
      </div>

      <label for="location">Location</label>
      <input id="location" name="location" placeholder="Rec Hall" value="<?= h($edit['location'] ?? '') ?>">

      <label for="statusNote">Status note (optional)</label>
      <input id="statusNote" name="statusNote" placeholder="Moved to Sunday 3pm"
             value="<?= h($edit['statusNote'] ?? '') ?>">
      <p class="hint">Shown when something is cancelled or rescheduled.</p>

      <label for="description">Description</label>
      <textarea id="description" name="description"><?= h($edit['description'] ?? '') ?></textarea>

      <label for="image">Photo or flyer</label>
      <input id="image" type="file" name="image" accept="image/jpeg,image/png,image/webp">
      <?php if (!empty($edit['image'])): ?>
        <p class="hint">Currently: <?= h($edit['image']) ?> &mdash; upload a new file to replace it.</p>
      <?php endif; ?>

      <p style="margin-top:18px">
        <button class="btn" type="submit"><?= $edit ? 'Save changes' : 'Add entry' ?></button>
        <?php if ($edit): ?><a class="btn sec" href="events-admin.php">Cancel</a><?php endif; ?>
      </p>
    </form>
  </div>

  <div class="card">
    <h2>Everything on the page (<?= count($events) ?>)</h2>
    <?php if (!$events): ?>
      <p style="color:var(--mut)">Nothing yet. Add your first entry above.</p>
    <?php else: ?>
    <table>
      <tr><th>Title</th><th>When</th><th>Type</th><th>Status</th><th></th></tr>
      <?php foreach ($events as $e): ?>
      <tr>
        <td><strong><?= h($e['title'] ?? '') ?></strong><br>
            <span style="color:var(--mut);font-size:.84rem"><?= h($e['location'] ?? '') ?></span></td>
        <td><?= h($e['date'] ?? ($e['recurs'] ?? '-')) ?><br>
            <span style="color:var(--mut);font-size:.84rem"><?= h($e['time'] ?? '') ?></span></td>
        <td><span class="pill <?= h($e['kind'] ?? 'event') ?>"><?= h($e['kind'] ?? 'event') ?></span></td>
        <td><span class="pill <?= h($e['status'] ?? 'scheduled') ?>"><?= h($e['status'] ?? 'scheduled') ?></span></td>
        <td class="acts">
          <a href="?edit=<?= urlencode((string)($e['id'] ?? '')) ?>">Edit</a>
          <form method="post" style="display:inline"
                onsubmit="return confirm('Delete this entry? This cannot be undone.')">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="token" value="<?= h($_SESSION['token']) ?>">
            <input type="hidden" name="id" value="<?= h($e['id'] ?? '') ?>">
            <button class="btn warn" type="submit" style="padding:5px 13px;font-size:.8rem">Delete</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>
    <p class="hint" style="margin-top:16px">
      Changes appear on <a href="/events" target="_blank">the events page</a> straight away.
    </p>
  </div>
</div>
<?php endif; ?>

</body>
</html>
