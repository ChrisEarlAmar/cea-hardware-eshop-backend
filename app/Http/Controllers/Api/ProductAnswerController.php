<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductAnswer;
use App\Models\ProductQuestion;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Answering a product question - open to any signed-in user, not just
 * staff; is_staff_answer just reflects the answering user's own is_staff
 * flag so the UI can badge it, it doesn't gate who may answer. See
 * App\Http\Controllers\Api\ProductQuestionController for asking a question
 * in the first place.
 */
class ProductAnswerController extends Controller
{
    public function store(Request $request, ProductQuestion $question): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $user = $request->user();

        $answer = ProductAnswer::create([
            'product_question_id' => $question->id,
            'user_id' => $user->id,
            'is_staff_answer' => (bool) $user->is_staff,
            'body' => $validated['body'],
        ]);

        $answer->setRelation('user', $user);

        return ApiResponse::success($this->answerPayload($answer), status: 201);
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
