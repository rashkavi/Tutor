<?php
declare(strict_types=1);

namespace Enlixo\Http\Controllers;

use Enlixo\App;
use Enlixo\Core\HttpException;
use Enlixo\Core\JsonResponse;
use Enlixo\Core\Request;
use Enlixo\Dashboard\TutorDashboard;

final class DashboardController
{
    public function __construct(private readonly App $app)
    {
    }

    /** GET /api/v1/tutor/dashboard: everything on tutor/dashboard.php in one request. Fees only for people who may see payments. */
    public function show(Request $r): JsonResponse
    {
        $auth = $r->auth();
        $this->app->permissions()->require($auth, 'classes.view');
        $money = $this->app->permissions()->can($auth, 'payments.view');
        return JsonResponse::ok((new TutorDashboard($this->app->db()))->overview($auth->tenantId(), $money));
    }

    /** GET /api/v1/tutor/exam-dates: the exams of the tutor's active classes and the day each one starts. */
    public function examDates(Request $r): JsonResponse
    {
        $auth = $r->auth();
        $this->app->permissions()->require($auth, 'classes.view');
        $d = new TutorDashboard($this->app->db());
        return JsonResponse::ok(['ready' => $d->examTableReady(), 'exams' => $d->examDates($auth->tenantId())]);
    }

    /** PUT /api/v1/tutor/exam-dates  { exams: [{ exam_type_id, year_id, date: 'Y-m-d' | null }] } */
    public function saveExamDates(Request $r): JsonResponse
    {
        $auth = $r->auth();
        $this->app->permissions()->require($auth, 'classes.manage');
        $d = new TutorDashboard($this->app->db());
        if (!$d->examTableReady()) {
            throw new HttpException(503, 'not_ready', 'This section is not available yet. Please contact your system administrator.');
        }
        $items = $r->input('exams');
        if (!is_array($items) || count($items) > 50) {
            throw new HttpException(422, 'validation_failed', 'Nothing to save.');
        }
        $clean = [];
        $errors = [];
        foreach ($items as $i => $it) {
            $date = trim((string) ($it['date'] ?? ''));
            if ($date !== '') {
                $dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
                if (!$dt || $dt->format('Y-m-d') !== $date || $dt->format('Y') < 2000 || $dt->format('Y') > 2100) {
                    $errors["date_$i"] = 'Choose a real date, for example 2027-02-02.';
                    continue;
                }
            }
            $clean[] = ['exam_type_id' => (int) ($it['exam_type_id'] ?? 0), 'year_id' => (int) ($it['year_id'] ?? 0), 'date' => $date === '' ? null : $date];
        }
        if ($errors) {
            throw new HttpException(422, 'validation_failed', 'Check the dates.', $errors);
        }
        $d->saveExamDates($auth->tenantId(), $clean);
        return JsonResponse::ok(['exams' => $d->examDates($auth->tenantId())]);
    }
}
