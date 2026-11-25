<?php
declare(strict_types=1);

namespace Orixo\Services;

use Orixo\Bootstrap\App;
use Orixo\Bootstrap\Database;

/**
 * ACID financial ledger service for Invoices, Line Items, Manual Payments & Voids.
 */
final class InvoiceService
{
    public static function createDraft(int $adminUserId, array $data): array
    {
        return Database::transaction(static function (\PDO $pdo) use ($adminUserId, $data): array {
            $clientId = (int) $data['client_id'];
            $projectId = (int) $data['project_id'];

            // Enforce project belongs to client
            $pChk = $pdo->prepare("SELECT id, client_id, title FROM projects WHERE id = ? LIMIT 1 FOR UPDATE");
            $pChk->execute([$projectId]);
            $project = $pChk->fetch();
            if (!$project || (int) $project['client_id'] !== $clientId) {
                return ['ok' => false, 'code' => 422, 'message' => 'Selected project does not belong to the selected client.'];
            }

            $items = $data['items'] ?? [];
            if (!is_array($items) || count($items) === 0) {
                return ['ok' => false, 'code' => 422, 'message' => 'At least one invoice line item is required.'];
            }

            $subtotal = 0.00;
            $cleanItems = [];
            foreach ($items as $idx => $item) {
                $desc = trim((string) ($item['description'] ?? ''));
                $qty = round((float) ($item['quantity'] ?? 1), 2);
                $price = round((float) ($item['unit_price'] ?? 0), 2);
                if ($desc === '' || $qty <= 0 || $price < 0) {
                    return ['ok' => false, 'code' => 422, 'message' => 'Each line item requires a description, positive quantity, and valid price.'];
                }
                $lineTotal = round($qty * $price, 2);
                $subtotal += $lineTotal;
                $cleanItems[] = [$desc, $qty, $price, $lineTotal, $idx + 1];
            }

            $subtotal = round($subtotal, 2);
            $taxRate = round(max(0, (float) ($data['tax_rate'] ?? setting('finance.default_tax_rate', '18.00'))), 2);
            $taxAmount = round($subtotal * ($taxRate / 100), 2);
            $totalAmount = round($subtotal + $taxAmount, 2);
            $currency = strtoupper(trim((string) ($data['currency_code'] ?? setting('finance.default_currency', 'INR'))));
            if (!preg_match('/^[A-Z]{3}$/', $currency)) {
                $currency = 'INR';
            }

            $invNum = setting('finance.invoice_prefix', 'INV-' . date('Y') . '-') . strtoupper(bin2hex(random_bytes(2))) . '-' . random_int(10, 99);

            $ins = $pdo->prepare("
                INSERT INTO invoices
                (invoice_number, client_id, project_id, currency_code, issue_date, due_date, subtotal, tax_rate, tax_amount, total_amount, amount_paid, remaining_balance, status, notes, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0.00, ?, 'draft', ?, ?)
            ");
            $ins->execute([
                $invNum,
                $clientId,
                $projectId,
                $currency,
                $data['issue_date'] ?? date('Y-m-d'),
                $data['due_date'] ?? date('Y-m-d', strtotime('+15 days')),
                $subtotal,
                $taxRate,
                $taxAmount,
                $totalAmount,
                $totalAmount,
                trim((string) ($data['notes'] ?? '')) ?: null,
                $adminUserId,
            ]);
            $invoiceId = (int) $pdo->lastInsertId();

            $iStmt = $pdo->prepare("INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, line_total, display_order) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($cleanItems as $ci) {
                $iStmt->execute([$invoiceId, $ci[0], $ci[1], $ci[2], $ci[3], $ci[4]]);
            }

            AuditService::log($adminUserId, 'admin', 'invoice.draft_created', 'invoice', $invoiceId, "Created draft invoice {$invNum}");
            return ['ok' => true, 'invoice_id' => $invoiceId, 'invoice_number' => $invNum];
        });
    }

    public static function updateDraft(int $invoiceId, int $adminUserId, array $data): array
    {
        return Database::transaction(static function (\PDO $pdo) use ($invoiceId, $adminUserId, $data): array {
            $stmt = $pdo->prepare("SELECT * FROM invoices WHERE id = ? LIMIT 1 FOR UPDATE");
            $stmt->execute([$invoiceId]);
            $inv = $stmt->fetch();
            if (!$inv) {
                return ['ok' => false, 'code' => 404, 'message' => 'Invoice not found.'];
            }
            if ($inv['status'] !== 'draft') {
                return ['ok' => false, 'code' => 422, 'message' => 'Only draft invoices may be edited. Issued invoices are locked.'];
            }

            $clientId = (int) ($data['client_id'] ?? $inv['client_id']);
            $projectId = (int) ($data['project_id'] ?? $inv['project_id']);

            $pChk = $pdo->prepare("SELECT id, client_id FROM projects WHERE id = ? LIMIT 1");
            $pChk->execute([$projectId]);
            $proj = $pChk->fetch();
            if (!$proj || (int) $proj['client_id'] !== $clientId) {
                return ['ok' => false, 'code' => 422, 'message' => 'Selected project does not belong to the selected client.'];
            }

            $items = $data['items'] ?? [];
            if (!is_array($items) || count($items) === 0) {
                return ['ok' => false, 'code' => 422, 'message' => 'At least one line item is required.'];
            }

            $subtotal = 0.00;
            $cleanItems = [];
            foreach ($items as $idx => $item) {
                $desc = trim((string) ($item['description'] ?? ''));
                $qty = round((float) ($item['quantity'] ?? 1), 2);
                $price = round((float) ($item['unit_price'] ?? 0), 2);
                if ($desc === '' || $qty <= 0 || $price < 0) {
                    return ['ok' => false, 'code' => 422, 'message' => 'Invalid line item data.'];
                }
                $lineTotal = round($qty * $price, 2);
                $subtotal += $lineTotal;
                $cleanItems[] = [$desc, $qty, $price, $lineTotal, $idx + 1];
            }

            $taxRate = round(max(0, (float) ($data['tax_rate'] ?? $inv['tax_rate'])), 2);
            $taxAmount = round($subtotal * ($taxRate / 100), 2);
            $totalAmount = round($subtotal + $taxAmount, 2);
            $currency = strtoupper(trim((string) ($data['currency_code'] ?? $inv['currency_code'])));
            if (!preg_match('/^[A-Z]{3}$/', $currency)) {
                $currency = (string) $inv['currency_code'];
            }

            $pdo->prepare("
                UPDATE invoices
                SET client_id = ?, project_id = ?, currency_code = ?, issue_date = ?, due_date = ?,
                    subtotal = ?, tax_rate = ?, tax_amount = ?, total_amount = ?, remaining_balance = ?, notes = ?
                WHERE id = ?
            ")->execute([
                $clientId,
                $projectId,
                $currency,
                $data['issue_date'] ?? $inv['issue_date'],
                $data['due_date'] ?? $inv['due_date'],
                $subtotal,
                $taxRate,
                $taxAmount,
                $totalAmount,
                $totalAmount,
                trim((string) ($data['notes'] ?? '')) ?: null,
                $invoiceId,
            ]);

            $pdo->prepare("DELETE FROM invoice_items WHERE invoice_id = ?")->execute([$invoiceId]);
            $iStmt = $pdo->prepare("INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, line_total, display_order) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($cleanItems as $ci) {
                $iStmt->execute([$invoiceId, $ci[0], $ci[1], $ci[2], $ci[3], $ci[4]]);
            }

            AuditService::log($adminUserId, 'admin', 'invoice.draft_updated', 'invoice', $invoiceId, "Updated draft invoice {$inv['invoice_number']}");
            return ['ok' => true, 'message' => 'Draft invoice updated.'];
        });
    }

