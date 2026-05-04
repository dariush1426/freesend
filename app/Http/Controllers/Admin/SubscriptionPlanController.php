<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use App\Support\PlanPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SubscriptionPlanController extends Controller
{
    public function index(): View
    {
        abort_unless(Auth::user()?->is_admin, 403);

        $plans = SubscriptionPlan::query()
            ->withCount('subscriptions')
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('admin.plans.index', [
            'plans' => $plans,
        ]);
    }

    public function create(): View
    {
        abort_unless(Auth::user()?->is_admin, 403);

        return view('admin.plans.create', [
            'expireOptionValues' => PlanPolicy::EXPIRE_OPTION_VALUES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(Auth::user()?->is_admin, 403);

        $validated = $this->validatePlan($request);
        $payload = $this->planPayload($request, $validated);
        $plan = SubscriptionPlan::create($payload);

        $this->syncDefaultFlag($plan);

        return redirect()
            ->route('admin.plans.edit', $plan)
            ->with('status', __('messages.admin.plan_created'));
    }

    public function edit(SubscriptionPlan $plan): View
    {
        abort_unless(Auth::user()?->is_admin, 403);

        return view('admin.plans.edit', [
            'plan' => $plan,
            'expireOptionValues' => PlanPolicy::EXPIRE_OPTION_VALUES,
        ]);
    }

    public function update(Request $request, SubscriptionPlan $plan): RedirectResponse
    {
        abort_unless(Auth::user()?->is_admin, 403);

        $validated = $this->validatePlan($request, $plan);
        $payload = $this->planPayload($request, $validated, $plan);
        $plan->update($payload);

        $this->syncDefaultFlag($plan);

        return back()->with('status', __('messages.admin.plan_updated'));
    }

    private function validatePlan(Request $request, ?SubscriptionPlan $plan = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => [
                'nullable',
                'string',
                'max:120',
                'regex:/^[a-z0-9\-]+$/',
                Rule::unique('subscription_plans', 'slug')->ignore($plan?->id),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'price_amount' => ['nullable', 'integer', 'min:0', 'max:99999999999'],
            'duration_value' => ['nullable', 'integer', 'min:1', 'max:120'],
            'duration_unit' => ['nullable', 'string', 'in:day,month'],
            'max_upload_size_mb' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'max_storage_mb' => ['nullable', 'integer', 'min:1', 'max:500000'],
            'max_team_members' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'expire_options' => ['nullable', 'array'],
            'expire_options.*' => ['string', Rule::in(PlanPolicy::EXPIRE_OPTION_VALUES)],
            'is_active' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
            'allow_public_links' => ['nullable', 'boolean'],
            'allow_password_protection' => ['nullable', 'boolean'],
            'allow_custom_expiry' => ['nullable', 'boolean'],
            'allow_never_expire' => ['nullable', 'boolean'],
            'allow_personal_storage' => ['nullable', 'boolean'],
            'allow_team_features' => ['nullable', 'boolean'],
            'allow_signature_workflow' => ['nullable', 'boolean'],
            'allow_folders' => ['nullable', 'boolean'],
            'allow_ai_features' => ['nullable', 'boolean'],
        ]);
    }

    private function planPayload(Request $request, array $validated, ?SubscriptionPlan $plan = null): array
    {
        $slug = trim((string) ($validated['slug'] ?? ''));

        if ($slug === '') {
            $slug = Str::slug((string) $validated['name']);
        }

        $options = PlanPolicy::expireOptionValues([
            'expire_options' => $validated['expire_options'] ?? null,
            'allow_custom_expiry' => $request->boolean('allow_custom_expiry'),
            'allow_never_expire' => $request->boolean('allow_never_expire'),
        ]);

        $isDefault = $request->boolean('is_default');
        $isActive = $request->boolean('is_active') || $isDefault;

        return [
            'name' => (string) $validated['name'],
            'slug' => $slug !== '' ? $slug : 'plan-'.($plan?->id ?? time()),
            'description' => $validated['description'] ?? null,
            'is_active' => $isActive,
            'is_default' => $isDefault,
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'price_amount' => isset($validated['price_amount']) && $validated['price_amount'] !== ''
                ? (int) $validated['price_amount']
                : 0,
            'duration_value' => ! empty($validated['duration_value']) ? (int) $validated['duration_value'] : null,
            'duration_unit' => ! empty($validated['duration_unit']) ? (string) $validated['duration_unit'] : null,
            'max_upload_size_mb' => ! empty($validated['max_upload_size_mb']) ? (int) $validated['max_upload_size_mb'] : null,
            'max_storage_mb' => ! empty($validated['max_storage_mb']) ? (int) $validated['max_storage_mb'] : null,
            'expire_options' => $options,
            'allow_public_links' => $request->boolean('allow_public_links'),
            'allow_password_protection' => $request->boolean('allow_password_protection'),
            'allow_custom_expiry' => $request->boolean('allow_custom_expiry'),
            'allow_never_expire' => $request->boolean('allow_never_expire'),
            'allow_personal_storage' => $request->boolean('allow_personal_storage'),
            'allow_team_features' => $request->boolean('allow_team_features'),
            'max_team_members' => ! empty($validated['max_team_members']) ? (int) $validated['max_team_members'] : null,
            'allow_signature_workflow' => $request->boolean('allow_signature_workflow'),
            'allow_folders' => $request->boolean('allow_folders'),
            'allow_ai_features' => $request->boolean('allow_ai_features'),
        ];
    }

    private function syncDefaultFlag(SubscriptionPlan $plan): void
    {
        if ($plan->is_default) {
            SubscriptionPlan::query()
                ->where('id', '!=', $plan->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }
    }
}
