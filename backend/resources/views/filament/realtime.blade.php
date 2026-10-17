{{-- Real-time refresh for staff: control-room events refresh the Livewire components on screen (admin.js connects). --}}
@if (auth()->user()?->isStaff() && ($rt = \App\Support\Realtime::publicConfig()))
<script>window.__realtime = {{ Js::from($rt) }};</script>
@endif
