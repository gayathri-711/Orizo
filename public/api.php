<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Bootstrap/App.php';

use Orixo\Bootstrap\App;
use Orixo\Bootstrap\Router;
use Orixo\Controllers\Api\AdminApiController;
use Orixo\Controllers\Api\AdminCmsApiController;
use Orixo\Controllers\Api\AuthApiController;
use Orixo\Controllers\Api\ClientApiController;
use Orixo\Controllers\Api\PublicApiController;
use Orixo\Controllers\Api\WorkspaceApiController;
use Orixo\Middleware\AuthMiddleware;
use Orixo\Middleware\CsrfMiddleware;
use Orixo\Middleware\RoleMiddleware;

App::bootHttp();

$csrf = [CsrfMiddleware::class];
$clientRead = [AuthMiddleware::class, [RoleMiddleware::class, 'handle', [['client']]]];
$clientWrite = [AuthMiddleware::class, [RoleMiddleware::class, 'handle', [['client']]], CsrfMiddleware::class];
$wsRead = [AuthMiddleware::class, [RoleMiddleware::class, 'handle', [['admin', 'team_member']]]];
$wsWrite = [AuthMiddleware::class, [RoleMiddleware::class, 'handle', [['admin', 'team_member']]], CsrfMiddleware::class];
$adminRead = [AuthMiddleware::class, [RoleMiddleware::class, 'handle', [['admin']]]];
$adminWrite = [AuthMiddleware::class, [RoleMiddleware::class, 'handle', [['admin']]], CsrfMiddleware::class];

$router = new Router();

// --- AUTH API ---
$router->post('/api/v1/auth/login', [AuthApiController::class, 'login'], $csrf);
$router->post('/api/v1/auth/logout', [AuthApiController::class, 'logout'], [AuthMiddleware::class, CsrfMiddleware::class]);
$router->post('/api/v1/auth/activate', [AuthApiController::class, 'activate'], $csrf);
$router->post('/api/v1/auth/forgot-password', [AuthApiController::class, 'forgotPassword'], $csrf);
$router->post('/api/v1/auth/reset-password', [AuthApiController::class, 'resetPassword'], $csrf);
$router->get('/api/v1/auth/me', [AuthApiController::class, 'me'], [AuthMiddleware::class]);

// --- PUBLIC API ---
$router->get('/api/v1/public/services', [PublicApiController::class, 'services']);
$router->get('/api/v1/public/portfolio', [PublicApiController::class, 'portfolio']);
$router->get('/api/v1/public/case-studies', [PublicApiController::class, 'caseStudies']);
$router->post('/api/v1/public/contact', [PublicApiController::class, 'submitContact'], $csrf);
$router->post('/api/v1/public/inquiries', [PublicApiController::class, 'submitInquiry'], $csrf);

// --- CLIENT PORTAL API ---
$router->get('/api/v1/client/dashboard', [ClientApiController::class, 'dashboard'], $clientRead);
$router->get('/api/v1/client/projects', [ClientApiController::class, 'projects'], $clientRead);
$router->get('/api/v1/client/projects/{id}', [ClientApiController::class, 'projectDetail'], $clientRead);
$router->get('/api/v1/client/deliverables', [ClientApiController::class, 'deliverables'], $clientRead);
$router->get('/api/v1/client/projects/{id}/deliverables', [ClientApiController::class, 'deliverables'], $clientRead);
$router->get('/api/v1/client/documents', [ClientApiController::class, 'documents'], $clientRead);
$router->get('/api/v1/client/projects/{id}/documents', [ClientApiController::class, 'documents'], $clientRead);
$router->post('/api/v1/client/projects/{id}/client-uploads', [ClientApiController::class, 'uploadClientFile'], $clientWrite);
$router->patch('/api/v1/client/projects/{id}/client-uploads/{doc_id}', [ClientApiController::class, 'updateClientUpload'], $clientWrite);
$router->get('/api/v1/client/files/{file_id}/download', [ClientApiController::class, 'downloadFile'], $clientRead);
$router->get('/api/v1/client/projects/{id}/messages', [ClientApiController::class, 'messages'], $clientRead);
$router->post('/api/v1/client/projects/{id}/messages', [ClientApiController::class, 'postMessage'], $clientWrite);
$router->get('/api/v1/client/notifications', [ClientApiController::class, 'notifications'], $clientRead);
$router->patch('/api/v1/client/notifications/{id}/read', [ClientApiController::class, 'markNotificationRead'], $clientWrite);
$router->get('/api/v1/client/invoices', [ClientApiController::class, 'invoices'], $clientRead);
$router->get('/api/v1/client/invoices/{id}', [ClientApiController::class, 'invoiceDetail'], $clientRead);
$router->get('/api/v1/client/invoices/{id}/pdf', [ClientApiController::class, 'downloadInvoicePdf'], $clientRead);
$router->patch('/api/v1/client/profile', [ClientApiController::class, 'updateProfile'], $clientWrite);
$router->patch('/api/v1/client/password', [ClientApiController::class, 'changePassword'], $clientWrite);

