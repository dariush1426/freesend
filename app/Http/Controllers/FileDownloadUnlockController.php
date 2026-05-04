<?php

namespace App\Http\Controllers;

use App\Models\FileSend;
use App\Models\SharedFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class FileDownloadUnlockController extends Controller
{
    public function show(Request $request, FileSend $fileSend): View|RedirectResponse
    {
        $fileSend->load(['file', 'sender', 'receiver']);
        $this->authorizeParticipant($fileSend);

        $file = $fileSend->file;

        if (! $file->isPasswordProtected()) {
            return redirect()->route('file-sends.download', $fileSend);
        }

        if ($this->isUnlocked($request, $file)) {
            return redirect()->route('file-sends.download', $fileSend);
        }

        return view('files.unlock', [
            'fileSend' => $fileSend,
            'file' => $file,
        ]);
    }

    public function verify(Request $request, FileSend $fileSend): RedirectResponse
    {
        $fileSend->load('file');
        $this->authorizeParticipant($fileSend);

        $file = $fileSend->file;

        if (! $file->isPasswordProtected()) {
            return redirect()->route('file-sends.download', $fileSend);
        }

        $validated = $request->validate([
            'download_password' => ['required', 'string', 'max:120'],
        ]);

        if (! Hash::check($validated['download_password'], (string) $file->download_password_hash)) {
            return back()->withErrors([
                'download_password' => __('messages.download.password_incorrect'),
            ]);
        }

        $request->session()->put($this->sessionKey($file), true);

        return redirect()->route('file-sends.download', $fileSend);
    }

    private function authorizeParticipant(FileSend $fileSend): void
    {
        $userId = Auth::id();

        abort_unless(
            $fileSend->receiver_id === $userId || $fileSend->sender_id === $userId,
            403
        );
    }

    private function isUnlocked(Request $request, SharedFile $file): bool
    {
        return (bool) $request->session()->get($this->sessionKey($file), false);
    }

    private function sessionKey(SharedFile $file): string
    {
        return 'download_password_unlocked_'.$file->id;
    }
}
