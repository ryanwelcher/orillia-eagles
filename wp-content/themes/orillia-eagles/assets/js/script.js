// :::SECTION:Scroll Animations:::
const animatedElements = document.querySelectorAll('.animate-on-scroll');

if (animatedElements.length > 0) {
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('is-visible');
        observer.unobserve(entry.target);
      }
    });
  }, {
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px'
  });

  animatedElements.forEach(el => observer.observe(el));
}

// :::SECTION:Smooth Scrolling:::
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
  anchor.addEventListener('click', function (e) {
    const targetId = this.getAttribute('href');
    if (targetId === '#') return;

    const targetEl = document.querySelector(targetId);
    if (targetEl) {
      e.preventDefault();
      const offset = 20;
      const targetPosition = targetEl.getBoundingClientRect().top + window.scrollY - offset;

      window.scrollTo({
        top: targetPosition,
        behavior: 'smooth'
      });
    }
  });
});

// :::SECTION:Roster Team Tabs:::
document.querySelectorAll('.roster-tabs').forEach(tabGroup => {
  const rosterSection = tabGroup.closest('.roster');
  const tabs = Array.from(tabGroup.querySelectorAll('.roster-tab .wp-element-button'));
  const playerItems = rosterSection
    ? Array.from(rosterSection.querySelectorAll('.roster-grid > li'))
    : [];

  if (tabs.length === 0 || playerItems.length === 0) return;

  tabGroup.setAttribute('role', 'tablist');
  tabGroup.setAttribute('aria-label', 'Roster filters');

  const getFilter = tab => {
    const tabWrapper = tab.closest('.roster-tab');
    const filterClass = Array.from(tabWrapper.classList).find(className => className.startsWith('roster-tab--'));
    return filterClass ? filterClass.replace('roster-tab--', '') : '';
  };

  const selectTeam = selectedTab => {
    const selectedFilter = getFilter(selectedTab);

    tabs.forEach(tab => {
      const isSelected = tab === selectedTab;
      tab.closest('.roster-tab').classList.toggle('is-active', isSelected);
      tab.setAttribute('role', 'tab');
      tab.setAttribute('aria-selected', String(isSelected));
      tab.setAttribute('tabindex', isSelected ? '0' : '-1');
    });

    playerItems.forEach(item => {
      const isCoach = item.classList.contains('roster_role-coach');
      const matchesFilter = selectedFilter === 'coaches'
        ? isCoach
        : item.classList.contains(`team-${selectedFilter}`) && !isCoach;
      item.hidden = !matchesFilter;
    });
  };

  tabs.forEach((tab, index) => {
    tab.addEventListener('click', event => {
      event.preventDefault();
      selectTeam(tab);
    });

    tab.addEventListener('keydown', event => {
      if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
      event.preventDefault();

      let nextIndex = index;
      if (event.key === 'ArrowLeft') nextIndex = (index - 1 + tabs.length) % tabs.length;
      if (event.key === 'ArrowRight') nextIndex = (index + 1) % tabs.length;
      if (event.key === 'Home') nextIndex = 0;
      if (event.key === 'End') nextIndex = tabs.length - 1;

      tabs[nextIndex].focus();
      selectTeam(tabs[nextIndex]);
    });
  });

  selectTeam(tabs[0]);
});

// :::SECTION:Roster Hover Effect:::
const rosterPlayers = document.querySelectorAll('.roster-player');

rosterPlayers.forEach(player => {
  player.addEventListener('mouseenter', () => {
    rosterPlayers.forEach(p => {
      if (p !== player) {
        p.style.opacity = '0.4';
      }
    });
  });

  player.addEventListener('mouseleave', () => {
    rosterPlayers.forEach(p => {
      p.style.opacity = '1';
    });
  });
});

// Apply transition to roster players for smooth dim effect
rosterPlayers.forEach(player => {
  player.style.transition = 'opacity 0.3s ease, background-color 0.3s ease';
});

// :::SECTION:Counter Animation:::
const valueNumbers = document.querySelectorAll('.about-value-number');

