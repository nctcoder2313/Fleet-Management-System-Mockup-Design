/**
 * Top Loading Bar & Micro-Interaction
 * Provides immediate feedback on page load, form submission, and link navigation.
 */
(function () {
    'use strict';

    var bar = null;
    var loader = null;
    var spinner = null;
    var progress = 0;
    var timer = null;
    var trickler = null;
    var isRunning = false;

    function ensureDOMElements() {
        if (!loader) {
            loader = document.getElementById('top-loader');
        }
        if (!loader) {
            loader = document.createElement('div');
            loader.id = 'top-loader';
            loader.className = 'top-loader';
            loader.setAttribute('aria-hidden', 'true');
            loader.innerHTML = '<div class="top-loader-bar" id="top-loader-bar"><div class="top-loader-glow"></div></div>';
            (document.body || document.documentElement).appendChild(loader);
        }

        if (!bar) {
            bar = document.getElementById('top-loader-bar') || loader.querySelector('.top-loader-bar');
        }

        if (!spinner) {
            spinner = document.getElementById('top-loader-spinner');
        }
        if (!spinner) {
            spinner = document.createElement('div');
            spinner.id = 'top-loader-spinner';
            spinner.className = 'top-loader-spinner';
            spinner.setAttribute('aria-hidden', 'true');
            spinner.innerHTML = '<div class="top-loader-spinner-circle"></div>';
            (document.body || document.documentElement).appendChild(spinner);
        }
    }

    function setWidth(p) {
        progress = p;
        if (!bar) ensureDOMElements();
        if (bar) {
            bar.style.width = p + '%';
        }
    }

    var TopLoader = {
        start: function () {
            ensureDOMElements();
            if (isRunning) return;
            isRunning = true;
            clearTimeout(timer);
            clearInterval(trickler);

            if (loader) loader.classList.add('is-active');
            if (spinner) spinner.classList.add('is-active');

            setWidth(0);
            requestAnimationFrame(function () {
                setWidth(22);

                // Trickle progress forward realistically
                trickler = setInterval(function () {
                    if (progress < 45) {
                        setWidth(progress + (Math.random() * 8 + 3));
                    } else if (progress < 75) {
                        setWidth(progress + (Math.random() * 4 + 1.5));
                    } else if (progress < 92) {
                        setWidth(progress + 0.6);
                    }
                }, 200);
            });
        },

        done: function () {
            if (!isRunning) return;
            clearInterval(trickler);
            setWidth(100);

            timer = setTimeout(function () {
                if (loader) loader.classList.remove('is-active');
                if (spinner) spinner.classList.remove('is-active');
                timer = setTimeout(function () {
                    setWidth(0);
                    isRunning = false;
                }, 220);
            }, 260);
        }
    };

    window.TopLoader = TopLoader;

    // 1. Initial Page Load Micro-interaction: Quick 0 -> 100% sweep
    ensureDOMElements();
    TopLoader.start();

    if (document.readyState === 'complete') {
        setTimeout(function () { TopLoader.done(); }, 140);
    } else {
        window.addEventListener('load', function () {
            setTimeout(function () { TopLoader.done(); }, 140);
        });
    }

    // Handle bfcache (back-forward navigation)
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) {
            TopLoader.done();
            // Re-enable any disabled buttons
            var buttons = document.querySelectorAll('button.is-loading, input.is-loading');
            buttons.forEach(function (btn) {
                btn.classList.remove('is-loading');
                btn.style.pointerEvents = '';
                btn.style.opacity = '';
            });
        }
    });

    // 2. Form Submission Micro-interaction
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form || form.tagName !== 'FORM') return;

        // If HTML5 validity check fails, do not show loader
        if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
            return;
        }

        TopLoader.start();

        // Add subtle loading state to the submit button
        var submitBtn = form.querySelector('button[type=submit], input[type=submit]');
        if (submitBtn) {
            setTimeout(function () {
                submitBtn.classList.add('is-loading');
            }, 20);
        }
    }, true);

    // 3. Link Navigation Micro-interaction
    document.addEventListener('click', function (e) {
        var a = e.target.closest('a');
        if (!a || !a.href) return;

        // Ignore modified clicks (new tab, etc.)
        if (e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
        var target = a.getAttribute('target');
        if (target && target !== '_self') return;

        var href = a.getAttribute('href');
        if (!href || href.startsWith('#') || href.startsWith('javascript:')) return;

        // Check if internal domain link
        if (a.hostname === window.location.hostname) {
            TopLoader.start();
        }
    }, true);

    // 4. Wrap fetch API if helper uses it
    if (typeof window.fetch === 'function') {
        var originalFetch = window.fetch;
        window.fetch = function () {
            TopLoader.start();
            return originalFetch.apply(this, arguments).then(function (res) {
                TopLoader.done();
                return res;
            }).catch(function (err) {
                TopLoader.done();
                throw err;
            });
        };
    }
})();
