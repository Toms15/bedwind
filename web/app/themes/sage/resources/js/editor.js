import domReady from '@wordpress/dom-ready';

const PANEL =
  '.block-editor-block-inspector .acf-block-component.acf-block-panel';

const INSPECTOR_BTN =
  '.block-editor-block-inspector .acf-blocks-open-expanded-editor-btn';

const TRIGGER = '[data-acf-expanded-editor]';

/**
 * Nasconde il pannello ACF nell'inspector (documento padre).
 */
const hideAcfBlockPanel = () => {
  document.querySelectorAll(PANEL).forEach((panel) => {
    if (panel.style.display !== 'none') {
      panel.style.display = 'none';
    }
  });
};

/**
 * Accorpa le chiamate a hideAcfBlockPanel in un solo frame.
 */
let hideScheduled = false;
const scheduleHide = () => {
  if (hideScheduled) return;
  hideScheduled = true;
  requestAnimationFrame(() => {
    hideScheduled = false;
    hideAcfBlockPanel();
  });
};

/**
 * Aspetta che un elemento compaia nel documento padre.
 */
const waitFor = (selector, timeout = 2000) =>
  new Promise((resolve) => {
    const existing = document.querySelector(selector);
    if (existing) {
      resolve(existing);
      return;
    }

    let timer;
    const obs = new MutationObserver(() => {
      const el = document.querySelector(selector);
      if (el) {
        obs.disconnect();
        clearTimeout(timer);
        resolve(el);
      }
    });

    obs.observe(document.body, { childList: true, subtree: true });

    timer = setTimeout(() => {
      obs.disconnect();
      resolve(null);
    }, timeout);
  });

/**
 * Apre la sidebar "Blocco", altrimenti l'inspector non viene renderizzato.
 */
const openBlockSidebar = () => {
  try {
    window.wp?.data
      ?.dispatch('core/edit-post')
      ?.openGeneralSidebar('edit-post/block');
  } catch (e) {
    // Store non disponibile (es. site editor): ignora
  }
};

/**
 * Click sul trigger dentro il blocco -> apre l'expanded editor ACF.
 */
const onClick = async (event) => {
  const trigger = event.target.closest?.(TRIGGER);
  if (!trigger) return;

  event.preventDefault();
  // Niente stopPropagation: Gutenberg deve selezionare il blocco.

  openBlockSidebar();

  const expandedEditorButton = await waitFor(INSPECTOR_BTN);

  if (expandedEditorButton) {
    expandedEditorButton.click();
  } else {
    console.warn('[ACF expanded editor] Bottone non trovato:', INSPECTOR_BTN);
  }
};

/**
 * Lega il listener a un document una sola volta.
 */
const bindDoc = (doc) => {
  if (!doc || doc.__acfExpandedBound) return;
  doc.__acfExpandedBound = true;
  doc.addEventListener('click', onClick, true);
};

/**
 * Lega il listener al documento padre e al canvas in iframe.
 */
const bindCanvas = () => {
  // Fallback per editor non in iframe
  bindDoc(document);

  const iframe = document.querySelector('iframe[name="editor-canvas"]');
  if (!iframe || iframe.__acfBound) return;
  iframe.__acfBound = true;

  const attach = () => bindDoc(iframe.contentDocument);
  attach();

  // Al load il contentDocument viene sostituito: ri-lega
  iframe.addEventListener('load', attach);
};

domReady(() => {
  hideAcfBlockPanel();
  bindCanvas();

  // Pannello ri-renderizzato e iframe ricreato (cambio device preview, ecc.)
  new MutationObserver(() => {
    scheduleHide();
    bindCanvas();
  }).observe(document.body, { childList: true, subtree: true });
});
