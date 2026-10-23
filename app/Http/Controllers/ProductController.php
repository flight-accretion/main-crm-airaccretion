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
use App\Services\WebsiteCatalogAiNotesService;

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

    // Display-only data for the redesigned list (no product data is changed here).
    $serviceCounts = self::serviceCountsByProduct();
    $allProducts = Product::select('id', 'product', 'status', 'is_airambulance')->get();
    $productSummary = [
        'total' => $allProducts->count(),
        'active' => $allProducts->where('status', 1)->count(),
        'inactive' => $allProducts->where('status', '!=', 1)->count(),
        'services_linked' => self::linkedServiceCount($allProducts->pluck('id')->map(fn ($id) => (string) $id)->all()),
        'service_links' => $allProducts->sum(fn ($p) => $serviceCounts[(string) $p->id] ?? 0),
    ];
    $productCategories = $allProducts->mapWithKeys(fn ($p) => [(string) $p->id => self::productCategories($p)])->all();
    $hasSyncColumn = \Illuminate\Support\Facades\Schema::hasColumn('products', 'sync_at');
    $canSyncProducts = auth()->user()
        && in_array(optional(auth()->user()->userType)->user_type, array_merge(UserType::ADMIN_ROLES, [UserType::SENIOR_OPERATIONS_MANAGER, UserType::OPERATIONS_MANAGER]), true);

    return view('admin.pages.products.index-product', compact(
        'products',
        'isSuperAdmin',
        'serviceCounts',
        'productSummary',
        'productCategories',
        'hasSyncColumn',
        'canSyncProducts'
    ));
}

    /**
     * Product ids stored on a service (services.product_ids may be JSON, or JSON encoded twice).
     */
    public static function serviceProductIds($productIds): array
    {
        for ($i = 0; $i < 3 && is_string($productIds); $i++) {
            $decoded = json_decode($productIds, true);
            if ($decoded === null) {
                break;
            }
            $productIds = $decoded;
        }

        return is_array($productIds) ? array_values(array_unique(array_map('strval', array_filter($productIds)))) : [];
    }

    /**
     * Number of services under each product id.
     */
    private static function serviceCountsByProduct(): array
    {
        $counts = [];
        Service::select('id', 'product_ids')->get()->each(function ($service) use (&$counts) {
            foreach (self::serviceProductIds($service->getRawOriginal('product_ids')) as $productId) {
                $counts[$productId] = ($counts[$productId] ?? 0) + 1;
            }
        });

        return $counts;
    }

    /**
     * Services linked to at least one existing product.
     */
    private static function linkedServiceCount(array $productIds): int
    {
        $productIds = array_flip($productIds);

        return Service::select('id', 'product_ids')->get()->filter(function ($service) use ($productIds) {
            foreach (self::serviceProductIds($service->getRawOriginal('product_ids')) as $productId) {
                if (isset($productIds[$productId])) {
                    return true;
                }
            }
            return false;
        })->count();
    }

    /**
     * Category tags for the list chips. There is no category column, so this is read from the
     * product name (and the Air Ambulance flag). Display only.
     */
    public static function productCategories($product): array
    {
        $name = mb_strtolower((string) $product->product);
        $categories = [];
        if (str_contains($name, 'heli')) {
            $categories[] = 'Helicopter';
        }
        if (str_contains($name, 'plane') || str_contains($name, 'jet') || str_contains($name, 'aircraft')) {
            $categories[] = 'Plane';
        }
        if (str_contains($name, 'yacht') || str_contains($name, 'boat') || str_contains($name, 'cruise')) {
            $categories[] = 'Yacht & boat';
        }
        if ((int) $product->is_airambulance === 1 || str_contains($name, 'ambulance')) {
            $categories[] = 'Ambulance';
        }

        return $categories ?: ['Other'];
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
                'booking_email_note' => 'nullable|string|max:5000',
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
                    'booking_email_note' => $request->booking_email_note,
                    'status' => 1,
                ]);
                DB::commit();
                app(\App\Services\MasterDataCacheService::class)->clear();

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
  public function edit(
    $id,
    WebsiteCatalogAiNotesService $websiteAiNotes
) {
    $product =
        Product::findOrFail(
            $id
        );


    if ($product->status == 0) {

        if (
            request()->wantsJson()
        ) {
            return response()->json(
                [
                    'success' => false,
                    'message' =>
                        'Inactive product cannot be edited.',
                ],
                400
            );
        }


        return redirect()
            ->route(
                'admin.products.index'
            )
            ->with(
                'error',
                'Inactive product cannot be edited.'
            );
    }


    if (
        request()->ajax()
        ||
        request()->wantsJson()
    ) {
        return response()->json([
            'success' => true,
            'product' => $product,
        ]);
    }


    $websiteAiInfo =
        $websiteAiNotes
            ->forProduct(
                $product
            );


    return view(
        'admin.pages.products.edit-product',
        compact(
            'product',
            'websiteAiInfo'
        )
    );
}

    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'product' => 'required|string|max:255|unique:products,product,'.$id,
            'is_private' => 'nullable|boolean',
            'is_airambulance' => 'nullable|boolean',
            'booking_email_note' => 'nullable|string|max:5000',
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
                'booking_email_note' => $request->booking_email_note,
                'updated_at' => now(),
                ]);

            DB::commit();
            app(\App\Services\MasterDataCacheService::class)->clear();
            
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
            app(\App\Services\MasterDataCacheService::class)->clear();
            
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
   public function show(
    $id,
    WebsiteCatalogAiNotesService $websiteAiNotes
) {
    $product =
        Product::findOrFail(
            $id
        );


    if (
        request()->ajax()
        ||
        request()->wantsJson()
    ) {
        return response()->json([
            'success' => true,
            'product' => $product,
        ]);
    }


    $websiteAiInfo =
        $websiteAiNotes
            ->forProduct(
                $product
            );


    return view(
        'admin.pages.products.view-product',
        compact(
            'product',
            'websiteAiInfo'
        )
    );
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
        $user = auth()->user();
        $userRole = optional($user?->userType)->user_type;

        if (
            !$user ||
            !in_array($userRole, array_merge(UserType::ADMIN_ROLES, UserType::OPERATIONS_ROLES), true)
        ) {
            return redirect()->route('admin.products.index')
                ->with('error', 'You do not have permission to delete products.');
        }

        $product = Product::findOrFail($product);

        DB::beginTransaction();
        try {
            $product->delete();
            DB::commit();
            app(\App\Services\MasterDataCacheService::class)->clear();
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
