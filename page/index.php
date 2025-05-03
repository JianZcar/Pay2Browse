<?php
session_start();
$db = new SQLite3('/var/www/html/data/db.sqlite');
$debug = [];
$message = '';


if (isset($_GET['admin_login'])) {
    $pwd = $_POST['admin_password'] ?? '';
    $stmt = $db->prepare('SELECT COUNT(*) AS cnt FROM admin WHERE password = :pwd');
    $stmt->bindValue(':pwd', $pwd, SQLITE3_TEXT);
    $res = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

    if ($res['cnt'] > 0) {
        $_SESSION['admin_logged_in'] = true;
        $ip = $_SERVER['REMOTE_ADDR'];
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        $message = "Invalid IP address.";
        } else {   
            $cmd = sprintf(
                'sudo /usr/sbin/ipset add allowed %s timeout 86400',
                escapeshellarg($ip),
            );

            exec($cmd . ' 2>>/tmp/ipset.log', $output, $status);
            $debug[] = "Command: $cmd";
            $debug[] = "Exit status: $status";

            if ($status === 0) {
                $message = "Access granted to admin.";
            } else {
                $message = "Failed to grant access—see /tmp/ipset.log.";
            }
        }
    } else {
        $message = '⚠️ Invalid admin password.';
        $debug[] = 'Attempted password: ' . htmlspecialchars($pwd);
    }
}

if (isset($_GET['client_request'])) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $hours = intval($_POST['hours'] ?? 1);
    $debug[] = "Requesting access for $ip for {$hours} hour(s)";

    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        $message = "Invalid IP address.";
    } else {
        $duration = $hours * 3600;

        try {
            $stmt = $db->prepare("INSERT INTO requests (ip, status, duration) VALUES (:ip, 0, :duration)");
            $stmt->bindValue(':ip', $ip);
            $stmt->bindValue(':duration', $duration);
            if (!$stmt->execute()) {
                die('Insert failed: ' . $db->lastErrorMsg());
            }
            $requestId = $db->lastInsertRowID();

            header("Location: confirm/?id=" . $requestId);
            exit();
        } catch (Exception $e) {
            $message = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Pay2Browse Portal</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="./favicon.png">
  <link href="./css/output.css" rel="stylesheet">
  <link href="./css/input.css" rel="stylesheet">
</head>
<body class="bg-gray-100 flex flex-col items-center justify-center min-h-screen p-4">
  <button id="adminBtn" class="absolute top-4 right-4 bg-gray-800 text-white px-4 py-2 rounded-xl hover:bg-gray-900 transition">
    Admin
  </button>
	<h1 class="text-4xl font-extrabold text-gray-800 mb-7">Pay2Browse</h1>
  <form method="POST" action="?client_request" class="bg-white p-8 rounded-2xl shadow-lg space-y-6 w-full max-w-md text-center">
    <h1 class="text-2xl font-bold">Select Access Duration</h1>
    <div class="flex items-center justify-center space-x-4">
      <input type="range" id="hourRange" min="1" max="10" value="1" class="w-2/3 accent-blue-500">
      <select name="hours" id="hourDropdown" class="border rounded px-2 py-1 text-lg">
        <?php for ($i = 1; $i <= 10; $i++): ?>
          <option value="<?= $i ?>" <?= $i === 1 ? 'selected' : '' ?>><?= $i ?> hr<?= $i !== 1 ? 's' : '' ?></option>
        <?php endfor; ?>
      </select>
    </div>
    <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded-xl hover:bg-green-700 transition">
      Confirm
    </button>
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
    <?php endif; ?>
  </form>
  <dialog id="adminDialog" class="p-0 rounded-2xl shadow-lg">
    <form method="POST" action="?admin_login" class="p-8 space-y-4 bg-white rounded-2xl">
      <h2 class="text-xl font-semibold">Admin Login</h2>
      <input
        type="password"
        name="admin_password"
        placeholder="Password"
        class="w-full border rounded px-3 py-2 focus:outline-none focus:ring"
        required
      >
      <div class="flex justify-end space-x-2">
        <button type="submit" class="px-4 py-2 rounded bg-blue-600 text-white hover:bg-blue-700 transition">
          Login
        </button>
      </div>
    </form>
  </dialog>

  <script>
    const range = document.getElementById('hourRange');
    const dropdown = document.getElementById('hourDropdown');
    dropdown.value = range.value;
    range.addEventListener('input', () => dropdown.value = range.value);
    dropdown.addEventListener('change', () => range.value = dropdown.value);

    const adminBtn = document.getElementById('adminBtn');
    const adminDialog = document.getElementById('adminDialog');

    adminBtn.addEventListener('click', () => {
      if (typeof adminDialog.showModal === 'function') {
        adminDialog.showModal();
      } else {
        alert('Your browser does not support <dialog>.');
      }
    });

    // Optional: close on backdrop click
    adminDialog.addEventListener('click', (e) => {
      const rect = adminDialog.getBoundingClientRect();
      if (
        e.clientX < rect.left ||
        e.clientX > rect.right ||
        e.clientY < rect.top ||
        e.clientY > rect.bottom
      ) {
        adminDialog.close();
      }
    });
  </script>
</body>
</html>

