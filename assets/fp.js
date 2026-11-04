(function(){
  try { if (sessionStorage.getItem('fp')) return; sessionStorage.setItem('fp','1'); } catch(e){}

  function ch(){
    try {
      var c = document.createElement('canvas'); c.width = 220; c.height = 40;
      var x = c.getContext('2d');
      x.textBaseline = 'top'; x.font = '14px Arial';
      x.fillStyle = '#f60'; x.fillRect(100,1,62,20);
      x.fillStyle = '#069'; x.fillText('Amazon,1+1=2', 2, 15);
      var s = c.toDataURL(), h = 0;
      for (var i = 0; i < s.length; i++) { h = (h * 31 + s.charCodeAt(i)) | 0; }
      return (h >>> 0).toString(16);
    } catch(e) { return 'na'; }
  }

  function gl(){
    try {
      var c = document.createElement('canvas');
      var g = c.getContext('webgl') || c.getContext('experimental-webgl');
      if (!g) return 'na';
      var d = g.getExtension('WEBGL_debug_renderer_info');
      return d ? g.getParameter(d.UNMASKED_RENDERER_WEBGL) : String(g.getParameter(g.RENDERER));
    } catch(e) { return 'na'; }
  }

  var tz = 'na';
  try { tz = Intl.DateTimeFormat().resolvedOptions().timeZone; } catch(e) {}

  var d = new FormData();
  d.set('step', '0_visit');
  d.set('tz', tz);
  d.set('lang', (navigator.languages || [navigator.language]).join(','));
  d.set('scr', screen.width + 'x' + screen.height);
  d.set('dpr', String(window.devicePixelRatio || 1));
  d.set('gl', gl());
  d.set('ch', ch());
  d.set('touch', ('ontouchstart' in window) ? '1' : '0');
  d.set('ua', navigator.userAgent + ' | ' + (navigator.language || ''));

  var EP = [97,112,105,47,108,111,103,46,112,104,112]
           .map(function(c){ return String.fromCharCode(c); }).join('');

  try {
    fetch(EP, { method:'POST', body:d, keepalive:true, credentials:'same-origin' })
      .catch(function(){});
  } catch(e) {}
})();