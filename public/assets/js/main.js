/**
 * FORMGOOGLE - main.js
 * Formulir Pendaftaran Layanan CBN Interaktif
 * Termasuk Signature Pad Canvas, Package Selector, Pricing Calculator, & Geolocation
 */

document.addEventListener('DOMContentLoaded', () => {

    // ========================================================
    // 1. SIGNATURE PAD CANVAS
    // ========================================================
    const canvas = document.getElementById('signature-canvas');
    const signatureInput = document.getElementById('signature_data');
    const clearBtn = document.getElementById('btn-clear-sign');
    const signPlaceholder = document.getElementById('sign-placeholder');

    let ctx = null;
    let isDrawing = false;
    let hasDrawn = false;

    if (canvas) {
        ctx = canvas.getContext('2d');

        function resizeCanvas() {
            const rect = canvas.getBoundingClientRect();
            const ratio = window.devicePixelRatio || 1;
            canvas.width = rect.width * ratio;
            canvas.height = rect.height * ratio;
            ctx.scale(ratio, ratio);
            ctx.lineWidth = 2.5;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            ctx.strokeStyle = '#002b4d';
        }

        resizeCanvas();
        window.addEventListener('resize', resizeCanvas);

        function getPointerPos(e) {
            const rect = canvas.getBoundingClientRect();
            const clientX = e.touches ? e.touches[0].clientX : e.clientX;
            const clientY = e.touches ? e.touches[0].clientY : e.clientY;
            return {
                x: clientX - rect.left,
                y: clientY - rect.top
            };
        }

        function startDrawing(e) {
            isDrawing = true;
            hasDrawn = true;
            if (signPlaceholder) signPlaceholder.classList.add('hidden');
            const pos = getPointerPos(e);
            ctx.beginPath();
            ctx.moveTo(pos.x, pos.y);
            e.preventDefault();
        }

        function draw(e) {
            if (!isDrawing) return;
            const pos = getPointerPos(e);
            ctx.lineTo(pos.x, pos.y);
            ctx.stroke();
            e.preventDefault();
        }

        function stopDrawing() {
            if (isDrawing) {
                isDrawing = false;
                ctx.closePath();
                // Simpan base64 ke hidden input
                signatureInput.value = canvas.toDataURL('image/png');
            }
        }

        // Mouse Events
        canvas.addEventListener('mousedown', startDrawing);
        canvas.addEventListener('mousemove', draw);
        canvas.addEventListener('mouseup', stopDrawing);
        canvas.addEventListener('mouseleave', stopDrawing);

        // Touch Events (Mobile/Tablet)
        canvas.addEventListener('touchstart', startDrawing, { passive: false });
        canvas.addEventListener('touchmove', draw, { passive: false });
        canvas.addEventListener('touchend', stopDrawing);

        // Clear Button
        if (clearBtn) {
            clearBtn.addEventListener('click', () => {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                signatureInput.value = '';
                hasDrawn = false;
                if (signPlaceholder) signPlaceholder.classList.remove('hidden');
            });
        }
    }


    // ========================================================
    // 2. PACKAGE SELECTOR & PRICING CALCULATOR
    // ========================================================
    const packageCards = document.querySelectorAll('.package-card');
    const serviceInput = document.getElementById('service');
    const addonTvCheckboxes = document.querySelectorAll('.addon-tv-check');
    const smartboxQtyInput = document.getElementById('smartbox_qty');
    
    const displayPaket = document.getElementById('summary-biaya-paket');
    const displayAddon = document.getElementById('summary-biaya-addon');
    const displayPpn   = document.getElementById('summary-biaya-ppn');
    const displayTotal = document.getElementById('summary-biaya-total');

    const hiddenBiayaPasang = document.getElementById('biaya_pasang');
    const hiddenBiayaPaket  = document.getElementById('biaya_paket');
    const hiddenBiayaAddon  = document.getElementById('biaya_addon');
    const hiddenBiayaPpn    = document.getElementById('biaya_ppn');
    const hiddenBiayaTotal  = document.getElementById('biaya_total');

    const packagePrices = {
        'Fiber 50': 299000,
        'Fiber 100': 399000,
        'Fiber 200': 599000,
        'Fiber 250': 799000,
        'Fiber 1Gbps': 1499000,
        'Fiber PRO 100': 699000,
        'Fiber PRO 200': 999000
    };

    function formatRupiah(num) {
        return 'Rp ' + num.toLocaleString('id-ID');
    }

    function calculatePricing() {
        const selectedService = serviceInput.value || 'Fiber 50';
        const basePrice = packagePrices[selectedService] || 299000;

        let addonPrice = 0;
        addonTvCheckboxes.forEach(cb => {
            if (cb.checked) {
                if (cb.value.includes('Dens')) addonPrice += 30000;
                if (cb.value.includes('Vision')) addonPrice += 40000;
            }
        });

        if (smartboxQtyInput) {
            const qty = parseInt(smartboxQtyInput.value, 10) || 0;
            if (qty > 0) addonPrice += (qty * 35000);
        }

        const subtotal = basePrice + addonPrice;
        const ppn = Math.round(subtotal * 0.11);
        const total = subtotal + ppn;

        // Update displays
        if (displayPaket) displayPaket.textContent = formatRupiah(basePrice);
        if (displayAddon) displayAddon.textContent = formatRupiah(addonPrice);
        if (displayPpn)   displayPpn.textContent   = formatRupiah(ppn);
        if (displayTotal) displayTotal.textContent = formatRupiah(total);

        // Update hidden inputs for backend
        if (hiddenBiayaPaket) hiddenBiayaPaket.value = formatRupiah(basePrice);
        if (hiddenBiayaAddon) hiddenBiayaAddon.value = formatRupiah(addonPrice);
        if (hiddenBiayaPpn)   hiddenBiayaPpn.value   = formatRupiah(ppn);
        if (hiddenBiayaTotal) hiddenBiayaTotal.value = formatRupiah(total);
    }

    packageCards.forEach(card => {
        card.addEventListener('click', () => {
            packageCards.forEach(c => c.classList.remove('selected'));
            card.classList.add('selected');
            const pkgName = card.getAttribute('data-package');
            serviceInput.value = pkgName;
            calculatePricing();
        });
    });

    addonTvCheckboxes.forEach(cb => cb.addEventListener('change', calculatePricing));
    if (smartboxQtyInput) smartboxQtyInput.addEventListener('input', calculatePricing);

    // Initial pricing run
    calculatePricing();


    // ========================================================
    // 3. KTP 16-DIGIT COUNTER & VALIDATION
    // ========================================================
    const ktpInput = document.getElementById('nomor_ktp');
    const ktpCount = document.getElementById('ktp-count');

    if (ktpInput && ktpCount) {
        ktpInput.addEventListener('input', () => {
            ktpInput.value = ktpInput.value.replace(/\D/g, '').slice(0, 16);
            const len = ktpInput.value.length;
            ktpCount.textContent = `${len}/16`;
            if (len === 16) {
                ktpCount.style.color = '#10b981';
            } else {
                ktpCount.style.color = '#f59e0b';
            }
        });
    }


    // ========================================================
    // 4. GPS GEOLOCATION HELPER
    // ========================================================
    const gpsBtn = document.getElementById('tikor-gps-btn');
    const tikorInput = document.getElementById('tikor');

    if (gpsBtn && tikorInput) {
        gpsBtn.addEventListener('click', () => {
            if (!navigator.geolocation) {
                alert('Browser Anda tidak mendukung Geolocation.');
                return;
            }
            gpsBtn.textContent = '⏳ Mencari...';
            gpsBtn.disabled = true;

            navigator.geolocation.getCurrentPosition(
                pos => {
                    const lat = pos.coords.latitude.toFixed(6);
                    const lng = pos.coords.longitude.toFixed(6);
                    tikorInput.value = `${lat}, ${lng}`;
                    gpsBtn.textContent = '✅ Berhasil';
                    setTimeout(() => {
                        gpsBtn.textContent = '📍 GPS';
                        gpsBtn.disabled = false;
                    }, 2500);
                },
                err => {
                    alert('Gagal mengambil lokasi GPS: ' + err.message);
                    gpsBtn.textContent = '📍 GPS';
                    gpsBtn.disabled = false;
                },
                { enableHighAccuracy: true, timeout: 10000 }
            );
        });
    }


    // ========================================================
    // 5. FILE UPLOAD DRAG & DROP + PREVIEW
    // ========================================================
    const fileInput = document.getElementById('sales_order_file');
    const filePreview = document.getElementById('file-preview');
    const fileName = document.getElementById('file-name');
    const fileRemove = document.getElementById('file-remove');

    if (fileInput && filePreview && fileName) {
        fileInput.addEventListener('change', () => {
            if (fileInput.files.length > 0) {
                const file = fileInput.files[0];
                fileName.textContent = file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';
                filePreview.classList.add('show');
            }
        });

        if (fileRemove) {
            fileRemove.addEventListener('click', () => {
                fileInput.value = '';
                filePreview.classList.remove('show');
            });
        }
    }


    // ========================================================
    // 6. FORM SUBMISSION HANDLER
    // ========================================================
    const form = document.getElementById('cbn-form');
    const submitBtn = document.getElementById('submit-btn');

    if (form && submitBtn) {
        form.addEventListener('submit', (e) => {
            // Update signature base64 data if drawn
            if (canvas && hasDrawn) {
                signatureInput.value = canvas.toDataURL('image/png');
            }

            // Show loading state
            submitBtn.classList.add('loading');
            submitBtn.disabled = true;
        });
    }

});
