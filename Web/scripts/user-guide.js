(function () {
    document.addEventListener('DOMContentLoaded', function () {
        var toc = document.getElementById('guideToc');
        if (!toc) {
            return;
        }

        var links = Array.prototype.slice.call(toc.querySelectorAll('a[href^="#"]'));
        var sections = links
            .map(function (link) {
                return document.getElementById(link.getAttribute('href').slice(1));
            })
            .filter(Boolean);

        if (sections.length === 0) {
            return;
        }

        // Highlights whichever section's heading is currently nearest the top of the
        // viewport, rather than waiting for a section to be fully scrolled past --
        // matches how most doc-site TOCs track scroll position.
        function updateActiveLink() {
            var activeIndex = 0;
            for (var i = 0; i < sections.length; i++) {
                if (sections[i].getBoundingClientRect().top <= 120) {
                    activeIndex = i;
                }
            }
            links.forEach(function (link, i) {
                link.classList.toggle('active', i === activeIndex);
            });
        }

        var ticking = false;
        window.addEventListener('scroll', function () {
            if (ticking) {
                return;
            }
            ticking = true;
            requestAnimationFrame(function () {
                updateActiveLink();
                ticking = false;
            });
        });

        updateActiveLink();
    });
})();
