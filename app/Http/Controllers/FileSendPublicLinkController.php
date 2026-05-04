<?php

namespace App\Http\Controllers;

use App\Models\FileSend;
use App\Models\Setting;
use App\Support\PlanPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FileSendPublicLinkController extends Controller
{
    public function update(Request $request, FileSend $fileSend): RedirectResponse
    {
        $fileSend->load('file');

        abort_unless($fileSend->sender_id === Auth::id(), 403);

        $validated = $request->validate([
            'action' => ['required', 'string', 'in:enable,disable,regenerate'],
        ]);

        $action = (string) $validated['action'];

        if ($action === 'disable') {
            $fileSend->forceFill(['public_link_enabled' => false])->save();

            return back()->with('status', __('messages.public_link.disabled'));
        }

        $isFeatureEnabled = Setting::getValue('public_link_enabled', 'false') === 'true';
        $isAllowedByPlan = PlanPolicy::profileForUser($request->user())['allow_public_links'];

        if (! $isFeatureEnabled) {
            return back()->withErrors([
                'public_link' => __('messages.public_link.disabled_by_admin'),
            ]);
        }

        if (! $isAllowedByPlan) {
            return back()->withErrors([
                'public_link' => __('messages.public_link.disabled_by_plan'),
            ]);
        }

        if ($action === 'enable') {
            $fileSend->forceFill([
                'public_token' => $fileSend->public_token ?: FileSend::generatePublicToken(),
                'public_link_enabled' => true,
                'public_link_expires_at' => $fileSend->public_link_expires_at ?: $fileSend->file->expires_at,
            ])->save();

            return back()->with('status', __('messages.public_link.enabled'));
        }

        $fileSend->forceFill([
            'public_token' => FileSend::generatePublicToken(),
            'public_link_enabled' => true,
        ])->save();

        return back()->with('status', __('messages.public_link.regenerated'));
    }
}
