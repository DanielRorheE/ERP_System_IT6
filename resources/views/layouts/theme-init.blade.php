<script>
    (() => {
        let theme = 'light';

        try {
            const preference = localStorage.getItem('out-of-boredom-theme') ?? localStorage.getItem('northstar-theme');

            theme = preference === 'dark' || preference === 'light'
                ? preference
                : (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        } catch {
            theme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }

        document.documentElement.dataset.theme = theme;
        document.documentElement.classList.toggle('dark', theme === 'dark');
    })();
</script>
