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
            if (!rect.width || !rect.height) return;
            const ratio = window.devicePixelRatio || 1;
            
            // Save existing drawing before resizing
            let tempCanvas = null;
            if (hasDrawn && canvas.width > 0 && canvas.height > 0) {
                tempCanvas = document.createElement('canvas');
                tempCanvas.width = canvas.width;
                tempCanvas.height = canvas.height;
                const tempCtx = tempCanvas.getContext('2d');
                tempCtx.drawImage(canvas, 0, 0);
            }

            canvas.width = rect.width * ratio;
            canvas.height = rect.height * ratio;
            ctx.scale(ratio, ratio);
            ctx.lineWidth = 2.5;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            ctx.strokeStyle = '#002b4d';

            // Restore drawing
            if (tempCanvas) {
                ctx.drawImage(tempCanvas, 0, 0, rect.width, rect.height);
            }
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
    
    const displayPaket    = document.getElementById('summary-biaya-paket');
    const displayTambahan = document.getElementById('summary-biaya-tambahan');
    const displayAddon    = document.getElementById('summary-biaya-addon');
    const displayPpn      = document.getElementById('summary-biaya-ppn');
    const displayTotal    = document.getElementById('summary-biaya-total');

    const hiddenBiayaPasang    = document.getElementById('biaya_pasang');
    const hiddenBiayaPaket     = document.getElementById('biaya_paket');
    const hiddenBiayaTambahan  = document.getElementById('biaya_tambahan');
    const hiddenBiayaAddon     = document.getElementById('biaya_addon');
    const hiddenBiayaPpn       = document.getElementById('biaya_ppn');
    const hiddenBiayaTotal     = document.getElementById('biaya_total');
    const hiddenAddonCbnPkg    = document.getElementById('addon_cbn_package');

    // === Package config from admin (injected by PHP as window.CBN_PACKAGES) ===
    const PKG_CONFIG = window.CBN_PACKAGES || {};

    // Build lookup maps from the admin-managed config
    function getPkgValue(pkgName, key, fallback) {
        return (PKG_CONFIG[pkgName] && PKG_CONFIG[pkgName][key] !== undefined)
            ? PKG_CONFIG[pkgName][key] : fallback;
    }

    function formatRupiah(num) {
        return 'Rp ' + num.toLocaleString('id-ID');
    }

    function updateCbnPackageDisplay(service, cbnPackages) {
        const container = document.getElementById('cbn-package-list');
        if (!container) return;
        if (!cbnPackages || cbnPackages.length === 0) {
            container.innerHTML = '<span style="color:#94a3b8;font-style:italic;font-size:12px;">Tidak ada paket CBN otomatis untuk paket ini.</span>';
            return;
        }
        container.innerHTML = cbnPackages.map(pkg =>
            `<div style="display:flex;align-items:flex-start;gap:6px;margin-bottom:4px;">
               <span style="color:#10b981;font-weight:900;font-size:13px;flex-shrink:0;margin-top:1px;">&#10003;</span>
               <span style="font-size:12.5px;color:#e2e8f0;font-weight:600;line-height:1.4;">${pkg}</span>
             </div>`
        ).join('');
    }

    function calculatePricing() {
        const selectedService = serviceInput ? serviceInput.value || '' : '';
        const basePrice       = getPkgValue(selectedService, 'price', 169000);
        const biayaTambahan   = getPkgValue(selectedService, 'biaya_tambahan', 5000);

        let addonPrice = 0;
        addonTvCheckboxes.forEach(cb => {
            if (cb.checked) {
                if (cb.value.includes('Dens'))   addonPrice += 30000;
                if (cb.value.includes('Vision')) addonPrice += 40000;
            }
        });

        if (smartboxQtyInput) {
            const qty = parseInt(smartboxQtyInput.value, 10) || 0;
            if (qty > 0) addonPrice += (qty * 35000);
        }

        const ppnRate  = (typeof window.PPN_PERCENT !== 'undefined' ? parseFloat(window.PPN_PERCENT) : 11) / 100;
        const subtotal = basePrice + biayaTambahan + addonPrice;
        const ppn      = Math.round(subtotal * ppnRate);
        const total    = subtotal + ppn;

        // Update PPN label in summary if present
        const ppnLabel = document.getElementById('summary-ppn-label');
        if (ppnLabel && typeof window.PPN_PERCENT !== 'undefined') {
            ppnLabel.textContent = `PPN ${window.PPN_PERCENT}%`;
        }

        // Update displays
        if (displayPaket)    displayPaket.textContent    = formatRupiah(basePrice);
        if (displayTambahan) displayTambahan.textContent = formatRupiah(biayaTambahan);
        if (displayAddon)    displayAddon.textContent    = formatRupiah(addonPrice);
        if (displayPpn)      displayPpn.textContent      = formatRupiah(ppn);
        if (displayTotal)    displayTotal.textContent    = formatRupiah(total);

        // Update hidden inputs for backend
        if (hiddenBiayaPasang)   hiddenBiayaPasang.value   = 'Rp 0';
        if (hiddenBiayaPaket)    hiddenBiayaPaket.value    = formatRupiah(basePrice);
        if (hiddenBiayaTambahan) hiddenBiayaTambahan.value = formatRupiah(biayaTambahan);
        if (hiddenBiayaAddon)    hiddenBiayaAddon.value    = formatRupiah(addonPrice);
        if (hiddenBiayaPpn)      hiddenBiayaPpn.value      = formatRupiah(ppn);
        if (hiddenBiayaTotal)    hiddenBiayaTotal.value    = formatRupiah(total);

        // Update CBN package add-on info (from admin config)
        const cbnPackages = getPkgValue(selectedService, 'cbn_package', []);
        if (hiddenAddonCbnPkg) hiddenAddonCbnPkg.value = JSON.stringify(cbnPackages);
        updateCbnPackageDisplay(selectedService, cbnPackages);
    }

    packageCards.forEach(card => {
        card.addEventListener('click', () => {
            packageCards.forEach(c => c.classList.remove('selected'));
            card.classList.add('selected');
            const pkgName = card.getAttribute('data-package');
            if (serviceInput) serviceInput.value = pkgName;
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
    // 4. GPS GEOLOCATION & INTERACTIVE MAP PICKER (LEAFLET)
    // ========================================================
    const gpsBtn        = document.getElementById('tikor-gps-btn');
    const mapBtn        = document.getElementById('tikor-map-btn');
    const tikorInput    = document.getElementById('tikor');
    const mapModal      = document.getElementById('map-modal');
    const closeMapBtn   = document.getElementById('close-map-btn');
    const useCoordsBtn  = document.getElementById('use-map-coords-btn');
    const coordsDisplay = document.getElementById('map-selected-coords');

    let leafletMap      = null;
    let leafletMarker   = null;
    let currentMapLat   = 3.595196;
    let currentMapLng   = 98.672223; // Default Medan

    // ---- A. DETEKSI GPS OTOMATIS (SMART MOBILE FALLBACK) ----
    if (gpsBtn && tikorInput) {
        gpsBtn.addEventListener('click', () => {
            if (!navigator.geolocation) {
                alert('Browser Anda tidak mendukung deteksi lokasi otomatis. Silakan gunakan tombol "Pilih di Peta".');
                return;
            }

            gpsBtn.textContent = '⏳ Mencari GPS...';
            gpsBtn.disabled = true;

            const applyCoords = (lat, lng) => {
                const latFormatted = parseFloat(lat).toFixed(6);
                const lngFormatted = parseFloat(lng).toFixed(6);
                tikorInput.value = `${latFormatted}, ${lngFormatted}`;
                currentMapLat = parseFloat(latFormatted);
                currentMapLng = parseFloat(lngFormatted);
                gpsBtn.textContent = '✅ GPS Ditemukan';
                setTimeout(() => {
                    gpsBtn.textContent = '📍 Deteksi GPS';
                    gpsBtn.disabled = false;
                }, 3000);
            };

            const handleGpsError = (err) => {
                // Coba fallback dengan low accuracy jika high accuracy gagal/timeout
                navigator.geolocation.getCurrentPosition(
                    pos => {
                        applyCoords(pos.coords.latitude, pos.coords.longitude);
                    },
                    err2 => {
                        gpsBtn.textContent = '📍 Deteksi GPS';
                        gpsBtn.disabled = false;
                        
                        let helpMsg = 'Gagal mendeteksi lokasi GPS otomatis.';
                        if (err2.code === 1) {
                            helpMsg = 'Izin lokasi ditolak. Silakan aktifkan izin lokasi/GPS pada pengaturan browser HP Anda.';
                        } else if (err2.code === 2) {
                            helpMsg = 'Lokasi tidak terdeteksi. Pastikan fitur GPS di HP Anda sudah aktif.';
                        } else if (err2.code === 3) {
                            helpMsg = 'Waktu pencarian GPS habis. Anda dapat menggunakan tombol "Pilih di Peta" untuk menentukan lokasi langsung.';
                        }
                        alert(helpMsg);
                    },
                    { enableHighAccuracy: false, timeout: 8000, maximumAge: 60000 }
                );
            };

            navigator.geolocation.getCurrentPosition(
                pos => {
                    applyCoords(pos.coords.latitude, pos.coords.longitude);
                },
                handleGpsError,
                { enableHighAccuracy: true, timeout: 12000, maximumAge: 30000 }
            );
        });
    }

    // ---- B. INTERACTIVE MAP PICKER MODAL (LEAFLET) ----
    if (mapBtn && mapModal) {
        mapBtn.addEventListener('click', () => {
            // Periksa apakah input sudah ada koordinat
            if (tikorInput && tikorInput.value) {
                const parts = tikorInput.value.split(',');
                if (parts.length === 2) {
                    const pLat = parseFloat(parts[0].trim());
                    const pLng = parseFloat(parts[1].trim());
                    if (!isNaN(pLat) && !isNaN(pLng)) {
                        currentMapLat = pLat;
                        currentMapLng = pLng;
                    }
                }
            }

            mapModal.style.display = 'flex';

            // Inisialisasi Peta Leaflet
            setTimeout(() => {
                if (!leafletMap && typeof L !== 'undefined') {
                    leafletMap = L.map('leaflet-map-container').setView([currentMapLat, currentMapLng], 15);

                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 19,
                        attribution: '&copy; OpenStreetMap'
                    }).addTo(leafletMap);

                    leafletMarker = L.marker([currentMapLat, currentMapLng], {
                        draggable: true
                    }).addTo(leafletMap);

                    const updateCoordsFromMarker = (lat, lng) => {
                        currentMapLat = parseFloat(lat).toFixed(6);
                        currentMapLng = parseFloat(lng).toFixed(6);
                        if (coordsDisplay) {
                            coordsDisplay.textContent = `${currentMapLat}, ${currentMapLng}`;
                        }
                    };

                    leafletMarker.on('dragend', (e) => {
                        const pos = e.target.getLatLng();
                        updateCoordsFromMarker(pos.lat, pos.lng);
                    });

                    leafletMap.on('click', (e) => {
                        leafletMarker.setLatLng(e.latlng);
                        updateCoordsFromMarker(e.latlng.lat, e.latlng.lng);
                    });

                    updateCoordsFromMarker(currentMapLat, currentMapLng);
                } else if (leafletMap) {
                    leafletMap.invalidateSize();
                    leafletMap.setView([currentMapLat, currentMapLng], 15);
                    if (leafletMarker) {
                        leafletMarker.setLatLng([currentMapLat, currentMapLng]);
                    }
                    if (coordsDisplay) {
                        coordsDisplay.textContent = `${parseFloat(currentMapLat).toFixed(6)}, ${parseFloat(currentMapLng).toFixed(6)}`;
                    }
                }
            }, 200);
        });

        // Tutup Modal
        if (closeMapBtn) {
            closeMapBtn.addEventListener('click', () => {
                mapModal.style.display = 'none';
            });
        }
        mapModal.addEventListener('click', (e) => {
            if (e.target === mapModal) {
                mapModal.style.display = 'none';
            }
        });

        // Gunakan Titik Koordinat dari Peta
        if (useCoordsBtn && tikorInput) {
            useCoordsBtn.addEventListener('click', () => {
                const latStr = parseFloat(currentMapLat).toFixed(6);
                const lngStr = parseFloat(currentMapLng).toFixed(6);
                tikorInput.value = `${latStr}, ${lngStr}`;
                mapModal.style.display = 'none';

                if (gpsBtn) {
                    gpsBtn.textContent = '✅ Titik Disimpan';
                    setTimeout(() => {
                        gpsBtn.textContent = '📍 Deteksi GPS';
                    }, 2500);
                }
            });
        }
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
    // 6. CLIENT-SIDE VALIDATION & FORM SUBMISSION HANDLER
    // ========================================================
    const form = document.getElementById('cbn-form');
    const submitBtn = document.getElementById('submit-btn');

    if (form && submitBtn) {
        // Hilangkan error merah secara dinamis saat user mengetik
        form.querySelectorAll('input, select, textarea').forEach(input => {
            input.addEventListener('input', () => {
                input.classList.remove('error', 'is-invalid');
                const errLabel = input.parentElement.querySelector('.field-error-msg');
                if (errLabel) errLabel.style.display = 'none';
            });
        });

        form.addEventListener('submit', (e) => {
            // Update data tanda tangan jika ada
            if (canvas && hasDrawn) {
                signatureInput.value = canvas.toDataURL('image/png');
            }

            // Bersihkan error lama
            document.querySelectorAll('.client-err').forEach(el => el.remove());
            document.querySelectorAll('.is-invalid, .error').forEach(el => el.classList.remove('is-invalid', 'error'));

            const clientErrors = [];
            let firstInvalidEl = null;

            const addError = (element, message, container = null) => {
                if (!element) return;
                element.classList.add('error', 'is-invalid');
                const targetBox = container || element.parentElement;
                
                const errDiv = document.createElement('div');
                errDiv.className = 'field-error-msg client-err';
                errDiv.style.cssText = 'color:#f87171;font-size:12px;font-weight:700;margin-top:5px;display:flex;align-items:center;gap:4px;';
                errDiv.innerHTML = `⚠️ ${message}`;
                targetBox.appendChild(errDiv);

                clientErrors.push(message);
                if (!firstInvalidEl) {
                    firstInvalidEl = element;
                }
            };

            // 1. Nama Pelanggan
            const namaEl = document.getElementById('nama_pelanggan');
            if (!namaEl || !namaEl.value.trim()) {
                addError(namaEl, 'Nama Lengkap Pelanggan belum diisi');
            }

            // 2. Tempat / Tanggal Lahir
            const ttlEl = document.getElementById('ttl');
            if (!ttlEl || !ttlEl.value.trim()) {
                addError(ttlEl, 'Tempat & Tanggal Lahir belum diisi (Contoh: Medan, 15/08/1995)');
            }

            // 3. Nomor KTP (16 Digit)
            const ktpEl = document.getElementById('nomor_ktp');
            const cleanKtp = ktpEl ? ktpEl.value.replace(/\D/g, '') : '';
            if (!ktpEl || !cleanKtp) {
                addError(ktpEl, 'Nomor KTP belum diisi');
            } else if (cleanKtp.length !== 16) {
                addError(ktpEl, `Nomor KTP harus 16 digit angka (saat ini: ${cleanKtp.length} digit)`);
            }

            // 4. Nomor Telepon / WA
            const telpEl = document.getElementById('telp');
            const cleanTelp = telpEl ? telpEl.value.replace(/[^\d+]/g, '') : '';
            if (!telpEl || !cleanTelp) {
                addError(telpEl, 'Nomor Telepon Seluler / WhatsApp belum diisi');
            } else if (cleanTelp.length < 9) {
                addError(telpEl, 'Nomor Telepon terlalu pendek (minimal 10 digit)');
            }

            // 5. Email Pelanggan
            const emailEl = document.getElementById('email_pelanggan');
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailEl || !emailEl.value.trim()) {
                addError(emailEl, 'Alamat Email Pelanggan belum diisi');
            } else if (!emailRegex.test(emailEl.value.trim())) {
                addError(emailEl, 'Format email tidak valid (Contoh: nama@gmail.com)');
            }

            // 6. Alamat Pemasangan
            const alamatEl = document.getElementById('alamat');
            if (!alamatEl || !alamatEl.value.trim()) {
                addError(alamatEl, 'Alamat Lengkap Rumah / Gedung belum diisi');
            }

            // 7. Kode Pos
            const kodePosEl = document.getElementById('kode_pos');
            if (!kodePosEl || !kodePosEl.value.trim()) {
                addError(kodePosEl, 'Kode Pos belum diisi');
            }

            // JIKA ADA FIELD YANG BELUM DIISI -> GAGALKAN SUBMIT & ARAHKAN KE FIELD TERSEBUT
            if (clientErrors.length > 0) {
                e.preventDefault();
                submitBtn.classList.remove('loading');
                submitBtn.disabled = false;

                if (firstInvalidEl) {
                    firstInvalidEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    if (typeof firstInvalidEl.focus === 'function') {
                        firstInvalidEl.focus();
                    }
                }
                return false;
            }

            // Jika valid -> tampilkan animasi loading submit
            submitBtn.classList.add('loading');
            submitBtn.disabled = true;
        });
    }

});
