<?php

namespace App\Http\Controllers;

use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\Enums\FilterOperator;
use Spatie\QueryBuilder\QueryBuilder;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Product::class);

        $products = QueryBuilder::for(Product::query())
            ->allowedFilters([
                AllowedFilter::partial('sku'),
                AllowedFilter::partial('name'),
                AllowedFilter::exact('is_active'),
                AllowedFilter::operator('min_price', FilterOperator::GREATER_THAN_OR_EQUAL, 'and', 'price'),
                AllowedFilter::operator('max_price', FilterOperator::LESS_THAN_OR_EQUAL, 'and', 'price')
            ])
            ->allowedSorts(['name', 'price', 'created_at'])
            ->allowedIncludes(['inventoryMovements'])
            ->paginate($request->query('page_size', 15));

        return response()->json([
            'data' => $products->items(),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    public function show(Product $product)
    {
        Gate::authorize('view', $product);

        $product->load(request('include') ? explode(',', request('include')) : []);

        return response()->json([
            'data' => $product,
        ]);
    }

    public function store(StoreProductRequest $request)
    {
        Gate::authorize('create', Product::class);

        $product = Product::create([
            'sku' => $request->string('sku'),
            'name' => $request->string('name'),
            'slug' => $request->input('slug'),
            'description' => $request->input('description'),
            'price' => $request->input('price'),
            'cost' => $request->input('cost'),
            'is_active' => $request->boolean('is_active', true),
            'track_inventory' => $request->boolean('track_inventory', true),
        ]);

        return response()->json(['data' => $product], 201);
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        Gate::authorize('update', $product);

        $product->update($request->validated());

        return response()->json(['data' => $product->fresh()]);
    }

    public function destroy(Product $product)
    {
        Gate::authorize('delete', $product);

        $product->delete();

        return response()->json(['message' => 'Deleted.']);
    }
}
