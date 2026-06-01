<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\PermissionRegistrar;

/**
 * Laravel 13 kompatibilitási kiegészítés a Spatie Permission csomaghoz.
 *
 * A PermissionRegistrar felelős a jogosultságok és szerepkörök közötti
 * kapcsolatok gyorsítótárazásáért és betöltéséért. Ez az osztály felülírja
 * a jogosultságok lekérdezésének módját annak érdekében, hogy a szerepkör
 * kapcsolatok már a cache építésekor eager loadinggal betöltődjenek.
 *
 * Ezzel elkerülhető a későbbi N+1 lekérdezés és biztosítható, hogy a
 * jogosultsági rendszer minden szükséges kapcsolatot előre ismerjen.
 */
class Laravel13PermissionRegistrar extends PermissionRegistrar
{
    /**
     * A jogosultságokat a hozzájuk rendelt szerepkörökkel együtt tölti be.
     *
     * A PermissionRegistrar belső működése több helyen feltételezi,
     * hogy a permission-role kapcsolatok azonnal rendelkezésre állnak.
     * Az eager loading csökkenti az adatbázis terhelését és stabilizálja
     * a jogosultsági cache felépítését nagyobb szerepkörstruktúrák esetén.
     *
     * @return Collection<int, \Spatie\Permission\Models\Permission>
     */
    protected function getPermissionsWithRoles(): Collection
    {
        return $this->permissionClass::select()
            ->with(['roles'])
            ->get();
    }
}