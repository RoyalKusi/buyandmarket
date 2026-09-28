<?php

namespace Tests\Feature\Catalogue;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_build_a_full_category_tree_up_to_the_max_depth(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        $level0 = $this->actingAs($admin)
            ->postJson('/api/v1/admin/categories', ['name' => 'Electronics', 'slug' => 'electronics'])
            ->assertCreated()
            ->json('data');

        $level1 = $this->actingAs($admin)
            ->postJson('/api/v1/admin/categories', [
                'name' => 'Phones', 'slug' => 'phones', 'parent_id' => $level0['id'],
            ])
            ->assertCreated()
            ->json('data');

        $level2 = $this->actingAs($admin)
            ->postJson('/api/v1/admin/categories', [
                'name' => 'Smartphones', 'slug' => 'smartphones', 'parent_id' => $level1['id'],
            ])
            ->assertCreated()
            ->json('data');

        $level3 = $this->actingAs($admin)
            ->postJson('/api/v1/admin/categories', [
                'name' => 'Android', 'slug' => 'android', 'parent_id' => $level2['id'],
            ])
            ->assertCreated()
            ->json('data');

        $this->assertSame(0, $level0['depth']);
        $this->assertSame(1, $level1['depth']);
        $this->assertSame(2, $level2['depth']);
        $this->assertSame(3, $level3['depth']);

        // A 5th level (depth 4) is rejected (TDD §6.2: CHECK(depth <= 3)).
        $this->actingAs($admin)
            ->postJson('/api/v1/admin/categories', [
                'name' => 'Foldables', 'slug' => 'foldables', 'parent_id' => $level3['id'],
            ])
            ->assertUnprocessable();
    }

    public function test_a_non_admin_cannot_create_a_category(): void
    {
        $buyer = User::factory()->withRole('buyer')->create();

        $this->actingAs($buyer)
            ->postJson('/api/v1/admin/categories', ['name' => 'Electronics', 'slug' => 'electronics'])
            ->assertForbidden();

        $this->assertDatabaseCount('categories', 0);
    }

    public function test_categories_are_publicly_listable(): void
    {
        Category::factory()->create(['name' => 'Electronics', 'slug' => 'electronics']);

        $this->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
