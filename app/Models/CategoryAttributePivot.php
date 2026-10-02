<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * The category_attributes pivot (Category::attributes() / Attribute::
 * categories()) carries a real column, `required`, beyond the bare
 * foreign keys — a dedicated Pivot class is how Eloquent (and static
 * analysis) knows that column exists, instead of the generic
 * Illuminate\Database\Eloquent\Relations\Pivot's magic-only properties.
 *
 * @property bool $required
 */
class CategoryAttributePivot extends Pivot
{
    protected function casts(): array
    {
        return [
            'required' => 'boolean',
        ];
    }
}
