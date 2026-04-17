import { trans } from "laravel-vue-i18n";

export const adminRowsPerPageOptions = [5, 10, 15, 25];

export const adminPaginatorTemplate =
    "RowsPerPageDropdown FirstPageLink PrevPageLink CurrentPageReport NextPageLink LastPageLink";

export const adminCurrentPageReportTemplate = trans("pagination_report");
