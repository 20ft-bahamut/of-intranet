<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    // 목록(검색/페이지네이션)
    public function index(Request $request)
    {
        $query = Product::query()
            ->when($request->filled('q'), function ($q) use ($request) {
                $kw = $request->string('q');
                $q->where(function ($w) use ($kw) {
                    $w->where('name','like',"%{$kw}%")
                        ->orWhere('code','like',"%{$kw}%")
                        ->orWhere('spec','like',"%{$kw}%");
                });
            })
            ->when($request->filled('active'), function ($q) use ($request) {
                $q->where('is_active', (bool) $request->boolean('active'));
            })
            ->orderByDesc('id');

        $perPage = (int) $request->integer('per_page', 20);
        return ProductResource::collection($query->paginate($perPage));
    }

    // 단건 조회
    public function show(Product $product)
    {
        return new ProductResource($product);
    }

    // 생성
    public function store(StoreProductRequest $request)
    {
        $data = $request->validated();
        $data['is_active'] = $data['is_active'] ?? true;

        $product = Product::create($data);
        return (new ProductResource($product))
            ->response()
            ->setStatusCode(201);
    }

    // 수정
    public function update(UpdateProductRequest $request, Product $product)
    {
        $product->fill($request->validated());
        $product->save();
        return new ProductResource($product);
    }

    // 삭제
    public function destroy(Product $product)
    {
        $product->delete();
        return response()->json(['message' => 'deleted'], 200);
    }
}