if (valueNumbers.length > 0) {
  const counterObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        const el = entry.target;
        const target = parseInt(el.textContent);
        if (isNaN(target)) return;

        let current = 0;
        const duration = 1200;
        const increment = target / (duration / 16);

        const counter = setInterval(() => {
          current += increment;
          if (current >= target) {
            el.textContent = target;
            clearInterval(counter);
          } else {
            el.textContent = Math.floor(current);
          }
        }, 16);

        counterObserver.unobserve(el);
      }
    });
  }, {
    threshold: 0.5
  });

  valueNumbers.forEach(el => counterObserver.observe(el));
}

// :::SECTION:Sticky Scroll-Aware Nav:::
const heroNav = document.querySelector('.hero-nav');

if (heroNav) {
  const PIN_THRESHOLD = 10;   // px scrolled before the bar gets its pinned shadow
  // Keep the nav visible well down the page before hide-on-scroll-down engages.
  const HIDE_THRESHOLD = Math.max(600, Math.round(window.innerHeight * 0.9));
  let lastScrollY = window.scrollY;
  let ticking = false;

  // Expose the nav's height so non-hero pages can reserve space for the fixed bar.
  const setNavHeight = () => {
    document.documentElement.style.setProperty('--nav-height', heroNav.offsetHeight + 'px');
  };
  setNavHeight();
  window.addEventListener('resize', setNavHeight, { passive: true });

  const DIRECTION_DEADZONE = 5; // px — ignore sub-pixel jitter / scroll-end bounce

  const updateNav = () => {
    const currentY = window.scrollY;
    const delta = currentY - lastScrollY;

    // Pinned styling once we've moved off the very top.
    heroNav.classList.toggle('is-pinned', currentY > PIN_THRESHOLD);

    if (currentY <= HIDE_THRESHOLD) {
      // Near the top: always show.
      heroNav.classList.remove('is-hidden');
    } else if (delta > DIRECTION_DEADZONE) {
      // Scrolling down meaningfully: hide.
      heroNav.classList.add('is-hidden');
    } else if (delta < -DIRECTION_DEADZONE) {
      // Scrolling up meaningfully: reveal.
      heroNav.classList.remove('is-hidden');
    }
    // Movements within the deadzone leave the current state untouched.

    lastScrollY = currentY;
    ticking = false;
  };

  window.addEventListener('scroll', () => {
    if (!ticking) {
      window.requestAnimationFrame(updateNav);
      ticking = true;
    }
  }, { passive: true });

  updateNav();
}

// :::SECTION:Back To Top Button:::
const backToTop = document.createElement('button');
backToTop.type = 'button';
backToTop.className = 'back-to-top';
backToTop.setAttribute('aria-label', 'Back to top');
backToTop.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><polyline points="18 15 12 9 6 15"></polyline></svg>';
document.body.appendChild(backToTop);

const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

// This theme sets `html { scroll-behavior: smooth }`, which makes
// `scrollTo({ behavior: 'smooth' })` a silent no-op here. Drive the scroll
// manually with `behavior: 'instant'` per frame so it works reliably.
const scrollToTop = () => {
  if (prefersReducedMotion) {
    window.scrollTo({ top: 0, behavior: 'instant' });
    return;
  }

  const startY = window.scrollY;
  const duration = 500;
  let startTime = null;

  const step = (timestamp) => {
    if (startTime === null) startTime = timestamp;
    const progress = Math.min((timestamp - startTime) / duration, 1);
    const eased = 1 - Math.pow(1 - progress, 3); // easeOutCubic
    window.scrollTo({ top: Math.round(startY * (1 - eased)), behavior: 'instant' });
    if (progress < 1) {
      window.requestAnimationFrame(step);
    }
  };

  window.requestAnimationFrame(step);
};

backToTop.addEventListener('click', scrollToTop);

const hero = document.querySelector('.hero');

if (hero) {
  // Front page: reveal the button once the hero has scrolled out of view.
  const heroObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      backToTop.classList.toggle('is-visible', !entry.isIntersecting);
    });
  }, { threshold: 0, rootMargin: '0px 0px 0px 0px' });

  heroObserver.observe(hero);
} else {
  // Pages with no hero: reveal after roughly one viewport of scrolling.
  let btnTicking = false;

  const updateButton = () => {
    backToTop.classList.toggle('is-visible', window.scrollY > window.innerHeight);
    btnTicking = false;
  };

  window.addEventListener('scroll', () => {
    if (!btnTicking) {
      window.requestAnimationFrame(updateButton);
      btnTicking = true;
    }
  }, { passive: true });

  updateButton();
}
