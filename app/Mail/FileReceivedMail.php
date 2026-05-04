<?php

namespace App\Mail;

use App\Models\FileSend;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class FileReceivedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public FileSend $fileSend)
    {
        $this->fileSend->loadMissing(['file', 'sender', 'receiver']);
    }

    public function build(): self
    {
        return $this
            ->subject(__('mail.file_received.subject'))
            ->view('emails.file-received');
    }
}