// --- WORKSPACE API (Admin + Assigned Team Member) ---
$router->get('/api/v1/workspace/dashboard', [WorkspaceApiController::class, 'dashboard'], $wsRead);
$router->get('/api/v1/workspace/projects', [WorkspaceApiController::class, 'projects'], $wsRead);
$router->get('/api/v1/workspace/projects/{id}', [WorkspaceApiController::class, 'projectDetail'], $wsRead);
$router->post('/api/v1/workspace/projects/{id}/tasks', [WorkspaceApiController::class, 'createTask'], $wsWrite);
$router->patch('/api/v1/workspace/tasks/{task_id}', [WorkspaceApiController::class, 'updateTask'], $wsWrite);
$router->get('/api/v1/workspace/projects/{id}/documents', [WorkspaceApiController::class, 'documents'], $wsRead);
$router->post('/api/v1/workspace/projects/{id}/documents', [WorkspaceApiController::class, 'uploadDocument'], $wsWrite);
$router->patch('/api/v1/workspace/documents/{doc_id}/review', [WorkspaceApiController::class, 'reviewDocument'], $wsWrite);
$router->get('/api/v1/workspace/projects/{id}/deliverables', [WorkspaceApiController::class, 'deliverables'], $wsRead);
$router->post('/api/v1/workspace/projects/{id}/deliverables', [WorkspaceApiController::class, 'submitDeliverable'], $wsWrite);
$router->post('/api/v1/workspace/deliverables/{id}/replace', [WorkspaceApiController::class, 'replaceDeliverable'], $wsWrite);
$router->get('/api/v1/workspace/projects/{id}/messages', [WorkspaceApiController::class, 'messages'], $wsRead);
$router->post('/api/v1/workspace/projects/{id}/messages', [WorkspaceApiController::class, 'postMessage'], $wsWrite);
$router->get('/api/v1/workspace/files/{file_id}/download', [WorkspaceApiController::class, 'downloadFile'], $wsRead);
$router->patch('/api/v1/workspace/profile', [WorkspaceApiController::class, 'updateProfile'], $wsWrite);
$router->patch('/api/v1/workspace/password', [WorkspaceApiController::class, 'changePassword'], $wsWrite);

// --- ADMIN-ONLY API ---
$router->get('/api/v1/admin/dashboard', [AdminApiController::class, 'dashboard'], $adminRead);
$router->get('/api/v1/admin/clients', [AdminApiController::class, 'clients'], $adminRead);
$router->post('/api/v1/admin/clients', [AdminApiController::class, 'createClient'], $adminWrite);
$router->patch('/api/v1/admin/clients/{id}', [AdminApiController::class, 'updateClient'], $adminWrite);
$router->post('/api/v1/admin/clients/{id}/resend-activation', [AdminApiController::class, 'resendActivation'], $adminWrite);

$router->get('/api/v1/admin/inquiries', [AdminApiController::class, 'inquiries'], $adminRead);
$router->get('/api/v1/admin/inquiries/{id}', [AdminApiController::class, 'inquiryDetail'], $adminRead);
$router->get('/api/v1/admin/inquiries/{id}/attachments/{att_id}/download', [AdminApiController::class, 'downloadInquiryAttachment'], $adminRead);
$router->patch('/api/v1/admin/inquiries/{id}', [AdminApiController::class, 'updateInquiry'], $adminWrite);
$router->post('/api/v1/admin/inquiries/{id}/convert', [AdminApiController::class, 'convertInquiry'], $adminWrite);

$router->get('/api/v1/admin/projects', [AdminApiController::class, 'projects'], $adminRead);
$router->post('/api/v1/admin/projects', [AdminApiController::class, 'createProject'], $adminWrite);
$router->patch('/api/v1/admin/projects/{id}', [AdminApiController::class, 'updateProject'], $adminWrite);
$router->patch('/api/v1/admin/projects/{id}/progress', [AdminApiController::class, 'updateProjectProgress'], $adminWrite);
$router->post('/api/v1/admin/projects/{id}/assignments', [AdminApiController::class, 'assignTeamMember'], $adminWrite);
$router->delete('/api/v1/admin/projects/{id}/assignments/{team_id}', [AdminApiController::class, 'removeTeamAssignment'], $adminWrite);
$router->post('/api/v1/admin/projects/{id}/milestones', [AdminApiController::class, 'createMilestone'], $adminWrite);
$router->patch('/api/v1/admin/milestones/{ms_id}', [AdminApiController::class, 'updateMilestone'], $adminWrite);
$router->delete('/api/v1/admin/milestones/{ms_id}', [AdminApiController::class, 'deleteMilestone'], $adminWrite);

