<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\TaxRate;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Staff-only management of the flat per-state tax rate table TaxService
 * reads from. A state with no row here simply charges 0% - see TaxService.
 * Reachable only via the `staff` middleware (see routes/api.php).
 */
class TaxRateController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function index(): JsonResponse
    {
        return ApiResponse::success(
            TaxRate::orderBy('state')->get()->map(fn (TaxRate $taxRate) => $this->taxRatePayload($taxRate))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateTaxRate($request);

        $taxRate = TaxRate::create($validated);

        $this->auditLogger->log($request->user(), 'tax_rate.create', $taxRate, $validated);

        return ApiResponse::success($this->taxRatePayload($taxRate), status: 201);
    }

    public function update(Request $request, TaxRate $taxRate): JsonResponse
    {
        $validated = $this->validateTaxRate($request, $taxRate);

        $taxRate->update($validated);

        $this->auditLogger->log($request->user(), 'tax_rate.update', $taxRate, $validated);

        return ApiResponse::success($this->taxRatePayload($taxRate->fresh()));
    }

    public function destroy(Request $request, TaxRate $taxRate): JsonResponse
    {
        $state = $taxRate->state;
        $taxRate->delete();

        $this->auditLogger->log($request->user(), 'tax_rate.delete', null, ['state' => $state]);

        return ApiResponse::success(['message' => 'Tax rate removed.']);
    }

    /**
     * @return array{state: string, rate_percent: string}
     */
    private function validateTaxRate(Request $request, ?TaxRate $taxRate = null): array
    {
        return $request->validate([
            'state' => ['required', 'string', 'size:2', Rule::unique('tax_rates', 'state')->ignore($taxRate?->id)],
            'rate_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);
    }

    private function taxRatePayload(TaxRate $taxRate): array
    {
        return [
            'id' => $taxRate->id,
            'state' => $taxRate->state,
            'rate_percent' => (string) $taxRate->rate_percent,
        ];
    }
}
