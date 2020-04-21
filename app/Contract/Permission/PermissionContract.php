<?php

namespace App\Contract\Permission;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

interface PermissionContract
{
    public function roles(): BelongsToMany;
    public function groups(): BelongsToMany;
    public static function findByName(string $name, $guardName): self;
    public static function findById(int $id, $guardName): self;
    public static function findOrCreate(string $name, $guardName): self;
}
