Pioneer Beach RV Resort — Multi-Page Website
============================================

The original single-file SPA has been split into separate pages,
with the header and footer as shared (reusable) components.

FOLDER STRUCTURE
----------------
/site
  index.html        -> Home
  rv-sites.html     -> RV Sites
  cottages.html     -> Beach Cottages
  amenities.html    -> Amenities & Attractions (About Us)
  events.html       -> Events
  activities.html   -> Activities & Schedule
  gallery.html      -> Gallery
  promotions.html   -> Reservations & Promotions
  faqs.html         -> FAQs
  map.html          -> Resort Map
  find-us.html      -> Find Us
  contact.html      -> Contact
  explore.html      -> Contact Us (Explore)

  /partials
    header.html     -> Shared header: top banner + navbar + mobile nav
    footer.html     -> Shared footer + lightbox + toast (loaded on every page)

  /assets
    styles.css      -> All site styles (shared by every page)
    main.js         -> Shared script: loads the header/footer components,
                       handles page navigation, and all interactive behaviour
                       (gallery filters, lightbox, calendar, testimonials,
                       FAQs, mobile nav, toasts)
    img-data.js     -> Base64 image data used by the Gallery lightbox
                       (only loaded on gallery.html)

HOW THE COMPONENTS WORK
-----------------------
Each page contains two placeholders:

    <div id="site-header"></div>
    ...page content...
    <div id="site-footer"></div>

On load, assets/main.js fetches partials/header.html and
partials/footer.html and injects them. This keeps the header and
footer in ONE place — edit a partial once and every page updates.

Navigation: the old SPA used showPage('x') to show/hide sections.
showPage() is now redefined to navigate to the real page file
(home -> index.html, everything else -> <slug>.html), so all the
existing links and buttons keep working unchanged.

IMPORTANT — RUN VIA A LOCAL SERVER
----------------------------------
Because the header/footer are loaded with fetch(), browsers block
that when you open the file directly (file://). Serve the folder
with any static server, for example:

    python -m http.server          (then open http://localhost:8000)

or use the VS Code "Live Server" extension (Right click index.html
-> "Open with Live Server").

All images are still embedded as base64, so no /images folder or
internet connection is required (only the Google Fonts links).
