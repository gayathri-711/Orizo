<?php
declare(strict_types=1);

namespace Orixo\Services;

use Orixo\Bootstrap\App;
use Orixo\Bootstrap\Database;

/**
 * Handles private non-webroot uploads, public CMS image uploads, streamed downloads, and reference-safe cleanup.
 */
final class FileService
{
    public static function storePrivateUpload(array $file, string $subFolder, int $maxBytes, ?int $uploadedBy): array
    {
        self::assertValidUpload($file, $maxBytes, App::config('uploads.allowed_private_mimes'));

        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($file['tmp_name']);
        $checksum = hash_file('sha256', $file['tmp_name']) ?: '';

        $yearMonth = date('Y/m');
        $cleanSub = preg_replace('/[^a-z0-9_-]/i', '', $subFolder) ?: 'misc';
        $root = App::config('uploads.private_root');
        $relDir = $cleanSub . '/' . $yearMonth;
        $absDir = $root . '/' . $relDir;

        if (!is_dir($absDir) && !mkdir($absDir, 0750, true) && !is_dir($absDir)) {
            throw new \RuntimeException('Failed to initialize private storage directory.');
        }

        $storedName = bin2hex(random_bytes(16)) . '.' . $ext;
        $relativePath = $relDir . '/' . $storedName;
        $absPath = $root . '/' . $relativePath;

        if (!move_uploaded_file($file['tmp_name'], $absPath)) {
            throw new \RuntimeException('Unable to persist uploaded file.');
        }
        @chmod($absPath, 0640);

        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare("
                INSERT INTO file_metadata
                (storage_disk, original_name, stored_name, relative_path, mime_type, file_size_bytes, sha256_checksum, lifecycle_status, uploaded_by)
                VALUES ('private', ?, ?, ?, ?, ?, ?, 'active', ?)
            ");
            $stmt->execute([
                substr(basename((string) $file['name']), 0, 255),
                $storedName,
                $relativePath,
                $mime,
                (int) $file['size'],
                $checksum,
                $uploadedBy,
            ]);
            return [
                'id' => (int) $pdo->lastInsertId(),
                'original_name' => basename((string) $file['name']),
                'stored_name' => $storedName,
                'relative_path' => $relativePath,
                'mime_type' => $mime,
                'file_size_bytes' => (int) $file['size'],
            ];
        } catch (\Throwable $e) {
            if (is_file($absPath)) {
                @unlink($absPath);
            }
            throw $e;
        }
    }

    public static function storeRawPrivateFile(string $binaryContent, string $subFolder, string $originalName, string $mimeType, ?int $uploadedBy): int
    {
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) ?: 'pdf';
        $yearMonth = date('Y/m');
        $cleanSub = preg_replace('/[^a-z0-9_-]/i', '', $subFolder) ?: 'invoices';
        $root = App::config('uploads.private_root');
        $relDir = $cleanSub . '/' . $yearMonth;
        $absDir = $root . '/' . $relDir;

        if (!is_dir($absDir) && !mkdir($absDir, 0750, true) && !is_dir($absDir)) {
            throw new \RuntimeException('Failed to create private directory.');
        }

        $storedName = bin2hex(random_bytes(16)) . '.' . $ext;
        $relativePath = $relDir . '/' . $storedName;
        $absPath = $root . '/' . $relativePath;

