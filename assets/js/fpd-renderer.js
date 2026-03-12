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
        
        let layersData = [];
        try {
            layersData = JSON.parse(canvas.dataset.layers || '[]');
        } catch(e) {
            console.error('Failed to parse FPD layers', e);
        }
        
        const designSrc = canvas.dataset.design;
        
        const box = {
            x: parseFloat(canvas.dataset.boxX),
            y: parseFloat(canvas.dataset.boxY),
            w: parseFloat(canvas.dataset.boxW),
            h: parseFloat(canvas.dataset.boxH),
            z: parseFloat(canvas.dataset.boxZ)
        };

        let renderQueue = [];

        layersData.forEach(layer => {
            renderQueue.push({
                type: 'base',
                src: layer.source,
                params: layer.params,
                z: layer.z
            });
        });

        if (designSrc) {
            renderQueue.push({
                type: 'design',
                src: designSrc,
                z: box.z
            });
        }

        renderQueue.sort((a, b) => a.z - b.z);

        if (renderQueue.length === 0) {
            drawAll();
            return;
        }

        let loadedImages = 0;
        renderQueue.forEach(item => {
            const img = new Image();
            img.crossOrigin = "Anonymous";
            img.src = item.src;
            img.onload = () => {
                item.img = img;
                loadedImages++;
                if (loadedImages === renderQueue.length) drawAll();
            };
            img.onerror = () => {
                console.warn('Failed to load image:', item.src);
                loadedImages++;
                if (loadedImages === renderQueue.length) drawAll();
            };
        });

        function drawAll() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            
            renderQueue.forEach(item => {
                if (!item.img) return;

                if (item.type === 'design') {
                    const scale = box.w / item.img.width;
                    const drawWidth = box.w;
                    const drawHeight = item.img.height * scale;
                    
                    const drawX = box.x; 
                    const drawY = box.y; 

                    ctx.save();
                    ctx.beginPath();
                    ctx.rect(box.x, box.y, box.w, box.h);
                    ctx.clip();
                    ctx.drawImage(item.img, drawX, drawY, drawWidth, drawHeight);
                    ctx.restore();
                } else {
                    const p = item.params || {};
                    const left = p.left !== undefined ? p.left : 0;
                    const top = p.top !== undefined ? p.top : 0;
                    const scaleX = p.scaleX !== undefined ? p.scaleX : 1;
                    const scaleY = p.scaleY !== undefined ? p.scaleY : 1;
                    const angle = p.angle || 0;
                    const originX = p.originX || 'left';
                    const originY = p.originY || 'top';
                    
                    const width = (p.width !== undefined ? p.width : item.img.width) * scaleX;
                    const height = (p.height !== undefined ? p.height : item.img.height) * scaleY;

                    ctx.save();
                    
                    if (p.blendMode) {
                        ctx.globalCompositeOperation = p.blendMode;
                    }

                    let dx = left;
                    let dy = top;
                    
                    if (originX === 'center') dx -= width / 2;
                    if (originY === 'center') dy -= height / 2;

                    if (angle) {
                        ctx.translate(left, top);
                        ctx.rotate(angle * Math.PI / 180);
                        ctx.translate(-left, -top);
                    }

                    ctx.drawImage(item.img, dx, dy, width, height);
                    ctx.restore();
                }
            });
        }
    }
});
