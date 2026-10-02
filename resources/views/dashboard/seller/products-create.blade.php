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
        x-data="productForm({{ $categoryAttributesJson }}, '{{ route('seller.dashboard.products.suggest-categorization') }}', '{{ csrf_token() }}')" class="max-w-3xl space-y-6">
        @csrf

        {{-- TDD §5.6, scoped per docs/adr/0007: a text-seeded category/
             attribute suggestion, matched to the real catalogue and
             applied to the fields below — nothing here is submitted
             directly, it only fills in the form for the seller to
             review. --}}
        <div class="bg-blue-50 border border-dashed border-blue-200 rounded-sm p-4">
            <p class="text-caption uppercase tracking-wide text-blue-600 mb-2">AI: suggest a category &amp; attributes</p>
            <textarea x-model="bullets" rows="2" placeholder="A few notes about the product, e.g. waterproof speaker, 10h battery, black"
                class="w-full rounded-sm border border-slate-200 px-3 py-2 text-body-md mb-2"></textarea>
            <button type="button" @click="suggestCategorization()" :disabled="suggesting" class="rounded-sm border border-blue-600 text-blue-600 px-4 h-10 text-button font-semibold hover:bg-blue-100 disabled:opacity-50">
                <span x-text="suggesting ? 'Thinking…' : 'Suggest from my notes'"></span>
            </button>
            <p class="text-body-sm text-red-600 mt-2" x-show="suggestionError" x-text="suggestionError"></p>
            <p class="text-body-sm text-slate-500 mt-2" x-show="suggestionApplied" x-cloak>Applied below — review before creating the product.</p>
        </div>

        <div class="bg-slate-0 border border-slate-100 rounded-md p-6 space-y-4">
            <h2 class="text-heading-sm font-display text-slate-900">Product details</h2>

            <div>
                <label for="title" class="block text-body-md text-slate-700 mb-1">Title</label>
                <input id="title" type="text" name="title" x-model="title" required
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
                    <button type="button" @click="showBrandForm = ! showBrandForm" class="text-body-sm text-blue-600 hover:underline mt-1">Can't find your brand? Suggest one</button>
                </div>
            </div>

            {{-- TDD §3.2 module 11 "suggest a new brand" — deferred since
                 Run 1.7 (fully functional via the API, web presentation
                 only missing). A separate POST so the brand suggestion
                 (pending admin approval, App\Services\BrandService)
                 never gets tangled with the product form's own
                 validation. --}}
            <div x-show="showBrandForm" x-cloak class="border border-slate-100 rounded-sm p-4">
                <form method="POST" action="{{ route('seller.dashboard.products.suggest-brand') }}" class="flex flex-wrap items-end gap-2">
                    @csrf
                    <div>
                        <label class="block text-body-sm text-slate-700 mb-1">Brand name</label>
                        <input type="text" name="name" required class="h-10 rounded-sm border border-slate-200 px-3 text-body-md">
                    </div>
                    <div>
                        <label class="block text-body-sm text-slate-700 mb-1">Slug</label>
                        <input type="text" name="slug" required class="h-10 rounded-sm border border-slate-200 px-3 text-body-md">
                    </div>
                    <button type="submit" class="h-10 rounded-sm border border-blue-600 text-blue-600 px-4 text-button font-semibold hover:bg-blue-50">Suggest brand</button>
                </form>
                <p class="text-body-sm text-slate-500 mt-2">An admin reviews every suggested brand before it appears in this list.</p>
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
                                                    <input type="checkbox" :name="'variants[' + index + '][attribute_value_ids][]'" :value="value.id" :checked="suggestedValueIds.includes(value.id)">
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
        function productForm(categoryAttributesById, suggestUrl, csrfToken) {
            return {
                title: '{{ old('title') }}',
                bullets: '',
                categoryId: '{{ old('category_id') }}',
                variants: [{ key: 0, sku: '', stock_quantity: 0, price_override: '' }],
                nextKey: 1,
                showBrandForm: false,
                suggesting: false,
                suggestionError: null,
                suggestionApplied: false,
                suggestedValueIds: [],
                get categoryAttributes() {
                    return categoryAttributesById[this.categoryId] ?? [];
                },
                addVariant() {
                    this.variants.push({ key: this.nextKey++, sku: '', stock_quantity: 0, price_override: '' });
                },
                removeVariant(index) {
                    this.variants.splice(index, 1);
                },
                async suggestCategorization() {
                    if (! this.title || ! this.bullets) {
                        this.suggestionError = 'Fill in the title and notes above first.';
                        return;
                    }

                    this.suggesting = true;
                    this.suggestionError = null;
                    this.suggestionApplied = false;

                    try {
                        const response = await fetch(suggestUrl, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' },
                            body: JSON.stringify({ title: this.title, bullets: this.bullets }),
                        });

                        if (! response.ok) {
                            throw new Error('The suggestion request failed.');
                        }

                        const data = await response.json();

                        if (data.category) {
                            this.categoryId = String(data.category.id);
                        }
                        this.suggestedValueIds = data.attributes.map((a) => a.value_id);
                        this.suggestionApplied = true;
                    } catch (e) {
                        this.suggestionError = 'Could not get a suggestion — fill in the fields manually.';
                    } finally {
                        this.suggesting = false;
                    }
                },
            };
        }
    </script>
</x-layouts.dashboard>
