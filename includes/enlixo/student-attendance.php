<?php
/**
 * Stat tiles of the student Attendance pages (portal/attendance.php, portal/class-attendance.php): class days that are
 * over, counted the way api/getAttendance.php marks them on the calendar (2 Present, 4 Watched the recording,
 * 1 Joined online and being checked, anything else or no row = Absent). Returns the tiles' HTML.
 */
require_once __DIR__ . '/stat-card.php';

if (!function_exists('sa_attendance_tiles')) {
    function sa_attendance_tiles(int $studentId, int $classId = 0): string
    {
        $now = (new DateTime('now', new DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s');
        $row = Database::search(
            "SELECT COUNT(DISTINCT tt.time_table_id) total,
                    COUNT(DISTINCT CASE WHEN a.attendance_status_attendance_status_id = 2 THEN tt.time_table_id END) present,
                    COUNT(DISTINCT CASE WHEN a.attendance_status_attendance_status_id = 4 THEN tt.time_table_id END) watched,
                    COUNT(DISTINCT CASE WHEN a.attendance_status_attendance_status_id = 1 THEN tt.time_table_id END) pending,
                    COUNT(DISTINCT CASE WHEN a.attendance_status_attendance_status_id = 2 AND tt.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN tt.time_table_id END) present30,
                    COUNT(DISTINCT CASE WHEN tt.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN tt.time_table_id END) total30
               FROM time_table tt
               JOIN students_has_classes shc ON shc.classes_class_id = tt.classes_class_id AND shc.students_student_id = '" . $studentId . "' AND shc.status_status_id = 1
               LEFT JOIN attendance a ON a.time_table_time_table_id = tt.time_table_id AND a.students_student_id = '" . $studentId . "'
              WHERE TIMESTAMP(tt.date, tt.start_time) <= '" . $now . "' AND tt.time_table_status_time_table_status_id = 1 AND tt.lecture_type_lecture_type_id != 3"
            . ($classId > 0 ? " AND tt.classes_class_id = '" . $classId . "'" : '')
        )->fetch_assoc() ?: [];
        $total = (int) ($row['total'] ?? 0);
        $present = (int) ($row['present'] ?? 0);
        $watched = (int) ($row['watched'] ?? 0);
        $pending = (int) ($row['pending'] ?? 0);
        $absent = max(0, $total - $present - $watched - $pending);
        $rate = $total ? (int) round(($present + $watched) / $total * 100) : 0;
        $rate30 = (int) ($row['total30'] ?? 0) ? (int) round((int) $row['present30'] / (int) $row['total30'] * 100) : null;
        $plural = static fn (int $n, string $one) => $n . ' ' . $one . ($n === 1 ? '' : 's');
        ob_start();
        enlixo_stat_card('check', 'Present', (string) $present, $total ? 'of ' . $plural($total, 'class day') . ' so far' : 'No class days yet');
        enlixo_stat_card('close', 'Absent', (string) $absent, $absent ? 'Watch the recordings to catch up' : 'You have not missed a class');
        enlixo_stat_card('play', 'Watched Recording', (string) $watched, $pending ? $plural($pending, 'online class') . ' being checked' : 'Missed classes you watched later');
        enlixo_stat_card('chart', 'Attendance Rate', $rate . '%', $rate30 !== null ? $rate30 . '% in the last 30 days' : 'Present + watched, of every class day');
        return (string) ob_get_clean();
    }
}