    public static function issueInvoice(int $invoiceId, int $adminUserId): array
    {
        $res = Database::transaction(static function (\PDO $pdo) use ($invoiceId, $adminUserId): array {
            $stmt = $pdo->prepare("
                SELECT i.*, c.company_name, c.billing_address, c.tax_id,
                       u.id AS client_user_id, u.full_name AS client_name, u.email AS client_email,
                       p.title AS project_title, p.project_code
                FROM invoices i
                INNER JOIN clients c ON c.id = i.client_id
                INNER JOIN users u ON u.id = c.user_id
                INNER JOIN projects p ON p.id = i.project_id
                WHERE i.id = ?
                LIMIT 1 FOR UPDATE
            ");
            $stmt->execute([$invoiceId]);
            $inv = $stmt->fetch();

            if (!$inv) {
                return ['ok' => false, 'code' => 404, 'message' => 'Invoice not found.'];
            }
            if ($inv['status'] !== 'draft') {
                return ['ok' => false, 'code' => 422, 'message' => 'Only draft invoices can be issued.'];
            }
            if ((int) $inv['client_id'] <= 0 || (int) $inv['project_id'] <= 0) {
                return ['ok' => false, 'code' => 422, 'message' => 'Invoice project/client linkage is invalid.'];
            }

            $itemsStmt = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY display_order ASC");
            $itemsStmt->execute([$invoiceId]);
            $items = $itemsStmt->fetchAll();

            $pdfFileId = null;
            if (class_exists(\Dompdf\Dompdf::class)) {
                $invForPdf = $inv;
                $invForPdf['status'] = 'issued';
                $pdfFileId = PdfService::generateInvoicePdf($invForPdf, $items, [], $adminUserId);
            }

            $pdo->prepare("UPDATE invoices SET status = 'issued', issued_at = NOW(), pdf_file_metadata_id = ? WHERE id = ?")
                ->execute([$pdfFileId, $invoiceId]);

            AuditService::log($adminUserId, 'admin', 'invoice.issued', 'invoice', $invoiceId, "Issued invoice {$inv['invoice_number']}");
            return ['ok' => true, 'invoice' => $inv];
        });

        if (!$res['ok']) {
            return $res;
        }

        $inv = $res['invoice'];
        $formattedTotal = format_currency($inv['total_amount'], $inv['currency_code']);
        $portalUrl = App::config('app.url') . '/portal/invoices/' . $invoiceId;

        EmailQueueService::enqueue(
            (string) $inv['client_email'],
            'invoice_issued',
            [
                'full_name' => $inv['client_name'],
                'invoice_number' => $inv['invoice_number'],
                'project_title' => $inv['project_title'],
                'formatted_total' => $formattedTotal,
                'due_date' => format_date($inv['due_date']),
                'action_url' => $portalUrl,
            ],
            "invoice_issued:{$invoiceId}"
        );

        NotificationService::notifyUser(
            (int) $inv['client_user_id'],
            'invoice_issued',
            "Invoice {$inv['invoice_number']} Issued",
            "Invoice for {$formattedTotal} (Due " . format_date($inv['due_date']) . ") is now available.",
            'invoice',
            $invoiceId,
            '/portal/invoices/' . $invoiceId
        );

        return ['ok' => true, 'message' => "Invoice {$inv['invoice_number']} issued and client notified."];
    }

    public static function recordPayment(int $invoiceId, int $adminUserId, array $data): array
    {
        $res = Database::transaction(static function (\PDO $pdo) use ($invoiceId, $adminUserId, $data): array {
            $stmt = $pdo->prepare("
                SELECT i.*, c.company_name, c.billing_address, c.tax_id,
                       u.id AS client_user_id, u.full_name AS client_name, u.email AS client_email,
                       p.title AS project_title, p.project_code
                FROM invoices i
                INNER JOIN clients c ON c.id = i.client_id
                INNER JOIN users u ON u.id = c.user_id
                INNER JOIN projects p ON p.id = i.project_id
                WHERE i.id = ?
                LIMIT 1 FOR UPDATE
            ");
            $stmt->execute([$invoiceId]);
            $inv = $stmt->fetch();

            if (!$inv) {
                return ['ok' => false, 'code' => 404, 'message' => 'Invoice not found.'];
            }
            if (!in_array($inv['status'], ['issued', 'partially_paid'], true)) {
                return ['ok' => false, 'code' => 422, 'message' => 'Payments can only be recorded for issued or partially paid invoices.'];
            }

            $amount = round((float) ($data['amount'] ?? 0), 2);
            $remaining = round((float) $inv['remaining_balance'], 2);
            if ($amount <= 0 || $amount > $remaining) {
                return ['ok' => false, 'code' => 422, 'message' => 'Payment amount must be greater than zero and cannot exceed remaining balance (' . format_currency($remaining, $inv['currency_code']) . ').'];
            }

            $payRef = 'PAY-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));
            $ins = $pdo->prepare("
                INSERT INTO payments
                (payment_reference, invoice_id, amount, payment_date, payment_method, transaction_reference, status, recorded_by, notes)
                VALUES (?, ?, ?, ?, ?, ?, 'recorded', ?, ?)
            ");
            $ins->execute([
                $payRef,
                $invoiceId,
                $amount,
                $data['payment_date'] ?? date('Y-m-d'),
                $data['payment_method'],
                trim((string) ($data['transaction_reference'] ?? '')) ?: null,
                $adminUserId,
                trim((string) ($data['notes'] ?? '')) ?: null,
            ]);
            $paymentId = (int) $pdo->lastInsertId();

            $recalc = self::recalculateInvoiceTotals($pdo, $invoiceId, (float) $inv['total_amount'], $adminUserId);

            AuditService::log(
                $adminUserId,
                'admin',
                'payment.recorded',
                'payment',
                $paymentId,
                "Recorded {$payRef} (" . format_currency($amount, $inv['currency_code']) . ") against {$inv['invoice_number']}"
            );

            return [
                'ok' => true,
                'payment_id' => $paymentId,
                'payment_reference' => $payRef,
                'amount' => $amount,
                'invoice' => $inv,
                'totals' => $recalc,
            ];
        });

        if (!$res['ok']) {
            return $res;
        }

        $inv = $res['invoice'];
        $totals = $res['totals'];
        $formattedPaid = format_currency($res['amount'], $inv['currency_code']);
        $formattedBalance = format_currency($totals['remaining_balance'], $inv['currency_code']);

        EmailQueueService::enqueue(
            (string) $inv['client_email'],
            'payment_recorded',
            [
                'full_name' => $inv['client_name'],
                'invoice_number' => $inv['invoice_number'],
                'payment_reference' => $res['payment_reference'],
                'formatted_amount' => $formattedPaid,
                'formatted_balance' => $formattedBalance,
            ],
            "payment_recorded:{$res['payment_id']}"
        );

        NotificationService::notifyUser(
            (int) $inv['client_user_id'],
            'payment_recorded',
            "Payment Recorded: {$inv['invoice_number']}",
            "Received {$formattedPaid} ({$res['payment_reference']}). Remaining balance: {$formattedBalance}.",
            'invoice',
            $invoiceId,
            '/portal/invoices/' . $invoiceId
        );

        return ['ok' => true, 'message' => "Payment {$res['payment_reference']} recorded successfully.", 'data' => $totals];
    }

    public static function voidPayment(int $paymentId, int $adminUserId, string $reason): array
    {
        return Database::transaction(static function (\PDO $pdo) use ($paymentId, $adminUserId, $reason): array {
            $pStmt = $pdo->prepare("SELECT * FROM payments WHERE id = ? LIMIT 1 FOR UPDATE");
            $pStmt->execute([$paymentId]);
            $pay = $pStmt->fetch();
            if (!$pay) {
                return ['ok' => false, 'code' => 404, 'message' => 'Payment record not found.'];
            }
            if ($pay['status'] === 'voided') {
                return ['ok' => false, 'code' => 422, 'message' => 'Payment is already voided.'];
            }

            $iStmt = $pdo->prepare("SELECT * FROM invoices WHERE id = ? LIMIT 1 FOR UPDATE");
            $iStmt->execute([(int) $pay['invoice_id']]);
            $inv = $iStmt->fetch();
            if (!$inv) {
                return ['ok' => false, 'code' => 404, 'message' => 'Parent invoice not found.'];
            }

            $pdo->prepare("
                UPDATE payments
                SET status = 'voided', voided_by = ?, voided_at = NOW(), void_reason = ?
                WHERE id = ?
            ")->execute([$adminUserId, trim($reason), $paymentId]);

            $recalc = self::recalculateInvoiceTotals($pdo, (int) $inv['id'], (float) $inv['total_amount'], $adminUserId);

            AuditService::log(
                $adminUserId,
                'admin',
                'payment.voided',
                'payment',
                $paymentId,
                "Voided payment {$pay['payment_reference']} on {$inv['invoice_number']}: {$reason}"
            );

            return ['ok' => true, 'message' => "Payment {$pay['payment_reference']} voided and invoice balance updated.", 'data' => $recalc];
        });
    }

    public static function cancelInvoice(int $invoiceId, int $adminUserId): array
    {
        return Database::transaction(static function (\PDO $pdo) use ($invoiceId, $adminUserId): array {
            $stmt = $pdo->prepare("SELECT * FROM invoices WHERE id = ? LIMIT 1 FOR UPDATE");
            $stmt->execute([$invoiceId]);
            $inv = $stmt->fetch();
            if (!$inv) {
                return ['ok' => false, 'code' => 404, 'message' => 'Invoice not found.'];
            }
            if (in_array($inv['status'], ['partially_paid', 'paid'], true) || (float) $inv['amount_paid'] > 0) {
                return ['ok' => false, 'code' => 422, 'message' => 'Paid or partially paid invoices cannot be cancelled. Void recorded payments first.'];
            }
            if ($inv['status'] === 'cancelled') {
                return ['ok' => false, 'code' => 422, 'message' => 'Invoice is already cancelled.'];
            }

            $pdo->prepare("UPDATE invoices SET status = 'cancelled', remaining_balance = 0.00 WHERE id = ?")->execute([$invoiceId]);
            AuditService::log($adminUserId, 'admin', 'invoice.cancelled', 'invoice', $invoiceId, "Cancelled invoice {$inv['invoice_number']}");

            return ['ok' => true, 'message' => "Invoice {$inv['invoice_number']} cancelled."];
        });
    }

    private static function recalculateInvoiceTotals(\PDO $pdo, int $invoiceId, float $totalAmount, int $actorId): array
    {
        $sumStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE invoice_id = ? AND status = 'recorded'");
        $sumStmt->execute([$invoiceId]);
        $paid = round((float) $sumStmt->fetchColumn(), 2);
        $balance = max(0.00, round($totalAmount - $paid, 2));

        $newStatus = $balance <= 0.00 ? 'paid' : ($paid > 0.00 ? 'partially_paid' : 'issued');

        // Refresh PDF snapshot if Dompdf available
        $oldPdfId = $pdo->query("SELECT pdf_file_metadata_id FROM invoices WHERE id = {$invoiceId}")->fetchColumn();
        $newPdfId = $oldPdfId ? (int) $oldPdfId : null;

        $pdo->prepare("UPDATE invoices SET amount_paid = ?, remaining_balance = ?, status = ? WHERE id = ?")
            ->execute([$paid, $balance, $newStatus, $invoiceId]);

        if (class_exists(\Dompdf\Dompdf::class)) {
            $invStmt = $pdo->prepare("
                SELECT i.*, c.company_name, c.billing_address, c.tax_id,
                       u.full_name AS client_name, u.email AS client_email,
                       p.title AS project_title, p.project_code
                FROM invoices i
                INNER JOIN clients c ON c.id = i.client_id
                INNER JOIN users u ON u.id = c.user_id
                INNER JOIN projects p ON p.id = i.project_id
                WHERE i.id = ? LIMIT 1
            ");
            $invStmt->execute([$invoiceId]);
            $inv = $invStmt->fetch();
            $itemsStmt = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY display_order ASC");
            $itemsStmt->execute([$invoiceId]);
            $payStmt = $pdo->prepare("SELECT * FROM payments WHERE invoice_id = ? AND status = 'recorded' ORDER BY payment_date ASC");
            $payStmt->execute([$invoiceId]);
            $newPdfId = PdfService::generateInvoicePdf($inv, $itemsStmt->fetchAll(), $payStmt->fetchAll(), $actorId);
            $pdo->prepare("UPDATE invoices SET pdf_file_metadata_id = ? WHERE id = ?")->execute([$newPdfId, $invoiceId]);
            if ($oldPdfId && (int) $oldPdfId !== $newPdfId) {
                // Safe cleanup after old PDF reference is detached
                FileService::deleteIfUnreferenced((int) $oldPdfId);
            }
        }

        return ['amount_paid' => $paid, 'remaining_balance' => $balance, 'status' => $newStatus];
    }

    public static function isOverdue(array $invoice): bool
    {
        return in_array($invoice['status'], ['issued', 'partially_paid'], true)
            && (float) $invoice['remaining_balance'] > 0
            && strtotime((string) $invoice['due_date']) < strtotime(date('Y-m-d'));
    }
}
