<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Validation\ValidationException;

/**
 * TDD §6.2: categories are self-referencing with a max depth of 3,
 * CHECK(depth <= 3) enforced here rather than at the schema level.
 */
class CategoryService
{
    public function create(string $name, string $slug, ?Category $parent = null): Category
    {
        $depth = $parent ? $parent->depth + 1 : 0;

        if ($depth > Category::MAX_DEPTH) {
            throw ValidationException::withMessages([
                'parent_id' => 'Categories may not be nested deeper than '.Category::MAX_DEPTH.' levels.',
            ]);
        }

        return Category::create([
            'parent_id' => $parent?->id,
            'name' => $name,
            'slug' => $slug,
            'depth' => $depth,
        ]);
    }
}
