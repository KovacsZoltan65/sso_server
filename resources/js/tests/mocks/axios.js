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

export const axiosGet = vi.fn(async () => ({
    data: {
        message: 'Operation completed successfully.',
        data: {},
        meta: {},
        errors: {},
    },
}));

export const resetAxiosMocks = () => {
    axiosGet.mockReset();
    axiosDelete.mockReset();
    axiosPost.mockReset();
    axiosPut.mockReset();
    axiosGet.mockResolvedValue({
        data: {
            message: 'Operation completed successfully.',
            data: {},
            meta: {},
            errors: {},
        },
    });
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
