<x-layouts.dashboard title="Create product" active="seller.products">
    {{-- TDD §3.2 modules 7/10: category picker, dynamic variant rows.
         Deferred since Run 1.7 — product creation was API-only
         (POST /api/v1/seller/products). This posts to the same
         App\Services\ProductService::create() the API uses, via
         App\Http\Controllers\Dashboard\SellerController::storeProduct().
         Every leaf category's attributes ship inline as JSON
         ($categoryAttributesJson) so switching the category client-side
         swaps each variant row's attribute checkboxes with no extra
         request. --}}
    <form method="POST" action="{{ route('seller.dashboard.products.store') }}"
        x-data="productForm({{ $categoryAttributesJson }})" class="max-w-3xl space-y-6">
        @csrf

        <div class="bg-slate-0 border border-slate-100 rounded-md p-6 space-y-4">
            <h2 class="text-heading-sm font-display text-slate-900">Product details</h2>

            <div>
                <label for="title" class="block text-body-md text-slate-700 mb-1">Title</label>
                <input id="title" type="text" name="title" value="{{ old('title') }}" required
                    class="w-full h-10 rounded-sm border border-slate-200 px-3 text-body-md focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-600/20">
            </div>

            <div>
                <label for="description" class="block text-body-md text-slate-700 mb-1">Description</label>
                <textarea id="description" name="description" rows="3"
                    class="w-full rounded-sm border border-slate-200 px-3 py-2 text-body-md focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-600/20">{{ old('description') }}</textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="category_id" class="block text-body-md text-slate-700 mb-1">Category</label>
                    <select id="category_id" name="category_id" x-model="categoryId" required
                        class="w-full h-10 rounded-sm border border-slate-200 px-3 text-body-md">
                        <option value="">Choose a category&hellip;</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected(old('category_id') == $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                    <p class="text-body-sm text-slate-500 mt-1">Only leaf categories can hold products.</p>
                </div>

                <div>
                    <label for="brand_id" class="block text-body-md text-slate-700 mb-1">Brand (optional)</label>
                    <select id="brand_id" name="brand_id" class="w-full h-10 rounded-sm border border-slate-200 px-3 text-body-md">
                        <option value="">No brand</option>
                        @foreach ($brands as $brand)
                            <option value="{{ $brand->id }}" @selected(old('brand_id') == $brand->id)>{{ $brand->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label for="base_price" class="block text-body-md text-slate-700 mb-1">Base price (USD)</label>
                <input id="base_price" type="text" name="base_price" value="{{ old('base_price') }}" inputmode="decimal" required
                    class="w-full h-10 rounded-sm border border-slate-200 px-3 text-body-md">
            </div>
        </div>

        <div class="bg-slate-0 border border-slate-100 rounded-md p-6 space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-heading-sm font-display text-slate-900">Variants</h2>
                <button type="button" @click="addVariant()" class="text-body-sm text-blue-600 hover:underline">+ Add another variant</button>
            </div>

            <template x-for="(variant, index) in variants" :key="variant.key">
                <div class="border border-slate-100 rounded-sm p-4 space-y-3">
                    <div class="flex items-center justify-between">
                        <p class="text-body-sm text-slate-500" x-text="'Variant ' + (index + 1)"></p>
                        <button type="button" x-show="variants.length > 1" @click="removeVariant(index)" class="text-body-sm text-red-600 hover:underline">Remove</button>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-body-sm text-slate-700 mb-1">SKU</label>
                            <input type="text" :name="'variants[' + index + '][sku]'" x-model="variant.sku" required
                                class="w-full h-10 rounded-sm border border-slate-200 px-3 text-body-md">
                        </div>
                        <div>
                            <label class="block text-body-sm text-slate-700 mb-1">Stock quantity</label>
                            <input type="number" min="0" :name="'variants[' + index + '][stock_quantity]'" x-model="variant.stock_quantity" required
                                class="w-full h-10 rounded-sm border border-slate-200 px-3 text-body-md">
                        </div>
                    </div>

                    <div>
                        <label class="block text-body-sm text-slate-700 mb-1">Price override (optional &mdash; defaults to base price)</label>
                        <input type="text" :name="'variants[' + index + '][price_override]'" x-model="variant.price_override" inputmode="decimal"
                            class="w-full h-10 rounded-sm border border-slate-200 px-3 text-body-md">
                    </div>

                    <template x-if="categoryAttributes.length > 0">
                        <div>
                            <p class="text-body-sm text-slate-700 mb-2">Attributes</p>
                            <div class="flex flex-wrap gap-4">
                                <template x-for="attribute in categoryAttributes" :key="attribute.id">
                                    <div>
                                        <p class="text-caption uppercase tracking-wide text-slate-400 mb-1" x-text="attribute.name"></p>
                                        <div class="flex flex-wrap gap-2">
                                            <template x-for="value in attribute.values" :key="value.id">
                                                <label class="inline-flex items-center gap-1 text-body-sm text-slate-700">
                                                    <input type="checkbox" :name="'variants[' + index + '][attribute_value_ids][]'" :value="value.id">
                                                    <span x-text="value.value"></span>
                                                </label>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </template>
        </div>

        <button type="submit" class="h-10 rounded-sm bg-blue-600 text-slate-0 px-4 text-button font-semibold hover:bg-blue-500">Create product</button>
    </form>

    <script>
        function productForm(categoryAttributesById) {
            return {
                categoryId: '{{ old('category_id') }}',
                variants: [{ key: 0, sku: '', stock_quantity: 0, price_override: '' }],
                nextKey: 1,
                get categoryAttributes() {
                    return categoryAttributesById[this.categoryId] ?? [];
                },
                addVariant() {
                    this.variants.push({ key: this.nextKey++, sku: '', stock_quantity: 0, price_override: '' });
                },
                removeVariant(index) {
                    this.variants.splice(index, 1);
                },
            };
        }
    </script>
</x-layouts.dashboard>
