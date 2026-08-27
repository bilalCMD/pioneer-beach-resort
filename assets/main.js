/* ============================================================
   Pioneer Beach RV Resort — shared site script
   - Loads the header & footer components into every page
   - Handles navigation between the separate page files
   - Keeps all original interactive behaviour (gallery, lightbox,
     calendar, testimonials, faqs, mobile nav, toasts)
   NOTE: components are loaded with fetch(), so run the site
   through a local server (e.g. VS Code "Live Server" or
   `python -m http.server`) rather than opening the file directly.
   ============================================================ */

/* Map a page slug to its HTML file. 'home' is index.html. */
/* Bump on deploy so browsers pick up new partials instead of a cached copy. */
const ASSET_V='20260827a';

function pageUrl(p){ return (p === 'home' || !p) ? 'index.html' : p + '.html'; }

/* Navigate to a real page. Replaces the old SPA show/hide logic
   so every existing onclick="showPage('x')" now just works. */
function showPage(p){ window.location.href = pageUrl(p); }

/* Inject a shared component (header/footer) into a placeholder. */
async function loadComponent(id, url){
  const host = document.getElementById(id);
  if(!host) return;
  try{
    const res = await fetch(url);
    if(!res.ok) throw new Error(res.status);
    host.innerHTML = await res.text();
    highlightActiveNav();
    syncHeaderOffset();
    if(window.iconify) window.iconify(host);
  }catch(err){
    console.warn('Component "' + url + '" could not be loaded. ' +
      'Serve the site through a local server (Live Server / python -m http.server).', err);
  }
}

/* The header partial renders an in-flow banner above the fixed navbar, so a
   100vh hero always hung that many pixels below the fold (and took the
   "Scroll to Explore" cue with it). Publish the banner's real height as a
   custom property and let the hero subtract it. */
function syncHeaderOffset(){
  const host = document.getElementById('site-header');
  if(!host) return;
  const h = Math.round(host.getBoundingClientRect().height);
  document.documentElement.style.setProperty('--hdr-static', h + 'px');
}

/* Add an "active" state to the nav item matching the current page. */
function highlightActiveNav(){
  const here = (location.pathname.split('/').pop() || 'index.html').toLowerCase();
  document.querySelectorAll('#navbar a, .mobile-nav a').forEach(a=>{
    const oc = a.getAttribute('onclick') || '';
    const m = oc.match(/showPage\('([^']*)'\)/);
    if(m && pageUrl(m[1]).toLowerCase() === here) a.classList.add('current');
  });
}

function toggleMobileNav(){document.getElementById('mobileNav').classList.toggle('open')}
function closeMobileNav(){document.getElementById('mobileNav').classList.remove('open')}
function toggleFaq(e){e.parentElement.classList.toggle('open')}
function filterGallery(c,b){document.querySelectorAll('.filter-btn').forEach(x=>x.classList.remove('active'));b.classList.add('active');const items=document.querySelectorAll('.gallery-masonry .gallery-item');let n=0;items.forEach(i=>{const show=(c==='all'||i.dataset.cat===c);if(show){i.style.display='block';i.classList.remove('in');setTimeout(()=>i.classList.add('in'),20+n*45);n++}else{i.classList.remove('in');i.style.display='none'}})}
function revealGallery(){const items=document.querySelectorAll('.gallery-masonry .gallery-item');items.forEach((i,n)=>{i.classList.remove('in');if(i.style.display!=='none')setTimeout(()=>i.classList.add('in'),60+n*55)})}

const lbSrc={img1:'stpat',img2:'boardwalk_beach',img3:'kite',img4:'park_pond',img5:'egrets',img6:'sunset_wide',img7:'sunset_ships',img8:'potluck',img9:'aerial',img10:'moorhen',img11:'park_gulf',img12:'park_dusk'};
function openLightbox(k,c){document.getElementById('lightboxImg').src=(window.IMG&&IMG[lbSrc[k]])||'';document.getElementById('lightboxCaption').textContent=c||'';document.getElementById('lightbox').classList.add('open')}
function closeLightbox(e){if(e.target.id==='lightbox'||e.target.classList.contains('lightbox-close'))document.getElementById('lightbox').classList.remove('open')}
document.addEventListener('keydown',e=>{if(e.key==='Escape')document.getElementById('lightbox').classList.remove('open')});

function switchExplore(t,b){document.querySelectorAll('.explore-tab').forEach(x=>x.classList.remove('active'));document.querySelectorAll('.explore-content-panel').forEach(x=>x.classList.remove('active'));b.classList.add('active');document.getElementById('explore-'+t).classList.add('active')}

