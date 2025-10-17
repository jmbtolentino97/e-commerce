<?php

namespace App\Http\Controllers;

use App\Models\InventoryMovement;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\Enums\FilterOperator;
use Spatie\QueryBuilder\QueryBuilder;

class InventoryReportController extends Controller
{
    /**
     * Paginated stock-on-hand across products.
     * Uses the DB view: product_current_stock (product_id, stock_on_hand).
     */
    public function stock(Request $request)
    {
        // Join products for sku/name filters & sorting
        $base = DB::table('product_current_stock as s')
            ->join('products as p', 'p.id', '=', 's.product_id')
            ->select([
                'p.id as product_id',
                'p.sku',
                'p.name',
                'p.is_active',
                's.stock_on_hand',
                'p.created_at',
            ]);

        // Minimal Spatie-like filtering behavior (without wrapping a model)
        // Accepts: filter[sku], filter[name], filter[active]
        if ($v = $request->input('filter.sku')) {
            $base->where('p.sku', 'like', '%' . $v . '%');
        }
        if ($v = $request->input('filter.name')) {
            $base->where('p.name', 'like', '%' . $v . '%');
        }
        if (($v = $request->input('filter.active')) !== null) {
            $base->where('p.is_active', (int) (bool) $v);
        }

        // Sorting (default: name asc). Accepts: sort=name, -name, stock_on_hand, -stock_on_hand, created_at, -created_at
        $sort = $request->input('sort', 'name');
        $dir = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $col = ltrim($sort, '-');
        $allowed = ['name' => 'p.name', 'stock_on_hand' => 's.stock_on_hand', 'created_at' => 'p.created_at'];
        $orderBy = $allowed[$col] ?? 'p.name';
        $base->orderBy($orderBy, $dir);

        $perPage = (int) $request->input('page_size', 15);
        $page = max(1, (int) $request->input('page', 1));

        $total = (clone $base)->count();
        $rows = $base->forPage($page, $perPage)->get();

        return response()->json([
            'data' => $rows,
            'meta' => [
                'current_page' => $page,
                'last_page' => (int) ceil($total / max(1, $perPage)),
                'per_page' => $perPage,
                'total' => $total,
            ],
        ]);
    }

    /**
     * Stock-on-hand for a single product.
     */
    public function stockOf(Product $product)
    {
        $row = DB::table('product_current_stock')
            ->where('product_id', $product->id)
            ->first();

        return response()->json([
            'data' => [
                'product_id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'is_active' => (bool) $product->is_active,
                'stock_on_hand' => (int) ($row->stock_on_hand ?? 0),
            ],
        ]);
    }

    /**
     * Movement history (paginated), filterable by product, type, and date range.
     * Query params:
     *   filter[product_id]=123
     *   filter[type]=sale (purchase|sale|return|adjustment|reservation|release|transfer_in|transfer_out)
     *   filter[date_between]=YYYY-MM-DD,YYYY-MM-DD
     * Sort: sort=created_at or sort=-created_at (default -created_at)
     * Include product: include=product
     */
    public function movements(Request $request)
    {
        $query = QueryBuilder::for(
            InventoryMovement::query()
        )
        ->allowedFilters([
            AllowedFilter::exact('product_id'),
            AllowedFilter::exact('type'),
            AllowedFilter::operator('min_created_at', FilterOperator::GREATER_THAN_OR_EQUAL, 'and', 'created_at'),
            AllowedFilter::operator('max_created_at', FilterOperator::LESS_THAN_OR_EQUAL, 'and', 'created_at'),
        ])
        ->allowedIncludes(['product'])
        ->allowedSorts(['created_at'])
        ->defaultSort('-created_at');

        $perPage = (int) $request->input('page_size', 15);
        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
