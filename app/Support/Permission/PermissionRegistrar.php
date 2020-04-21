<?php

namespace App\Support\Permission;

use Illuminate\Cache\CacheManager;
use Illuminate\Support\Collection;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Access\Authorizable;

use App\Contract\Permission\RoleContract;
use App\Contract\Permission\GroupContract;
use App\Contract\Permission\PermissionContract;

class PermissionRegistrar
{
    protected $cache;
    protected $cacheManager;
    protected $permissions;
    
    protected $roleClass;
    protected $groupClass;
    protected $permissionClass;
    
    public static $cacheExpirationTime;
    public static $cacheKey;
    public static $cacheModelKey;

    public function __construct(CacheManager $cacheManager)
    {
        $this->permissionClass = \App\Model\Administration\Permission::class;
        $this->groupClass = \App\Model\Administration\Group::class;
        $this->roleClass = \App\Model\Administration\Role::class;
        $this->cacheManager = $cacheManager;
        $this->initializeCache();
    }

    protected function initializeCache()
    {
        self::$cacheExpirationTime = config('permission.cache.expiration_time', 3600);
        self::$cacheKey = config('permission.cache.key', 'rgpab_cache');
        self::$cacheModelKey = config('permission.cache.model_key', 'name');
        $this->cache = $this->getCacheStoreFromConfig();
    }

    protected function getCacheStoreFromConfig(): \Illuminate\Contracts\Cache\Repository
    {
        $cacheDriver = config('permission.cache.store', 'default');
        if ($cacheDriver === 'default') {
            return $this->cacheManager->store();
        }
        if (! \array_key_exists($cacheDriver, config('cache.stores'))) {
            $cacheDriver = 'file';
        }
        return $this->cacheManager->store($cacheDriver);
    }

    public function registerPermissions(): bool
    {
        app(Gate::class)->before(function (Authorizable $user, string $ability) {
            if (method_exists($user, 'checkPermissionTo')) {
                return $user->checkPermissionTo($ability) ?: null;
            }
        });
        return true;
    }

    public function forgetCachedPermissions()
    {
        $this->permissions = null;
        return $this->cache->forget(self::$cacheKey);
    }

    public function clearClassPermissions()
    {
        $this->permissions = null;
    }

    public function getPermissions(array $params = []): Collection
    {
        if ($this->permissions === null) {
            $this->permissions = $this->cache->remember(self::$cacheKey, self::$cacheExpirationTime, function () {
                return $this->getPermissionClass()->with('roles', 'groups')->get();
            });
        }
        $permissions = clone $this->permissions;
        foreach ($params as $attr => $value) {
            $permissions = $permissions->where($attr, $value);
        }
        return $permissions;
    }

    public function getPermissionClass(): PermissionContract
    {
        return app($this->permissionClass);
    }

    public function setPermissionClass($permissionClass)
    {
        $this->permissionClass = $permissionClass;
        return $this;
    }

    public function getRoleClass(): RoleContract
    {
        return app($this->roleClass);
    }

    public function getGroupClass(): GroupContract
    {
        return app($this->groupClass);
    }

    public function getCacheStore(): \Illuminate\Contracts\Cache\Store
    {
        return $this->cache->getStore();
    }
}
