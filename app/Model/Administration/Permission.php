<?php

namespace App\Model\Administration;

use Illuminate\Database\Eloquent\Model;

use App\Contract\Permission\PermissionContract;

class Permission extends Model implements PermissionContract
{
    public function roles(): BelongsToMany
    {}
    public function groups(): BelongsToMany
    {}
    public static function findByName(string $name, $guardName): self
    {}
    public static function findById(int $id, $guardName): self
    {}
    public static function findOrCreate(string $name, $guardName): self
    {}
}
