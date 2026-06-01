(function () {
    'use strict';

    var AUTO_REFRESH_SECONDS = 300;

    function initSlider() {
        var slides = document.querySelectorAll('.hero-slide');
        var dots = document.querySelectorAll('.slider-dot');
        if (!slides.length || !dots.length) return;

        var current = 0;
        function show(index) {
            slides.forEach(function (slide, i) { slide.classList.toggle('active', i === index); });
            dots.forEach(function (dot, i) { dot.classList.toggle('active', i === index); });
            current = index;
        }

        dots.forEach(function (dot, i) {
            dot.addEventListener('click', function () { show(i); });
        });

        window.setInterval(function () {
            show((current + 1) % slides.length);
        }, 5000);
    }

    function initTickerMarquee() {
        var tickerItems = document.querySelector('.ticker__items');
        if (!tickerItems || tickerItems.dataset.marqueeReady === '1') return;

        var links = Array.prototype.slice.call(tickerItems.querySelectorAll('a'));
        if (links.length < 3) return;

        var firstTrack = document.createElement('div');
        firstTrack.className = 'ticker__track';
        links.forEach(function (link) { firstTrack.appendChild(link); });

        var secondTrack = firstTrack.cloneNode(true);
        secondTrack.classList.add('ticker__track--clone');

        tickerItems.textContent = '';
        tickerItems.classList.add('is-marquee');
        tickerItems.appendChild(firstTrack);
        tickerItems.appendChild(secondTrack);
        tickerItems.dataset.marqueeReady = '1';
    }

    function initReveal() {
        var targets = document.querySelectorAll('.story-card, .signal-board__panel, .category-column, .live-rail, .follow-strip, .article-body, .article-sidebar, .social-video-hub, .fact-table');
        if (!targets.length) return;

        targets.forEach(function (el) { el.classList.add('reveal-up'); });

        if (!('IntersectionObserver' in window)) {
            targets.forEach(function (el) { el.classList.add('is-visible'); });
            return;
        }

        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.14 });

        targets.forEach(function (el) { observer.observe(el); });
    }

    function initTilt3D() {
        if (!window.matchMedia('(hover: hover) and (pointer: fine)').matches) return;
        var targets = document.querySelectorAll('.story-card, .signal-board__panel, .category-column, .live-rail, .article-body, .social-video-hub, .source-box');
        if (!targets.length) return;

        targets.forEach(function (el) {
            el.classList.add('interactive-3d');

            el.addEventListener('mousemove', function (event) {
                var rect = el.getBoundingClientRect();
                var px = (event.clientX - rect.left) / rect.width;
                var py = (event.clientY - rect.top) / rect.height;
                var ry = ((px - 0.5) * 9).toFixed(2);
                var rx = ((0.5 - py) * 8).toFixed(2);

                el.style.setProperty('--rx', rx + 'deg');
                el.style.setProperty('--ry', ry + 'deg');
                el.style.setProperty('--ty', '-4px');
            });

            el.addEventListener('mouseleave', function () {
                el.style.setProperty('--rx', '0deg');
                el.style.setProperty('--ry', '0deg');
                el.style.setProperty('--ty', '0px');
            });
        });
    }

    function formatCountdown(seconds) {
        var m = Math.floor(seconds / 60);
        var s = seconds % 60;
        return m + ':' + String(s).padStart(2, '0');
    }

    function refreshCountdownLine(label, lastIso) {
        var lastMillis = Date.parse(lastIso || '');
        if (!lastMillis) return;

        var now = Date.now();
        var elapsed = Math.floor((now - lastMillis) / 1000);
        var remain = AUTO_REFRESH_SECONDS - (elapsed % AUTO_REFRESH_SECONDS);
        if (remain === AUTO_REFRESH_SECONDS) remain = 0;

        label.textContent = 'Last refresh: just now | Next auto refresh: ' + formatCountdown(remain);
    }

    function initRefreshCountdown() {
        var label = document.querySelector('.topline__time[data-last-refresh]');
        if (!label) return;
        window.setInterval(function () {
            refreshCountdownLine(label, label.getAttribute('data-last-refresh'));
        }, 1000);
    }

    function updateLatest() {
        fetch('/api/latest.php', { headers: { Accept: 'application/json' } })
            .then(function (response) { return response.ok ? response.json() : null; })
            .then(function (payload) {
                var last = payload && payload.state ? payload.state.last_refresh_at : '';
                if (!last) return;

                var modern = document.querySelector('.topline__time[data-last-refresh]');
                if (modern) {
                    modern.setAttribute('data-last-refresh', last);
                    refreshCountdownLine(modern, last);
                }

                var legacy = document.querySelector('.topbar__update');
                if (legacy) {
                    legacy.textContent = 'Last refresh: just now';
                }
            })
            .catch(function () {});
    }

    document.addEventListener('DOMContentLoaded', function () {
        initSlider();
        initTickerMarquee();
        initReveal();
        initTilt3D();
        initRefreshCountdown();
        updateLatest();
        window.setInterval(updateLatest, 60000);
    });
}());
