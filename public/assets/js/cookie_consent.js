/**
 * Cookie Consent & Performance Cache Optimizer
 * PT. TALENTA INTEGRITAS NASIONAL - CBN Sales & Form System
 */
(function() {
    'use strict';

    const COOKIE_KEY = 'cbn_cookie_consent';
    const CONSENT_DURATION_DAYS = 365;

    function getCookie(name) {
        const matches = document.cookie.match(new RegExp(
            "(?:^|; )" + name.replace(/([\.$?*|{}\(\)\[\]\\\/\+^])/g, '\\$1') + "=([^;]*)"
        ));
        return matches ? decodeURIComponent(matches[1]) : undefined;
    }

    function setCookie(name, value, days) {
        let expires = "";
        if (days) {
            const date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            expires = "; expires=" + date.toUTCString();
        }
        document.cookie = name + "=" + encodeURIComponent(value) + expires + "; path=/; SameSite=Lax";
    }

    function isConsentGiven() {
        return localStorage.getItem(COOKIE_KEY) === 'accepted' || getCookie(COOKIE_KEY) === 'accepted';
    }

    function applyPerformanceOptimizations() {
        // Optimize browser memory & prefetch critical DNS
        try {
            if (!document.getElementById('cbn-dns-prefetch')) {
                const head = document.head || document.getElementsByTagName('head')[0];
                const preconnectUrls = [
                    'https://fonts.googleapis.com',
                    'https://fonts.gstatic.com',
                    'https://unpkg.com',
                    'https://cdnjs.cloudflare.com'
                ];
                preconnectUrls.forEach(url => {
                    const link = document.createElement('link');
                    link.rel = 'dns-prefetch';
                    link.href = url;
                    head.appendChild(link);
                });
            }
        } catch (e) {}
    }

    function setConsent(type) {
        localStorage.setItem(COOKIE_KEY, type);
        setCookie(COOKIE_KEY, type, CONSENT_DURATION_DAYS);
        applyPerformanceOptimizations();

        const banner = document.getElementById('cbn-cookie-banner');
        if (banner) {
            banner.style.opacity = '0';
            banner.style.transform = 'translateY(30px)';
            setTimeout(() => {
                banner.remove();
            }, 300);
        }
    }

    function createCookieBanner() {
        if (isConsentGiven()) {
            applyPerformanceOptimizations();
            return;
        }

        // Create Banner Element
        const banner = document.createElement('div');
        banner.id = 'cbn-cookie-banner';
        banner.innerHTML = `
            <style>
                #cbn-cookie-banner {
                    position: fixed;
                    bottom: 24px;
                    left: 24px;
                    right: 24px;
                    max-width: 580px;
                    margin: 0 auto;
                    background: rgba(10, 20, 42, 0.96);
                    backdrop-filter: blur(18px);
                    -webkit-backdrop-filter: blur(18px);
                    border: 1px solid rgba(0, 160, 223, 0.4);
                    border-radius: 16px;
                    padding: 20px 24px;
                    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.6), 0 0 35px rgba(0, 160, 223, 0.15);
                    z-index: 999999;
                    font-family: 'Plus Jakarta Sans', 'Segoe UI', sans-serif;
                    color: #f8fafc;
                    display: flex;
                    flex-direction: column;
                    gap: 14px;
                    opacity: 0;
                    transform: translateY(30px);
                    transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1);
                }
                #cbn-cookie-banner.show {
                    opacity: 1;
                    transform: translateY(0);
                }
                .cbn-cookie-header {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }
                .cbn-cookie-icon {
                    font-size: 24px;
                    line-height: 1;
                }
                .cbn-cookie-title {
                    font-size: 15px;
                    font-weight: 800;
                    color: #ffffff;
                    letter-spacing: 0.2px;
                }
                .cbn-cookie-body {
                    font-size: 12.5px;
                    line-height: 1.6;
                    color: #cbd5e1;
                }
                .cbn-cookie-body strong {
                    color: #67e8f9;
                }
                .cbn-cookie-actions {
                    display: flex;
                    align-items: center;
                    justify-content: flex-end;
                    flex-wrap: wrap;
                    gap: 10px;
                    margin-top: 4px;
                }
                .btn-cookie-essential {
                    background: rgba(255, 255, 255, 0.08);
                    border: 1px solid rgba(255, 255, 255, 0.2);
                    color: #94a3b8;
                    padding: 8px 16px;
                    border-radius: 8px;
                    font-size: 12.5px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.2s ease;
                }
                .btn-cookie-essential:hover {
                    background: rgba(255, 255, 255, 0.15);
                    color: #ffffff;
                }
                .btn-cookie-accept {
                    background: linear-gradient(135deg, #00a0df 0%, #005696 100%);
                    border: none;
                    color: #ffffff;
                    padding: 9px 22px;
                    border-radius: 8px;
                    font-size: 13px;
                    font-weight: 800;
                    cursor: pointer;
                    box-shadow: 0 4px 15px rgba(0, 160, 223, 0.4);
                    transition: all 0.2s ease;
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                }
                .btn-cookie-accept:hover {
                    transform: translateY(-1px);
                    box-shadow: 0 6px 20px rgba(0, 160, 223, 0.6);
                    opacity: 0.95;
                }
                @media (max-width: 600px) {
                    #cbn-cookie-banner {
                        bottom: 15px;
                        left: 15px;
                        right: 15px;
                        padding: 16px 18px;
                    }
                    .cbn-cookie-actions {
                        flex-direction: column-reverse;
                        width: 100%;
                    }
                    .btn-cookie-accept, .btn-cookie-essential {
                        width: 100%;
                        justify-content: center;
                        text-align: center;
                        padding: 10px;
                    }
                }
            </style>
            <div class="cbn-cookie-header">
                <span class="cbn-cookie-icon">🍪</span>
                <div class="cbn-cookie-title">Pengaturan Cookie &amp; Kecepatan Website</div>
            </div>
            <div class="cbn-cookie-body">
                Website ini menggunakan cookie dan cache lokal untuk mempercepat akses pendaftaran, menyimpan pilihan provinsi &amp; paket secara instan, serta memastikan pengalaman browsing Anda berjalan lancar <strong>tanpa lag</strong>.
            </div>
            <div class="cbn-cookie-actions">
                <button type="button" class="btn-cookie-essential" id="btn-cookie-essential">Hanya Esensial</button>
                <button type="button" class="btn-cookie-accept" id="btn-cookie-accept">
                    <span>⚡ Accept All Cookies</span>
                </button>
            </div>
        `;

        document.body.appendChild(banner);

        // Trigger entrance animation
        setTimeout(() => {
            banner.classList.add('show');
        }, 100);

        // Bind Buttons
        const btnAccept = document.getElementById('btn-cookie-accept');
        const btnEssential = document.getElementById('btn-cookie-essential');

        if (btnAccept) {
            btnAccept.addEventListener('click', () => setConsent('accepted'));
        }
        if (btnEssential) {
            btnEssential.addEventListener('click', () => setConsent('essential'));
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', createCookieBanner);
    } else {
        createCookieBanner();
    }
})();
