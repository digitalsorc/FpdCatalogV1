document.addEventListener('DOMContentLoaded', () => {
    
    // Intersection Observer for Lazy Rendering
    const observer = new IntersectionObserver((entries, obs) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                renderFPDCanvas(entry.target);
                obs.unobserve(entry.target); // Only render once
            }
        });
    }, { rootMargin: '200px' });

    document.querySelectorAll('.fpd-render-canvas').forEach(canvas => {
        observer.observe(canvas);
    });

    // Handle AJAX filtering (FacetWP, WP Grid Builder)
    document.addEventListener('facetwp-loaded', reinitCanvases);
    document.addEventListener('wpgb.loaded', reinitCanvases);
    if (typeof jQuery !== 'undefined') {
        jQuery(document).on('ajaxComplete', function(event, xhr, settings) {
            // Catch generic Elementor/WooCommerce AJAX pagination
            reinitCanvases();
        });
    }

    function reinitCanvases() {
        document.querySelectorAll('.fpd-render-canvas:not(.rendered)').forEach(canvas => {
            observer.observe(canvas);
        });
    }

    function renderFPDCanvas(canvas) {
        canvas.classList.add('rendered');
        const ctx = canvas.getContext('2d');
        
        const baseSrc = canvas.dataset.base;
        const designSrc = canvas.dataset.design;
        
        const box = {
            x: parseFloat(canvas.dataset.boxX),
            y: parseFloat(canvas.dataset.boxY),
            w: parseFloat(canvas.dataset.boxW),
            h: parseFloat(canvas.dataset.boxH)
        };

        // Load Base Image
        const baseImg = new Image();
        baseImg.crossOrigin = "Anonymous";
        baseImg.src = baseSrc;
        baseImg.onload = () => {
            // Draw Base
            ctx.drawImage(baseImg, 0, 0, canvas.width, canvas.height);
            
            // Load Design Image
            if (designSrc) {
                const designImg = new Image();
                designImg.crossOrigin = "Anonymous";
                designImg.src = designSrc;
                designImg.onload = () => {
                    // Logic: Scale to max width of printing box, center X, align top Y
                    const scale = box.w / designImg.width;
                    const drawWidth = box.w;
                    const drawHeight = designImg.height * scale;
                    
                    const drawX = box.x; // Already centered since width matches box width
                    const drawY = box.y; // Align to top

                    // Optional: Clip to bounding box if design is taller than box
                    ctx.save();
                    ctx.beginPath();
                    ctx.rect(box.x, box.y, box.w, box.h);
                    ctx.clip();

                    ctx.drawImage(designImg, drawX, drawY, drawWidth, drawHeight);
                    ctx.restore();
                };
            }
        };
    }
});
