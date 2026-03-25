import { vi } from 'vitest';

export const toastAdd = vi.fn();
export const confirmRequire = vi.fn();
export const confirmClose = vi.fn();

export const resetPrimeVueMocks = () => {
    toastAdd.mockReset();
    confirmRequire.mockReset();
    confirmClose.mockReset();
};
