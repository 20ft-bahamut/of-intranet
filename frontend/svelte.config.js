import adapterStatic from '@sveltejs/adapter-static';

export default {
    kit: {
        adapter: adapterStatic({
            fallback: 'index.html' // SPA fallback
        }),
        prerender: {
            handleHttpError: 'warn'
        }
    }
};
