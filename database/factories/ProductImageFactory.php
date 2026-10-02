<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductImage>
 */
class ProductImageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'disk_path' => 'products/1/'.fake()->uuid().'.jpg',
            'original_filename' => 'photo.jpg',
            'mime_type' => 'image/jpeg',
            'width' => 1200,
            'height' => 1200,
            'size_bytes' => 204800,
            'is_primary' => false,
            'sort_order' => 0,
            'status' => 'processed',
        ];
    }
}
