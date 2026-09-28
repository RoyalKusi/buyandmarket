<x-layouts.dashboard title="Audit log" active="admin.audit">
    {{-- TDD §8.9: immutable, filterable table; monospace actor/action
         columns. Filtering is deferred — see CHANGELOG.md. --}}
    <div class="bg-slate-0 border border-slate-100 rounded-md overflow-hidden">
        <table class="w-full text-body-sm">
            <thead class="bg-slate-50 text-slate-600">
                <tr>
                    <th class="text-left px-4 py-3 font-medium">When</th>
                    <th class="text-left px-4 py-3 font-medium">Actor</th>
                    <th class="text-left px-4 py-3 font-medium">Action</th>
                    <th class="text-left px-4 py-3 font-medium">Subject</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($entries as $entry)
                    <tr class="border-t border-slate-100">
                        <td class="px-4 py-3 font-mono">{{ $entry->created_at->format('d M Y H:i') }}</td>
                        <td class="px-4 py-3 font-mono">{{ $entry->actor?->name ?? $entry->actor_type }}</td>
                        <td class="px-4 py-3 font-mono">{{ $entry->action }}</td>
                        <td class="px-4 py-3 font-mono text-slate-500">{{ class_basename($entry->subject_type) }}#{{ $entry->subject_id }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-6 text-center text-body-md text-slate-500">No audit entries yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $entries->links() }}</div>
</x-layouts.dashboard>
