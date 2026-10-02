<x-layouts.dashboard title="Audit log" active="admin.audit">
    {{-- TDD §8.9: immutable, filterable table; monospace actor/action
         columns. --}}
    <form method="GET" class="mb-4 bg-slate-0 border border-slate-100 rounded-md p-4 grid grid-cols-2 md:grid-cols-5 gap-3 items-end">
        <div>
            <label class="block text-caption text-slate-500 mb-1" for="action">Action</label>
            <select id="action" name="action" class="w-full rounded-sm border-slate-200 text-body-sm">
                <option value="">All</option>
                @foreach ($actions as $action)
                    <option value="{{ $action }}" @selected(($filters['action'] ?? null) === $action)>{{ $action }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-caption text-slate-500 mb-1" for="subject_type">Subject</label>
            <select id="subject_type" name="subject_type" class="w-full rounded-sm border-slate-200 text-body-sm">
                <option value="">All</option>
                @foreach ($subjectTypes as $subjectType)
                    <option value="{{ $subjectType }}" @selected(($filters['subject_type'] ?? null) === $subjectType)>{{ class_basename($subjectType) }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-caption text-slate-500 mb-1" for="actor">Actor</label>
            <input type="text" id="actor" name="actor" value="{{ $filters['actor'] ?? '' }}" placeholder="Name or email" class="w-full rounded-sm border-slate-200 text-body-sm">
        </div>

        <div>
            <label class="block text-caption text-slate-500 mb-1" for="from">From</label>
            <input type="date" id="from" name="from" value="{{ $filters['from'] ?? '' }}" class="w-full rounded-sm border-slate-200 text-body-sm">
        </div>

        <div>
            <label class="block text-caption text-slate-500 mb-1" for="to">To</label>
            <input type="date" id="to" name="to" value="{{ $filters['to'] ?? '' }}" class="w-full rounded-sm border-slate-200 text-body-sm">
        </div>

        <div class="col-span-2 md:col-span-5 flex gap-3">
            <button type="submit" class="rounded-sm bg-blue-600 px-4 py-2 text-body-sm text-white hover:bg-blue-700">Filter</button>
            <a href="{{ route('admin.dashboard.audit-log') }}" class="rounded-sm px-4 py-2 text-body-sm text-slate-600 hover:bg-slate-50">Clear</a>
        </div>
    </form>

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
                    <tr><td colspan="4" class="px-4 py-6 text-center text-body-md text-slate-500">No audit entries match these filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $entries->links() }}</div>
</x-layouts.dashboard>
