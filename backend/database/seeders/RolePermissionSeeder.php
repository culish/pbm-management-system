<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $matrix = json_decode(file_get_contents(database_path('data/permissions.json')), true, flags: JSON_THROW_ON_ERROR);

        foreach ($matrix['permissions'] as $key => $label) {
            Permission::updateOrCreate(['key' => $key], ['label' => $label]);
        }

        $allIds = Permission::pluck('id', 'key');

        foreach ($matrix['roles'] as $key => $definition) {
            $role = Role::updateOrCreate(['key' => $key], ['label' => $definition['label']]);

            // "*" berarti semua izin, dikurangi yang tercantum di "except".
            $ids = in_array('*', $definition['permissions'], true)
                ? $allIds->except($definition['except'] ?? [])->values()->all()
                : collect($definition['permissions'])->map(fn ($p) => $allIds[$p])->all();

            $role->permissions()->sync($ids);
        }
    }
}
