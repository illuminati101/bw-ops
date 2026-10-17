<div class="space-y-3">
    @forelse ($record->revisions()->with('user')->limit(25)->get() as $rev)
        <div class="flex items-start justify-between gap-4 rounded-lg border border-white/10 p-3">
            <div>
                <div class="text-sm font-semibold">{{ $rev->created_at->format('D j M Y, H:i') }} @if($loop->first)<span class="bw-pill" style="background:rgba(201,168,106,.15);color:#C9A86A">Live</span>@endif</div>
                <div class="text-xs text-gray-400">{{ $rev->user?->name ?? 'System' }} @if($rev->note) · {{ $rev->note }} @endif</div>
            </div>
            @unless ($loop->first)
                <x-filament::button size="xs" color="gray" wire:click="restoreRevision({{ $rev->id }})">Restore as draft</x-filament::button>
            @endunless
        </div>
    @empty
        <p class="text-sm text-gray-400">No published versions yet.</p>
    @endforelse
</div>
