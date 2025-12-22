// Simple lazy-loading enhancer: add loading="lazy" to images without blocking
(function(){
    if (!('loading' in HTMLImageElement.prototype)) {
        // For very old browsers, we skip auto-attribute to avoid breaking behavior
        return;
    }

    function addLazy() {
        var imgs = document.querySelectorAll('img');
        imgs.forEach(function(img){
            // Only add if not already set and not explicitly eager
            if (!img.hasAttribute('loading') && img.getAttribute('data-no-lazy') !== '1') {
                img.setAttribute('loading', 'lazy');
            }
        });
    }

    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        addLazy();
    } else {
        document.addEventListener('DOMContentLoaded', addLazy);
    }
})();
