<?php
declare(strict_types=1);

namespace Orixo\Database\Seeds;

use PDO;

/**
 * Idempotent database seeder for Orixo core system records and realistic digital agency demo data.
 */
final class SeedRunner
{
    public static function run(PDO $pdo): void
    {
        $pdo->beginTransaction();
        try {
            self::seedRoles($pdo);
            $userIds = self::seedUsersAndProfiles($pdo);
            self::seedSettings($pdo, $userIds['admin']);
            self::seedCmsPages($pdo, $userIds['admin']);
            self::seedEmailTemplates($pdo, $userIds['admin']);
            $cmsIds = self::seedAgencyCatalog($pdo);
            self::seedDemoOperations($pdo, $userIds, $cmsIds);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    private static function hashPassword(string $plain): string
    {
        $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
        return password_hash($plain, $algo);
    }

    private static function seedRoles(PDO $pdo): void
    {
        $roles = [
            ['admin', 'Administrator', 'Full platform and operations access'],
            ['team_member', 'Team Member', 'Scoped workspace access for assigned projects'],
            ['client', 'Client', 'Isolated client portal access for own projects and invoices'],
        ];
        $stmt = $pdo->prepare("INSERT IGNORE INTO roles (slug, name, description) VALUES (?, ?, ?)");
        foreach ($roles as $r) {
            $stmt->execute($r);
        }
    }

    private static function seedUsersAndProfiles(PDO $pdo): array
    {
        $roleMap = $pdo->query("SELECT slug, id FROM roles")->fetchAll(PDO::FETCH_KEY_PAIR);

        $ensureUser = function (string $roleSlug, string $email, string $name, string $phone, string $plainPass, ?string $avatar = null) use ($pdo, $roleMap): int {
            $q = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $q->execute([$email]);
            $existing = $q->fetchColumn();
            if ($existing) {
                return (int) $existing;
            }
            $ins = $pdo->prepare("
                INSERT INTO users (role_id, email, password_hash, full_name, phone, avatar_path, status, email_verified_at)
                VALUES (?, ?, ?, ?, ?, ?, 'active', NOW())
            ");
            $ins->execute([$roleMap[$roleSlug], $email, self::hashPassword($plainPass), $name, $phone, $avatar]);
            return (int) $pdo->lastInsertId();
        };

        $adminId = $ensureUser('admin', 'admin@orixo.studio', 'Vikramaditya Sen', '+91 98200 11223', 'OrixoAdmin@2026!');
        $leadId  = $ensureUser('team_member', 'arjun@orixo.studio', 'Arjun Mehta', '+91 98200 22334', 'OrixoTeam@2026!', '/assets/img/team/arjun-mehta.jpg');
        $uxId    = $ensureUser('team_member', 'meera@orixo.studio', 'Meera Krishnan', '+91 98200 33445', 'OrixoTeam@2026!', '/assets/img/team/meera-krishnan.jpg');
        $devId   = $ensureUser('team_member', 'kabir@orixo.studio', 'Kabir Verma', '+91 98200 44556', 'OrixoTeam@2026!', '/assets/img/team/kabir-verma.jpg');
        $cliUId  = $ensureUser('client', 'rohan@nexafin.in', 'Rohan Deshmukh', '+91 98111 77889', 'OrixoClient@2026!');

        $ensureTeam = function (int $userId, string $designation, string $bio, array $skills, int $showPublic, int $order) use ($pdo): int {
            $q = $pdo->prepare("SELECT id FROM team_members WHERE user_id = ?");
            $q->execute([$userId]);
            $id = $q->fetchColumn();
            if ($id) {
                return (int) $id;
            }
            $ins = $pdo->prepare("
                INSERT INTO team_members (user_id, designation, bio, skills_json, linkedin_url, show_on_public_website, display_order, status)
                VALUES (?, ?, ?, ?, 'https://linkedin.com', ?, ?, 'active')
            ");
            $ins->execute([$userId, $designation, $bio, json_encode($skills), $showPublic, $order]);
            return (int) $pdo->lastInsertId();
        };

        $leadTeamId = $ensureTeam($leadId, 'Principal Systems Architect', 'Leads enterprise web architecture, cloud infrastructure, and API engineering across fintech and SaaS engagements.', ['PHP 8', 'MySQL', 'AWS', 'System Design', 'Security'], 1, 1);
        $uxTeamId   = $ensureTeam($uxId, 'Head of Product & UI/UX Design', 'Crafts editorial digital flagships, enterprise design systems, and conversion-focused product interfaces.', ['Design Systems', 'Figma', 'UX Research', 'Prototyping'], 1, 2);
        $devTeamId  = $ensureTeam($devId, 'Senior Frontend & Motion Engineer', 'Builds accessible, responsive web applications with disciplined interaction craft and performance budgets.', ['HTML5', 'CSS3', 'JavaScript', 'jQuery', 'Bootstrap 5'], 1, 3);

        $qClient = $pdo->prepare("SELECT id FROM clients WHERE user_id = ?");
        $qClient->execute([$cliUId]);
        $clientId = $qClient->fetchColumn();
        if (!$clientId) {
            $insC = $pdo->prepare("
                INSERT INTO clients (user_id, company_name, billing_address, tax_id, website_url, notes, status)
                VALUES (?, 'NexaFin Technologies Pvt Ltd', 'Level 9, One BKC, Bandra Kurla Complex, Mumbai 400051, India', '27AABCN1234F1Z5', 'https://nexafin.in', 'Enterprise fintech client — core web portal & design system engagement.', 'active')
            ");
            $insC->execute([$cliUId]);
            $clientId = (int) $pdo->lastInsertId();
        }

        return [
            'admin' => $adminId,
            'lead_user' => $leadId,
            'ux_user' => $uxId,
            'dev_user' => $devId,
            'client_user' => $cliUId,
            'lead_team' => $leadTeamId,
            'ux_team' => $uxTeamId,
            'dev_team' => $devTeamId,
            'client' => (int) $clientId,
        ];
    }

    private static function seedSettings(PDO $pdo, int $adminId): void
    {
        $settings = [
            ['site.name', 'website', 'Orixo', 1],
            ['site.tagline', 'website', 'Creative Digital Solutions & Innovation Studio', 1],
            ['site.email', 'website', 'hello@orixo.studio', 1],
            ['site.phone', 'website', '+91 22 4890 3210', 1],
            ['site.address', 'website', '402 Studio Crest, Lower Parel, Mumbai 400013, India', 1],
            ['site.footer_note', 'website', 'Engineering purposeful digital products, brand systems, and enterprise platforms.', 1],
            ['site.social_linkedin', 'website', 'https://linkedin.com/company/orixo-studio', 1],
            ['site.social_github', 'website', 'https://github.com/orixo-studio', 1],
            ['site.social_dribbble', 'website', 'https://dribbble.com/orixo-studio', 1],
            ['seo.default_title', 'seo', 'Orixo — Creative Digital Solutions & Innovation Studio', 1],
            ['seo.default_description', 'seo', 'Enterprise web engineering, UI/UX design systems, cloud architecture, and AI automation for ambitious organizations.', 1],
            ['finance.default_currency', 'finance', 'INR', 0],
            ['finance.default_tax_rate', 'finance', '18.00', 0],
            ['finance.tax_label', 'finance', 'GST (18%)', 0],
            ['finance.invoice_prefix', 'finance', 'INV-2026-', 0],
            ['finance.bank_details', 'finance', "Account Name: Orixo Digital Studio Pvt Ltd\nBank: HDFC Bank, Lower Parel Branch\nAccount No: 50200048912345\nIFSC: HDFC0000142\nUPI ID: orixostudio@hdfcbank", 1],
            ['email.reply_to', 'email', 'hello@orixo.studio', 0],
            ['system.enable_blog', 'system', '0', 1],
            ['system.maintenance_mode', 'system', '0', 0],
        ];
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO app_settings (setting_key, setting_group, setting_value, is_public, updated_by)
            VALUES (?, ?, ?, ?, ?)
        ");
        foreach ($settings as $s) {
            $stmt->execute([$s[0], $s[1], $s[2], $s[3], $adminId]);
        }
    }

    private static function seedCmsPages(PDO $pdo, int $adminId): void
    {
        $pages = [
            [
                'about',
                'About Orixo Studio',
                '<p>Orixo is an independent creative-technology studio partnering with growth-stage companies and enterprises to design, architect, and ship resilient digital products.</p><p>Founded on the conviction that engineering precision and editorial design belong in the same room, our multidisciplinary teams combine product strategy, UI/UX systems, full-stack PHP/cloud engineering, and applied AI automation into cohesive commercial platforms.</p>',
                json_encode([
                    'vision' => 'To set the benchmark for calm, durable, commercially measurable digital craftsmanship.',
                    'mission' => 'Replace fragmented agency handoffs with integrated strategy, design, and engineering squads accountable to business outcomes.',
                    'values' => [
                        ['title' => 'Architectural Clarity', 'desc' => 'Every interface and database schema is designed to remain maintainable years after launch.'],
                        ['title' => 'Radical Transparency', 'desc' => 'Clients follow real milestones, deliverables, and budgets inside our dedicated Client Portal.'],
                        ['title' => 'Measurable Craft', 'desc' => 'Aesthetics serve conversion, speed, accessibility, and operational reliability.'],
                    ],
                ]),
            ],
            [
                'our_process',
                'Our 8-Stage Delivery Methodology',
                '<p>Great software is predictable by design. Every Orixo engagement progresses through eight structured checkpoints with full visibility in your Client Portal.</p>',
                json_encode([
                    ['step' => '01', 'title' => 'Discovery', 'desc' => 'Stakeholder interviews, competitive audit, and technical landscape assessment.'],
                    ['step' => '02', 'title' => 'Requirement Analysis', 'desc' => 'Functional specifications, user roles, compliance constraints, and acceptance criteria.'],
                    ['step' => '03', 'title' => 'Strategy & Planning', 'desc' => 'Information architecture, relational data modeling, milestone schedule, and risk plan.'],
                    ['step' => '04', 'title' => 'UI/UX Design', 'desc' => 'Editorial design tokens, interactive wireframes, component systems, and usability review.'],
                    ['step' => '05', 'title' => 'Development', 'desc' => 'Clean modular backend engineering, secure APIs, and responsive frontend implementation.'],
                    ['step' => '06', 'title' => 'Quality Assurance', 'desc' => 'Role-permission verification, cross-device responsiveness, security hardening, and load checks.'],
                    ['step' => '07', 'title' => 'Deployment', 'desc' => 'Zero-downtime release, SSL hardening, database migrations, and observability setup.'],
                    ['step' => '08', 'title' => 'Maintenance & Support', 'desc' => 'Proactive patching, performance tuning, and iterative product evolution.'],
                ]),
            ],
            [
                'privacy_policy',
                'Privacy Policy',
                '<h3>1. Information We Collect</h3><p>Orixo Digital Studio collects contact details, company information, and project briefs voluntarily submitted via our Contact and Project Inquiry forms, as well as operational records required to deliver contracted client portal services.</p><h3>2. How We Use Information</h3><p>Submitted inquiry data is used strictly to evaluate project scope, communicate regarding engagements, provision authorized Client Portal access, and fulfill billing and legal obligations.</p><h3>3. Data Security & Private File Storage</h3><p>Client uploads, agreements, deliverables, and invoices are stored outside the public webroot and served exclusively over authenticated, role-verified sessions.</p><h3>4. Contact</h3><p>For privacy or data deletion inquiries, contact hello@orixo.studio.</p>',
                null,
            ],
            [
                'terms_conditions',
                'Terms and Conditions',
                '<h3>1. Scope of Services</h3><p>All digital design, software engineering, consulting, and maintenance services delivered by Orixo Digital Studio are governed by mutually agreed Statements of Work (SOW) and milestone schedules.</p><h3>2. Client Portal & Confidentiality</h3><p>Client Portal credentials are provisioned strictly by Orixo administrators following project approval. Clients are responsible for safeguarding account passwords and authorized uploads.</p><h3>3. Intellectual Property & Deliverables</h3><p>Official deliverables published through the Client Portal transfer usage or ownership rights upon full settlement of associated invoices as specified in the governing service agreement.</p><h3>4. Invoicing & Payments</h3><p>Invoices are issued in the agreed billing currency (default INR) with payment terms specified per invoice. Official receipts are recorded upon verified settlement.</p>',
                null,
            ],
        ];
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO cms_pages (page_key, title, content_body, structured_sections_json, updated_by)
            VALUES (?, ?, ?, ?, ?)
        ");
        foreach ($pages as $p) {
            $stmt->execute([$p[0], $p[1], $p[2], $p[3], $adminId]);
        }
    }

    private static function seedEmailTemplates(PDO $pdo, int $adminId): void
    {
        $templates = [
            ['client_inquiry_confirmation', 'Client Inquiry Confirmation', 'We received your inquiry ({{reference_code}}) — Orixo Studio', '<p>Hi {{full_name}},</p><p>Thank you for contacting <strong>Orixo Studio</strong>. Your reference number is <strong>{{reference_code}}</strong>.</p><p>Our solutions team is reviewing your request and will respond within 1 business day.</p>'],
            ['admin_inquiry_notification', 'Admin Inquiry Notification', 'New {{inquiry_type_label}}: {{reference_code}} from {{full_name}}', '<p>A new submission has arrived:</p><ul><li><strong>Reference:</strong> {{reference_code}}</li><li><strong>Name:</strong> {{full_name}} ({{email}})</li><li><strong>Company:</strong> {{company_name}}</li><li><strong>Summary:</strong> {{summary}}</li></ul><p><a href="{{action_url}}">Review in Admin Dashboard</a></p>'],
            ['client_account_activation', 'Client Account Activation', 'Activate your Orixo Client Portal account', '<p>Hello {{full_name}},</p><p>Your project <strong>{{project_title}}</strong> has been approved at Orixo Studio. Activate your Client Portal account within 48 hours:</p><p><a href="{{activation_url}}">Activate My Account</a></p><p style="font-size:12px;color:#52525B;word-break:break-all;">Or copy and paste this link into your browser:<br>{{activation_url}}</p>'],
            ['password_reset', 'Password Reset', 'Reset your Orixo password', '<p>Hello {{full_name}},</p><p>Use the secure link below to reset your password (valid for 30 minutes):</p><p><a href="{{reset_url}}">Reset Password</a></p><p>If you did not request this, you can safely ignore this email.</p>'],
            ['project_created', 'Project Created', 'New project initialized: {{project_title}} ({{project_code}})', '<p>Hello {{full_name}},</p><p>Your project <strong>{{project_title}}</strong> (<code>{{project_code}}</code>) is now live in your Client Portal.</p><p><a href="{{action_url}}">Open Project Overview</a></p>'],
            ['project_assigned', 'Project Assigned to Team Member', 'You have been assigned to {{project_title}} ({{project_code}})', '<p>Hi {{full_name}},</p><p>You have been assigned to <strong>{{project_title}}</strong> as <strong>{{project_role}}</strong>.</p><p><a href="{{action_url}}">Open Team Workspace</a></p>'],
            ['project_updated', 'Project & Milestone Update', 'Update on {{project_title}}: {{update_summary}}', '<p>Hello {{full_name}},</p><p>There is a new update on <strong>{{project_title}}</strong>: {{update_summary}}</p><p><a href="{{action_url}}">View Timeline</a></p>'],
            ['task_updated', 'Task Status Update', 'Task update on {{project_title}}: {{task_title}}', '<p>Hi {{full_name}},</p><p>Task <strong>{{task_title}}</strong> is now <strong>{{task_status}}</strong>.</p><p><a href="{{action_url}}">View Tasks</a></p>'],
            ['deliverable_published', 'Deliverable Published', 'New deliverable published: {{deliverable_title}}', '<p>Hello {{full_name}},</p><p>An official deliverable (<strong>{{deliverable_title}}</strong>, {{version_label}}) has been published to your Client Portal.</p><p><a href="{{action_url}}">Download Deliverable</a></p>'],
            ['new_project_message', 'New Project Message', 'New message on {{project_title}} from {{sender_name}}', '<p>Hello {{full_name}},</p><p><strong>{{sender_name}}</strong> posted a message on <strong>{{project_title}}</strong>:</p><blockquote>{{message_excerpt}}</blockquote><p><a href="{{action_url}}">Reply in Portal</a></p>'],
            ['invoice_issued', 'Invoice Issued', 'Invoice {{invoice_number}} issued ({{formatted_total}})', '<p>Hello {{full_name}},</p><p>Invoice <strong>{{invoice_number}}</strong> for <strong>{{project_title}}</strong> has been issued.</p><ul><li><strong>Total Amount:</strong> {{formatted_total}}</li><li><strong>Due Date:</strong> {{due_date}}</li></ul><p><a href="{{action_url}}">View & Download PDF</a></p>'],
            ['payment_recorded', 'Payment Recorded', 'Payment received for Invoice {{invoice_number}} ({{formatted_amount}})', '<p>Hello {{full_name}},</p><p>We have recorded your payment of <strong>{{formatted_amount}}</strong> ({{payment_reference}}) against Invoice <strong>{{invoice_number}}</strong>.</p><p>Remaining Balance: <strong>{{formatted_balance}}</strong></p>'],
            ['payment_reminder', 'Payment Reminder', 'Payment reminder: Invoice {{invoice_number}} ({{formatted_balance}} due)', '<p>Hello {{full_name}},</p><p>This is a gentle reminder that Invoice <strong>{{invoice_number}}</strong> has an outstanding balance of <strong>{{formatted_balance}}</strong> (Due: {{due_date}}).</p><p><a href="{{action_url}}">View Invoice</a></p>'],
            ['email_delivery_failure_admin', 'Email Delivery Failure Alert', '[Action Required] Email delivery permanently failed (#{{queue_id}})', '<p>Email queue job <strong>#{{queue_id}}</strong> to <code>{{recipient_email}}</code> ({{template_key}}) failed after maximum retries.</p><p><strong>Error:</strong> {{last_error}}</p>'],
        ];
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO email_templates (template_key, name, subject_template, body_html_template, is_active, updated_by)
            VALUES (?, ?, ?, ?, 1, ?)
        ");
        foreach ($templates as $t) {
            $stmt->execute([$t[0], $t[1], $t[2], $t[3], $adminId]);
        }
    }

    private static function seedAgencyCatalog(PDO $pdo): array
    {
        // Service categories
        $scStmt = $pdo->prepare("INSERT IGNORE INTO service_categories (name, slug, description, display_order) VALUES (?, ?, ?, ?)");
        $scStmt->execute(['Product & Web Engineering', 'product-web-engineering', 'Custom web platforms, mobile apps, and enterprise software systems.', 1]);
        $scStmt->execute(['Design & Brand Systems', 'design-brand-systems', 'Editorial UI/UX design, brand identity, and design systems.', 2]);
        $scStmt->execute(['Cloud, AI & Growth', 'cloud-ai-growth', 'Cloud DevOps, AI automation workflows, SEO, and technology consulting.', 3]);
        $scMap = $pdo->query("SELECT slug, id FROM service_categories")->fetchAll(PDO::FETCH_KEY_PAIR);

        // Services (10 core services)
        $servicesData = [
            [$scMap['product-web-engineering'], 'Website Design & Development', 'website-design-development', 'Bespoke editorial websites engineered for speed, conversion, and effortless content management.', 'fa-globe', 1, 1],
            [$scMap['product-web-engineering'], 'Web Application Development', 'web-application-development', 'Full-stack SaaS products, client portals, and operational web applications built on resilient databases.', 'fa-layer-group', 1, 2],
            [$scMap['product-web-engineering'], 'Mobile Application Development', 'mobile-application-development', 'Cross-platform and native mobile experiences connected to secure enterprise APIs.', 'fa-mobile-screen-button', 0, 3],
            [$scMap['design-brand-systems'], 'UI/UX Design', 'ui-ux-design', 'Research-led product design, information architecture, and scalable UI component libraries.', 'fa-compass-drafting', 1, 4],
            [$scMap['product-web-engineering'], 'Enterprise Software Solutions', 'enterprise-software-solutions', 'Custom ERP, workflow automation, and internal business systems tailored to complex operations.', 'fa-building-columns', 1, 5],
            [$scMap['design-brand-systems'], 'Branding & Creative Design', 'branding-creative-design', 'Cohesive visual identities, typography systems, and digital brand guidelines.', 'fa-pen-nib', 0, 6],
            [$scMap['cloud-ai-growth'], 'Digital Marketing & SEO', 'digital-marketing-seo', 'Technical SEO, structured schema architecture, and performance-driven organic acquisition.', 'fa-chart-line', 0, 7],
            [$scMap['cloud-ai-growth'], 'AI & Automation Solutions', 'ai-automation-solutions', 'Intelligent document processing, workflow orchestration, and custom LLM integrations.', 'fa-microchip', 1, 8],
            [$scMap['cloud-ai-growth'], 'Cloud & DevOps Solutions', 'cloud-devops-solutions', 'Automated CI/CD pipelines, containerization, security hardening, and high-availability hosting.', 'fa-cloud', 0, 9],
            [$scMap['cloud-ai-growth'], 'Technology Consulting', 'technology-consulting', 'Architecture audits, legacy modernization roadmaps, and CTO advisory.', 'fa-chess-knight', 0, 10],
        ];
        $sStmt = $pdo->prepare("
            INSERT IGNORE INTO services
            (category_id, title, slug, short_summary, introduction, problems_addressed, solutions_offered, key_features_json, icon_class, is_featured, status, display_order)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'published', ?)
        ");
        foreach ($servicesData as $sv) {
            $sStmt->execute([
                $sv[0],
                $sv[1],
                $sv[2],
                $sv[3],
                "{$sv[1]} at Orixo combines strategic discovery with disciplined engineering so your digital platform performs reliably under real commercial growth.",
                "Fragmented user journeys, slow legacy templates, manual spreadsheet workflows, and brittle integrations limit scalability.",
                "We deliver clean modular architecture, accessible responsive interfaces, automated validation, and transparent milestone governance.",
                json_encode(['Architecture & Scope Blueprint', 'Responsive Cross-Device Craft', 'Security & Performance Hardening', 'Post-Launch Documentation & Handover']),
                $sv[4],
                $sv[5],
                $sv[6],
            ]);
        }
        $svcMap = $pdo->query("SELECT slug, id FROM services")->fetchAll(PDO::FETCH_KEY_PAIR);

        // Industries
        $industriesData = [
            ['Fintech & Financial Services', 'fintech-financial-services', 'Secure customer portals, onboarding automation, and compliance-ready financial dashboards.', 'fa-landmark', 1],
            ['B2B SaaS & Enterprise Tech', 'b2b-saas-enterprise', 'High-converting marketing flagships paired with multi-tenant operational web apps.', 'fa-cubes', 2],
            ['Logistics & Supply Chain', 'logistics-supply-chain', 'Real-time dispatch visibility, partner portals, and automated document pipelines.', 'fa-truck-fast', 3],
            ['Manufacturing & Industrial', 'manufacturing-industrial', 'Dealer portals, procurement workflows, and modernized digital brand presence.', 'fa-industry', 4],
        ];
        $iStmt = $pdo->prepare("
            INSERT IGNORE INTO industries
            (name, slug, short_summary, introduction, digital_challenges, available_solutions, icon_class, status, display_order)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'published', ?)
        ");
        foreach ($industriesData as $ind) {
            $iStmt->execute([
                $ind[0],
                $ind[1],
                $ind[2],
                "Orixo partners with organizations across {$ind[0]} to modernize customer touchpoints and core operational tooling.",
                "Disparate legacy tools, manual approval bottlenecks, and inconsistent client communication create operational drag.",
                "Unified web portals, role-based operational dashboards, automated notifications, and resilient relational data models.",
                $ind[3],
                $ind[4],
            ]);
        }
        $indMap = $pdo->query("SELECT slug, id FROM industries")->fetchAll(PDO::FETCH_KEY_PAIR);

        // Technologies
        $techs = [
            ['PHP 8.2+', 'php-8', 'backend', 'Modern strongly-typed server-side PHP architecture.', 'fa-brands fa-php', 1],
            ['MySQL 8', 'mysql-8', 'database', 'ACID-compliant relational data modeling with InnoDB.', 'fa-solid fa-database', 2],
            ['HTML5 & CSS3', 'html5-css3', 'frontend', 'Semantic markup, CSS Grid, custom properties, and fluid typography.', 'fa-brands fa-html5', 3],
            ['JavaScript & jQuery', 'javascript-jquery', 'frontend', 'Interactive client behaviors and streamlined AJAX workflows.', 'fa-brands fa-js', 4],
            ['Bootstrap 5', 'bootstrap-5', 'frontend', 'Responsive 12-column layout system customized with brand tokens.', 'fa-brands fa-bootstrap', 5],
            ['Docker & Cloud CI', 'docker-cloud', 'cloud_devops', 'Reproducible deployments, TLS termination, and automated backups.', 'fa-brands fa-docker', 6],
            ['Applied AI Workflows', 'applied-ai', 'ai_data', 'Document extraction, classification, and automated triage pipelines.', 'fa-solid fa-wand-magic-sparkles', 7],
            ['Figma Design Systems', 'figma', 'design', 'Tokenized multi-surface UI kits and interactive prototypes.', 'fa-brands fa-figma', 8],
        ];
        $tStmt = $pdo->prepare("
            INSERT IGNORE INTO technologies (name, slug, category, description, icon_class_or_path, is_active, display_order)
            VALUES (?, ?, ?, ?, ?, 1, ?)
        ");
        foreach ($techs as $t) {
            $tStmt->execute($t);
        }
        $techMap = $pdo->query("SELECT slug, id FROM technologies")->fetchAll(PDO::FETCH_KEY_PAIR);

        // Portfolio categories & projects
        $pcStmt = $pdo->prepare("INSERT IGNORE INTO portfolio_categories (name, slug, description, display_order) VALUES (?, ?, ?, ?)");
        $pcStmt->execute(['Web Platforms', 'web-platforms', 'Custom enterprise web applications and client portals.', 1]);
        $pcStmt->execute(['Brand & Editorial Web', 'brand-editorial-web', 'Flagship brand websites and design systems.', 2]);
        $pcStmt->execute(['Enterprise Automation', 'enterprise-automation', 'Operational software and AI workflow integrations.', 3]);
        $pcMap = $pdo->query("SELECT slug, id FROM portfolio_categories")->fetchAll(PDO::FETCH_KEY_PAIR);

        $portfolios = [
            [$pcMap['web-platforms'], $svcMap['web-application-development'], $indMap['fintech-financial-services'], 'NexaFin Wealth Client Portal', 'nexafin-wealth-client-portal', 'NexaFin Technologies', 'Unified investor onboarding, document vault, and INR invoice tracking portal.', 'Fragmented email document exchanges delayed onboarding by 11 days.', 'Engineered an isolated role-based client portal with encrypted document review and automated milestone alerts.', 'Onboarding turnaround dropped by 64% across 4,200+ active client accounts.', '/assets/img/portfolio/nexafin-portal.jpg', 1, 2026],
            [$pcMap['brand-editorial-web'], $svcMap['website-design-development'], $indMap['b2b-saas-enterprise'], 'Kestrel Cloud Editorial Flagship', 'kestrel-cloud-flagship', 'Kestrel Infrastructure', 'High-contrast editorial website and interactive solution explorer for an enterprise cloud vendor.', 'Generic SaaS templates failed to differentiate Kestrel among enterprise buyers.', 'Designed an asymmetric editorial visual system paired with structured case studies and inquiry qualification.', 'Qualified enterprise inbound leads increased 2.4x within 90 days of launch.', '/assets/img/portfolio/kestrel-cloud.jpg', 1, 2026],
            [$pcMap['enterprise-automation'], $svcMap['enterprise-software-solutions'], $indMap['logistics-supply-chain'], 'Meridian Freight Ops Console', 'meridian-freight-ops', 'Meridian Logistics', 'Role-restricted dispatch, milestone tracking, and carrier billing platform.', 'Zero visibility across regional hubs caused frequent invoice discrepancies.', 'Built a unified operations console with strict branch-level RBAC and auditable invoice ledgers.', 'Eliminated 92% of manual reconciliation errors across 14 regional hubs.', '/assets/img/portfolio/meridian-ops.jpg', 1, 2025],
        ];
        $ppStmt = $pdo->prepare("
            INSERT IGNORE INTO portfolio_projects
            (category_id, service_id, industry_id, title, slug, client_display_name, summary, challenge_summary, solution_summary, outcomes_summary, cover_image_path, is_featured, status, completed_year)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'published', ?)
        ");
        foreach ($portfolios as $p) {
            $ppStmt->execute($p);
        }
        $ppMap = $pdo->query("SELECT slug, id FROM portfolio_projects")->fetchAll(PDO::FETCH_KEY_PAIR);

        // Case studies
        $csStmt = $pdo->prepare("
            INSERT IGNORE INTO case_studies
            (portfolio_project_id, industry_id, service_id, title, slug, client_overview, business_challenge, proposed_solution, implementation_approach, final_outcome, metrics_json, cover_image_path, is_featured, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 'published')
        ");
        $csStmt->execute([
            $ppMap['nexafin-wealth-client-portal'],
            $indMap['fintech-financial-services'],
            $svcMap['web-application-development'],
            'Cutting Fintech Onboarding Time by 64% via Private Client Portals',
            'nexafin-onboarding-transformation',
            'NexaFin Technologies manages high-touch corporate treasury onboarding across India.',
            'Advisors tracked KYC documents and fee invoices across email threads and spreadsheets, creating compliance gaps and slow turnaround.',
            'A dedicated PHP/MySQL Client Portal with isolated project workspaces, structured Client Uploads, deliverable sign-off, and automated INR invoicing.',
            'Delivered in 4 phased milestones: relational schema design, RBAC middleware, private non-webroot file streaming, and Dompdf billing.',
            'Reduced average onboarding cycle from 14 days to 5 days with 100% auditability.',
            json_encode([['label' => 'Onboarding Speed', 'value' => '64% Faster'], ['label' => 'Document Compliance', 'value' => '100% Audited'], ['label' => 'Support Tickets', 'value' => '-41%']]),
            '/assets/img/portfolio/nexafin-portal.jpg',
        ]);
        $csStmt->execute([
            $ppMap['kestrel-cloud-flagship'],
            $indMap['b2b-saas-enterprise'],
            $svcMap['ui-ux-design'],
            'Re-Architecting Enterprise Lead Conversion for Kestrel Cloud',
            'kestrel-enterprise-lead-conversion',
            'Kestrel Cloud provides multi-region Kubernetes orchestration for mid-market engineering teams.',
            'Their legacy template website had a 78% bounce rate on service pages and unstructured contact inquiries.',
            'Editorial Vermilion-inspired visual overhaul with problem-solution service matrices and structured project inquiry intake.',
            'Conducted buyer interviews, rewrote technical positioning, and integrated dynamic portfolio/case-study cross-linking.',
            '2.4x increase in qualified enterprise discovery calls within one quarter.',
            json_encode([['label' => 'Qualified Leads', 'value' => '2.4x'], ['label' => 'Page Speed Score', 'value' => '98/100'], ['label' => 'Bounce Reduction', 'value' => '-33%']]),
            '/assets/img/portfolio/kestrel-cloud.jpg',
        ]);
        $csMap = $pdo->query("SELECT slug, id FROM case_studies")->fetchAll(PDO::FETCH_KEY_PAIR);

        // Testimonials
        $testStmt = $pdo->prepare("
            INSERT IGNORE INTO testimonials (client_name, designation, company_name, quote, portfolio_project_id, rating, is_featured, status, display_order)
            VALUES (?, ?, ?, ?, ?, 5, 1, 'published', ?)
        ");
        $existingTestCount = (int) $pdo->query("SELECT COUNT(*) FROM testimonials")->fetchColumn();
        if ($existingTestCount === 0) {
            $testStmt->execute(['Rohan Deshmukh', 'VP of Digital Operations', 'NexaFin Technologies', 'Orixo delivered more than a clean interface — their Client Portal transformed how our compliance and advisory teams collaborate with clients every day.', $ppMap['nexafin-wealth-client-portal'], 1]);
            $testStmt->execute(['Ananya Rao', 'Chief Marketing Officer', 'Kestrel Cloud', 'Rarely do you find a studio with genuine editorial taste and rigorous backend engineering discipline under one roof.', $ppMap['kestrel-cloud-flagship'], 2]);
            $testStmt->execute(['Siddharth Nair', 'Director of Operations', 'Meridian Logistics', 'From milestone governance to zero-error INR billing workflows, Orixo operates with true enterprise maturity.', $ppMap['meridian-freight-ops'], 3]);
        }

        // Link M:N junctions
        $stStmt = $pdo->prepare("INSERT IGNORE INTO service_technologies (service_id, technology_id) VALUES (?, ?)");
        foreach ($svcMap as $sId) {
            $stStmt->execute([$sId, $techMap['php-8']]);
            $stStmt->execute([$sId, $techMap['mysql-8']]);
            $stStmt->execute([$sId, $techMap['bootstrap-5']]);
        }
        $ptStmt = $pdo->prepare("INSERT IGNORE INTO portfolio_technologies (portfolio_project_id, technology_id) VALUES (?, ?)");
        foreach ($ppMap as $pId) {
            $ptStmt->execute([$pId, $techMap['php-8']]);
            $ptStmt->execute([$pId, $techMap['mysql-8']]);
            $ptStmt->execute([$pId, $techMap['html5-css3']]);
        }
        $ctStmt = $pdo->prepare("INSERT IGNORE INTO case_study_technologies (case_study_id, technology_id) VALUES (?, ?)");
        foreach ($csMap as $cId) {
            $ctStmt->execute([$cId, $techMap['php-8']]);
            $ctStmt->execute([$cId, $techMap['mysql-8']]);
        }
        $isStmt = $pdo->prepare("INSERT IGNORE INTO industry_services (industry_id, service_id) VALUES (?, ?)");
        foreach ($indMap as $iId) {
            $isStmt->execute([$iId, $svcMap['web-application-development']]);
            $isStmt->execute([$iId, $svcMap['ui-ux-design']]);
        }

        return ['services' => $svcMap, 'industries' => $indMap];
    }

    private static function seedDemoOperations(PDO $pdo, array $userIds, array $cmsIds): void
    {
        // Demo Project PRJ-2026-0001
        $qProj = $pdo->prepare("SELECT id FROM projects WHERE project_code = 'PRJ-2026-0001'");
        $qProj->execute();
        $projectId = $qProj->fetchColumn();

        if (!$projectId) {
            $insP = $pdo->prepare("
                INSERT INTO projects
                (project_code, client_id, service_id, project_manager_id, title, description, scope_of_work, start_date, target_completion_date, status, progress_mode, progress_percentage, total_active_tasks, completed_tasks_count, blocked_tasks_count, created_by)
                VALUES ('PRJ-2026-0001', ?, ?, ?, 'NexaFin Wealth Advisory Portal v2', 'Design and engineering of NexaFin client onboarding, document review vault, and advisory reporting platform.', '1. Editorial UI/UX Design System\n2. Role-Based Client Portal\n3. Private Document Vault & INR Billing Ledger\n4. Security Hardening & UAT', DATE_SUB(CURDATE(), INTERVAL 21 DAY), DATE_ADD(CURDATE(), INTERVAL 35 DAY), 'in_progress', 'auto', 40, 5, 2, 1, ?)
            ");
            $insP->execute([$userIds['client'], $cmsIds['services']['web-application-development'], $userIds['lead_team'], $userIds['admin']]);
            $projectId = (int) $pdo->lastInsertId();

            // Assignments
            $paStmt = $pdo->prepare("INSERT IGNORE INTO project_assignments (project_id, team_member_id, project_role, assigned_by) VALUES (?, ?, ?, ?)");
            $paStmt->execute([$projectId, $userIds['lead_team'], 'Lead Architect', $userIds['admin']]);
            $paStmt->execute([$projectId, $userIds['ux_team'], 'UI/UX Lead', $userIds['admin']]);
            $paStmt->execute([$projectId, $userIds['dev_team'], 'Frontend Engineer', $userIds['admin']]);

            // Milestones
            $mStmt = $pdo->prepare("INSERT INTO project_milestones (project_id, title, description, planned_date, completion_date, status, display_order, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $mStmt->execute([$projectId, 'Discovery & Database Architecture', 'Finalized requirements, ER schema, and security checklist.', date('Y-m-d', strtotime('-14 days')), date('Y-m-d', strtotime('-12 days')), 'completed', 1, $userIds['admin']]);
            $m1 = (int) $pdo->lastInsertId();
            $mStmt->execute([$projectId, 'UI/UX Design System & Prototypes', 'Editorial component tokens and responsive portal layouts.', date('Y-m-d', strtotime('+5 days')), null, 'in_progress', 2, $userIds['admin']]);
            $m2 = (int) $pdo->lastInsertId();
            $mStmt->execute([$projectId, 'Core Engineering & UAT Launch', 'Backend modules, Dompdf billing, security verification, and launch.', date('Y-m-d', strtotime('+30 days')), null, 'pending', 3, $userIds['admin']]);
            $m3 = (int) $pdo->lastInsertId();

            // Tasks (2 completed, 1 review, 1 in_progress, 1 blocked => 2/5 = 40%)
            $tStmt = $pdo->prepare("INSERT INTO project_tasks (project_id, milestone_id, assigned_to_team_id, title, description, priority, status, due_date, is_client_visible, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?)");
            $tStmt->execute([$projectId, $m1, $userIds['lead_team'], 'Finalize MySQL RBAC & Audit Schema', 'Normalized InnoDB schema and composite invoice constraints.', 'high', 'completed', date('Y-m-d', strtotime('-13 days')), $userIds['admin']]);
            $tStmt->execute([$projectId, $m1, $userIds['ux_team'], 'Deliver Information Architecture Map', 'Sitemap and role navigation hierarchy signed off.', 'medium', 'completed', date('Y-m-d', strtotime('-12 days')), $userIds['admin']]);
            $tStmt->execute([$projectId, $m2, $userIds['ux_team'], 'Portal Design System & Invoice Templates', 'Editorial Vermilion component library ready for review.', 'high', 'review', date('Y-m-d', strtotime('+2 days')), $userIds['admin']]);
            $tStmt->execute([$projectId, $m2, $userIds['dev_team'], 'Implement Responsive Portal Views', 'Bootstrap 5 portal shell and AJAX table controllers.', 'high', 'in_progress', date('Y-m-d', strtotime('+6 days')), $userIds['lead_user']]);
            $tStmt->execute([$projectId, $m3, $userIds['lead_team'], 'Configure Client Production SMTP Relay', 'Awaiting verified SPF/DKIM DNS records from client IT team.', 'urgent', 'blocked', date('Y-m-d', strtotime('+12 days')), $userIds['admin']]);

            // Thread & initial message
            $thStmt = $pdo->prepare("INSERT INTO project_threads (project_id, subject, last_message_at) VALUES (?, 'General Project Discussion', NOW())");
            $thStmt->execute([$projectId]);
            $threadId = (int) $pdo->lastInsertId();

            $msgStmt = $pdo->prepare("INSERT INTO project_messages (thread_id, sender_user_id, body) VALUES (?, ?, ?)");
            $msgStmt->execute([$threadId, $userIds['lead_user'], 'Welcome to your NexaFin Portal workspace! Discovery is complete and the UI/UX component kit is currently in final review. Please upload your updated brand guidelines in Client Uploads when ready.']);

            // Demo INR Invoice & partial NEFT payment
            $invStmt = $pdo->prepare("
                INSERT INTO invoices
                (invoice_number, client_id, project_id, currency_code, issue_date, due_date, subtotal, tax_rate, tax_amount, total_amount, amount_paid, remaining_balance, status, notes, created_by, issued_at)
                VALUES ('INV-2026-0001', ?, ?, 'INR', DATE_SUB(CURDATE(), INTERVAL 10 DAY), DATE_ADD(CURDATE(), INTERVAL 20 DAY), 150000.00, 18.00, 27000.00, 177000.00, 85000.00, 92000.00, 'partially_paid', 'Milestone 1 & 2 commencement billing. 18% IGST/CGST+SGST included.', ?, NOW())
            ");
            $invStmt->execute([$userIds['client'], $projectId, $userIds['admin']]);
            $invId = (int) $pdo->lastInsertId();

            $iiStmt = $pdo->prepare("INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, line_total, display_order) VALUES (?, ?, ?, ?, ?, ?)");
            $iiStmt->execute([$invId, 'Phase 1: Technical Discovery & Relational Architecture', 1.00, 60000.00, 60000.00, 1]);
            $iiStmt->execute([$invId, 'Phase 2: Editorial UI/UX Design System & Portal Prototype', 1.00, 90000.00, 90000.00, 2]);

            $payStmt = $pdo->prepare("
                INSERT INTO payments (payment_reference, invoice_id, amount, payment_date, payment_method, transaction_reference, status, recorded_by, notes)
                VALUES ('PAY-2026-0001', ?, 85000.00, DATE_SUB(CURDATE(), INTERVAL 5 DAY), 'neft_rtgs', 'HDFC-NEFT-99482103', 'recorded', ?, 'Initial commencement tranche received via NEFT.')
            ");
            $payStmt->execute([$invId, $userIds['admin']]);

            // Demo notification for client
            $nStmt = $pdo->prepare("INSERT INTO notifications (user_id, event_type, title, message, entity_type, entity_id, action_url) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $nStmt->execute([$userIds['client_user'], 'invoice_issued', 'Invoice INV-2026-0001 Issued', 'Invoice INV-2026-0001 for ₹1,77,000.00 has been issued. Remaining balance: ₹92,000.00.', 'invoice', $invId, '/portal/invoices/' . $invId]);
        }

        // Demo Inquiry
        $qInq = $pdo->query("SELECT COUNT(*) FROM inquiries")->fetchColumn();
        if ((int) $qInq === 0) {
            $inqStmt = $pdo->prepare("
                INSERT INTO inquiries
                (reference_code, inquiry_type, full_name, email, phone, company_name, service_id, project_title, message_or_description, estimated_budget, expected_timeline, status)
                VALUES ('INQ-2026-0001', 'project_inquiry', 'Neha Kapoor', 'neha.kapoor@veloxhealth.example', '+91 98450 66778', 'Velox Enterprise Systems', ?, 'B2B Partner Procurement Portal', 'Looking to replace our manual dealer order spreadsheets with a role-based web application, document approvals, and automated INR invoicing.', '₹4,00,000 – ₹7,00,000', '8–12 Weeks', 'new')
            ");
            $inqStmt->execute([$cmsIds['services']['enterprise-software-solutions']]);
        }
    }
}
