<?php

namespace Tests\Feature\Api;

use App\Http\Controllers\Api\Admin\ProductQuestionController as AdminProductQuestionController;
use App\Http\Controllers\Api\ProductAnswerController;
use App\Http\Controllers\Api\ProductQuestionController;
use App\Models\AdminAuditLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAnswer;
use App\Models\ProductQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * NOTE: these routes are not yet wired into routes/api.php - that file is
 * owned by a separate integration pass merging every feature slice at once
 * (see this feature's task notes), so it is deliberately not touched here.
 * setUp() below registers the exact same routes locally, through the same
 * 'api' middleware group + guards, purely so this slice's controllers can
 * be exercised over HTTP ahead of that integration. See "## ROUTES NEEDED"
 * in the final report for the real routes/api.php lines to add.
 */
class ProductQuestionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('api')->group(function () {
            Route::get('/api/products/{product:slug}/questions', [ProductQuestionController::class, 'index']);

            Route::middleware('auth:sanctum')->group(function () {
                Route::post('/api/products/{product:slug}/questions', [ProductQuestionController::class, 'store']);
                Route::post('/api/questions/{question}/answers', [ProductAnswerController::class, 'store']);

                Route::middleware('staff')->prefix('api/admin')->group(function () {
                    Route::delete('/questions/{question}', [AdminProductQuestionController::class, 'destroyQuestion']);
                    Route::delete('/answers/{answer}', [AdminProductQuestionController::class, 'destroyAnswer']);
                });
            });
        });
    }

    public function test_asking_a_question_requires_authentication(): void
    {
        $product = $this->product();

        $this->postJson("/api/products/{$product->slug}/questions", ['body' => 'Does this fit a 10mm bolt?'])
            ->assertStatus(401);
    }

    public function test_a_signed_in_user_can_ask_a_question(): void
    {
        $user = User::factory()->create(['name' => 'Asking Annie']);
        $product = $this->product();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/products/{$product->slug}/questions", ['body' => 'Does this fit a 10mm bolt?']);

        $response->assertStatus(201)
            ->assertJsonPath('data.body', 'Does this fit a 10mm bolt?')
            ->assertJsonPath('data.author', 'Asking Annie')
            ->assertJsonPath('data.answers', []);

        $this->assertDatabaseHas('product_questions', [
            'product_id' => $product->id,
            'user_id' => $user->id,
            'body' => 'Does this fit a 10mm bolt?',
        ]);
    }

    public function test_a_question_body_is_required(): void
    {
        $user = User::factory()->create();
        $product = $this->product();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/products/{$product->slug}/questions", ['body' => ''])
            ->assertStatus(422);
    }

    public function test_answering_a_question_requires_authentication(): void
    {
        $product = $this->product();
        $question = $this->questionOn($product);

        $this->postJson("/api/questions/{$question->id}/answers", ['body' => 'Yes, it does.'])
            ->assertStatus(401);
    }

    public function test_a_signed_in_user_can_answer_a_question(): void
    {
        $product = $this->product();
        $question = $this->questionOn($product);
        $answerer = User::factory()->create(['name' => 'Helpful Hank']);

        $response = $this->actingAs($answerer, 'sanctum')
            ->postJson("/api/questions/{$question->id}/answers", ['body' => 'Yes, it does.']);

        $response->assertStatus(201)
            ->assertJsonPath('data.body', 'Yes, it does.')
            ->assertJsonPath('data.author', 'Helpful Hank')
            ->assertJsonPath('data.is_staff_answer', false);

        $this->assertDatabaseHas('product_answers', [
            'product_question_id' => $question->id,
            'user_id' => $answerer->id,
            'is_staff_answer' => false,
        ]);
    }

    public function test_an_answer_from_a_staff_user_is_flagged_as_a_staff_answer(): void
    {
        $product = $this->product();
        $question = $this->questionOn($product);
        $staff = User::factory()->create(['is_staff' => true, 'name' => 'Store Staff']);

        $response = $this->actingAs($staff, 'sanctum')
            ->postJson("/api/questions/{$question->id}/answers", ['body' => 'Confirmed by the manufacturer.']);

        $response->assertStatus(201)->assertJsonPath('data.is_staff_answer', true);

        $this->assertDatabaseHas('product_answers', [
            'product_question_id' => $question->id,
            'user_id' => $staff->id,
            'is_staff_answer' => true,
        ]);
    }

    public function test_the_public_index_lists_questions_newest_first_with_their_answers_in_order(): void
    {
        $product = $this->product();
        $asker = User::factory()->create(['name' => 'Asking Annie']);
        $staffAnswerer = User::factory()->create(['is_staff' => true, 'name' => 'Store Staff']);
        $customerAnswerer = User::factory()->create(['name' => 'Helpful Hank']);

        $olderQuestion = ProductQuestion::create([
            'product_id' => $product->id, 'user_id' => $asker->id, 'body' => 'Older question?',
        ]);
        $newerQuestion = ProductQuestion::create([
            'product_id' => $product->id, 'user_id' => $asker->id, 'body' => 'Newer question?',
        ]);

        $firstAnswer = ProductAnswer::create([
            'product_question_id' => $olderQuestion->id, 'user_id' => $customerAnswerer->id,
            'is_staff_answer' => false, 'body' => 'A customer chimes in first.',
        ]);
        $secondAnswer = ProductAnswer::create([
            'product_question_id' => $olderQuestion->id, 'user_id' => $staffAnswerer->id,
            'is_staff_answer' => true, 'body' => 'Staff confirms afterwards.',
        ]);

        $response = $this->getJson("/api/products/{$product->slug}/questions");

        $response->assertStatus(200);
        $this->assertSame('Newer question?', $response->json('data.0.body'));
        $this->assertSame('Older question?', $response->json('data.1.body'));

        $answers = $response->json('data.1.answers');
        $this->assertCount(2, $answers);
        $this->assertSame('A customer chimes in first.', $answers[0]['body']);
        $this->assertSame('Helpful Hank', $answers[0]['author']);
        $this->assertFalse($answers[0]['is_staff_answer']);
        $this->assertSame('Staff confirms afterwards.', $answers[1]['body']);
        $this->assertSame('Store Staff', $answers[1]['author']);
        $this->assertTrue($answers[1]['is_staff_answer']);
    }

    public function test_a_non_staff_user_cannot_delete_another_users_question(): void
    {
        $product = $this->product();
        $question = $this->questionOn($product);
        $otherUser = User::factory()->create();

        $this->actingAs($otherUser, 'sanctum')
            ->deleteJson("/api/admin/questions/{$question->id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('product_questions', ['id' => $question->id]);
    }

    public function test_a_non_staff_user_cannot_delete_another_users_answer(): void
    {
        $product = $this->product();
        $question = $this->questionOn($product);
        $answer = ProductAnswer::create([
            'product_question_id' => $question->id, 'user_id' => User::factory()->create()->id,
            'is_staff_answer' => false, 'body' => 'Some answer.',
        ]);
        $otherUser = User::factory()->create();

        $this->actingAs($otherUser, 'sanctum')
            ->deleteJson("/api/admin/answers/{$answer->id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('product_answers', ['id' => $answer->id]);
    }

    public function test_staff_can_delete_a_question_and_it_is_audit_logged(): void
    {
        $product = $this->product();
        $question = $this->questionOn($product);
        $staff = User::factory()->create(['is_staff' => true]);

        $this->actingAs($staff, 'sanctum')
            ->deleteJson("/api/admin/questions/{$question->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('product_questions', ['id' => $question->id]);
        $this->assertDatabaseHas('admin_audit_logs', [
            'actor_id' => $staff->id,
            'action' => 'product_question.delete',
            'subject_type' => (new ProductQuestion())->getMorphClass(),
            'subject_id' => $question->id,
        ]);
    }

    public function test_staff_can_delete_an_answer_and_it_is_audit_logged(): void
    {
        $product = $this->product();
        $question = $this->questionOn($product);
        $answer = ProductAnswer::create([
            'product_question_id' => $question->id, 'user_id' => User::factory()->create()->id,
            'is_staff_answer' => false, 'body' => 'Some answer.',
        ]);
        $staff = User::factory()->create(['is_staff' => true]);

        $this->actingAs($staff, 'sanctum')
            ->deleteJson("/api/admin/answers/{$answer->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('product_answers', ['id' => $answer->id]);
        $this->assertSame(1, AdminAuditLog::where('action', 'product_answer.delete')->where('actor_id', $staff->id)->count());
    }

    private function product(string $name = 'Hex Bolt', ?string $slug = null): Product
    {
        $category = Category::create(['name' => 'Bolts', 'slug' => 'bolts-'.uniqid()]);

        return Product::create([
            'category_id' => $category->id,
            'name' => $name,
            'slug' => $slug ?? 'product-'.uniqid(),
        ]);
    }

    private function questionOn(Product $product): ProductQuestion
    {
        $asker = User::factory()->create();

        return ProductQuestion::create([
            'product_id' => $product->id,
            'user_id' => $asker->id,
            'body' => 'Does this fit a 10mm bolt?',
        ]);
    }
}
