<?php

namespace App\Model\Administration;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

use Illuminate\Support\Facades\DB;

use App\Support\Permission\PermissionRegistrar;

trait HasRoles
{
    private $roleClass;
    private $groupClass;
    private $permissionClass;

    public static function bootHasRoles()
    {
        static::deleting(function ($model) {
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }
            $model->roles()->detach();
        });
    }

    public static function bootHasGroups()
    {
        static::deleting(function ($model) {
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }
            $model->groups()->detach();
        });
    }

    public static function bootHasPermissions()
    {
        static::deleting(function ($model) {
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }
            $model->permissions()->detach();
        });
    }

    public function getRoleClass()
    {
        if (! isset($this->roleClass)) {
            $this->roleClass = app(PermissionRegistrar::class)->getRoleClass();
        }
        return $this->roleClass;
    }

    public function getGroupClass()
    {
        if (! isset($this->groupClass)) {
            $this->groupClass = app(PermissionRegistrar::class)->getGroupClass();
        }
        return $this->groupClass;
    }

    public function getPermissionClass()
    {
        if (! isset($this->permissionClass)) {
            $this->permissionClass = app(PermissionRegistrar::class)->getPermissionClass();
        }
        return $this->permissionClass;
    }

    public function groups()
    {
        return $this->belongsToMany(Group::class);
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class);
    }

    public function assignGroup(...$group)
    {
        $groups = collect($group)->flatten()->map(function($g) {
            if (empty($g)) {
                return false;
            }
            return $this->getStoredGroup($g);
        })
        ->filter(function($g) {
            return $g instanceof GroupContract;
        })->map->id->all();

        $model = $this->getModel();
        if ($model->exists) {
            $this->groups()->sync($groups, false);
            $model->load('groups');
        } else {
            $cls = \get_class($model);
            $cls::saved(function($obj) use($groups, $model) {
                static $modelLastFiredOn;
                if ($modelLastFiredOn !== null && $modelLastFiredOn === $model) {
                    return;
                }
                $obj->groups->sync($groups);
                $obj->load('groups');
                $modelLastFiredOn = $obj;
            });
        }
        $this->forgetCachedPermissions();
        return $this;
    }

    public function assignRole(...$role)
    {
        $roles = collect($role)->flatten()->map(function($r) {
            if (empty($r)) {
                return false;
            }
            return $this->getStoredRole($r);
        })
        ->filter(function($r) {
            return $r instanceof RoleContract;
        })
        ->map->id->all();

        $model = $this->getModel();
        if ($model->exists) {
            $this->roles()->sync($roles);
            $this->load('roles');
        } else {
            $cls = \get_class($model);
            $cls::saved(function($obj) use($roles, $model) {
                static $modelLastFiredOn;
                if ($modelLastFiredOn !== null && $modelLastFiredOn === $model) {
                    return;
                }
                $obj->roles()->sync($roles);
                $obj->load('roles');
                $modelLastFiredOn = $obj;
            });
        }
        $this->forgetCachedPermissions();
        return $this;
    }

    public function givePermission(...$permission)
    {
        $permissions = collect($permission)->flatten()->map(function($p) {
            if (empty($p)) {
                return false;
            }
            return $this->getStoredPermission($p);
        })
        ->filter(function($p) {
            return $p instanceof PermissionContract;
        })
        ->map()->id->all();
        
        $model = $this->getModel();
        if ($model->exists) {
            $this->roles()->sync($permissions);
            $this->load('permissions');
        } else {
            $cls = \get_class($model);
            $cls::saved(function($obj) use ($model, $permissions) {
                static $modelLastFiredOn;
                if ($modelLastFiredOn !== null && $modelLastFiredOn === $model) {
                    $cls->permissions()->sync($permissions);
                    $cls->load('permissions');
                    $modelLastFiredOn = $obj;
                }
            });
        }
        $this->forgetCachedPermissions();
        return $this;
    }

    public function syncRoles(...$roles)
    {
        $this->roles()->detach();
        return $this->assignRole($roles);
    }

    public function syncGroups(...$groups)
    {
        $this->groups()->detach();
        return $this->assignGroup($groups);
    }

    public function syncPermissions(...$permissions)
    {
        $this->permissions()->detach();
        return $this->givePermission($permissions);
    }

    public function hasRole($role)
    {
        if (is_string($role)) {
            $roles = explode('|', $role);
            if (count($roles) <= 1) {
                return $this->roles->contains('name', $role);
            }
            $role = $roles;
        }
        if (is_numeric($role) || is_int($role)) {
            return $this->roles->contains('id', (int)$role);
        }
        if (is_array($role)) {
            foreach ($role as $r) {
                if ($this->hasRole($r)) {
                    return true;
                }
            }
            return false;
        }
        return !!$role->intersect($this->roles)->count();
    }

    public function hasGroup($group)
    {
        if (is_string($group)) {
            $groups = explode('|', $group);
            if (count($groups) <= 1) {
                return $this->groups->contains('name', $group);
            }
            $group = $groups;
        }
        if (is_numeric($group) || is_int($group)) {
            return $this->groups->contains('id', (int)$group);
        }
        if (is_array($group)) {
            foreach ($group as $g) {
                if ($this->hasGroup($g)) {
                    return true;
                }
            }
            return false;
        }
        return !!$group->intersect($this->groups)->count();
    }

    public function hasPermission($permission)
    {
        if (is_string($permission)) {
            $permissions = explode('|', $permission);
            if (count($permissions) <= 1) {
                return $this->permissions->contains('name', $permission);
            }
            $permission = $permissions;
        }
        if (is_numeric($permission) || is_int($permission)) {
            return $this->permissions->contains('id', (int)$permission);
        }
        if (is_array($permission)) {
            foreach ($permission as $p) {
                if ($this->hasPermission($p)) {
                    return true;
                }
            }
            return false;
        }
        return !!$permission->intersect($this->permissions)->count();
    }

    public function getStoredRole($role) {
        $roleClass = $this->getRoleClass();
        if (is_numeric($role)) {
            return $roleClass->find((int)$role);
        }
        if (is_string($role)) {
            return $roleClass->where('name', $role)->first();
        }
        return $role;
    }

    public function getStoredGroup($group) {
        $groupClass = $this->getGroupClass();
        if (is_numeric($group)) {
            return $groupClass->find((int)$group);
        }
        if (is_string($group)) {
            return $groupClass->where('name', $group)->first();
        }
        return $group;
    }

    public function getStoredPermission($permission) {
        $permissionClass = $this->getPermissionClass();
        if (is_numeric($permission)) {
            return $permissionClass->find((int)$permission);
        }
        if (is_string($permission)) {
            return $permissionClass->where('name', $permission)->first();
        }
        return $permission;
    }
}
