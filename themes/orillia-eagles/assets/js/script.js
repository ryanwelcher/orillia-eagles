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

// :::SECTION:Form Interaction:::
const contactForm = document.querySelector('.contact-form');

if (contactForm) {
  contactForm.addEventListener('submit', function (e) {
    e.preventDefault();

    const submitBtn = this.querySelector('.form-submit');
    const originalText = submitBtn.textContent;

    submitBtn.textContent = 'Sending...';
    submitBtn.style.opacity = '0.7';
    submitBtn.disabled = true;

    setTimeout(() => {
      submitBtn.textContent = 'Message Sent!';
      submitBtn.style.opacity = '1';
      submitBtn.style.backgroundColor = '#2d8a4e';

      setTimeout(() => {
        submitBtn.textContent = originalText;
        submitBtn.style.backgroundColor = '';
        submitBtn.disabled = false;
        contactForm.reset();
      }, 2500);
    }, 1200);
  });
}

// :::SECTION:Diagonal Accent Parallax:::
const diagonalAccent = document.querySelector('.hero-diagonal-accent');

if (diagonalAccent) {
  let ticking = false;

  window.addEventListener('scroll', () => {
    if (!ticking) {
      window.requestAnimationFrame(() => {
        const scrolled = window.scrollY;
        const maxScroll = document.documentElement.scrollHeight - window.innerHeight;
        const progress = Math.min(scrolled / maxScroll, 1);

        // Slowly fade out the diagonal accent as user scrolls
        diagonalAccent.style.opacity = Math.max(1 - progress * 3, 0);

        ticking = false;
      });
      ticking = true;
    }
  }, { passive: true });
}

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
