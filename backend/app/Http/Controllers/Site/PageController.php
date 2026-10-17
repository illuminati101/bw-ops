<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Enquiry;
use App\Models\JobApplication;
use App\Models\Page;
use App\Models\Region;
use App\Models\Service;
use App\Services\Messaging\Notifier;
use App\Support\LegalRenderer;
use App\Support\PhoneNumber;
use Illuminate\Http\Request;

class PageController extends Controller
{
    private function find(string $slug): Page
    {
        return Page::query()->published()->where('slug', $slug)->firstOrFail();
    }

    private function staffPreview(Request $request): bool
    {
        return $request->boolean('preview') && $request->user()?->isStaff();
    }

    public function home()
    {
        return view('site.page', ['page' => $this->find('home')]);
    }

    public function services()
    {
        return view('site.page', ['page' => $this->find('services')]);
    }

    public function page(string $slug)
    {
        return view('site.page', ['page' => $this->find($slug)]);
    }

    public function service(Request $request, string $slug)
    {
        $preview = $this->staffPreview($request);
        $service = Service::query()->when(! $preview, fn ($q) => $q->published())->where('slug', $slug)->firstOrFail();
        if ($preview) {
            $service = $service->withDraftApplied();
        }

        return view('site.service', ['service' => $service, 'card' => $service->rateCardFor(Region::default(), ! $preview), 'preview' => $preview]);
    }

    public function legal(string $slug)
    {
        $page = $this->find('legal/'.$slug);

        return view('site.legal', ['page' => $page, 'html' => LegalRenderer::render((string) $page->body)]);
    }

    public function preview(Page $page)
    {
        $draft = $page->withDraftApplied();
        if ($draft->kind === 'legal') {
            return view('site.legal', ['page' => $draft, 'html' => LegalRenderer::render((string) $draft->body)]);
        }

        return view('site.page', ['page' => $draft, 'preview' => true]);
    }

    public function enquiry(Request $request, string $type, Notifier $notifier)
    {
        if ($request->filled('website')) {
            return back()->with('status', 'Thank you. We will be in touch shortly.'); // honeypot
        }
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:150',
            'phone' => ($type === 'corporate' ? 'required' : 'nullable').'|string|max:24',
            'company' => ($type === 'corporate' ? 'required' : 'nullable').'|string|max:150',
            'message' => 'required|string|max:3000',
        ]);
        Enquiry::create($data + ['type' => $type]);
        $notifier->staff($type === 'corporate' ? 'New corporate enquiry' : 'New enquiry', "{$data['name']}: ".mb_strimwidth($data['message'], 0, 80, '…'), 'enquiries', 'enquiries.view');

        return back()->with('status', 'Thank you. A member of our team will reply within one business day.')->withFragment('form');
    }

    public function apply(Request $request, Notifier $notifier)
    {
        if ($request->filled('website')) {
            return back()->with('status', 'Thank you for applying.');
        }
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:150',
            'phone' => 'required|string|max:24',
            'position' => 'required|in:driver,protection,both',
            'years_experience' => 'nullable|integer|min:0|max:50',
            'psira_number' => 'nullable|string|max:32',
            'psira_grade' => 'nullable|string|max:2',
            'message' => 'nullable|string|max:3000',
            'files' => 'nullable|array|max:5',
            'files.*' => 'file|max:8192|mimes:pdf,jpg,jpeg,png,doc,docx',
        ]);
        $paths = [];
        foreach ($request->file('files', []) as $file) {
            $paths[] = $file->store('applications', 'local');
        }
        JobApplication::create(collect($data)->except('files')->all() + [
            'phone' => PhoneNumber::normalize($data['phone']) ?? $data['phone'],
            'firearm_competent' => $request->boolean('firearm_competent'),
            'has_prdp' => $request->boolean('has_prdp'),
            'files' => $paths,
        ]);
        $notifier->staff('New job application', "{$data['name']} applied ({$data['position']}).", 'enquiries', 'enquiries.view');

        return back()->with('status', 'Thank you for applying. We review every application and will contact you if there is a fit.')->withFragment('form');
    }
}
