<?php
$debug   = [];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $hours = intval($_POST['hours'] ?? 1);
    $ip    = $_SERVER['REMOTE_ADDR'];
    $debug[] = "Granting $ip for {$hours} hour(s)";

    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        $message = "Invalid IP address.";
    } else {
        // Calculate TTL in seconds
        $ttl = $hours * 3600;

        // Add this client to the 'allowed' set with its own timeout
        $cmd = sprintf(
            'sudo /usr/sbin/ipset add allowed %s timeout %d',
            escapeshellarg($ip),
            $ttl
        );

        exec($cmd . ' 2>>/tmp/ipset.log', $output, $status);
        $debug[] = "Command: $cmd";
        $debug[] = "Exit status: $status";

        if ($status === 0) {
            $message = "Access granted for {$hours} hour(s).";
        } else {
            $message = "Failed to grant access—see /tmp/ipset.log.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Pay2Browse Portal</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen p-4">
  <form method="POST" class="bg-white p-8 rounded-2xl shadow-lg space-y-6 w-full max-w-md text-center">
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
  <script>
    const range = document.getElementById('hourRange');
    const dropdown = document.getElementById('hourDropdown');
    dropdown.value = range.value;
    range.addEventListener('input', () => dropdown.value = range.value);
    dropdown.addEventListener('change', () => range.value = dropdown.value);
  </script>
</body>
</html>

