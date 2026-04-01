import { vi } from 'vitest';

export const axiosDelete = vi.fn(async () => ({
    data: {
        message: 'Operation completed successfully.',
        data: {},
        meta: {},
        errors: {},
    },
}));

export const axiosPost = vi.fn(async () => ({
    data: {
        message: 'Operation completed successfully.',
        data: {},
        meta: {},
        errors: {},
    },
}));

export const axiosPut = vi.fn(async () => ({
    data: {
        message: 'Operation completed successfully.',
        data: {},
        meta: {},
        errors: {},
    },
}));

export const resetAxiosMocks = () => {
    axiosDelete.mockReset();
    axiosPost.mockReset();
    axiosPut.mockReset();
    axiosDelete.mockResolvedValue({
        data: {
            message: 'Operation completed successfully.',
            data: {},
            meta: {},
            errors: {},
        },
    });
    axiosPost.mockResolvedValue({
        data: {
            message: 'Operation completed successfully.',
            data: {},
            meta: {},
            errors: {},
        },
    });
    axiosPut.mockResolvedValue({
        data: {
            message: 'Operation completed successfully.',
            data: {},
            meta: {},
            errors: {},
        },
    });
};
