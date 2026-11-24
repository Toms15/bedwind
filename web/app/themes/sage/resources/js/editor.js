import domReady from '@wordpress/dom-ready';

/**
 * Apre l'"expanded editor" ACF di un blocco da un trigger renderizzato dentro
 * l'anteprima del blocco (components/acf/placeholder: [data-acf-expanded-editor]).
 *
 * ACF Pro 6.8 (blocks v3) tiene lo stato di apertura dell'expanded editor in uno
 * useState React locale del componente di modifica del blocco: non c'è uno store,
 * un'azione o un'API JS pubblica per aprirlo. Gli unici punti d'ingresso sono il
 * pulsante "Open Expanded Editor" nell'inspector del blocco e un pulsante della
 * toolbar senza etichetta, quindi si apre la sidebar del blocco e si clicca il
 * pulsante dell'inspector.
 *
 * Il pannello dei campi ACF nell'inspector è nascosto con un foglio di stile
 * aggiunto al documento padre (l'inspector non sta nell'iframe del canvas, dove
 * è caricato editor.css): i campi si modificano solo nell'expanded editor.
 */

/**
 * Selettori interni di ACF, presi da acf-pro-blocks.min.js di ACF Pro 6.8.12
 * (blocks v3). Vanno ricontrollati quando si aggiorna ACF: con SCRIPT_DEBUG o
 * in development un selettore mancante viene segnalato in console.
 */
const ACF_SELECTORS = {
  // Form dei campi del blocco nell'inspector (blocks v2 e v3).
  inspectorPanel: '.block-editor-block-inspector .acf-block-component.acf-block-panel',
  // Pulsante "Open Expanded Editor" nell'inspector (blocks v3).
  expandedEditorButton: '.block-editor-block-inspector .acf-blocks-open-expanded-editor-btn',
};

const TRIGGER = '[data-acf-expanded-editor]';
const CANVAS = 'iframe[name="editor-canvas"]';

/** Quanto attendere che l'inspector renderizzi il pulsante ACF. */
const BUTTON_TIMEOUT = 2000;

const debug = Boolean(window.sageEditor?.debug);

const warn = (...args) => {
  if (debug) {
    console.warn('[sage/editor]', ...args);
  }
};

/**
 * Apre la scheda "Blocco" della sidebar dell'editor, altrimenti l'inspector (e il
 * pulsante ACF) non viene renderizzato.
 *
 * Post editor: core/edit-post. Site editor (e qualsiasi editor senza quello store):
 * core/interface, scope "core". In WP 7.1 entrambi gli editor condividono la
 * sidebar del pacchetto editor, la cui scheda del blocco è "edit-post/block".
 */
const openBlockSidebar = () => {
  const data = window.wp?.data;
  if (!data) return;

  const editPost = data.select('core/edit-post') ? data.dispatch('core/edit-post') : null;

  if (editPost?.openGeneralSidebar) {
    editPost.openGeneralSidebar('edit-post/block');
    return;
  }

  const editorInterface = data.select('core/interface') ? data.dispatch('core/interface') : null;

  if (editorInterface?.enableComplementaryArea) {
    editorInterface.enableComplementaryArea('core', 'edit-post/block');
    return;
  }

  warn('Nessuno store per aprire la sidebar del blocco (core/edit-post, core/interface).');
};

/**
 * Risolve con il primo elemento che corrisponde al selettore nel documento padre,
 * controllando una volta per frame, o con null dopo il timeout.
 */
const waitFor = (selector, timeout = BUTTON_TIMEOUT) =>
  new Promise((resolve) => {
    const start = performance.now();

    const check = () => {
      const el = document.querySelector(selector);

      if (el) {
        resolve(el);
      } else if (performance.now() - start >= timeout) {
        resolve(null);
      } else {
        requestAnimationFrame(check);
      }
    };

    check();
  });

/**
 * Clic sul trigger dentro un blocco -> apre l'expanded editor ACF.
 */
const onClick = async (event) => {
  const trigger = event.target.closest?.(TRIGGER);
  if (!trigger) return;

  event.preventDefault();
  // Niente stopPropagation: l'editor deve comunque selezionare il blocco.

  openBlockSidebar();

  const button = await waitFor(ACF_SELECTORS.expandedEditorButton);

  if (button) {
    // Controllo prima del clic: quando l'expanded editor si apre, ACF sposta il
    // form dei campi dall'inspector alla modale e il pannello non c'è più.
    if (!document.querySelector(ACF_SELECTORS.inspectorPanel)) {
      warn(
        "Pannello ACF dell'inspector non trovato, non viene nascosto:",
        ACF_SELECTORS.inspectorPanel,
      );
    }

    button.click();
  } else {
    warn("Pulsante dell'expanded editor ACF non trovato:", ACF_SELECTORS.expandedEditorButton);
  }
};

/**
 * Nasconde con il CSS, una volta sola, il pannello dei campi ACF dell'inspector.
 */
const hideInspectorPanel = () => {
  const style = document.createElement('style');
  style.id = 'sage-editor-acf-panel';
  style.textContent = `${ACF_SELECTORS.inspectorPanel} { display: none !important; }`;
  document.head.append(style);
};

/**
 * Documenti con il listener del clic. WeakSet: i documenti degli iframe
 * sostituiti vengono liberati dal garbage collector.
 */
const boundDocs = new WeakSet();

const bindDoc = (doc) => {
  if (!doc || boundDocs.has(doc)) return;
  boundDocs.add(doc);
  doc.addEventListener('click', onClick, true);
};

/**
 * Collega l'iframe del canvas: il suo documento corrente e, a ogni load, quello
 * nuovo (il documento viene sostituito quando il contenuto dell'iframe si ricarica).
 */
const boundIframes = new WeakSet();

const bindIframe = (iframe) => {
  if (!(iframe instanceof HTMLIFrameElement)) return;

  bindDoc(iframe.contentDocument);

  if (boundIframes.has(iframe)) return;
  boundIframes.add(iframe);
  iframe.addEventListener('load', () => bindDoc(iframe.contentDocument));
};

/**
 * Cerca l'iframe del canvas al massimo una volta per frame. L'iframe viene
 * ricreato (passaggio da editor di codice a editor visuale, navigazione nel site
 * editor), quindi questo gira sui cambi dello store; il lavoro è un solo
 * querySelector più qualche lookup nei WeakSet.
 */
let checkScheduled = false;

const scheduleCanvasCheck = () => {
  if (checkScheduled) return;
  checkScheduled = true;

  requestAnimationFrame(() => {
    checkScheduled = false;
    bindIframe(document.querySelector(CANVAS));
  });
};

/**
 * Il puntatore (o il focus) deve entrare nell'iframe prima che si possa
 * cliccare qualcosa al suo interno, e il documento padre vede quell'evento
 * sull'elemento iframe: si collega anche lì, così un iframe ricreato è sempre
 * collegato in tempo anche senza un cambio dello store dopo la sua creazione.
 */
const onEnterCanvas = (event) => {
  if (event.target instanceof HTMLIFrameElement && event.target.matches(CANVAS)) {
    bindIframe(event.target);
  }
};

domReady(() => {
  hideInspectorPanel();

  // Fallback per un canvas dell'editor che non sta in un iframe.
  bindDoc(document);

  document.addEventListener('pointerover', onEnterCanvas, true);
  document.addEventListener('focusin', onEnterCanvas, true);

  bindIframe(document.querySelector(CANVAS));
  window.wp?.data?.subscribe(scheduleCanvasCheck);
});
