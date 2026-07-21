<?php

namespace App\Models;

use App\Mail\CustomResetPassword;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $fillable = [
        'name',
        'email',
        'password',
        'user_type_id',
        'status',
        'address',
        'contact_number',
        'joining_date',
        'last_login',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];
 

    protected static function booted()
    {
        static::creating(function ($user) {
            if (empty($user->id)) {
                $user->id = (string) Str::uuid();
            }
        });
    }
    public function userType()
    {
        return $this->belongsTo(UserType::class, 'user_type_id');
    }

    /**
     * Get users with same or child user types based on hierarchy
     */
    public function getUsersInHierarchy()
    {
        if (!$this->userType) {
            return collect([$this]);
        }

        // Get all descendant user type IDs
        $descendantUserTypeIds = $this->userType->getDescendantIds();

        // Build the query for users in hierarchy
        $query = User::query();

        // If the current user has descendants, include current user + descendants
        // If no descendants, only include the current user
        if (!empty($descendantUserTypeIds)) {
            $query->where(function($q) use ($descendantUserTypeIds) {
                $q->where('id', $this->id) // Include current user
                  ->orWhereIn('user_type_id', $descendantUserTypeIds); // Include descendants
            });
        } else {
            // No descendants, only return current user
            $query->where('id', $this->id);
        }

        return $query->get();
    }

    /**
     * Static method to get users in hierarchy for a given user
     */
    public static function getUsersInHierarchyFor($userId)
    {
        $user = self::find($userId);
        return $user ? $user->getUsersInHierarchy() : collect();
    }
    
    public function isSuperAdmin()
    {
        return $this->userType &&
            strtolower($this->userType->user_type) === strtolower(UserType::SUPER_ADMIN);
    }

    /**
     * Get targets assigned to this user (as sales executive)
     */
    public function targets()
    {
        return $this->hasMany(Target::class, 'sales_executive_id');
    }

    /**
     * Get targets assigned by this user (as manager)
     */
    public function assignedTargets()
    {
        return $this->hasMany(Target::class, 'assigned_by');
    }

    /**
     * Get active targets for current year
     */
    public function getCurrentYearTargets()
    {
        return $this->targets()
            ->where('year', date('Y'))
            ->where('status', 'active')
            ->orderBy('month');
    }

    /**
     * Get current month target
     */
    public function getCurrentMonthTarget()
    {
        return $this->targets()
            ->where('year', date('Y'))
            ->where('month', date('n'))
            ->where('status', 'active')
            ->first();
    }

    /**
     * Get sales executives assigned to this user (as manager)
     */
    public function assignedSalesExecutives()
    {
        return $this->hasManyThrough(
            User::class,
            SalesExecutiveAssignment::class,
            'manager_id',
            'id',
            'id',
            'sales_executive_id'
        )->where('sales_executive_assignments.status', 1);
    }

    /**
     * Get managers that this user is assigned to (as sales executive)
     */
    public function assignedManagers()
    {
        return $this->hasManyThrough(
            User::class,
            SalesExecutiveAssignment::class,
            'sales_executive_id',
            'id',
            'id',
            'manager_id'
        )->where('sales_executive_assignments.status', 1);
    }

    /**
     * Get all leads visible to this user based on their role and assignments
     */
    public function getVisibleLeads()
    {
        $userType = $this->userType->user_type ?? '';
        
        // Admin and Super Admin can see all leads
        if (in_array($userType, [UserType::SUPER_ADMIN, UserType::ADMIN])) {
            return Lead::query();
        }
        
        // Sales Executives can see only their own leads
        if ($userType === UserType::SALES_EXECUTIVE) {
            return Lead::where('representative_user_id', $this->id);
        }
        
        // Sales Managers can see their own leads + leads of assigned sales executives
        if (in_array($userType, [UserType::SENIOR_SALES_MANAGER, UserType::SALES_MANAGER])) {
            $assignedExecutiveIds = SalesExecutiveAssignment::getSalesExecutivesForManager($this->id)->pluck('id');
            $assignedExecutiveIds->push($this->id); // Include manager's own leads
            
            return Lead::whereIn('representative_user_id', $assignedExecutiveIds);
        }
        
        // Default: only own leads
        return Lead::where('representative_user_id', $this->id);
    }
}
