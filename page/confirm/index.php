<?php
$lastOctet = isset($_GET['ip']) ? intval($_GET['ip']) : 0;
$duration  = isset($_GET['dur']) ? intval($_GET['dur']) : 0;

if ($lastOctet <= 0 || $lastOctet > 255 || $duration <= 0) {
    die('Invalid parameters.');
}

$ipPrefix  = '200.200.200.';
$clientIp  = $ipPrefix . $lastOctet;
$hours     = round($duration / 3600, 2); 

if (!filter_var($clientIp, FILTER_VALIDATE_IP)) {
    die('Malformed client IP.');
}

// Check if IP is already in the "allowed" ipset and get remaining time
$ipAllowed = false;
$remaining = 0;

exec('sudo /usr/sbin/ipset list allowed -o save 2>/dev/null', $output, $status);
foreach ($output as $line) {
    if (preg_match('/^add allowed\s+' . preg_quote($clientIp, '/') . '\s+timeout\s+(\d+)/', $line, $m)) {
        $ipAllowed = true;
        $remaining = (int)$m[1];
        break;
    }
}

$isExtension = $ipAllowed;

// Build the approval URL
$approveUrl = sprintf(
    'http://200.200.200.1/approve/?ip=%d&dur=%d',
    $lastOctet,
    $duration
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= $isExtension ? 'Extension' : 'Request' ?> for <?= htmlspecialchars($clientIp) ?> (<?= $hours ?>h)</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="./favicon.png">
  <link href="../css/output.css" rel="stylesheet">
  <link href="../css/input.css"  rel="stylesheet">
</head>
<body class="bg-gray-100 flex flex-col items-center justify-center min-h-screen p-4">
  <div class="bg-white p-8 rounded-2xl shadow-lg text-center space-y-6 max-w-md w-full">
    <h1 class="text-3xl font-extrabold text-gray-800">
      <?= $isExtension ? 'Extension Request' : 'Internet Access Request' ?>
    </h1>

    <p class="text-lg text-gray-700"><strong>IP Address:</strong> <?= htmlspecialchars($clientIp) ?></p>
    <p class="text-lg text-gray-700"><strong>Duration:</strong> <?= $hours ?> hour<?= $hours != 1 ? 's' : '' ?></p>
    <p class="text-lg text-gray-700">
      <strong>Please pay at the counter to complete your <?= $isExtension ? 'extension' : 'request' ?>.</strong>
    </p>

    <div class="mt-4">
      <h2 class="text-xl font-semibold text-gray-800 mb-2">Scan to <?= $isExtension ? 'Extend' : 'Approve' ?></h2>
      <div class="flex justify-center">
        <div id="qrcode"></div>
      </div>
    </div>

    <p class="text-sm text-gray-600">
      Or visit:<br>
      <a href="<?= htmlspecialchars($approveUrl) ?>" class="text-blue-600 hover:underline break-words">
        <?= htmlspecialchars($approveUrl) ?>
      </a>
    </p>
  </div>

  <script src="/js/qrcode.min.js"></script>
  <script>
    new QRCode(document.getElementById("qrcode"), {
      text: "<?= addslashes($approveUrl) ?>",
      width: 200,
      height: 200,
      correctLevel: QRCode.CorrectLevel.L
    });
  </script>
</body>
</html>
