<?php

/**
 * otp_resend.php — offline checks for otp_resend_wait(), the pure seam
 * behind the OTP resend cooldown.
 *
 * Rule: a new code issues only after the live one dies, so two codes never
 * overlap. Run: php tests/otp_resend.php (no network, no credentials).
 */
declare(strict_types=1);

require __DIR__ . '/../includes/functions.php';

require_once __DIR__ . '/helpers.php';

$live    = ['otp_expires' => date('Y-m-d H:i:s', time() + 600)];
$expired = ['otp_expires' => date('Y-m-d H:i:s', time() - 1)];

$wait = otp_resend_wait($live, 'otp_expires');
check('live code blocks resend ~full TTL', $wait > 590 && $wait <= 600);
check('expired code opens resend', otp_resend_wait($expired, 'otp_expires') === 0);
check('missing field opens resend', otp_resend_wait([], 'otp_expires') === 0);
check('null user opens resend', otp_resend_wait(null, 'otp_expires') === 0);
check('garbage date opens resend', otp_resend_wait(['otp_expires' => 'not-a-date'], 'otp_expires') === 0);
check('reset fields work too', otp_resend_wait(['reset_otp_expires' => date('Y-m-d H:i:s', time() + 300)], 'reset_otp_expires') > 290);

exit($fails === 0 ? 0 : 1);
