<?php
declare(strict_types=1);

namespace Orixo\Controllers;

use Orixo\Bootstrap\Database;

/**
 * Renders all public agency website pages and public auth views.
 */
final class PublicController
{
    private function checkMaintenance(): void
    {
        if (setting('system.maintenance_mode', '0') === '1') {
            http_response_code(503);
            render_view('public/maintenance', ['pageTitle' => 'Scheduled Maintenance | Orixo'], 'layouts/public_layout');
            exit;
        }
    }

    public function home(): void
    {
        $this->checkMaintenance();
        $pdo = Database::connection();

        $services = $pdo->query("
            SELECT s.*, sc.name AS category_name
            FROM services s
            INNER JOIN service_categories sc ON sc.id = s.category_id
            WHERE s.status = 'published'
            ORDER BY s.is_featured DESC, s.display_order ASC
            LIMIT 6
        ")->fetchAll();

        $portfolio = $pdo->query("
            SELECT p.*, pc.name AS category_name
            FROM portfolio_projects p
            INNER JOIN portfolio_categories pc ON pc.id = p.category_id
            WHERE p.status = 'published'
            ORDER BY p.is_featured DESC, p.completed_year DESC
            LIMIT 4
        ")->fetchAll();

        $caseStudies = $pdo->query("
            SELECT cs.*, i.name AS industry_name, s.title AS service_title
            FROM case_studies cs
            LEFT JOIN industries i ON i.id = cs.industry_id
            LEFT JOIN services s ON s.id = cs.service_id
            WHERE cs.status = 'published'
            ORDER BY cs.is_featured DESC, cs.id DESC
            LIMIT 2
        ")->fetchAll();

        $industries = $pdo->query("
            SELECT * FROM industries WHERE status = 'published' ORDER BY display_order ASC LIMIT 4
        ")->fetchAll();

        $technologies = $pdo->query("
            SELECT * FROM technologies WHERE is_active = 1 ORDER BY display_order ASC LIMIT 8
        ")->fetchAll();

        $testimonials = $pdo->query("
            SELECT * FROM testimonials WHERE status = 'published' ORDER BY is_featured DESC, display_order ASC LIMIT 3
        ")->fetchAll();

        $team = $pdo->query("
            SELECT tm.*, u.full_name, u.avatar_path
            FROM team_members tm
            INNER JOIN users u ON u.id = tm.user_id
            WHERE tm.show_on_public_website = 1 AND tm.status = 'active' AND u.status = 'active'
            ORDER BY tm.display_order ASC
            LIMIT 3
        ")->fetchAll();

        $processStmt = $pdo->prepare("SELECT * FROM cms_pages WHERE page_key = 'our_process' LIMIT 1");
        $processStmt->execute();
        $processPage = $processStmt->fetch();

        render_view('public/home', [
            'pageTitle' => setting('seo.default_title', 'Orixo — Creative Digital Solutions & Innovation Studio'),
            'metaDescription' => setting('seo.default_description'),
            'services' => $services,
            'portfolio' => $portfolio,
            'caseStudies' => $caseStudies,
            'industries' => $industries,
            'technologies' => $technologies,
            'testimonials' => $testimonials,
            'team' => $team,
            'processSteps' => $processPage && $processPage['structured_sections_json'] ? json_decode((string) $processPage['structured_sections_json'], true) : [],
        ], 'layouts/public_layout');
    }

    public function about(): void
    {
        $this->checkMaintenance();
        $pdo = Database::connection();
        $stmt = $pdo->prepare("SELECT * FROM cms_pages WHERE page_key = 'about' LIMIT 1");
        $stmt->execute();
        $page = $stmt->fetch();

        $team = $pdo->query("
            SELECT tm.*, u.full_name, u.avatar_path
            FROM team_members tm
            INNER JOIN users u ON u.id = tm.user_id
            WHERE tm.show_on_public_website = 1 AND tm.status = 'active' AND u.status = 'active'
            ORDER BY tm.display_order ASC
            LIMIT 6
        ")->fetchAll();

        render_view('public/about', [
            'pageTitle' => 'About Us | Orixo',
            'page' => $page,
            'sections' => $page && $page['structured_sections_json'] ? json_decode((string) $page['structured_sections_json'], true) : [],
            'team' => $team,
        ], 'layouts/public_layout');
    }

    public function services(): void
    {
        $this->checkMaintenance();
        $pdo = Database::connection();
        $categories = $pdo->query("SELECT * FROM service_categories WHERE is_active = 1 ORDER BY display_order ASC")->fetchAll();
        $services = $pdo->query("
            SELECT s.*, sc.name AS category_name, sc.slug AS category_slug
            FROM services s
            INNER JOIN service_categories sc ON sc.id = s.category_id
            WHERE s.status = 'published'
            ORDER BY s.display_order ASC
        ")->fetchAll();

        render_view('public/services', [
            'pageTitle' => 'Services & Capabilities | Orixo',
            'categories' => $categories,
            'services' => $services,
        ], 'layouts/public_layout');
    }

    public function serviceDetail(array $params): void
    {
        $this->checkMaintenance();
        $pdo = Database::connection();
        $stmt = $pdo->prepare("
            SELECT s.*, sc.name AS category_name
            FROM services s
            INNER JOIN service_categories sc ON sc.id = s.category_id
            WHERE s.slug = ? AND s.status = 'published'
            LIMIT 1
        ");
        $stmt->execute([$params['slug']]);
        $service = $stmt->fetch();
        if (!$service) {
            http_response_code(404);
            render_view('public/404', ['pageTitle' => '404 — Service Not Found | Orixo'], 'layouts/public_layout');
            return;
        }

        $techStmt = $pdo->prepare("
            SELECT t.* FROM technologies t
            INNER JOIN service_technologies st ON st.technology_id = t.id
            WHERE st.service_id = ? AND t.is_active = 1
            ORDER BY t.display_order ASC
        ");
        $techStmt->execute([$service['id']]);

        $relStmt = $pdo->prepare("
            SELECT p.*, pc.name AS category_name
            FROM portfolio_projects p
            INNER JOIN portfolio_categories pc ON pc.id = p.category_id
            WHERE p.service_id = ? AND p.status = 'published'
            ORDER BY p.is_featured DESC LIMIT 3
        ");
        $relStmt->execute([$service['id']]);

        render_view('public/service_detail', [
            'pageTitle' => ($service['seo_title'] ?: $service['title']) . ' | Orixo',
            'metaDescription' => $service['seo_description'] ?: $service['short_summary'],
            'service' => $service,
            'features' => $service['key_features_json'] ? json_decode((string) $service['key_features_json'], true) : [],
            'technologies' => $techStmt->fetchAll(),
            'relatedPortfolio' => $relStmt->fetchAll(),
        ], 'layouts/public_layout');
    }

    public function portfolio(): void
    {
        $this->checkMaintenance();
        $pdo = Database::connection();
        $categories = $pdo->query("SELECT * FROM portfolio_categories WHERE is_active = 1 ORDER BY display_order ASC")->fetchAll();
        $projects = $pdo->query("
            SELECT p.*, pc.name AS category_name, pc.slug AS category_slug
            FROM portfolio_projects p
            INNER JOIN portfolio_categories pc ON pc.id = p.category_id
            WHERE p.status = 'published'
            ORDER BY p.is_featured DESC, p.completed_year DESC, p.id DESC
        ")->fetchAll();

        render_view('public/portfolio', [
            'pageTitle' => 'Selected Work & Portfolio | Orixo',
            'categories' => $categories,
            'projects' => $projects,
        ], 'layouts/public_layout');
    }

    public function portfolioDetail(array $params): void
    {
        $this->checkMaintenance();
        $pdo = Database::connection();
        $stmt = $pdo->prepare("
            SELECT p.*, pc.name AS category_name, s.title AS service_title, s.slug AS service_slug, i.name AS industry_name
            FROM portfolio_projects p
            INNER JOIN portfolio_categories pc ON pc.id = p.category_id
            LEFT JOIN services s ON s.id = p.service_id
            LEFT JOIN industries i ON i.id = p.industry_id
            WHERE p.slug = ? AND p.status = 'published'
            LIMIT 1
        ");
        $stmt->execute([$params['slug']]);
        $project = $stmt->fetch();
        if (!$project) {
            http_response_code(404);
            render_view('public/404', ['pageTitle' => '404 — Project Not Found | Orixo'], 'layouts/public_layout');
            return;
        }

        $imgStmt = $pdo->prepare("SELECT * FROM portfolio_images WHERE portfolio_project_id = ? ORDER BY display_order ASC");
        $imgStmt->execute([$project['id']]);

        $techStmt = $pdo->prepare("
            SELECT t.* FROM technologies t
            INNER JOIN portfolio_technologies pt ON pt.technology_id = t.id
            WHERE pt.portfolio_project_id = ? AND t.is_active = 1
        ");
        $techStmt->execute([$project['id']]);

        render_view('public/portfolio_detail', [
            'pageTitle' => $project['title'] . ' | Orixo Portfolio',
            'project' => $project,
            'gallery' => $imgStmt->fetchAll(),
            'technologies' => $techStmt->fetchAll(),
        ], 'layouts/public_layout');
    }

    public function caseStudies(): void
    {
        $this->checkMaintenance();
        $pdo = Database::connection();
        $studies = $pdo->query("
            SELECT cs.*, i.name AS industry_name, s.title AS service_title
            FROM case_studies cs
            LEFT JOIN industries i ON i.id = cs.industry_id
            LEFT JOIN services s ON s.id = cs.service_id
            WHERE cs.status = 'published'
            ORDER BY cs.is_featured DESC, cs.id DESC
        ")->fetchAll();

        render_view('public/case_studies', [
            'pageTitle' => 'Case Studies | Orixo',
            'caseStudies' => $studies,
        ], 'layouts/public_layout');
    }

    public function caseStudyDetail(array $params): void
    {
        $this->checkMaintenance();
        $pdo = Database::connection();
        $stmt = $pdo->prepare("
            SELECT cs.*, i.name AS industry_name, i.slug AS industry_slug, s.title AS service_title, s.slug AS service_slug
            FROM case_studies cs
            LEFT JOIN industries i ON i.id = cs.industry_id
            LEFT JOIN services s ON s.id = cs.service_id
            WHERE cs.slug = ? AND cs.status = 'published'
            LIMIT 1
        ");
        $stmt->execute([$params['slug']]);
        $study = $stmt->fetch();
        if (!$study) {
            http_response_code(404);
            render_view('public/404', ['pageTitle' => '404 — Case Study Not Found | Orixo'], 'layouts/public_layout');
            return;
        }

        $techStmt = $pdo->prepare("
            SELECT t.* FROM technologies t
            INNER JOIN case_study_technologies ct ON ct.technology_id = t.id
            WHERE ct.case_study_id = ? AND t.is_active = 1
        ");
        $techStmt->execute([$study['id']]);

        render_view('public/case_study_detail', [
            'pageTitle' => $study['title'] . ' | Case Study — Orixo',
            'study' => $study,
            'metrics' => $study['metrics_json'] ? json_decode((string) $study['metrics_json'], true) : [],
            'technologies' => $techStmt->fetchAll(),
        ], 'layouts/public_layout');
    }

    public function industries(): void
    {
        $this->checkMaintenance();
        $industries = Database::connection()->query("SELECT * FROM industries WHERE status = 'published' ORDER BY display_order ASC")->fetchAll();
        render_view('public/industries', [
            'pageTitle' => 'Industries Served | Orixo',
            'industries' => $industries,
        ], 'layouts/public_layout');
    }

    public function industryDetail(array $params): void
    {
        $this->checkMaintenance();
        $pdo = Database::connection();
        $stmt = $pdo->prepare("SELECT * FROM industries WHERE slug = ? AND status = 'published' LIMIT 1");
        $stmt->execute([$params['slug']]);
        $industry = $stmt->fetch();
        if (!$industry) {
            http_response_code(404);
            render_view('public/404', ['pageTitle' => '404 — Industry Not Found | Orixo'], 'layouts/public_layout');
            return;
        }

        $svcStmt = $pdo->prepare("
            SELECT s.* FROM services s
            INNER JOIN industry_services ins ON ins.service_id = s.id
            WHERE ins.industry_id = ? AND s.status = 'published'
        ");
        $svcStmt->execute([$industry['id']]);

        $csStmt = $pdo->prepare("SELECT * FROM case_studies WHERE industry_id = ? AND status = 'published' ORDER BY is_featured DESC");
        $csStmt->execute([$industry['id']]);

        render_view('public/industry_detail', [
            'pageTitle' => $industry['name'] . ' Solutions | Orixo',
            'industry' => $industry,
            'services' => $svcStmt->fetchAll(),
            'caseStudies' => $csStmt->fetchAll(),
        ], 'layouts/public_layout');
    }

    public function ourProcess(): void
    {
        $this->checkMaintenance();
        $stmt = Database::connection()->prepare("SELECT * FROM cms_pages WHERE page_key = 'our_process' LIMIT 1");
        $stmt->execute();
        $page = $stmt->fetch();
        render_view('public/our_process', [
            'pageTitle' => 'Our Process | Orixo',
            'page' => $page,
            'steps' => $page && $page['structured_sections_json'] ? json_decode((string) $page['structured_sections_json'], true) : [],
        ], 'layouts/public_layout');
    }

    public function technologies(): void
    {
        $this->checkMaintenance();
        $rows = Database::connection()->query("SELECT * FROM technologies WHERE is_active = 1 ORDER BY category ASC, display_order ASC")->fetchAll();
        $grouped = [];
        foreach ($rows as $r) {
            $grouped[$r['category']][] = $r;
        }
        render_view('public/technologies', [
            'pageTitle' => 'Technology Expertise | Orixo',
            'groupedTech' => $grouped,
        ], 'layouts/public_layout');
    }

    public function team(): void
    {
        $this->checkMaintenance();
        $team = Database::connection()->query("
            SELECT tm.*, u.full_name, u.avatar_path
            FROM team_members tm
            INNER JOIN users u ON u.id = tm.user_id
            WHERE tm.show_on_public_website = 1 AND tm.status = 'active' AND u.status = 'active'
            ORDER BY tm.display_order ASC
        ")->fetchAll();
        render_view('public/team', [
            'pageTitle' => 'Our Team | Orixo',
            'team' => $team,
        ], 'layouts/public_layout');
    }

    public function contact(): void
    {
        $this->checkMaintenance();
        render_view('public/contact', [
            'pageTitle' => 'Contact Us | Orixo',
        ], 'layouts/public_layout');
    }

    public function projectInquiry(): void
    {
        $this->checkMaintenance();
        $services = Database::connection()->query("SELECT id, title FROM services WHERE status = 'published' ORDER BY display_order ASC")->fetchAll();
        render_view('public/project_inquiry', [
            'pageTitle' => 'Start a Project Inquiry | Orixo',
            'services' => $services,
            'selectedServiceId' => (int) ($_GET['service'] ?? 0),
        ], 'layouts/public_layout');
    }

    public function login(): void
    {
        if (!empty($_SESSION['user_id'])) {
            // Check if already logged in via quick lookup
            $stmt = Database::connection()->prepare("SELECT r.slug FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE u.id = ? AND u.status = 'active' LIMIT 1");
            $stmt->execute([(int) $_SESSION['user_id']]);
            $role = $stmt->fetchColumn();
            if ($role === 'admin') {
                redirect('/admin');
            } elseif ($role === 'team_member') {
                redirect('/admin/workspace');
            } elseif ($role === 'client') {
                redirect('/portal');
            }
        }
        $loginError = (string) ($_SESSION['_login_error'] ?? '');
        $loginEmail = (string) ($_SESSION['_login_email'] ?? '');
        unset($_SESSION['_login_error'], $_SESSION['_login_email']);

        render_view('public/login', [
            'pageTitle' => 'Client & Workspace Login | Orixo',
            'reason' => (string) ($_GET['reason'] ?? ''),
            'loginError' => $loginError,
            'loginEmail' => $loginEmail,
        ], 'layouts/public_layout');
    }

    public function handleLogin(): void
    {
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        $v = new \Orixo\Helpers\Validator();
        if (!$v->validate(['email' => $email, 'password' => $password], [
            'email' => 'required|email',
            'password' => 'required|min:6',
        ])) {
            $_SESSION['_login_error'] = 'Please enter a valid email address and password.';
            $_SESSION['_login_email'] = $email;
            redirect('/login');
        }

        $res = \Orixo\Services\AuthService::attemptLogin($email, $password);
        if (!$res['ok']) {
            $_SESSION['_login_error'] = $res['message'];
            $_SESSION['_login_email'] = $email;
            redirect('/login');
        }

        redirect($res['redirect']);
    }

    public function activateAccount(): void
    {
        render_view('public/activate_account', [
            'pageTitle' => 'Activate Your Account | Orixo',
            'token' => (string) ($_GET['token'] ?? ''),
        ], 'layouts/public_layout');
    }

    public function forgotPassword(): void
    {
        render_view('public/forgot_password', [
            'pageTitle' => 'Forgot Password | Orixo',
        ], 'layouts/public_layout');
    }

    public function resetPassword(): void
    {
        render_view('public/reset_password', [
            'pageTitle' => 'Reset Password | Orixo',
            'token' => (string) ($_GET['token'] ?? ''),
        ], 'layouts/public_layout');
    }

    public function legalPage(string $pageKey, string $fallbackTitle): void
    {
        $this->checkMaintenance();
        $stmt = Database::connection()->prepare("SELECT * FROM cms_pages WHERE page_key = ? LIMIT 1");
        $stmt->execute([$pageKey]);
        $page = $stmt->fetch();
        render_view('public/legal', [
            'pageTitle' => ($page['title'] ?? $fallbackTitle) . ' | Orixo',
            'page' => $page,
            'fallbackTitle' => $fallbackTitle,
        ], 'layouts/public_layout');
    }

    public function notFound(): void
    {
        http_response_code(404);
        render_view('public/404', ['pageTitle' => '404 — Page Not Found | Orixo'], 'layouts/public_layout');
    }

    public function forbidden(): void
    {
        http_response_code(403);
        render_view('public/403', ['pageTitle' => '403 — Access Denied | Orixo'], 'layouts/public_layout');
    }

    public function maintenance(): void
    {
        http_response_code(503);
        render_view('public/maintenance', ['pageTitle' => 'Scheduled Maintenance | Orixo'], 'layouts/public_layout');
    }
}
