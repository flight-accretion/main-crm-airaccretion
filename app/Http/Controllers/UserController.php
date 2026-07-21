<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Auth\Events\PasswordReset;
use App\Mail\CustomResetPassword;
use Illuminate\Support\Facades\Mail;
use App\Models\Country;
use App\Models\State;
use App\Models\City;
use App\Models\UserType;
use Illuminate\Support\Facades\DB;


class UserController extends Controller
{
    public function login(Request $request)
    {
        // Validate input
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Attempt login using web guard
        if (!Auth::attempt($request->only('email', 'password'), $request->filled('remember'))) {
            return back()->withErrors([
                'email' => 'Email or password is incorrect.',
            ])->withInput();
        }

        // Regenerate session
        $request->session()->regenerate();

        // Redirect based on user type
        $user = Auth::user();
        $userType = $user?->userType?->user_type;

        if (in_array($userType, UserType::ADMIN_ROLES)) {
            $path = 'admin/rides/upcoming-ride';
        } elseif (in_array($userType, UserType::OPERATIONS_ROLES)) {
            $path = 'admin/rides/upcoming-ride';
        } elseif (in_array($userType, UserType::SALES_ROLES)) {
            $path = '/sales-dashboard';
        } elseif (in_array($userType, UserType::ACCOUNTS_ROLES)) {
            $path = 'admin/rides/ride-status';
        } else {
            $path = 'admin/rides/upcoming-ride'; // fallback
        }

        return redirect()->intended($path)->with('success', 'Login successful');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('success', 'You have been logged out successfully.');
    }

    public function showForgotPasswordForm()
    {
        return view('admin.auth.forgot-password');
    }

    public function sendResetLink(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return back()->withErrors(['email' => 'We can\'t find a user with that email address.']);
        }

        // Create token
        $token = Password::broker()->createToken($user);

        // Create reset URL
        $resetUrl = url(route('password.reset', [
            'token' => $token,
            'email' => $user->email,
        ], false));

        // Send custom email
        Mail::to($user->email)->send(new CustomResetPassword($resetUrl));

