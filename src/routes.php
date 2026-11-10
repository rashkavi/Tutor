<?php
declare(strict_types=1);

use Enlixo\App;
use Enlixo\Core\Router;
use Enlixo\Http\Controllers\ClassController;
use Enlixo\Http\Controllers\EntitlementController;
use Enlixo\Http\Controllers\RecordingController;
use Enlixo\Http\Controllers\RoleController;
use Enlixo\Http\Controllers\StudentController;
use Enlixo\Http\Controllers\ZoomWebhookController;

return static function (Router $router, App $app): void {
    $access = new EntitlementController($app);
    $roles = new RoleController($app);
    $rec = new RecordingController($app);
    $zoom = new ZoomWebhookController($app);
    $students = new StudentController($app);
    $classes = new ClassController($app, dirname(__DIR__) . '/assets/imgs/classes-covers');

    $router->get('/api/v1/me/access', $access->show(...));

    $router->get('/api/v1/tutor/students', $students->index(...));
    $router->post('/api/v1/tutor/students/status', $students->updateStatus(...));
    $router->get('/api/v1/tutor/classes', $students->classes(...));
    $router->post('/api/v1/tutor/classes', $classes->store(...));
    $router->get('/api/v1/tutor/class-form', $classes->form(...));
    $router->put('/api/v1/tutor/class-form', $classes->saveForm(...));
    $router->get('/api/v1/tutor/class-form/options', $classes->options(...));
    $router->post('/api/v1/tutor/class-form/options/{list}', $classes->addOption(...));
    $router->put('/api/v1/tutor/class-form/options/{list}/{id}', $classes->renameOption(...));
    $router->delete('/api/v1/tutor/class-form/options/{list}/{id}', $classes->deleteOption(...));
    $router->post('/api/v1/tutor/classes/thumbnail', $classes->thumbnail(...));
    $router->get('/api/v1/tutor/classes/overview', $classes->overview(...));
    $router->post('/api/v1/tutor/classes/order', $classes->order(...));
    $router->post('/api/v1/tutor/classes/status', $classes->status(...));
    $router->post('/api/v1/tutor/classes/{id}', $classes->update(...)); // keep after the fixed /classes/... routes

    $router->get('/api/v1/permissions', $roles->catalogue(...));
    $router->get('/api/v1/tutor/roles', $roles->index(...));
    $router->post('/api/v1/tutor/roles', $roles->store(...));
    $router->put('/api/v1/tutor/roles/{id}', $roles->update(...));
    $router->delete('/api/v1/tutor/roles/{id}', $roles->destroy(...));

    $router->get('/api/v1/student/recordings/{kind}/{id}/status', $rec->status(...));
    $router->post('/api/v1/student/recordings/{kind}/{id}/start', $rec->start(...));
    $router->post('/api/v1/student/recordings/heartbeat', $rec->heartbeat(...));

    $router->post('/api/v1/webhooks/zoom', $zoom->handle(...));
};
