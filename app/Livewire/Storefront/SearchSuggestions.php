<?php

namespace App\Livewire\Storefront;

use App\Contracts\SearchProvider;
use Livewire\Component;

/**
 * Design System §6.2's search-as-you-type suggestion dropdown — flagged
 * deferred since Run 1.4 ("needs its own debounced endpoint and
 * keyboard-nav JS"). Reuses the existing App\Contracts\SearchProvider
 * (Run 1.4), the same provider-agnostic boundary the results page
 * itself queries through — no separate suggestion index to keep in
 * sync.
 */
class SearchSuggestions extends Component
{
    public string $query = '';

    public bool $open = false;

    public function updatedQuery(): void
    {
        $this->open = strlen(trim($this->query)) >= 2;
    }

    public function close(): void
    {
        $this->open = false;
    }

    public function render(SearchProvider $searchProvider)
    {
        $suggestions = $this->open
            ? $searchProvider->search($this->query, perPage: 6)->items()
            : [];

        return view('livewire.storefront.search-suggestions', ['suggestions' => $suggestions]);
    }
}
