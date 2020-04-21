<?php

namespace App\Contract\Permission;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

interface RoleContract
{
    public function permissions(): BelongsToMany;
    public static function findByName(string $name, $guardName): self;
    public static function findById(int $id, $guardName): self;
    public static function findOrCreate(string $name, $guardName): self;
}
