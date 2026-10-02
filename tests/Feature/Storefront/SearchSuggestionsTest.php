<?php

namespace Tests\Feature\Storefront;

use App\Livewire\Storefront\SearchSuggestions;
use App\Models\Product;
use App\Models\Seller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Design System §6.2's search-as-you-type dropdown, flagged deferred
 * since Run 1.4 ("needs its own debounced endpoint and keyboard-nav
 * JS"). Reuses App\Contracts\SearchProvider — the same provider the
 * results page itself queries through.
 */
class SearchSuggestionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_typing_two_or_more_characters_shows_matching_published_products(): void
    {
        $seller = Seller::factory()->active()->create();
        Product::factory()->for($seller->store)->published()->create(['title' => 'Bluetooth Speaker']);
        Product::factory()->for($seller->store)->published()->create(['title' => 'Desk Lamp']);

        Livewire::test(SearchSuggestions::class)
            ->set('query', 'Spea')
            ->assertSet('open', true)
            ->assertSeeText('Bluetooth Speaker')
            ->assertDontSeeText('Desk Lamp');
    }

    public function test_a_single_character_does_not_open_the_dropdown(): void
    {
        $seller = Seller::factory()->active()->create();
        Product::factory()->for($seller->store)->published()->create(['title' => 'Bluetooth Speaker']);

        Livewire::test(SearchSuggestions::class)
            ->set('query', 'B')
            ->assertSet('open', false);
    }

    public function test_an_unpublished_product_never_appears_as_a_suggestion(): void
    {
        $seller = Seller::factory()->active()->create();
        Product::factory()->for($seller->store)->create(['title' => 'Draft Gadget', 'status' => 'draft']);

        Livewire::test(SearchSuggestions::class)
            ->set('query', 'Draft')
            ->assertDontSeeText('Draft Gadget');
    }
}
