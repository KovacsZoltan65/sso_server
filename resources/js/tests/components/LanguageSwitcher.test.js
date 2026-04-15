import { flushPromises, mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { loadLanguageAsync } from 'laravel-vue-i18n';
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue';
import { setPageProps } from '../mocks/inertia';
import { axiosPost } from '../mocks/axios';

describe('LanguageSwitcher', () => {
    it('switches locale instantly and persists selection without full page reload', async () => {
        setPageProps({
            locale: {
                current: 'hu',
                fallback: 'en',
                available: ['hu', 'en'],
            },
            flash: {},
        });

        const wrapper = mount(LanguageSwitcher);

        const enButton = wrapper.findAll('button').find((button) => button.text().trim() === 'EN');
        expect(enButton).toBeDefined();
        await enButton.trigger('click');
        await flushPromises();

        expect(loadLanguageAsync).toHaveBeenCalledWith('en');
        expect(axiosPost).toHaveBeenCalledTimes(1);

        const [url, payload, options] = axiosPost.mock.calls[0];

        expect(typeof url).toBe('string');
        expect(payload).toEqual({ locale: 'en' });
        expect(options.headers.Accept).toBe('application/json');
    });
});
