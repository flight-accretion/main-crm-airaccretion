<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserType extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    const SUPER_ADMIN = 'Super Admin';
    const ADMIN = 'Admin';
    const SENIOR_SALES_MANAGER = 'Senior Sales Manager';
    const SALES_MANAGER = 'Sales Manager';
    const SALES_EXECUTIVE = 'Sales Executive';
    const SENIOR_ACCOUNTS_MANAGER = 'Senior Accounts Manager';
    const ACCOUNTS_MANAGER = 'Accounts Manager';
    const ACCOUNTS_EXECUTIVE = 'Accounts Executive';
    const SENIOR_OPERATIONS_MANAGER = 'Senior Operations Manager';
    const OPERATIONS_MANAGER = 'Operations Manager';
    const OPERATIONS_EXECUTIVE = 'Operations Executive';
    const HR = 'Hr';

    // Group roles by team or permission
    const ACCOUNTS_ROLES = [
        self::SENIOR_ACCOUNTS_MANAGER,
        self::ACCOUNTS_MANAGER,
        self::ACCOUNTS_EXECUTIVE,
    ];

    const SALES_ROLES = [
        self::SENIOR_SALES_MANAGER,
        self::SALES_MANAGER,
        self::SALES_EXECUTIVE
    ];

    const OPERATIONS_ROLES = [
        self::SENIOR_OPERATIONS_MANAGER,
        self::OPERATIONS_MANAGER,
        self::OPERATIONS_EXECUTIVE,
    ];

    const ADMIN_ROLES = [
        self::SUPER_ADMIN,
        self::ADMIN,
        self::HR
    ];

    protected $fillable = ['id',
    'user_type',
     'description',
     'status',
     'parent_id'
    ];
    public function parent()
    {
        return $this->belongsTo(UserType::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(UserType::class, 'parent_id');
    }

    /**
     * Get all descendant user type IDs (children and their children recursively)
     */
    public function getDescendantIds()
    {
        $descendants = [];
        $this->loadDescendants($descendants, $this);
        return $descendants;
    }

    private function loadDescendants(&$collection, $userType)
    {
        $children = UserType::where('parent_id', $userType->id)->get();
        foreach ($children as $child) {
            $collection[] = $child->id;
            $this->loadDescendants($collection, $child);
        }
    }

}
