<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Support\PlanPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SubscriptionSubscriberController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(Auth::user()?->is_admin, 403);

        $search = trim((string) $request->query('search', ''));

        $users = User::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($builder) use ($search): void {
                    $builder
                        ->where('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('mobile', 'like', "%{$search}%")
                        ->orWhere('full_name', 'like', "%{$search}%");
                });
            })
            ->with(['subscriptions.plan'])
            ->withCount('subscriptionOrders')
            ->orderByDesc('is_admin')
            ->orderBy('username')
            ->paginate(12)
            ->withQueryString();

        return view('admin.subscribers.index', [
            'plans' => SubscriptionPlan::query()
                ->where('is_active', true)
                ->orderByDesc('is_default')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'users' => $users,
            'search' => $search,
        ]);
    }

    public function assign(Request $request): RedirectResponse
    {
        abort_unless(Auth::user()?->is_admin, 403);

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'plan_id' => ['required', 'integer', 'exists:subscription_plans,id'],
            'ends_at' => ['nullable', 'date', 'after:now'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $user = User::query()->findOrFail((int) $validated['user_id']);
        $plan = SubscriptionPlan::query()->findOrFail((int) $validated['plan_id']);

        PlanPolicy::assignPlan(
            $user,
            $plan,
            ! empty($validated['ends_at']) ? Carbon::parse((string) $validated['ends_at']) : null,
            $request->user(),
            $validated['notes'] ?? null,
        );

        return back()->with('status', __('messages.admin.plan_assigned', [
            'user' => $user->username,
            'plan' => $plan->name,
        ]));
    }
}
