/**
 * Condivisione nativa (Web Share API) per components/share.
 *
 * Il pulsante è nascosto di default: viene mostrato solo se il browser
 * supporta navigator.share, altrimenti restano i soli link social.
 */
if (typeof navigator.share === 'function') {
  document.querySelectorAll('[data-share]').forEach((item) => {
    const button = item.querySelector('button');

    if (!button) {
      return;
    }

    item.hidden = false;

    button.addEventListener('click', () => {
      navigator
        .share({ title: button.dataset.shareTitle, url: button.dataset.shareUrl })
        .catch((error) => {
          // L'annullamento da parte dell'utente non è un errore.
          if (error?.name !== 'AbortError') {
            console.warn(error);
          }
        });
    });
  });
}
