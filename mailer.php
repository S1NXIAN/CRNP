<?php

/**
 * mailer.php — PHPMailer configured for Gmail SMTP (STARTTLS, port 587).
 * Exposes sendOTP($email, $otp, $purpose).
 */
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';
require_once __DIR__ . '/PHPMailer/Exception.php';

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * True when $email's domain can plausibly receive mail (has MX or A
 * records). Skips the SMTP attempt otherwise so typo and test domains
 * never burn a worker or bounce into the sender inbox. Fails open when
 * local DNS itself is down, so a resolver hiccup never blocks real mail.
 */
function is_deliverable(string $email): bool
{
    $domain = (string) substr((string) strrchr($email, '@'), 1);
    // Reject control chars anywhere (header injection into API MIME).
    if (preg_match('/[\r\n]/', $email) === 1) {
        return false;
    }
    if ($domain === '' || preg_match('/\s/', $domain) === 1) {
        return false;
    }
    if (checkdnsrr($domain, 'MX') || checkdnsrr($domain, 'A')) {
        return true;
    }
    static $dnsUp = null;
    if ($dnsUp === null) {
        $dnsUp = checkdnsrr('gmail.com', 'MX');
    }
    if (!$dnsUp) {
        return true;
    }
    error_log('[mailer] skip undeliverable domain: ' . $domain);
    return false;
}

/* ---------- Gmail API transport (HTTPS; works where SMTP is blocked) ----------
 * Render free drops outbound SMTP, so when OAuth creds are configured the
 * mail goes through gmail.users.messages.send over port 443 instead.
 * Without creds the SMTP path stays, so local dev needs zero setup.
 * New env: GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, GMAIL_REFRESH_TOKEN. */
function gmail_api_ready(): bool
{
    foreach (['GOOGLE_CLIENT_ID', 'GOOGLE_CLIENT_SECRET', 'GMAIL_REFRESH_TOKEN'] as $key) {
        $val = getenv($key);
        if ($val === false || $val === '') {
            return false;
        }
    }
    return true;
}
/**
 * OAuth2 access token from the stored refresh token, memoized per request
 * and cached on disk until a minute before expiry. Null when the exchange
 * fails (logged), letting callers fall back to SMTP.
 */
function gmail_api_token(): ?string
{
    static $memo = null;
    if (is_array($memo) && ($memo['exp'] ?? 0) > time() + 60) {
        return $memo['token'];
    }
    $cacheFile = sys_get_temp_dir() . '/crnp_gmail_token.json';
    if (is_readable($cacheFile)) {
        $cached = json_decode((string) file_get_contents($cacheFile), true);
        if (is_array($cached) && ($cached['exp'] ?? 0) > time() + 60) {
            $memo = $cached;
            return $cached['token'];
        }
    }
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'client_id'     => getenv('GOOGLE_CLIENT_ID'),
            'client_secret' => getenv('GOOGLE_CLIENT_SECRET'),
            'refresh_token' => getenv('GMAIL_REFRESH_TOKEN'),
            'grant_type'    => 'refresh_token',
        ]),
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    $resp = curl_exec($ch);
    if (curl_errno($ch)) {
        error_log('[mailer] Gmail token cURL error: ' . curl_error($ch));
        curl_close($ch);
        return null;
    }
    curl_close($ch);
    $tok = json_decode((string) $resp, true);
    if (!is_array($tok) || empty($tok['access_token'])) {
        error_log('[mailer] Gmail token exchange failed: ' . substr((string) $resp, 0, 200));
        return null;
    }
    $entry = ['token' => $tok['access_token'], 'exp' => time() + (int) ($tok['expires_in'] ?? 3600)];
    file_put_contents($cacheFile, json_encode($entry), LOCK_EX);
    chmod($cacheFile, 0600);
    $memo = $entry;
    return $entry['token'];
}
/** URL-safe base64 without padding, as the Gmail API expects. */
function gmail_b64url(string $bytes): string
{
    return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
}
/** Minimal multipart/alternative MIME for one recipient. */
function gmail_mime(string $to, string $subject, string $html, string $plain): string
{
    $boundary = 'crnp_' . bin2hex(random_bytes(8));
    return implode("\r\n", [
        'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM . '>',
        'To: <' . $to . '>',
        'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        '',
        '--' . $boundary,
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        '',
        $plain,
        '',
        '--' . $boundary,
        'Content-Type: text/html; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        '',
        $html,
        '',
        '--' . $boundary . '--',
    ]);
}
/** Send one message through the Gmail API. False on any failure (logged). */
function gmail_api_send(string $to, string $subject, string $htmlBody, string $altBody): bool
{
    $token = gmail_api_token();
    if ($token === null) {
        return false;
    }
    $raw = gmail_b64url(gmail_mime($to, $subject, $htmlBody, $altBody));
    $ch = curl_init('https://gmail.googleapis.com/gmail/v1/users/me/messages/send');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode(['raw' => $raw]),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Authorization: Bearer ' . $token],
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    $resp = curl_exec($ch);
    if (curl_errno($ch)) {
        error_log('[mailer] Gmail API cURL error: ' . curl_error($ch));
        curl_close($ch);
        return false;
    }
    curl_close($ch);
    $data = json_decode((string) $resp, true);
    if (!is_array($data) || empty($data['id'])) {
        error_log('[mailer] Gmail API send failed: ' . substr((string) $resp, 0, 200));
        return false;
    }
    return true;
}

