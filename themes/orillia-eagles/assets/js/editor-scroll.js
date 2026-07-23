(function() {
  function setup() {
    var iframe = document.querySelector('iframe[name="editor-canvas"]');
    if (!iframe) { setTimeout(setup, 500); return; }
    var doc = iframe.contentDocument;
    if (!doc) { setTimeout(setup, 500); return; }
    var header = doc.querySelector('.overlay-header');
    if (!header) { setTimeout(setup, 500); return; }
    var script = doc.createElement('script');
    script.textContent = '(function(){' +
      'var header = document.querySelector(".overlay-header");' +
      'if(!header) return;' +
      'var inner = header.querySelector("[class*=header]");' +
      'document.addEventListener("scroll", function(){' +
        'var scrolled = document.documentElement.scrollTop > 50;' +
        'header.classList.toggle("is-scrolled", scrolled);' +
        'if(inner) inner.classList.toggle("is-scrolled", scrolled);' +
      '}, {passive:true});' +
    '})();';
    doc.body.appendChild(script);
  }
  if (document.readyState === 'complete') {
    setTimeout(setup, 2000);
  } else {
    window.addEventListener('load', function() { setTimeout(setup, 2000); });
  }
})();