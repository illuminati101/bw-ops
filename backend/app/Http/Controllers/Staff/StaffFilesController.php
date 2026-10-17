<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\ClientProfile;
use App\Models\Invoice;
use App\Models\JobApplication;
use Illuminate\Support\Facades\Storage;

class StaffFilesController extends Controller
{
    public function client(ClientProfile $profile, string $kind)
    {
        abort_unless(auth()->user()->can('clients.verify'), 403);
        $path = $kind === 'selfie' ? $profile->selfie_path : $profile->id_document_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);
        activity()->causedBy(auth()->user())->performedOn($profile)->event('viewed')->log("Viewed client {$kind}");

        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'private, no-store']);
    }

    public function application(JobApplication $application, int $index)
    {
        abort_unless(auth()->user()->can('enquiries.view'), 403);
        $path = $application->files[$index] ?? null;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path);
    }

    public function invoice(Invoice $invoice)
    {
        abort_unless(auth()->user()->can('finance.view'), 403);

        return view('documents.invoice', ['invoice' => $invoice->load(['bookings.service', 'company', 'user'])]);
    }
}
