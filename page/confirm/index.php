<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    die('Invalid request ID.');
}

$db = new SQLite3('/var/www/html/data/db.sqlite');
$stmt = $db->prepare('SELECT ip, duration FROM requests WHERE id = :id');
$stmt->bindValue(':id', $id, SQLITE3_INTEGER);
$res = $stmt->execute();
$request = $res->fetchArray(SQLITE3_ASSOC);
if (!$request) {
    die('Request not found.');
}

$ip         = htmlspecialchars($request['ip'],     ENT_QUOTES, 'UTF-8');
$hours      = $request['duration'] / 3600;
$approveUrl = sprintf('http://200.200.200.1/approve/?id=%d', $id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Request #<?= $id ?> Submitted</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="./favicon.png">
  <link href="../css/output.css" rel="stylesheet">
  <link href="../css/input.css"  rel="stylesheet">
</head>
<body class="bg-gray-100 flex flex-col items-center justify-center min-h-screen p-4">
  <div class="bg-white p-8 rounded-2xl shadow-lg text-center space-y-6 max-w-md w-full">
    <h1 class="text-3xl font-extrabold text-gray-800">Access Request Submitted</h1>
    <p class="text-lg text-gray-700"><strong>IP Address:</strong> <?= $ip ?></p>
    <p class="text-lg text-gray-700"><strong>Duration:</strong> <?= $hours ?> hour(s)</p>
    <p class="text-lg text-gray-700"><strong>Please pay at the counter</strong></p>


    <div class="mt-4">
      <h2 class="text-xl font-semibold text-gray-800 mb-2">Scan to Approve</h2>
      <div class="flex justify-center">
        <div id="qrcode"></div>
      </div>
    </div>

    <p class="text-sm text-gray-600">
      Or visit:<br>
      <a href="<?= $approveUrl ?>" class="text-blue-600 hover:underline break-words">
        <?= $approveUrl ?>
      </a>
    </p>
  </div>

  <!-- load your local copy of qrcode.min.js -->
  <script src="/js/qrcode.min.js"></script>
  <script>
    // after the library loads, draw into #qrcode
    new QRCode(document.getElementById("qrcode"), {
      text: "<?= $approveUrl ?>",
      width: 200,
      height: 200,
      correctLevel: QRCode.CorrectLevel.L
    });
  </script>
</body>
</html>
