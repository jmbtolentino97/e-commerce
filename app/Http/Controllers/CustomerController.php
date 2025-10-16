<?php

namespace App\Http\Controllers;

use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\Enums\FilterOperator;
use Spatie\QueryBuilder\QueryBuilder;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Customer::class);

        $customers = QueryBuilder::for(Customer::query())
            ->allowedFilters([
                AllowedFilter::partial('email'),
                AllowedFilter::partial('first_name'),
                AllowedFilter::partial('last_name'),
                AllowedFilter::operator('min_created_at', FilterOperator::GREATER_THAN_OR_EQUAL, 'and', 'created_at'),
                AllowedFilter::operator('max_created_at', FilterOperator::LESS_THAN_OR_EQUAL, 'and', 'created_at'),
            ])
            ->allowedSorts(['created_at', 'last_name'])
            ->allowedIncludes(['orders'])
            ->paginate($request->query('page_size', 15));

        return response()->json([
            'data' => $customers->items(),
            'meta' => [
                'current_page' => $customers->currentPage(),
                'last_page' => $customers->lastPage(),
                'per_page' => $customers->perPage(),
                'total' => $customers->total(),
            ],
        ]);
    }

    public function show(Customer $customer)
    {
        Gate::authorize('view', $customer);

        $customer->load(request('include') ? explode(',', request('include')) : []);

        return response()->json([
            'data' => $customer,
        ]);
    }

    public function store(StoreCustomerRequest $request)
    {
        Gate::authorize('create', Customer::class);

        $customer = Customer::create([
            'first_name' => $request->string('first_name'),
            'last_name' => $request->string('last_name'),
            'email' => $request->string('email'),
            'phone' => $request->input('phone'),
            'password' => Hash::make($request->string('password')),
        ]);

        return response()->json(['data' => $customer], 201);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer)
    {
        Gate::authorize('update', $customer);

        $payload = $request->validated();
        if (isset($payload['password'])) {
            $payload['password'] = Hash::make($payload['password']);
        }

        $customer->update($payload);

        return response()->json(['data' => $customer->fresh()]);
    }

    public function destroy(Customer $customer)
    {
        Gate::authorize('delete', $customer);

        $customer->delete();

        return response()->json(['message' => 'Deleted.']);
    }
}
