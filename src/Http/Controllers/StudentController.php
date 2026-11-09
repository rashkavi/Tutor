<?php
declare(strict_types=1);

namespace Enlixo\Http\Controllers;

use Enlixo\App;
use Enlixo\Classes\ClassRepository;
use Enlixo\Core\HttpException;
use Enlixo\Core\JsonResponse;
use Enlixo\Core\Request;
use Enlixo\Students\StudentRepository;
use Enlixo\Support\ListQuery;

final class StudentController
{
    public function __construct(private readonly App $app)
    {
    }

    /** GET /api/v1/tutor/students?q=&class=&status=&sort=name|date&dir=&page=&per_page= */
    public function index(Request $r): JsonResponse
    {
        $auth = $r->auth();
        $this->app->permissions()->require($auth, 'students.view');
        $q = ListQuery::fromArray($r->query, StudentRepository::FILTERS, StudentRepository::SORTS, 10);
        return (new StudentRepository($this->app->db()))->forTutor($auth->tenantId(), $q)->toResponse();
    }

    /** POST /api/v1/tutor/students/status  { ids: [..], status: 1|2, class?: id }  (the "Update ticked students" bulk action) */
    public function updateStatus(Request $r): JsonResponse
    {
        $auth = $r->auth();
        $this->app->permissions()->require($auth, 'students.manage');
        $ids = $r->input('ids');
        $status = (int) $r->input('status');
        if (!is_array($ids) || $ids === [] || count($ids) > 500 || !in_array($status, [1, 2], true)) {
            throw new HttpException(422, 'validation_failed', 'Tick at least one student and choose Active or Inactive.');
        }
        $class = $r->input('class');
        $changed = (new StudentRepository($this->app->db()))->setStatus($auth->tenantId(), $ids, $status, $class !== null && $class !== '' ? (int) $class : null);
        return JsonResponse::ok(['changed' => $changed]);
    }

    /** GET /api/v1/tutor/classes (filter options) */
    public function classes(Request $r): JsonResponse
    {
        $auth = $r->auth();
        $this->app->permissions()->require($auth, 'classes.view');
        return JsonResponse::ok((new ClassRepository($this->app->db()))->optionsForTutor($auth->tenantId()));
    }
}
