// CoAsia first-run product tour. Reads step copy from window.coasiaTourConfig (assigned in
// tpl/Overview/overview_fullcalendar.tpl via {translate}, so it comes out pre-localized the
// same way overviewFullCalendarOpts.translations does — this file itself never contains
// user-facing text). Exposes window.CoAsiaTour.start() so the sidebar "Help" menu and the
// calendar toolbar's "?" button can both replay it on demand, independent of the
// first-login auto-trigger.
window.CoAsiaTour = (function () {
    var STORAGE_KEY = 'lbTourSeen_v1';
    var SPOTLIGHT_PADDING = 8;

    var config = null;
    var steps = [];
    var currentIndex = -1;
    var els = null; // {overlay, spotlight, card, title, body, progress, backBtn, nextBtn, skipBtn}
    var repositionHandler = null;

    function hasSeenTour() {
        try {
            return window.localStorage.getItem(STORAGE_KEY) === '1';
        } catch (e) {
            return false;
        }
    }

    function markSeen() {
        try {
            window.localStorage.setItem(STORAGE_KEY, '1');
        } catch (e) {
            // Private browsing / storage disabled -- the tour just replays every visit,
            // which is a harmless degradation rather than something worth surfacing.
        }
    }

    function buildDom() {
        if (els) {
            return els;
        }

        var overlay = document.createElement('div');
        overlay.className = 'coasia-tour-overlay';
        overlay.innerHTML =
            '<div class="coasia-tour-spotlight coasia-tour-spotlight-center" id="coasiaTourSpotlight"></div>' +
            '<div class="coasia-tour-card" id="coasiaTourCard" role="dialog" aria-modal="true">' +
            '  <div class="coasia-tour-progress" id="coasiaTourProgress"></div>' +
            '  <h3 class="coasia-tour-title" id="coasiaTourTitle"></h3>' +
            '  <div class="coasia-tour-body" id="coasiaTourBody"></div>' +
            '  <div class="coasia-tour-actions">' +
            '    <button type="button" class="coasia-tour-skip" id="coasiaTourSkip"></button>' +
            '    <div class="coasia-tour-actions-right">' +
            '      <button type="button" class="coasia-tour-back" id="coasiaTourBack"></button>' +
            '      <button type="button" class="coasia-tour-next" id="coasiaTourNext"></button>' +
            '    </div>' +
            '  </div>' +
            '</div>';
        document.body.appendChild(overlay);

        els = {
            overlay: overlay,
            spotlight: overlay.querySelector('#coasiaTourSpotlight'),
            card: overlay.querySelector('#coasiaTourCard'),
            progress: overlay.querySelector('#coasiaTourProgress'),
            title: overlay.querySelector('#coasiaTourTitle'),
            body: overlay.querySelector('#coasiaTourBody'),
            skipBtn: overlay.querySelector('#coasiaTourSkip'),
            backBtn: overlay.querySelector('#coasiaTourBack'),
            nextBtn: overlay.querySelector('#coasiaTourNext'),
        };

        els.skipBtn.textContent = config.strings.skip;
        els.skipBtn.addEventListener('click', function () {
            end();
        });
        els.backBtn.addEventListener('click', function () {
            goTo(currentIndex - 1);
        });
        els.nextBtn.addEventListener('click', function () {
            if (currentIndex >= steps.length - 1) {
                end();
            } else {
                goTo(currentIndex + 1);
            }
        });
        overlay.addEventListener('keydown', function (ev) {
            if (ev.key === 'Escape') {
                end();
            }
        });

        return els;
    }

    function positionForTarget(targetEl) {
        var spotlight = els.spotlight;
        var card = els.card;

        if (!targetEl) {
            spotlight.classList.add('coasia-tour-spotlight-center');
            spotlight.style.cssText = '';
            card.style.top = '';
            card.style.left = '';
            card.classList.add('coasia-tour-card-center');
            return;
        }

        card.classList.remove('coasia-tour-card-center');
        spotlight.classList.remove('coasia-tour-spotlight-center');

        var rect = targetEl.getBoundingClientRect();
        var top = rect.top - SPOTLIGHT_PADDING;
        var left = rect.left - SPOTLIGHT_PADDING;
        var width = rect.width + SPOTLIGHT_PADDING * 2;
        var height = rect.height + SPOTLIGHT_PADDING * 2;

        spotlight.style.top = top + 'px';
        spotlight.style.left = left + 'px';
        spotlight.style.width = width + 'px';
        spotlight.style.height = height + 'px';

        // Try below, then above, then beside (right, then left) the target, in that
        // order -- a target as tall as the whole calendar grid (#fullcalendarRoot) has no
        // room above or below it, so without the beside options the card fell back to
        // "below" anyway and landed off the bottom of the viewport. Whatever this picks,
        // the final clamp below still guarantees the card stays fully on-screen.
        var cardRect = card.getBoundingClientRect();
        var gap = 16;
        var vw = window.innerWidth;
        var vh = window.innerHeight;

        var spaceBelow = vh - (top + height);
        var spaceAbove = top;
        var spaceRight = vw - (left + width);
        var spaceLeft = left;

        var cardTop, cardLeft;

        if (spaceBelow >= cardRect.height + gap) {
            cardTop = top + height + gap;
            cardLeft = left;
        } else if (spaceAbove >= cardRect.height + gap) {
            cardTop = top - cardRect.height - gap;
            cardLeft = left;
        } else if (spaceRight >= cardRect.width + gap) {
            cardTop = (vh - cardRect.height) / 2;
            cardLeft = left + width + gap;
        } else if (spaceLeft >= cardRect.width + gap) {
            cardTop = (vh - cardRect.height) / 2;
            cardLeft = left - cardRect.width - gap;
        } else {
            // Nothing fits cleanly (the target fills the viewport) -- pin to a corner
            // instead of a computed offset so the card is still fully visible.
            cardTop = gap;
            cardLeft = vw - cardRect.width - gap;
        }

        card.style.top = Math.min(Math.max(cardTop, gap), vh - cardRect.height - gap) + 'px';
        card.style.left = Math.min(Math.max(cardLeft, gap), vw - cardRect.width - gap) + 'px';
    }

    function renderStep(index) {
        var step = steps[index];
        var targetEl = step.target ? document.querySelector(step.target) : null;

        els.title.textContent = step.title;
        els.body.innerHTML = step.body;
        els.progress.textContent = config.strings.stepOf
            .replace('{n}', index + 1)
            .replace('{total}', steps.length);

        els.backBtn.style.visibility = index === 0 ? 'hidden' : 'visible';
        els.backBtn.textContent = config.strings.back;
        els.nextBtn.textContent = index === steps.length - 1 ? config.strings.done : config.strings.next;

        if (targetEl) {
            targetEl.scrollIntoView({ block: 'center', behavior: 'instant' in window ? 'instant' : 'auto' });
        }

        // Layout twice: once to get the card's own size for the above/below flip decision
        // in positionForTarget, then again next frame once the browser has actually applied
        // that position (getBoundingClientRect right after a style write can still reflect
        // the previous step's box on some engines).
        positionForTarget(targetEl);
        requestAnimationFrame(function () {
            positionForTarget(targetEl);
        });
    }

    function goTo(index) {
        if (index < 0 || index >= steps.length) {
            return;
        }
        currentIndex = index;
        renderStep(index);
    }

    function reposition() {
        if (currentIndex < 0 || !els) {
            return;
        }
        var step = steps[currentIndex];
        var targetEl = step.target ? document.querySelector(step.target) : null;
        positionForTarget(targetEl);
    }

    function start() {
        if (!config || steps.length === 0) {
            return;
        }
        buildDom();
        els.overlay.classList.add('coasia-tour-open');
        els.overlay.setAttribute('tabindex', '-1');
        els.overlay.focus();

        repositionHandler = function () {
            reposition();
        };
        window.addEventListener('resize', repositionHandler);
        window.addEventListener('scroll', repositionHandler, true);

        goTo(0);
    }

    function end() {
        markSeen();
        if (els) {
            els.overlay.classList.remove('coasia-tour-open');
        }
        if (repositionHandler) {
            window.removeEventListener('resize', repositionHandler);
            window.removeEventListener('scroll', repositionHandler, true);
            repositionHandler = null;
        }
        currentIndex = -1;
    }

    document.addEventListener('DOMContentLoaded', function () {
        config = window.coasiaTourConfig;
        if (!config || !config.steps) {
            return;
        }
        steps = config.steps;

        // ?tour=1 (the sidebar "Help" menu's replay link) always (re)starts the tour,
        // regardless of whether it has been seen before -- strip the flag from the URL
        // right after so a later refresh of this same page does not replay it again.
        var params = new URLSearchParams(window.location.search);
        if (params.get('tour') === '1') {
            params.delete('tour');
            var newSearch = params.toString();
            var newUrl = window.location.pathname + (newSearch ? '?' + newSearch : '') + window.location.hash;
            window.history.replaceState(null, '', newUrl);
            start();
            return;
        }

        if (!hasSeenTour()) {
            start();
        }
    });

    return {
        start: start,
        hasSeenTour: hasSeenTour,
    };
})();
