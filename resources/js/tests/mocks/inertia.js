import { reactive } from 'vue';
import { vi } from 'vitest';

const createFormState = (initialValues = {}) => {
    const state = reactive({
        ...initialValues,
        processing: false,
        errors: {},
    });

    const applyDefaults = (defaults = {}) => {
        Object.entries(defaults).forEach(([key, value]) => {
            state[key] = Array.isArray(value) ? [...value] : value;
        });
    };

    state.post = vi.fn((_url, options = {}) => {
        options.onSuccess?.();
    });
    state.put = vi.fn((_url, options = {}) => {
        options.onSuccess?.();
    });
    state.patch = vi.fn((_url, options = {}) => {
        options.onSuccess?.();
    });
    state.reset = vi.fn(() => {
        applyDefaults(initialValues);
    });
    state.clearErrors = vi.fn(() => {
        state.errors = {};
    });
    state.defaults = vi.fn((defaults = {}) => {
        initialValues = {
            ...initialValues,
            ...defaults,
        };

        applyDefaults(initialValues);
    });

    return state;
};

let currentPage = reactive({
    url: '/dashboard',
    props: {
        flash: {},
    },
});

let lastForm = null;
let forms = [];

export const router = {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
};

export const resetInertiaMocks = () => {
    router.get.mockReset();
    router.post.mockReset();
    router.put.mockReset();
    router.delete.mockReset();

    currentPage = reactive({
        url: '/dashboard',
        props: {
            flash: {},
        },
    });

    lastForm = null;
    forms = [];
};

export const setPageProps = (props) => {
    currentPage.props = props;
};

export const setPageUrl = (url) => {
    currentPage.url = url;
};

export const getPage = () => currentPage;

export const getLastForm = () => lastForm;
export const getForms = () => forms;

export const createMockForm = (initialValues = {}) => {
    lastForm = createFormState(initialValues);
    forms.push(lastForm);

    return lastForm;
};
