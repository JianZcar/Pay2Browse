<?php
session_start();
$db = new SQLite3(__DIR__ . '/../data/db.sqlite');
$debug = [];
$message = '';

// Redirect non-admins back to the portal
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: ../');
    exit;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    die('Invalid request ID.');
}

$stmt = $db->prepare('SELECT ip, duration, status FROM requests WHERE id = :id');
$stmt->bindValue(':id', $id, SQLITE3_INTEGER);
$request = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
if (!$request) {
    die('Request not found.');
}

if ($request['status'] == 1) {
    die('This request has already been approved.');
}

if (isset($_POST['approve'])) {
    $now      = time();
    $duration = intval($request['duration']);
    $end      = $now + $duration;

    // update the DB
    $u = $db->prepare('UPDATE requests
                        SET status     = 1,
                            start_time = :start,
                            end_time   = :end
                      WHERE id = :id');
    $u->bindValue(':start', date('Y-m-d H:i:s', $now));
    $u->bindValue(':end',   date('Y-m-d H:i:s', $end));
    $u->bindValue(':id',    $id, SQLITE3_INTEGER);
    $u->execute();

    // add the client IP to ipset
    $clientIp = $request['ip'];
    $cmd = sprintf(
      'sudo /usr/sbin/ipset add allowed %s timeout %d',
      escapeshellarg($clientIp),
      $duration
    );
    exec($cmd . ' 2>>/tmp/ipset.log', $out, $status);

    $debug[] = "Command: $cmd";
    $debug[] = "Exit status: $status";

    if ($status === 0) {
      $message = "Request #$id approved and {$clientIp} added for {$duration}s.";
    } else {
      $message = "Approved in database, but ipset failed—see /tmp/ipset.log.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Approve Request #<?= $id ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="./favicon.png">
  <link href="../css/output.css" rel="stylesheet">
  <link href="../css/input.css"  rel="stylesheet">
</head>
<body class="bg-gray-100 flex flex-col items-center justify-center min-h-screen p-4">
  <div class="bg-white p-8 rounded-2xl shadow-lg text-center space-y-6 max-w-md w-full">
    <h1 class="text-3xl font-extrabold text-gray-800">Approve Access Request</h1>
    <p class="text-lg text-gray-700"><strong>Request ID:</strong> <?= $id ?></p>
    <p class="text-lg text-gray-700"><strong>Client IP:</strong> <?= htmlspecialchars($request['ip']) ?></p>
    <p class="text-lg text-gray-700"><strong>Duration:</strong> <?= intval($request['duration'])/3600 ?> hour(s)</p>

    <h2 class="text-xl font-semibold text-gray-800 mb-2">Allow access to the internet?</h2>
    <form method="post" class="space-y-4">
      <button
        type="submit"
        name="approve"
        class="w-full px-4 py-2 bg-blue-600 text-white rounded-2xl hover:bg-blue-700"
      >
        Approve
      </button>
    </form>
    <?php if ($message): ?>
      <div class="mt-4 text-left text-gray-800"><?= $message ?></div>
    <?php endif; ?>
    <?php if (!empty($debug)): ?>
      <details class="mt-4 text-left text-sm text-gray-600">
        <summary class="cursor-pointer">Debug Info</summary>
        <ul class="list-disc list-inside">
          <?php foreach ($debug as $d): ?>
            <li><?= htmlspecialchars($d) ?></li>
          <?php endforeach; ?>
        </ul>
      </details>
    <?php endif ?>
  </div>
</body>
</html>
