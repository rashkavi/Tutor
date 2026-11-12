<?php
declare(strict_types=1);

use Enlixo\App;
use Enlixo\Core\Router;
use Enlixo\Http\Controllers\ApplicationController;
use Enlixo\Http\Controllers\ClassController;
use Enlixo\Http\Controllers\ClassRecordingController;
use Enlixo\Http\Controllers\DashboardController;
use Enlixo\Http\Controllers\EntitlementController;
use Enlixo\Http\Controllers\PlatformController;
use Enlixo\Http\Controllers\RecordingController;
use Enlixo\Http\Controllers\RoleController;
use Enlixo\Http\Controllers\StudentBulkController;
use Enlixo\Http\Controllers\StudentController;
use Enlixo\Http\Controllers\TuteController;
use Enlixo\Http\Controllers\TutorRequestController;
use Enlixo\Http\Controllers\ZoomWebhookController;

return static function (Router $router, App $app): void {
    $access = new EntitlementController($app);
    $roles = new RoleController($app);
    $rec = new RecordingController($app);
    $zoom = new ZoomWebhookController($app);
    $students = new StudentController($app);
    $bulk = new StudentBulkController($app);
    $apps = new ApplicationController($app, dirname(__DIR__) . '/assets/imgs/students');
    $classes = new ClassController($app, dirname(__DIR__) . '/assets/imgs/classes-covers');

    $router->get('/api/v1/me/access', $access->show(...));

    $dashboard = new DashboardController($app);
    $router->get('/api/v1/tutor/dashboard', $dashboard->show(...));
    $router->get('/api/v1/tutor/exam-dates', $dashboard->examDates(...));
    $router->put('/api/v1/tutor/exam-dates', $dashboard->saveExamDates(...));

    $router->get('/api/v1/tutor/students', $students->index(...));
    $router->post('/api/v1/tutor/students/status', $students->updateStatus(...));
    $router->post('/api/v1/tutor/students', $apps->addStudent(...));
    $router->get('/api/v1/tutor/students/bulk', $bulk->info(...));
    $router->get('/api/v1/tutor/students/bulk/template', $bulk->template(...));
    $router->post('/api/v1/tutor/students/bulk', $bulk->upload(...));
    $router->get('/api/v1/tutor/student-form', $apps->form(...));
    $router->put('/api/v1/tutor/student-form', $apps->saveForm(...));
    $router->get('/api/v1/tutor/applications', $apps->index(...));
    $router->get('/api/v1/tutor/applications/stats', $apps->stats(...));
    $router->get('/api/v1/tutor/applications/{id}', $apps->show(...)); // keep after /applications/stats
    $router->post('/api/v1/tutor/applications/{id}/status', $apps->status(...));
    $router->get('/api/v1/tutor/application-link', $apps->link(...));
    $router->post('/api/v1/tutor/application-link', $apps->updateLink(...));
    $router->get('/api/v1/public/apply/{token}', $apps->publicForm(...));
    $router->post('/api/v1/public/apply/{token}', $apps->publicApply(...));

    // Online tutor sign-up (tutor-registration-application.php) and the admin's Requested Tutors page
    $treq = new TutorRequestController($app);
    $router->get('/api/v1/public/tutor-signup', $treq->info(...));
    $router->post('/api/v1/public/tutor-signup', $treq->submit(...));
    $router->get('/api/v1/admin/tutor-requests', $treq->index(...));
    $router->get('/api/v1/admin/tutor-requests/{id}', $treq->show(...));
    $router->post('/api/v1/admin/tutor-requests/{id}/approve', $treq->approve(...));
    $router->post('/api/v1/admin/tutor-requests/{id}/reject', $treq->reject(...));
    $router->post('/api/v1/admin/tutor-requests/{id}/reopen', $treq->reopen(...));
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
    $tutes = new TuteController($app, dirname(__DIR__) . '/assets/uploads/tutes-attachments');
    $router->get('/api/v1/tutor/tutes/form', $tutes->form(...));
    $router->post('/api/v1/tutor/tutes', $tutes->store(...));
    $router->get('/api/v1/tutor/classes/{id}/courier-tutes', $tutes->courierTutes(...));
    $router->get('/api/v1/tutor/classes/{id}/summary', $classes->summary(...));
    $router->get('/api/v1/tutor/tutes/{id}/deliveries', $tutes->deliveries(...));
    $router->post('/api/v1/tutor/tutes/{id}/deliveries/sync', $tutes->sync(...));
    $router->post('/api/v1/tutor/tutes/{id}/deliveries', $tutes->updateDeliveries(...));
    $router->get('/api/v1/student/tute-deliveries', $tutes->forStudent(...));
    $router->post('/api/v1/tutor/classes/{id}', $classes->update(...)); // keep after the fixed /classes/... routes

    $router->get('/api/v1/permissions', $roles->catalogue(...));
    $router->get('/api/v1/tutor/roles', $roles->index(...));
    $router->post('/api/v1/tutor/roles', $roles->store(...));
    $router->put('/api/v1/tutor/roles/{id}', $roles->update(...));
    $router->delete('/api/v1/tutor/roles/{id}', $roles->destroy(...));

    // Settings -> Recording Types and Academic -> Class Recordings (fixed paths before /{id})
    $recs = new ClassRecordingController($app);
    $router->get('/api/v1/tutor/recording-types', $recs->typeIndex(...));
    $router->post('/api/v1/tutor/recording-types', $recs->typeSave(...));
    $router->post('/api/v1/tutor/recording-types/order', $recs->typeOrder(...));
    $router->post('/api/v1/tutor/recording-types/check', $recs->checkLink(...));
    $router->put('/api/v1/tutor/recording-types/{id}', $recs->typeSave(...));
    $router->post('/api/v1/tutor/recording-types/{id}/status', $recs->typeStatus(...));
    $router->delete('/api/v1/tutor/recording-types/{id}', $recs->typeDelete(...));
    $router->get('/api/v1/tutor/recordings', $recs->index(...));
    $router->post('/api/v1/tutor/recordings', $recs->save(...));
    $router->get('/api/v1/tutor/recordings/overview', $recs->overview(...));
    $router->get('/api/v1/tutor/recordings/days', $recs->days(...));
    $router->get('/api/v1/tutor/recordings/{id}', $recs->show(...));
    $router->put('/api/v1/tutor/recordings/{id}', $recs->save(...));
    $router->delete('/api/v1/tutor/recordings/{id}', $recs->destroy(...));

    $router->get('/api/v1/student/recordings/{kind}/{id}/status', $rec->status(...));
    $router->post('/api/v1/student/recordings/{kind}/{id}/start', $rec->start(...));
    $router->post('/api/v1/student/recordings/heartbeat', $rec->heartbeat(...));

    $router->post('/api/v1/webhooks/zoom', $zoom->handle(...));

    // Admin side (platform owner): tutors, subscriptions, invoices, payments, plans and rules (docs/ADMIN-PLAN.md)
    $pf = new PlatformController($app);
    $router->get('/api/v1/admin/dashboard', $pf->dashboard(...));
    $router->get('/api/v1/admin/lookups', $pf->lookups(...));
    $router->get('/api/v1/admin/tutors', $pf->tutors(...));
    $router->post('/api/v1/admin/tutors', $pf->addTutor(...));
    $router->get('/api/v1/admin/tutors/{id}', $pf->tutor(...));
    $router->post('/api/v1/admin/tutors/{id}/{action}', $pf->tutorAction(...));
    $router->get('/api/v1/admin/invoices', $pf->invoices(...));
    $router->post('/api/v1/admin/invoices/{id}/payments', $pf->recordPayment(...));
    $router->post('/api/v1/admin/invoices/{id}/void', $pf->voidInvoice(...));
    $router->get('/api/v1/admin/payments', $pf->payments(...));
    $router->get('/api/v1/admin/payments/{id}/slip', $pf->adminSlip(...));
    $router->post('/api/v1/admin/payments/{id}/approve', $pf->approve(...));
    $router->post('/api/v1/admin/payments/{id}/reject', $pf->reject(...));
    $router->get('/api/v1/admin/plans', $pf->plans(...));
    $router->put('/api/v1/admin/plans/{key}', $pf->savePlan(...));
    $router->put('/api/v1/admin/addons/{key}', $pf->saveAddon(...));
    $router->put('/api/v1/admin/billing-rules', $pf->saveRules(...));
    $router->post('/api/v1/admin/bank-accounts', $pf->saveBank(...));
    $router->put('/api/v1/admin/bank-accounts/{id}', $pf->saveBank(...));
    $router->delete('/api/v1/admin/bank-accounts/{id}', $pf->deleteBank(...));

    // The tutor's side of the same bill: Finance -> My Subscription
    $router->get('/api/v1/tutor/subscription', $pf->mySubscription(...));
    $router->post('/api/v1/tutor/subscription/payments', $pf->uploadSlip(...));
    $router->post('/api/v1/tutor/subscription/change', $pf->changeMyPlan(...));
    $router->post('/api/v1/tutor/subscription/pay-now', $pf->payNow(...));
    $router->get('/api/v1/tutor/subscription/payments/{id}/slip', $pf->mySlip(...));
};
