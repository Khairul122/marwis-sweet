<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

class ReviewService
{
    public function __construct(private ?BaseConnection $database = null)
    {
        $this->database ??= db_connect();
    }

    public function issueToken(int $saleId): string
    {
        $sale = $this->database->table('sales')->where('id', $saleId)->whereIn('status', ['paid', 'partially_refunded'])->get()->getRowArray();
        if (!$sale) { throw new RuntimeException('Tautan ulasan tidak dapat dibuat untuk transaksi ini.'); }
        $token = bin2hex(random_bytes(32));
        $now = date('Y-m-d H:i:s');
        $this->database->transStart();
        $this->database->table('review_tokens')->where('sale_id', $saleId)->where('disabled_at', null)->update(['disabled_at' => $now, 'disabled_reason' => 'Diganti dengan tautan baru']);
        $this->database->table('review_tokens')->insert(['sale_id' => $saleId, 'token_hash' => hash('sha256', $token), 'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')), 'created_at' => $now]);
        $this->database->transComplete();
        if (!$this->database->transStatus()) { throw new RuntimeException('Tautan ulasan belum dapat dibuat.'); }
        return $token;
    }

    public function resolve(string $token): array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) { throw new RuntimeException('Tautan ulasan tidak valid.'); }
        $row = $this->database->table('review_tokens')->select('review_tokens.*, sales.sale_number, sales.status AS sale_status')->join('sales', 'sales.id = review_tokens.sale_id')->where('token_hash', hash('sha256', $token))->get()->getRowArray();
        if (!$row || $row['disabled_at'] || $row['expires_at'] < date('Y-m-d H:i:s') || $row['sale_status'] === 'refunded') { throw new RuntimeException('Tautan ulasan sudah kedaluwarsa atau tidak aktif.'); }
        $items = $this->database->table('sale_items')->select('sale_items.id, sale_items.product_id, sale_items.product_name, sale_items.variant_name, product_reviews.id AS review_id, product_reviews.status AS review_status')->join('product_reviews', 'product_reviews.sale_item_id = sale_items.id', 'left')->where('sale_items.sale_id', $row['sale_id'])->orderBy('sale_items.id')->get()->getResultArray();
        return ['token' => $row, 'items' => $items];
    }

    public function submit(string $token, array $payload, array $files, string $ipAddress): void
    {
        $context = $this->resolve($token);
        $this->recordAttempt($token, $ipAddress);
        if ($this->attemptCount($token, $ipAddress) > 10) { throw new RuntimeException('Terlalu banyak percobaan. Coba kembali satu jam lagi.'); }
        $saleItemId = filter_var($payload['sale_item_id'] ?? null, FILTER_VALIDATE_INT);
        $item = array_values(array_filter($context['items'], static fn (array $row): bool => (int) $row['id'] === (int) $saleItemId && !$row['review_id']))[0] ?? null;
        $rating = filter_var($payload['rating'] ?? null, FILTER_VALIDATE_INT);
        $displayName = trim((string) ($payload['display_name'] ?? ''));
        $comment = trim((string) ($payload['comment'] ?? ''));
        if (!$item || !$rating || $rating < 1 || $rating > 5) { throw new RuntimeException('Pilih produk dan rating 1 sampai 5.'); }
        if (mb_strlen($displayName) > 80 || mb_strlen($comment) < 10 || mb_strlen($comment) > 1000) { throw new RuntimeException('Komentar harus berisi 10 sampai 1.000 karakter dan nama maksimal 80 karakter.'); }
        $files = array_values(array_filter($files, static fn ($file): bool => $file && $file->getError() !== UPLOAD_ERR_NO_FILE));
        if (count($files) > 3) { throw new RuntimeException('Maksimal tiga foto untuk satu ulasan.'); }
        $storedPaths = [];
        try {
            foreach ($files as $file) { $storedPaths[] = (new ReviewImageService())->store($file); }
            $now = date('Y-m-d H:i:s');
            $this->database->transException(true)->transStart();
            $this->database->table('product_reviews')->insert([
                'review_token_id' => $context['token']['id'], 'sale_item_id' => $item['id'], 'product_id' => $item['product_id'],
                'display_name' => $displayName ?: null, 'rating' => $rating, 'comment' => $comment, 'status' => 'pending',
                'submitted_ip_hash' => $this->ipHash($ipAddress), 'created_at' => $now, 'updated_at' => $now,
            ]);
            $reviewId = (int) $this->database->insertID();
            foreach ($storedPaths as $path) { $this->database->table('review_images')->insert(['product_review_id' => $reviewId, 'image_path' => $path, 'created_at' => $now]); }
            $this->database->transComplete();
        } catch (\Throwable $exception) {
            $this->database->transRollback();
            foreach ($storedPaths as $path) { @unlink(WRITEPATH . str_replace('/', DIRECTORY_SEPARATOR, $path)); }
            if (str_contains(strtolower($exception->getMessage()), 'duplicate')) { throw new RuntimeException('Produk ini sudah pernah diulas melalui tautan tersebut.'); }
            throw $exception;
        }
    }

    public function moderate(int $reviewId, string $action, string $reason, int $ownerId): void
    {
        if (!in_array($action, ['approve', 'reject', 'hide'], true)) { throw new RuntimeException('Tindakan moderasi tidak valid.'); }
        $review = $this->database->table('product_reviews')->where('id', $reviewId)->get()->getRowArray();
        if (!$review || ($action === 'hide' ? $review['status'] !== 'approved' : $review['status'] !== 'pending')) { throw new RuntimeException('Ulasan tidak tersedia untuk tindakan tersebut.'); }
        if ($action !== 'approve' && (mb_strlen(trim($reason)) < 5 || mb_strlen(trim($reason)) > 500)) { throw new RuntimeException('Alasan moderasi harus berisi 5 sampai 500 karakter.'); }
        $status = ['approve' => 'approved', 'reject' => 'rejected', 'hide' => 'hidden'][$action];
        $this->database->table('product_reviews')->where('id', $reviewId)->update(['status' => $status, 'moderation_reason' => $action === 'approve' ? null : trim($reason), 'moderated_by' => $ownerId, 'moderated_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
    }

    public function attemptCount(string $token, string $ipAddress): int
    {
        return (int) (cache()->get($this->attemptKey($token, $ipAddress)) ?? 0);
    }

    private function recordAttempt(string $token, string $ipAddress): void
    {
        $key = $this->attemptKey($token, $ipAddress); cache()->save($key, $this->attemptCount($token, $ipAddress) + 1, 3600);
    }

    private function attemptKey(string $token, string $ipAddress): string
    {
        return 'review_attempt_' . hash('sha256', $token . '|' . $ipAddress);
    }

    private function ipHash(string $ipAddress): string
    {
        return hash_hmac('sha256', $ipAddress, (string) env('REVIEW_HASH_KEY', 'marwis-review-local-key'));
    }
}
