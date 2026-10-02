import './bootstrap';

// Livewire v3 bundles and auto-starts its own Alpine instance (the one
// @livewireScripts injects), including the $wire magic every Livewire
// component's Alpine directives need. Starting a second, separate Alpine
// instance here raced it: whichever Alpine claimed an x-data element first
// "won," so some elements (reliably, the header's search-suggestions
// @click.outside="$wire.close()" among them) ended up bound to the
// $wire-less instance and threw "$wire is not defined" on every click, on
// every page. Plain (non-Livewire) x-data elsewhere in the app still work
// fine under Livewire's single shared Alpine — that's the documented,
// supported way to mix Livewire and Alpine, not a second Alpine.start().
