<?php
// PrintFlow lead notification — emails each lead to Leasure Digital.
// Same-origin endpoint; uses the host's PHP mail() like the LD contact form.

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit();
}

// Strip header-injection characters from anything that could touch headers.
function clean_line($v) {
    return trim(str_replace(["\r", "\n", "%0a", "%0d"], '', (string)$v));
}

$name   = clean_line(htmlspecialchars(strip_tags($data['name'] ?? '')));
$shop   = clean_line(htmlspecialchars(strip_tags($data['shop'] ?? '')));
$email  = filter_var(clean_line($data['email'] ?? ''), FILTER_SANITIZE_EMAIL);
$phone  = clean_line(htmlspecialchars(strip_tags($data['phone'] ?? '')));
$plan   = clean_line(htmlspecialchars(strip_tags($data['plan'] ?? '')));
$source = ($data['source'] ?? '') === 'demo_gate' ? 'Demo unlock' : 'Trial signup';

if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing or invalid fields']);
    exit();
}

$to      = 'contact@leasuredigital.com';
$subject = "PrintFlow lead — $source: $name" . ($shop !== '' ? " ($shop)" : '');

$headers = [
    'MIME-Version: 1.0',
    'Content-type: text/html; charset=UTF-8',
    'From: PrintFlow Leads <contact@leasuredigital.com>',
    'Reply-To: ' . $name . ' <' . $email . '>',
];

$submitted = date('Y-m-d H:i:s T');
$body = "
<!DOCTYPE html>
<html>
<head><meta charset='utf-8'><title>PrintFlow Lead</title>
<style>
  body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
  .header { background: #9D025B; color: white; padding: 20px; border-radius: 8px 8px 0 0; }
  .content { background: #f8f9fa; padding: 20px; border-radius: 0 0 8px 8px; }
  .label { font-weight: bold; color: #9D025B; }
</style></head>
<body>
  <div class='header'>
    <h2>New PrintFlow Lead — $source</h2>
    <p><strong>$name</strong>" . ($shop !== '' ? " · $shop" : '') . "</p>
  </div>
  <div class='content'>
    <p><span class='label'>Name:</span> $name</p>
    " . ($shop !== '' ? "<p><span class='label'>Shop:</span> $shop</p>" : '') . "
    <p><span class='label'>Email:</span> $email</p>
    " . ($phone !== '' ? "<p><span class='label'>Phone:</span> $phone</p>" : '') . "
    " . ($plan !== '' ? "<p><span class='label'>Plan:</span> $plan</p>" : '') . "
    <p><span class='label'>Source:</span> $source</p>
    <p><span class='label'>Submitted:</span> $submitted</p>
    <p style='color:#666;font-size:13px'>Also saved to Supabase → Leasure Digital project → printflow_leads.</p>
  </div>
</body>
</html>";

$sent = mail($to, $subject, $body, implode("\r\n", $headers));

if ($sent) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'mail() failed']);
}
?>
