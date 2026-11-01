<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductAnswer;
use App\Models\ProductQuestion;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Staff-only abuse cleanup for product Q&A. Unlike Reviews, a question or
 * answer is never held for moderation before going live (see
 * App\Http\Controllers\Api\ProductQuestionController /
 * ProductAnswerController) - this outright delete is the only backstop
 * against spam or abuse, audit-logged, reachable only via the `staff`
 * middleware (see routes/api.php). Two resource types live in one
 * controller here (destroyQuestion/destroyAnswer rather than two
 * same-named `destroy` methods, which PHP does not allow) since each is a
 * single one-line action not worth a controller of its own.
 */
class ProductQuestionController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function destroyQuestion(Request $request, ProductQuestion $question): JsonResponse
    {
        $this->auditLogger->log($request->user(), 'product_question.delete', $question, [
            'product_id' => $question->product_id,
        ]);

        $question->delete();

        return ApiResponse::success(['message' => 'Question deleted.']);
    }

    public function destroyAnswer(Request $request, ProductAnswer $answer): JsonResponse
    {
        $this->auditLogger->log($request->user(), 'product_answer.delete', $answer, [
            'product_question_id' => $answer->product_question_id,
        ]);

        $answer->delete();

        return ApiResponse::success(['message' => 'Answer deleted.']);
    }
}
