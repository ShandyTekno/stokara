import { ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

export const DEFAULT_THEME = {
    theme_color: 'blue',
    sidebar_style: 'dark',
    font_family: 'figtree',
    font_size: 'md',
    border_radius: 'rounded',
    compact_mode: false,
};

export const activeTheme = ref({ ...DEFAULT_THEME });

export function applyTheme(settings = {}) {
    const merged = { ...DEFAULT_THEME, ...(settings || {}) };
    activeTheme.value = merged;

    if (typeof document === 'undefined') {
        return;
    }

    const root = document.documentElement;
    root.setAttribute('data-theme', merged.theme_color || 'blue');
    root.setAttribute('data-sidebar', merged.sidebar_style || 'dark');
    root.setAttribute('data-font', merged.font_family || 'figtree');
    root.setAttribute('data-font-size', merged.font_size || 'md');
    root.setAttribute('data-radius', merged.border_radius || 'rounded');
    root.setAttribute('data-compact', merged.compact_mode ? 'true' : 'false');
}

export function useTheme() {
    const page = usePage();

    watch(
        () => page.props.site?.settings,
        (settings) => {
            applyTheme(settings);
        },
        { immediate: true, deep: true },
    );

    return { activeTheme, applyTheme };
}
