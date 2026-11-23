import domReady from '@wordpress/dom-ready';

/**
 * Open the ACF "expanded editor" of a block from a trigger rendered inside
 * the block preview (components/acf/placeholder: [data-acf-expanded-editor]).
 *
 * ACF Pro 6.8 (blocks v3) keeps the expanded editor open state in a local
 * React useState of the block edit component: there is no store, action or
 * public JS API to open it. The only entry points are the "Open Expanded
 * Editor" button in the block inspector and an unlabelled toolbar button, so
 * we open the block sidebar and click the inspector button.
 *
 * The ACF fields panel in the inspector is hidden with a stylesheet added to
 * the parent document (the inspector is not in the canvas iframe, where
 * editor.css is loaded): the fields are edited in the expanded editor only.
 */

/**
 * ACF internal selectors, taken from acf-pro-blocks.min.js of ACF Pro 6.8.12
 * (blocks v3). Check them again when updating ACF: with SCRIPT_DEBUG or in
 * development a missing selector is reported in the console.
 */
const ACF_SELECTORS = {
  // Fields form of the block in the inspector (blocks v2 and v3).
  inspectorPanel: '.block-editor-block-inspector .acf-block-component.acf-block-panel',
  // "Open Expanded Editor" button in the inspector (blocks v3).
  expandedEditorButton: '.block-editor-block-inspector .acf-blocks-open-expanded-editor-btn',
};

const TRIGGER = '[data-acf-expanded-editor]';
const CANVAS = 'iframe[name="editor-canvas"]';

/** How long to wait for the inspector to render the ACF button. */
const BUTTON_TIMEOUT = 2000;

const debug = Boolean(window.sageEditor?.debug);

const warn = (...args) => {
  if (debug) {
    console.warn('[sage/editor]', ...args);
  }
};

/**
 * Open the "Block" tab of the editor sidebar, otherwise the inspector (and the
 * ACF button) is not rendered.
 *
 * Post editor: core/edit-post. Site editor (and any editor without that store):
 * core/interface, scope "core". In WP 7.1 both editors share the sidebar of
 * the editor package, whose block tab is "edit-post/block".
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

  warn('No store to open the block sidebar (core/edit-post, core/interface).');
};

/**
 * Resolve with the first element matching the selector in the parent document,
 * polling once per frame, or with null after the timeout.
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
 * Click on the trigger inside a block -> open the ACF expanded editor.
 */
const onClick = async (event) => {
  const trigger = event.target.closest?.(TRIGGER);
  if (!trigger) return;

  event.preventDefault();
  // No stopPropagation: the editor must still select the block.

  openBlockSidebar();

  const button = await waitFor(ACF_SELECTORS.expandedEditorButton);

  if (button) {
    // Check before the click: when the expanded editor opens, ACF moves the
    // fields form from the inspector to the modal and the panel is gone.
    if (!document.querySelector(ACF_SELECTORS.inspectorPanel)) {
      warn('ACF inspector panel not found, it is not hidden:', ACF_SELECTORS.inspectorPanel);
    }

    button.click();
  } else {
    warn('ACF expanded editor button not found:', ACF_SELECTORS.expandedEditorButton);
  }
};

/**
 * Hide the ACF fields panel of the inspector, once, with CSS.
 */
const hideInspectorPanel = () => {
  const style = document.createElement('style');
  style.id = 'sage-editor-acf-panel';
  style.textContent = `${ACF_SELECTORS.inspectorPanel} { display: none !important; }`;
  document.head.append(style);
};

/**
 * Documents with the click listener. WeakSet: replaced iframe documents are
 * garbage collected.
 */
const boundDocs = new WeakSet();

const bindDoc = (doc) => {
  if (!doc || boundDocs.has(doc)) return;
  boundDocs.add(doc);
  doc.addEventListener('click', onClick, true);
};

/**
 * Bind the canvas iframe: its current document and, on every load, the new
 * one (the document is replaced when the iframe content is reloaded).
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
 * Look for the canvas iframe at most once per frame. The iframe is recreated
 * (code editor -> visual editor switch, navigation in the site editor), so
 * this runs on store changes; the work is a single querySelector plus WeakSet
 * lookups.
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
 * The pointer (or focus) has to enter the iframe before anything inside it
 * can be clicked, and the parent document sees that event on the iframe
 * element: bind it there too, so a recreated iframe is always bound in time
 * even without a store change after its creation.
 */
const onEnterCanvas = (event) => {
  if (event.target instanceof HTMLIFrameElement && event.target.matches(CANVAS)) {
    bindIframe(event.target);
  }
};

domReady(() => {
  hideInspectorPanel();

  // Fallback for an editor canvas that is not in an iframe.
  bindDoc(document);

  document.addEventListener('pointerover', onEnterCanvas, true);
  document.addEventListener('focusin', onEnterCanvas, true);

  bindIframe(document.querySelector(CANVAS));
  window.wp?.data?.subscribe(scheduleCanvasCheck);
});
