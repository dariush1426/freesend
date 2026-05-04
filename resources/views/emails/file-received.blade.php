<div dir="{{ app()->getLocale() === 'fa' ? 'rtl' : 'ltr' }}" style="font-family: Tahoma, Arial, sans-serif; line-height: 1.8;">
    <h2>{{ __('mail.file_received.title') }}</h2>
    <p>{{ __('mail.file_received.body', ['sender' => $fileSend->senderDisplayName()]) }}</p>
    <p><strong>{{ __('mail.file_received.file_name') }}</strong> {{ $fileSend->file->original_name }}</p>
    <p>{{ __('mail.file_received.login_prompt') }}</p>
</div>
