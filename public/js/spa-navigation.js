(function () {
  'use strict';

  var contentSelector = '#spa-content';

  function isEligibleLink(link) {
    if (!link || !link.href) return false;
    if (link.target && link.target !== '_self') return false;
    if (link.hasAttribute('download')) return false;
    if (link.getAttribute('data-toggle') === 'modal') return false;

    var href = link.getAttribute('href') || '';
    if (!href || href.indexOf('#') === 0 || href.indexOf('javascript:') === 0) return false;

    var url;
    try {
      url = new URL(link.href, window.location.origin);
    } catch (e) {
      return false;
    }

    if (url.origin !== window.location.origin) return false;
    if (url.pathname === window.location.pathname && url.search === window.location.search) return false;

    return true;
  }

  function setActiveNav(pathname) {
    var links = document.querySelectorAll('.navigation-1 a, .dl-menu a');
    links.forEach(function (link) {
      var href = link.getAttribute('href');
      if (!href) return;
      var url = new URL(href, window.location.origin);
      var isActive = url.pathname === pathname;
      if (isActive) {
        link.classList.add('spa-active');
        if (link.parentElement) link.parentElement.classList.add('active');
      } else {
        link.classList.remove('spa-active');
        if (link.parentElement) link.parentElement.classList.remove('active');
      }
    });
  }

  function initDynamicWidgets() {
    if (!window.jQuery) return;
    var $ = window.jQuery;

    if ($('.banner_slider').length && !$('.banner_slider').hasClass('slick-initialized')) {
      $('.banner_slider').slick({ fade: true, autoplay: true, arrows: false, centerMode: true });
    }

    if ($('.msl-eventlist2-slider').length && !$('.msl-eventlist2-slider').hasClass('slick-initialized')) {
      $('.msl-eventlist2-slider').slick({
        centerMode: false,
        arrows: true,
        dots: true,
        centerPadding: '0px',
        vertical: true,
        slidesToShow: 4,
        slidesToScroll: 1,
        responsive: [
          { breakpoint: 1680, settings: { arrows: true, centerMode: false, centerPadding: '0px', slidesToShow: 3 } },
          { breakpoint: 768, settings: { arrows: true, centerMode: true, centerPadding: '0px', slidesToShow: 2 } },
          { breakpoint: 481, settings: { arrows: true, centerMode: true, centerPadding: '0px', slidesToShow: 1 } }
        ]
      });
    }
  }

  function swapPage(html, url, push) {
    var parser = new DOMParser();
    var doc = parser.parseFromString(html, 'text/html');
    var nextContent = doc.querySelector(contentSelector);

    if (!nextContent) {
      window.location.href = url;
      return;
    }

    var currentContent = document.querySelector(contentSelector);
    if (!currentContent) return;

    currentContent.innerHTML = nextContent.innerHTML;
    executeScripts(currentContent);
    document.title = doc.title || document.title;

    if (push) {
      window.history.pushState({ spa: true, url: url }, '', url);
    }

    setActiveNav(new URL(url, window.location.origin).pathname);
    initDynamicWidgets();
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  function executeScripts(container) {
    var scripts = container.querySelectorAll('script');

    scripts.forEach(function (oldScript) {
      var newScript = document.createElement('script');

      Array.prototype.slice.call(oldScript.attributes).forEach(function (attr) {
        newScript.setAttribute(attr.name, attr.value);
      });

      if (oldScript.textContent) {
        newScript.textContent = oldScript.textContent;
      }

      oldScript.parentNode.replaceChild(newScript, oldScript);
    });
  }

  async function navigate(url, push) {
    try {
      var response = await fetch(url, {
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      });

      if (!response.ok) {
        window.location.href = url;
        return;
      }

      var html = await response.text();
      swapPage(html, url, push);
    } catch (e) {
      window.location.href = url;
    }
  }

  document.addEventListener('click', function (event) {
    var link = event.target.closest('a');

    if (!link || event.defaultPrevented) return;
    if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    if (!isEligibleLink(link)) return;

    event.preventDefault();
    navigate(link.href, true);
  });

  window.addEventListener('popstate', function () {
    navigate(window.location.href, false);
  });

  document.addEventListener('DOMContentLoaded', function () {
    setActiveNav(window.location.pathname);
  });
})();
