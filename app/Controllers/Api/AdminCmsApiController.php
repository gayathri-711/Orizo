<?php
declare(strict_types=1);

namespace Orixo\Controllers\Api;

use Orixo\Bootstrap\Database;
use Orixo\Helpers\Validator;
use Orixo\Services\AuditService;
use Orixo\Services\FileService;

/**
 * Admin-only CMS CRUD JSON API (/api/v1/admin/cms/*).
 */
final class AdminCmsApiController
{
    public function listAll(): void
    {
        $pdo = Database::connection();
        json_response(true, 'CMS catalog loaded.', [
            'service_categories' => $pdo->query("SELECT * FROM service_categories ORDER BY display_order ASC")->fetchAll(),
            'services' => $pdo->query("SELECT s.*, sc.name AS category_name FROM services s INNER JOIN service_categories sc ON sc.id = s.category_id ORDER BY s.display_order ASC")->fetchAll(),
            'portfolio_categories' => $pdo->query("SELECT * FROM portfolio_categories ORDER BY display_order ASC")->fetchAll(),
            'portfolio' => $pdo->query("SELECT p.*, pc.name AS category_name FROM portfolio_projects p INNER JOIN portfolio_categories pc ON pc.id = p.category_id ORDER BY p.id DESC")->fetchAll(),
            'case_studies' => $pdo->query("SELECT cs.*, i.name AS industry_name FROM case_studies cs LEFT JOIN industries i ON i.id = cs.industry_id ORDER BY cs.id DESC")->fetchAll(),
            'industries' => $pdo->query("SELECT * FROM industries ORDER BY display_order ASC")->fetchAll(),
            'technologies' => $pdo->query("SELECT * FROM technologies ORDER BY category ASC, display_order ASC")->fetchAll(),
            'testimonials' => $pdo->query("SELECT * FROM testimonials ORDER BY display_order ASC")->fetchAll(),
            'cms_pages' => $pdo->query("SELECT * FROM cms_pages ORDER BY id ASC")->fetchAll(),
        ]);
    }

    public function saveServiceCategory(): void
    {
        $data = request_json();
        $v = new Validator();
        if (!$v->validate($data, ['name' => 'required|min:2|max:120'])) {
            json_response(false, 'Validation failed.', null, [], 422, $v->errors());
        }
        $pdo = Database::connection();
        $id = !empty($data['id']) ? (int) $data['id'] : 0;
        $slug = slugify((string) ($data['slug'] ?? $data['name']));
        if ($id > 0) {
            $pdo->prepare("UPDATE service_categories SET name = ?, slug = ?, description = ?, display_order = ?, is_active = ? WHERE id = ?")
                ->execute([trim((string) $data['name']), $slug, trim((string) ($data['description'] ?? '')) ?: null, (int) ($data['display_order'] ?? 0), isset($data['is_active']) ? ((int) $data['is_active'] ? 1 : 0) : 1, $id]);
        } else {
            $pdo->prepare("INSERT INTO service_categories (name, slug, description, display_order, is_active) VALUES (?, ?, ?, ?, ?)")
                ->execute([trim((string) $data['name']), $slug, trim((string) ($data['description'] ?? '')) ?: null, (int) ($data['display_order'] ?? 0), 1]);
            $id = (int) $pdo->lastInsertId();
        }
        json_response(true, 'Service category saved.', ['id' => $id]);
    }

