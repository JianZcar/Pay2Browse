<?php
session_start();
$db = new SQLite3('/var/www/html/data/db.sqlite');
$ip = $_SERVER['REMOTE_ADDR'];
$parts = explode('.', $ip);
$lastOctet = intval($parts[3] ?? 0);
$ipAllowed = false;
$remaining = 0;

exec('sudo /usr/sbin/ipset list allowed -o save 2>/dev/null', $output, $status);
foreach ($output as $line) {
    if (preg_match('/^add allowed\s+' . preg_quote($ip, '/') . '\s+timeout\s+(\d+)/', $line, $m)) {
        $ipAllowed = true;
        $remaining = (int)$m[1];
        break;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_GET['admin_login'])) {
        $pwd = $_POST['admin_password'] ?? '';
        $stmt = $db->prepare('SELECT COUNT(*) AS cnt FROM admin WHERE password = :pwd');
        $stmt->bindValue(':pwd', $pwd, SQLITE3_TEXT);
        $res = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

        if ($res['cnt'] > 0) {
            $_SESSION['admin_logged_in'] = true;
            if (!$ipAllowed) {
                $cmd = sprintf('sudo /usr/sbin/ipset add allowed %s timeout 86400', escapeshellarg($ip));
                exec($cmd);
                $ipAllowed = true;
                $remaining = 86400;
            }
        } else {
            // Store the error message in the session
            $_SESSION['error_message'] = "Invalid admin password.";
        }
    }

    if (isset($_GET['client_request'])) {
        $hours = intval($_POST['hours'] ?? 1);
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            $duration = $hours * 3600;
            header("Location: confirm/?ip={$lastOctet}&dur={$duration}");
            exit();
        }
    }
    header("Location: ./");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Pay2Browse Portal</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" href="./favicon.png">
  <link href="./css/output.css" rel="stylesheet">
  <link href="./css/input.css" rel="stylesheet">
</head>
<body class="bg-gray-100 flex flex-col items-center justify-center min-h-screen p-4">
  <button id="adminBtn" class="absolute top-4 right-4 bg-gray-800 text-white px-4 py-2 rounded-xl hover:bg-gray-900 transition">Admin</button>
  <h1 class="text-4xl font-extrabold text-gray-800 mb-7">Pay2Browse</h1>
  <form method="POST" action="?client_request" class="bg-white p-8 rounded-2xl shadow-lg space-y-6 w-full max-w-md text-center">
    <?php if ($ipAllowed): ?>
      <h2 class="text-gray-700 text-xl">Time remaining: <strong id="timeLeft"><?= gmdate('H:i:s', $remaining) ?></strong></h2>
      <p class="text-gray-500 mb-2">Extend your session below:</p>
    <?php else: ?>
      <h1 class="text-2xl font-bold">Select Access Duration</h1>
    <?php endif; ?>
    <div class="flex items-center justify-center space-x-4">
      <input type="range" id="hourRange" min="1" max="10" value="1" class="w-2/3 accent-blue-500">
      <select name="hours" id="hourDropdown" class="border rounded px-2 py-1 text-lg">
        <?php for ($i = 1; $i <= 10; $i++): ?>
          <option value="<?= $i ?>" <?= $i === 1 ? 'selected' : '' ?>><?= $i ?> hr<?= $i !== 1 ? 's' : '' ?></option>
        <?php endfor; ?>
      </select>
    </div>
    <button type="submit" class="bg-<?= $ipAllowed ? 'blue' : 'green' ?>-600 text-white px-6 py-2 rounded-xl hover:bg-<?= $ipAllowed ? 'blue' : 'green' ?>-700 transition">
      <?= $ipAllowed ? 'Extend' : 'Confirm' ?>
    </button>
  </form>

  <dialog id="adminDialog" class="p-0 rounded-2xl shadow-lg">
    <form method="POST" action="?admin_login" class="p-8 space-y-4 bg-white rounded-2xl">
      <h2 class="text-xl font-semibold">Admin Login</h2>
      <input type="password" name="admin_password" placeholder="Password" class="w-full border rounded px-3 py-2" required>
      <div class="flex justify-end space-x-2">
        <button type="submit" class="px-4 py-2 rounded bg-blue-600 text-white hover:bg-blue-700 transition">Login</button>
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

    adminDialog.addEventListener('click', (e) => {
      const rect = adminDialog.getBoundingClientRect();
      if (e.clientX < rect.left || e.clientX > rect.right || e.clientY < rect.top || e.clientY > rect.bottom) {
        adminDialog.close();
      }
    });

    // Check if the error message exists in the session
    <?php if (isset($_SESSION['error_message'])): ?>
      alert("<?= $_SESSION['error_message'] ?>");
      // Remove the error message from the session after displaying it
      <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <?php if ($ipAllowed): ?>
    let timeLeft = <?= $remaining ?>;
    const timeElem = document.getElementById('timeLeft');
    setInterval(() => {
      if (timeLeft > 0) {
        timeLeft--;
        const hrs = String(Math.floor(timeLeft / 3600)).padStart(2, '0');
        const mins = String(Math.floor((timeLeft % 3600) / 60)).padStart(2, '0');
        const secs = String(timeLeft % 60).padStart(2, '0');
        timeElem.textContent = `${hrs}:${mins}:${secs}`;
      }
    }, 1000);
    <?php endif; ?>
  </script>
</body>
</html>
