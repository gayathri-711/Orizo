<?php
declare(strict_types=1);

namespace Orixo\Controllers\Api;

use Orixo\Bootstrap\Database;
use Orixo\Helpers\Validator;
use Orixo\Middleware\RateLimitMiddleware;
use Orixo\Services\InquiryService;

/**
 * Public website catalog & inquiry JSON endpoints (/api/v1/public/*).
 */
final class PublicApiController
{
    public function services(): void
    {
        $pdo = Database::connection();
        $cat = trim((string) ($_GET['category'] ?? ''));
        $featured = isset($_GET['featured']) ? (int) $_GET['featured'] : null;

        $where = ["s.status = 'published'"];
        $params = [];
        if ($cat !== '') {
            $where[] = 'sc.slug = ?';
            $params[] = $cat;
        }
        if ($featured !== null) {
            $where[] = 's.is_featured = ?';
            $params[] = $featured ? 1 : 0;
        }

        $sql = "SELECT s.id, s.title, s.slug, s.short_summary, s.icon_class, s.is_featured, sc.name AS category_name, sc.slug AS category_slug
                FROM services s INNER JOIN service_categories sc ON sc.id = s.category_id
                WHERE " . implode(' AND ', $where) . " ORDER BY s.display_order ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        json_response(true, 'Services loaded.', $stmt->fetchAll());
    }

    public function portfolio(): void
    {
        $pdo = Database::connection();
        $cat = trim((string) ($_GET['category'] ?? ''));
        $tech = trim((string) ($_GET['technology'] ?? ''));

        $where = ["p.status = 'published'"];
        $params = [];
        $join = '';
        if ($cat !== '') {
            $where[] = 'pc.slug = ?';
            $params[] = $cat;
        }
        if ($tech !== '') {
            $join = 'INNER JOIN portfolio_technologies pt ON pt.portfolio_project_id = p.id INNER JOIN technologies t ON t.id = pt.technology_id';
            $where[] = 't.slug = ?';
            $params[] = $tech;
        }

        $sql = "SELECT DISTINCT p.id, p.title, p.slug, p.client_display_name, p.summary, p.outcomes_summary, p.completed_year, p.is_featured, pc.name AS category_name, pc.slug AS category_slug
                FROM portfolio_projects p
                INNER JOIN portfolio_categories pc ON pc.id = p.category_id
                {$join}
                WHERE " . implode(' AND ', $where) . " ORDER BY p.is_featured DESC, p.completed_year DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        json_response(true, 'Portfolio loaded.', $stmt->fetchAll());
    }

    public function caseStudies(): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->query("
            SELECT cs.id, cs.title, cs.slug, cs.client_overview, cs.final_outcome, cs.metrics_json, i.name AS industry_name, s.title AS service_title
            FROM case_studies cs
            LEFT JOIN industries i ON i.id = cs.industry_id
            LEFT JOIN services s ON s.id = cs.service_id
            WHERE cs.status = 'published'
            ORDER BY cs.is_featured DESC, cs.id DESC
        ");
        json_response(true, 'Case studies loaded.', $stmt->fetchAll());
    }

    public function submitContact(): void
    {
        RateLimitMiddleware::checkPublicAction('public_contact', 8, 600);
        $data = request_json();
        if (!empty($data['website_honeypot'])) {
            json_response(false, 'Invalid submission.', null, [], 400);
        }

        $v = new Validator();
        if (!$v->validate($data, [
            'full_name' => 'required|min:2|max:120',
            'email' => 'required|email|max:190',
            'phone' => 'nullable|max:30',
            'message' => 'required|min:10|max:4000',
        ])) {
            json_response(false, 'Please correct the highlighted fields.', null, [], 422, $v->errors());
        }

        $res = InquiryService::submit([
            'inquiry_type' => 'general_contact',
            'full_name' => $data['full_name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? '',
            'company_name' => $data['company_name'] ?? '',
            'project_title' => $data['subject'] ?? 'General Contact Message',
            'message_or_description' => $data['message'],
        ]);

        json_response(true, "Thank you! Your message reference is {$res['reference_code']}.", $res, [], 201);
    }

    public function submitInquiry(): void
    {
        RateLimitMiddleware::checkPublicAction('public_inquiry', 6, 600);
        $data = $_POST;
        if (!empty($data['website_honeypot'])) {
            json_response(false, 'Invalid submission.', null, [], 400);
        }

        $v = new Validator();
        if (!$v->validate($data, [
            'full_name' => 'required|min:2|max:120',
            'email' => 'required|email|max:190',
            'phone' => 'nullable|max:30',
            'company_name' => 'nullable|max:160',
            'service_id' => 'nullable|numeric',
            'project_title' => 'required|min:3|max:180',
            'message_or_description' => 'required|min:20|max:5000',
            'estimated_budget' => 'required|max:80',
            'expected_timeline' => 'required|max:80',
        ])) {
            json_response(false, 'Please complete all required inquiry fields.', null, [], 422, $v->errors());
        }

        try {
            $res = InquiryService::submit($data, $_FILES['attachment'] ?? null);
        } catch (\InvalidArgumentException $e) {
            json_response(false, $e->getMessage(), null, [], 422, ['attachment' => $e->getMessage()]);
        }

        json_response(true, "Inquiry submitted successfully! Reference: {$res['reference_code']}.", $res, [], 201);
    }
}
