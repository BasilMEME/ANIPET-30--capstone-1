<?php
require_once __DIR__ . '/db_connect.php';

$application_id = isset($_GET['application_id']) ? (int)$_GET['application_id'] : 0;
if ($application_id <= 0) {
    echo "<p>Missing application_id. Provide ?application_id=123</p>";
    exit;
}

$query = $conn->prepare("\
    SELECT aa.*, p.name as pet_name, p.image as pet_image, u.full_name as user_name, u.email as user_email\
    FROM adoption_applications aa\
    JOIN pets p ON aa.pet_id = p.id\
    JOIN users u ON aa.user_id = u.id\
    WHERE aa.id = ?\
");
$query->bind_param('i', $application_id);
$query->execute();
$res = $query->get_result();
if ($res->num_rows === 0) {
    echo "<p>Application not found</p>";
    exit;
}
$app = $res->fetch_assoc();

$statuses = ['pending','screening','approved','for_releasing','ready_pickup','completed'];
$current_index = array_search($app['status'], $statuses);
$completed_steps = $current_index !== false ? $current_index + 1 : 0;

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <title>Application Status #<?php echo $app['id']; ?></title>
    <style>
        body{font-family:Arial,Helvetica,sans-serif;padding:18px;background:#f6f8fa}
        .card{background:#fff;padding:18px;border-radius:8px;max-width:760px;margin:0 auto;box-shadow:0 6px 18px rgba(0,0,0,.06)}
        .muted{color:#666}
        .qr{max-width:220px}
        .progress{display:flex;gap:8px;margin-top:12px}
        .step{flex:1;padding:8px;border-radius:6px;background:#eee;text-align:center}
        .step.done{background:#cfead7}
    </style>
</head>
<body>
<div class="card">
    <h2>Application #<?php echo $app['id']; ?> — <?php echo htmlspecialchars($app['applicant_name']); ?></h2>
    <p class="muted">Pet: <?php echo htmlspecialchars($app['pet_name']); ?></p>
    <p>Status: <strong><?php echo htmlspecialchars(ucfirst($app['status'])); ?></strong></p>

    <div class="progress">
        <?php foreach ($statuses as $i => $s):
            $cls = ($i <= $current_index) ? 'step done' : 'step';
        ?>
            <div class="<?php echo $cls; ?>"><?php echo htmlspecialchars(ucfirst($s)); ?></div>
        <?php endforeach; ?>
    </div>

    <?php if (!empty($app['qr_code'])): ?>
        <h3>QR for tracking</h3>
        <p class="muted">Show this QR when visiting the shelter or for pickup tracking.</p>
        <img class="qr" src="<?php echo htmlspecialchars($app['qr_code']); ?>" alt="QR code" />
    <?php else: ?>
        <p class="muted">QR will appear here once your application is approved.</p>
    <?php endif; ?>

    <h3>Application details</h3>
    <p><strong>Submitted:</strong> <?php echo htmlspecialchars($app['created_at']); ?></p>
    <?php if (!empty($app['form_data'])): $fd = json_decode($app['form_data'], true); ?>
        <h4>Questionnaire</h4>
        <ul>
        <?php foreach ($fd as $k=>$v): ?>
            <li><strong><?php echo htmlspecialchars($k); ?>:</strong> <?php echo htmlspecialchars(is_array($v)?json_encode($v):$v); ?></li>
        <?php endforeach; ?>
        </ul>
    <?php endif; ?>

</div>
</body>
</html>
