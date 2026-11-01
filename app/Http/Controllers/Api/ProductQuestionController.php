<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductAnswer;
use App\Models\ProductQuestion;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Customer Q&A on a product page. Unlike Reviews, nothing here is
 * moderated before going live - a question and any answer are visible
 * immediately on creation (a deliberate simplification for this learning
 * project, not an oversight). See
 * App\Http\Controllers\Api\ProductAnswerController for answering a
 * question, and App\Http\Controllers\Api\Admin\ProductQuestionController
 * for the staff-only abuse-cleanup deletion path.
 */
class ProductQuestionController extends Controller
{
    public function index(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        // Product::questions() already orders by created_at desc and
        // ProductQuestion::answers() by created_at asc; id is added as a
        // tie-breaker on both, since two rows can otherwise land in the
        // same second.
        $paginator = $product->questions()
            ->with([
                'user:id,name',
                'answers' => fn ($query) => $query->with('user:id,name')->orderBy('id'),
            ])
            ->orderByDesc('id')
            ->paginate($validated['per_page'] ?? 10);

        return ApiResponse::success(
            $paginator->getCollection()->map(fn (ProductQuestion $question) => $this->questionPayload($question)),
            meta: [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function store(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $user = $request->user();

        $question = ProductQuestion::create([
            'product_id' => $product->id,
            'user_id' => $user->id,
            'body' => $validated['body'],
        ]);

        $question->setRelation('user', $user);
        $question->setRelation('answers', collect());

        return ApiResponse::success($this->questionPayload($question), status: 201);
    }

    private function questionPayload(ProductQuestion $question): array
    {
        return [
            'id' => $question->id,
            'body' => $question->body,
            'author' => $question->relationLoaded('user') ? $question->user?->name : null,
            'created_at' => $question->created_at->toIso8601String(),
            'answers' => $question->relationLoaded('answers')
                ? $question->answers->map(fn (ProductAnswer $answer) => $this->answerPayload($answer))->values()->all()
                : [],
        ];
    }

    private function answerPayload(ProductAnswer $answer): array
    {
        return [
            'id' => $answer->id,
            'body' => $answer->body,
            'author' => $answer->relationLoaded('user') ? $answer->user?->name : null,
            'is_staff_answer' => $answer->is_staff_answer,
            'created_at' => $answer->created_at->toIso8601String(),
        ];
    }
}
