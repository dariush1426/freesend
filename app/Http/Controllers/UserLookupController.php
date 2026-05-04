<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserLookupController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q'));

        if ($query === '') {
            return response()->json([
                'found' => false,
                'users' => [],
            ]);
        }

        $users = User::query()
            ->where('id', '!=', Auth::id())
            ->where(function ($builder) use ($query): void {
                $builder
                    ->where('username', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%")
                    ->orWhere('mobile', 'like', "%{$query}%");
            })
            ->orderBy('username')
            ->limit(8)
            ->get(['id', 'username', 'full_name', 'email', 'mobile']);

        return response()->json([
            'found' => $users->isNotEmpty(),
            'users' => $users->map(fn (User $user): array => [
                'id' => $user->id,
                'username' => $user->username,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'mobile' => $user->mobile,
            ])->all(),
        ]);
    }
}
