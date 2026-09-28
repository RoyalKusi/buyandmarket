<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\View\View;

/**
 * Design System §6.3: hero + sub-category chips + the same filter-rail
 * grid pattern as search, since "a category page is architecturally
 * search pre-scoped to one category."
 */
class CategoryController extends Controller
{
    public function show(Category $category): View
    {
        return view('storefront.category', [
            'category' => $category,
            'children' => $category->children()->orderBy('name')->get(),
            'breadcrumbs' => $this->breadcrumbTrail($category),
        ]);
    }

    /**
     * @return array<int, array{label: string, href: string}>
     */
    private function breadcrumbTrail(Category $category): array
    {
        $trail = [];
        $node = $category;

        while ($node !== null) {
            array_unshift($trail, ['label' => $node->name, 'href' => route('storefront.categories.show', $node)]);
            $node = $node->parent;
        }

        array_unshift($trail, ['label' => 'Home', 'href' => route('storefront.home')]);

        return $trail;
    }
}
