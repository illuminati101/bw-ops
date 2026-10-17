@if (session('status'))
    <div class="mb-6 rounded-lg border border-ok/40 bg-ok/10 px-4 py-3 text-[#cfeedd]" role="status">{{ session('status') }}</div>
@endif
@if ($errors->any())
    <div class="mb-6 rounded-lg border border-alert/40 bg-alert/10 px-4 py-3 text-[#f6c3c4]" role="alert">Please check the highlighted fields.</div>
@endif
