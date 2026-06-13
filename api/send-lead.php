<?php
// PrintFlow lead notification — emails Brad + sends demo link to the lead.
// Same-origin endpoint; uses PHP mail() on Hostinger.

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

function clean_line($v) {
    return trim(str_replace(["\r", "\n", "%0a", "%0d"], '', (string)$v));
}

$name   = clean_line(htmlspecialchars(strip_tags($data['name'] ?? '')));
$shop   = clean_line(htmlspecialchars(strip_tags($data['shop'] ?? '')));
$email  = filter_var(clean_line($data['email'] ?? ''), FILTER_SANITIZE_EMAIL);
$phone  = clean_line(htmlspecialchars(strip_tags($data['phone'] ?? '')));
$plan   = clean_line(htmlspecialchars(strip_tags($data['plan'] ?? '')));
$source = ($data['source'] ?? '') === 'demo_gate' ? 'Demo request' : 'Trial signup';

if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing or invalid fields']);
    exit();
}

$demo_url   = 'https://printflow.leasuredigital.com/demo/';
$brad_email = 'contact@leasuredigital.com';
$submitted  = date('Y-m-d H:i:s T');
$first_name = explode(' ', $name)[0];

// ── 1. Notification to Brad ───────────────────────────────────────────────
$brad_subject = "PrintFlow lead — $source: $name" . ($shop !== '' ? " ($shop)" : '');
$brad_headers = implode("\r\n", [
    'MIME-Version: 1.0',
    'Content-type: text/html; charset=UTF-8',
    'From: PrintFlow Leads <contact@leasuredigital.com>',
    'Reply-To: ' . $name . ' <' . $email . '>',
]);
$brad_body = "
<!DOCTYPE html><html><head><meta charset='utf-8'>
<style>
  body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
  .header { background: #9D025B; color: white; padding: 20px; border-radius: 8px 8px 0 0; }
  .content { background: #f8f9fa; padding: 20px; border-radius: 0 0 8px 8px; }
  .label { font-weight: bold; color: #9D025B; }
</style></head><body>
  <div class='header'>
    <h2 style='margin:0'>New PrintFlow Lead — $source</h2>
    <p style='margin:4px 0 0'><strong>$name</strong>" . ($shop !== '' ? " &middot; $shop" : '') . "</p>
  </div>
  <div class='content'>
    <p><span class='label'>Name:</span> $name</p>
    " . ($shop !== '' ? "<p><span class='label'>Shop:</span> $shop</p>" : '') . "
    <p><span class='label'>Email:</span> $email</p>
    " . ($phone !== '' ? "<p><span class='label'>Phone:</span> $phone</p>" : '') . "
    " . ($plan !== '' ? "<p><span class='label'>Plan:</span> $plan</p>" : '') . "
    <p><span class='label'>Source:</span> $source</p>
    <p><span class='label'>Submitted:</span> $submitted</p>
    <p><span class='label'>Demo link sent to:</span> $email</p>
    <p style='color:#666;font-size:13px'>Also saved to Supabase &rarr; Leasure Digital project &rarr; printflow_leads.</p>
  </div>
</body></html>";

mail($brad_email, $brad_subject, $brad_body, $brad_headers);

// ── 2. Demo link email to the lead ────────────────────────────────────────
$lead_subject = 'Your PrintFlow demo link';
$lead_headers = implode("\r\n", [
    'MIME-Version: 1.0',
    'Content-type: text/html; charset=UTF-8',
    'From: PrintFlow <contact@leasuredigital.com>',
    'Reply-To: Brad Leasure <contact@leasuredigital.com>',
]);
$lead_body = "
<!DOCTYPE html><html><head><meta charset='utf-8'>
<style>
  body { font-family: Arial, sans-serif; line-height: 1.6; color: #1a1a1a; background: #f4f4f4; margin: 0; padding: 0; }
  .wrap { max-width: 520px; margin: 40px auto; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,0.08); }
  .header { background: linear-gradient(135deg, #7A0247, #C9037A); padding: 32px 36px; }
  .header h1 { color: #fff; margin: 0; font-size: 22px; font-weight: 700; letter-spacing: -0.3px; }
  .header p { color: rgba(255,255,255,0.8); margin: 6px 0 0; font-size: 14px; }
  .body { padding: 32px 36px; }
  .body p { margin: 0 0 16px; color: #444; font-size: 15px; }
  .cta { display: block; margin: 24px 0; text-align: center; }
  .cta a { background: #C9037A; color: #fff; text-decoration: none; padding: 14px 36px; border-radius: 50px; font-weight: 700; font-size: 16px; display: inline-block; }
  .footer { padding: 20px 36px; border-top: 1px solid #eee; font-size: 12px; color: #999; }
</style></head><body>
  <div class='wrap'>
    <div class='header'>
      <h1>PrintFlow</h1>
      <p>Run your whole print shop from one screen.</p>
    </div>
    <div class='body'>
      <p>Hey $first_name,</p>
      <p>Here&rsquo;s your demo link &mdash; it&rsquo;s loaded with realistic shop data so you can click through everything: quotes, production board, invoices, AI assistant.</p>
      <div class='cta'><a href='$demo_url'>Launch the Live Demo &rarr;</a></div>
      <p>If the button doesn&rsquo;t work, copy and paste this link:<br>
        <a href='$demo_url' style='color:#C9037A;word-break:break-all'>$demo_url</a>
      </p>
      <p>Any questions, just reply to this email.<br>&mdash; Brad, Leasure Digital</p>
    </div>
    <div class='footer'>
      You requested a PrintFlow demo at printflow.leasuredigital.com.
    </div>
  </div>
</body></html>";

$sent = mail($email, $lead_subject, $lead_body, $lead_headers);

if ($sent) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'mail() failed']);
}
?>