let calDate=new Date(2026,3,1);const calEv=[7,12,18,19,25,26];
function renderCalendar(){const g=document.getElementById('calGrid'),l=document.getElementById('calMonthLabel');if(!g||!l)return;const m=['January','February','March','April','May','June','July','August','September','October','November','December'];l.textContent=m[calDate.getMonth()]+' '+calDate.getFullYear();const f=new Date(calDate.getFullYear(),calDate.getMonth(),1).getDay(),d=new Date(calDate.getFullYear(),calDate.getMonth()+1,0).getDate(),t=new Date();let h=['S','M','T','W','T','F','S'].map(x=>'<div class="cal-day-header">'+x+'</div>').join('');for(let i=0;i<f;i++)h+='<div class="cal-day empty"></div>';for(let i=1;i<=d;i++){const isT=i===t.getDate()&&calDate.getMonth()===t.getMonth()&&calDate.getFullYear()===t.getFullYear();h+='<div class="cal-day'+(isT?' today':'')+(calEv.includes(i)?' has-event':'')+'">'+i+'</div>'}g.innerHTML=h}
function changeMonth(d){calDate.setMonth(calDate.getMonth()+d);renderCalendar()}
function showToast(m){const t=document.getElementById('toast');t.textContent=m;t.classList.add('show');setTimeout(()=>t.classList.remove('show'),4000)}

// TESTIMONIAL SLIDER
let testiPos=0;
function slideTestimonials(dir){const slider=document.getElementById('testiSlider');if(!slider)return;const cards=slider.querySelectorAll('.testi-card');const cardW=cards[0].offsetWidth+parseFloat(getComputedStyle(slider).gap||24);const visible=Math.floor(slider.parentElement.offsetWidth/cardW);const maxPos=Math.max(0,cards.length-visible);testiPos=Math.max(0,Math.min(testiPos+dir,maxPos));slider.style.transform='translateX(-'+(testiPos*cardW)+'px)';cards.forEach((c,i)=>c.classList.toggle('active',i===testiPos))}

function initFadeUps(){const o=new IntersectionObserver(e=>{e.forEach(x=>{if(x.isIntersecting){x.target.classList.add('visible');o.unobserve(x.target)}})},{threshold:.08,rootMargin:'0px 0px -40px 0px'});document.querySelectorAll('.fade-up:not(.visible),.stagger:not(.visible),.reveal-scale:not(.visible)').forEach(e=>o.observe(e))}

/* Scroll-driven extras: progress bar, navbar depth, back-to-top button. */
function initScrollFx(){
  // Inject the elements once.
  if(!document.getElementById('scrollProgress')){
    const bar = document.createElement('div'); bar.id = 'scrollProgress';
    document.body.appendChild(bar);
  }
  if(!document.getElementById('backToTop')){
    const btn = document.createElement('button');
    btn.id = 'backToTop'; btn.type = 'button';
    btn.setAttribute('aria-label','Back to top');
    btn.innerHTML = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5"/><path d="m5 12 7-7 7 7"/></svg>';
    btn.addEventListener('click', ()=>window.scrollTo({top:0,behavior:'smooth'}));
    document.body.appendChild(btn);
  }
  const bar = document.getElementById('scrollProgress');
  const btn = document.getElementById('backToTop');
  let ticking = false;
  function onScroll(){
    if(ticking) return; ticking = true;
    requestAnimationFrame(()=>{
      const y = window.scrollY || document.documentElement.scrollTop;
      const h = document.documentElement.scrollHeight - window.innerHeight;
      bar.style.width = (h > 0 ? (y / h) * 100 : 0) + '%';
      const nav = document.getElementById('navbar');
      if(nav) nav.classList.toggle('scrolled', y > 10);
      btn.classList.toggle('show', y > 600);
      ticking = false;
    });
  }
  window.addEventListener('resize', syncHeaderOffset, {passive:true});
  window.addEventListener('scroll', onScroll, {passive:true});
  onScroll();
}

/* Startup: load shared components, then original page init. */
document.addEventListener('DOMContentLoaded', ()=>{
  loadComponent('site-header', 'partials/header.html?v='+ASSET_V);
  loadComponent('site-footer', 'partials/footer.html?v='+ASSET_V);
  if(window.iconify) window.iconify(document.body); // swap emoji -> SVG in page content
  renderCalendar();
  initFadeUps();
  initScrollFx();
  // The old SPA revealed the gallery when you navigated to it; with
  // separate pages we trigger it here whenever the gallery is present.
  if(document.querySelector('.gallery-masonry')) setTimeout(revealGallery, 200);
});
