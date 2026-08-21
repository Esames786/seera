<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

#[Fillable([
    'name', 'email', 'password', 'employee_id', 'username', 'phone', 'language',
    'department_id', 'designation_id', 'branch_id', 'project_id', 'site_id',
    'warehouse_id', 'joining_date', 'contract_type', 'iqama_number',
    'iqama_expiry_date', 'mobile_access', 'two_factor_enabled', 'temporary_access',
    'access_start_date', 'access_end_date', 'last_login_at', 'status',
    'must_change_password', 'password_changed_at',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    private ?Collection $cachedEffectiveRoles = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'joining_date' => 'date',
            'iqama_expiry_date' => 'date',
            'access_start_date' => 'date',
            'access_end_date' => 'date',
            'last_login_at' => 'datetime',
            'mobile_access' => 'boolean',
            'two_factor_enabled' => 'boolean',
            'temporary_access' => 'boolean',
            'must_change_password' => 'boolean',
            'password_changed_at' => 'datetime',
        ];
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function designation()
    {
        return $this->belongsTo(Designation::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_roles')
            ->withPivot(['is_primary', 'is_temporary', 'access_start_date', 'access_end_date'])
            ->withTimestamps();
    }

    public function primaryRole(): ?Role
    {
        return $this->roles->firstWhere('pivot.is_primary', true) ?? $this->roles->first();
    }

    public function hasPermission(string $module, string $action): bool
    {
        return $this->effectiveRoleModels()->contains(fn (Role $role) => $role->permissions
            ->contains(fn (Permission $permission) => $permission->module === $module && $permission->action === $action));
    }

    public function effectiveAccessScope(): string
    {
        $scopes = $this->effectiveRoleModels()->pluck('access_scope');

        if ($scopes->contains(fn ($scope) => in_array($scope, ['All Company', 'Company Level'], true))) {
            return 'company';
        }
        if ($scopes->contains('Project Level')) {
            return 'project';
        }
        if ($scopes->contains('Site Level')) {
            return 'site';
        }
        if ($scopes->contains('Warehouse Level')) {
            return 'warehouse';
        }

        return 'none';
    }

    private function effectiveRoleModels(): Collection
    {
        return $this->cachedEffectiveRoles ??= $this->effectiveRolesQuery()->with('permissions')->get();
    }

    private function effectiveRolesQuery()
    {
        return $this->roles()
            ->where('roles.status', 'active')
            ->where(function ($query) {
                $query->where('user_roles.is_temporary', false)
                    ->orWhere(function ($temporary) {
                        $temporary->where('user_roles.is_temporary', true)
                            ->where(function ($dates) {
                                $dates->whereNull('user_roles.access_start_date')
                                    ->orWhereDate('user_roles.access_start_date', '<=', today());
                            })
                            ->where(function ($dates) {
                                $dates->whereNull('user_roles.access_end_date')
                                    ->orWhereDate('user_roles.access_end_date', '>=', today());
                            });
                    });
            });
    }

    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::upper(Str::substr($word, 0, 1)))
            ->implode('');
    }
}
