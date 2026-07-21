<?php
namespace App\Http\Controllers;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Models\UserType;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ProductController extends Controller
{
public function index(Request $request)
{
    $query = Product::latest();

    // Apply search filters
    if ($request->filled('product')) {
        $query->where('product', 'like', '%' . $request->input('product') . '%');
    }

    if ($request->filled('status') && in_array($request->input('status'), [0, 1])) {
        $query->where('status', $request->input('status'));
    }

    $products = $query->get();
    $isSuperAdmin = auth()->user() && auth()->user()->isSuperAdmin();
    return view('admin.pages.products.index-product', compact('products', 'isSuperAdmin'));
}

    public function create()
    {
        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Form data retrieved successfully'
            ]);
        }
        return view('admin.pages.products.add-product');
    }

   // In your ProductController

    public function store(Request $request)
    {
            $validator = Validator::make($request->all(), [
                'product' => 'required|string|max:255|unique:products',
                'is_private' => 'nullable|boolean',
                'is_airambulance' => 'nullable|boolean',
            ], [
                'product.required' => 'The product name field is required.',
                'product.unique' => 'This product name already exists.',
            ]);

            if ($validator->fails()) {
                if ($request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Validation failed',
                        'errors' => $validator->errors()
                    ], 422);
                }
                return redirect()->back()
                    ->withErrors($validator)
                    ->withInput()
                    ->with('error', 'Please correct the errors below.');
            }

            // Case-insensitive uniqueness check
            $existingProduct = Product::whereRaw('LOWER(product) = ?', [strtolower($request->product)])->first();
            if ($existingProduct) {
                $message = 'Product already exists.';
                if ($request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => $message
                    ], 422);
                }
                return redirect()->back()
                    ->withInput()
                    ->with('error', $message);
            }

            // Custom validation: cannot check both Private Charter and Air Ambulance
            if ($request->has('is_private') && $request->has('is_airambulance')) {
                $message = 'You cannot select both Private Charter and Air Ambulance.';
                if ($request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => $message
                    ], 422);
                }
                return redirect()->back()
                    ->withInput()
                    ->with('error', $message);
            }

            DB::beginTransaction();
            
            try {
                $product = Product::create([
                    'id' => Str::uuid(),
                    'product' => $request->product,
                    'is_private' => $request->has('is_private') ? true : false,
                    'is_airambulance' => $request->has('is_airambulance') ? 1 : 0,
                    'status' => 1,
                ]);
                DB::commit();

                $message = 'Product created successfully.';
                if (request()->ajax() || request()->wantsJson()) {
                    return response()->json([
                        'success' => true,
                        'message' => $message,
                        'product' => $product
                    ]);
                }
                
                return redirect()->route('admin.products.index')
                    ->with('success', $message);
                    
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Product creation failed: ' . $e->getMessage());

                $message = 'Failed to create product: ' . $e->getMessage();
                if ($request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => $message
                    ], 500);
                }
                
                return back()
                    ->withInput()
                    ->with('error', $message);
            }
    }

// Similarly update other methods (update, toggleStatus, destroy) with this pattern
    public function edit($id)
    {
        $product = Product::findOrFail($id);
        if ($product->status == 0) {
            if (request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Inactive product cannot be edited.'
                ], 400);
            }
            return redirect()->route('admin.products.index')
                ->with('error', 'Inactive product cannot be edited.');
        }

        if (request()->ajax() || request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'product' => $product
            ]);
        }
        
        return view('admin.pages.products.edit-product', compact('product'));
    }

    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'product' => 'required|string|max:255|unique:products,product,'.$id,
            'is_private' => 'nullable|boolean',
            'is_airambulance' => 'nullable|boolean',
        ], [
            'product.required' => 'The product name field is required.',
            'product.unique' => 'This product name already exists.',
        ]);

        if ($validator->fails()) {
            if (request()->ajax() || request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            return redirect()->back()
                ->withErrors($validator)
                ->withInput()
                ->with('error', 'Please correct the errors below.');
        }

        // Custom validation: cannot check both Private Charter and Air Ambulance
        if ($request->has('is_private') && $request->has('is_airambulance')) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot select both Private Charter and Air Ambulance.'
                ], 422);
            }
            return redirect()->back()
                ->withInput()
                ->with('error', 'You cannot select both Private Charter and Air Ambulance.');
        }

        DB::beginTransaction();
        
        try {
            $product->update([
                'product' => $request->product,
                'is_private' => $request->has('is_private') ? true : false,
                'is_airambulance' => $request->has('is_airambulance') ? 1 : 0,
                'updated_at' => now(),
                ]);

            DB::commit();
            
            if (request()->ajax() || request()->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Product updated successfully.',
                    'product' => $product->fresh()
                ]);
            }
            
            return redirect()->route('admin.products.index')
                ->with('success', 'Product updated successfully.');
                
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Product update failed: ' . $e->getMessage());
            
            if (request()->ajax() || request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update product: ' . $e->getMessage()
                ], 500);
            }
            
            return back()
                ->withInput()
                ->with('error', 'Failed to update product: ' . $e->getMessage());
        }
    }

    public function toggleStatus($id)
    {
        $product = Product::findOrFail($id);
        
        DB::beginTransaction();
        try {
            $newStatus = !$product->status;
            $product->update(['status' => $newStatus]);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Product status updated successfully',
                'new_status' => $newStatus
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update product status'
            ], 500);
        }
    }
    public function show($id)
    {
        $product = Product::findOrFail($id);
        
        if (request()->ajax() || request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'product' => $product
            ]);
        }
        
        return view('admin.pages.products.view-product', compact('product'));
    }

    public function view($id)
    {
        return $this->show($id);
    }

    public function getUsersByProducts(Request $request)
    {
        $productIds = $request->input('product_ids', []);

        $userIds = Product::whereIn('id', $productIds)
            ->pluck('user_ids')
            ->flatten() // Combine arrays into one
            ->filter()  // Remove nulls
            ->unique();

        $users = User::whereIn('id', $userIds)->where('status', 1)->get(['id', 'name']);

        return response()->json($users);
    }

    public function destroy($product)
    {
        // Example superadmin check (adjust as per your auth logic)
        if (!auth()->user() || !auth()->user()->isSuperAdmin()) {
            return redirect()->route('admin.products.index')
                ->with('error', 'You do not have permission to delete products.');
        }

        $product = Product::findOrFail($product);

        DB::beginTransaction();
        try {
            $product->delete();
            DB::commit();
            return redirect()->route('admin.products.index')
                ->with('success', 'Product deleted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Product deletion failed: ' . $e->getMessage());
            return redirect()->route('admin.products.index')
                ->with('error', 'Failed to delete product: ' . $e->getMessage());
        }
    }
}