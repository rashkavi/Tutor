<?php
declare(strict_types=1);

namespace Enlixo\Http\Controllers;

use Enlixo\App;
use Enlixo\Classes\ClassRepository;
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

    /** GET /api/v1/tutor/classes (filter options) */
    public function classes(Request $r): JsonResponse
    {
        $auth = $r->auth();
        $this->app->permissions()->require($auth, 'classes.view');
        return JsonResponse::ok((new ClassRepository($this->app->db()))->optionsForTutor($auth->tenantId()));
    }
}
