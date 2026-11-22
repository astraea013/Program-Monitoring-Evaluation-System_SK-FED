<?php
/**
 * Role-aware grouped navigation. The absences matter: the federation has
 * no Inventory and no QR Attendance, and KK members have no Budget.
 * Hiding a link is not the access control — require_role() is.
 */

function nav_icon(string $name): string
{
    static $paths = [
        'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
        'people' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'programs' => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2Z"/><path d="M8 7h8M8 11h6"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/><path d="M8 14h.01M12 14h.01M16 14h.01M8 17h.01M12 17h.01"/>',
        'participation' => '<circle cx="12" cy="8" r="4"/><path d="M5.5 21a6.5 6.5 0 0 1 13 0M19 8l1.5 1.5L23 7"/>',
        'documents' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6M8 13h8M8 17h8"/>',
        'content' => '<path d="M4 4h16v13H7l-3 3Z"/><path d="M8 8h8M8 12h5"/>',
        'reports' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'qr' => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><path d="M14 14h3v3h-3zM18 18h3v3h-3zM18 14h3M14 18v3"/>',
        'inventory' => '<path d="m3 7 9-4 9 4-9 4Z"/><path d="m3 7 9 4 9-4v10l-9 4-9-4Z"/><path d="M12 11v10"/>',
        'budget' => '<rect x="3" y="5" width="18" height="15" rx="2"/><path d="M16 9h5v7h-5a3.5 3.5 0 0 1 0-7ZM7 5V3h10v2"/>',
        'verify' => '<path d="M20 6 9 17l-5-5"/>',
        'certificate' => '<circle cx="12" cy="8" r="5"/><path d="m8.5 12-1 9 4.5-2 4.5 2-1-9"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M2 12h3M19 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1"/>',
        'logs' => '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5M12 7v5l3 2"/>',
        'announcements' => '<path d="m3 11 18-5v12L3 14Z"/><path d="m11.6 16.4.9 4.6H8l-1.4-6"/>',
        'feedback' => '<path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4Z"/><path d="M8 9h8M8 13h5"/>',
        'scan' => '<path d="M3 7V4a1 1 0 0 1 1-1h3M17 3h3a1 1 0 0 1 1 1v3M21 17v3a1 1 0 0 1-1 1h-3M7 21H4a1 1 0 0 1-1-1v-3M7 12h10"/>',
        'bell' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/>',
    ];
    return '<svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? '') . '</svg>';
}

function nav_groups(int $role): array
{
    switch ($role) {
        case ROLE_ADMIN:
            return [
                ['', [['dashboard', 'Dashboard', 'admin/index.php', 'dashboard'],
                      ['users', 'Users & Roles', 'admin/users.php', 'people'],
                      ['barangays', 'Barangays & Settings', 'admin/barangays.php', 'settings'],
                      ['logs', 'System Logs', 'admin/logs.php', 'logs']]],
            ];
        case ROLE_FEDERATION:
            return [
                ['', [['dashboard', 'Dashboard', 'federation/index.php', 'dashboard']]],
                ['Monitoring', [['youth', 'Youth Profiling', 'federation/youth.php', 'people'],
                                ['programs', 'Programs', 'federation/programs.php', 'programs'],
                                ['calendar', 'Calendar', 'federation/calendar.php', 'calendar'],
                                ['participation', 'Participation', 'federation/participation.php', 'participation']]],
                ['Budget & Reports', [['budget', 'Budget', 'federation/budget.php', 'budget'],
                                      ['reports', 'Reports', 'federation/reports.php', 'reports']]],
                ['Content', [['documents', 'Documents', 'federation/documents.php', 'documents'],
                             ['content', 'KK Member Content', 'federation/content.php', 'content']]],
            ];
        case ROLE_BARANGAY:
            return [
                ['', [['dashboard', 'Dashboard', 'barangay/index.php', 'dashboard']]],
                ['Members', [['verify', 'Verify Members', 'barangay/verify.php', 'verify'],
                             ['youth', 'Youth Profiling', 'barangay/youth.php', 'people']]],
                ['Programs', [['programs', 'Programs', 'barangay/programs.php', 'programs'],
                              ['calendar', 'Calendar', 'barangay/calendar.php', 'calendar'],
                              ['qr', 'QR Attendance', 'barangay/qr.php', 'qr'],
                              ['participation', 'Participation', 'barangay/participation.php', 'participation']]],
                ['Resources', [['inventory', 'Inventory', 'barangay/inventory.php', 'inventory'],
                               ['budget', 'Budget', 'barangay/budget.php', 'budget']]],
                ['Content & Reports', [['documents', 'Documents', 'barangay/documents.php', 'documents'],
                                       ['content', 'KK Member Content', 'barangay/content.php', 'content'],
                                       ['reports', 'Reports', 'barangay/reports.php', 'reports']]],
            ];
        case ROLE_MEMBER:
            return [
                ['', [['home', 'Home', 'member/index.php', 'dashboard']]],
                ['My Activities', [['calendar', 'Calendar', 'member/calendar.php', 'calendar'],
                                   ['scan', 'Scan QR Code', 'member/scan.php', 'scan'],
                                   ['attendance', 'My Attendance', 'member/attendance.php', 'participation'],
                                   ['evaluations', 'My Evaluations', 'member/evaluations.php', 'feedback'],
                                   ['certificates', 'My Certificates', 'member/certificates.php', 'certificate']]],
                ['Requests', [['equipment', 'Request Equipment', 'member/equipment.php', 'inventory']]],
                ['Information', [['announcements', 'Announcements', 'member/announcements.php', 'announcements'],
                                 ['documents', 'Reports & Documents', 'member/documents.php', 'documents'],
                                 ['feedback', 'Feedback', 'member/feedback.php', 'content']]],
            ];
    }
    return [];
}

$__groups = nav_groups(role());
?>
<nav class="nav-rail" id="nav-rail" aria-label="Main navigation">
  <a class="nav-brand" href="<?= e(url(home_url(role()))) ?>">
    <img src="<?= e(asset('img/sk-federation-logo.png')) ?>" alt="SK Federation Nasipit logo" width="38" height="38">
    <span>
      <strong>SK Federation</strong>
      <small>Monitoring &amp; Evaluation System</small>
    </span>
  </a>
  <div class="nav-groups">
    <?php foreach ($__groups as [$__label, $__items]): ?>
      <div class="nav-group">
        <?php if ($__label !== ''): ?><div class="nav-group-label"><?= e($__label) ?></div><?php endif; ?>
        <?php foreach ($__items as [$__id, $__text, $__href, $__icon]): ?>
          <a class="nav-item<?= ($nav ?? '') === $__id ? ' active' : '' ?>" href="<?= e(url($__href)) ?>"<?= ($nav ?? '') === $__id ? ' aria-current="page"' : '' ?>>
            <span class="nav-icon"><?= nav_icon($__icon) ?></span><span><?= e($__text) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="nav-foot">
    <div class="nav-foot-name"><?= e(display_name()) ?></div>
    <div class="nav-foot-role"><?= e(ROLE_LABELS[role()] ?? '') ?><?= current_user()['barangay_name'] ? ' · ' . e(current_user()['barangay_name']) : '' ?></div>
  </div>
</nav>
