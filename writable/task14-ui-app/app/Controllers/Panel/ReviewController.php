<?php

namespace App\Controllers\Panel;

use App\Controllers\BaseController;
use App\Services\ReviewService;
use CodeIgniter\HTTP\RedirectResponse;

class ReviewController extends BaseController
{
    public function index(): string
    {
        $database = db_connect();
        $status = (string) $this->request->getGet('status');
        $builder = $database->table('product_reviews')->select('product_reviews.*, products.name AS product_name, sale_items.variant_name, sales.sale_number, users.name AS moderator_name')->join('products', 'products.id = product_reviews.product_id')->join('sale_items', 'sale_items.id = product_reviews.sale_item_id')->join('review_tokens', 'review_tokens.id = product_reviews.review_token_id')->join('sales', 'sales.id = review_tokens.sale_id')->join('users', 'users.id = product_reviews.moderated_by', 'left');
        if (in_array($status, ['pending', 'approved', 'rejected', 'hidden'], true)) { $builder->where('product_reviews.status', $status); }
        $reviews = $builder->orderBy('product_reviews.id', 'DESC')->limit(100)->get()->getResultArray();
        $imagesByReview = [];
        if ($reviews !== []) { foreach ($database->table('review_images')->whereIn('product_review_id', array_column($reviews, 'id'))->get()->getResultArray() as $image) { $imagesByReview[$image['product_review_id']][] = $image; } }
        $counts = [];
        foreach ($database->query('SELECT status, COUNT(*) AS total FROM product_reviews GROUP BY status')->getResultArray() as $row) { $counts[$row['status']] = (int) $row['total']; }
        return view('panel/reviews/index', ['title' => 'Moderasi ulasan', 'user' => session('user'), 'reviews' => $reviews, 'imagesByReview' => $imagesByReview, 'activeStatus' => $status, 'counts' => $counts]);
    }

    public function moderate(int $reviewId): RedirectResponse
    {
        try { (new ReviewService())->moderate($reviewId, (string) $this->request->getPost('action'), (string) $this->request->getPost('reason'), (int) session('user_id')); }
        catch (\Throwable $exception) { return redirect()->route('panel.reviews')->with('error', $exception->getMessage()); }
        return redirect()->route('panel.reviews')->with('success', 'Status ulasan diperbarui.');
    }
}
