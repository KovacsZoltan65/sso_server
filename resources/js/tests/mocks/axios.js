import { vi } from 'vitest';

export const axiosDelete = vi.fn(async () => ({
    data: {
        message: 'Operation completed successfully.',
        data: {},
        meta: {},
        errors: {},
    },
}));

export const resetAxiosMocks = () => {
    axiosDelete.mockReset();
    axiosDelete.mockResolvedValue({
        data: {
            message: 'Operation completed successfully.',
            data: {},
            meta: {},
            errors: {},
        },
    });
};
