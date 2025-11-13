<?php

namespace App\Http\Controllers\Api\V1\Product;

use App\Http\Controllers\Controller;
use App\Models\Price;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{

    protected $merchant;

    /**
     * Initialize the merchant app
     */
    public function __construct()
    {
        $this->merchant = merchantApp(request());
    }

    /**
     * Display all products.
     */
    public function index()
    {
        $products = Product::with(['price', 'merchantApp'])->get();
        return response()->json($products);
    }

    /**
     * Store a new product.
     */
    public function store(Request $request)
    {
        $validated = Validator::make($request->all(),[
            'name' => 'required|string|max:255',
            'type' => 'required|in:physical,digital,service',
            'short_description' => 'nullable|string',
            'sku' => 'required|string|unique:products,sku',
            'image' => 'required|string',
        ]);

        // check validation
        if ($validated->fails()) {
            return response()->json([
                'code'    => "INVALID_DATA",
                'message' => 'Invalid product data, Please have a look our API docs.',
                'errors'  => $validated->errors(),
            ], 422);
        }

        // price validation
        $price = Price::where('merchant_app_id', $this->merchant->id)->where('price_id',$request->price_id)->first();

        if(!$price){
            return response()->json([
                'code' => 'INVALID_PRICE_ID',
                'message' => 'Invalid price ID'
            ],422);
        }

        $form = $validated->validated();
        $form['price_id'] = $price->id;
        $form['product_id'] = uniqueToken(Product::class,'product_id','product_');
        $form['merchant_app_id'] = $this->merchant->id;

        $product = Product::create($form);

        return response()->json([
            'code' => 'PRODUCT_CREATED',
            'message' => 'Product created successfully',
            'data' => $product
        ], 201);
    }

    /**
     * Display a single product.
     */
    public function show($id)
    {
        if($id){
            $product = Product::where('merchant_app_id', $this->merchant->id)->where('product_id',$id)->first();
        }else{
            $product = Product::where('merchant_app_id', $this->merchant->id)->limit(100)->get();
        }

        // product validation
        if(!$product){
            return response()->json([
                'code' => 'PRODUCT_NOT_FOUND',
                'message' => 'Product ID or authentication is invalid!'
            ],422);
        }

        return response()->json([
            'code' => 'PRODUCT_RETRIEVED',
            'message' => 'Product successfully retrieved',
            'price' => $product
        ],200);
    }

    /**
     * Update an existing product.
     */
    public function update(Request $request, $id)
    {
        $product = Product::where('merchant_app_id', $this->merchant->id)->where('product_id',$id)->first();

        if (!$product) {
            return response()->json([
                'code' => 'PRODUCT_NOT_FOUND',
                'message' => 'Product ID or authentication is invalid!'
            ], 404);
        }

        $validated = Validator::make($request->all(),[
            'name' => 'sometimes|string|max:255',
            'type' => 'sometimes|in:physical,digital,service',
            'short_description' => 'nullable|string',
            'sku' => 'sometimes|string',
            'image' => 'sometimes|string',
        ]);

        // price validation
        $price = Price::where('merchant_app_id', $this->merchant->id)->where('price_id',$request->price_id)->exists();

        if(!$price){
            return response()->json([
                'code' => 'INVALID_PRICE_ID',
                'message' => 'Invalid price ID'
            ],422);
        }

        // check validation
        if ($validated->fails()) {
            return response()->json([
                'code'    => "INVALID_DATA",
                'message' => 'Invalid product data, Please have a look our API docs.',
                'errors'  => $validated->errors(),
            ], 422);
        }

        $form = $validated->validated();
        $form['merchant_app_id'] = $this->merchant->id;

        $product->update($form);

        return response()->json([
            'code' => 'PRODUCT_UPDATED',
            'message' => 'Product updated successfully',
            'data' => $product
        ],200);
    }

    /**
     * Delete a product.
     */
    public function destroy($id)
    {
        $product = Product::where('merchant_app_id', $this->merchant->id)->where('product_id',$id)->first($id);

        if (!$product) {
            return response()->json([
                'code' => 'PRODUCT_NOT_FOUND',
                'message' => 'Product ID or authentication is invalid!'
            ], 404);
        }

        $product->delete();

        return response()->json([
            'code' => 'PRODUCT_DELETED',
            'message' => 'Product deleted successfully'
        ],200);
    }
}
