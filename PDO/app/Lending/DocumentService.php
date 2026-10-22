<?php
require_once dirname(__DIR__, 1) . '/autoload.php';

class DocumentService extends LendingAccess
{
    public const TYPES = ['NATIONAL_ID' => 'National ID', 'PROOF_OF_INCOME' => 'Proof of income', 'OTHER' => 'Supporting document'];

    public static function storage(): string
    {
        // Default is outside the web root. Set a persistent private directory in production.
        // Keep the original project storage identity when implementation folders move.
        $storageIdentity = dirname(__DIR__, 2) . '/modules/lending';
        return rtrim(getenv('DOCUMENT_STORAGE_PATH') ?: sys_get_temp_dir() . '/mlms-documents-' . substr(hash('sha256', $storageIdentity), 0, 16), '/');
    }

    public static function ready(PDO $db, int $customerId, bool $collateral = false): bool
    {
        $s = $db->prepare("SELECT d.document_type, d.review_status FROM customer_documents d
            WHERE d.customer_id=? AND d.document_id=(SELECT MAX(v.document_id) FROM customer_documents v
                WHERE v.customer_id=d.customer_id AND v.document_type=d.document_type)");
        $s->execute([$customerId]);
        $statuses = $s->fetchAll(PDO::FETCH_KEY_PAIR);
        foreach ($collateral ? ['NATIONAL_ID', 'PROOF_OF_INCOME', 'OTHER'] : ['NATIONAL_ID', 'PROOF_OF_INCOME'] as $type) {
            if (($statuses[$type] ?? '') !== 'APPROVED') {
                return false;
            }
        }
        return true;
    }

    public function documents(?int $customerId = null, bool $pending = false): array
    {
        $this->allow(['CUSTOMER', 'FIRM', 'MANAGER', 'SUPER_ADMIN']);
        return $this->rows('SELECT d.*, c.full_name, c.kyc_status,
            (d.document_id=(SELECT MAX(v.document_id) FROM customer_documents v WHERE v.customer_id=d.customer_id AND v.document_type=d.document_type)) AS is_latest
            FROM customer_documents d JOIN customers c ON c.customer_id=d.customer_id WHERE ' . $this->scope()
            . ($customerId ? ' AND c.customer_id=' . $customerId : '')
            . ($pending ? " AND d.review_status='PENDING' AND d.document_id=(SELECT MAX(v.document_id) FROM customer_documents v WHERE v.customer_id=d.customer_id AND v.document_type=d.document_type)" : '')
            . ' ORDER BY is_latest DESC, d.document_id DESC LIMIT 200');
    }

    public function upload(string $type, array $file): int
    {
        $this->allow(['CUSTOMER']);
        if (!isset(self::TYPES[$type])) {
            throw new DomainException('Choose a valid document type.');
        }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null)
            || !is_uploaded_file($file['tmp_name'])) {
            throw new DomainException('Choose a PDF, JPG, or PNG file up to 5 MB. Check your server upload limit if the upload fails.');
        }
        $size = filesize($file['tmp_name']);
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if ($size < 1 || $size > 5242880 || !in_array($mime, ['application/pdf', 'image/jpeg', 'image/png'], true)) {
            throw new DomainException('Only PDF, JPG, and PNG documents up to 5 MB are accepted.');
        }
        $folder = self::storage();
        if ((!is_dir($folder) && !mkdir($folder, 0700, true)) || !is_writable($folder)) {
            throw new RuntimeException('Private document storage is not writable.');
        }
        $webRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__, 3));
        if ($webRoot && str_starts_with(realpath($folder) . '/', rtrim($webRoot, '/') . '/')) {
            throw new RuntimeException('Document storage must be outside the web root.');
        }
        $key = bin2hex(random_bytes(24));
        $destination = $folder . '/' . $key;
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new RuntimeException('The uploaded file could not be stored.');
        }
        chmod($destination, 0600);
        try {
            return $this->atomic(function () use ($type, $file, $key, $mime, $size): int {
                $profile = $this->rows('SELECT customer_id FROM customers WHERE user_id=?', [$this->actor['user_id']])[0] ?? null;
                if (!$profile) {
                    throw new DomainException('Ask your firm to link your customer profile first.');
                }
                $customer = $this->customer((int) $profile['customer_id'], true);
                if ($customer['firm_status'] !== 'APPROVED') {
                    throw new DomainException('Your firm must be approved before you upload documents.');
                }
                $name = mb_substr(preg_replace('/[\x00-\x1f\x7f]/u', '', basename((string) $file['name'])) ?: 'Document', 0, 190);
                $this->run('INSERT INTO customer_documents (customer_id,document_type,file_path,mime_type,file_size_bytes,uploaded_by,original_name) VALUES (?,?,?,?,?,?,?)',
                    [$customer['customer_id'], $type, $key, $mime, $size, $this->actor['user_id'], $name]);
                $id = (int) $this->db->lastInsertId();
                if ($type !== 'OTHER') {
                    $this->run("UPDATE customers SET kyc_status='PENDING' WHERE customer_id=?", [$customer['customer_id']]);
                }
                $this->audit('DOCUMENT_UPLOADED', 'customer_documents', $id, $type);
                return $id;
            });
        } catch (Throwable $error) {
            unlink($destination);
            throw $error;
        }
    }

    public function review(int $id, string $decision, string $notes): void
    {
        $this->allow(['FIRM', 'SUPER_ADMIN']);
        if (!in_array($decision, ['APPROVED', 'REJECTED'], true)) {
            throw new DomainException('Choose approve or reject.');
        }
        if ($decision === 'REJECTED') {
            $notes = self::text($notes, 1000, 'A rejection reason');
        } elseif (mb_strlen($notes) > 1000) {
            throw new DomainException('Review notes must be at most 1000 characters.');
        }
        $doc = $this->rows('SELECT d.* FROM customer_documents d JOIN customers c ON c.customer_id=d.customer_id WHERE d.document_id=? AND ' . $this->scope(), [$id])[0] ?? null;
        if (!$doc) {
            throw new OutOfBoundsException('Document not found.');
        }
        $this->atomic(function () use ($doc, $id, $decision, $notes): void {
            $this->customer((int) $doc['customer_id'], true);
            $latest = $this->rows('SELECT * FROM customer_documents WHERE customer_id=? AND document_type=? ORDER BY document_id DESC LIMIT 1 FOR UPDATE', [$doc['customer_id'], $doc['document_type']])[0];
            if ((int) $latest['document_id'] !== $id || $latest['review_status'] !== 'PENDING') {
                throw new DomainException('This document was already reviewed or replaced. Refresh the document list.');
            }
            $this->run('UPDATE customer_documents SET review_status=?,reviewed_by=?,reviewed_at=NOW(),review_notes=? WHERE document_id=?',
                [$decision, $this->actor['user_id'], trim($notes) ?: null, $id]);
            $statuses = $this->rows("SELECT review_status FROM customer_documents d WHERE customer_id=? AND document_type IN ('NATIONAL_ID','PROOF_OF_INCOME') AND document_id=(SELECT MAX(v.document_id) FROM customer_documents v WHERE v.customer_id=d.customer_id AND v.document_type=d.document_type)", [$doc['customer_id']]);
            $status = self::ready($this->db, (int) $doc['customer_id']) ? 'VERIFIED'
                : (in_array('REJECTED', array_column($statuses, 'review_status'), true) ? 'REJECTED' : 'PENDING');
            $this->run('UPDATE customers SET kyc_status=? WHERE customer_id=?', [$status, $doc['customer_id']]);
            $this->audit('DOCUMENT_' . $decision, 'customer_documents', $id, $notes);
        });
    }

    public function download(int $id): array
    {
        $this->allow(['CUSTOMER', 'FIRM', 'MANAGER', 'SUPER_ADMIN']);
        $doc = $this->rows('SELECT d.* FROM customer_documents d JOIN customers c ON c.customer_id=d.customer_id WHERE document_id=? AND ' . $this->scope(), [$id])[0] ?? null;
        if (!$doc || !preg_match('/^[a-f0-9]{48}$/D', $doc['file_path']) || !is_file(self::storage() . '/' . $doc['file_path'])) {
            throw new OutOfBoundsException('Document file not found.');
        }
        return $doc;
    }
}
