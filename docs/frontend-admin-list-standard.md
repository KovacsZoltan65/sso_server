# Frontend Admin List Standard

## 1. Bevezető

Az admin list design system celja, hogy az admin tablazatos oldalak ugyanarra a PrimeVue-alapu szerzodesre epuljenek. Ez a standard a leggyakoribb UX es karbantarthatosagi problemakat fogja ossze egy kozos mintaba:

- paginator boilerplate
- loading es empty state kovetkezetlenseg
- toolbar ujraepitese oldalankent
- summary logika duplikalasa
- selection es bulk UX szetszorasa

Ez a standard **nem** egy framework es **nem** egy univerzalis grid motor. Nem akar minden listaoldalt config-driven objektumokkal generalni. A cel a tiszta, PrimeVue-kompatibilis kozos szerzodes.

## 2. Kotelezo epitoelemek

Minden uj vagy atdolgozott admin listaoldal kotelezoen hasznalja:

- `BaseDataTable`
- `useAdminTableState()`
- `AdminTableToolbar`
- `AdminTableSummary`

Ha az oldalon van bulk muvelet vagy checkbox selection, kotelezo:

- `useAdminTableSelection()`

## 3. Listaoldal szerkezet (canonical pattern)

```vue
<script setup>
import BaseDataTable from "@/Components/Admin/BaseDataTable.vue";
import AdminTableToolbar from "@/Components/Admin/AdminTableToolbar.vue";
import AdminTableSummary from "@/Components/Admin/AdminTableSummary.vue";
import { useAdminTableState } from "@/Composables/useAdminTableState";
import { useAdminTableSelection } from "@/Composables/useAdminTableSelection";

const rows = ref([]);
const loading = ref(false);

const {
    state: tableState,
    filters,
    first,
    lastPage,
    resetPagination,
    setPageFromEvent,
    setSortFromEvent,
    applyMeta,
    buildFetchParams,
} = useAdminTableState({
    initialSortField: "created_at",
    initialSortOrder: -1,
    initialFilters: {
        search: "",
    },
});

const {
    selectedIds,
    selectedCount,
    selectableCount,
    allSelected,
    partiallySelected,
    clearSelection,
    toggleRowSelection,
    toggleAllSelection,
} = useAdminTableSelection(computed(() => rows.value));

async function loadRows() {
    const envelope = await fetchRows(buildFetchParams({
        filters: {
            search: filters.search || undefined,
        },
    }));

    rows.value = envelope.data.items ?? [];
    applyMeta(envelope.meta.pagination ?? {});
}

function onPage(event) {
    setPageFromEvent(event);
    loadRows();
}

function onSort(event) {
    setSortFromEvent(event, "created_at");
    loadRows();
}
</script>

<template>
    <AdminTableToolbar />

    <BaseDataTable>
        <!-- columns -->
    </BaseDataTable>

    <AdminTableSummary />
</template>
```

Felelossegek:

- `BaseDataTable`: vizualis es ergonomiai table shell
- `useAdminTableState()`: pagination, sort es filter state
- `AdminTableToolbar`: search, filter, action es bulk zona szerkezete
- `AdminTableSummary`: footer summary fallbackokkal
- `useAdminTableSelection()`: page-local selection es bulk UX state

Nem ide tartozik:

- backend business rule
- endpoint-specifikus validation
- modal workflow business logika
- oldal-specifikus delete policy

## 4. useAdminTableState contract

`useAdminTableState()` kezeli a kozos admin list state-et:

- `page`
- `perPage`
- `sortField`
- `sortOrder`
- `filters`
- `first`
- `lastPage`
- `totalRecords`

Fo helper-ek:

- `resetPagination()`
- `setPageFromEvent(event)`
- `setSortFromEvent(event, fallbackField)`
- `applyMeta(meta)`
- `buildFetchParams({ filters, extra })`

Hasznalat:

- a fetch parameterek mindig `buildFetchParams()`-on keresztul menjenek
- backend pagination meta mindig `applyMeta()`-val frissitse a state-et
- search/filter valtozasnal `resetPagination()` utan ujratoltes

Nem szabad beletenni:

- modal visibility state
- row action menu state
- bulk confirm flow
- endpoint-specifikus hibakezeles

## 5. Selection + bulk contract

`useAdminTableSelection()` csak akkor hasznalando, ha az oldalon valodi checkbox selection es backend bulk endpoint is van.

Tudatos dontes:

- a selection **page-local**
- nem globalis, nem multi-page selection

Fo derived state:

- `selectedRows`
- `selectedIds`
- `selectedCount`
- `selectableCount`
- `hasSelection`
- `allSelected`
- `partiallySelected`

`isRowSelectable(row)` felel az UX-szintu eloszuresert. Ez nem valtja ki a backend policy-t vagy validationt.

`autoPrune` viselkedes:

- ha a lista ujratoltodik vagy valtozik a sorhalmaz
- a mar nem letezo vagy mar nem kijelolheto sorok kikerulnek a selectionbol

Kotelezo szabalyok:

- `page` valtas -> selection reset
- `filter` valtas -> selection reset
- `sort` valtas -> selection reset
- `refresh` -> selection reset
- sikeres bulk muvelet -> selection reset
- nem kijelolheto sor -> disabled checkbox

## 6. Bulk UX szabalyok

Ha van bulk muvelet:

- disabled state kotelezo
- selected count megjelenites kotelezo
- confirm flow kotelezo
- success utan kotelezo:
  - toast
  - refresh
  - selection reset

Frontend bulk action nem jelenhet meg backend bulk endpoint nelkul.

## 7. Toolbar szabalyok

`AdminTableToolbar` a canonical toolbar szerkezet:

- standard search mezo
- `filters` slot extra filterelemekhez
- `actions` slot masodlagos actionokhoz
- `primary` slot kiemelt actionhoz
- `bulk` slot oldal-specifikus bulk kiterjeszteshez

A keresomezo nem epulhet ujra oldalankent sajat `IconField` boilerplate-bol, ha a standard toolbar mar eleg.

## 8. Summary szabaly

`AdminTableSummary` alapertelmezetten:

- `Showing X-Y of Z`
- empty fallback
- meta hiany eseten safe fallback

Az oldal nem tarthat sajat summary szamolast, ha az `AdminTableSummary` eleg.

## 9. Mikor NEM hasznaljuk a bulk contractot

Ne hasznald a bulk contractot:

- audit log oldalon
- read-only listakon
- ahol nincs backend bulk endpoint
- ahol a domain szabaly nem tamogat bulk muveletet

## 10. Anti-pattern lista

❌ Ne legyen:

- sajat DataTable wrapper oldalankent
- sajat pagination state
- sajat selection ref-ek
- toolbar ujraepitese oldalankent
- bulk action backend nelkul
- summary szamolas kezileg, ha a standard eleg

## 11. Checklist

- [ ] `BaseDataTable` hasznalva
- [ ] `useAdminTableState()` hasznalva
- [ ] `AdminTableToolbar` hasznalva
- [ ] `AdminTableSummary` hasznalva
- [ ] `useAdminTableSelection()` hasznalva, ha van bulk
- [ ] nincs duplikalt state logika
- [ ] nincs custom paginator logika
- [ ] search / filter / sort a standard contractot koveti
