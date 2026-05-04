<?php

namespace App\Http\Controllers;

use App\Models\FileSend;
use App\Support\FilePreviewPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class GuestFileSendController extends Controller
{
    public function show(Request $request, FileSend $fileSend): View
    {
        abort_unless($fileSend->receiver_id === Auth::id(), 403);
        abort_unless($fileSend->isGuestSender(), 404);

        if ($fileSend->read_at === null) {
            $fileSend->forceFill(['read_at' => now()])->save();
        }

        return view('guest.show', [
            'fileSend' => $fileSend->loadMissing(['file', 'receiver']),
            'file' => $fileSend->file,
            'previewPolicy' => FilePreviewPolicy::fromSettings(),
            'unlockedFileIds' => $request->session()->get('download_password_unlocked_'.$fileSend->file_id, false)
                ? [$fileSend->file_id]
                : [],
        ]);
    }
}