        return back()->with('status', 'Password reset link has been sent to your email address.');
    }

    public function showResetForm(Request $request, $token = null)
    {
        return view('admin.auth.reset-password')->with(
            ['token' => $token, 'email' => $request->email]
        );
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password)
                ])->setRememberToken(Str::random(60));

                $user->save();

                event(new PasswordReset($user));
            }
        );

        return $status == Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', __($status))
            : back()->withErrors(['email' => [__($status)]]);
    }
    public function downloadLog()
    {
        $today = now()->format('Y-m-d');
        $filePath = storage_path("logs/laravel-{$today}.log");

        // Check if file exists
        if (!file_exists($filePath)) {
            return response()->json([
                'status' => 'error',
                'message' => "Log file for {$today} not found."
            ], 404);
        }

        // Download file
        return response()->download($filePath);
    }
    public function showChangePasswordForm()
    {
        return view('admin.auth.change-password');
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => [
                'required',
                'min:8',
                'max:64',
                'different:current_password',
                // Only allow letters, numbers, and common symbols
                'regex:/^[A-Za-z0-9!@#$%^&*()_+\-=\[\]{};:\'"|,.<>\/?`~]+$/'
            ],
            'confirm_password' => 'required|same:new_password',
        ], [
            'new_password.max' => 'Password must not exceed 64 characters.',
            'new_password.regex' => 'Password contains invalid characters.',
        ]);

        $user = Auth::user();
        // Sanitize password input (strip tags)
        $newPassword = strip_tags($request->new_password);

        // Verify current password
        if (!Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => 'Your current password is incorrect']);
        }

        // Update password
        $user->password = Hash::make($newPassword);
        $user->save();

        return redirect()->route('password.change')->with('success', 'Password changed successfully!');
    }
    public function applyUuid(Request $request)
    {
        $countryChunk = 1000;
        $stateChunk   = 2000;
        $cityChunk    = 5000;
        DB::beginTransaction();
        try {
        /*-------------------------------------------------
        | 1. Countries  → new `countries` table (uuid PK)
        *------------------------------------------------*/
            $countryMap = [];                       // old → new uuid
            Country::chunkById($countryChunk, function ($chunk) use (&$countryMap) {
                $rows = [];
                foreach ($chunk as $legacy) {
                    $uuid = (string) Str::uuid();
                    $rows[] = [
                        'id'            => $uuid,
                        'name'          => $legacy->name,
                        'alpha_code'    => $legacy->alpha_code,
                        'symbol'        => $legacy->symbol,
                        'currency_code' => $legacy->currency_code,
                        'isd_code'      => $legacy->isd_code,
                        'status'        => $legacy->status ?? 1,
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ];
                    $countryMap[$legacy->id] = $uuid;
                }
                if ($rows) {
                    DB::table('countries_temp')->insert($rows);
                }
            });
        /*-------------------------------------------------
        | 2. States  → new `states`
        *------------------------------------------------*/
            $stateMap = [];                         // old → new uuid
            State::chunkById($stateChunk, function ($chunk) use (&$stateMap, $countryMap) {
                $rows = [];
                foreach ($chunk as $legacy) {
                    $cUuid = $countryMap[$legacy->country_id] ?? null;
                    if (!$cUuid) continue;          // orphan guard
                    $uuid = (string) Str::uuid();
                    $rows[] = [
                        'id'          => $uuid,
                        'country_id'  => $cUuid,
                        'name'        => $legacy->name,
                        'status'      => $legacy->status ?? 1,
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ];
                    $stateMap[$legacy->id] = $uuid;
                }
                if ($rows) {
                    DB::table('states_temp')->insert($rows);
                }
            });
        /*-------------------------------------------------
        | 3. Cities  → new `cities`
        *------------------------------------------------*/
            City::chunkById($cityChunk, function ($chunk) use ($countryMap, $stateMap) {
                $rows = [];
                $orphanCities = [];
                foreach ($chunk as $legacy) {
                    $cUuid = $countryMap[$legacy->country_id] ?? null;
                    $sUuid = $stateMap[$legacy->state_id]    ?? null;
                     // ──► store city if country or state is missing
                    if (!$cUuid || !$sUuid) {
                        $orphanCities[] = [
                            'legacy_id'        => $legacy->id,
                            'name'             => $legacy->name,
                            'legacy_country_id'=> $legacy->country_id,
                            'legacy_state_id'  => $legacy->state_id,
                        ];
                        continue;
                    }
                    $rows[] = [
                        'id'          => (string) Str::uuid(),
                        'country_id'  => $cUuid,
                        'state_id'    => $sUuid,
                        'name'        => $legacy->name,
                        'lat'         => $legacy->lat,
                        'lng'         => $legacy->lng,
                        'timezone'    => $legacy->timezone,
                        'utc'         => $legacy->utc,
                        'status'      => $legacy->status ?? 1,
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ];
                }
                if ($rows) {
                    DB::table('city_temp')->insert($rows);
                }
            });
            DB::commit();
            return response()->json([
                'status'  => 'success',
                'message' => 'UUID migration completed.'
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
     public function create()
    {
        // Get Super Admin ID first
        $superAdmin = UserType::where('user_type', UserType::SUPER_ADMIN)->first();

        // Get children of Super Admin as the first level options (excluding Admin)
        $userTypes = UserType::where('parent_id', $superAdmin->id)
                ->whereNotIn('user_type', [UserType::ADMIN])
                ->where('status', 1)
                ->orderBy('user_type', 'asc')
                ->get();

        return view('admin.pages.staff.add-staff', compact('userTypes'));
    }

   public function store(Request $request)
    {
       $validator = Validator::make($request->all(),[
            'name' => [
                'required',
                'string',
                'min:2',
                'max:255',
                'regex:/^[a-zA-Z\s\'-\.]+$/'
            ],
            'email' => [
                'required',
                'string',
                'email:rfc,dns',
                'max:320',
                'unique:'.User::class.',email',
                'regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/'
            ],
            'password' => [
                'required',
                'string',
                'min:8',
                'max:128',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/',
                'not_regex:/^(password|123456|qwerty|abc123|admin|letmein)$/i'
            ],
            'address' => [
                'required',
                'string',
                'min:10',
                'max:500',
                'regex:/^[a-zA-Z0-9\s\-\,\.\#\/\(\)]+$/'
            ],
            'contact_number' => [
                'required',
                'string',
                'min:10',
                'max:15',
                'regex:/^[\+]?[0-9\-\(\)\s]+$/',
                'unique:users,contact_number'
            ],
            'user_type_id' => ['required', 'exists:user_types,id'],
            'status' => ['required', 'boolean'],
            'joining_date' => [
                'nullable',
                'date',
                'before_or_equal:today'
            ],
        ], [
            'name.required' => 'Name is required.',
            'name.regex' => 'Name can only contain letters, spaces, hyphens, apostrophes, and periods.',
            'name.min' => 'Name must be at least 2 characters.',
            'email.required' => 'Email is required.',
            'email.email' => 'Enter a valid email address.',
            'email.regex' => 'Invalid email format.',
            'email.unique' => 'This email is already registered.',
            'email.max' => 'Email too long.',
            'password.required' => 'Password is required.',
            'password.min' => 'Password too short (minimum 8 characters).',
            'password.max' => 'Password too long (maximum 128 characters).',
            'password.regex' => 'Password too weak. Must contain uppercase, lowercase, number, and special character.',
            'password.not_regex' => 'Password too weak. Cannot use common passwords.',
            'address.required' => 'Address is required.',
            'address.min' => 'Address too short (minimum 10 characters).',
            'address.max' => 'Address too long (maximum 500 characters).',
            'address.regex' => 'Invalid address format.',
            'contact_number.required' => 'Contact number is required.',
            'contact_number.min' => 'Number too short (minimum 10 digits).',
            'contact_number.max' => 'Number too long (maximum 15 digits).',
            'contact_number.regex' => 'Enter numeric value only.',
            'contact_number.unique' => 'Contact number must be unique.',
            'joining_date.before_or_equal' => 'Date cannot be in the future.',
        ]);
        if ($validator->fails()) {

            return back()->withErrors($validator,'add')->withInput();
        }
        DB::beginTransaction();

        try {
            // Sanitize and clean input data
            $cleanedData = [
                'name' => trim(strip_tags($request->name)),
                'email' => strtolower(trim($request->email)),
                'password' => Hash::make($request->password),
                'address' => trim(strip_tags($request->address)),
                'contact_number' => preg_replace('/[^\d\+\-\(\)\s]/', '', trim($request->contact_number)),
                'user_type_id' => $request->user_type_id,
                'status' => $request->status,
                'joining_date' => $request->joining_date,
                'last_login' => now(),
            ];

            $user = User::create($cleanedData);
            DB::commit();
            return redirect()->route('admin.users.index')->with('success', 'Staff created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Failed to create staff. Error: ' . $e->getMessage());
        }
    }
    public function getUserTypesByParent(Request $request)
    {
        $parentId = $request->input('parent_id');

        // Get child user types for the given parent_id
        $userTypes = UserType::where('parent_id', $parentId)
            ->whereNotIn('user_type', [UserType::SUPER_ADMIN, UserType::ADMIN])
            ->where('status', 1)
            ->orderBy('user_type', 'asc')
            ->get(['id', 'user_type']);

        return response()->json($userTypes);
    }
    public function index()
    {
        $users = User::with('userType')
        ->orderBy('created_at', 'desc')
        ->get();

        // Get Super Admin ID first
        $superAdmin = UserType::where('user_type', UserType::SUPER_ADMIN)->first();

        // Get children of Super Admin as the first level options (excluding Admin)
        $userTypes = UserType::where('parent_id', $superAdmin->id)
                ->whereNotIn('user_type', [UserType::ADMIN])
                ->where('status', 1)
                ->orderBy('user_type', 'asc')
                ->get();

        return view('admin.pages.staff.index-staff', [
            'users' => $users,
            'userTypes' => $userTypes
        ]);
    }
    public function edit($id)
    {
        $user = User::findOrFail($id);
        $userTypes = UserType::whereNull('parent_id')->get();

        return view('admin.pages.staff.edit-staff', compact('user', 'userTypes'));
    }
   public function getUserforEdit(Request $request)
   {
    $user = User::with('userType')->findOrFail($request->user_id);
    if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }
    if ($user->joining_date) {
        $user->joining_date = date('Y-m-d', strtotime($user->joining_date));
    }

    return response()->json($user);
   }

    public function getUserTypeHierarchy(Request $request)
    {
        $id = $request->input('id');
        $hierarchy = [];

        // Build hierarchy from bottom to top
        $currentId = $id;
        while ($currentId) {
            $userType = UserType::find($currentId);
            if (!$userType) break;

            $hierarchy[] = $userType;
            $currentId = $userType->parent_id;
        }

        // Reverse to get from top-level to selected (Super Admin -> Manager -> Operational Manager -> User)
        return response()->json(array_reverse($hierarchy));
    }

    /**
     * Get the complete hierarchy path for a user type (used in edit forms)
     */
    public function getUserTypeHierarchyPath(Request $request)
    {
        $userTypeId = $request->input('user_type_id');

        if (!$userTypeId) {
            return response()->json([]);
        }

        $hierarchy = [];
        $currentId = $userTypeId;

        // Build path from selected user type to root
        while ($currentId) {
            $userType = UserType::find($currentId);
            if (!$userType) break;

            $hierarchy[] = [
                'id' => $userType->id,
                'user_type' => $userType->user_type,
                'parent_id' => $userType->parent_id,
            ];

            $currentId = $userType->parent_id;
        }

        // Reverse to get from root to selected (level 1 -> level 2 -> level 3)
        $hierarchyPath = array_reverse($hierarchy);

        // Remove Super Admin from hierarchy and adjust levels
        $filteredHierarchy = [];
        foreach ($hierarchyPath as $item) {
            if ($item['user_type'] !== UserType::SUPER_ADMIN) {
                $filteredHierarchy[] = $item;
            }
        }

        // Add level numbers starting from 1 (excluding Super Admin)
        foreach ($filteredHierarchy as $index => &$item) {
            $item['level'] = $index + 1;
        }

        return response()->json($filteredHierarchy);
    }


// Update the user
    public function update(Request $request, User $user)
    {
        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'min:2',
                'max:255',
                'regex:/^[a-zA-Z\s\'-\.]+$/'
            ],
            'email' => [
                'required',
                'string',
                'email:rfc,dns',
                'max:320',
                'unique:users,email,'.$user->id,
                'regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/'
            ],
            'password' => [
                'nullable',
                'string',
                'min:8',
                'max:128',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/',
                'not_regex:/^(password|123456|qwerty|abc123|admin|letmein)$/i'
            ],
            'address' => [
                'required',
                'string',
                'min:10',
                'max:500',
                'regex:/^[a-zA-Z0-9\s\-\,\.\#\/\(\)]+$/'
            ],
            'contact_number' => [
                'required',
                'string',
                'min:10',
                'max:15',
                'regex:/^[\+]?[0-9\-\(\)\s]+$/',
                'unique:users,contact_number,'.$user->id
            ],
            'user_type_id' => ['required', 'exists:user_types,id'],
            'status' => ['required', 'boolean'],
            'joining_date' => [
                'nullable',
                'date',
                'before_or_equal:today'
            ],
        ], [
            'name.required' => 'Name is required.',
            'name.regex' => 'Name can only contain letters, spaces, hyphens, apostrophes, and periods.',
            'name.min' => 'Name must be at least 2 characters.',
            'email.required' => 'Email is required.',
            'email.email' => 'Enter a valid email address.',
            'email.regex' => 'Invalid email format.',
            'email.unique' => 'This email is already registered.',
            'email.max' => 'Email too long.',
            'password.min' => 'Password too short (minimum 8 characters).',
            'password.max' => 'Password too long (maximum 128 characters).',
            'password.regex' => 'Password too weak. Must contain uppercase, lowercase, number, and special character.',
            'password.not_regex' => 'Password too weak. Cannot use common passwords.',
            'address.required' => 'Address is required.',
            'address.min' => 'Address too short (minimum 10 characters).',
            'address.max' => 'Address too long (maximum 500 characters).',
            'address.regex' => 'Invalid address format.',
            'contact_number.required' => 'Contact number is required.',
            'contact_number.min' => 'Number too short (minimum 10 digits).',
            'contact_number.max' => 'Number too long (maximum 15 digits).',
            'contact_number.regex' => 'Enter numeric value only.',
            'contact_number.unique' => 'Contact number must be unique.',
            'joining_date.before_or_equal' => 'Date cannot be in the future.',
        ]);

        if ($validator->fails()) {

            return back()->withErrors($validator,'edit')->withInput();
        }

        DB::beginTransaction();

        try {
            $data = [
                'name' => trim(strip_tags($request->name)),
                'address' => trim(strip_tags($request->address)),
                'email' => strtolower(trim($request->email)),
                'contact_number' => preg_replace('/[^\d\+\-\(\)\s]/', '', trim($request->contact_number)),
                'user_type_id' => $request->user_type_id,
                'status' => $request->status,
                'joining_date' => $request->joining_date,
            ];

            // Only update password if provided
            if ($request->filled('password')) {
                $data['password'] = Hash::make($request->password);
            }

            $user->update($data);
            DB::commit();
            return redirect()->route('admin.users.index')->with('success', 'Staff updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Failed to update staff. Error: ' . $e->getMessage());
        }
    }
    public function toggleStatus(User $user)
    {
        try {
            $user->update(['status' => !$user->status]);
            return response()->json([
                'success' => true,
                'status' => $user->status,
                'message' => $user->status ? 'User activated successfully' : 'User deactivated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle user status: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show(User $user)
    {
        return view('admin.pages.staff.view-staff', compact('user'));
    }

    /**
     * Get active users for dropdowns
     */
    public function getActiveUsers()
    {
        $activeUsers = User::where('status', 1)
            ->with('userType')
            ->get(['id', 'name', 'email', 'user_type_id']);

        return response()->json($activeUsers);
    }
}