/**
 * Send a 6-digit OTP email. $purpose is 'signup' (verify a new account) or
 * 'reset' (approve a password reset) — subject, heading, and copy differ so
 * the recipient knows which flow the code belongs to. Returns true on success.
 */
function sendOTP(string $email, string $otp, string $purpose = 'signup'): bool
{
    if (!is_deliverable($email)) {
        return false;
    }
    $isReset = $purpose === 'reset';
    $subject = $isReset ? 'Reset your ' . BRAND_NAME . ' password' : 'Confirm your ' . BRAND_NAME . ' account';
    $html  = otp_email_html($otp, $purpose);
    $plain = $subject . ': ' . $otp . "\nThis code expires in 10 minutes.";
    if (gmail_api_ready() && gmail_api_send($email, $subject, $html, $plain)) {
        return true;
    }
    $mail = new PHPMailer(true);
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = GMAIL_ADDRESS;
        $mail->Password   = GMAIL_APP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        // Fail fast: OTP callers redirect first and send in the background.
        // Never hold a worker longer than this waiting on SMTP.
        $mail->Timeout = 10;
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($email);
        $mail->addReplyTo(MAIL_FROM, MAIL_FROM_NAME);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html;
        $mail->AltBody = $plain;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('[mailer] OTP send failed to ' . $email . ': ' . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Generic mailer for receipts / notifications.
 */
function sendMail(string $to, string $subject, string $htmlBody, string $altBody = ''): bool
{
    if (!is_deliverable($to)) {
        return false;
    }
    $plain = $altBody !== '' ? $altBody : strip_tags($htmlBody);
    if (gmail_api_ready() && gmail_api_send($to, $subject, $htmlBody, $plain)) {
        return true;
    }
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = GMAIL_ADDRESS;
        $mail->Password   = GMAIL_APP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        // Bounded for the same reason as sendOTP(): receipts send off the
        // critical path and must fail fast, never hang the response.
        $mail->Timeout = 10;
        $mail->CharSet    = 'UTF-8';
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $plain;
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('[mailer] sendMail failed to ' . $to . ': ' . $mail->ErrorInfo);
        return false;
    }
}

function otp_email_html(string $otp, string $purpose = 'signup'): string
{
    $brand   = BRAND_NAME;
    $tagline = BRAND_TAGLINE;
    $isReset = $purpose === 'reset';
    $heading = $isReset ? 'Reset your password' : 'Confirm your account';
    $intro   = $isReset
        ? 'You asked to reset your password. Enter the code below to continue. It expires in 10 minutes.'
        : 'Thanks for signing up. Enter the code below to confirm your email address. It expires in 10 minutes.';
    $footer  = $isReset
        ? 'If you did not ask to reset your password, you can safely ignore this email.'
        : 'If you did not create an account, you can safely ignore this email.';
    return <<<HTML
<!doctype html><html><body style="margin:0;background:#f6f2ea;font-family:Georgia,'Times New Roman',serif;">
  <div style="max-width:520px;margin:0 auto;background:#ffffff;border:1px solid #e6dfd1;border-radius:14px;overflow:hidden;">
    <div style="background:#211b14;color:#f6f2ea;padding:28px 32px;">
      <div style="font-size:13px;letter-spacing:.22em;text-transform:uppercase;color:#c8a45c;">{$brand}</div>
      <div style="font-size:22px;margin-top:6px;">{$heading}</div>
    </div>
    <div style="padding:32px;color:#211b14;">
      <p style="margin:0 0 14px;">{$intro}</p>
      <div style="text-align:center;margin:26px 0;">
        <span style="display:inline-block;font-family:'Courier New',monospace;font-size:34px;letter-spacing:.5em;color:#211b14;background:#f6f2ea;border:1px dashed #c8a45c;border-radius:12px;padding:16px 22px 16px 28px;">{$otp}</span>
      </div>
      <p style="margin:0;color:#8a7f70;font-size:13px;">{$footer}</p>
    </div>
    <div style="background:#fbf8f2;color:#8a7f70;font-size:12px;padding:16px 32px;text-align:center;">&copy; {$brand} &middot; {$tagline}</div>
  </div>
</body></html>
HTML;
}

/**
 * Send an order confirmation / receipt email.
 *
 * $order keys: id, items (map of {name,qty,price,subtotal}), total,
 * full_name, payment_method ('gcash' | 'counter'), payment_status,
 * created_at, pickup_time (optional).
 *
 * Visually consistent with otp_email_html() — dark header, gold accent,
 * warm cream body. Returns true on success, false on failure.
 */
function sendOrderReceipt(string $email, array $order): bool
{
    $brand   = BRAND_NAME;
    $tagline = BRAND_TAGLINE;

    $orderId     = (string) ($order['id'] ?? '');
    $shortId     = $orderId !== '' ? substr($orderId, 0, 8) : '—';
    $fullName    = (string) ($order['full_name'] ?? '');
    $total       = (float) ($order['total'] ?? 0);
    $method      = (string) ($order['payment_method'] ?? 'counter');
    $payStatus   = (string) ($order['payment_status'] ?? '');
    $createdAt   = (string) ($order['created_at'] ?? '');
    $pickupTime  = (string) ($order['pickup_time'] ?? '');
    $notes       = (string) ($order['notes'] ?? '');
    $items       = is_array($order['items'] ?? null) ? $order['items'] : [];

    /* Payment line: "Payment: GCash — verifying" or "Payment: Pay at counter". */
    if ($method === 'gcash') {
        $payLine = 'GCash — ' . ($payStatus === 'paid' ? 'paid' : 'verifying');
    } else {
        $payLine = 'Pay at counter';
    }

    /* Items table rows. */
    $rowsHtml = '';
    foreach ($items as $row) {
        if (!is_array($row)) {
            continue;
        }
        $name      = htmlspecialchars((string) ($row['name'] ?? 'Item'), ENT_QUOTES, 'UTF-8');
        $qty       = (int) ($row['qty'] ?? 1);
        $price     = (float) ($row['price'] ?? 0);
        $subtotal  = (float) ($row['subtotal'] ?? ($price * $qty));
        $priceTxt    = "\u{20B1}" . number_format($price, 2);
        $subtotalTxt = "\u{20B1}" . number_format($subtotal, 2);
        $rowsHtml .= <<<HTML
          <tr>
            <td style="padding:10px 12px;border-bottom:1px solid #efe8da;color:#211b14;">{$name}</td>
            <td style="padding:10px 12px;border-bottom:1px solid #efe8da;text-align:center;color:#211b14;">{$qty}</td>
            <td style="padding:10px 12px;border-bottom:1px solid #efe8da;text-align:right;color:#211b14;">{$priceTxt}</td>
            <td style="padding:10px 12px;border-bottom:1px solid #efe8da;text-align:right;color:#211b14;font-weight:600;">{$subtotalTxt}</td>
          </tr>
HTML;
    }
    if ($rowsHtml === '') {
        $rowsHtml = '<tr><td colspan="4" style="padding:14px 12px;color:#8a7f70;text-align:center;">No items recorded.</td></tr>';
    }

    $totalTxt   = "\u{20B1}" . number_format($total, 2);
    $placedTxt  = $createdAt !== '' ? date('M j, Y g:i A', strtotime($createdAt)) : '';
    $pickupLine = $pickupTime !== ''
        ? 'Pickup time: <strong style="color:#211b14;">' . htmlspecialchars($pickupTime, ENT_QUOTES, 'UTF-8') . '</strong><br>'
        : '';
    $greeting   = $fullName !== '' ? 'Hi ' . htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') . ',' : 'Hi there,';
    $notesHtml  = $notes !== ''
        ? '<div style="margin-top:14px;padding:14px 16px;background:#fff7e0;border-left:3px solid #f0b429;border-radius:8px;font-size:14px;color:#8a5a00;">'
          . '<strong style="color:#8a5a00;">Special instructions:</strong> '
          . htmlspecialchars($notes, ENT_QUOTES, 'UTF-8') . '</div>'
        : '';

    $subject = 'Your ' . $brand . ' order #' . $shortId;

    $html = <<<HTML
<!doctype html><html><body style="margin:0;background:#f6f2ea;font-family:Georgia,'Times New Roman',serif;">
  <div style="max-width:560px;margin:0 auto;background:#ffffff;border:1px solid #e6dfd1;border-radius:14px;overflow:hidden;">
    <div style="background:#211b14;color:#f6f2ea;padding:28px 32px;">
      <div style="font-size:13px;letter-spacing:.22em;text-transform:uppercase;color:#c8a45c;">{$brand}</div>
      <div style="font-size:22px;margin-top:6px;">Order confirmation</div>
    </div>
    <div style="padding:32px;color:#211b14;">
      <p style="margin:0 0 6px;">{$greeting}</p>
      <p style="margin:0 0 18px;color:#5a4f42;">Thanks for your order! Here's your receipt.</p>

      <div style="background:#fbf8f2;border:1px solid #efe8da;border-radius:10px;padding:14px 16px;margin-bottom:22px;">
        <div style="display:flex;justify-content:space-between;font-size:13px;color:#8a7f70;margin-bottom:4px;">
          <span>Order number</span><span style="color:#211b14;font-weight:600;">#{$shortId}</span>
        </div>
        {$pickupLine}
        <div style="display:flex;justify-content:space-between;font-size:13px;color:#8a7f70;">
          <span>Placed</span><span style="color:#211b14;">{$placedTxt}</span>
        </div>
      </div>

      <table role="presentation" style="width:100%;border-collapse:collapse;font-size:14px;">
        <thead>
          <tr style="background:#211b14;color:#f6f2ea;">
            <th style="padding:12px;text-align:left;font-weight:600;letter-spacing:.04em;">Item</th>
            <th style="padding:12px;text-align:center;font-weight:600;letter-spacing:.04em;">Qty</th>
            <th style="padding:12px;text-align:right;font-weight:600;letter-spacing:.04em;">Unit</th>
            <th style="padding:12px;text-align:right;font-weight:600;letter-spacing:.04em;">Subtotal</th>
          </tr>
        </thead>
        <tbody>
          {$rowsHtml}
        </tbody>
        <tfoot>
          <tr>
            <td colspan="3" style="padding:14px 12px;text-align:right;text-transform:uppercase;font-size:12px;letter-spacing:.08em;color:#8a7f70;">Total</td>
            <td style="padding:14px 12px;text-align:right;font-size:18px;font-weight:700;color:#211b14;">{$totalTxt}</td>
          </tr>
        </tfoot>
      </table>

      <div style="margin-top:18px;padding:14px 16px;background:#fbf8f2;border-left:3px solid #c8a45c;border-radius:8px;font-size:14px;color:#211b14;">
        <strong>Payment:</strong> {$payLine}
      </div>

{$notesHtml}
      <p style="margin:18px 0 6px;color:#211b14;">Please present this confirmation at the counter.</p>
      <p style="margin:0;color:#8a7f70;font-size:13px;">We'll notify you when your order is ready for pickup.</p>
    </div>
    <div style="background:#fbf8f2;color:#8a7f70;font-size:12px;padding:16px 32px;text-align:center;">&copy; {$brand} &middot; {$tagline}</div>
  </div>
</body></html>
HTML;

    $alt = 'Your ' . $brand . ' order #' . $shortId . "\n"
         . 'Total: ' . $totalTxt . "\n"
         . 'Payment: ' . $payLine . "\n\n"
         . 'Special instructions: ' . ($notes !== '' ? $notes : 'None') . "\n\n"
         . "Please present this confirmation at the counter.\n"
         . "We'll notify you when your order is ready for pickup.";

    return sendMail($email, $subject, $html, $alt);
}

/**
 * Send a booking confirmation / receipt email.
 *
 * $booking keys: id, items (map of {name,qty,price,subtotal}), total,
 * full_name, payment_method ('gcash' | 'counter'), payment_status,
 * appointment_time, return_time, created_at, contact, address.
 */
function sendBookingReceipt(string $email, array $booking): bool
{
    $brand   = BRAND_NAME;
    $tagline = BRAND_TAGLINE;

    $bookingId = (string) ($booking['id'] ?? '');
    $shortId   = $bookingId !== '' ? substr($bookingId, 0, 8) : '—';
    $fullName  = (string) ($booking['full_name'] ?? $booking['user_name'] ?? '');
    $total     = (float) ($booking['total'] ?? 0);
    $method    = (string) ($booking['payment_method'] ?? 'counter');
    $payStatus = (string) ($booking['payment_status'] ?? '');
    $apptTime  = (string) ($booking['appointment_time'] ?? '');
    $retTime   = (string) ($booking['return_time'] ?? '');
    $contact   = (string) ($booking['contact'] ?? '');
    $address   = (string) ($booking['address'] ?? '');
    $createdAt = (string) ($booking['created_at'] ?? '');
    $notes     = (string) ($booking['notes'] ?? '');
    $items     = is_array($booking['items'] ?? null) ? $booking['items'] : [];

    if ($method === 'gcash') {
        $payLine = 'GCash — ' . ($payStatus === 'paid' ? 'paid' : 'verifying');
    } else {
        $payLine = 'Pay at counter';
    }

    $rowsHtml = '';
    foreach ($items as $row) {
        if (!is_array($row)) {
            continue;
        }
        $name      = htmlspecialchars((string) ($row['name'] ?? 'Item'), ENT_QUOTES, 'UTF-8');
        $qty       = (int) ($row['qty'] ?? 1);
        $price     = (float) ($row['price'] ?? 0);
        $subtotal  = (float) ($row['subtotal'] ?? ($price * $qty));
        $priceTxt    = "\u{20B1}" . number_format($price, 2);
        $subtotalTxt = "\u{20B1}" . number_format($subtotal, 2);
        $rowsHtml .= <<<HTML
          <tr>
            <td style="padding:10px 12px;border-bottom:1px solid #efe8da;color:#211b14;">{$name}</td>
            <td style="padding:10px 12px;border-bottom:1px solid #efe8da;text-align:center;color:#211b14;">{$qty}</td>
            <td style="padding:10px 12px;border-bottom:1px solid #efe8da;text-align:right;color:#211b14;">{$priceTxt}</td>
            <td style="padding:10px 12px;border-bottom:1px solid #efe8da;text-align:right;color:#211b14;font-weight:600;">{$subtotalTxt}</td>
          </tr>
HTML;
    }
    if ($rowsHtml === '') {
        $rowsHtml = '<tr><td colspan="4" style="padding:14px 12px;color:#8a7f70;text-align:center;">No items recorded.</td></tr>';
    }

    $totalTxt   = "\u{20B1}" . number_format($total, 2);
    $placedTxt  = $createdAt !== '' ? date('M j, Y g:i A', strtotime($createdAt)) : '';
    $apptLine   = $apptTime !== '' ? 'Appointment: <strong style="color:#211b14;">' . htmlspecialchars($apptTime, ENT_QUOTES, 'UTF-8') . '</strong><br>' : '';
    $returnLine = $retTime !== '' ? 'Return by: <strong style="color:#211b14;">' . htmlspecialchars($retTime, ENT_QUOTES, 'UTF-8') . '</strong><br>' : '';
    $greeting   = $fullName !== '' ? 'Hi ' . htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') . ',' : 'Hi there,';
    $notesHtml  = $notes !== ''
        ? '<div style="margin-top:14px;padding:14px 16px;background:#fff7e0;border-left:3px solid #f0b429;border-radius:8px;font-size:14px;color:#8a5a00;">'
          . '<strong style="color:#8a5a00;">Special instructions:</strong> '
          . htmlspecialchars($notes, ENT_QUOTES, 'UTF-8') . '</div>'
        : '';

    $subject = 'Your ' . $brand . ' booking #' . $shortId;

    $html = <<<HTML
<!doctype html><html><body style="margin:0;background:#f6f2ea;font-family:Georgia,'Times New Roman',serif;">
  <div style="max-width:560px;margin:0 auto;background:#ffffff;border:1px solid #e6dfd1;border-radius:14px;overflow:hidden;">
    <div style="background:#211b14;color:#f6f2ea;padding:28px 32px;">
      <div style="font-size:13px;letter-spacing:.22em;text-transform:uppercase;color:#c8a45c;">{$brand}</div>
      <div style="font-size:22px;margin-top:6px;">Booking confirmation</div>
    </div>
    <div style="padding:32px;color:#211b14;">
      <p style="margin:0 0 6px;">{$greeting}</p>
      <p style="margin:0 0 18px;color:#5a4f42;">Thanks for your rental booking! Here's your receipt.</p>

      <div style="background:#fbf8f2;border:1px solid #efe8da;border-radius:10px;padding:14px 16px;margin-bottom:22px;">
        <div style="display:flex;justify-content:space-between;font-size:13px;color:#8a7f70;margin-bottom:4px;">
          <span>Booking number</span><span style="color:#211b14;font-weight:600;">#{$shortId}</span>
        </div>
        {$apptLine}
        {$returnLine}
        <div style="display:flex;justify-content:space-between;font-size:13px;color:#8a7f70;">
          <span>Booked</span><span style="color:#211b14;">{$placedTxt}</span>
        </div>
      </div>

      <table role="presentation" style="width:100%;border-collapse:collapse;font-size:14px;">
        <thead>
          <tr style="background:#211b14;color:#f6f2ea;">
            <th style="padding:12px;text-align:left;font-weight:600;letter-spacing:.04em;">Item</th>
            <th style="padding:12px;text-align:center;font-weight:600;letter-spacing:.04em;">Qty</th>
            <th style="padding:12px;text-align:right;font-weight:600;letter-spacing:.04em;">Unit</th>
            <th style="padding:12px;text-align:right;font-weight:600;letter-spacing:.04em;">Subtotal</th>
          </tr>
        </thead>
        <tbody>
          {$rowsHtml}
        </tbody>
        <tfoot>
          <tr>
            <td colspan="3" style="padding:14px 12px;text-align:right;text-transform:uppercase;font-size:12px;letter-spacing:.08em;color:#8a7f70;">Total</td>
            <td style="padding:14px 12px;text-align:right;font-size:18px;font-weight:700;color:#211b14;">{$totalTxt}</td>
          </tr>
        </tfoot>
      </table>

      <div style="margin-top:18px;padding:14px 16px;background:#fbf8f2;border-left:3px solid #c8a45c;border-radius:8px;font-size:14px;color:#211b14;">
        <strong>Payment:</strong> {$payLine}
      </div>

{$notesHtml}
      <p style="margin:18px 0 6px;color:#211b14;">Please present this confirmation when picking up your rental items.</p>
      <p style="margin:0;color:#8a7f70;font-size:13px;">We will confirm your booking shortly.</p>
    </div>
    <div style="background:#fbf8f2;color:#8a7f70;font-size:12px;padding:16px 32px;text-align:center;">&copy; {$brand} &middot; {$tagline}</div>
  </div>
</body></html>
HTML;

    $alt = 'Your ' . $brand . ' booking #' . $shortId . "\n"
         . 'Total: ' . $totalTxt . "\n"
         . 'Payment: ' . $payLine . "\n\n"
         . 'Special instructions: ' . ($notes !== '' ? $notes : 'None') . "\n\n"
         . "Please present this confirmation when picking up your rental items.\n"
         . 'We will confirm your booking shortly.';

    return sendMail($email, $subject, $html, $alt);
}