        if (file_put_contents($absPath, $binaryContent) === false) {
            throw new \RuntimeException('Unable to write private generated file.');
        }
        @chmod($absPath, 0640);

        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare("
                INSERT INTO file_metadata
                (storage_disk, original_name, stored_name, relative_path, mime_type, file_size_bytes, sha256_checksum, lifecycle_status, uploaded_by)
                VALUES ('private', ?, ?, ?, ?, ?, ?, 'active', ?)
            ");
            $stmt->execute([
                substr($originalName, 0, 255),
                $storedName,
                $relativePath,
                $mimeType,
                strlen($binaryContent),
                hash('sha256', $binaryContent),
                $uploadedBy,
            ]);
            return (int) $pdo->lastInsertId();
        } catch (\Throwable $e) {
            if (is_file($absPath)) {
                @unlink($absPath);
            }
            throw $e;
        }
    }

    public static function storePublicCmsImage(array $file, string $subFolder = 'general'): string
    {
        self::assertValidUpload($file, (int) App::config('uploads.max_cms_bytes'), App::config('uploads.allowed_cms_mimes'));
        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        $cleanSub = preg_replace('/[^a-z0-9_-]/i', '', $subFolder) ?: 'general';
        $root = App::config('uploads.public_cms_root');
        $absDir = $root . '/' . $cleanSub;
        if (!is_dir($absDir) && !mkdir($absDir, 0755, true) && !is_dir($absDir)) {
            throw new \RuntimeException('Failed to initialize CMS upload directory.');
        }
        $filename = bin2hex(random_bytes(12)) . '.' . $ext;
        $absPath = $absDir . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $absPath)) {
            throw new \RuntimeException('Failed to store CMS image.');
        }
        return '/uploads/cms/' . $cleanSub . '/' . $filename;
    }

    public static function assertValidUpload(array $file, int $maxBytes, array $allowedMimes): void
    {
        if (!isset($file['error']) || is_array($file['error'])) {
            throw new \InvalidArgumentException('Invalid upload payload.');
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException('File upload failed with error code ' . (int) $file['error'] . '.');
        }
        if (($file['size'] ?? 0) <= 0 || $file['size'] > $maxBytes) {
            $mb = round($maxBytes / (1024 * 1024), 1);
            throw new \InvalidArgumentException("Uploaded file must be between 1 byte and {$mb} MB.");
        }
        $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if ($ext === '' || !isset($allowedMimes[$ext])) {
            throw new \InvalidArgumentException('Unsupported file extension.');
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detectedMime = (string) $finfo->file($file['tmp_name']);
        if (!in_array($detectedMime, $allowedMimes[$ext], true)) {
            throw new \InvalidArgumentException('File MIME type does not match its extension.');
        }
    }

    public static function streamPrivateDownload(int $fileMetadataId, ?string $overrideFilename = null): never
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare("SELECT * FROM file_metadata WHERE id = ? AND storage_disk = 'private' AND lifecycle_status = 'active' LIMIT 1");
        $stmt->execute([$fileMetadataId]);
        $meta = $stmt->fetch();
        if (!$meta) {
            http_response_code(404);
            exit('File not found.');
        }

        $absPath = App::config('uploads.private_root') . '/' . ltrim($meta['relative_path'], '/');
        if (!is_file($absPath)) {
            http_response_code(404);
            exit('Stored file missing.');
        }

        $downloadName = str_replace(['"', "\r", "\n"], '', $overrideFilename ?: $meta['original_name']);
        header('Content-Description: File Transfer');
        header('Content-Type: ' . $meta['mime_type']);
        header('Content-Disposition: attachment; filename="' . $downloadName . '"');
        header('Content-Length: ' . (string) filesize($absPath));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store, max-age=0');
        readfile($absPath);
        exit;
    }

    /**
     * Reference-safe physical file removal. Never deletes while any table references file_metadata_id.
     */
    public static function deleteIfUnreferenced(int $fileMetadataId): bool
    {
        return Database::transaction(static function (\PDO $pdo) use ($fileMetadataId): bool {
            $stmt = $pdo->prepare("SELECT * FROM file_metadata WHERE id = ? FOR UPDATE");
            $stmt->execute([$fileMetadataId]);
            $meta = $stmt->fetch();
            if (!$meta || $meta['lifecycle_status'] === 'deleted') {
                return false;
            }

            $refCount = 0;
            $checks = [
                'SELECT COUNT(*) FROM inquiry_attachments WHERE file_metadata_id = ?',
                'SELECT COUNT(*) FROM project_documents WHERE file_metadata_id = ?',
                'SELECT COUNT(*) FROM project_deliverables WHERE file_metadata_id = ?',
                'SELECT COUNT(*) FROM message_attachments WHERE file_metadata_id = ?',
                'SELECT COUNT(*) FROM invoices WHERE pdf_file_metadata_id = ?',
            ];
            foreach ($checks as $sql) {
                $c = $pdo->prepare($sql);
                $c->execute([$fileMetadataId]);
                $refCount += (int) $c->fetchColumn();
            }

            if ($refCount > 0) {
                return false;
            }

            if ($meta['storage_disk'] === 'private') {
                $absPath = App::config('uploads.private_root') . '/' . ltrim($meta['relative_path'], '/');
                if (is_file($absPath)) {
                    @unlink($absPath);
                }
            }

            $upd = $pdo->prepare("UPDATE file_metadata SET lifecycle_status = 'deleted', deleted_at = NOW() WHERE id = ?");
            $upd->execute([$fileMetadataId]);
            return true;
        });
    }

    public static function cleanupOrphans(int $olderThanHours = 24): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare("
            SELECT id FROM file_metadata
            WHERE storage_disk = 'private'
              AND lifecycle_status IN ('active','pending_deletion')
              AND created_at < DATE_SUB(NOW(), INTERVAL ? HOUR)
        ");
        $stmt->execute([$olderThanHours]);
        $cleaned = 0;
        foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $fid) {
            if (self::deleteIfUnreferenced((int) $fid)) {
                $cleaned++;
            }
        }
        return $cleaned;
    }
}
