{{-- After admin › Servers › Create was opened by its address (OpenServerMaker): Simplify opens Create server in its popup. --}}
<script>
    (() => {
        let opened = false;
        const open = () => {
            if (opened) return;
            opened = true;
            setTimeout(() => window.dispatchEvent(new CustomEvent('simplify:create')), 0);
        };
        document.addEventListener('alpine:initialized', open, { once: true });
        document.addEventListener('livewire:navigated', open, { once: true });
    })();
</script>
