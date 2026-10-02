<x-layouts.dashboard title="BM Assistant" active="assistant">
    <livewire:ai-assistant :product="request()->integer('product') ?: null" />
</x-layouts.dashboard>
