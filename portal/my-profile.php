<?php
// Student → My Profile: the ENLIXO V3 student portal profile (enlixo-uni-v3-portal/my-profile.php): a profile card on
// the left (photo, name, classes, contact rows) and the details in sections that open and close on the right.
// Read only; "Edit Profile" opens portal/edit-profile.php (the form, saved by api/updateProfile.php).
require_once '../includes/UserClass.php';
require_once "../includes/connection.php";
require_once "../includes/UserPermissionsClass.php";
require_once "../includes/Project.php";
if (!SessionUser::ifUserIsStudent()) {
    header("Location: ./index");
    exit;
}
$sessionUserObject = SessionUser::getCurrentUserObject('Student');
require_once __DIR__ . '/../includes/enlixo/student-learning.php';
$sid = (int) $sessionUserObject['student_id'];

$s = Database::search("SELECT s.*, ld.first_name, ld.last_name, ld.email, ld.whatsapp, ld.date_registered, g.gender
    FROM students s
    LEFT JOIN login_details ld ON ld.login_details_id = s.login_details_login_details_id
    LEFT JOIN gender g ON g.gender_id = s.gender_gender_id
    WHERE s.student_id = '" . $sid . "'")->fetch_assoc() ?: [];
$addr = Database::search("SELECT a.address_line_1, a.address_line_2, a.postal_code, c.city, d.district, p.province
    FROM student_has_address a
    LEFT JOIN city c ON c.city_id = a.city_city_id
    LEFT JOIN district d ON d.district_id = c.district_district_id
    LEFT JOIN province p ON p.province_id = d.province_province_id
    WHERE a.students_student_id = '" . $sid . "' LIMIT 1")->fetch_assoc() ?: [];
$guardians = [];
$g = Database::search("SELECT p.parent_fname, p.parent_lname, p.whatsapp, p.mobile, p.email, t.title, r.guardian_relation
    FROM parents_has_students ps
    JOIN parents p ON p.parent_id = ps.parents_parent_id
    LEFT JOIN title t ON t.title_id = p.title_title_id
    LEFT JOIN guardian_relation r ON r.guardian_relation_id = p.guardian_relation_guardian_relation_id
    WHERE ps.students_student_id = '" . $sid . "'");
while ($g && ($row = $g->fetch_assoc())) {
    $guardians[] = $row;
}
$classes = [];
$c = Database::search("SELECT c.class_id, c.class_name, shc.student_class_id, shc.added_at
    FROM students_has_classes shc JOIN classes c ON c.class_id = shc.classes_class_id
    WHERE shc.students_student_id = '" . $sid . "' AND shc.status_status_id = 1 ORDER BY c.class_name");
while ($c && ($row = $c->fetch_assoc())) {
    $classes[] = $row;
}

$name = trim(($s['first_name'] ?? '') . ' ' . ($s['last_name'] ?? '')) ?: (string) ($s['full_name'] ?? 'Student');
$initials = strtoupper(mb_substr((string) ($s['first_name'] ?? $name), 0, 1) . mb_substr((string) ($s['last_name'] ?? ''), 0, 1));
$photo = !empty($s['profile_img']) && file_exists(Project::getAssetsPath() . '/imgs/students/' . $s['profile_img']) ? Project::getAssetsPath() . '/imgs/students/' . $s['profile_img'] : null;
$address = trim(implode(', ', array_filter([$addr['address_line_1'] ?? '', $addr['address_line_2'] ?? '', $addr['city'] ?? ''])));
$studentCode = $classes[0]['student_class_id'] ?? '';
$v = static fn ($x) => ($x === null || trim((string) $x) === '') ? null : (string) $x;

// [key => [title, help, icon, [[label, value, wide?], ...]]]
$sections = [
    'basic' => ['Personal Details', 'Your name and personal details', 'user', [
        ['First Name', $v($s['first_name'] ?? null)], ['Last Name', $v($s['last_name'] ?? null)],
        ['Full Name', $v($s['full_name'] ?? null), true],
        ['Name with Initials', $v($s['name_with_initials'] ?? null)], ['Gender', $v($s['gender'] ?? null)],
        ['Date of Birth', !empty($s['date_of_birth']) ? date('j F Y', strtotime($s['date_of_birth'])) : null],
    ]],
    'contact' => ['Contact Details', 'How your tutor reaches you', 'phone', [
        ['E-mail', $v($s['email'] ?? null), true], ['Mobile', $v($s['mobile'] ?? null)], ['WhatsApp', $v($s['whatsapp'] ?? null)],
        ['Address Line 1', $v($addr['address_line_1'] ?? null)], ['Address Line 2', $v($addr['address_line_2'] ?? null)],
        ['City', $v($addr['city'] ?? null)], ['District', $v($addr['district'] ?? null)],
        ['Province', $v($addr['province'] ?? null)], ['Postal Code', $v($addr['postal_code'] ?? null)],
    ]],
];
$filled = 0;
$fields = 0;
foreach ($sections as $sec) {
    foreach ($sec[3] as $f) {
        $fields++;
        $filled += $f[1] !== null ? 1 : 0;
    }
}

$lf = ['title' => 'My Profile', 'menu' => 'My Profile', 'css' => [Project::getAssetsPath() . '/css/student-profile.css']];
include __DIR__ . '/includes/learning-frame-top.php';
sl_page_title('My Profile', [], 'Your personal, contact and guardian details as your tutor has them.',
    '<a class="custom-btn" href="./edit-profile">' . enlixo_icon('edit', 18) . 'Edit Profile</a>');
?>
                            <div class="pf-layout">
                                <aside class="pf-card">
                                    <div class="pf-cover"></div>
                                    <div class="pf-identity">
                                        <?php if ($photo) { ?>
                                            <img class="pf-photo" src="<?= sl_e($photo) ?>" alt="Profile photo">
                                        <?php } else { ?>
                                            <div class="pf-photo pf-photo-empty"><?= sl_e($initials) ?></div>
                                        <?php } ?>
                                        <div class="pf-name"><?= sl_e($name) ?></div>
                                        <div class="pf-sub"><?= count($classes) ? sl_e(count($classes) . (count($classes) === 1 ? ' class' : ' classes')) : 'No active class' ?></div>
                                        <div class="pf-chips">
                                            <?php if ($studentCode) { ?><span class="pf-chip"><?= enlixo_icon('id-card', 14) ?><?= sl_e($studentCode) ?></span><?php } ?>
                                            <span class="pf-chip is-active">Active</span>
                                        </div>
                                    </div>
                                    <div class="pf-contacts">
                                        <?php
                                        foreach ([
                                            ['mail', 'E-mail', $v($s['email'] ?? null)],
                                            ['phone', 'Mobile', $v($s['mobile'] ?? null)],
                                            ['whatsapp', 'WhatsApp', $v($s['whatsapp'] ?? null)],
                                            ['location', 'Address', $address ?: null],
                                            ['calendar', 'Joined', !empty($s['date_added']) ? date('j F Y', strtotime($s['date_added'])) : null],
                                        ] as [$ico, $label, $value]) {
                                            if ($value === null) continue; ?>
                                            <div class="pf-contact">
                                                <span class="pf-contact-icon"><?= enlixo_icon($ico, 18) ?></span>
                                                <div class="pf-contact-text"><small><?= sl_e($label) ?></small><span><?= sl_e($value) ?></span></div>
                                            </div>
                                        <?php } ?>
                                    </div>
                                </aside>

                                <div class="pf-stack">
                                    <div class="pf-card pf-progress">
                                        <div class="pf-progress-text"><strong><?= $filled ?> of <?= $fields ?></strong> details filled<?= $filled < $fields ? ' · add the missing ones with Edit Profile' : '' ?></div>
                                        <div class="pf-progress-bar"><span style="width: <?= $fields ? (int) round($filled / $fields * 100) : 0 ?>%"></span></div>
                                    </div>
                                    <?php
                                    $first = true;
                                    foreach ($sections as $key => [$title, $help, $ico, $items]) {
                                        $n = count(array_filter($items, static fn ($f) => $f[1] !== null)); ?>
                                        <section class="pf-card pf-acc">
                                            <button type="button" class="pf-section-head pf-acc-toggle <?= $first ? '' : 'collapsed' ?>" data-bs-toggle="collapse" data-bs-target="#pf-<?= $key ?>" aria-expanded="<?= $first ? 'true' : 'false' ?>" aria-controls="pf-<?= $key ?>">
                                                <span class="pf-section-icon"><?= enlixo_icon($ico, 20) ?></span>
                                                <span class="pf-acc-text"><h2><?= sl_e($title) ?></h2><p><?= sl_e($help) ?> · <?= $n ?> of <?= count($items) ?> filled</p></span>
                                                <?= enlixo_icon('chevron-down', 20, 'pf-acc-chevron') ?>
                                            </button>
                                            <div id="pf-<?= $key ?>" class="collapse <?= $first ? 'show' : '' ?>">
                                                <div class="pf-grid">
                                                    <?php foreach ($items as $f) { ?>
                                                        <div class="pf-item <?= !empty($f[2]) ? 'is-wide' : '' ?>">
                                                            <span class="pf-label"><?= sl_e($f[0]) ?></span>
                                                            <span class="pf-value <?= $f[1] === null ? 'is-empty' : '' ?>"><?= $f[1] === null ? 'Not provided' : sl_e($f[1]) ?></span>
                                                        </div>
                                                    <?php } ?>
                                                </div>
                                            </div>
                                        </section>
                                    <?php $first = false;
                                    } ?>

                                    <section class="pf-card pf-acc">
                                        <button type="button" class="pf-section-head pf-acc-toggle collapsed" data-bs-toggle="collapse" data-bs-target="#pf-guardian" aria-expanded="false" aria-controls="pf-guardian">
                                            <span class="pf-section-icon"><?= enlixo_icon('shield', 20) ?></span>
                                            <span class="pf-acc-text"><h2>Parent / Guardian</h2><p>Who your tutor contacts about you · <?= count($guardians) ?: 'none' ?> added</p></span>
                                            <?= enlixo_icon('chevron-down', 20, 'pf-acc-chevron') ?>
                                        </button>
                                        <div id="pf-guardian" class="collapse">
                                            <?php if (!$guardians) { ?>
                                                <div class="empty-state"><div class="empty-state-icon"><?= enlixo_icon('inbox', 28) ?></div>
                                                    <div class="empty-state-title">No parent or guardian yet</div>
                                                    <div class="empty-state-text">Your tutor adds your parent or guardian. Ask them if you want one added.</div></div>
                                            <?php }
                                            foreach ($guardians as $gd) { ?>
                                                <div class="pf-grid">
                                                    <div class="pf-item is-wide"><span class="pf-label">Name</span><span class="pf-value"><?= sl_e(trim(($gd['title'] ? $gd['title'] . '. ' : '') . $gd['parent_fname'] . ' ' . $gd['parent_lname'])) ?></span></div>
                                                    <div class="pf-item"><span class="pf-label">Relationship</span><span class="pf-value <?= $v($gd['guardian_relation']) === null ? 'is-empty' : '' ?>"><?= sl_e($v($gd['guardian_relation']) ?? 'Not provided') ?></span></div>
                                                    <div class="pf-item"><span class="pf-label">WhatsApp</span><span class="pf-value <?= $v($gd['whatsapp']) === null ? 'is-empty' : '' ?>"><?= sl_e($v($gd['whatsapp']) ?? 'Not provided') ?></span></div>
                                                    <div class="pf-item"><span class="pf-label">Mobile</span><span class="pf-value <?= $v($gd['mobile']) === null ? 'is-empty' : '' ?>"><?= sl_e($v($gd['mobile']) ?? 'Not provided') ?></span></div>
                                                    <div class="pf-item"><span class="pf-label">E-mail</span><span class="pf-value <?= $v($gd['email']) === null ? 'is-empty' : '' ?>"><?= sl_e($v($gd['email']) ?? 'Not provided') ?></span></div>
                                                </div>
                                            <?php } ?>
                                        </div>
                                    </section>

                                    <section class="pf-card pf-acc">
                                        <button type="button" class="pf-section-head pf-acc-toggle collapsed" data-bs-toggle="collapse" data-bs-target="#pf-classes" aria-expanded="false" aria-controls="pf-classes">
                                            <span class="pf-section-icon"><?= enlixo_icon('classes', 20) ?></span>
                                            <span class="pf-acc-text"><h2>My Classes</h2><p>The classes you are in now · <?= count($classes) ?></p></span>
                                            <?= enlixo_icon('chevron-down', 20, 'pf-acc-chevron') ?>
                                        </button>
                                        <div id="pf-classes" class="collapse">
                                            <?php if (!$classes) { ?>
                                                <div class="empty-state"><div class="empty-state-icon"><?= enlixo_icon('inbox', 28) ?></div>
                                                    <div class="empty-state-title">No active class</div>
                                                    <div class="empty-state-text">Classes you join appear here.</div></div>
                                            <?php } else { ?>
                                                <div class="pf-grid">
                                                    <?php foreach ($classes as $cl) { ?>
                                                        <a class="pf-item is-wide pf-class-link" href="./class-details?class-id=<?= (int) $cl['class_id'] ?>">
                                                            <span class="pf-label"><?= sl_e($cl['student_class_id'] ?: 'Class') ?> · joined <?= sl_e(date('j M Y', strtotime((string) $cl['added_at']))) ?></span>
                                                            <span class="pf-value"><?= sl_e($cl['class_name']) ?></span>
                                                        </a>
                                                    <?php } ?>
                                                </div>
                                            <?php } ?>
                                        </div>
                                    </section>
                                </div>
                            </div>
<?php include __DIR__ . '/includes/learning-frame-bottom.php';
