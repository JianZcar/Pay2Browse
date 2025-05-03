<?php
session_start();

if (empty($_SESSION['admin_logged_in'])) {
    header('Location: ../');
    exit;
}

$lastOctet = isset($_GET['ip']) ? intval($_GET['ip']) : -1;
$duration  = isset($_GET['dur']) ? intval($_GET['dur']) : 0;

if ($lastOctet < 0 || $lastOctet > 255 || $duration <= 0) {
    die('Invalid parameters.');
}

$prefix   = '200.200.200.';
$clientIp = $prefix . $lastOctet;

if (!filter_var($clientIp, FILTER_VALIDATE_IP)) {
    die('Malformed client IP.');
}

$isExtension = false;
$existingTime = 0;

// Check if the IP is already in the set with a timeout
exec('sudo /usr/sbin/ipset list allowed -o save 2>/dev/null', $output, $status);
foreach ($output as $line) {
    if (preg_match('/^add allowed\s+' . preg_quote($clientIp, '/') . '\s+timeout\s+(\d+)/', $line, $m)) {
        $existingTime = (int)$m[1];
        $isExtension = true;
        break;
    }
}

$isSubmitted = false; // To track if form was submitted

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $finalTimeout = $isExtension ? $existingTime + $duration : $duration;

    // Re-add IP with new timeout (it will update if already present)
    $cmd = sprintf(
        'sudo /usr/sbin/ipset add allowed %s timeout %d -exist',
        escapeshellarg($clientIp),
        $finalTimeout
    );
    exec($cmd . ' 2>/dev/null', $output, $status);

    $isSubmitted = true;
}
?>
<?php
session_start();

if (empty($_SESSION['admin_logged_in'])) {
    header('Location: ../');
    exit;
}

$lastOctet = isset($_GET['ip']) ? intval($_GET['ip']) : -1;
$duration  = isset($_GET['dur']) ? intval($_GET['dur']) : 0;

if ($lastOctet < 0 || $lastOctet > 255 || $duration <= 0) {
    die('Invalid parameters.');
}

$prefix   = '200.200.200.';
$clientIp = $prefix . $lastOctet;

if (!filter_var($clientIp, FILTER_VALIDATE_IP)) {
    die('Malformed client IP.');
}

$isExtension = false;
$existingTime = 0;

exec('sudo /usr/sbin/ipset list allowed -o save 2>/dev/null', $output, $status);
foreach ($output as $line) {
    if (preg_match('/^add allowed\s+' . preg_quote($clientIp, '/') . '\s+timeout\s+(\d+)/', $line, $m)) {
        $existingTime = (int)$m[1];
        $isExtension = true;
        break;
    }
}

$isSubmitted = false; // To track if form was submitted

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $finalTimeout = $isExtension ? $existingTime + $duration : $duration;

    // Re-add IP with new timeout (it will update if already present)
    $cmd = sprintf(
        'sudo /usr/sbin/ipset add allowed %s timeout %d -exist',
        escapeshellarg($clientIp),
        $finalTimeout
    );
    exec($cmd . ' 2>/dev/null', $output, $status);

    $isSubmitted = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= $isExtension ? 'Approve Extension' : 'Approve Access' ?> <?= htmlspecialchars($clientIp) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" href="../favicon.png">
  <link href="../css/output.css" rel="stylesheet">
  <link href="../css/input.css" rel="stylesheet">
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen p-4">
  <div class="bg-white p-8 rounded-2xl shadow-lg w-full max-w-md space-y-6 text-center">
    <h1 class="text-3xl font-extrabold text-gray-800"><?= $isExtension ? 'Approve Extension' : 'Approve Access' ?></h1>
    <p class="text-lg text-gray-700">
      <strong>Client IP:</strong> <?= htmlspecialchars($clientIp) ?>
    </p>
    <p class="text-lg text-gray-700">
      <strong>Duration:</strong> <?= round($duration / 3600, 2) ?> hour<?= $duration !== 3600 ? 's' : '' ?>
    </p>
    
    <form method="post">
      <button
        type="submit"
        class="w-full px-4 py-2 bg-blue-600 text-white rounded-2xl hover:bg-blue-700 transition"
        <?= $isSubmitted ? 'disabled' : '' ?>
      >
        <?= $isSubmitted ? 'Success' : ($isExtension ? 'Extend' : 'Approve') ?>
      </button>
    </form>

    <?php if ($isSubmitted): ?>
    <button onclick="window.location.href='../';" class="w-full px-4 py-2 bg-blue-600 text-white rounded-2xl hover:bg-blue-700 transition">
      Go Back
    </button>
    <?php endif; ?>
  </div>
</body>
</html>
