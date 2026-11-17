<?php
/**
 * The student Time Table (ENLIXO V3 student portal "Lecture Schedule Calendar"), shared by
 * portal/time-table.php (every class) and portal/class-time-table.php?class-id=<id> ($ttClass = that class).
 * Needs $sessionUserObject; $ttClass = null or the class row from sl_student_class(). Script: portal/js/time-table.js.
 * The parent portal uses it too: $ttFrame = extra $lf keys (side, header, js) and $ttDetails = the class day page
 * ('class-day?time-table-id='), $ttIntro = the line under the title.
 */
require_once __DIR__ . '/../../includes/enlixo/student-learning.php';
$ttClass = $ttClass ?? null;
// Attendance (portal/attendance.php, portal/class-attendance.php) uses the same calendar: $ttAttendance = true, every
// class day coloured Present / Absent / Watched / Not marked, data from api/getAttendance.php, stat tiles on top.
$ttAttendance = $ttAttendance ?? false;
$ttWord = $ttAttendance ? 'Attendance' : 'Time Table';
$ttTitle = $ttClass ? $ttClass['class_name'] . ' ' . $ttWord : $ttWord;
$lf = ['title' => $ttTitle, 'menu' => $ttClass ? 'Classes' : $ttWord, 'js' => ['js/time-table.js'],
    'icons' => ['physical', 'online', 'teacher', 'chevron-right', 'calendar', 'calendar-empty']];
$lf = ($ttFrame ?? []) + $lf;
include __DIR__ . '/learning-frame-top.php';
?>
                            <?php
                            if ($ttClass) {
                                sl_page_title($ttTitle, [
                                    ['Classes', './classes'],
                                    [$ttClass['class_name'], './class-details?class-id=' . (int) $ttClass['class_id']],
                                    [$ttWord, null],
                                ], '', '<a class="custom-btn" href="./' . ($ttAttendance ? 'attendance' : 'time-table') . '">' . enlixo_icon($ttAttendance ? 'check' : 'timetable', 18) . 'All Classes</a>');
                            } else {
                                sl_page_title($ttWord, [], $ttIntro ?? ($ttAttendance
                                    ? 'Every class day you were marked for: present, absent or watched the recording. Tap a day to open it.'
                                    : 'See every class you are in by week or month. Tap a class to open its details.'));
                            }
                            if (!empty($ttTiles)) {
                                echo '<div class="ac-tiles">' . $ttTiles . '</div>';
                            }
                            ?>

                            <div class="ac-card p-3 p-lg-4" id="timeTable" data-mode="<?= $ttAttendance ? 'attendance' : 'timetable' ?>" data-class-id="<?= $ttClass ? (int) $ttClass['class_id'] : '' ?>" data-details="<?= sl_e($ttDetails ?? 'time-table-class-details?time-table-id=') ?>">
                                <!-- Phones and tablets: month grid, tap a day to see its classes below -->
                                <div class="d-lg-none">
                                    <div class="m-cal">
                                        <div class="m-cal-head">
                                            <button type="button" class="m-cal-nav" data-tt="prev-month" aria-label="Previous month"><?= enlixo_icon('chevron-left', 20) ?></button>
                                            <span class="m-cal-title" id="ttMobileTitle"></span>
                                            <button type="button" class="m-cal-nav" data-tt="next-month" aria-label="Next month"><?= enlixo_icon('chevron-right', 20) ?></button>
                                        </div>
                                        <div class="m-cal-weekdays">
                                            <span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span>
                                        </div>
                                        <div class="m-cal-grid" id="ttMobileGrid"></div>
                                        <div class="m-cal-legend">
                                            <?php if ($ttAttendance) { ?>
                                                <span><i class="m-cal-key is-today"></i>Today</span>
                                                <span><i class="m-cal-key is-present"></i>Present</span>
                                                <span><i class="m-cal-key is-absent"></i>Absent</span>
                                                <span><i class="m-cal-key is-watched"></i>Watched</span>
                                            <?php } else { ?>
                                                <span><i class="m-cal-key is-today"></i>Today</span>
                                                <span><i class="m-cal-key has-class"></i>Class day</span>
                                                <span><i class="m-cal-key is-cancelled"></i>Cancelled</span>
                                                <span><i class="m-cal-key is-selected"></i>Selected</span>
                                            <?php } ?>
                                        </div>
                                    </div>
                                    <div class="m-day">
                                        <div class="m-day-head">
                                            <span class="m-day-title" id="ttMobileDayTitle"></span>
                                            <span class="m-day-count" id="ttMobileDayCount"></span>
                                        </div>
                                        <div class="m-day-list" id="ttMobileDay"></div>
                                    </div>
                                </div>

                                <!-- Large screens: week view (class days get the room) or the month grid -->
                                <div class="d-none d-lg-block">
                                    <div class="w-toolbar">
                                        <div class="w-views" role="tablist" aria-label="Calendar view">
                                            <button type="button" class="w-view is-active" data-tt="view" data-view="week" role="tab"><?= enlixo_icon('calendar-empty', 18) ?>Week</button>
                                            <button type="button" class="w-view" data-tt="view" data-view="month" role="tab"><?= enlixo_icon('calendar', 18) ?>Month</button>
                                        </div>
                                        <h2 class="w-title" id="ttTitle">Time Table</h2>
                                        <div class="w-nav">
                                            <button type="button" class="w-nav-btn" data-tt="prev" aria-label="Previous"><?= enlixo_icon('chevron-left', 18) ?></button>
                                            <button type="button" class="w-nav-btn" data-tt="today">Today</button>
                                            <button type="button" class="w-nav-btn" data-tt="next" aria-label="Next"><?= enlixo_icon('chevron-right', 18) ?></button>
                                        </div>
                                    </div>
                                    <?php if ($ttAttendance) { ?>
                                        <div class="att-legend" aria-label="Colours">
                                            <span><i class="att-key is-present"></i>Present</span><span><i class="att-key is-absent"></i>Absent</span>
                                            <span><i class="att-key is-watched"></i>Watched the recording</span><span><i class="att-key is-pending"></i>Joined online, being checked</span>
                                        </div>
                                    <?php } ?>
                                    <div id="ttWeek"></div>
                                    <div id="ttMonth" class="d-none"></div>
                                </div>
                            </div>
<?php include __DIR__ . '/learning-frame-bottom.php';