$router->patch('/api/v1/admin/documents/{doc_id}/publish', [AdminApiController::class, 'publishDocument'], $adminWrite);
$router->patch('/api/v1/admin/documents/{doc_id}/unpublish', [AdminApiController::class, 'unpublishDocument'], $adminWrite);
$router->delete('/api/v1/admin/documents/{doc_id}', [AdminApiController::class, 'deleteDocument'], $adminWrite);
$router->patch('/api/v1/admin/deliverables/{id}/publish', [AdminApiController::class, 'publishDeliverable'], $adminWrite);
$router->patch('/api/v1/admin/deliverables/{id}/reject', [AdminApiController::class, 'rejectDeliverable'], $adminWrite);

$router->get('/api/v1/admin/invoices', [AdminApiController::class, 'invoices'], $adminRead);
$router->get('/api/v1/admin/invoices/{id}', [AdminApiController::class, 'invoiceDetail'], $adminRead);
$router->post('/api/v1/admin/invoices', [AdminApiController::class, 'createInvoice'], $adminWrite);
$router->patch('/api/v1/admin/invoices/{id}', [AdminApiController::class, 'updateInvoice'], $adminWrite);
$router->post('/api/v1/admin/invoices/{id}/issue', [AdminApiController::class, 'issueInvoice'], $adminWrite);
$router->post('/api/v1/admin/invoices/{id}/cancel', [AdminApiController::class, 'cancelInvoice'], $adminWrite);
$router->post('/api/v1/admin/invoices/{id}/payments', [AdminApiController::class, 'recordPayment'], $adminWrite);
$router->post('/api/v1/admin/payments/{id}/void', [AdminApiController::class, 'voidPayment'], $adminWrite);
$router->get('/api/v1/admin/invoices/{id}/pdf', [AdminApiController::class, 'downloadInvoicePdf'], $adminRead);

$router->get('/api/v1/admin/team-members', [AdminApiController::class, 'teamMembers'], $adminRead);
$router->post('/api/v1/admin/team-members', [AdminApiController::class, 'createTeamMember'], $adminWrite);
$router->patch('/api/v1/admin/team-members/{id}', [AdminApiController::class, 'updateTeamMember'], $adminWrite);
$router->get('/api/v1/admin/users', [AdminApiController::class, 'users'], $adminRead);
$router->patch('/api/v1/admin/users/{id}', [AdminApiController::class, 'updateUser'], $adminWrite);

$router->get('/api/v1/admin/settings', [AdminApiController::class, 'settings'], $adminRead);
$router->patch('/api/v1/admin/settings', [AdminApiController::class, 'updateSettings'], $adminWrite);
$router->get('/api/v1/admin/email-templates', [AdminApiController::class, 'emailTemplates'], $adminRead);
$router->patch('/api/v1/admin/email-templates/{id}', [AdminApiController::class, 'updateEmailTemplate'], $adminWrite);
$router->get('/api/v1/admin/email-queue', [AdminApiController::class, 'emailQueue'], $adminRead);
$router->post('/api/v1/admin/email-queue/process', [AdminApiController::class, 'processEmailQueue'], $adminWrite);
$router->post('/api/v1/admin/email-queue/{id}/retry', [AdminApiController::class, 'retryEmailJob'], $adminWrite);
$router->get('/api/v1/admin/activity-logs', [AdminApiController::class, 'activityLogs'], $adminRead);

// --- ADMIN CMS API ---
$router->get('/api/v1/admin/cms/all', [AdminCmsApiController::class, 'listAll'], $adminRead);
$router->post('/api/v1/admin/cms/service-categories', [AdminCmsApiController::class, 'saveServiceCategory'], $adminWrite);
$router->post('/api/v1/admin/cms/services', [AdminCmsApiController::class, 'saveService'], $adminWrite);
$router->delete('/api/v1/admin/cms/services/{id}', [AdminCmsApiController::class, 'deleteService'], $adminWrite);
$router->post('/api/v1/admin/cms/portfolio-categories', [AdminCmsApiController::class, 'savePortfolioCategory'], $adminWrite);
$router->post('/api/v1/admin/cms/portfolio', [AdminCmsApiController::class, 'savePortfolio'], $adminWrite);
$router->delete('/api/v1/admin/cms/portfolio/{id}', [AdminCmsApiController::class, 'deletePortfolio'], $adminWrite);
$router->post('/api/v1/admin/cms/case-studies', [AdminCmsApiController::class, 'saveCaseStudy'], $adminWrite);
$router->delete('/api/v1/admin/cms/case-studies/{id}', [AdminCmsApiController::class, 'deleteCaseStudy'], $adminWrite);
$router->post('/api/v1/admin/cms/industries', [AdminCmsApiController::class, 'saveIndustry'], $adminWrite);
$router->post('/api/v1/admin/cms/technologies', [AdminCmsApiController::class, 'saveTechnology'], $adminWrite);
$router->post('/api/v1/admin/cms/testimonials', [AdminCmsApiController::class, 'saveTestimonial'], $adminWrite);
$router->patch('/api/v1/admin/cms/pages/{key}', [AdminCmsApiController::class, 'saveCmsPage'], $adminWrite);

$reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/api/v1', PHP_URL_PATH) ?: '/';
$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $reqPath, true);
