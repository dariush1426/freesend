<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SubscriptionOrderController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(Auth::user()?->is_admin, 403);

        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));

        $orders = SubscriptionOrder::query()
            ->with(['user', 'plan', 'latestPayment'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($builder) use ($search): void {
                    $builder
                        ->where('order_number', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($userQuery) use ($search): void {
                            $userQuery
                                ->where('username', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('mobile', 'like', "%{$search}%");
                        })
                        ->orWhereHas('plan', fn ($planQuery) => $planQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.orders.index', [
            'orders' => $orders,
            'search' => $search,
            'status' => $status,
            'statuses' => [
                SubscriptionOrder::STATUS_PENDING,
                SubscriptionOrder::STATUS_REDIRECTED,
                SubscriptionOrder::STATUS_PAID,
                SubscriptionOrder::STATUS_FAILED,
                SubscriptionOrder::STATUS_CANCELLED,
            ],
        ]);
    }
}
