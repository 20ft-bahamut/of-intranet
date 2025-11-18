<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    // GET /api/v1/products?q=&is_active=&page=&per_page=
    public function index(Request $req)
    {
        $q         = trim((string) $req->query('q', ''));
        $isActive  = $req->query('is_active', null); // '1' | '0' | null
        $page      = max(1, (int) $req->query('page', 1));
        $perPage   = (int) $req->query('per_page', 20);
        $perPage   = max(5, min($perPage, 100)); // 5~100

        $qb = Product::query();

        if ($q !== '') {
            $qb->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%");
            });
        }
        if ($isActive === '1') $qb->where('is_active', true);
        if ($isActive === '0') $qb->where('is_active', false);

        $qb->orderByDesc('id');

        $p = $qb->paginate($perPage, ['*'], 'page', $page);

        // ✅ meta를 4번째 인자로 주지 말고 data 안에 포함
        return ApiResponse::success([
            'items' => $p->items(),
            'pagination' => [
                'current_page' => $p->currentPage(),
                'per_page'     => $p->perPage(),
                'total'        => $p->total(),
                'last_page'    => $p->lastPage(),
                'from'         => $p->firstItem(),
                'to'           => $p->lastItem(),
            ],
        ]);
    }

    // GET /api/v1/products/{product}
    public function show(Product $product)
    {
        return ApiResponse::success($product);
    }

    // POST /api/v1/products
    public function store(StoreProductRequest $req)
    {
        $p = Product::create($req->validated());
        return ApiResponse::success($p, 'created', 201);
    }

    // PUT /api/v1/products/{product}
    public function update(UpdateProductRequest $req, Product $product)
    {
        $dirty = array_filter($req->validated(), function ($v) use ($product) {
            // 변경된 필드만 반영
            return true;
        });

        if (empty($dirty)) {
            return ApiResponse::fail('validation_failed', '수정할 값이 없습니다.', 422);
        }

        $product->fill($dirty)->save();

        return ApiResponse::success($product, 'updated');
    }

    // DELETE /api/v1/products/{product}
    public function destroy(Product $product)
    {
        $product->delete();
        return ApiResponse::success(null, 'deleted');
    }
}
