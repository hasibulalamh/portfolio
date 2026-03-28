import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

function setupSmoothScroll() {
    document.querySelectorAll("a[href^='#']").forEach(anchor => {
        // Only add listener if not already added
        if (anchor.dataset.smoothScrollAttached) return;
        anchor.dataset.smoothScrollAttached = 'true';

        anchor.addEventListener("click", function (e) {
            const href = this.getAttribute("href");
            if (href === "#" || !href) return;
            try {
                const target = document.querySelector(href);
                if (!target) return;
                e.preventDefault();
                const offset = 80;
                const targetPosition = target.getBoundingClientRect().top + window.scrollY - offset;
                window.scrollTo({ top: targetPosition, behavior: "smooth" });
            } catch (error) {
                console.error("Invalid href selector:", href);
            }
        });
    });
}

function setupFadeInOnScroll() {
    const sections = document.querySelectorAll('section');

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
            }
        });
    }, { threshold: 0.1 });

    sections.forEach(section => {
        section.classList.add('fade-in-section');
        observer.observe(section);
    });
}

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
        setupSmoothScroll();
        setupFadeInOnScroll();

        return app;
    },
    progress: {
        color: '#4B5563',
    },
});
