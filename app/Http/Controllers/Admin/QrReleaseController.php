<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\Tickets\QrReleaseSandbox;
use App\Services\Tickets\QrReleaseService;
use Illuminate\Http\Request;

/** Admin controls for QR-release e-mails: Test sandbox, going Live (with a recipient preview), retrying failed notifications. */
class QrReleaseController extends Controller
{
    private function back(Event $event, string $kind, string $message)
    {
        return redirect()->to(route('admin.events.edit', $event).'#ev-release')->with($kind, $message);
    }

    /** Test mode: sample tickets only. Never touches a real ticket, the real QR visibility or the gate. */
    public function sandbox(Request $request, Event $event, QrReleaseSandbox $sandbox)
    {
        $data = $request->validate([
            'action' => ['required', 'in:reset,show,hide,add-late,retry,sample'],
            'test_email' => ['nullable', 'email', 'max:255'],
            'simulate_failure' => ['nullable', 'boolean'],
        ]);
        $email = filled($data['test_email'] ?? null) ? $data['test_email'] : null;
        $by = $request->user();

        match ($data['action']) {
            'reset' => $sandbox->reset($event),
            'show' => $sandbox->show($event, $by, $email, (bool) ($data['simulate_failure'] ?? false)),
            'hide' => $sandbox->hide($event),
            'add-late' => $sandbox->addLateTicket($event),
            'retry' => $sandbox->retryFailed($event, $by, $email),
            'sample' => $email ? $sandbox->sendSample($event, $email, $by) : $request->validate(['test_email' => ['required', 'email']]),
        };

        return $this->back($event, 'success', $data['action'] === 'sample' ? 'Test e-mail sent to '.$email.'. No customer received anything.' : 'Test sandbox updated. No real ticket, QR or customer was touched.');
    }

    public function activate(Request $request, Event $event, QrReleaseService $service)
    {
        $data = $request->validate([
            'choice' => ['required', 'in:notify,skip'],
            'confirm_live' => ['accepted'],
        ], ['confirm_live.accepted' => 'Tick the confirmation to go Live.']);

        $service->activateLive($event, $request->user(), $data['choice']);

        return $this->back($event, 'success', $data['choice'] === 'notify' ? 'Live. Eligible tickets are being e-mailed in the background.' : 'Live. Already-eligible tickets were skipped; only tickets that unlock from now on are e-mailed.');
    }

    public function pause(Request $request, Event $event, QrReleaseService $service)
    {
        $service->switchToTest($event, $request->user());

        return $this->back($event, 'success', 'Back to Test mode. No e-mail will be sent to customers.');
    }

    public function retry(Request $request, Event $event, QrReleaseService $service)
    {
        $n = $service->retryFailed($event, $request->user());

        return $this->back($event, 'success', $n.' failed notification'.($n === 1 ? '' : 's').' queued again. Sent ones are not repeated.');
    }
}
