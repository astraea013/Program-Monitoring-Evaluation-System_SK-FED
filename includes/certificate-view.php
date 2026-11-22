<?php
/**
 * Printable e-certificate. Expects $cert from cert_by_id(). The certificate
 * certifies participation only, not performance.
 */
function cert_by_id(int $id): ?array
{
    return q_one('SELECT c.*, yp.full_name, yp.date_encoded, yp.barangay_id AS member_barangay, a.title AS activity_title, a.activity_date, a.venue,
                         p.title AS program_title, p.barangay_id AS program_barangay, b.barangay_name
                    FROM certificates c JOIN youth_profiles yp ON yp.profile_id = c.profile_id
                    JOIN activities a ON a.activity_id = c.activity_id JOIN programs p ON p.program_id = a.program_id
                    JOIN barangays b ON b.barangay_id = p.barangay_id WHERE c.certificate_id = ?', [$id]);
}

function render_certificate(array $cert): void
{ ?>
  <div class="certificate">
    <img src="<?= e(asset('img/sk-federation-logo.png')) ?>" alt="SK Federation Nasipit logo">
    <div class="cert-kicker" style="margin-top:8px">Republic of the Philippines</div>
    <div class="cert-kicker">Sangguniang Kabataan Federation · <?= e(setting('municipality', 'Nasipit, Agusan del Norte')) ?></div>
    <div class="cert-title">Certificate of Cooperation</div>
    <div class="cert-kicker">This certifies that</div>
    <div class="cert-name"><?= e($cert['full_name']) ?></div>
    <div class="mono" style="font-size:11px;color:#5A6478"><?= e(kk_id((int) $cert['profile_id'], $cert['date_encoded'])) ?></div>
    <div style="margin-top:16px;font-size:13px;color:#5A6478">participated in</div>
    <div class="cert-activity"><?= e($cert['activity_title']) ?></div>
    <div style="font-size:12px;color:#5A6478"><?= e($cert['program_title']) ?> · SK <?= e($cert['barangay_name']) ?></div>
    <div style="font-size:12px;color:#5A6478;margin-top:2px">held on <?= e(fmt_date($cert['activity_date'], 'F j, Y')) ?><?= $cert['venue'] ? ' at ' . e($cert['venue']) : '' ?></div>
    <div style="margin-top:26px" class="cert-kicker">Verification Code</div>
    <div class="cert-code"><?= e($cert['certificate_no']) ?></div>
    <div style="font-size:10px;color:#5A6478;margin-top:4px">Issued <?= e(fmt_datetime($cert['date_issued'])) ?> upon submission of the activity evaluation. This certifies participation only.</div>
  </div>
<?php }