    public function saveService(): void
    {
        $data = $_POST ?: request_json();
        $v = new Validator();
        if (!$v->validate($data, [
            'category_id' => 'required|numeric',
            'title' => 'required|min:2|max:160',
            'short_summary' => 'required|min:5|max:500',
            'status' => 'nullable|in:draft,published,archived',
        ])) {
            json_response(false, 'Validation failed.', null, [], 422, $v->errors());
        }

        $pdo = Database::connection();
        $id = !empty($data['id']) ? (int) $data['id'] : 0;
        $slug = slugify((string) ($data['slug'] ?? $data['title']));
        $imagePath = null;
        if (!empty($_FILES['image']) && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $imagePath = FileService::storePublicCmsImage($_FILES['image'], 'services');
        }

        $features = is_array($data['key_features'] ?? null)
            ? json_encode($data['key_features'])
            : json_encode(array_values(array_filter(array_map('trim', explode("\n", (string) ($data['key_features'] ?? ''))))));

        if ($id > 0) {
            $curImg = $pdo->query("SELECT featured_image_path FROM services WHERE id = {$id}")->fetchColumn();
            $pdo->prepare("
                UPDATE services SET category_id = ?, title = ?, slug = ?, short_summary = ?, introduction = ?, problems_addressed = ?, solutions_offered = ?, key_features_json = ?, icon_class = ?, featured_image_path = ?, is_featured = ?, status = ?, display_order = ?
                WHERE id = ?
            ")->execute([
                (int) $data['category_id'],
                trim((string) $data['title']),
                $slug,
                trim((string) $data['short_summary']),
                trim((string) ($data['introduction'] ?? $data['short_summary'])),
                trim((string) ($data['problems_addressed'] ?? '')),
                trim((string) ($data['solutions_offered'] ?? '')),
                $features,
                trim((string) ($data['icon_class'] ?? 'fa-layer-group')),
                $imagePath ?: $curImg,
                !empty($data['is_featured']) ? 1 : 0,
                $data['status'] ?? 'published',
                (int) ($data['display_order'] ?? 0),
                $id,
            ]);
        } else {
            $pdo->prepare("
                INSERT INTO services (category_id, title, slug, short_summary, introduction, problems_addressed, solutions_offered, key_features_json, icon_class, featured_image_path, is_featured, status, display_order)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                (int) $data['category_id'],
                trim((string) $data['title']),
                $slug,
                trim((string) $data['short_summary']),
                trim((string) ($data['introduction'] ?? $data['short_summary'])),
                trim((string) ($data['problems_addressed'] ?? '')),
                trim((string) ($data['solutions_offered'] ?? '')),
                $features,
                trim((string) ($data['icon_class'] ?? 'fa-layer-group')),
                $imagePath,
                !empty($data['is_featured']) ? 1 : 0,
                $data['status'] ?? 'published',
                (int) ($data['display_order'] ?? 0),
            ]);
            $id = (int) $pdo->lastInsertId();
        }
        AuditService::log((int) current_user()['id'], 'admin', 'cms.service_saved', 'service', $id, "Saved service '{$data['title']}'");
        json_response(true, 'Service saved.', ['id' => $id]);
    }

    public function deleteService(array $params): void
    {
        $id = (int) $params['id'];
        Database::connection()->prepare("UPDATE services SET status = 'archived' WHERE id = ?")->execute([$id]);
        json_response(true, 'Service archived.');
    }

    public function savePortfolioCategory(): void
    {
        $data = request_json();
        $v = new Validator();
        if (!$v->validate($data, ['name' => 'required|min:2|max:120'])) {
            json_response(false, 'Validation failed.', null, [], 422, $v->errors());
        }
        $pdo = Database::connection();
        $id = !empty($data['id']) ? (int) $data['id'] : 0;
        $slug = slugify((string) ($data['slug'] ?? $data['name']));
        if ($id > 0) {
            $pdo->prepare("UPDATE portfolio_categories SET name = ?, slug = ?, description = ?, display_order = ?, is_active = ? WHERE id = ?")
                ->execute([trim((string) $data['name']), $slug, trim((string) ($data['description'] ?? '')) ?: null, (int) ($data['display_order'] ?? 0), isset($data['is_active']) ? ((int) $data['is_active'] ? 1 : 0) : 1, $id]);
        } else {
            $pdo->prepare("INSERT INTO portfolio_categories (name, slug, description, display_order, is_active) VALUES (?, ?, ?, ?, 1)")
                ->execute([trim((string) $data['name']), $slug, trim((string) ($data['description'] ?? '')) ?: null, (int) ($data['display_order'] ?? 0)]);
            $id = (int) $pdo->lastInsertId();
        }
        json_response(true, 'Portfolio category saved.', ['id' => $id]);
    }

    public function savePortfolio(): void
    {
        $data = $_POST ?: request_json();
        $v = new Validator();
        if (!$v->validate($data, [
            'category_id' => 'required|numeric',
            'title' => 'required|min:2|max:180',
            'summary' => 'required|min:5|max:500',
        ])) {
            json_response(false, 'Validation failed.', null, [], 422, $v->errors());
        }
        $pdo = Database::connection();
        $id = !empty($data['id']) ? (int) $data['id'] : 0;
        $slug = slugify((string) ($data['slug'] ?? $data['title']));
        $imagePath = null;
        if (!empty($_FILES['image']) && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $imagePath = FileService::storePublicCmsImage($_FILES['image'], 'portfolio');
        }

        if ($id > 0) {
            $curImg = $pdo->query("SELECT cover_image_path FROM portfolio_projects WHERE id = {$id}")->fetchColumn();
            $pdo->prepare("
                UPDATE portfolio_projects
                SET category_id = ?, service_id = ?, industry_id = ?, title = ?, slug = ?, client_display_name = ?, summary = ?, challenge_summary = ?, solution_summary = ?, outcomes_summary = ?, cover_image_path = ?, is_featured = ?, status = ?, completed_year = ?
                WHERE id = ?
            ")->execute([
                (int) $data['category_id'],
                !empty($data['service_id']) ? (int) $data['service_id'] : null,
                !empty($data['industry_id']) ? (int) $data['industry_id'] : null,
                trim((string) $data['title']),
                $slug,
                trim((string) ($data['client_display_name'] ?? '')) ?: null,
                trim((string) $data['summary']),
                trim((string) ($data['challenge_summary'] ?? '')) ?: null,
                trim((string) ($data['solution_summary'] ?? '')) ?: null,
                trim((string) ($data['outcomes_summary'] ?? '')) ?: null,
                $imagePath ?: $curImg,
                !empty($data['is_featured']) ? 1 : 0,
                $data['status'] ?? 'published',
                !empty($data['completed_year']) ? (int) $data['completed_year'] : (int) date('Y'),
                $id,
            ]);
        } else {
            $pdo->prepare("
                INSERT INTO portfolio_projects
                (category_id, service_id, industry_id, title, slug, client_display_name, summary, challenge_summary, solution_summary, outcomes_summary, cover_image_path, is_featured, status, completed_year)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                (int) $data['category_id'],
                !empty($data['service_id']) ? (int) $data['service_id'] : null,
                !empty($data['industry_id']) ? (int) $data['industry_id'] : null,
                trim((string) $data['title']),
                $slug,
                trim((string) ($data['client_display_name'] ?? '')) ?: null,
                trim((string) $data['summary']),
                trim((string) ($data['challenge_summary'] ?? '')) ?: null,
                trim((string) ($data['solution_summary'] ?? '')) ?: null,
                trim((string) ($data['outcomes_summary'] ?? '')) ?: null,
                $imagePath,
                !empty($data['is_featured']) ? 1 : 0,
                $data['status'] ?? 'published',
                !empty($data['completed_year']) ? (int) $data['completed_year'] : (int) date('Y'),
            ]);
            $id = (int) $pdo->lastInsertId();
        }
        AuditService::log((int) current_user()['id'], 'admin', 'cms.portfolio_saved', 'portfolio_project', $id, "Saved portfolio '{$data['title']}'");
        json_response(true, 'Portfolio project saved.', ['id' => $id]);
    }

    public function deletePortfolio(array $params): void
    {
        Database::connection()->prepare("UPDATE portfolio_projects SET status = 'archived' WHERE id = ?")->execute([(int) $params['id']]);
        json_response(true, 'Portfolio project archived.');
    }

    public function saveCaseStudy(): void
    {
        $data = request_json();
        $v = new Validator();
        if (!$v->validate($data, ['title' => 'required|min:2|max:180', 'client_overview' => 'required', 'business_challenge' => 'required', 'proposed_solution' => 'required', 'final_outcome' => 'required'])) {
            json_response(false, 'Validation failed.', null, [], 422, $v->errors());
        }
        $pdo = Database::connection();
        $id = !empty($data['id']) ? (int) $data['id'] : 0;
        $slug = slugify((string) ($data['slug'] ?? $data['title']));
        if ($id > 0) {
            $pdo->prepare("UPDATE case_studies SET title = ?, slug = ?, industry_id = ?, service_id = ?, client_overview = ?, business_challenge = ?, proposed_solution = ?, implementation_approach = ?, final_outcome = ?, is_featured = ?, status = ? WHERE id = ?")
                ->execute([trim((string) $data['title']), $slug, !empty($data['industry_id']) ? (int) $data['industry_id'] : null, !empty($data['service_id']) ? (int) $data['service_id'] : null, $data['client_overview'], $data['business_challenge'], $data['proposed_solution'], $data['implementation_approach'] ?? '', $data['final_outcome'], !empty($data['is_featured']) ? 1 : 0, $data['status'] ?? 'published', $id]);
        } else {
            $pdo->prepare("INSERT INTO case_studies (title, slug, industry_id, service_id, client_overview, business_challenge, proposed_solution, implementation_approach, final_outcome, is_featured, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                ->execute([trim((string) $data['title']), $slug, !empty($data['industry_id']) ? (int) $data['industry_id'] : null, !empty($data['service_id']) ? (int) $data['service_id'] : null, $data['client_overview'], $data['business_challenge'], $data['proposed_solution'], $data['implementation_approach'] ?? '', $data['final_outcome'], !empty($data['is_featured']) ? 1 : 0, $data['status'] ?? 'published']);
            $id = (int) $pdo->lastInsertId();
        }
        json_response(true, 'Case study saved.', ['id' => $id]);
    }

    public function deleteCaseStudy(array $params): void
    {
        Database::connection()->prepare("UPDATE case_studies SET status = 'archived' WHERE id = ?")->execute([(int) $params['id']]);
        json_response(true, 'Case study archived.');
    }

    public function saveIndustry(): void
    {
        $data = request_json();
        $v = new Validator();
        if (!$v->validate($data, ['name' => 'required|min:2|max:140', 'short_summary' => 'required'])) {
            json_response(false, 'Validation failed.', null, [], 422, $v->errors());
        }
        $pdo = Database::connection();
        $id = !empty($data['id']) ? (int) $data['id'] : 0;
        $slug = slugify((string) ($data['slug'] ?? $data['name']));
        if ($id > 0) {
            $pdo->prepare("UPDATE industries SET name = ?, slug = ?, short_summary = ?, introduction = ?, digital_challenges = ?, available_solutions = ?, icon_class = ?, status = ? WHERE id = ?")
                ->execute([trim((string) $data['name']), $slug, $data['short_summary'], $data['introduction'] ?? $data['short_summary'], $data['digital_challenges'] ?? '', $data['available_solutions'] ?? '', $data['icon_class'] ?? 'fa-building', $data['status'] ?? 'published', $id]);
        } else {
            $pdo->prepare("INSERT INTO industries (name, slug, short_summary, introduction, digital_challenges, available_solutions, icon_class, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
                ->execute([trim((string) $data['name']), $slug, $data['short_summary'], $data['introduction'] ?? $data['short_summary'], $data['digital_challenges'] ?? '', $data['available_solutions'] ?? '', $data['icon_class'] ?? 'fa-building', $data['status'] ?? 'published']);
            $id = (int) $pdo->lastInsertId();
        }
        json_response(true, 'Industry saved.', ['id' => $id]);
    }

    public function saveTechnology(): void
    {
        $data = request_json();
        $v = new Validator();
        if (!$v->validate($data, ['name' => 'required|max:100', 'category' => 'required|in:frontend,backend,mobile,cloud_devops,ai_data,database,design'])) {
            json_response(false, 'Validation failed.', null, [], 422, $v->errors());
        }
        $pdo = Database::connection();
        $id = !empty($data['id']) ? (int) $data['id'] : 0;
        $slug = slugify((string) ($data['slug'] ?? $data['name']));
        if ($id > 0) {
            $pdo->prepare("UPDATE technologies SET name = ?, slug = ?, category = ?, description = ?, icon_class_or_path = ?, is_active = ? WHERE id = ?")
                ->execute([trim((string) $data['name']), $slug, $data['category'], $data['description'] ?? null, $data['icon_class_or_path'] ?? 'fa-solid fa-code', isset($data['is_active']) ? ((int) $data['is_active'] ? 1 : 0) : 1, $id]);
        } else {
            $pdo->prepare("INSERT INTO technologies (name, slug, category, description, icon_class_or_path, is_active) VALUES (?, ?, ?, ?, ?, 1)")
                ->execute([trim((string) $data['name']), $slug, $data['category'], $data['description'] ?? null, $data['icon_class_or_path'] ?? 'fa-solid fa-code']);
            $id = (int) $pdo->lastInsertId();
        }
        json_response(true, 'Technology saved.', ['id' => $id]);
    }

    public function saveTestimonial(): void
    {
        $data = request_json();
        $v = new Validator();
        if (!$v->validate($data, ['client_name' => 'required|max:120', 'designation' => 'required|max:120', 'company_name' => 'required|max:160', 'quote' => 'required|min:10'])) {
            json_response(false, 'Validation failed.', null, [], 422, $v->errors());
        }
        $pdo = Database::connection();
        $id = !empty($data['id']) ? (int) $data['id'] : 0;
        if ($id > 0) {
            $pdo->prepare("UPDATE testimonials SET client_name = ?, designation = ?, company_name = ?, quote = ?, rating = ?, is_featured = ?, status = ? WHERE id = ?")
                ->execute([trim((string) $data['client_name']), trim((string) $data['designation']), trim((string) $data['company_name']), trim((string) $data['quote']), (int) ($data['rating'] ?? 5), !empty($data['is_featured']) ? 1 : 0, $data['status'] ?? 'published', $id]);
        } else {
            $pdo->prepare("INSERT INTO testimonials (client_name, designation, company_name, quote, rating, is_featured, status) VALUES (?, ?, ?, ?, ?, ?, ?)")
                ->execute([trim((string) $data['client_name']), trim((string) $data['designation']), trim((string) $data['company_name']), trim((string) $data['quote']), (int) ($data['rating'] ?? 5), !empty($data['is_featured']) ? 1 : 0, $data['status'] ?? 'published']);
            $id = (int) $pdo->lastInsertId();
        }
        json_response(true, 'Testimonial saved.', ['id' => $id]);
    }

    public function saveCmsPage(array $params): void
    {
        $key = $params['key'];
        $data = request_json();
        $v = new Validator();
        if (!$v->validate($data, ['title' => 'required|max:180', 'content_body' => 'required'])) {
            json_response(false, 'Validation failed.', null, [], 422, $v->errors());
        }
        Database::connection()->prepare("UPDATE cms_pages SET title = ?, content_body = ?, seo_title = ?, seo_description = ?, updated_by = ? WHERE page_key = ?")
            ->execute([trim((string) $data['title']), (string) $data['content_body'], $data['seo_title'] ?? null, $data['seo_description'] ?? null, (int) current_user()['id'], $key]);
        AuditService::log((int) current_user()['id'], 'admin', 'cms.page_updated', 'cms_page', null, "Updated CMS page '{$key}'");
        json_response(true, 'CMS page updated.');
    }
}
