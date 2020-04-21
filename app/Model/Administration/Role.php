<?php

namespace App\Model\Administration;

use Illuminate\Database\Eloquent\Model;

use App\Contract\Permission\RoleContract;

class Role extends Model implements RoleContract
{
    public function permissions(): BelongsToMany
    {}
    public static function findByName(string $name, $guardName): self
    {}
    public static function findById(int $id, $guardName): self
    {}
    public static function findOrCreate(string $name, $guardName): self
    {}
}
