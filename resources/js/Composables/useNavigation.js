import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

export function useNavigation() {
    const page = usePage();

    return {
        items: computed(() => page.props.navigation ?? []),
        user: computed(() => page.props.auth?.user ?? null),
    };
}
