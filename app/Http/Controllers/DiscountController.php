<?php

namespace App\Http\Controllers;

use App\Http\Requests\Discount\StoreDiscountRequest;
use App\Http\Requests\Discount\UpdateDiscountRequest;
use App\Models\Discount;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\Enums\FilterOperator;
use Spatie\QueryBuilder\QueryBuilder;

class DiscountController extends Controller
{
    public function index(Request $request)
    {
        $items = QueryBuilder::for(Discount::query())
            ->allowedFilters([
                AllowedFilter::partial('code'),
                AllowedFilter::partial('name'),
                AllowedFilter::exact('active'),
                AllowedFilter::scope('inDateRange'),
            ])
            ->allowedSorts(['created_at', 'starts_at', 'ends_at'])
            ->paginate($request->input('page_size', 15));

        return response()->json([
            'data' => $items->items(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    public function show(Discount $discount)
    {
        return response()->json(['data' => $discount]);
    }

    public function store(StoreDiscountRequest $request)
    {
        $discount = Discount::create($request->validated());

        return response()->json(['data' => $discount], 201);
    }

    public function update(UpdateDiscountRequest $request, Discount $discount)
    {
        $discount->update($request->validated());

        return response()->json(['data' => $discount->fresh()]);
    }

    public function destroy(Discount $discount)
    {
        $discount->delete();

        return response()->json(['message' => 'Deleted.']);
    }
}
